'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const Envelope = require('../plugins/reel-player/assets/meters.js').Envelope;
assert.equal(typeof Envelope,'function','The independent meter presentation controller is exported for checks');

const targets=[12,6];
const near=(actual,expected,label)=>assert.ok(Math.abs(actual-expected)<1e-9,label+': '+actual+' versus '+expected);
function run(envelope,seconds,fps,active,values=targets) {
    const frame=1/fps;
    let remaining=seconds;
    while(remaining>1e-12){const elapsed=Math.min(frame,remaining);envelope.step(values,elapsed,{active});remaining-=elapsed;}
    return [...envelope.values];
}

// Compare elapsed-time behavior across displays, rather than implementing the
// same easing equation in the test. A silent source must remain silent.
for(const fps of [30,60,120]){
    const envelope=new Envelope();assert.deepEqual(envelope.values,[0,0]);assert.equal(envelope.moving,false);
    let previous=[0,0];
    for(let n=0;n<Math.ceil(.22*fps);n++){
        const display=envelope.step(targets,1/fps,{active:true});
        assert.ok(display.every((value,i)=>value>=previous[i] && value<=targets[i]),'Onset is monotonic, finite and never exceeds measured audio');
        near(display[0],display[1]*2,'Independent stereo levels retain their real ratio');previous=[...display];
    }
    near(envelope.values[0],12,'Attack reaches the actual left level within 220 ms');
    near(envelope.values[1],6,'Attack reaches the actual right level within 220 ms');
    assert.equal(envelope.moving,false,'A steady playing signal adds no independent animation');
    envelope.step([3,0],0,{active:true});assert.deepEqual(envelope.values,[3,0],'A steady envelope follows true changes, including channel silence');
    envelope.step(targets,0,{active:true});
    // Stop only takes the previously displayed values. Newly passed targets
    // during release cannot invent a higher level or extra music activity.
    const early=run(envelope,.1,fps,false,[15,15]);
    assert.ok(early.every((value,i)=>value>0 && value<targets[i]),'Pause has a visible, decreasing release at 100 ms');
    previous=early;
    for(let n=0;n<Math.ceil(.4*fps);n++){
        const display=envelope.step([15,15],1/fps,{active:false});
        assert.ok(display.every((value,i)=>value>=0 && value<=previous[i]),'The stopped display never grows or reads new targets');previous=[...display];
    }
    assert.deepEqual(envelope.values,[0,0],'Pause release reaches true zero within 500 ms');
    assert.equal(envelope.moving,false,'A completed release stops scheduling presentation animation');
    for(let n=0;n<100;n++)envelope.step([0,0],1/fps,{active:true});
    assert.deepEqual(envelope.values,[0,0],'Playing real silence never generates decorative levels');
    assert.equal(envelope.moving,false,'Real silence does not leave an endless onset animation');
}

const attackAt100=[30,60,120].map(fps=>run(new Envelope(),.1,fps,true));
for(const levels of attackAt100)for(let channel=0;channel<2;channel++)near(levels[channel],attackAt100[0][channel],'Attack is independent of display refresh');
const releaseAt100=[30,60,120].map(fps=>{const envelope=new Envelope();envelope.step(targets,0,{active:true,immediate:true});return run(envelope,.1,fps,false);});
for(const levels of releaseAt100)for(let channel=0;channel<2;channel++)near(levels[channel],releaseAt100[0][channel],'Release is independent of display refresh');

const safe=new Envelope();safe.step(targets,.3,{active:true});safe.step([0,0],0,{active:false,immediate:true});
assert.deepEqual(safe.values,[0,0],'Hidden page, seek, mute or expired sample can hard-clear the cosmetic release');
assert.equal(safe.moving,false,'Hard resets add no frames');
safe.step([4,9],0,{active:true,immediate:true});
assert.deepEqual(safe.values,[4,9],'Reduced motion can display actual levels directly without smoothing');
assert.equal(safe.moving,false,'Reduced motion does not start an independent attack');
safe.step([0,0],0,{active:false,immediate:true});
safe.step([12,6],.05,{active:true});
assert.ok(safe.values.every((value,i)=>value>0 && value<targets[i]),'Playback after a safety reset starts a fresh short ramp');
safe.step([0,0],1,{active:false});assert.deepEqual(safe.values,[0,0],'A delayed frame completes an elapsed release');
const rapid=new Envelope();rapid.step(targets,.3,{active:true});rapid.step([0,0],.1,{active:false});
const remaining=[...rapid.values];rapid.step(targets,0,{active:true});
assert.deepEqual(rapid.values,remaining,'Rapid resume continues from the visible release without dropping to zero');
rapid.step(targets,1/120,{active:true});
assert.ok(rapid.values.every((value,i)=>value>=remaining[i] && value-remaining[i]<.1),'First resumed frame rises continuously');
run(rapid,.22,60,true);assert.deepEqual(rapid.values,targets,'Rapid resume returns to current measured levels');
safe.step([NaN,-5],0,{active:true,immediate:true});assert.deepEqual(safe.values,[0,0],'Nonfinite and negative readings do not produce levels');
safe.step([Infinity,100],0,{active:true,immediate:true});assert.deepEqual(safe.values,[15,15],'Display remains bounded by the physical number of bars');
for(const invalid of [NaN,Infinity,-1]){
    const envelope=new Envelope();envelope.step(targets,invalid,{active:true});assert.deepEqual(envelope.values,[0,0],'Invalid elapsed time cannot advance the presentation clock');
}

const browser={window:{}};
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../plugins/reel-player/assets/meters.js'),'utf8'),browser);
assert.equal(typeof browser.window.TapeRoomMeters,'function','The existing browser analyser export remains available');
assert.equal(typeof browser.window.TapeRoomMeterEnvelope,'function','The browser exports the same independent presentation controller');
const browserEnvelope=new browser.window.TapeRoomMeterEnvelope();browserEnvelope.step([5,2],0,{active:true,immediate:true});
assert.deepEqual([...browserEnvelope.values],[5,2],'The browser presentation export runs without an audio graph');
console.log('Meter envelope checks passed: 220 ms real onset, 450 ms release, stereo/refresh invariance, silence, immediate safety and reduced motion, bounded readings, elapsed-time validation and browser exports.');
