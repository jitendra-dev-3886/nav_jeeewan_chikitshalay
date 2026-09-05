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


await send('Page.navigate',{url:'http://127.0.0.1:8000/'});await until("!!document.querySelector('.site-header')");
const credentials=await fs.readFile(path.join(root,'.local/access.txt'),'utf8');const email=credentials.match(/^Email: (.+)$/m)[1];const password=credentials.match(/^Password: (.+)$/m)[1];
const status=await evaluate(`(async()=>{const csrf=await(await fetch('/api/auth/csrf')).json();return(await fetch('/api/auth/login',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf.token},body:JSON.stringify({email:${JSON.stringify(email)},password:${JSON.stringify(password)}})})).status})()`);check(status===200,'Staff login succeeds');


for(const resource of ['services','content']){
await send('Page.navigate',{url:`http://127.0.0.1:8000/admin/${resource}`});await until("Array.from(document.querySelectorAll('button')).some(b=>b.textContent.includes('Add new'))");
await evaluate("Array.from(document.querySelectorAll('button')).find(b=>b.textContent.includes('Add new')).click()");await until("!!document.querySelector('.cover-image-field input[type=file]')");
const doc=await send('DOM.getDocument');const input=await send('DOM.querySelector',{nodeId:doc.root.nodeId,selector:'.cover-image-field input[type=file]'});await send('DOM.setFileInputFiles',{nodeId:input.nodeId,files:[path.join(root,'logo.jpg')]});await until("document.querySelector('.cover-upload-preview')?.naturalWidth>0");console.log('PASS: '+resource+' image upload preview');
await evaluate("document.querySelector('.cover-image-field button').click()");await until("!document.querySelector('.cover-upload-preview')");check(await evaluate("document.querySelector('.cover-image-field input[type=file]').value===''"),resource+' removal clears file selection');
await evaluate("document.querySelector('[aria-label=\"Close editor\"]').click()");
}
await evaluate("(async()=>{const c=await(await fetch('/api/auth/csrf')).json();await fetch('/api/auth/logout',{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':c.token}})})()");check(errors.length===0,'No browser runtime exceptions');ws.close();
