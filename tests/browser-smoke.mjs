// Browser checks using installed Chrome's DevTools protocol. No npm dependencies.
import fs from 'node:fs/promises';
import path from 'node:path';
const root=path.resolve(import.meta.dirname,'..');
const targets=await(await fetch('http://127.0.0.1:9223/json')).json();
const target=targets.find(t=>t.type==='page');
const ws=new WebSocket(target.webSocketDebuggerUrl);await new Promise(r=>ws.addEventListener('open',r,{once:true}));
let next=1;const pending=new Map();const errors=[];
ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(m.id){const p=pending.get(m.id);if(p){pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result)}}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text)});
function send(method,params={}){return new Promise((resolve,reject)=>{const id=next++;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}))})}
async function evaluate(expression){const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value}
async function waitFor(expression){for(let i=0;i<100;i++){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,100))}throw Error('Timed out: '+expression)}
async function navigate(url){await send('Page.navigate',{url});await waitFor("document.readyState==='complete' && !!document.querySelector('h1')");await new Promise(r=>setTimeout(r,500))}
function assert(ok,message){if(!ok)throw Error(message);console.log('PASS:',message)}
await send('Page.enable');await send('Runtime.enable');await fs.mkdir(path.join(root,'.local','screenshots'),{recursive:true});
await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
await navigate('http://127.0.0.1:5173');await waitFor("document.body.innerText.includes('General consultation')");
assert(await evaluate("document.querySelector('h1').innerText.includes('Thoughtful care')"),'Homepage displays clinic content');
assert(await evaluate('document.documentElement.scrollWidth <= innerWidth'),'Desktop has no horizontal overflow');
let capture=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});await fs.writeFile(path.join(root,'.local/screenshots/home-desktop.png'),Buffer.from(capture.data,'base64'));
await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
await new Promise(r=>setTimeout(r,300));assert(await evaluate('document.documentElement.scrollWidth <= innerWidth'),'Mobile homepage has no horizontal overflow');
capture=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true});await fs.writeFile(path.join(root,'.local/screenshots/home-mobile.png'),Buffer.from(capture.data,'base64'));
await navigate('http://127.0.0.1:5173/appointment');
assert(await evaluate('document.documentElement.scrollWidth <= innerWidth'),'Mobile booking has no horizontal overflow');
assert(await evaluate("Array.from(document.querySelectorAll('input:not(.honeypot),select,textarea')).every(el=>el.closest('label') || el.getAttribute('aria-label'))"),'Booking fields have accessible labels');
await waitFor("document.querySelector('select')?.options.length>1");
await evaluate("(()=>{const s=document.querySelector('select');s.value=s.options[1].value;s.dispatchEvent(new Event('change',{bubbles:true}))})()");
// Pick a date with availability, avoiding the weekly closure.
await evaluate("(()=>{const input=document.querySelector('input[type=date]');const d=new Date(input.value+'T12:00:00Z');if(d.getUTCDay()===0)d.setUTCDate(d.getUTCDate()+1);const setter=Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set;setter.call(input,d.toISOString().slice(0,10));input.dispatchEvent(new Event('input',{bubbles:true}));input.dispatchEvent(new Event('change',{bubbles:true}))})()");
await waitFor("!!document.querySelector('.slots button')");await evaluate("document.querySelector('.slots button').click();Array.from(document.querySelectorAll('button')).find(b=>b.innerText.includes('Continue')).click()");
await waitFor("!!Array.from(document.querySelectorAll('h2')).find(h=>h.innerText.includes('few details'))");
assert(await evaluate("!!document.querySelector('input[name=name]')"),'Booking advances from available slot to patient details');
await navigate('http://127.0.0.1:5173/login');
const access=await fs.readFile(path.join(root,'.local/access.txt'),'utf8');const email=access.match(/^Email: (.+)$/m)[1];const password=access.match(/^Password: (.+)$/m)[1];
const login=await evaluate(`(async()=>{const c=await(await fetch('/api/auth/csrf')).json();const r=await fetch('/api/auth/login',{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':c.token},body:JSON.stringify({email:${JSON.stringify(email)},password:${JSON.stringify(password)}})});return r.status})()`);assert(login===200,'Staff session login with CSRF succeeds');
await navigate('http://127.0.0.1:5173/admin');await waitFor("document.body.innerText.includes('Today’s schedule')");assert(await evaluate('document.documentElement.scrollWidth <= innerWidth'),'Mobile staff dashboard has no horizontal overflow');
for(const page of ['appointments','services','availability','enquiries','content','banners','redirects','reports','settings','users','notifications','audit']){await navigate('http://127.0.0.1:5173/admin/'+page);await waitFor("!document.querySelector('.state')");assert(!(await evaluate("!!document.querySelector('.error')")),`Staff ${page} loads without API errors`)}
const csrfStatus=await evaluate("(async()=>{const r=await fetch('/api/admin/settings',{method:'PUT',headers:{'Content-Type':'application/json',Accept:'application/json'},body:'{}'});return r.status})()");assert(csrfStatus===419,'Real HTTP requests enforce CSRF');
assert(errors.length===0,'No uncaught browser runtime exceptions');await evaluate("(async()=>{const c=await(await fetch('/api/auth/csrf')).json();await fetch('/api/auth/logout',{method:'POST',headers:{'X-CSRF-TOKEN':c.token,Accept:'application/json'}})})()");
await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
await navigate('http://127.0.0.1:8000');await waitFor("document.body.innerText.includes('General consultation')");
assert(await evaluate("document.querySelector('h1').innerText.includes('Thoughtful care')"),'Production build is served directly by Laravel');
const html=await(await fetch('http://127.0.0.1:8000/about')).text();assert(html.includes('BAMS, DETCT')&&html.includes('Dr. Parmesh Kumar'),'Public HTML includes server-rendered doctor content before JavaScript');
ws.close();console.log('Browser smoke checks complete. Screenshots saved in .local/screenshots.');
