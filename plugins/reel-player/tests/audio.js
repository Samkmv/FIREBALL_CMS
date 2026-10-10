'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const Meters=require('../assets/meters.js');
const source=fs.readFileSync(require('node:path').join(__dirname,'../assets/player.js'),'utf8');
class Events {constructor(){this.listeners=new Map();}addEventListener(n,f){if(!this.listeners.has(n))this.listeners.set(n,new Set());this.listeners.get(n).add(f);}removeEventListener(n,f){this.listeners.get(n)?.delete(f);}emit(n){for(const f of [...(this.listeners.get(n)||[])])f();}}
class Node {constructor(){this.connections=[];this.gain={value:1};}connect(n){this.connections.push(n);}disconnect(){this.connections=[];}}
const flush=async()=>{for(let n=0;n<6;n++)await Promise.resolve();};
function setup({capture=false,contextState='running',resumeHang=false}={}){
    let now=0,generation=1,nativePauses=0,serial=0,origin='local',ahead=20;
    const timers=new Map(),contexts=[],copies=[];
    const env={performance:{now:()=>now},document:{hidden:false},navigator:{onLine:true,userActivation:{isActive:false}},location:{href:'https://fixture.invalid/player'},URL,Float32Array,MediaStream:class{constructor(tracks){this.tracks=tracks;}},setInterval(fn){timers.set(++serial,fn);return serial;},clearInterval(id){timers.delete(id);}};
    const streams=[];
    function stream(){const t=new Events();t.readyState='live';t.muted=false;t.stop=()=>{t.readyState='ended';};const s=new Events();s.getTracks=s.getAudioTracks=()=>[t];streams.push(s);return s;}
    const audio={src:'https://fixture.invalid/track-1',currentTime:0,readyState:4,seeking:false,playbackRate:1,paused:false,ended:false,volume:.8,muted:false,pause(){nativePauses++;},...(capture?{captureStream:()=>stream()}:{})};
    class Context extends Events {
        constructor(){super();this.state=contextState;this.currentTime=0;this.sampleRate=44100;this.destination=new Node();this.wakes=[];this.resumes=0;contexts.push(this);}
        createChannelSplitter(){return new Node();}createGain(){return new Node();}createAnalyser(){const n=new Node();n.getFloatTimeDomainData=a=>a.fill(0);return n;}
        createMediaElementSource(element){const n=new Node();n.element=element;return n;}createMediaStreamSource(stream){const n=new Node();n.stream=stream;return n;}
        createBuffer(channels,length,sampleRate){return {channels,length,sampleRate,data:new Float32Array(length)};}
        createBufferSource(){const n=new Node();n.start=()=>{n.started=true;};this.wakes.push(n);return n;}
        resume(){this.resumes++;if(resumeHang)return new Promise(()=>{});this.state='running';return Promise.resolve();}
        close(){this.state='closed';return Promise.resolve();}
    }
    class Mirror extends Events {
        constructor(){super();this.url='';this.currentSrc='';this.currentTime=0;this.readyState=0;this.paused=true;this.ended=false;this.seeking=false;this.rate=1;this.rateWrites=0;this.playCount=0;this.loadCount=0;this.errors=[];copies.push(this);}
        setAttribute(){}hasAttribute(n){return n==='src'&&Boolean(this.url);}removeAttribute(n){if(n==='src')this.url='';}
        get src(){return this.url;}set src(url){this.url=url;this.currentSrc='';this.currentTime=0;this.readyState=0;this.paused=true;this.error=null;this.loadCount++;}
        get playbackRate(){return this.rate;}set playbackRate(v){this.rate=v;this.rateWrites++;}
        load(){this.loadCount++;this.readyState=0;this.paused=true;this.currentSrc='';this.error=null;}pause(){this.paused=true;}
        play(){this.playCount++;const error=this.errors.shift();if(error==='hung')return new Promise(()=>{});if(error)return Promise.reject(Object.assign(new Error('never include private URL'),{name:error}));this.paused=false;return Promise.resolve();}
    }
    env.window={AudioContext:Context};env.Audio=Mirror;
    const m=new Meters({audio,generation:()=>generation,source:()=>origin,bufferedAhead:()=>ahead,env});
    return {m,audio,env,contexts,copies,timers,streams,setSource(v){origin=v;},setAhead(v){ahead=v;},setResumeHang(v){resumeHang=v;},setContextState(v){contextState=v;},gesture(){env.navigator.userActivation.isActive=true;m.resume('user',{isTrusted:true,type:'click'});env.navigator.userActivation.isActive=false;},
        ready(){const copy=m.engine?.mirror;if(copy){copy.currentSrc=copy.src;copy.readyState=4;copy.error=null;copy.emit('loadedmetadata');copy.emit('playing');}},
        advance(ms,{freezeContext=false,freezeCopy=false}={}){now+=ms;audio.currentTime+=ms/1000;const e=m.engine;if(e){if(!freezeContext)e.context.currentTime+=ms/1000;if(e.mirror&&!e.mirror.paused&&e.mirror.readyState>=2&&!e.mirror.seeking&&!freezeCopy)e.mirror.currentTime+=ms/1000*e.mirror.playbackRate;}m.tick();},
        change(){generation++;audio.src='https://fixture.invalid/track-'+generation;audio.currentTime=0;audio.ended=false;},nativePauses:()=>nativePauses};
}
(async()=>{
    const first=setup({contextState:'suspended'});first.audio.paused=true;first.gesture();await flush();
    assert.equal(first.m.lastActivation,true);assert.equal(first.m.engine.context.wakes.length,1);assert.equal(first.m.engine.context.wakes[0].buffer.data[0],0);assert.equal(first.m.engine.context.wakes[0].connections[0],first.m.engine.context.destination);
    first.audio.paused=false;first.ready();first.m.resume('lifecycle');await flush();assert.equal(first.m.engine.context.state,'running');assert.equal(first.nativePauses(),0);
    const fake=setup();fake.m.resume(true);assert.equal(fake.m.lastActivation,false);assert.equal(fake.m.engine.context.wakes.length,0);fake.m.resume('user',{type:'click',isTrusted:false});assert.equal(fake.m.lastActivation,false);
    fake.m.resume('user',{type:'click',isTrusted:true});assert.equal(fake.m.lastActivation,false,'Expired transient activation is not restored by a saved event');
    const normal=setup();normal.gesture();normal.ready();await flush();const retained=normal.m.engine,copy=retained.mirror;
    for(let n=0;n<20;n++){normal.change();normal.m.stop('track-change');normal.m.resume('automatic');normal.ready();await flush();normal.advance(1000);normal.m.sample(0,.08);normal.m.sample(1,.04);assert.equal(normal.m.lastActivation,false);assert.equal(normal.m.needsGesture,false);assert.equal(normal.m.engine,retained);assert.equal(normal.m.engine.mirror,copy);}
    assert.equal(normal.m.counts.contexts,1);assert.equal(normal.m.counts.copies,1);assert.equal(copy.rateWrites,0);assert.equal(normal.m.canSample(),true);assert.equal(copy.loadCount,21,'20 automatic transitions select one source each, without redundant load');
    for(let n=0;n<20;n++){normal.change();normal.m.stop('track-change');normal.gesture();normal.ready();await flush();normal.advance(40);assert.equal(normal.m.engine,retained);assert.equal(normal.m.needsGesture,false);}
    assert.equal(normal.m.counts.contexts,1);assert.equal(normal.m.counts.copies,1);assert.equal(normal.nativePauses(),0);
    normal.audio.paused=true;normal.m.stop();const loads=copy.loadCount;normal.audio.paused=false;normal.gesture();await flush();assert.equal(copy.loadCount,loads,'Pause/resume preserves decoded resource and authorisation');
    normal.audio.currentTime=0;normal.m.seek();assert.equal(copy.currentTime,0);normal.audio.playbackRate=1.25;normal.m.tick();normal.m.tick();assert.equal(copy.rateWrites,1);
    normal.audio.playbackRate=1;normal.m.tick();normal.audio.readyState=2;normal.setAhead(0);
    for(let n=0;n<120;n++)normal.advance(500);assert.equal(normal.m.engine,retained);assert.equal(copy.paused,false);assert.equal(normal.m.counts.rebuilds,0);
    normal.setSource('drive');normal.m.tick();normal.ready();await flush();const initialLoads=copy.loadCount;
    normal.m.buffering(true);normal.advance(1000);assert.equal(copy.loadCount,initialLoads);normal.m.buffering(false);normal.advance(600);assert.equal(copy.loadCount,initialLoads);
    normal.m.buffering(true);normal.advance(1600);const yieldedLoads=copy.loadCount;assert.ok(!copy.src);normal.advance(1600);assert.equal(copy.loadCount,yieldedLoads,'One unload per long wait episode');
    normal.m.buffering(false);normal.advance(500);assert.equal(copy.src,'');normal.m.buffering(true);normal.advance(2000);assert.equal(copy.loadCount,yieldedLoads,'Flapping network does not keep restarting requests');
    normal.m.buffering(false);normal.audio.readyState=4;normal.setAhead(20);normal.advance(2000);normal.ready();await flush();assert.ok(copy.src.endsWith('?meter=1'));assert.equal(normal.m.engine,retained);
    normal.env.document.hidden=true;normal.m.stop('hidden');assert.equal(copy.src,'');assert.equal(normal.audio.paused,false);const afterHidden=copy.loadCount;
    normal.advance(60000);normal.env.document.hidden=false;normal.m.resume('lifecycle');normal.ready();await flush();assert.equal(normal.m.engine,retained);assert.equal(copy.loadCount,afterHidden+1);assert.equal(normal.nativePauses(),0);
    const warm=setup();warm.gesture();warm.ready();await flush();assert.equal(warm.m.canSample(),false);warm.advance(50);assert.equal(warm.m.canSample(),true);warm.change();warm.m.stop('track-change');warm.m.resume('automatic');warm.ready();assert.equal(warm.m.canSample(),false,'Old analyser window must expire on new source');warm.advance(50);assert.equal(warm.m.canSample(),true);
    const capture=setup({capture:true});capture.gesture();const captureEngine=capture.m.engine;
    assert.equal(captureEngine.input.channelCount,2);assert.equal(captureEngine.input.channelCountMode,'explicit');assert.equal(captureEngine.input.channelInterpretation,'speakers');
    captureEngine.capturedTrack.muted=true;captureEngine.capturedTrack.emit('mute');capture.ready();await flush();assert.ok(captureEngine.mirror);assert.equal(capture.m.engine,captureEngine);assert.equal(capture.nativePauses(),0);
    const missing=setup({capture:true});missing.audio.captureStream=()=>{const s=new Events();s.getTracks=s.getAudioTracks=()=>[];return s;};missing.gesture();missing.advance(1500);assert.ok(missing.m.engine.mirror);
    const ended=setup({capture:true});ended.gesture();ended.m.engine.capturedTrack.emit('ended');assert.ok(ended.m.engine.mirror);
    const silent=setup();silent.gesture();silent.ready();await flush();for(let n=0;n<20;n++){silent.advance(500);silent.m.sample(0,0);silent.m.sample(1,0);}
    assert.equal(silent.m.phase,'zero-or-unavailable');assert.equal(silent.m.firstSignal,null);assert.equal(silent.m.counts.rebuilds,0,'Valid zero signal does not restart a healthy decoder');silent.m.sample(0,1e-6);silent.m.sample(1,2e-6);assert.equal(silent.m.phase,'sampling');assert.ok(silent.m.firstSignal>=10000);
    silent.m.readFailure(0);assert.equal(silent.m.reason,'sample-read-failed');assert.equal(silent.m.counts.sampleErrors,1);silent.m.sample(0,.1);assert.equal(silent.m.phase,'sampling');assert.equal(silent.m.counts.rebuilds,0);
    const frozen=setup({capture:true});frozen.gesture();frozen.advance(3100,{freezeContext:true});assert.equal(frozen.m.counts.rebuilds,1);assert.equal(frozen.contexts[0].state,'closed');frozen.advance(500,{freezeContext:true});frozen.advance(3100,{freezeContext:true});assert.equal(frozen.m.terminal,true);assert.equal(frozen.m.needsGesture,false);for(let n=0;n<50;n++)frozen.advance(500,{freezeContext:true});assert.equal(frozen.m.counts.contexts,2);assert.equal(frozen.nativePauses(),0);
    const frozenCopy=setup();frozenCopy.gesture();frozenCopy.ready();await flush();frozenCopy.advance(3100,{freezeContext:true});frozenCopy.ready();await flush();frozenCopy.advance(100,{freezeContext:true});frozenCopy.advance(3100,{freezeContext:true});assert.equal(frozenCopy.m.terminal,true);assert.equal(frozenCopy.m.engine.mirror.src,'','Exhausted context recovery closes analysis requests');assert.equal(frozenCopy.m.engine.mirror.paused,true);assert.equal(frozenCopy.nativePauses(),0);
    const hung=setup({contextState:'suspended',resumeHang:true,capture:true});hung.gesture();hung.advance(4000,{freezeContext:true});assert.equal(hung.m.counts.contexts,1,'Resume has its own deadline, not a two-second decoder timeout');hung.setResumeHang(false);hung.setContextState('running');hung.advance(4100,{freezeContext:true});assert.equal(hung.m.counts.rebuilds,1);hung.advance(1000);assert.equal(hung.m.engine.context.state,'running');assert.equal(hung.nativePauses(),0);
    const resumeRace=setup({contextState:'suspended',capture:true});let rejectResume;
    resumeRace.env.window.AudioContext.prototype.resume=function(){return new Promise((resolve,reject)=>{rejectResume=reject;});};
    resumeRace.gesture();resumeRace.change();resumeRace.m.stop('track-change');resumeRace.m.resume('automatic');resumeRace.m.engine.context.state='running';
    rejectResume(Object.assign(new Error('old resume'),{name:'NotAllowedError'}));await flush();assert.equal(resumeRace.m.needsGesture,false,'Old resume rejection cannot block a new generation');
    resumeRace.advance(91000);resumeRace.m.snapshot();assert.ok(resumeRace.m.events.every(event=>event.ms>=1000),'Old events expire on report even when phase stays unchanged');
    const interrupted=setup();interrupted.gesture();interrupted.ready();await flush();const before=interrupted.m.engine;before.context.state='interrupted';before.context.emit('statechange');await flush();assert.equal(interrupted.m.engine,before);assert.equal(before.context.state,'running');before.context.state='closed';interrupted.m.tick();assert.notEqual(interrupted.m.engine,before);
    const denied=setup();denied.m.create=(()=>{const original=denied.m.create.bind(denied.m);return ()=>{original();denied.m.engine.mirror.errors=['NotAllowedError'];};})();denied.m.resume('automatic');await flush();assert.equal(denied.m.needsGesture,true);const deniedEngine=denied.m.engine;for(let n=0;n<20;n++)denied.advance(500);assert.equal(denied.m.counts.contexts,1);denied.gesture();denied.ready();await flush();assert.equal(denied.m.needsGesture,false);assert.equal(denied.m.engine,deniedEngine,'Real activation reuses Safari element');assert.equal(denied.nativePauses(),0);
    for(const name of ['AbortError','NotSupportedError','NetworkError']){
        const failed=setup();failed.gesture();failed.ready();await flush();const e=failed.m.engine;e.mirror.pause();e.mirror.errors=[name];failed.m.tick();await flush();assert.equal(failed.m.needsGesture,false,name+' is not an autoplay refusal');
        if(name==='NotSupportedError')assert.equal(failed.m.terminal,true);else{failed.advance(2100);failed.ready();await flush();assert.equal(failed.m.terminal,false);assert.equal(failed.m.engine,e);assert.equal(failed.m.counts.retries,1);}assert.equal(failed.nativePauses(),0);
    }
    const network=setup();network.gesture();network.ready();await flush();const networkEngine=network.m.engine;
    for(let n=0;n<3;n++){networkEngine.mirror.error={code:2};networkEngine.mirror.emit('error');network.advance(4100);network.ready();await flush();}
    assert.equal(network.m.terminal,true);assert.equal(network.m.needsGesture,false);assert.equal(network.m.counts.retries,2);
    network.env.navigator.onLine=false;network.m.network(false);network.advance(25000);network.env.navigator.onLine=true;network.m.network(true);network.advance(1600);network.ready();await flush();assert.equal(network.m.terminal,false);assert.equal(network.m.engine,networkEngine);assert.equal(network.m.networkRecoveries,1);
    const delay=setup();delay.gesture();await flush();delay.advance(10000);assert.equal(delay.m.counts.retries,0,'Initial metadata has a separate 20-second deadline');delay.advance(10100);assert.equal(delay.m.reason,'copy-load-timeout');assert.equal(delay.m.counts.retries,1);assert.equal(delay.m.needsGesture,false);
    const seekDelay=setup();seekDelay.gesture();seekDelay.ready();await flush();seekDelay.m.engine.mirror.seeking=true;seekDelay.advance(4000);assert.equal(seekDelay.m.counts.retries,0);seekDelay.advance(8100);assert.equal(seekDelay.m.reason,'copy-seek-timeout');
    const stalled=setup();stalled.gesture();stalled.ready();await flush();stalled.advance(3100,{freezeCopy:true});assert.equal(stalled.m.reason,'copy-decode-stalled');assert.equal(stalled.m.counts.contexts,1,'Decoder recovery preserves Web Audio');
    const racing=setup();racing.gesture();racing.ready();await flush();const oldEngine=racing.m.engine;let rejectOld;
    oldEngine.mirror.pause();oldEngine.mirror.play=()=>new Promise((resolve,reject)=>{rejectOld=reject;});racing.m.tick();
    const oldError=[...oldEngine.mirror.listeners.get('error')][0];racing.change();racing.m.stop('track-change');oldEngine.mirror.play=()=>{oldEngine.mirror.paused=false;return Promise.resolve();};racing.gesture();racing.ready();await flush();
    rejectOld(Object.assign(new Error('private-url'),{name:'NotAllowedError'}));oldError();await flush();assert.equal(racing.m.needsGesture,false);assert.equal(racing.m.engine,oldEngine);
    racing.m.sample(0,.12);racing.m.sample(1,.03);racing.m.nativeFailure(3,206);const report=JSON.stringify({snapshot:racing.m.snapshot(),events:racing.m.events});assert.ok(!report.includes('fixture.invalid')&&!report.includes('private-url'));assert.equal(racing.m.snapshot().nativeError,3);assert.equal(racing.m.snapshot().driveStatus,206);
    const events=new Events(),waits=[];Object.assign(events,{paused:false,ended:false,readyState:2,currentTime:15});
    const eventsContext=vm.createContext({audio:events,setBuffering:v=>waits.push(v),drawProgress(){},resumeMeters(){},startAnimation(){},preloader:{cancel(){}},stopMeters(){},save(){},prepareUpcoming(){},Date,lastSave:0,meterController:{seek(){}},syncMirror(){},next(){}});
    vm.runInContext(source.slice(source.indexOf("    audio.addEventListener('playing'"),source.indexOf('    let errorProbe =')),eventsContext);
    events.emit('stalled');assert.deepEqual(waits,[]);events.emit('waiting');events.emit('playing');assert.deepEqual(waits,[true,false]);
    const playback=source.slice(source.indexOf('    async function play('),source.indexOf('    function shuffled('));
    const wrapper=source.slice(source.indexOf('    function resumeMeters('),source.indexOf('    function stopMeters('));
    let nativePlays=0,analysisErrors=0;
    const isolated=vm.createContext({current:1,generation:1,audio:{paused:true,ended:false,error:null,play(){nativePlays++;this.paused=false;return Promise.resolve();}},meterController:{engine:null,resume(){throw new Error('analysis fault');},state(phase){assert.equal(phase,'unavailable');analysisErrors++;}},configureAudioSession(){},toast(){throw new Error('Analysis must not be misreported as native audio failure');},graph:null});
    vm.runInContext(wrapper+playback,isolated);await isolated.play('user',{isTrusted:true,type:'click'});assert.equal(nativePlays,1);assert.equal(isolated.audio.paused,false);assert.equal(analysisErrors,1,'Analysis command exceptions do not reject native play');
    const controls=source.slice(source.indexOf("    if ('mediaSession' in navigator) {"),source.indexOf("    window.addEventListener('pagehide', save);"));
    const navigation=source.slice(source.indexOf('    function next(automatic = false,'),source.indexOf('    function applyState(result)'));
    const actions=new Map(); const audio={duration:200,currentTime:75,paused:false,pause(){this.paused=true;}};
    const context=vm.createContext({audio,current:2,queue:[1,2,3],repeat:'off',shuffle:false,bag:[],futureBag:[],history:[],playOrigins:[],navigator:{mediaSession:{setActionHandler(name,handler){actions.set(name,handler);}}},drawProgress(){}});
    vm.runInContext('function load(id){current=id;audio.currentTime=0;} function play(origin){playOrigins.push(origin);audio.paused=false;}',context);
    vm.runInContext(navigation+controls,context);
    assert.equal(actions.get('seekbackward'),null); assert.equal(actions.get('seekforward'),null);
    actions.get('nexttrack')(); assert.equal(context.current,3); assert.equal(audio.currentTime,0);
    actions.get('previoustrack')(); assert.equal(context.current,2);
    actions.get('seekto')({seekTime:150}); assert.equal(audio.currentTime,150);
    actions.get('pause')(); assert.equal(audio.paused,true); actions.get('play')(); assert.equal(audio.paused,false);assert.deepEqual(Array.from(context.playOrigins),['system','system','system'],'Lock-screen commands never pretend to be user gestures');
    const cover=source.slice(source.indexOf('    function updateNow()'),source.indexOf('    function updateButtons()'));
    const nodes=new Map(); const currentTrack={title:'Fixture',artist:'Artist',cover:'',source:'drive'};
    const metaContext=vm.createContext({activeTrack:()=>currentTrack,$:selector=>{if(!nodes.has(selector))nodes.set(selector,{textContent:'',getAttribute(){return '';}});return nodes.get(selector);},config:{defaultCover:'/cover.svg',defaultArtwork:'/cover.png'},current:1,queueName:'Fixture',navigator:{mediaSession:{}},window:{MediaMetadata:true},MediaMetadata:class {constructor(metadata){Object.assign(this,metadata);}},URL,location:{href:'https://fixture.invalid/player'}});
    vm.runInContext(cover,metaContext); metaContext.updateNow();
    assert.equal(metaContext.navigator.mediaSession.metadata.artwork[0].sizes,'512x512'); assert.equal(metaContext.navigator.mediaSession.metadata.artwork[0].type,'image/png'); assert.equal(metaContext.navigator.mediaSession.metadata.artwork[0].src,'https://fixture.invalid/cover.png');
    currentTrack.cover='/private-cover';currentTrack.cover_mime='image/jpeg';metaContext.updateNow();assert.equal(metaContext.navigator.mediaSession.metadata.artwork[0].type,'image/jpeg');assert.equal(metaContext.navigator.mediaSession.metadata.artwork[0].sizes,undefined);
    const volumeCode=source.slice(source.indexOf('    function syncVolume()'),source.indexOf('    function updateButtons()'));
    const volumeSlider={value:.65}, attributes={};let volumeFill,volumeIcon,saves=0;
    const volumeContext=vm.createContext({audio:{volume:.65,muted:false},audibleVolume:.65,$:selector=>selector==='[data-volume]'?volumeSlider:{setAttribute(name,value){attributes[name]=value;},classList:{toggle(name,value){attributes[name]=value;}}},rangeFill(node,value){volumeFill=value;},setIcon(node,name){volumeIcon=name;},save(){saves++;}});
    vm.runInContext(volumeCode,volumeContext);volumeContext.syncVolume();assert.equal(volumeSlider.value,.65);
    volumeContext.toggleMute();assert.equal(Number(volumeSlider.value),0);assert.equal(volumeFill,0);assert.equal(volumeContext.audio.volume,.65);assert.equal(volumeIcon,'mute');assert.equal(attributes['aria-pressed'],'true');
    volumeContext.toggleMute();assert.equal(Number(volumeSlider.value),.65);assert.equal(volumeContext.audio.muted,false);
    volumeContext.audio.volume=0;volumeContext.syncVolume();assert.equal(attributes['aria-label'],'Включить звук');volumeContext.toggleMute();assert.equal(volumeContext.audio.volume,.65);assert.equal(saves,3);
    console.log('Audio audit passed: activation origins, 20 automatic and 20 rapid transitions, persistent copy, buffering hysteresis, bounded recovery, separate deadlines, racing callbacks, silence, privacy, native isolation and Media Session.');
})().catch(error=>{console.error(error);process.exitCode=1;});
