// Read-only gallery fixtures. No clinic records are changed.
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

const injection=await send('Page.addScriptToEvaluateOnNewDocument',{source:`const nativeFetch=window.fetch;window.fetch=async(...args)=>{const response=await nativeFetch(...args);const url=String(args[0]);if(url.endsWith('/api/public/clinic')||url.endsWith('/api/admin/media')){const media=[{id:99991,path:'/brand-logo.jpg',alt:'Clinic photo one',caption:'Our clinic',published:true},{id:99992,path:'/brand-logo.jpg',alt:'Clinic photo two',caption:'Welcome to Nav Jeevan',published:false}];const data=await response.json();return new Response(JSON.stringify(url.endsWith('/api/admin/media')?media:{...data,media:media.map(m=>({...m,published:true}))}),{status:response.status,headers:response.headers})}return response};`});
await send('Page.navigate',{url:'http://127.0.0.1:8000/gallery'});
await until("document.querySelectorAll('.gallery-open').length===2");
await evaluate("document.querySelector('.gallery-open').click()");await until("document.querySelector('dialog')?.open");
check(await evaluate("getComputedStyle(document.querySelector('.viewer-stage img')).objectFit==='contain'"),'Full image fits viewer without cropping');
await send('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowRight',code:'ArrowRight'});
await until("document.querySelector('.viewer-footer p')?.textContent==='Welcome to Nav Jeevan'");console.log('PASS: Keyboard advances image');
await evaluate("document.querySelector('[aria-label=\"Zoom image\"]').click()");check(await evaluate("!!document.querySelector('.is-zoomed')"),'Zoom control works');
await evaluate("document.querySelector('.viewer-thumbnails button').click()");check(await evaluate("!document.querySelector('.is-zoomed')"),'Changing image resets zoom');
await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape'});await until("!document.querySelector('dialog')");await until("document.activeElement===document.querySelector('.gallery-open')");console.log('PASS: Closing restores keyboard focus');
await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
await evaluate("document.querySelector('.gallery-open').click()");await until("document.querySelector('dialog')?.open");check(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Mobile gallery fits screen');
await fs.mkdir(path.join(root,'.local/screenshots'),{recursive:true});const shot=await send('Page.captureScreenshot',{format:'png'});await fs.writeFile(path.join(root,'.local/screenshots/gallery-viewer-mobile.png'),Buffer.from(shot.data,'base64'));
await evaluate("document.querySelector('[aria-label=\"Close image viewer\"]').click()");
const credentials=await fs.readFile(path.join(root,'.local/access.txt'),'utf8');const email=credentials.match(/^Email: (.+)$/m)[1];const password=credentials.match(/^Password: (.+)$/m)[1];
const status=await evaluate(`(async()=>{const csrf=await(await fetch('/api/auth/csrf')).json();return(await fetch('/api/auth/login',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf.token},body:JSON.stringify({email:${JSON.stringify(email)},password:${JSON.stringify(password)}})})).status})()`);check(status===200,'Staff login succeeds');

await send('Page.navigate',{url:'http://127.0.0.1:8000/admin/media'});await until("document.querySelectorAll('.gallery-open').length===2");
await evaluate("document.querySelectorAll('.gallery-open')[1].click()");await until("document.querySelector('dialog')?.open");check(await evaluate("document.querySelector('.viewer-footer p').textContent==='Welcome to Nav Jeevan'"),'Admin drafts open in the same image viewer');
await evaluate("document.querySelector('[aria-label=\"Close image viewer\"]').click()");
await evaluate("(async()=>{const c=await(await fetch('/api/auth/csrf')).json();await fetch('/api/auth/logout',{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':c.token}})})()");
await send('Page.removeScriptToEvaluateOnNewDocument',{identifier:injection.identifier});check(errors.length===0,'No browser runtime exceptions');ws.close();
