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
    removeAttribute(name){ if(name==='src')this.src=''; }
    load(){ this.readyState=0; this.paused=true; }
    pause(){ this.paused=true; }
    play(){ this.playCount++; this.paused=false; return Promise.resolve(); }
}
function stream(){ const track=new Events(); track.readyState='live'; track.stop=()=>{track.readyState='ended';}; const result=new Events(); result.getTracks=result.getAudioTracks=()=>[track]; return result; }
function setup(capture){
    const intervals=new Map(); let intervalId=0, nativePauses=0;
    const audio={src:'https://fixture.invalid/song-1',currentTime:0,playbackRate:1,paused:false,ended:false,pause(){nativePauses++;},...(capture?{captureStream:()=>stream()}:{})};
    const context=vm.createContext({audio,graph:undefined,generation:1,meterTimer:undefined,document:{hidden:false},window:{AudioContext:Context},Audio:Mirror,MediaStream:class {constructor(tracks){this.tracks=tracks;}},Float32Array,setInterval(callback){intervals.set(++intervalId,callback);return intervalId;},clearInterval(id){intervals.delete(id);},drawMeters(){}});
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
    m.resumeMeters(); const mirror=m.graph.mirror; assert.equal(mirror.playCount,1); assert.equal(mirror.src,mirrored.audio.src);
    mirrored.audio.src='https://fixture.invalid/song-2'; m.generation++; m.resumeMeters(); assert.equal(mirror.src,mirrored.audio.src); assert.equal(mirror.playCount,2);
    mirror.readyState=4; mirrored.audio.currentTime=20; m.syncMirror(); assert.equal(mirror.currentTime,20);
    mirror.paused=true; [...mirrored.intervals.values()][0](); assert.equal(mirror.paused,false); assert.equal(mirror.playCount,3);
    m.document.hidden=true; m.stopMeters(true); assert.equal(mirror.src,''); assert.equal(mirrored.nativePauses(),0);
    m.document.hidden=false; m.resumeMeters(); assert.equal(mirror.src,mirrored.audio.src); assert.equal(mirror.paused,false);
    const controls=source.slice(source.indexOf("    if ('mediaSession' in navigator) {"),source.indexOf("    window.addEventListener('pagehide', save);"));
    const navigation=source.slice(source.indexOf('    function next(automatic = false)'),source.indexOf('    function applyState(result)'));
    const actions=new Map(); const audio={duration:200,currentTime:75,paused:false,pause(){this.paused=true;}};
    const context=vm.createContext({audio,current:2,queue:[1,2,3],repeat:'off',shuffle:false,bag:[],history:[],navigator:{mediaSession:{setActionHandler(name,handler){actions.set(name,handler);}}},drawProgress(){}});
    vm.runInContext('function load(id){current=id;audio.currentTime=0;} function play(){audio.paused=false;}',context);
    vm.runInContext(navigation+controls,context);
    assert.equal(actions.get('seekbackward'),null); assert.equal(actions.get('seekforward'),null);
    actions.get('nexttrack')(); assert.equal(context.current,3); assert.equal(audio.currentTime,0);
    actions.get('previoustrack')(); assert.equal(context.current,2);
    actions.get('seekto')({seekTime:150}); assert.equal(audio.currentTime,150);
    actions.get('pause')(); assert.equal(audio.paused,true); actions.get('play')(); assert.equal(audio.paused,false);
    console.log('Audio checks passed: new track capture, silent sink, ended capture recovery, interrupted/closed context recovery, mirror switching/restart, background return, native audio isolation and Media Session controls.');
})().catch(error=>{console.error(error);process.exitCode=1;});
