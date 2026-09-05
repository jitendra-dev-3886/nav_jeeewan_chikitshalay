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


const injection=await send('Page.addScriptToEvaluateOnNewDocument',{source:`const nativeFetch=window.fetch;window.fetch=async(...args)=>{const response=await nativeFetch(...args);if(String(args[0]).endsWith('/api/public/content')){return new Response(JSON.stringify([{id:99991,type:'article',slug:'reading-preview',title:'Reading preview',excerpt:'Browser test article',body:'First paragraph.\\n\\nSecond paragraph.',language:'en'},{id:99992,type:'article',slug:'hindi-preview',title:'Hindi preview',excerpt:'Second article',body:'Preview content',language:'hi'}]),{status:200,headers:response.headers})}return response};`});
await send('Page.navigate',{url:'http://127.0.0.1:8000/services'});await until("!!document.querySelector('.care-card')");
check(await evaluate("!!document.querySelector('.care-card-bottom a[href*=\"service=\"]')"),'Service cards link to preselected booking');
await evaluate("(()=>{const el=document.querySelector('input[type=search]');Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(el,'no-matching-service-xyz');el.dispatchEvent(new Event('input',{bubbles:true}))})()");await until("document.querySelectorAll('.care-card').length===0");console.log('PASS: Services search filters results');
await send('Page.navigate',{url:'http://127.0.0.1:8000/articles'});await until("document.querySelectorAll('.journal-card').length===2");
await evaluate("(()=>{const el=document.querySelector('.journal-language select');el.value='hi';el.dispatchEvent(new Event('change',{bubbles:true}))})()");await until("document.querySelectorAll('.journal-card').length===1");check(await evaluate("document.querySelector('.journal-card').lang==='hi'"),'Article language filter works');
await send('Page.navigate',{url:'http://127.0.0.1:8000/articles/reading-preview'});await until("document.querySelector('.journal-reading h1')?.textContent==='Reading preview'");check(await evaluate("document.querySelectorAll('.reading-text p').length===2"),'Article paragraphs render as readable text');
await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});check(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Article detail fits mobile');
await send('Page.navigate',{url:'http://127.0.0.1:8000/services'});await until("!!document.querySelector('.care-card')");check(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Service cards fit mobile');
await fs.mkdir(path.join(root,'.local/screenshots'),{recursive:true});const shot=await send('Page.captureScreenshot',{format:'png'});await fs.writeFile(path.join(root,'.local/screenshots/services-mobile.png'),Buffer.from(shot.data,'base64'));
await send('Page.removeScriptToEvaluateOnNewDocument',{identifier:injection.identifier});check(errors.length===0,'No browser runtime exceptions');ws.close();
