'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const quality=require('../plugins/reel-player/assets/quality.js');
const source=fs.readFileSync(require('node:path').join(__dirname,'../plugins/reel-player/assets/player.js'),'utf8');
const methods=source.slice(source.indexOf('    function drawAudioQuality()'),source.indexOf('    function updateNow()'));
function setup() {
    const requests=[],timers=new Set(),node={textContent:''};let timer=0;
    const tracks=[{id:1,filename:'old.mp3',file_size:9600000,duration:240},{id:2,filename:'new.flac',file_size:20000000,duration:100}];
    const context=vm.createContext({current:1,generation:1,qualityCache:new Map(),qualityJob:null,audio:{readyState:0,duration:NaN},AbortController,
        window:{TapeRoomQuality:quality},$:()=>node,setTimeout(){timers.add(++timer);return timer;},clearTimeout(id){timers.delete(id);},
        api:(path,form,signal)=>new Promise((resolve,reject)=>requests.push({path,signal,resolve,reject}))});
    context.activeTrack=()=>tracks.find(track=>track.id===context.current);
    vm.runInContext(methods,context);
    return {context,requests,timers,node};
}
(async()=>{
    const test=setup();test.context.drawAudioQuality();assert.match(test.node.textContent,/MP3.*≈ 320/,'Format and estimated rate appear before an optional request');
    const old=test.context.requestQuality();test.context.current=2;test.context.generation++;
    const recent=test.context.requestQuality();assert.equal(test.requests[0].signal.aborted,true,'Changing song cancels the preceding metadata request');
    test.requests[0].resolve({quality:{format:'MP3',sampleRate:22050}});await old;
    assert.equal(test.context.qualityCache.has(1),false,'Late metadata cannot populate a superseded generation');
    test.requests[1].resolve({quality:{format:'FLAC',sampleRate:48000,bitDepth:24,channels:2}});await recent;
    assert.match(test.node.textContent,/FLAC.*24 БИТ.*48 КГЦ.*СТЕРЕО/,'Only the active song gets its verified quality');
    assert.equal(test.timers.size,0,'Completed and aborted requests clean their deadline timers');
    await test.context.requestQuality();assert.equal(test.requests.length,2,'Returning to cached metadata does not read audio again');
    const failure=setup(),pending=failure.context.requestQuality();failure.requests[0].reject(new Error('unavailable'));await pending;
    failure.context.drawAudioQuality();assert.match(failure.node.textContent,/MP3/,'Optional metadata failure retains a usable format label');
    assert.equal(failure.timers.size,0,'Failed requests clean their deadlines');
    const sameId=setup(),prior=sameId.context.requestQuality();sameId.context.generation++;
    sameId.requests[0].resolve({quality:{format:'MP3',sampleRate:22050}});await prior;
    assert.equal(sameId.context.qualityCache.size,0,'Reloading the same track still rejects older generation results');
    test.context.current=0;test.context.drawAudioQuality();assert.equal(test.node.textContent,'АУДИО','Removing the current song clears stale format and bitrate');
    console.log('Audio quality client checks passed: fallback, abort, stale generations, cache reuse, optional failure and deadline cleanup.');
})().catch(error=>{console.error(error);process.exitCode=1;});
