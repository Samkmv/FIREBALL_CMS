'use strict';
const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
const path = require('node:path');
const TapeRoomTransport = require('../assets/transport.js');
const TapeRoomMeterEnvelope = require('../assets/meters.js').Envelope;
const source = fs.readFileSync(path.join(__dirname,'../assets/player.js'),'utf8');
const progress = source.slice(source.indexOf('    function setBuffering('),source.indexOf('    const meterController ='));
const animation = source.slice(source.indexOf('    function drawMeters('),source.indexOf('    async function play('));
const events = source.slice(source.indexOf("    audio.addEventListener('play',"),source.indexOf("    $('[data-cover]').addEventListener('error',"));
assert.ok(progress && animation && events, 'The integration harness must execute the actual player functions and lifecycle handlers');

function harness(mode = 'fallback') {
    const counts = {geometry:0,tape:0,bars:0,samples:0,scheduled:0,positions:0,fills:0,cancelledPreloads:0};
    const frames = new Map(), handlers = new Map(), styles = new Map();
    let now = 1000, sequence = 0;
    function shape(radius, tape = false) {
        const attributes={r:radius};
        return {getAttribute:name=>attributes[name],setAttribute(name,value){attributes[name]=value;counts.geometry++;if(tape)counts.tape++;}};
    }
    const visuals = {
        seek:{value:0,style:{getPropertyValue:name=>styles.get(name),setProperty:(name,value)=>styles.set(name,value)}},
        elapsed:{textContent:''}, duration:{textContent:''}, tape:[shape('',true),shape('',true)],
        reels:[0,1].map(i=>({pack:shape(i?'78.00':'174.00'),clip:shape(i?'78.00':'174.00'),tapeMask:shape(i?'78.00':'174.00'),rotor:{style:{}},bars:Array.from({length:15},()=>({classList:{toggle(){counts.bars++;}}}))}))
    };
    if (mode !== 'fallback') for (const reel of visuals.reels) reel.rotor.animate = (keyframes,options) => {
        assert.equal(keyframes[1].transform,'rotate(-360deg)');
        assert.equal(options.iterations,Infinity);
        const motion={playState:'running',pending:false,playbackRate:1,currentTime:137,rates:[],pause(){this.playState='paused';},play(){this.playState='running';}};
        if (mode === 'native') motion.updatePlaybackRate=function(rate){this.playbackRate=rate;this.rates.push(rate);};
        return motion;
    };
    const audio={duration:240,currentTime:0,playbackRate:1,volume:.8,muted:false,paused:false,ended:false,error:null,readyState:4,buffered:{length:1,start:()=>0,end:()=>80},addEventListener(name,callback){handlers.set(name,callback);}};
    const transport=new TapeRoomTransport();
    const meterEnvelope=new TapeRoomMeterEnvelope();
    const context=vm.createContext({audio,visuals,transport,meterEnvelope,window:{TapeRoomTransport,TapeRoomMeterEnvelope},generation:1,positionKey:'',packRadii:[174,78],meterLevels:[0,0],meterSignal:{generation:1,values:[0,0],rms:[0,0],sampled:[-Infinity,-Infinity],drawn:0},meterReport:{samples:[],time:-Infinity,status:null},meterController:{sample(){},readFailure(){},canSample(){return true;},snapshot(){return {phase:'test'};},buffering(){},seek(){},nativeFailure(){}},performance:{now:()=>now},progressFrame:-Infinity,meterFrame:-Infinity,tapeFrame:-Infinity,frameId:0,frameTime:0,frameInProgress:false,angles:[0,0],loading:false,bufferVisible:false,bufferTimer:undefined,seekActive:false,current:1,lastSave:0,activeTrack:()=>({duration:audio.duration,source:'local'}),graph:{context:{state:'running'},samples:new Float32Array(2048),analysers:[0,1].map(()=>({getFloatTimeDomainData(array){counts.samples++;array.fill(.15);}}))},navigator:{mediaSession:{setPositionState(){counts.positions++;}}},document:{hidden:false},reducedMotion:{matches:false},syncChapter(){},playbackPosition:()=>audio.currentTime,formatTime:value=>String(Math.floor(value)),rangeFill(){counts.fills++;},requestAnimationFrame(callback){counts.scheduled++;const id=++sequence;frames.set(id,callback);return id;},cancelAnimationFrame:id=>frames.delete(id),setTimeout:()=>1,clearTimeout(){},updateButtons(){},prepareUpcoming(){},resumeMeters(){},preloader:{cancel(){counts.cancelledPreloads++;}},save(){},next(){},syncMirror(){},toast(){},stopMeters(){context.drawMeters(false);}});
    context.drawAudioQuality=()=>{};
    context.sampleListening=()=>{};
    context.startListening=()=>{};
    context.listening=null;
    vm.runInContext(progress+animation+events,context);
    // Production initializes the display with a paused draw while loading.
    context.drawMeters(false);
    function tick(milliseconds = 1000/120, advance = true) {
        const first = frames.entries().next();
        if (first.done) return false;
        const [id,callback] = first.value; frames.delete(id); now += milliseconds;
        if (advance && !audio.paused && !audio.ended && !audio.error && !context.loading) audio.currentTime += milliseconds/1000;
        callback(now); return true;
    }
    function run(milliseconds, fps = 120) {
        const count = Math.ceil(milliseconds/(1000/fps));
        for(let n=0;n<count;n++) assert.ok(tick(1000/fps), 'Animation ended before frame '+n+' of '+count);
    }
    function settle() {
        let count = 0;
        while (frames.size && count++ < 360) tick();
        assert.ok(count < 360,'Stopped tape settles and stops requesting frames within three seconds');
        assert.equal(frames.size,0,'Stopped animation does not retain a pending frame');
        assert.equal(transport.speed,0,'Settled reels stop exactly');
        assert.equal(transport.slack,8,'Stopped tape returns to a restrained resting sag');
        assert.equal(transport.moving,false,'Settled transport has no residual spring motion');
        assert.equal(meterEnvelope.moving,false,'Settled meter presentation has no residual animation');
        assert.ok(context.meterLevels.every(level=>level===0),'Stopped real indicators are completely off');
        return count;
    }
    return {audio,visuals,transport,meterEnvelope,context,counts,frames,tick,run,settle,advanceClock:ms=>{now+=ms;},fire:name=>handlers.get(name)()};
}

// A high-refresh display must not multiply geometry, analysis or system updates.
const high = harness(); high.context.syncReels(); high.run(1000);
const {audio,visuals,context,counts} = high;
assert.ok(counts.fills>=9 && counts.fills<=11,'Progress updates are 10 Hz, got '+counts.fills);
assert.ok(counts.samples>=46 && counts.samples<=62,'Stereo analysis stays live and at most 30 Hz, got '+counts.samples);
assert.ok(counts.geometry<=145,'Changing pack, acrylic mask and tension geometry stays bounded, got '+counts.geometry);
assert.ok(counts.tape<=85,'Tape paths use bounded geometry updates, got '+counts.tape);
assert.equal(counts.positions,1,'Frame-level playback updates do not spam the system player within one second');
audio.currentTime=1;context.drawProgress();assert.equal(counts.positions,2,'Crossing a second updates the system position key');
assert.ok(context.angles.every(angle=>angle<0),'Both fallback reels rotate counterclockwise');
assert.ok(context.angles[1]<context.angles[0],'Small take-up pack rotates faster');
const low=harness();low.context.syncReels();low.run(1000,60);
assert.ok(Math.abs(low.counts.geometry-counts.geometry)<=30,'Geometry cadence is independent of screen refresh rate');
const beforeGeometry=counts.geometry,beforeBars=counts.bars,beforePositions=counts.positions;
context.drawProgress();context.drawMeters(true);
assert.equal(counts.geometry,beforeGeometry,'Unchanged tape geometry does not repaint');
assert.equal(counts.bars,beforeBars,'Unchanged meter bars do not receive class mutations');
assert.equal(counts.positions,beforePositions,'Repeated progress event does not spam the system player');
audio.currentTime=.2;context.drawProgress(true);assert.equal(counts.positions,beforePositions+1,'Seek updates system position immediately');
for (const fraction of [0,.25,.5,.75,1]) {
    audio.duration=7200;audio.currentTime=7200*fraction;
    const nativePosition=audio.currentTime,nativePaused=audio.paused;
    context.drawProgress();
    assert.ok(context.packRadii.every(radius=>radius>=78 && radius<=174),'Long recordings retain physical pack radius bounds');
    for (const reel of visuals.reels) {
        assert.equal(reel.clip.getAttribute('r'),reel.pack.getAttribute('r'),'Tape winding and rotor clipping share the same physical radius');
        assert.equal(reel.tapeMask.getAttribute('r'),reel.pack.getAttribute('r'),'Clear acrylic reveals the actual tape pack at every recording position');
    }
    const expectedArea=174**2+78**2;
    assert.ok(Math.abs(context.packRadii.reduce((sum,radius)=>sum+radius**2,0)-expectedArea)<1e-8,'Winding conserves the combined tape area');
    assert.ok(Math.abs(visuals.reels.reduce((sum,reel)=>sum+Number(reel.tapeMask.getAttribute('r'))**2,0)-expectedArea)<4,'Rendered acrylic masks conserve tape area within two-decimal rounding');
    assert.equal(audio.currentTime,nativePosition,'Mask and winding updates never seek the original audio');
    assert.equal(audio.paused,nativePaused,'Mask and winding updates never change native playback');
    for (const tape of visuals.tape) assert.ok(/^M[\d. -]+(?: C[\d. -]+){3} A29\.75 29\.75/.test(tape.getAttribute('d')) && !/NaN|Infinity/.test(tape.getAttribute('d')),'Thin tape remains a finite, continuous curved tangent around the guide with its cap hidden under the pack');
}

// Run the real pause/waiting/playing/ended/error handlers, not copies of their logic.
for (const mode of ['fallback','native','native-direct-rate']) {
    for (const lifecycle of ['pause','ended','error']) {
        const test=harness(mode);test.context.syncReels();test.run(700);
        const soundPosition=test.audio.currentTime;
        const initialLevels=[...test.context.meterLevels],initialReads=test.counts.samples;
        if(lifecycle==='pause')test.audio.paused=true;
        if(lifecycle==='ended')test.audio.ended=true;
        if(lifecycle==='error')test.audio.error={code:4};
        test.fire(lifecycle);
        assert.equal(test.transport.speed,1,mode+'/'+lifecycle+': stop does not snap the reels');
        test.run(100);
        assert.ok(test.transport.speed>0 && test.transport.speed<1,mode+'/'+lifecycle+': reels gradually brake');
        if(lifecycle==='error')assert.ok(test.context.meterLevels.every(level=>level===0),mode+'/'+lifecycle+': a media error clears old displayed signal immediately');
        else assert.ok(test.context.meterLevels.every((level,index)=>level>0 && level<initialLevels[index]),mode+'/'+lifecycle+': real measured indicators decay smoothly after stopping');
        assert.ok(test.context.meterSignal.rms.every(rms=>rms===0),mode+'/'+lifecycle+': real samples remain zero during the visual release');
        assert.equal(test.counts.samples,initialReads,mode+'/'+lifecycle+': release never reads a paused analyser');
        test.run(400);assert.ok(test.context.meterLevels.every(level=>level===0),mode+'/'+lifecycle+': displayed indicators are off after 500 ms');
        test.settle();
        assert.equal(test.audio.currentTime,soundPosition,mode+'/'+lifecycle+': visual inertia never advances native sound');
        assert.ok(test.visuals.reels.every(reel=>!reel.motion || reel.motion.playState==='paused'),mode+'/'+lifecycle+': native animations pause at rest');
        const queued=test.counts.scheduled;test.context.drawProgress();test.context.syncReels();
        assert.equal(test.counts.scheduled,queued,mode+'/'+lifecycle+': repeated progress does not restart a settled loop');
    }
    const test=harness(mode);test.context.syncReels();test.run(700);
    const preWaitingLevels=[...test.context.meterLevels],preWaitingReads=test.counts.samples;
    test.fire('waiting');assert.equal(test.context.loading,true,'Waiting begins actual buffering');
    assert.equal(test.transport.speed,1,'Buffering begins a smooth brake');
    test.run(100);
    assert.ok(test.context.meterLevels.every((level,index)=>level>0 && level<preWaitingLevels[index]),'Waiting releases only the last measured display smoothly');
    assert.ok(test.context.meterSignal.rms.every(rms=>rms===0),'Buffering real RMS stays zero while the old display releases');
    test.run(1700);
    assert.equal(test.counts.samples,preWaitingReads,'Buffering release never samples the stalled analysis stream');
    assert.equal(test.transport.speed,0,'Waiting reels stop after the brake');
    assert.equal(test.transport.moving,false,'Waiting tape spring also settles');
    assert.ok(test.context.meterLevels.every(level=>level===0),'No fabricated meter signal during buffering');
    assert.ok(test.visuals.reels.every(reel=>!reel.motion || reel.motion.playState==='paused'),'Waiting pauses native rotor animations');
    test.fire('playing');assert.equal(test.context.loading,false,'Playing ends buffering');
    assert.equal(test.transport.speed,0,'A fresh playback starts without a speed jump');
    test.run(100);assert.ok(test.transport.speed>0 && test.transport.speed<1,'Playing accelerates smoothly');
    assert.ok(test.context.meterLevels.every((level,index)=>level>0 && level<preWaitingLevels[index]),'New real samples ramp into the indicator display on resume');
    assert.ok(test.context.meterSignal.rms.every(rms=>rms>0),'Resume ramp represents newly measured audio');
    const rampSpeed=test.transport.speed;test.audio.paused=true;test.fire('pause');test.run(100);
    assert.ok(test.transport.speed>0 && test.transport.speed<rampSpeed,'Pausing an unfinished ramp brakes from the current speed');
    const coastSpeed=test.transport.speed;test.audio.paused=false;test.fire('playing');
    assert.equal(test.transport.speed,coastSpeed,'Rapid relaunch preserves the current speed');
    test.run(100);assert.ok(test.transport.speed>coastSpeed && test.transport.speed<1,'Rapid relaunch ramps from the coast speed');
    test.run(600);assert.equal(test.transport.speed,1,'Running transport reaches its exact nominal speed');
    if(mode!=='fallback'){
        const oldTransforms=test.visuals.reels.map(reel=>reel.rotor.style.transform);
        test.run(100);assert.deepEqual(test.visuals.reels.map(reel=>reel.rotor.style.transform),oldTransforms,'WAAPI rotation is not overwritten by frame transforms');
        test.audio.playbackRate=1.5;test.context.packRadii=[100,160];test.context.syncReels();
        assert.ok(test.visuals.reels.every(reel=>reel.motion.currentTime===137),'WAAPI speed changes preserve rotor phase');
        assert.ok(Math.abs(test.visuals.reels[0].motion.playbackRate-1.89)<1e-10,'WAAPI speed reflects pack radius and native playback rate');
        assert.ok(Math.abs(test.visuals.reels[1].motion.playbackRate-1.18125)<1e-10,'Both WAAPI rates follow the current tape pack');
    }else{
        const oldAngles=[...test.context.angles];test.audio.playbackRate=1.5;test.context.packRadii=[100,160];
        // Keep the geometry pass outside this frame so the known radii are retained.
        test.context.progressFrame=Infinity;test.tick(10);
        assert.ok(Math.abs((test.context.angles[0]-oldAngles[0])/(test.context.angles[1]-oldAngles[1])-1.6)<1e-8,'Fallback speed uses each physical pack radius');
    }
    for(const frozen of ['hidden','reduced']){
        if(frozen==='hidden')test.context.document.hidden=true;else test.context.reducedMotion.matches=true;
        const oldAngles=[...test.context.angles],position=test.audio.currentTime;
        test.context.syncReels();test.context.drawTape();
        assert.equal(test.transport.speed,0,frozen+': visual motion freezes immediately');
        assert.ok(test.visuals.reels.every(reel=>!reel.motion || reel.motion.playState==='paused'),frozen+': native reels freeze immediately');
        test.tick(16,false);
        assert.deepEqual([...test.context.angles],oldAngles,frozen+': no fallback rotation remains');
        assert.equal(test.audio.paused,false,frozen+': native audio is unaffected');
        assert.equal(test.audio.currentTime,position,frozen+': visual lifecycle never seeks the audio');
        if(frozen==='hidden')test.context.document.hidden=false;else test.context.reducedMotion.matches=false;
        test.context.syncReels();test.context.progressFrame=-Infinity;test.run(700);
        assert.equal(test.transport.speed,1,frozen+': returning to the deck ramps back to normal speed');
    }
}

// Existing real-audio meter regressions stay independent of visual inertia.
audio.duration=240;audio.currentTime=.2;
audio.muted=true;context.drawMeters(true);assert.ok(context.meterLevels.every(level=>level===0),'Mute clears both real levels');
audio.muted=false;high.advanceClock(50);context.drawMeters(true);assert.ok(context.meterLevels.every(level=>level>0),'Fresh measured signal appears during the short unmute ramp');
const nativeTime=audio.currentTime;context.graph.mirror={paused:false,seeking:true,readyState:3,currentTime:nativeTime};context.drawMeters(true);
assert.ok(context.meterLevels.every(level=>level>0),'A brief analysis seek decays the last measured signal');
high.advanceClock(181);context.drawMeters(true);assert.ok(context.meterLevels.every(level=>level===0),'Missing samples are cleared within the bounded grace');
assert.equal(audio.currentTime,nativeTime,'Meter never changes native playback position');
context.graph.mirror.seeking=false;high.advanceClock(50);context.drawMeters(true);assert.ok(context.meterLevels.every(level=>level>0),'Recovered real samples restart a bounded display ramp');
context.graph.mirror.currentTime=audio.currentTime-.85;context.drawMeters(true);assert.ok(context.meterLevels.every(level=>level>0),'Clock drift alone cannot blank a playing real analyser');
audio.seeking=true;context.drawMeters(true);assert.ok(context.meterLevels.every(level=>level===0),'User seeking clears old-song levels immediately');audio.seeking=false;context.drawMeters(true);
for(let n=0;n<1800;n++){
    high.advanceClock(1000/30);audio.currentTime+=1/30;context.graph.mirror.currentTime=audio.currentTime-.2;
    context.graph.mirror.readyState=n%60<2?1:2;context.drawMeters(true);
    assert.ok(context.meterLevels.every(level=>level>0),'Brief decoder fluctuation at frame '+n);
}
for(const analyser of context.graph.analysers)analyser.getFloatTimeDomainData=array=>array.fill(0);
for(let n=0;n<20;n++){high.advanceClock(1000/30);context.drawMeters(true);}
assert.ok(context.meterLevels.every(level=>level===0),'Real silence settles to zero without fabricated movement');
audio.src='https://private.invalid/secret-song?token=secret';audio.title='Private music';
for(let n=0;n<200;n++){high.advanceClock(500);context.recordMeterDiagnostic();}
assert.equal(context.meterReport.samples.length,180,'Device diagnostics retain only a bounded history');
const retry={hidden:true};context.$=()=>retry;context.dialog={open:true};context.meterReport.status={isConnected:true,textContent:''};context.meterController.needsGesture=true;context.recordMeterDiagnostic(true);assert.equal(retry.hidden,false,'Manual activation is offered only for confirmed autoplay refusal');audio.paused=true;context.recordMeterDiagnostic(true);assert.equal(retry.hidden,true,'Paused music does not request activation');audio.paused=false;context.meterController.needsGesture=false;context.recordMeterDiagnostic(true);assert.equal(retry.hidden,true,'Healthy analysis does not request a gesture');context.meterReport.status=null;
const diagnostic=JSON.stringify(context.meterReport.samples);assert.ok(!diagnostic.includes('secret')&&!diagnostic.includes('Private music'),'Diagnostics contain no URLs, tokens or track names');
assert.ok(context.meterReport.samples.every(sample=>sample.analysis.rms.every(rms=>rms===0)),'Diagnostics distinguish actual zero samples from a frozen clock');
high.advanceClock(100000);context.recordMeterDiagnostic();assert.equal(context.meterReport.samples.length,1,'Old history expires after a long pause or background interval');

// Safety boundaries still clear stale display immediately instead of extending
// the cosmetic pause release across a seek, hidden page or expired sample.
for(const [name,change] of [
    ['mute',test=>{test.audio.muted=true;}],
    ['zero-volume',test=>{test.audio.volume=0;}],
    ['native-seek',test=>{test.audio.seeking=true;}],
    ['hidden',test=>{test.context.document.hidden=true;}],
    ['terminal-analysis',test=>{test.context.meterController.terminal=true;}],
    ['autoplay-refusal',test=>{test.context.meterController.needsGesture=true;}],
    ['closed-context',test=>{test.context.graph.context.state='closed';}]
]){
    const test=harness();test.context.syncReels();test.run(700);
    const position=test.audio.currentTime,reads=test.counts.samples;
    change(test);test.context.drawMeters(true);
    assert.ok(test.context.meterLevels.every(level=>level===0),name+': unsafe or inaudible samples clear the display immediately');
    assert.ok(test.context.meterSignal.rms.every(rms=>rms===0),name+': diagnostics do not retain stale real RMS');
    assert.equal(test.counts.samples,reads,name+': safety reset does not ask for new samples');
    assert.equal(test.meterEnvelope.moving,false,name+': no hidden cosmetic release continues');
    assert.equal(test.audio.currentTime,position,name+': meter safety never seeks or advances native sound');
    assert.equal(test.audio.paused,false,name+': meter safety never pauses native sound');
}
const reduced=harness();reduced.context.reducedMotion.matches=true;reduced.fire('playing');reduced.run(50);
assert.ok(reduced.context.meterLevels.every(level=>level>0),'Reduced motion displays actual levels without an onset animation');
assert.equal(reduced.meterEnvelope.moving,false,'Reduced motion adds no envelope animation');
reduced.audio.paused=true;reduced.fire('pause');assert.ok(reduced.context.meterLevels.every(level=>level===0),'Reduced motion pauses indicators immediately');
const replaced=harness();replaced.context.syncReels();replaced.run(700);
replaced.context.generation++;
for(const analyser of replaced.context.graph.analysers)analyser.getFloatTimeDomainData=array=>array.fill(0);
replaced.context.drawMeters(true);assert.ok(replaced.context.meterLevels.every(level=>level===0),'A new recording never inherits the previous recording\'s envelope');
const envelopeOnly=harness();envelopeOnly.context.syncReels();envelopeOnly.run(700);
envelopeOnly.audio.paused=true;envelopeOnly.transport.reset();envelopeOnly.fire('pause');
envelopeOnly.run(100);assert.ok(envelopeOnly.context.meterLevels.every(level=>level>0),'A remaining meter release gets frames even if the reels are already at rest');
envelopeOnly.settle();
console.log('Animation checks passed: smooth tape and meter stop/relaunch, real RMS during release, immediate safety resets, buffering/error lifecycle, bounded 120 Hz geometry, WAAPI/fallback rates, hidden/reduced motion, privacy and settled animation cleanup.');
