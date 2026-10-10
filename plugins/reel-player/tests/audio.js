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
    constructor(){ super(); this.state='running'; this.destination=new Node(); this.resumeCount=0; this.gains=[]; }
    createChannelSplitter(){ return new Node(); }
    createAnalyser(){ return new Node(); }
    createGain(){ const gain=new Node(); gain.gain={value:1}; this.gains.push(gain); return gain; }
    createMediaStreamSource(stream){ const node=new Node(); node.stream=stream; return node; }
    createMediaElementSource(element){ const node=new Node(); node.element=element; return node; }
    resume(){ this.resumeCount++; this.state='running'; return Promise.resolve(); }
    close(){ this.state='closed'; return Promise.resolve(); }
}
class Mirror extends Events {
    constructor(){ super(); this.src=''; this.readyState=0; this.currentTime=0; this.paused=true; this.ended=false; this.playCount=0; }
    setAttribute(){}
    hasAttribute(name){return name==='src'&&Boolean(this.src);}
    removeAttribute(name){ if(name==='src')this.src=''; }
    load(){ this.readyState=0; this.paused=true; }
    pause(){ this.paused=true; }
    play(){ this.playCount++; this.paused=false; return Promise.resolve(); }
}
function stream(){ const track=new Events(); track.readyState='live'; track.stop=()=>{track.readyState='ended';}; const result=new Events(); result.getTracks=result.getAudioTracks=()=>[track]; return result; }
function setup(capture){
    const intervals=new Map(); let intervalId=0, nativePauses=0;
    const audio={src:'https://fixture.invalid/song-1',currentTime:0,playbackRate:1,paused:false,ended:false,pause(){nativePauses++;},...(capture?{captureStream:()=>stream()}:{})};
    const context=vm.createContext({audio,loading:false,bufferedAhead:()=>20,navigator:{},location:{href:'https://fixture.invalid/player'},URL,activeTrack:()=>({source:'local'}),graph:undefined,generation:1,meterTimer:undefined,document:{hidden:false},window:{AudioContext:Context},Audio:Mirror,MediaStream:class {constructor(tracks){this.tracks=tracks;}},Float32Array,setInterval(callback){intervals.set(++intervalId,callback);return intervalId;},clearInterval(id){intervals.delete(id);},drawMeters(){}});
    vm.runInContext(meters,context);
    return {context,audio,intervals,nativePauses:()=>nativePauses};
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
    m.activeTrack=()=>({source:'drive'});m.bufferedAhead=()=>0;m.resumeMeters();assert.equal(mirror.src,'');assert.equal(mirrored.nativePauses(),0);
    m.bufferedAhead=()=>20;m.resumeMeters();await m.graph.mirrorStarting;assert.equal(mirror.src,mirrored.audio.src+'?meter=1');assert.equal(mirror.paused,false);
    m.loading=true;m.resumeMeters();assert.equal(mirror.src,'');m.loading=false;m.navigator.connection={saveData:true};m.resumeMeters();assert.equal(mirror.src,'');m.navigator.connection={};
    m.resumeMeters();await m.graph.mirrorStarting;mirror.error={code:2};mirror.emit('error');m.resumeMeters();assert.equal(mirror.src,'');mirror.error=null;
    m.generation++;m.resumeMeters();await m.graph.mirrorStarting;assert.equal(mirror.src,mirrored.audio.src+'?meter=1');m.document.hidden=true;m.stopMeters(true);assert.equal(mirror.src,'');assert.equal(mirrored.nativePauses(),0);
    c.activeTrack=()=>({source:'drive'}); c.resumeMeters(); assert.ok(c.graph?.capture); assert.equal(captured.nativePauses(),0);
    const startup=setup(false), s=startup.context;s.activeTrack=()=>({source:'drive'});s.bufferedAhead=()=>0;s.loading=true;
    s.resumeMeters();assert.equal(s.graph.mirror.src,'');s.resumeMeters(true);await s.graph.mirrorStarting;assert.ok(s.graph.mirror.src.endsWith('?meter=1'));
    const analysis=s.graph.mirror;s.stopMeters(true);analysis.play=()=>Promise.reject(Object.assign(new Error('cancelled'),{name:'AbortError'}));
    s.loading=false;s.bufferedAhead=()=>1;s.resumeMeters();await s.graph.mirrorStarting;assert.equal(s.graph.mirrorFailedGeneration,undefined);
    analysis.play=Mirror.prototype.play;s.resumeMeters();await s.graph.mirrorStarting;assert.equal(analysis.paused,false);
    s.graph.mirrorFailedGeneration=s.generation;s.resumeMeters(true);assert.equal(s.graph.mirrorFailedGeneration,undefined);assert.equal(startup.nativePauses(),0);
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
    console.log('Audio checks passed: new track capture, silent sink, ended capture recovery, interrupted/closed context recovery, mirror switching/restart, background return, native audio isolation and Media Session controls.');
})().catch(error=>{console.error(error);process.exitCode=1;});
