(function (root) {
    'use strict';
    const clamp = (value,min,max) => Math.max(min,Math.min(max,Number(value) || 0));
    /** One audible element; a legacy adapter preserves the established Safari path. */
    class TapeRoomPlaybackCore {
        static privateSource(source) { try{return /\/admin\/reel-player\/(?:catalog\/)?media\/\d+$/.test(new URL(source,root.location?.href || 'http://localhost/').pathname);}catch(_){return false;} }
        static safeTrack(track,config={}) {
            if(!track || !Number.isSafeInteger(Number(track.id)) || Number(track.id)<1 || track.access?.stream===false)return null;
            let url;try{url=new URL(track.url,root.location?.href || 'http://localhost/');}catch(_){return null;}
            if(!['http:','https:'].includes(url.protocol) || url.username || url.password || root.location && url.origin!==root.location.origin)return null;
            const privateSource=this.privateSource(url.href);
            if(privateSource && config.acceptPrivateSources!==true)return null;
            if(!privateSource && !/\/music\/(?:embed\/)?media\/\d+$/.test(url.pathname))return null;
            if(Number(url.pathname.split('/').at(-1))!==Number(track.id))return null;
            const embedToken=/\/music\/embed\/media\/\d+$/.test(url.pathname)?url.searchParams.get('token'):null;url.search='';if(embedToken)url.searchParams.set('token',embedToken);url.hash='';
            let cover='';try{if(track.cover){const image=new URL(track.cover,root.location?.href || 'http://localhost/');if(image.origin===url.origin && !image.username && !image.password && ['http:','https:'].includes(image.protocol) && /\/(?:music\/|admin\/reel-player\/(?:catalog\/)?)(?:cover|album-cover|artist-image)\/\d+$/.test(image.pathname) && (config.acceptPrivateSources===true || !image.pathname.includes('/admin/reel-player/'))){for(const name of [...image.searchParams.keys()])if(!['v','size'].includes(name))image.searchParams.delete(name);image.hash='';cover=image.href;}}}catch(_){}
            const text=value=>String(value || '').slice(0,240);
            const chapters=(Array.isArray(track.chapters)?track.chapters:[]).slice(0,1000).filter(item=>Number.isFinite(Number(item.start)) && Number(item.start)>=0 && Number(item.start)<=604800).map(item=>({id:Number(item.id) || 0,track_id:Number(track.id),title:text(item.title),artist:text(item.artist),start:Number(item.start)})).sort((a,b)=>a.start-b.start);
            return {id:Number(track.id),title:text(track.title),artist:text(track.artist),album:text(track.album),url:url.href,cover,duration:Number.isFinite(Number(track.duration))?Math.max(0,Number(track.duration)):0,file_size:Number.isFinite(Number(track.file_size))?Math.max(0,Number(track.file_size)):0,mime:text(track.mime),source:privateSource && track.source==='drive'?'drive':'local',private_source:privateSource,shared:Boolean(track.shared),chapters};
        }
        static readShared(config={}) {
            if(!config.commonPlaybackKey)return null;
            try{const saved=JSON.parse(root.localStorage?.getItem(config.commonPlaybackKey) || 'null');if(!saved || saved.version!==1 || !Array.isArray(saved.queue) || saved.queue.length>2000)return null;const queue=saved.queue.map(track=>this.safeTrack(track,config)).filter(Boolean),current=Number(saved.current);if(!queue.some(track=>track.id===current))return null;return {...saved,queue,current,position:Math.max(0,Number(saved.position) || 0),savedAt:Number(saved.savedAt) || 0};}catch(_){return null;}
        }
        constructor({audio,adapter=null,tracks=[],config={},onError=()=>{},onPlay=()=>{},storage=null} = {}) {
            if (!audio) throw new Error('Tape Room requires its existing native audio element.');
            if (TapeRoomPlaybackCore.instance && TapeRoomPlaybackCore.instance.audio !== audio) throw new Error('Only one Tape Room playback host may be mounted.');
            if (TapeRoomPlaybackCore.instance) return TapeRoomPlaybackCore.instance;
            this.audio=audio;this.adapter=adapter;this.config=config;this.onError=onError;this.onPlay=onPlay;this.storage=storage;this.lastSaved=0;this.sharedLastSaved=0;
            this.tracks=new Map();this.queue=[];this.current=0;this.shuffle=false;this.repeat='off';this.bag=[];this.history=[];this.generation=0;
            this.restoreTime=0;this.buffering=false;this.error='';this.listeners=new Set();this.bindings=[];this.stats=null;this.volumeBeforeMute=.8;
            this.owner={play:()=>this.play('system'),pause:()=>this.pause(),nexttrack:()=>this.next(false,'system'),previoustrack:()=>this.previous('system'),seekto:event=>this.seek(event.seekTime),deactivate:()=>this.pause(),activate:()=>{this.sessionKey='';this.positionKey='';this.syncSession();}};
            this.setTracks(tracks);
            for (const name of ['play','playing','pause','ended','waiting','canplay','loadedmetadata','timeupdate','seeked','volumechange','ratechange','error']) {
                const listener=()=>this.event(name);audio.addEventListener(name,listener);this.bindings.push([name,listener]);
            }
            TapeRoomPlaybackCore.instance=this;
        }
        setTracks(tracks) {
            for (const track of tracks || []) {
                if (!Number.isSafeInteger(Number(track.id)) || Number(track.id)<1 || !track.url || track.access?.stream===false) continue;
                let url;try {url=new URL(track.url,root.location?.href || 'http://localhost/');} catch (_) {continue;}
                if (root.location && url.origin !== root.location.origin) continue;
                if (!['http:','https:'].includes(url.protocol)) continue;
                if(TapeRoomPlaybackCore.privateSource(url.href) && this.config.acceptPrivateSources!==true)continue;
                this.tracks.set(Number(track.id),{...track,id:Number(track.id),url:url.href,private_source:TapeRoomPlaybackCore.privateSource(url.href),chapters:[...(track.chapters || [])].filter(chapter=>Number.isFinite(Number(chapter.start)) && Number(chapter.start)>=0).sort((a,b)=>Number(a.start)-Number(b.start))});
            }
            this.publish();
        }
        snapshot() {
            if (this.adapter?.snapshot) return this.adapter.snapshot();
            const track=this.tracks.get(this.current) || null;
            const position=this.audio.readyState===0 && this.restoreTime ? this.restoreTime : this.audio.currentTime || 0;
            const duration=Number.isFinite(this.audio.duration) ? this.audio.duration : Number(track?.duration) || 0;
            const chapterIndex=root.TapeRoomChapters?.indexAt(track?.chapters || [],position) ?? -1;
            return {track,queue:this.queue.map(id=>this.tracks.get(id)).filter(Boolean),current:this.current,position,duration,playing:!this.audio.paused && !this.audio.ended,buffering:this.buffering,volume:this.audio.volume,muted:this.audio.muted,shuffle:this.shuffle,repeat:this.repeat,error:this.error,generation:this.generation,chapterIndex,chapter:track?.chapters?.[chapterIndex] || null};
        }
        subscribe(listener) {this.listeners.add(listener);listener(this.snapshot());return()=>this.listeners.delete(listener);}
        publish() {const state=this.snapshot();for(const listener of this.listeners)listener(state);this.syncSession(state);this.persistShared(state);return state;}
        setPreferences(preferences){this.preferences=preferences;}
        persistShared(state=this.snapshot(),force=false){
            const key=this.config.commonPlaybackKey;if(!key)return;
            const preferences=this.preferences?.get() || this.config.preferences || {};
            if(preferences.remember_queue===false){try{root.localStorage?.removeItem(key);}catch(_){}return;}
            if(!state.current || !state.track)return;
            const now=performance.now();if(!force && state.playing && now-this.sharedLastSaved<3000)return;
            const queue=state.queue.map(track=>TapeRoomPlaybackCore.safeTrack(track,this.config)).filter(Boolean),current=TapeRoomPlaybackCore.safeTrack(state.track,this.config);
            if(!current)return;if(!queue.some(track=>track.id===current.id))queue.push(current);if(queue.length>2000)return;
            const snapshot={version:1,savedAt:Date.now(),current:current.id,queue,queueName:String(state.queueName || state.track.album || 'Очередь').slice(0,240),position:preferences.restore_position===false?0:state.position,volume:state.volume,muted:state.muted,shuffle:state.shuffle,repeat:state.repeat};
            if(state.queueSource)snapshot.queueSource=state.queueSource;
            try{root.localStorage?.setItem(key,JSON.stringify(snapshot));this.sharedLastSaved=now;}catch(_){}
        }
        command(name,...args) {const command=this.adapter?.[name];return command ? command(...args) : undefined;}
        setQueue(tracks,current=0) {
            if (this.adapter) {this.command('setQueue',tracks,current);return this.publish();}
            this.setTracks(tracks);this.queue=[...new Set((tracks || []).map(track=>Number(track.id)))].filter(id=>this.tracks.has(id));this.bag=[];this.history=[];
            if (current) this.load(current);
            return this.publish();
        }
        load(id,time=0) {
            if (this.adapter) {this.command('load',id,time);return this.publish();}
            const track=this.tracks.get(Number(id));if(!track)return false;
            this.sampleListening();this.stats=null;this.generation++;this.audio.pause();this.current=track.id;this.restoreTime=Math.max(0,Number(time) || 0);this.buffering=false;this.error='';
            this.audio.src=track.url;this.audio.load();this.publish();this.persist();return true;
        }
        select(id,origin='user',event) {if(this.snapshot().current!==Number(id))this.load(id);if(this.snapshot().current!==Number(id)){this.onError('Запись недоступна для воспроизведения.');return false;}return this.play(origin,event);}
        async play(origin='automatic',event) {
            if(this.adapter)return this.command('play',origin,event);
            if(!this.current){if(!this.queue.length)return;this.load(this.queue[0]);}
            if(this.audio.error)this.load(this.current,this.snapshot().position);
            if(this.audio.ended)this.audio.currentTime=0;
            try {if(root.navigator?.audioSession)root.navigator.audioSession.type='playback';}catch(_){}
            root.FireballMediaSession?.claim(this.owner);
            const generation=this.generation;
            try {const job=this.audio.play();try{this.onPlay(origin,event);}catch(_){/* Optional visual analysis never gates native sound. */}await job;}
            catch(error){if(generation===this.generation && error.name!=='AbortError'){this.error='Не удалось воспроизвести запись. Проверьте доступ к музыке и соединение.';this.buffering=false;this.onError(this.error);this.publish();}}
        }
        pause(){if(this.adapter)this.command('pause');else this.audio.pause();this.publish();}
        toggle(origin='user',event){return this.audio.paused ? this.play(origin,event) : this.pause();}
        seek(time){this.positionKey='';if(this.adapter){this.command('seek',time);return this.publish();}const duration=this.snapshot().duration;const position=clamp(time,0,duration || Math.max(0,Number(time) || 0));if(this.audio.readyState===0)this.restoreTime=position;else this.audio.currentTime=position;this.publish();this.persist();}
        seekChapter(direction,origin,event){const state=this.snapshot(),destination=root.TapeRoomChapters?.destination(state.track?.chapters || [],state.position,direction);if(destination===null || destination===undefined)return false;this.seek(destination);this.play(origin,event);return true;}
        next(automatic=false,origin='automatic',event){
            if(this.adapter){this.command('next',automatic,origin,event);return this.publish();}
            if(!this.current || !this.queue.length)return;
            if(!automatic && this.seekChapter(1,origin,event))return;
            if(automatic && this.repeat==='one'){this.seek(0);this.play(origin,event);return;}
            let id;
            if(this.shuffle){if(!this.bag.length){if(automatic && this.repeat==='off' && this.history.length)return;this.bag=this.queue.filter(value=>value!==this.current);for(let i=this.bag.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[this.bag[i],this.bag[j]]=[this.bag[j],this.bag[i]];}}id=this.bag.pop() || this.current;if(automatic && this.repeat==='off' && this.queue.length===1)return;}
            else {const index=this.queue.indexOf(this.current);if(automatic && this.repeat==='off' && index>=this.queue.length-1)return;id=this.queue[(index+1)%this.queue.length];}
            this.history.push(this.current);this.load(id);this.play(origin,event);
        }
        previous(origin='automatic',event){if(this.adapter){this.command('previous',origin,event);return this.publish();}if(!this.current)return;if(this.seekChapter(-1,origin,event))return;if(this.snapshot().position>3){this.seek(0);return;}const id=this.shuffle && this.history.length ? this.history.pop() : this.queue[(this.queue.indexOf(this.current)-1+this.queue.length)%this.queue.length];if(id){this.load(id);this.play(origin,event);}}
        volume(value){if(this.adapter)this.command('volume',value);else{this.audio.volume=clamp(value,0,1);this.audio.muted=this.audio.volume===0;if(this.audio.volume>0)this.volumeBeforeMute=this.audio.volume;}this.publish();this.persist();}
        mute(){if(this.adapter)this.command('mute');else if(this.audio.muted || this.audio.volume===0){if(this.audio.volume===0)this.audio.volume=this.volumeBeforeMute;this.audio.muted=false;}else{this.volumeBeforeMute=this.audio.volume;this.audio.muted=true;}this.publish();this.persist();}
        toggleShuffle(){if(this.adapter)this.command('shuffle');else{this.shuffle=!this.shuffle;this.bag=[];this.history=[];}this.publish();this.persist();}
        cycleRepeat(){if(this.adapter)this.command('repeat');else this.repeat={off:'all',all:'one',one:'off'}[this.repeat];this.publish();this.persist();}
        event(name){
            if(name==='play' || name==='playing')root.FireballMediaSession?.claim(this.owner);
            if(!this.adapter){
                if(name==='waiting')this.buffering=true;
                if(['playing','pause','ended','canplay','error'].includes(name))this.buffering=false;
                if(name==='loadedmetadata' && this.restoreTime){this.audio.currentTime=clamp(this.restoreTime,0,Number.isFinite(this.audio.duration)?Math.max(0,this.audio.duration-.01):this.restoreTime);this.restoreTime=0;}
                if(name==='playing')this.startListening();
                if(['timeupdate','pause','ended'].includes(name))this.sampleListening(name==='ended');
                if(name==='seeked' && this.stats){this.stats.position=this.audio.currentTime;this.stats.wall=performance.now();}
                if(name==='error'){this.error='Запись недоступна или её формат не поддерживается.';this.onError(this.error);}
                if(name==='ended'){this.stats=null;this.next(true);}
                if(['timeupdate','pause','seeked','volumechange'].includes(name))this.persist();
            }
            this.publish();
        }
        syncSession(state=this.snapshot()){
            const arbiter=root.FireballMediaSession;if(!arbiter?.owns(this.owner))return;
            const track=state.track,chapter=state.chapter,theme=root.document?.documentElement.dataset.theme || 'light';
            const defaultVariant=this.config.defaultCoverVariants?.[theme]?.[640];
            const cover=track?.cover || defaultVariant || this.config.defaultCovers?.[theme] || this.config.defaultCover;
            const key=JSON.stringify([track?.id,track?.title,track?.artist,chapter?.id,chapter?.title,chapter?.artist,cover]);
            let metadata;
            if(key!==this.sessionKey && track && root.MediaMetadata){try{metadata=new root.MediaMetadata({title:chapter?.title || track.title || '',artist:chapter?.artist || track.artist || '',album:track.album || 'Tape Room',artwork:cover?[{src:new URL(cover,root.location?.href || 'http://localhost/').href,...(!track.cover && defaultVariant?{sizes:'640x640',type:'image/webp'}:{})}]:[]});this.sessionKey=key;}catch(_){}}
            const positionKey=`${state.generation}:${state.duration}:${Math.floor(state.position)}:${this.audio.playbackRate}`;
            const position=state.duration>0 && this.positionKey!==positionKey?{duration:state.duration,playbackRate:this.audio.playbackRate,position:clamp(state.position,0,state.duration)}:null;
            if(position)this.positionKey=positionKey;
            arbiter.update(this.owner,{metadata,playbackState:state.playing?'playing':'paused',position});
        }
        persist(force=false){this.persistShared(this.snapshot(),force);if(!this.adapter && (force || this.audio.paused || performance.now()-this.lastSaved>3000)){this.lastSaved=performance.now();this.storage?.save?.(this.snapshot());}}
        async listeningRequest(values){if(!this.config.listenUrl)return null;const form=new FormData();form.set('needCSRFToken',this.config.csrf || '');for(const [name,value] of Object.entries(values))form.set(name,value);const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),10000);try{const response=await fetch(this.config.listenUrl,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','X-CSRF-Token':this.config.csrf || ''},body:form,signal:controller.signal});if(!response.ok)return null;return await response.json();}catch(_){return null;}finally{clearTimeout(timer);}}
        startListening(){if(!this.config.listenUrl || this.config.authenticated!==true || TapeRoomPlaybackCore.privateSource(this.snapshot().track?.url) || this.stats?.generation===this.generation)return;const job=this.stats={id:this.current,generation:this.generation,seconds:0,position:this.audio.currentTime,wall:performance.now(),token:'',threshold:Infinity,counted:false,busy:false,attempts:0};this.listeningRequest({action:'start',track_id:job.id}).then(result=>{if(result?.token){job.token=result.token;job.threshold=Number(result.threshold) || 30;}});}
        sampleListening(ended=false){const job=this.stats;if(!job || job.generation!==this.generation)return;const now=performance.now(),wall=Math.max(0,(now-job.wall)/1000),delta=this.audio.currentTime-job.position,rate=Math.max(.25,this.audio.playbackRate || 1);if((!this.audio.paused || ended) && !this.audio.seeking && !this.audio.muted && this.audio.volume>0 && !this.audio.error && delta>0 && delta<=wall*rate+.5)job.seconds+=Math.min(delta/rate,wall+.1);job.position=this.audio.currentTime;job.wall=now;if(!job.token || job.counted || job.busy || job.attempts>=3 || job.seconds<job.threshold)return;job.busy=true;job.attempts++;this.listeningRequest({action:'finish',token:job.token,listened:job.seconds}).then(result=>{job.counted=Boolean(result?.counted);}).finally(()=>{job.busy=false;});}
        destroy(){for(const [name,listener]of this.bindings)this.audio.removeEventListener(name,listener);this.audio.pause();root.FireballMediaSession?.release(this.owner);this.listeners.clear();if(TapeRoomPlaybackCore.instance===this)TapeRoomPlaybackCore.instance=null;}
    }
    root.TapeRoomPlaybackCore=TapeRoomPlaybackCore;
    if(typeof module!=='undefined' && module.exports)module.exports=TapeRoomPlaybackCore;
})(typeof window!=='undefined'?window:globalThis);
