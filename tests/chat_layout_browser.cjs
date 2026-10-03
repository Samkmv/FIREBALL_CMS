'use strict';
const {chromium}=require(process.env.FIREBALL_PLAYWRIGHT_MODULE || 'playwright');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const base=process.env.FIREBALL_TEST_URL || 'http://localhost:8888';
const output=process.env.FIREBALL_TEST_OUTPUT || '/private/tmp/fireball-chat-layout';
fs.mkdirSync(output,{recursive:true});
(async()=>{const b=await chromium.launch({headless:true,args:['--disable-gpu','--disable-software-rasterizer']});try{
 const c=await b.newContext({serviceWorkers:'block',storageState:process.env.FIREBALL_AUTH_STATE});
 const p=await c.newPage();
 await p.goto(base+'/chat',{waitUntil:'domcontentloaded'});
 const group=await p.locator('a[href*="/chat/group?"]').first().getAttribute('href');
 assert.ok(group);
 for(const width of [1440,390,320])for(const url of [base+'/chat',new URL(group,base).href]){
   await p.setViewportSize({width,height:width===1440?1000:width===390?844:568});
   let release, entered;const hold=new Promise(r=>release=r);
   const requested=new Promise(r=>entered=r);
   const route=/\/(?:assets\/default|themes\/[^/]+\/assets)\/js\/chat[^/]*\.js(?:\?.*)?$/;
   await p.route(route,async r=>{if(r.request().url().includes('chat-viewport.js')){await r.continue();return;}entered();await hold;await r.continue();});
   const navigation=p.goto(url,{waitUntil:'domcontentloaded'});
   await requested;
   await p.waitForTimeout(150);
   await p.locator('.chat-app-layout').first().waitFor();
   const before=await p.locator('.chat-app-layout').first().boundingBox();
   assert.ok(await p.evaluate(()=>document.documentElement.classList.contains('chat-viewport-fullscreen')),'Shell ready before chat.js');
   release();await navigation;await p.waitForTimeout(500);
   const after=await p.locator('.chat-app-layout').first().boundingBox();
   for(const k of ['x','y','width','height'])assert.ok(Math.abs(before[k]-after[k])<=2,`Initial shell shifts: ${width}, ${url}, ${k}: ${before[k]} -> ${after[k]}`);
   await p.unroute(route);
 }
 console.log('PASS initial paint: direct/group shell stable before and after delayed chat scripts at 1440/390/320px');
 await p.setViewportSize({width:1440,height:1000});
 await p.goto(base+'/chat',{waitUntil:'domcontentloaded'});
 const card=p.locator('a.chat-contact-item').first();
 assert.ok(await card.evaluate(e=>parseFloat(getComputedStyle(e).borderRadius)>=12),'Group card has rounded corners');
 await card.hover();
 await p.screenshot({path:output+'/sidebar-light.png'});
 await p.evaluate(()=>document.documentElement.setAttribute('data-bs-theme','dark'));
 await p.screenshot({path:output+'/sidebar-dark.png'});
 await p.goto(new URL(group,base).href,{waitUntil:'domcontentloaded'});
 await p.locator('[data-group-chat-messages] .chat-message-bubble').first().waitFor();
 for(const theme of ['light','dark'])for(const [width,height] of [[1440,1000],[390,844],[320,568]]){
   await p.setViewportSize({width,height});await p.evaluate(t=>document.documentElement.setAttribute('data-bs-theme',t),theme);await p.waitForTimeout(300);
   const bubbles=await p.locator('[data-group-chat-messages] .chat-message-bubble').evaluateAll(es=>es.map(e=>{const s=getComputedStyle(e);return {background:s.backgroundColor,image:s.backgroundImage,color:s.color,radius:parseFloat(s.borderRadius)}}));
   assert.ok(bubbles.every(s=>(s.background!=='rgba(0, 0, 0, 0)' || s.image!=='none')&&s.radius>=12),'All group messages have visible bubbles');
   assert.ok(await p.evaluate(()=>document.body.scrollWidth<=innerWidth));
   await p.screenshot({path:`${output}/group-${theme}-${width}.png`});
   await p.evaluate(()=>{toastr.remove();toastr.options.timeOut=60000;toastr.chat({title:'Очень длинное имя собеседника для проверки переноса',message:'Текст уведомления: '+ 'сообщение '.repeat(15),time:'2026-09-27 12:34:00',avatar:document.querySelector('.chat-message-avatar')?.src || ''});});
   await p.waitForFunction(()=>{const e=document.querySelector('.app-toast--chat');return e?.classList.contains('show')&&getComputedStyle(e).opacity==='1';});
   const toast=await p.locator('.app-toast--chat').boundingBox();assert.ok(toast.x>=0&&toast.x+toast.width<=width+1);
   await p.screenshot({path:`${output}/toast-${theme}-${width}.png`});
   await p.locator('.app-toast--chat .btn-close').click();
 }
 console.log('PASS group cards, message bubbles and toast: light/dark, 1440/390/320px');
}finally{await b.close()}})().catch(e=>{console.error(e);process.exitCode=1;});
