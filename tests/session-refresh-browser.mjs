// Verifies real session-token rotation without creating clinic records.
import fs from 'node:fs/promises';
const tabs=await(await fetch('http://127.0.0.1:9223/json')).json();
const socket=new WebSocket(tabs.find(t=>t.type==='page').webSocketDebuggerUrl);
await new Promise(r=>socket.addEventListener('open',r,{once:true}));
let next=1;const jobs=new Map();
socket.addEventListener('message',e=>{const m=JSON.parse(e.data);if(m.id){const j=jobs.get(m.id);jobs.delete(m.id);m.error?j.reject(Error(m.error.message)):j.resolve(m.result)}});
function send(method,params={}){return new Promise((resolve,reject)=>{const id=next++;jobs.set(id,{resolve,reject});socket.send(JSON.stringify({id,method,params}))})}
async function evaluate(expression){const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value}
function assert(ok,message){if(!ok)throw Error(message);console.log('PASS:',message)}
await send('Page.enable');await send('Page.navigate',{url:'http://127.0.0.1:5173/login'});
for(let i=0;i<100;i++){if(await evaluate("document.readyState==='complete'&&!!document.querySelector('h1')"))break;await new Promise(r=>setTimeout(r,100))}
const access=await fs.readFile('.local/access.txt','utf8');const credentials={email:access.match(/^Email: (.+)$/m)[1],password:access.match(/^Password: (.+)$/m)[1]};
const result=await evaluate(`(async()=>{const {api}=await import('/src/lib.ts');const credentials=${JSON.stringify(credentials)};await api('/auth/login','POST',credentials);let afterLogin=0;try{await api('/admin/banner-images','POST',new FormData())}catch(e){afterLogin=e.status}const csrf=await(await fetch('/api/auth/csrf')).json();const rotated=await fetch('/api/auth/login',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf.token},body:JSON.stringify(credentials)});const nativeFetch=window.fetch;const attempts=[];window.fetch=async(...args)=>{const response=await nativeFetch(...args);if(String(args[0]).endsWith('/admin/banner-images'))attempts.push(response.status);return response};let recovered=0;try{await api('/admin/banner-images','POST',new FormData())}catch(e){recovered=e.status}finally{window.fetch=nativeFetch}await api('/auth/logout','POST');let afterLogout=0;try{await api('/public/enquiries','POST',{})}catch(e){afterLogout=e.status}return{afterLogin,rotated:rotated.status,recovered,attempts,afterLogout}})()`);
assert(result.afterLogin===422,'Saving immediately after login reaches validation without requiring refresh');
assert(result.rotated===200&&result.recovered===422&&result.attempts.join(',')==='419,422','An expired security token is refreshed and the rejected request retried exactly once');
assert(result.afterLogout===422,'Public forms receive a fresh token after logout');socket.close();
