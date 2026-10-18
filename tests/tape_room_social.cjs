'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const checks=[];function check(label,ok){assert.ok(ok,label);checks.push(label);}
function classes(){const values=new Set();return {add:v=>values.add(v),remove:v=>values.delete(v),toggle:(v,on)=>on?values.add(v):values.delete(v),contains:v=>values.has(v)};}
function form(id=1){
    const icon={className:''},label={textContent:'Подписаться'},field={value:'1'},feedback={textContent:'',classList:classes()};
    const button={disabled:false,classList:classes(),attrs:{},setAttribute(k,v){this.attrs[k]=v;},querySelector:()=>icon};
    return {dataset:{trFollowUser:String(id),trFollowName:'Тестовый слушатель'},action:'http://fixture.test/tape-room/people/'+id+'/follow',isConnected:true,
        matches:()=>true,hasAttribute:k=>k==='data-tr-follow-user',querySelector:s=>({'button[type="submit"]':button,'[name="following"]':field,'[data-tr-follow-label]':label,'[data-tr-social-feedback]':feedback}[s]),
        field,button,feedback,icon,label};
}
(async()=>{
    const first=form(),second=form(),count={dataset:{trFollowCount:'1'},textContent:'0'},noun={dataset:{trFollowNoun:'1'},textContent:''},image={dataset:{trSocialCoverLight:'/light.webp',trSocialCoverDark:'/dark.webp'},src:'/light.webp',getAttribute(){return this.src;}};
    const handlers={},timeouts=new Map();let timerId=0,observer,requests=[],fetcher;
    const document={documentElement:{dataset:{theme:'light'}},addEventListener:(name,fn)=>handlers[name]=fn,querySelectorAll:s=>({'[data-tr-follow-user]':[first,second],'[data-tr-follow-count]':[count],'[data-tr-follow-noun]':[noun],'[data-tr-social-cover]':[image]}[s]||[])};
    const context={document,FormData:class{constructor(f){this.following=f.field.value;this.csrf='csrf-fixture';}},AbortController,MutationObserver:class{constructor(fn){observer=fn;}observe(){}},setTimeout:fn=>{timeouts.set(++timerId,fn);return timerId;},clearTimeout:id=>timeouts.delete(id),fetch:async(url,options)=>{requests.push({url,options});return fetcher(url,options);}};
    vm.runInNewContext(fs.readFileSync(require('node:path').join(__dirname,'../themes/tape-room/assets/js/social.js'),'utf8'),context);
    const submit=f=>handlers.submit({target:f,defaultPrevented:false,preventDefault(){}});
    let resolve;fetcher=()=>new Promise(r=>resolve=r);const pending=submit(first);await submit(first);
    check('Only one request is sent during a pending submit',requests.length===1&&first.button.disabled);
    check('Request sends same-origin credentials CSRF form and JSON preference',requests[0].options.credentials==='same-origin'&&requests[0].options.body.csrf==='csrf-fixture'&&requests[0].options.headers.Accept==='application/json');
    resolve({ok:true,headers:{get:()=> 'application/json'},json:async()=>({status:true,target:1,following:true,person:{followersCount:2}})});await pending;
    check('Confirmed subscription updates every visible copy',[first,second].every(f=>f.field.value==='0'&&f.button.attrs['aria-pressed']==='true'&&f.label.textContent==='Вы подписаны'));
    check('Subscription icon and accessible action match state',first.icon.className.includes('user-check')&&first.button.attrs['aria-label'].startsWith('Отписаться'));
    check('Count and Russian noun update together',count.textContent==='2'&&noun.textContent==='подписчика');
    check('Success clears pending state and timers',!first.button.disabled&&!first.dataset.trSocialBusy&&timeouts.size===0);
    check('Follow success adds no message or extra card height',first.feedback.textContent==='');
    fetcher=async()=>({ok:false,headers:{get:()=> 'application/json'},json:async()=>({status:false,message:'Профиль скрыт.'})});await submit(first);
    check('Rejected action preserves previously confirmed state',first.field.value==='0'&&first.feedback.textContent==='Профиль скрыт.'&&first.feedback.classList.contains('is-error'));
    fetcher=async()=>({ok:true,headers:{get:()=> 'text/html'}});await submit(first);
    check('Expired session HTML never appears as false success',first.feedback.textContent.includes('Обновите страницу')&&first.field.value==='0');
    fetcher=async()=>{throw new Error('Нет сети');};await submit(first);
    check('Network error leaves control usable without automatic retry',first.feedback.textContent==='Нет сети'&&!first.button.disabled);
    fetcher=(_,options)=>new Promise((_,reject)=>options.signal.addEventListener('abort',()=>{const e=new Error('aborted');e.name='AbortError';reject(e);}));
    const timeout=submit(first);timeouts.values().next().value();await timeout;
    check('Timeout reports uncertain outcome and preserves state',first.feedback.textContent.includes('проверьте результат')&&first.field.value==='0'&&!first.button.disabled&&timeouts.size===0);
    fetcher=async()=>({ok:true,headers:{get:()=> 'application/json'},json:async()=>({status:true,target:1,following:false,person:{followersCount:1}})});await submit(first);
    check('Unfollow restores subscribe controls and count',first.field.value==='1'&&first.button.attrs['aria-pressed']==='false'&&count.textContent==='1'&&noun.textContent==='подписчик');
    document.documentElement.dataset.theme='dark';observer();check('Default public album covers follow the theme',image.src==='/dark.webp');
    const before=requests.length;await handlers.submit({target:first,defaultPrevented:true});check('Other submit handlers are respected',requests.length===before);
    const report=JSON.stringify({passed:checks.length,checks},null,2);fs.mkdirSync(__dirname+'/artifacts/tape-room-social',{recursive:true});fs.writeFileSync(__dirname+'/artifacts/tape-room-social/client.json',report+'\n');console.log(report);
})().catch(error=>{console.error(error);process.exitCode=1;});
