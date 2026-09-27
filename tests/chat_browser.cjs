'use strict';
// Run against an authenticated LOCAL installation. Mutations and realtime payloads
// are intercepted: no test messages are sent to real people and no DB rows are created.
// FIREBALL_PLAYWRIGHT_MODULE: installed Playwright module path
// FIREBALL_AUTH_STATE: temporary storageState from a normal authorized login
const {chromium} = require(process.env.FIREBALL_PLAYWRIGHT_MODULE || 'playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const base = process.env.FIREBALL_TEST_URL || 'http://localhost:8888';
const output = process.env.FIREBALL_TEST_OUTPUT || '/private/tmp/fireball-chat-qa';
fs.mkdirSync(output, {recursive:true});
const json = body => ({contentType:'application/json', body:JSON.stringify(body)});
(async()=>{
 const browser = await chromium.launch({headless:true,args:['--disable-gpu','--disable-software-rasterizer']});
 try {
 const context = await browser.newContext({serviceWorkers:'block',storageState:process.env.FIREBALL_AUTH_STATE,viewport:{width:1440,height:1000}});
 const errors=[];
 await context.route('**/chat/send', r=>r.fulfill(json({status:false,message:'QA blocks sends'})));
 await context.route('**/chat/group/send', r=>r.fulfill(json({status:false,message:'QA blocks sends'})));
 context.on('page', p=>p.on('pageerror', e=>errors.push(e.message)));
 await context.addInitScript(()=>{
   window.qaSources=[];
   window.EventSource=class extends EventTarget {
     constructor(url){super();this.url=url;window.qaSources.push(this);setTimeout(()=>this.dispatchEvent(new Event('open')),0);}
     close(){this.closed=true;}
   };
 });
 const page=await context.newPage();
 const groups=[];
 await page.goto(base+'/chat',{waitUntil:'domcontentloaded'});
 groups.push(...await page.locator('a[href*="/chat/group?"]').evaluateAll(es=>es.map(e=>e.href)));
 assert.ok(groups.length,'An existing group is required');
 for (const kind of ['direct','group']) {
   const isGroup=kind==='group';
   const endpoint=isGroup?'**/chat/group/messages*':'**/chat/messages?*';
   let payload, calls=0;
   await page.route(endpoint,async route=>{
     calls++;
     if(!payload){payload=await (await route.fetch()).json();const mine=payload.current_user_id || 0;
       payload.messages=[{id:990001,sender_id:mine,is_mine:true,sender_name:'QA',message:'Проверка выделения длинного текста сообщения при обновлении чата. '.repeat(5),created_at:'2026-09-27 12:00:00',reactions:[]}];}
     await route.fulfill(json(payload));
   });
   let mutations=0;
   await page.route('**/chat/typing',r=>r.fulfill(json({status:true})));
   await page.route('**/chat/messages/react',async r=>{mutations++;await r.fulfill(json(payload));});
   await page.route('**/chat/messages/edit',async r=>{mutations++;payload.messages[0].message='QA edited';await r.fulfill(json(payload));});
   await page.goto(isGroup?groups[0]:base+'/chat',{waitUntil:'domcontentloaded'});
   const box=isGroup?'[data-group-chat-messages]':'[data-chat-messages]';
   await page.locator(box+' .chat-message-text').first().waitFor();
   const selected=await page.locator(box+' .chat-message-text').first().evaluate(el=>{const r=document.createRange();r.selectNodeContents(el);const s=getSelection();s.removeAllRanges();s.addRange(r);return s.toString();});
   const before=calls;
   payload.messages.push({...payload.messages[0],id:990002,message:'QA newest message'});
   const emit=()=>page.evaluate(group=>{const s=window.qaSources.filter(s=>!s.closed).at(-1);const url=new URL(s.url);const data=group?{conversation_id:Number(url.searchParams.get('conversation_id'))}:{contact_id:Number(url.searchParams.get('user_id')),changes:['messages','reactions','receipts']};s.dispatchEvent(new MessageEvent(group?'group-chat':'chat',{data:JSON.stringify(data)}));},isGroup);
   for(let i=0;i<4;i++){await emit();await page.waitForTimeout(5000);assert.equal(await page.evaluate(()=>getSelection().toString()),selected);}
   assert.equal(calls,before,'No forced fetch while selected');
   assert.equal(await page.getByText('QA newest message',{exact:true}).count(),0);
   await page.evaluate(()=>getSelection().removeAllRanges());
   await page.getByText('QA newest message',{exact:true}).waitFor();
   assert.ok(calls>before);
   if(!isGroup){
     await page.locator('[data-chat-reply-message="990001"]').click({force:true});
     assert.equal(await page.locator('[data-chat-reply-to-id]').inputValue(),'990001');
     await page.locator('[data-chat-reply-cancel]').click();
     await page.locator('[data-chat-reaction-open="990001"]').click({force:true});
     await page.locator('[data-chat-reaction-choice][data-message-id="990001"]').first().click();
     await page.waitForTimeout(200);
     await page.locator('[data-chat-reaction-open="990001"]').click({force:true});
     await page.locator('[data-chat-reaction-choice][data-message-id="990001"]').first().click();
     await page.waitForTimeout(200);
     await page.locator('[data-chat-edit-message="990001"]').click({force:true});
     await page.locator('[data-chat-message-input]').fill('QA edited');
     await page.locator('[data-chat-message-input]').press('Enter');
     await page.getByText('QA edited',{exact:true}).waitFor();
     assert.equal(mutations,3,'Reaction toggle twice and edit reach their handlers');
     for (const typing of [true,false]) {
       await page.evaluate(isTyping=>{const s=window.qaSources.filter(s=>!s.closed).at(-1);const u=new URL(s.url);s.dispatchEvent(new MessageEvent('chat',{data:JSON.stringify({contact_id:Number(u.searchParams.get('user_id')),changes:['typing'],typing:{is_typing:isTyping}})}));},typing);
       assert.equal(await page.locator('[data-chat-typing-indicator]').isVisible(),typing);
     }

   }
   console.log(`PASS ${kind}: selection held 20s across 4 SSE events; latest state resumes${!isGroup?'; Reply/Edit/reaction/Typing handlers':''}`);
   await page.unroute(endpoint);
 }
 // Use real group contents for layout screenshots, rather than QA payloads.
 await page.goto(groups[0],{waitUntil:'domcontentloaded'});
 await page.locator('[data-group-chat-messages] .chat-message-text').first().waitFor();
 for(const theme of ['light','dark'])for(const [width,height] of [[1440,1000],[390,844],[320,568]]){
   await page.setViewportSize({width,height});
   await page.evaluate(t=>document.documentElement.setAttribute('data-bs-theme',t),theme);
   await page.waitForTimeout(250);
   const bounds=await page.locator('.chat-thread__composer').boundingBox();
   assert.ok(bounds.y+bounds.height<=height+1,'Composer remains visible');
   assert.ok(await page.evaluate(()=>document.body.scrollWidth<=innerWidth),'No horizontal overflow');
   await page.screenshot({path:`${output}/group-${theme}-${width}.png`});
   await page.evaluate(()=>{window.toastr.remove();window.toastr.options.timeOut=60000;window.toastr.chat({title:'Очень длинное имя собеседника для проверки переноса',message:'Длинное уведомление: '+ 'сообщение '.repeat(15),time:'2026-09-27 12:34:00',avatar:document.querySelector('.chat-message-avatar')?.src || ''});});
   const toast=page.locator('.app-toast--chat');
   await toast.waitFor();
   await page.waitForFunction(()=>{const el=document.querySelector('.app-toast--chat');return el && el.classList.contains('show') && getComputedStyle(el).opacity==='1';});
   const tb=await toast.boundingBox();
   assert.ok(tb.x>=0 && tb.x+tb.width<=width+1);
   assert.ok(tb.width<=(width>575?352:320)+1);
   assert.ok(await toast.locator('.btn-close').isVisible());
   await page.screenshot({path:`${output}/toast-${theme}-${width}.png`});
   await toast.locator('.btn-close').click();
 }
 console.log('PASS group and toast: 1440/390/320px, light/dark, composer, overflow, close button');
 await context.close();
 // Three visible pages share Web Locks and BroadcastChannel. Inject only feed data.
 const multi=await browser.newContext({serviceWorkers:'block',storageState:process.env.FIREBALL_AUTH_STATE});
 let feed={status:true,items:[],chat_unread_count:0,total_unread_count:0};
 const times=[];
 await multi.route('**/notifications/feed*',async r=>{times.push(Date.now());await r.fulfill(json(feed));});
 const pages=await Promise.all([multi.newPage(),multi.newPage(),multi.newPage()]);
 await Promise.all(pages.map(p=>p.goto(base+'/',{waitUntil:'domcontentloaded'})));
 await pages[0].waitForTimeout(1500);
 const start=Date.now();const initial=times.length;
 feed={status:true,chat_unread_count:1,total_unread_count:1,items:[{type:'chat',sort_id:990003,sender_id:990003,title:'QA notification',text:'QA notification text',url:'/chat',created_at:'2026-09-27 12:00:00'}]};
 await pages[0].getByText('QA notification text',{exact:true}).last().waitFor({timeout:7000});
 const latency=Date.now()-start;
 await pages[0].waitForTimeout(10500);
 const count=times.length-initial;
 assert.ok(count<=4,`Polling should be shared: got ${count} requests across three pages`);
 assert.ok(latency<=6000,`Notification latency ${latency}ms`);
 console.log(`PASS notifications: ${latency}ms delivery to UI; ${count} shared feed requests across 3 pages in ${Date.now()-start}ms`);
 assert.deepEqual(errors,[],'No JavaScript errors');
 await multi.close();
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
