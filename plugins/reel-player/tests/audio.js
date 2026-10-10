'use strict';
const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname,'../assets/player.js'),'utf8');
const meters = source.slice(source.indexOf('    function initGraph()'),source.indexOf('    function configureAudioSession()'));
class Events {
    constructor(){ this.listeners = new Map(); }
    addEventListener(name,callback){ if (!this.listeners.has(name)) this.listeners.set(name,new Set()); this.listeners.get(name).add(callback); }
    removeEventListener(name,callback){ this.listeners.get(name)?.delete(callback); }
    emit(name){ for (const callback of this.listeners.get(name) || []) callback(); }
}
class Node {
    constructor(){ this.connections=[]; }
    connect(node){ this.connections.push(node); }
    disconnect(){ this.connections=[]; }
}
class Context extends Events {
    constructor(){ super(); this.state='running'; this.currentTime=0; this.sampleRate=44100; this.destination=new Node(); this.resumeCount=0; this.gains=[]; this.wakes=[]; }
    createChannelSplitter(){ return new Node(); }
    createAnalyser(){ return new Node(); }
    createGain(){ const gain=new Node(); gain.gain={value:1}; this.gains.push(gain); return gain; }
    createMediaStreamSource(stream){ const node=new Node(); node.stream=stream; return node; }
    createMediaElementSource(element){ const node=new Node(); node.element=element; return node; }
    createBuffer(channels,length,sampleRate){return {channels,length,sampleRate,data:new Float32Array(length)};}
    createBufferSource(){const node=new Node();node.start=()=>{node.started=true;};this.wakes.push(node);return node;}
    resume(){ this.resumeCount++; this.state='running'; return Promise.resolve(); }
    close(){ this.state='closed'; return Promise.resolve(); }
}
class Mirror extends Events {
    constructor(){ super(); this.src=''; this.readyState=0; this.currentTime=0; this.paused=true; this.ended=false; this.seeking=false; this.playCount=0; this.loadCount=0; }
    setAttribute(){}
    hasAttribute(name){return name==='src'&&Boolean(this.src);}
    removeAttribute(name){ if(name==='src')this.src=''; }
    load(){ this.loadCount++; this.readyState=0; this.paused=true; }
    pause(){ this.paused=true; }
    play(){ this.playCount++; this.paused=false; return Promise.resolve(); }
}
function stream(){ const track=new Events(); track.readyState='live'; track.stop=()=>{track.readyState='ended';}; const result=new Events(); result.getTracks=result.getAudioTracks=()=>[track]; return result; }
function setup(capture){
    const intervals=new Map(); let intervalId=0, nativePauses=0, now=0; const notice={hidden:true};
    const audio={src:'https://fixture.invalid/song-1',currentTime:0,readyState:4,seeking:false,playbackRate:1,paused:false,ended:false,pause(){nativePauses++;},...(capture?{captureStream:()=>stream()}:{})};
    const context=vm.createContext({audio,$:()=>notice,meterRecovery:{generation:-1,rebuilds:0,blocked:false,forceMirror:false},performance:{now:()=>now},current:1,loading:false,bufferVisible:false,bufferTimer:undefined,meterBufferTimer:undefined,bufferedAhead:()=>20,navigator:{},location:{href:'https://fixture.invalid/player'},URL,activeTrack:()=>({source:'local'}),graph:undefined,generation:1,meterTimer:undefined,document:{hidden:false},window:{AudioContext:Context},Audio:Mirror,MediaStream:class {constructor(tracks){this.tracks=tracks;}},Float32Array,setInterval(callback){intervals.set(++intervalId,callback);return intervalId;},clearInterval(id){intervals.delete(id);},setTimeout(callback){intervals.set(++intervalId,callback);return intervalId;},clearTimeout(id){intervals.delete(id);},drawMeters(){},updateButtons(){},prepareUpcoming(){}});
    vm.runInContext(meters,context);
    context.syncReels=()=>{};
    vm.runInContext(source.slice(source.indexOf('    function setBuffering('),source.indexOf('    function drawBuffered(')),context);
    return {context,audio,intervals,notice,advance(ms){now+=ms;audio.currentTime+=ms/1000;if(context.graph)context.graph.context.currentTime+=ms/1000;},nativePauses:()=>nativePauses};
}
(async()=>{
    const captured=setup(true), c=captured.context;
    c.resumeMeters(); const firstTrack=c.graph.capturedTrack, firstSource=c.graph.source;
    assert.equal(c.graph.analysers[0].connections[0].gain.value,0);
    assert.equal(c.graph.analysers[0].connections[0].connections[0],c.graph.context.destination);
    c.generation++; captured.audio.src='https://fixture.invalid/song-2'; c.resumeMeters();
    assert.notEqual(c.graph.capturedTrack,firstTrack); assert.notEqual(c.graph.source,firstSource); assert.equal(firstTrack.readyState,'ended');
    const liveTrack=c.graph.capturedTrack; liveTrack.stop(); c.resumeMeters(); assert.notEqual(c.graph.capturedTrack,liveTrack);
    c.graph.context.state='interrupted'; c.graph.context.emit('statechange'); await c.graph.resuming;
    assert.equal(c.graph.context.state,'running'); assert.equal(c.graph.context.resumeCount,1);
    const closed=c.graph.context; closed.state='closed'; c.resumeMeters(); assert.notEqual(c.graph.context,closed);
    c.document.hidden=true; c.resumeMeters(); assert.equal(captured.intervals.size,0); assert.equal(captured.nativePauses(),0);
    c.document.hidden=false; c.resumeMeters(); assert.equal(captured.intervals.size,1);
    const mirrored=setup(false), m=mirrored.context;
    m.resumeMeters(); const mirror=m.graph.mirror; await m.graph.mirrorStarting; assert.equal(mirror.playCount,1); assert.equal(mirror.src,mirrored.audio.src);
    mirrored.audio.src='https://fixture.invalid/song-2'; m.generation++; m.resumeMeters(); await m.graph.mirrorStarting; assert.equal(mirror.src,mirrored.audio.src); assert.equal(mirror.playCount,2);
    mirror.readyState=4; mirrored.audio.currentTime=20; m.syncMirror(); assert.equal(mirror.currentTime,20);
    mirror.paused=true; [...mirrored.intervals.values()][0](); await m.graph.mirrorStarting; assert.equal(mirror.paused,false); assert.equal(mirror.playCount,3);
    m.document.hidden=true; m.stopMeters(true); assert.equal(mirror.src,''); assert.equal(mirrored.nativePauses(),0);
    m.document.hidden=false; m.resumeMeters(); await m.graph.mirrorStarting; assert.equal(mirror.src,mirrored.audio.src); assert.equal(mirror.paused,false);
    m.stopMeters(true);m.activeTrack=()=>({source:'drive'});mirrored.audio.readyState=2;m.bufferedAhead=()=>0;m.resumeMeters();assert.equal(mirror.src,'');assert.equal(mirrored.nativePauses(),0);
    m.bufferedAhead=()=>20;m.resumeMeters();await m.graph.mirrorStarting;assert.equal(mirror.src,mirrored.audio.src+'?meter=1');assert.equal(mirror.paused,false);
    m.loading=true;m.resumeMeters();assert.equal(mirror.src,mirrored.audio.src+'?meter=1');assert.equal(mirror.paused,true);m.loading=false;m.navigator.connection={saveData:true};m.resumeMeters();assert.equal(mirror.src,'');m.navigator.connection={};
    m.resumeMeters();await m.graph.mirrorStarting;mirror.error={code:2};mirror.emit('error');m.resumeMeters();assert.equal(mirror.src,'');assert.equal(m.meterRecovery.blocked,true);assert.equal(m.graph,null);
    m.generation++;m.resumeMeters();await m.graph.mirrorStarting;assert.equal(m.graph.mirror.src,mirrored.audio.src+'?meter=1');m.document.hidden=true;m.stopMeters(true);assert.equal(m.graph.mirror.src,'');assert.equal(mirrored.nativePauses(),0);
    c.activeTrack=()=>({source:'drive'}); c.resumeMeters(); assert.ok(c.graph?.capture); assert.equal(captured.nativePauses(),0);
    const startup=setup(false), s=startup.context;s.activeTrack=()=>({source:'drive'});startup.audio.readyState=0;s.bufferedAhead=()=>0;s.loading=true;
    s.resumeMeters();assert.equal(s.graph.mirror.src,'');s.resumeMeters(true);await s.graph.mirrorStarting;assert.ok(s.graph.mirror.src.endsWith('?meter=1'));
    const analysis=s.graph.mirror, initialLoads=analysis.loadCount;
    s.setBuffering(true);analysis.emit('loadedmetadata');s.resumeMeters();assert.equal(analysis.loadCount,initialLoads);assert.equal(analysis.paused,false,'Brief startup buffering preserves gesture-unlocked request');
    const grace=s.meterBufferTimer;s.setBuffering(false);startup.audio.readyState=3;s.bufferedAhead=()=>0;s.resumeMeters();assert.equal(analysis.loadCount,initialLoads);assert.equal(startup.intervals.has(grace),false);
    // Once metadata has aligned the mirror, a pending seek may finish instead
    // of being reset to the audible player's continually moving position.
    startup.audio.currentTime=20;analysis.readyState=1;s.syncMirror();assert.equal(analysis.currentTime,20);
    analysis.seeking=true;startup.audio.currentTime=21;analysis.readyState=3;s.syncMirror();assert.equal(analysis.currentTime,20,'Do not chase a moving target while a seek is decoding');
    analysis.seeking=false;analysis.currentTime=20.8;s.syncMirror();assert.equal(analysis.currentTime,20.8);assert.ok(analysis.playbackRate>startup.audio.playbackRate);assert.equal(startup.audio.playbackRate,1);
    s.setBuffering(true);startup.intervals.get(s.meterBufferTimer)();assert.equal(analysis.src,'','Persistent buffering yields the secondary download');s.setBuffering(false);
    s.stopMeters(true);analysis.play=()=>Promise.reject(Object.assign(new Error('cancelled'),{name:'AbortError'}));
    s.loading=false;s.bufferedAhead=()=>1;s.resumeMeters();await s.graph.mirrorStarting;assert.equal(s.graph.mirrorFailedGeneration,undefined);
    analysis.play=Mirror.prototype.play;s.resumeMeters();await s.graph.mirrorStarting;assert.equal(analysis.paused,false);
    s.setMeterBlocked(true);s.resumeMeters(true);await s.graph.mirrorStarting;assert.equal(s.meterRecovery.blocked,false);assert.equal(startup.nativePauses(),0);
    const replacement=s.graph.mirror;
    s.stopMeters(true);let rejectOld;replacement.play=()=>new Promise((resolve,reject)=>{rejectOld=reject;});s.resumeMeters(true);const oldPlay=s.graph.mirrorStarting;
    s.stopMeters(true);replacement.play=Mirror.prototype.play;s.resumeMeters(true);await s.graph.mirrorStarting;
    rejectOld(Object.assign(new Error('old request'),{name:'NotAllowedError'}));await oldPlay;
    assert.equal(s.meterRecovery.blocked,false,'Old rejected play cannot disable a newer request for the same song');assert.equal(replacement.paused,false);
    // Safari may defer native playback while a trusted tap is still the only
    // opportunity to activate Web Audio and the separate analysis element.
    const deferred=setup(false), d=deferred.context;
    deferred.audio.paused=true; d.window.AudioContext=class extends Context {constructor(){super();this.state='suspended';}};
    d.resumeMeters(true); const unlocked=d.graph; await unlocked.resuming; await unlocked.mirrorStarting;
    assert.equal(unlocked.context.resumeCount,1);assert.equal(unlocked.mirror.playCount,1);assert.ok(unlocked.mirror.src);
    assert.equal(unlocked.context.wakes.length,1);assert.equal(unlocked.context.wakes[0].started,true);assert.equal(unlocked.context.wakes[0].buffer.data[0],0);assert.equal(unlocked.context.wakes[0].connections[0],unlocked.context.destination,'Silent activation never enters the measured channels');
    unlocked.mirror.emit('loadedmetadata');d.resumeMeters();assert.ok(unlocked.mirror.src,'Pending native playback preserves the gesture-unlocked analysis');
    deferred.audio.paused=false;d.resumeMeters();assert.equal(d.graph,unlocked);assert.equal(deferred.nativePauses(),0);
    // A pending resume cannot suppress a fresh user tap indefinitely.
    const hanging=setup(true), h=hanging.context;h.resumeMeters();const abandoned=h.graph;
    abandoned.context.state='interrupted';abandoned.context.resume=()=>new Promise(()=>{});h.resumeMeters();assert.ok(abandoned.resuming);
    h.resumeMeters(true);assert.notEqual(h.graph,abandoned);assert.equal(abandoned.context.state,'closed');assert.equal(hanging.nativePauses(),0);
    // iOS sometimes reports running while its audio clock is frozen. One
    // bounded rebuild recovers; a persistent failure stops automatic retries.
    const frozen=setup(true), f=frozen.context;f.resumeMeters();const dead=f.graph;
    frozen.advance(2100);dead.context.currentTime=0;f.resumeMeters();assert.notEqual(f.graph,dead);assert.equal(f.meterRecovery.rebuilds,1);
    const second=f.graph;frozen.advance(2100);second.context.currentTime=0;f.resumeMeters();
    assert.equal(f.graph,null);assert.equal(f.meterRecovery.blocked,true);assert.equal(frozen.notice.hidden,false);assert.equal(frozen.intervals.size,0);
    f.resumeMeters();assert.equal(f.graph,null,'No unbounded context recreation or secondary traffic');
    f.resumeMeters(true);assert.ok(f.graph);assert.equal(frozen.notice.hidden,true);assert.equal(f.meterRecovery.rebuilds,0);
    const fresh=f.graph;frozen.advance(2100);f.resumeMeters();assert.equal(f.graph,fresh,'A healthy engine stays alive even when samples represent silence');assert.equal(frozen.nativePauses(),0);
    frozen.advance(2100);fresh.context.currentTime=fresh.watch.clock;f.resumeMeters(true);assert.notEqual(f.graph,fresh);assert.equal(f.graph.context.wakes.length,1,'A frozen running engine preserves the trusted tap when rebuilt');
    // The same bounded recovery applies when resume() never resolves.
    const timed=setup(true), t=timed.context;t.resumeMeters();const timedOld=t.graph;
    timedOld.context.state='suspended';timedOld.context.resume=()=>new Promise(()=>{});t.resumeMeters();timed.advance(2100);timedOld.context.currentTime=0;t.resumeMeters();assert.notEqual(t.graph,timedOld);
    timedOld.context.emit('statechange');assert.equal(t.graph.context.resumeCount,0,'Discarded engine events cannot resume or replace its successor');
    // A capture capability without an audio track falls back once, leaving
    // the audible element untouched. No test assumes nonzero music samples.
    const missing=setup(true), n=missing.context;missing.audio.captureStream=()=>{const empty=new Events();empty.getTracks=empty.getAudioTracks=()=>[];return empty;};
    n.resumeMeters();const engine=n.graph.context;missing.advance(2100);n.resumeMeters();assert.equal(n.graph.capture,null);assert.ok(n.graph.mirror);assert.equal(n.graph.context,engine);assert.equal(missing.nativePauses(),0);
    const denied=setup(false), e=denied.context;e.resumeMeters();await e.graph.mirrorStarting;
    const deniedMirror=e.graph.mirror;deniedMirror.pause();deniedMirror.play=()=>Promise.reject(Object.assign(new Error('activation required'),{name:'NotAllowedError'}));
    e.resumeMeters();const deniedPlay=e.graph.mirrorStarting;await deniedPlay;assert.equal(e.graph,null);assert.equal(e.meterRecovery.blocked,true);assert.equal(denied.intervals.size,0);
    e.resumeMeters();assert.equal(e.graph,null,'Local tracks do not repeatedly retry a rejected play');e.resumeMeters(true);await e.graph.mirrorStarting;assert.ok(e.graph.mirror.src);assert.equal(e.meterRecovery.blocked,false);assert.equal(denied.nativePauses(),0);
    const stalled=setup(false), q=stalled.context;q.resumeMeters();await q.graph.mirrorStarting;
    const stalledEngine=q.graph;stalledEngine.mirror.readyState=4;stalled.advance(2100);q.resumeMeters();assert.notEqual(q.graph,stalledEngine,'A decoded mirror with a frozen clock recovers independently');assert.equal(stalled.nativePauses(),0);
    const slow=setup(false), z=slow.context;z.resumeMeters();await z.graph.mirrorStarting;const waitingEngine=z.graph;slow.advance(16000);z.resumeMeters();assert.notEqual(z.graph,waitingEngine,'A hung metadata request has a deadline');
    const background=setup(true), b=background.context;b.resumeMeters();const beforeHide=b.graph;
    b.document.hidden=true;b.resumeMeters();background.advance(10000);b.document.hidden=false;b.resumeMeters();assert.equal(b.graph,beforeHide,'Hidden time does not count as a foreground engine stall');assert.equal(background.nativePauses(),0);
    const economical=setup(false), k=economical.context;k.activeTrack=()=>({source:'drive'});k.resumeMeters();await k.graph.mirrorStarting;
    k.navigator.connection={saveData:true};k.resumeMeters();const idle=k.graph;economical.advance(20000);k.resumeMeters();assert.equal(k.graph,idle,'An intentionally unloaded analysis stream is not treated as hung');assert.equal(k.meterRecovery.blocked,false);
    f.setMeterBlocked(true);f.document.hidden=true;f.resumeMeters();assert.equal(frozen.notice.hidden,true);f.document.hidden=false;f.resumeMeters();assert.equal(frozen.notice.hidden,false,'Recovery action returns with the foreground app');
    const controls=source.slice(source.indexOf("    if ('mediaSession' in navigator) {"),source.indexOf("    window.addEventListener('pagehide', save);"));
    const navigation=source.slice(source.indexOf('    function next(automatic = false)'),source.indexOf('    function applyState(result)'));
    const actions=new Map(); const audio={duration:200,currentTime:75,paused:false,pause(){this.paused=true;}};
    const context=vm.createContext({audio,current:2,queue:[1,2,3],repeat:'off',shuffle:false,bag:[],futureBag:[],history:[],navigator:{mediaSession:{setActionHandler(name,handler){actions.set(name,handler);}}},drawProgress(){}});
    vm.runInContext('function load(id){current=id;audio.currentTime=0;} function play(){audio.paused=false;}',context);
    vm.runInContext(navigation+controls,context);
    assert.equal(actions.get('seekbackward'),null); assert.equal(actions.get('seekforward'),null);
    actions.get('nexttrack')(); assert.equal(context.current,3); assert.equal(audio.currentTime,0);
    actions.get('previoustrack')(); assert.equal(context.current,2);
    actions.get('seekto')({seekTime:150}); assert.equal(audio.currentTime,150);
    actions.get('pause')(); assert.equal(audio.paused,true); actions.get('play')(); assert.equal(audio.paused,false);
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
    console.log('Audio checks passed: trusted activation, hung resume, frozen running clock, bounded recovery, missing capture fallback, local autoplay rejection, mirror deadlines, background return, native audio isolation and Media Session controls.');
})().catch(error=>{console.error(error);process.exitCode=1;});
