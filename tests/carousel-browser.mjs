// Public banner fixtures exist only in this browser; clinic data is never changed.
import fs from 'node:fs/promises';
const targets=await(await fetch('http://127.0.0.1:9223/json')).json();
const ws=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);
await new Promise(r=>ws.addEventListener('open',r,{once:true}));
let serial=1;const pending=new Map();const errors=[];
ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(m.id){const p=pending.get(m.id);pending.delete(m.id);m.error?p.reject(Error(m.error.message)):p.resolve(m.result)}if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text)});
function send(method,params={}){return new Promise((resolve,reject)=>{const id=serial++;pending.set(id,{resolve,reject});ws.send(JSON.stringify({id,method,params}))})}
async function evaluate(expression){const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value}
const delay=ms=>new Promise(r=>setTimeout(r,ms));
function check(condition,label){if(!condition)throw Error(label);console.log('PASS:',label)}
async function until(expression){for(let i=0;i<100;i++){if(await evaluate(expression))return;await delay(100)}throw Error('Timeout: '+expression)}
await send('Page.enable');await send('Runtime.enable');
const injection=await send('Page.addScriptToEvaluateOnNewDocument',{source:`const originalFetch=window.fetch;window.fetch=async(...args)=>{const response=await originalFetch(...args);if(String(args[0]).endsWith('/api/public/clinic')){const data=await response.json();const count=Number(new URLSearchParams(location.search).get('test-banners')||0);data.banners=Array.from({length:count},(_,i)=>({id:i+1,title:'Clinic announcement '+(i+1),body:'Plan your next visit with Nav Jeevan Chikitsalay. Choose a convenient appointment time online.',link_label:'Book a visit',link_path:'/appointment'}));data.branding={logo_url:'/brand-logo.jpg?carousel-test',custom:true};return new Response(JSON.stringify(data),{status:response.status,headers:response.headers})}return response};`});
async function page(count){await send('Page.navigate',{url:'http://127.0.0.1:8000/?test-banners='+count});await until("document.querySelector('.brand-logo')?.getAttribute('src').includes('carousel-test')");if(count)await until("!!document.querySelector('.banner-track')");await send('Input.dispatchMouseEvent',{type:'mouseMoved',x:1,y:1});}
const position=()=>evaluate("parseFloat(document.querySelector('.banner-track').style.transform.replace('translateX(',''))");
await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
await page(0);check(await evaluate("!document.querySelector('.banner-carousel')"),'No empty carousel when no banners are published');
check(await evaluate("Array.from(document.querySelectorAll('.brand-logo,.doctor-emblem img')).every(i=>i.getAttribute('src').includes('carousel-test'))"),'Configured logo reaches header, footer, and homepage');
await page(1);check(await evaluate("document.querySelectorAll('.banner-slide:not([aria-hidden=true])').length===1"),'Single banner exposes one accessible slide');
await until("document.querySelector('.banner-track').style.transform==='translateX(-100%)'");console.log('PASS: Single banner automatically slides left');
await until("parseFloat(document.querySelector('.banner-track').style.transform.replace('translateX(',''))===0");console.log('PASS: Single banner automatically slides back right');
await evaluate("document.querySelector('[aria-label=\"Pause banner autoplay\"]').click()");const paused=await position();await delay(5300);check(await position()===paused,'Pause control stops automatic movement');
await page(3);
await evaluate("document.querySelector('[aria-label=\"Next announcement\"]').click()");await delay(50);check((await position())===-100,'Next control advances one banner');
await evaluate("document.querySelector('[aria-label=\"Previous announcement\"]').click()");await delay(50);check((await position())===0,'Previous control returns to the first banner');
await evaluate("document.querySelector('[aria-label=\"Previous announcement\"]').click()");await delay(50);check((await position())===-200,'Manual controls loop across multiple banners');
await evaluate("document.querySelector('.banner-pagination button').click()");await delay(50);check((await position())===0,'Slide indicators select the correct banner');
check(await evaluate("document.querySelectorAll('.banner-slide[inert]').length===2"),'Offscreen banners cannot receive keyboard focus');
await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});await delay(900);
check(await evaluate('document.documentElement.scrollWidth<=innerWidth'),'Mobile carousel has no horizontal overflow');
await fs.mkdir('.local/screenshots',{recursive:true});const shot=await send('Page.captureScreenshot',{format:'png'});await fs.writeFile('.local/screenshots/carousel-mobile.png',Buffer.from(shot.data,'base64'));
await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});await page(1);await delay(5300);
check((await position())===0,'Reduced-motion preference disables autoplay');
check(await evaluate("getComputedStyle(document.querySelector('.banner-track')).transitionDuration==='0s'"),'Reduced-motion preference removes sliding transitions');
check(errors.length===0,'No browser runtime errors');
await send('Page.removeScriptToEvaluateOnNewDocument',{identifier:injection.identifier});await send('Emulation.setEmulatedMedia',{features:[]});await send('Page.navigate',{url:'http://127.0.0.1:8000'});ws.close();
