'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const Preloader=require('../assets/preload.js');
const originalTimeout=global.setTimeout,originalClear=global.clearTimeout;
const timers=new Map(); let timerId=0;
global.setTimeout=(callback,delay)=>{timers.set(++timerId,{callback,delay});return timerId;};
global.clearTimeout=id=>timers.delete(id);
const tracks=[1,2,3,4].map(id=>({id,url:'/media/'+id,source:'drive'}));
async function tick(){ const scheduled=[...timers].find(([,value])=>value.delay<17000); if(scheduled){timers.delete(scheduled[0]);scheduled[1].callback();} await new Promise(resolve=>setImmediate(resolve)); }
(async()=>{
    const calls=[];
    const p=new Preloader({url:'/prepare',csrf:'fixture-csrf',fetcher:async(url,options)=>{calls.push({id:Number(options.body.get('id')),url,options});return {ok:true,json:async()=>({status:true,ready:true})};}});
    p.schedule(tracks,true); await tick(); await tick(); await tick();
    assert.deepEqual(calls.map(c=>c.id),[1,2]); assert.equal(calls[0].options.headers['X-CSRF-Token'],'fixture-csrf');
    p.schedule(tracks,true); await tick(); assert.equal(calls.length,2); // Repeat progress events do not duplicate requests.
    p.schedule(tracks.slice(1),true); await tick(); await tick(); assert.deepEqual(calls.map(c=>c.id),[1,2,3]); // Prepared next stays prepared.
    p.reset();
    let aborts=0,requests=0;
    const busy=new Preloader({url:'/prepare',csrf:'x',fetcher:(url,options)=>new Promise((resolve,reject)=>{requests++;options.signal.addEventListener('abort',()=>{aborts++;reject(Object.assign(new Error('aborted'),{name:'AbortError'}));});})});
    busy.schedule(tracks,true); await tick(); assert.equal(requests,1);
    busy.schedule(tracks,false); await tick(); assert.equal(aborts,1); assert.equal(requests,1); // Buffer starvation/pause/hidden cancels.
    busy.schedule(tracks,true); await tick(); assert.equal(requests,2);
    busy.schedule([tracks[3]],true); await tick(); assert.equal(aborts,2); assert.equal(requests,3); busy.reset();
    let failures=0;
    const error=new Preloader({url:'/prepare',csrf:'x',fetcher:async()=>{failures++;return {ok:false,json:async()=>({status:false})};}});
    error.schedule([tracks[0]],true); for(let i=0;i<5;i++)await tick(); assert.equal(failures,1); error.reset();
    let locked=0;
    const lockedLoader=new Preloader({url:'/prepare',csrf:'x',fetcher:async()=>{locked++;return {ok:true,json:async()=>({status:true,ready:false,reason:'busy'})};}});
    lockedLoader.schedule([tracks[0]],true); for(let i=0;i<5;i++)await tick(); assert.equal(locked,2); lockedLoader.reset();
    for(const status of [429,502]) {
        let attempts=0;const transient=new Preloader({url:'/prepare',csrf:'x',fetcher:async()=>{attempts++;return {ok:false,status,json:async()=>({status:false})};}});
        transient.schedule([tracks[0]],true);for(let n=0;n<6;n++)await tick();assert.equal(attempts,2,'Temporary HTTP '+status+' has two bounded attempts');transient.reset();
    }
    for(const status of [401,403,404]) {
        let attempts=0;const permanent=new Preloader({url:'/prepare',csrf:'x',fetcher:async()=>{attempts++;return {ok:false,status,json:async()=>{throw new SyntaxError('HTML response');}};}});
        permanent.schedule([tracks[0]],true);for(let n=0;n<6;n++)await tick();assert.equal(attempts,1,'HTTP '+status+' is not retried even for HTML');permanent.reset();
    }
    let networkAttempts=0;const network=new Preloader({url:'/prepare',csrf:'x',fetcher:async()=>{networkAttempts++;throw new TypeError('network');}});
    network.schedule([tracks[0]],true);for(let n=0;n<6;n++)await tick();assert.equal(networkAttempts,2);network.reset();
    let offlineAttempts=0;const offline=new Preloader({url:'/prepare',csrf:'x',fetcher:async()=>{offlineAttempts++;return {ok:true,json:async()=>({status:true,ready:true})};}});
    offline.connectivity(false);offline.schedule(tracks,true);for(let n=0;n<4;n++)await tick();assert.equal(offlineAttempts,0);
    offline.connectivity(true);await tick();await tick();assert.equal(offlineAttempts,2,'Reconnect resumes only the two planned targets');offline.reset();
    const local=new Preloader({url:'/prepare',csrf:'x',fetcher:()=>{throw new Error('Local file must not open another network stream');}});
    local.schedule([{...tracks[0],source:'local'}],true); await tick(); assert.equal(local.done.size,1); local.reset();
    const source=fs.readFileSync(require('node:path').join(__dirname,'../assets/player.js'),'utf8');
    const plan=source.slice(source.indexOf('    function shuffled(exclude)'),source.indexOf('    function bufferedAhead()'));
    const navigation=source.slice(source.indexOf('    function next(automatic = false,'),source.indexOf('    function applyState(result)'));
    const c=vm.createContext({queue:[1,2,3,4],current:1,repeat:'off',shuffle:false,bag:[],futureBag:[],shuffleCycleStarted:false,history:[],track:id=>tracks.find(t=>t.id===id),audio:{currentTime:0},Math,drawProgress(){}});
    vm.runInContext('function load(id){current=id;} function play(){}',c);vm.runInContext(plan+navigation,c);
    assert.deepEqual(Array.from(c.upcoming(),t=>t.id),[2,3]); c.next(); assert.equal(c.current,2);
    c.current=4; assert.equal(c.upcoming().length,0); c.next(true); assert.equal(c.current,4); // Stop at end.
    c.repeat='all'; assert.deepEqual(Array.from(c.upcoming(),t=>t.id),[1,2]); c.next(true); assert.equal(c.current,1);
    c.repeat='one'; assert.equal(c.upcoming().length,0); c.next(true); assert.equal(c.current,1);
    c.repeat='off'; c.shuffle=true; c.bag=[]; c.futureBag=[]; c.shuffleCycleStarted=false;
    let visited=new Set([c.current]);
    for(let i=0;i<3;i++){ const expected=c.upcoming()[0].id; const stable=c.upcoming()[0].id; assert.equal(expected,stable); c.next(true); assert.equal(c.current,expected); assert.equal(visited.has(c.current),false); visited.add(c.current); }
    assert.equal(visited.size,4); const last=c.current; c.next(true); assert.equal(c.current,last); assert.equal(c.upcoming().length,0);
    c.repeat='all'; const nextCycle=c.upcoming().map(t=>t.id); c.next(true); assert.equal(c.current,nextCycle[0]); c.next(true); assert.equal(c.current,nextCycle[1]);
    c.repeat='one'; assert.equal(c.upcoming().length,0);
    console.log('Preload checks passed: two targets, reuse, cancellation, offline/reconnect, bounded network/429/5xx retries, terminal OAuth/404, local isolation, real Shuffle order, repeat and automatic end.');
})().catch(error=>{console.error(error);process.exitCode=1;}).finally(()=>{global.setTimeout=originalTimeout;global.clearTimeout=originalClear;});
