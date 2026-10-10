'use strict';
const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname,'../assets/player.js'),'utf8');
const progress = source.slice(source.indexOf('    function drawBuffered('),source.indexOf('    function initGraph('));
const animation = source.slice(source.indexOf('    function drawMeters('),source.indexOf('    async function play('));
let geometryWrites=0, barWrites=0, samples=0, scheduled=0, positions=0, fills=0;
function shape(radius) { const attributes={r:radius}; return {getAttribute:name=>attributes[name],setAttribute(name,value){attributes[name]=value;geometryWrites++;}}; }
const styles=new Map(), visuals={
    seek:{value:0,style:{getPropertyValue:name=>styles.get(name),setProperty:(name,value)=>styles.set(name,value)}},
    elapsed:{textContent:''}, duration:{textContent:''}, tape:[shape(''),shape('')],
    reels:[0,1].map(i=>({pack:shape(i?'78.00':'174.00'),clip:shape(i?'78.00':'174.00'),rotor:{style:{}},bars:Array.from({length:15},()=>({classList:{toggle(){barWrites++;}}}))}))
};
const audio={duration:240,currentTime:0,playbackRate:1,volume:.8,muted:false,paused:false,ended:false,buffered:{length:1,start:()=>0,end:()=>80}};
const context=vm.createContext({audio,visuals,generation:1,positionKey:'',packRadii:[174,78],meterLevels:[0,0],progressFrame:-Infinity,meterFrame:-Infinity,frameId:0,frameTime:0,angles:[0,0],loading:false,seekActive:false,current:1,activeTrack:()=>({duration:240}),graph:{context:{state:'running'},samples:new Float32Array(256),analysers:[0,1].map(()=>({getFloatTimeDomainData(array){samples++;array.fill(.15);}}))},navigator:{mediaSession:{setPositionState(){positions++;}}},document:{hidden:false},reducedMotion:{matches:false},formatTime:value=>String(Math.floor(value)),rangeFill(){fills++;},requestAnimationFrame(){scheduled++;return scheduled;}});
vm.runInContext(progress+animation,context);
// A 120 Hz screen must not cause 120 geometry rebuilds or system updates.
for (let n=0;n<=120;n++) { audio.currentTime=n/120; context.animate(n*1000/120); }
assert.ok(fills>=9 && fills<=11,`Progress updates are 10 Hz, got ${fills}`);
assert.ok(samples>=50 && samples<=62,`Stereo analysis is at most 30 Hz, got ${samples}`);
assert.ok(geometryWrites<=66,`Geometry is independent of display cadence, got ${geometryWrites}`);
assert.equal(positions,2,'System position updated once per second');
assert.ok(context.angles.every(angle=>angle<0),'Both reels rotate counterclockwise');
assert.ok(context.angles[1]<context.angles[0],'Small take-up pack rotates faster');
const beforeGeometry=geometryWrites,beforeBars=barWrites,beforePositions=positions;
context.drawProgress();context.drawMeters(true);
assert.equal(geometryWrites,beforeGeometry,'Unchanged tape geometry does not repaint');
assert.equal(barWrites,beforeBars,'Unchanged meter bars do not receive class mutations');
assert.equal(positions,beforePositions,'Repeated progress event does not spam system player');
audio.currentTime=.2;context.drawProgress(true);assert.equal(positions,beforePositions+1,'Seek updates system position immediately');
audio.muted=true;context.drawMeters(true);assert.ok(context.meterLevels.every(level=>level===0),'Mute clears both real levels');
audio.muted=false;context.drawMeters(true);assert.ok(context.meterLevels.every(level=>level>0));
const nativeTime=audio.currentTime;context.graph.mirror={paused:false,seeking:true,readyState:3,currentTime:nativeTime};context.drawMeters(true);
assert.ok(context.meterLevels.every(level=>level===0),'Seeking mirror does not display stale signal');
assert.equal(audio.currentTime,nativeTime,'Meter never changes native playback position');
context.graph.mirror.seeking=false;context.drawMeters(true);assert.ok(context.meterLevels.every(level=>level>0));
context.reducedMotion.matches=true;const angles=[...context.angles];context.animate(1300);assert.deepEqual([...context.angles],angles,'Reduced motion freezes rotation');
audio.paused=true;const beforeScheduled=scheduled;context.animate(1400);assert.equal(scheduled,beforeScheduled,'Paused player stops scheduling animation');
assert.ok(context.meterLevels.every(level=>level===0));
// Supported browsers run rotation on native animations, outside JS frames.
audio.paused=false;context.reducedMotion.matches=false;
for (const reel of visuals.reels) {
    reel.motionInitialized=false;
    reel.rotor.animate=(keyframes,options)=>{
        assert.equal(keyframes[1].transform,'rotate(-360deg)');assert.equal(options.iterations,Infinity);
        return {playState:'running',pending:false,playbackRate:1,currentTime:137,pause(){this.playState='paused';},play(){this.playState='running';},updatePlaybackRate(rate){this.playbackRate=rate;}};
    };
}
context.syncReels();assert.ok(visuals.reels.every(reel=>reel.motion.playState==='running'));
const nativeTransforms=visuals.reels.map(reel=>reel.rotor.style.transform);
context.animate(1500);assert.deepEqual(visuals.reels.map(reel=>reel.rotor.style.transform),nativeTransforms,'Native rotation is not overwritten each JS frame');
context.packRadii=[100,160];context.syncReels();assert.ok(visuals.reels.every(reel=>reel.motion.currentTime===137),'Speed updates preserve animation phase');
assert.equal(visuals.reels[0].motion.playbackRate,1.26);
context.document.hidden=true;context.syncReels();assert.ok(visuals.reels.every(reel=>reel.motion.playState==='paused'));assert.equal(audio.paused,false,'Background animation suspension preserves native sound');
context.document.hidden=false;context.syncReels();context.loading=true;context.syncReels();assert.ok(visuals.reels.every(reel=>reel.motion.playState==='paused'));
context.loading=false;context.syncReels();audio.paused=true;context.syncReels();assert.ok(visuals.reels.every(reel=>reel.motion.playState==='paused'));
console.log('Animation checks passed: 120 Hz display, bounded geometry and samples, minimal DOM mutations, system position, rotation, reduced motion, real meters and pause.');
