import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
let checks=0;
const check=(condition,message)=>{assert.ok(condition,message);checks++;};
for(const file of ['themes/default/assets/js/chat.js','public/assets/default/js/chat.js']) {
 const source=readFileSync(new URL('../'+file,import.meta.url),'utf8');
 const block=source.slice(source.indexOf('    let messagesRequest = null;'),source.indexOf('    const setActiveContact ='));
 let now=100000,contact=1;
 const pending=[],applied=[];
 const context=vm.createContext({Date:{now:()=>now},document:{hidden:false},activeContactId:()=>contact,
 state:{messagesRequestId:0,messages:[],messagesLoadErrorShown:false},fetchUrl:'/chat/messages',chatApp:{data:()=>''},
 messagesBox:{html:()=>{}},escapeHtml:x=>x,showFlashAlert:()=>{},applyPayload:x=>applied.push(x),
 $:{ajax:options=>{pending.push(options);return {abort:()=>{options.error({statusText:'abort'});options.complete();}};}}});
 vm.runInContext(block,context);
 const load=(options={})=>vm.runInContext(`loadMessages(${JSON.stringify(options)})`,context);
 load({poll:true});load({poll:true});check(pending.length===1,'Slow chat requests do not overlap');
 pending[0].success({status:true});pending[0].complete();now+=4000;load({poll:true});
 check(pending.length===2,'Healthy active chat retains four-second updates');
 pending[1].error({status:500});pending[1].complete();now+=4000;load({poll:true});check(pending.length===2,'Chat errors back off');
 now+=4000;load({poll:true});check(pending.length===3,'Chat retry after backoff');
 contact=2;load();check(pending.length===4,'Changing contact cancels stale pending fetch');
 pending[2].success({stale:true});check(applied.length===1,'Old contact response is ignored');
 pending[3].success({fresh:true});pending[3].complete();now+=5000;context.document.hidden=true;load({poll:true});
 check(pending.length===4,'Hidden chat does not poll');
}
const php=readFileSync(new URL('../app/Services/PwaService.php',import.meta.url),'utf8');
const worker=php.slice(php.indexOf('const safeStaticResponse'),php.indexOf('let fireballBadgeQueue'));
function sw(options={}) {
 let handler,fetches=0,puts=0;
 const response={ok:true,type:'basic',redirected:false,headers:{get:()=>options.private?'private, no-store':''},clone(){return this;}};
 const cache={match:async()=>options.cached?response:undefined,put:async()=>{puts++;if(options.quota)throw Error('quota');},keys:async()=>[],delete:async()=>{}};
 const context={self:{addEventListener:(name,callback)=>handler=callback},location:{origin:'https://example.test'},URL,
 FIREBALL_PWA:{basePath:'/cms',locales:['en','ru'],offlineUrl:'https://example.test/cms/offline',cacheName:'test'},
 caches:{open:async()=>{if(options.denied)throw Error('denied');return cache;},match:async()=>'offline'},
 fetch:async()=>{fetches++;if(options.offline)throw Error('offline');return response;}};
 vm.runInNewContext(worker,context);
 return {async request(path,opts={}){let result;handler({request:{url:'https://example.test/cms'+path,method:opts.method||'GET',mode:opts.navigate?'navigate':'cors',headers:{has:()=>!!opts.range}},respondWith:p=>result=p,waitUntil:()=>{}});return result;},get fetches(){return fetches;},get puts(){return puts;}};
}
for(const path of ['/admin','/en/admin/settings','/api/pwa/status','/profile','/chat','/notifications/feed','/search/suggest','/assets/live.m3u8','/assets/segment.ts']) {
 const h=sw();check(await h.request(path)===undefined && h.fetches===0,'SW bypass: '+path);
}
for(const opts of [{method:'POST'},{range:true}]) {const h=sw();check(await h.request('/assets/app.js?v=12345678',opts)===undefined,'SW excludes writes/ranges');}
let h=sw({cached:true});await h.request('/assets/app.js?v=12345678');check(h.fetches===0,'Versioned asset cache-first');
h=sw({quota:true});check((await h.request('/assets/app.js?v=12345678')).ok,'Quota failure preserves network response');
h=sw({denied:true});check((await h.request('/assets/app.js?v=12345678')).ok,'Denied storage preserves network response');
h=sw({private:true});await h.request('/assets/app.js?v=12345678');check(h.puts===0,'Personalized responses are not cached');
h=sw({offline:true});check(await h.request('/posts',{navigate:true})==='offline','Navigation has offline fallback');
// Exercise the legacy upgrade using the real initializer: its explicit mode must
// reach the generated wrapper when the layout omits the live module for VOD.
const initializer = readFileSync(new URL('../public/assets/default/js/fireplayer-init.js', import.meta.url), 'utf8');
const upgrade = initializer.slice(initializer.indexOf('    const legacyOptions'), initializer.indexOf('    const initialize'));
for (const mode of ['vod', 'live']) {
 const element = attrs => ({attrs, classList:{add(){}}, appendChild(){}, parentNode:{insertBefore(){}},
   tagName:'VIDEO', closest:()=>null, querySelector:()=>null,
   getAttribute(name){return this.attrs[name] ?? null;}, hasAttribute(name){return name in this.attrs;},
   setAttribute(name,value){this.attrs[name]=value;}, removeAttribute(name){delete this.attrs[name];}});
 const media = element({src:'https://example.test/stream.m3u8', 'data-mode':mode, poster:'poster.jpg'});
 const wrapper = element({});
 const document = {querySelectorAll:()=>[media], createElement:()=>wrapper};
 const context = vm.createContext({document, Element:class {}, URL, window:{location:{href:'https://example.test/'}}});
 vm.runInContext(readFileSync(new URL('../public/assets/default/js/fireplayer.js', import.meta.url),'utf8'), context);
 vm.runInContext(upgrade + '\nupgradeLegacyContentMedia(document);', context);
 check(wrapper.attrs['data-mode']===mode && wrapper.attrs['data-protocol']==='hls', 'Legacy HLS mode survives upgrade: '+mode);
 check(wrapper.attrs['data-poster']==='poster.jpg', 'Legacy poster survives module selection');
}
console.log(`Background requests: ${checks} checks passed.`);
