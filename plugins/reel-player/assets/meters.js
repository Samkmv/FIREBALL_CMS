(() => {
    'use strict';
    // Analysis owns its own resources. It never pauses, routes, seeks or changes
    // the rate of the audible HTML Audio element.
    class TapeRoomMeters {
        constructor({audio,generation,source,bufferedAhead,changed=()=>{},env=globalThis}) {
            Object.assign(this,{audio,generation,source,bufferedAhead,changed,env});
            this.engine = null; this.trackGeneration = -1; this.request = 0;
            this.phase = 'idle'; this.reason = ''; this.needsGesture = false;
            this.events = []; this.rebuildTimes = []; this.timer = null;
            this.counts = {contexts:0,copies:0,loads:0,seeks:0,rateWrites:0,rebuilds:0,retries:0,activations:0,sampleErrors:0};
            this.lastOrigin = 'automatic'; this.lastActivation = false; this.forceMirror = false;
            this.rms = [0,0]; this.firstSignal = null; this.sampleTime = null;
            this.waitingSince = null; this.stableSince = null; this.yielded = false; this.restartAfter = 0;
        }
        now() { return this.env.performance.now(); }
        state(phase,reason='') {
            if (phase !== this.phase || reason !== this.reason) {
                this.phase = phase; this.reason = reason;
                this.events.push({ms:Math.round(this.now()),generation:this.trackGeneration,phase,reason,origin:this.lastOrigin,activation:this.lastActivation});
                while (this.events.length > 80 || this.now()-this.events[0].ms > 90000) this.events.shift();
                this.changed();
            }
        }
        pruneEvents() { const now=this.now();while(this.events.length && (this.events.length>80 || now-this.events[0].ms>90000))this.events.shift(); }
        activation(origin,event) {
            // A boolean or a saved event is not user activation. Callers pass a
            // trusted input synchronously; modern browsers must also confirm it.
            return origin === 'user' && event?.isTrusted === true && ['click','pointerup','touchend','keydown'].includes(event.type)
                && this.env.navigator.userActivation?.isActive !== false;
        }
        newGeneration() {
            const generation = this.generation();
            if (this.trackGeneration === generation) return;
            this.trackGeneration = generation; this.request++;
            this.started = null; this.firstSignal = this.sampleTime = null; this.rms = [0,0];
            this.copyRetries = this.trackRebuilds = 0; this.retryAt = null; this.terminal = false;
            this.networkRecoveries = 0; this.lastFailure = null;
            this.mediaError = 0; this.nativeError = 0; this.driveStatus = null;
            this.waitingSince = this.stableSince = null; this.yielded = false; this.restartAfter = 0;
            if (this.engine) { this.engine.watch = null; this.engine.mirrorStarting = null; this.engine.aligned = false; this.engine.seekRequested = false; }
            // Keep a genuine autoplay refusal until a real input unlocks it.
            this.state(this.needsGesture ? 'activation-required' : 'starting','track-change');
        }
        resume(origin='automatic',event) {
            const activation = this.activation(origin,event);
            this.lastOrigin = ['user','automatic','system','lifecycle'].includes(origin) ? origin : 'automatic';
            this.lastActivation = activation; this.newGeneration();
            if(this.lastOrigin!=='lifecycle')this.playOrigin=this.lastOrigin;
            if (this.env.document.hidden || !this.audio.src) { this.stop('hidden'); return; }
            if (activation) {
                this.counts.activations++;this.lastActivationMs=this.now();
                if(this.terminal||this.retryAt!==null)this.unload('manual-retry');
                this.needsGesture = this.terminal = false; this.retryAt = null; this.copyRetries = 0;
                this.gestureUntil = this.now()+1500;
                // Only objective engine failure justifies a new context/copy.
                if (this.engine?.context.state === 'closed' || (this.engine?.resumeJob && this.now()-this.engine.resumeStarted >= 8000)) this.release('trusted-engine-recovery');
            } else if ((this.audio.paused || this.audio.ended) && !(this.gestureUntil > this.now())) { this.stop('paused'); return; }
            this.started??=this.now();
            if (this.stopped) {
                if (this.engine?.resumeJob) this.engine.resumeStarted = this.now();
                this.stopped = false;
            }
            if (!this.engine && !this.terminal) this.create();
            if (!this.timer && !this.terminal) this.timer = this.env.setInterval(()=>this.tick(),500);
            if (activation && this.engine) {
                try {
                    const context = this.engine.context, wake = context.createBufferSource();
                    wake.buffer = context.createBuffer(1,1,context.sampleRate);
                    wake.connect(context.destination); wake.onended = ()=>wake.disconnect(); wake.start();
                } catch (_) { /* Silent gesture activation is optional. */ }
            }
            this.tick(activation);
        }
        create() {
            const Context = this.env.window?.AudioContext || this.env.window?.webkitAudioContext || this.env.AudioContext || this.env.webkitAudioContext;
            if (!Context) { this.terminal = true; this.state('unsupported','web-audio-unavailable'); return; }
            let context;
            try {
                context = new Context(); this.counts.contexts++;
                const splitter = context.createChannelSplitter(2), input = context.createGain(), sink = context.createGain();
                // Source channelCount does not upmix a mono source's output.
                // Explicit speaker mixing before splitting duplicates real mono
                // into L/R and leaves a stereo pair separate.
                input.channelCount = 2; input.channelCountMode = 'explicit'; input.channelInterpretation = 'speakers'; input.connect(splitter);
                sink.gain.value = 0; sink.connect(context.destination);
                const analysers = [context.createAnalyser(),context.createAnalyser()];
                analysers.forEach((node,i)=>{node.fftSize=2048;splitter.connect(node,i);node.connect(sink);});
                const capture = this.forceMirror ? null : this.audio.captureStream || this.audio.mozCaptureStream;
                const engine = this.engine = {context,input,splitter,analysers,samples:new Float32Array(2048),capture,source:null,bindings:[],watch:null};
                context.addEventListener('statechange',()=>{if(this.engine===engine&&!engine.resumeJob)this.tick();});
                if (capture) this.bindCapture(true); else this.initMirror();
                this.state('starting','engine-created');
            } catch (_) {
                this.engine = null; context?.close().catch(()=>{}); this.terminal = true; this.state('unavailable','engine-create-failed');
            }
        }
        detachCapture(engine=this.engine) {
            if (!engine?.stream) return;
            for(const [node,event,handler] of engine.captureBindings || []) node.removeEventListener(event,handler);
            engine.source?.disconnect(); engine.source=null;
            engine.stream.getTracks().forEach(track=>track.stop()); engine.stream=null; engine.capturedTrack=null;
        }
        bindCapture(refresh=false) {
            const e = this.engine; if(!e?.capture)return;
            try {
                if(refresh || e.captureGeneration!==this.trackGeneration) {
                    this.detachCapture(); e.stream=e.capture.call(this.audio); e.captureGeneration=this.trackGeneration; e.captureStarted=this.now();e.captureBindings=[];
                    for(const event of ['addtrack','removetrack']) {const fn=()=>{if(this.engine===e)this.bindCapture();};e.stream.addEventListener(event,fn);e.captureBindings.push([e.stream,event,fn]);}
                }
                const track=e.stream.getAudioTracks().find(track=>track.readyState==='live');
                if(track===e.capturedTrack)return;
                e.source?.disconnect();e.source=null;e.capturedTrack=track;
                if(!track)return;
                for(const event of ['mute','ended']) {const fn=()=>{if(this.engine===e&&!this.env.document.hidden)this.initMirror('capture-'+event);};track.addEventListener(event,fn);e.captureBindings.push([track,event,fn]);}
                e.source=e.context.createMediaStreamSource(new this.env.MediaStream([track]));e.source.connect(e.input);
                this.warmSamples();
            } catch (_) { this.initMirror('capture-failed'); }
        }
        initMirror(reason='capture-unavailable') {
            const e=this.engine;if(!e||e.mirror)return;
            this.detachCapture();e.capture=null;this.forceMirror=true;
            e.mirror=new this.env.Audio();this.counts.copies++;
            e.mirror.preload='auto';e.mirror.setAttribute('playsinline','');
            e.source=e.context.createMediaElementSource(e.mirror);e.source.connect(e.input);
            this.state('starting',reason);
        }
        warmSamples() { const e=this.engine;if(e)e.sampleAfterClock=e.context.currentTime+2048/e.context.sampleRate; }
        canSample() { const e=this.engine;return Boolean(e&&!this.terminal&&!this.needsGesture&&this.retryAt===null&&e.context.state==='running'&&e.context.currentTime>=(e.sampleAfterClock??Infinity)); }
        clearBindings(e) { for(const [event,fn] of e.bindings)e.mirror.removeEventListener(event,fn);e.bindings=[]; }
        unload(reason) {
            const e=this.engine;if(!e?.mirror)return;
            this.request++;e.mirrorStarting=null;e.aligned=false;e.watch=null;e.seekStarted=null;
            this.clearBindings(e);e.mirror.pause();
            if(e.mirror.hasAttribute('src')) {e.mirror.removeAttribute('src');e.mirror.load();this.counts.loads++;}
            this.state('yielded',reason);
        }
        release(reason) {
            const e=this.engine;if(!e)return;
            this.detachCapture(e);this.unload(reason);this.engine=null;
            e.source?.disconnect();e.context.close().catch(()=>{});this.changed();
        }
        stop(reason='paused') {
            this.env.clearInterval(this.timer);this.timer=null;this.stopped=true;this.gestureUntil=0;
            this.request++;
            if(this.engine) {this.engine.watch=null;this.engine.mirrorStarting=null;this.engine.copyGeneration=-1;this.engine.mirror?.pause();}
            if(['hidden','native-error'].includes(reason))this.unload(reason);
            this.rms=[0,0];this.state(reason==='hidden'?'hidden':'paused',reason);
        }
        buffering(waiting) {
            this.newGeneration();
            if(waiting) {if(this.waitingSince===null)this.waitingSince=this.now();this.stableSince=null;}
            else if(this.waitingSince!==null) {this.waitingSince=null;this.stableSince=this.now();}
            this.tick();
        }
        fail(domain,reason,code=0) {
            this.lastFailure={domain,reason,code,ms:Math.round(this.now()),generation:this.trackGeneration};
            this.mediaError=code || this.mediaError;
            if(domain==='context') {
                this.rebuildTimes=this.rebuildTimes.filter(time=>this.now()-time<60000);
                if(this.trackRebuilds>=1 || this.rebuildTimes.length>=3) {this.terminal=true;this.unload('context-recovery-exhausted');this.state('unavailable',reason);return;}
                this.trackRebuilds++;this.counts.rebuilds++;this.rebuildTimes.push(this.now());
                this.release(reason);this.create();return;
            }
            this.engine?.mirror?.pause();
            if(reason==='play-NotAllowedError'||reason==='resume-NotAllowedError') {this.needsGesture=true;this.state('activation-required',reason);return;}
            if(reason==='play-NotSupportedError'||[3,4].includes(code)) {this.terminal=true;this.unload('unsupported-copy');this.state('unsupported',reason);return;}
            if(this.retryAt!==null)return;
            if(this.copyRetries>=2) {this.terminal=true;this.unload('copy-recovery-exhausted');this.state('unavailable',reason);return;}
            this.copyRetries++;this.counts.retries++;this.retryAt=this.now()+2000*this.copyRetries;
            this.state('retry-wait',reason);
        }
        startContext(e) {
            if(e.context.state==='running')return;
            if(e.resumeJob) {if(this.now()-e.resumeStarted>=8000)this.fail('context','context-resume-timeout');return;}
            e.resumeStarted=this.now();const job={generation:this.trackGeneration};e.resumeJob=job;
            this.state('context-starting',e.context.state);
            try {
                e.context.resume().then(()=>{if(this.engine===e&&e.resumeJob===job){e.resumeJob=null;this.tick();}})
                    .catch(error=>{if(this.engine!==e||e.resumeJob!==job)return;e.resumeJob=null;if(job.generation!==this.generation()){this.tick();return;}if(error.name==='NotAllowedError')this.fail('copy','resume-NotAllowedError');else this.fail('context','context-resume-failed');});
            } catch (_) {e.resumeJob=null;this.fail('context','context-resume-failed');}
        }
        loadCopy(e,url) {
            this.clearBindings(e);const request=++this.request,generation=this.trackGeneration;
            e.mirrorStarting=null;e.aligned=false;e.driftSince=null;e.lastSeek=-Infinity;e.seekStarted=null;e.watch=null;
            e.loadStarted=this.now();e.decodeStarted=null;e.copyGeneration=this.trackGeneration;
            e.sampleAfterClock=Infinity;
            const valid=()=>this.engine===e&&request===this.request&&generation===this.generation()&&e.mirror.src===url
                &&(!e.mirror.currentSrc||e.mirror.currentSrc===url);
            for(const event of ['loadedmetadata','canplay','playing','seeked','pause','ended','waiting']) {
                const fn=()=>{if(!valid())return;if(event==='seeked')e.seekStarted=null;if(event==='playing'||event==='seeked')this.warmSamples();this.tick();};
                e.mirror.addEventListener(event,fn);e.bindings.push([event,fn]);
            }
            const error=()=>{if(valid()&&e.mirror.error&&e.mirror.error.code!==1)this.fail('copy','media-error',e.mirror.error.code);};
            e.mirror.addEventListener('error',error);e.bindings.push(['error',error]);
            if(e.mirror.src!==url){e.mirror.src=url;this.counts.loads++;} // src selects the resource; no second load().
            this.state('copy-loading','source-selected');
        }
        syncCopy(activation) {
            const e=this.engine,m=e?.mirror;if(!m)return;
            if(this.needsGesture||this.terminal||this.retryAt!==null||this.env.navigator.onLine===false)return;
            const cloud=this.source()==='drive',connection=this.env.navigator.connection;
            if(cloud&&(connection?.saveData||['slow-2g','2g'].includes(connection?.effectiveType))) {if(m.hasAttribute('src'))this.unload('data-saving');return;}
            if(this.waitingSince!==null&&!activation) {
                if(cloud&&this.now()-this.waitingSince>=1500&&!this.yielded) {
                    this.yielded=true;this.restartAfter=this.now()+3000;this.unload('native-buffering');
                }
                return;
            }
            if(this.yielded&&!activation) {
                if(this.now()<this.restartAfter||this.stableSince===null||this.now()-this.stableSince<1500||(this.audio.readyState<3&&this.bufferedAhead()<1))return;
                this.yielded=false;
            }
            if(this.audio.seeking)return;
            if(cloud&&!activation&&!m.hasAttribute('src')&&this.audio.readyState<3&&this.bufferedAhead()<.25)return;
            const url=new this.env.URL(this.audio.src,this.env.location.href);if(cloud)url.searchParams.set('meter','1');
            if(m.src!==url.href||e.copyGeneration!==this.trackGeneration)this.loadCopy(e,url.href);
            const drift=this.audio.currentTime-m.currentTime,now=this.now();e.drift=drift;
            if(Math.abs(drift)>.75&&!m.paused&&!m.seeking&&e.aligned) e.driftSince??=now;else e.driftSince=null;
            const resync=e.driftSince!==null&&now-e.driftSince>=1000&&now-e.lastSeek>=4000;
            if(m.readyState>=1&&!m.seeking&&(!e.aligned||m.ended||resync)) {
                try {if(Math.abs(drift)>.1||m.ended||e.seekRequested){m.currentTime=this.audio.currentTime;e.seekStarted=now;e.lastSeek=now;this.counts.seeks++;}e.aligned=true;e.seekRequested=false;e.driftSince=null;e.watch=null;} catch(_){this.state('copy-seeking','seek-pending');}
            }
            if(m.playbackRate!==this.audio.playbackRate){m.playbackRate=this.audio.playbackRate;this.counts.rateWrites++;}
            if(m.paused&&!e.mirrorStarting) {
                const job={request:this.request,generation:this.trackGeneration,time:now};e.mirrorStarting=job;
                const valid=()=>this.engine===e&&e.mirrorStarting===job&&job.request===this.request&&job.generation===this.generation();
                try {m.play().then(()=>{if(valid()){e.mirrorStarting=null;this.tick();}}).catch(error=>{if(!valid())return;e.mirrorStarting=null;this.fail('copy',['NotAllowedError','NotSupportedError','AbortError'].includes(error.name)?'play-'+error.name:'play-failed');});}
                catch(_){e.mirrorStarting=null;this.fail('copy','play-failed');}
            }
        }
        tick(activation=false) {
            if(this.ticking)return;this.ticking=true;
            try {
                this.newGeneration();
                if(this.env.document.hidden)return;
                if((this.audio.paused||this.audio.ended)&&!(this.gestureUntil>this.now()))return;
                const e=this.engine;if(!e||this.terminal)return;
                if(e.context.state==='closed'){this.fail('context','context-closed');return;}
                if(this.needsGesture&&!activation)return;
                this.startContext(e);if(this.engine!==e)return;
                if(this.env.navigator.onLine===false){e.watch=null;this.state('offline','network-offline');return;}
                if(e.capture) {
                    this.bindCapture();
                    if(!e.capturedTrack&&this.audio.readyState>=2&&this.now()-e.captureStarted>=1500)this.initMirror('capture-no-track');
                    else if(e.capturedTrack?.muted)this.initMirror('capture-muted');
                }
                if(this.retryAt!==null) {
                    if(this.now()<this.retryAt||this.env.navigator.onLine===false||this.waitingSince!==null)return;
                    this.retryAt=null;this.unload('bounded-retry');
                }
                this.syncCopy(activation);
                if(this.engine!==e||this.needsGesture||this.terminal||this.retryAt!==null)return;
                // A suspended clock is expected while resume is pending. Its
                // eight-second deadline must not be pre-empted by decode health.
                if(e.context.state!=='running'){e.watch=null;return;}
                if(this.waitingSince!==null||this.audio.seeking||this.audio.readyState<2){e.watch=null;return;}
                const now=this.now(),m=e.mirror;
                if(m?.hasAttribute('src')) {
                    if(m.readyState<2&&now-e.loadStarted>=20000){this.fail('copy','copy-load-timeout');return;}
                    if(m.seeking){e.seekStarted??=now;if(now-e.seekStarted>=8000)this.fail('copy','copy-seek-timeout');return;}
                    if(m.readyState>=2)e.decodeStarted??=now;
                    if(e.mirrorStarting&&now-e.mirrorStarting.time>=8000&&m.paused){this.fail('copy','copy-play-timeout');return;}
                }
                if(!e.watch){e.watch={time:now,native:this.audio.currentTime,clock:e.context.currentTime,copy:m?.currentTime};return;}
                if(now-e.watch.time<3000)return;
                const progressing=this.audio.currentTime-e.watch.native>.1;
                if(progressing&&e.context.currentTime-e.watch.clock<.02){this.fail('context','context-clock-stalled');return;}
                if(progressing&&m?.hasAttribute('src')&&m.readyState>=2&&m.currentTime-e.watch.copy<.02){this.fail('copy','copy-decode-stalled');return;}
                e.watch={time:now,native:this.audio.currentTime,clock:e.context.currentTime,copy:m?.currentTime};
                this.changed();
            } catch (_) { this.terminal=true;try{this.unload('analysis-exception');}catch(_){}this.state('unavailable','analysis-exception'); }
            finally {this.ticking=false;}
        }
        seek() {if(this.engine){this.engine.aligned=false;this.engine.seekRequested=true;this.engine.watch=null;}this.rms=[0,0];this.tick();}
        network(online) {
            const e=this.engine;if(e){e.watch=null;e.loadStarted=this.now();e.seekStarted=null;}
            if(online&&this.lastFailure?.domain==='copy'&&!this.needsGesture&&!['play-NotSupportedError'].includes(this.lastFailure.reason)&&![3,4].includes(this.lastFailure.code)&&this.networkRecoveries<1) {
                this.networkRecoveries++;this.copyRetries=0;this.terminal=false;this.retryAt=this.now()+1500;this.state('retry-wait','network-restored');
            }
            this.tick();
        }
        sample(channel,rms) {
            if(this.terminal||this.needsGesture||this.retryAt!==null)return;
            this.rms[channel]=Number.isFinite(rms)?rms:0;this.sampleTime=this.now();
            if(rms>1e-8&&this.firstSignal===null)this.firstSignal=this.now()-this.started;
            // Moving clocks or successful getFloatTimeDomainData are not proof
            // of nonzero audio. Genuine silence must never trigger a rebuild.
            this.state(this.rms.some(value=>value>1e-8)?'sampling':'zero-or-unavailable');
        }
        readFailure(channel) {
            this.counts.sampleErrors++;this.lastFailure={domain:'sample',reason:'sample-read-failed',channel,code:0,ms:Math.round(this.now()),generation:this.trackGeneration};
            this.state('sample-gap','sample-read-failed');
        }
        nativeFailure(code,status=null) {this.nativeError=code;this.driveStatus=status;this.state('native-error','native-media-error');}
        snapshot() {
            this.pruneEvents();
            const e=this.engine,m=e?.mirror,now=this.now();
            return {generation:this.trackGeneration,phase:this.phase,reason:this.reason,needsGesture:this.needsGesture,origin:this.lastOrigin,playOrigin:this.playOrigin,activation:this.lastActivation,lastActivationMs:this.lastActivationMs??null,
                online:this.env.navigator.onLine!==false,mediaError:this.mediaError||0,nativeError:this.nativeError||0,driveStatus:this.driveStatus??null,lastFailure:this.lastFailure,counts:{...this.counts},firstSignalMs:this.firstSignal,firstSignalWaitMs:this.firstSignal===null&&this.started!==null?Math.round(now-this.started):null,
                sampleAgeMs:this.sampleTime===null?null:Math.round(now-this.sampleTime),rms:[...this.rms],context:e?.context.state||'absent',clock:e?.context.currentTime,
                resumeWaitMs:e?.resumeJob?Math.round(now-e.resumeStarted):null,loadWaitMs:m?.hasAttribute('src')&&m.readyState<2?Math.round(now-e.loadStarted):null,seekWaitMs:m?.seeking?Math.round(now-e.seekStarted):null,
                copy:m?{time:m.currentTime,ready:m.readyState,paused:m.paused,seeking:m.seeking,ended:m.ended,rate:m.playbackRate,drift:e.drift}:null};
        }
    }
    if(typeof module!=='undefined'&&module.exports)module.exports=TapeRoomMeters;else window.TapeRoomMeters=TapeRoomMeters;
})();
