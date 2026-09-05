// Uses browser-only public fixtures and an unsaved admin draft. No clinic records are changed.
import fs from 'node:fs/promises';
import path from 'node:path';
const root=path.resolve(import.meta.dirname,'..');
const targets=await(await fetch('http://127.0.0.1:9223/json')).json();
const ws=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
let next=1;const jobs=new Map();const errors=[];
ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(m.id){const job=jobs.get(m.id);jobs.delete(m.id);m.error?job.reject(Error(m.error.message)):job.resolve(m.result)}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text)});
function send(method,params={}){return new Promise((resolve,reject)=>{const id=next++;jobs.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}))})}
async function evaluate(expression){const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value}
const delay=ms=>new Promise(r=>setTimeout(r,ms));
async function until(expression){for(let i=0;i<100;i++){if(await evaluate(expression))return;await delay(100)}throw Error('Timeout: '+expression)}
function check(ok,message){if(!ok)throw Error(message);console.log('PASS:',message)}
await send('Page.enable');await send('Runtime.enable');
const injection=await send('Page.addScriptToEvaluateOnNewDocument',{source:`const nativeFetch=window.fetch;window.fetch=async(...args)=>{const response=await nativeFetch(...args);if(String(args[0]).endsWith('/api/public/clinic')&&new URLSearchParams(location.search).has('poster-test')){const data=await response.json();data.banners=[{id:9999,title:sessionStorage.getItem('poster-title')||'Clinic poster',body:'Complete artwork without cropping',kind:'poster',image_url:'/brand-logo.jpg',image_alt:'Clinic poster image',link_path:'/appointment',link_label:'Book a visit'}];return new Response(JSON.stringify(data),{status:response.status,headers:response.headers})}return response};`});
await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
await send('Page.navigate',{url:'http://127.0.0.1:8000/?poster-test=1'});
await until("document.querySelector('.banner-artwork img')?.naturalWidth>0");
check(await evaluate("getComputedStyle(document.querySelector('.banner-artwork img')).objectFit==='contain'"),'Posters use complete-image display without cropping');
check(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Poster layout fits mobile width');
await send('Page.reload',{ignoreCache:true});await until("document.querySelector('.banner-artwork img')?.naturalWidth>0");console.log('PASS: Poster loads correctly after a hard refresh');
await evaluate("window.scrollTo(0,700);sessionStorage.setItem('poster-title','Updated clinic poster');window.dispatchEvent(new StorageEvent('storage',{key:'clinic:updated',newValue:String(Date.now())}))");
await until("document.querySelector('.banner-copy h2')?.textContent==='Updated clinic poster'");
check(await evaluate('window.scrollY>600'),'Live updates refresh banners without jumping the page to the top');
await evaluate('window.scrollTo(0,0)');await delay(800);await fs.mkdir(path.join(root,'.local/screenshots'),{recursive:true});const screenshot=await send('Page.captureScreenshot',{format:'png'});await fs.writeFile(path.join(root,'.local/screenshots/poster-mobile.png'),Buffer.from(screenshot.data,'base64'));
const credentials=await fs.readFile(path.join(root,'.local/access.txt'),'utf8');const email=credentials.match(/^Email: (.+)$/m)[1];const password=credentials.match(/^Password: (.+)$/m)[1];
const status=await evaluate(`(async()=>{const csrf=await(await fetch('/api/auth/csrf')).json();return(await fetch('/api/auth/login',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf.token},body:JSON.stringify({email:${JSON.stringify(email)},password:${JSON.stringify(password)}})})).status})()`);check(status===200,'Staff login succeeds');
await send('Page.navigate',{url:'http://127.0.0.1:8000/admin/banners'});await until("document.querySelector('h1')?.textContent==='Banners & posters'");
await send('Page.reload',{ignoreCache:true});await until("document.querySelector('h1')?.textContent==='Banners & posters'");console.log('PASS: Staff banner page survives direct URL refresh');
await evaluate("Array.from(document.querySelectorAll('button')).find(b=>b.textContent.includes('Add banner / poster')).click()");await until("!!document.querySelector('.banner-manager-dialog')");
await evaluate("(()=>{const select=document.querySelector('.banner-manager-dialog select');select.value='poster';select.dispatchEvent(new Event('change',{bubbles:true}))})()");await until("!!document.querySelector('.banner-manager-dialog input[type=file]')");
const document=await send('DOM.getDocument');const input=await send('DOM.querySelector',{nodeId:document.root.nodeId,selector:'.banner-manager-dialog input[type=file]'});await send('DOM.setFileInputFiles',{nodeId:input.nodeId,files:[path.join(root,'logo.jpg')]});
await until("document.querySelector('.banner-upload-preview')?.naturalWidth>0");console.log('PASS: Image upload shows a local preview before saving');
await evaluate("Array.from(document.querySelectorAll('.banner-manager-dialog button')).find(b=>b.textContent==='Preview').click()");await until("document.querySelector('.banner-editor-preview .banner-artwork img')?.naturalWidth>0");console.log('PASS: Poster carousel preview works in the editor');
await evaluate("document.querySelector('[aria-label=\"Close banner editor\"]').click();sessionStorage.removeItem('poster-title')");
await evaluate("(async()=>{const c=await(await fetch('/api/auth/csrf')).json();await fetch('/api/auth/logout',{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':c.token}})})()");
await send('Page.removeScriptToEvaluateOnNewDocument',{identifier:injection.identifier});check(errors.length===0,'No browser runtime exceptions');ws.close();
