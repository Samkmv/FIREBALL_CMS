((root) => {
    'use strict';
    class TapeRoomPlaybackSync {
        constructor({url,csrf,initial,snapshot,apply,request,device,visible=()=>!document.hidden}) {
            Object.assign(this,{url,csrf,snapshot,apply,visible});
            this.request=request || (async (body) => {
                const controller=new AbortController(), timer=setTimeout(()=>controller.abort(),10000);
                try {
                    const response=await fetch(url,{method:body?'POST':'GET',credentials:'same-origin',cache:'no-store',keepalive:Boolean(body),signal:controller.signal,
                        headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest',...(body?{'Content-Type':'application/json','X-CSRF-Token':this.csrf}:{})},
                        ...(body?{body:JSON.stringify(body)}:{})});
                    if(!response.ok)throw new Error('Playback sync unavailable');
                    const result=await response.json();if(!result.status)throw new Error('Playback sync unavailable');return result;
                } finally {clearTimeout(timer);}
            });
            this.device=device || root.crypto.randomUUID();
            this.revision=Number(initial?.revision)||0;this.initial=initial;
            this.seenDevice=initial?.device||'';this.intentAt=0;this.claimRetries=0;
            this.owned=false;this.dirty=false;this.claimPending=false;this.busy=false;this.intent=0;this.applying=false;
        }
        static position(value,elapsed=0) {
            if(!value?.state)return 0;
            return Math.max(0,Number(value.state.time)||0)+(value.playing?Math.min(30,Math.max(0,(value.serverTime-value.updatedAt)/1000)+Math.max(0,elapsed)):0);
        }
        claim() {this.intent++;this.intentAt=Date.now();this.claimRetries=0;this.owned=true;this.claimPending=true;this.dirty=true;this.changed(0);}
        changed(delay=200) {
            if(!this.owned||this.applying)return;
            this.dirty=true;clearTimeout(this.timer);this.timer=setTimeout(()=>this.flush(),delay);
        }
        async receive(value,intent) {
            if(intent!==this.intent||!value||Number(value.revision)<this.revision)return;
            this.revision=Number(value.revision);
            this.seenDevice=value.device;
            if(value.device===this.device&&this.owned)return;
            this.owned=false;this.applying=true;
            try {await this.apply(value,()=>intent===this.intent&&!this.owned);}
            finally {this.applying=false;if(this.dirty&&this.owned)this.changed(0);}
        }
        async flush(closing=false) {
            if(this.busy||!this.dirty||!this.owned||this.applying)return;
            const snapshot=this.snapshot();if(!snapshot){this.dirty=false;return;}
            this.busy=true;this.dirty=false;
            const intent=this.intent,claim=this.claimPending;this.claimPending=false;
            let failed=false;
            try {
                const result=await this.request({device:this.device,seenDevice:this.seenDevice,revision:this.revision,claim,playing:closing?false:snapshot.playing,state:snapshot.state});
                if(result.playback){this.revision=Math.max(this.revision,Number(result.playback.revision)||0);this.seenDevice=result.playback.device;}
                if(!result.accepted){
                    // Retry a fresh tap once after learning the latest owner.
                    // A queued offline tap must not steal a later listening session.
                    if(!closing&&claim&&intent===this.intent&&Date.now()-this.intentAt<8000&&this.claimRetries++===0){this.claimPending=true;this.dirty=true;}
                    else await this.receive(result.playback,intent);
                }
            } catch (_) {
                failed=true;this.dirty=true;this.claimPending ||= claim;
            } finally {
                this.busy=false;
                if(this.dirty&&!failed)this.changed(0);
            }
        }
        async poll() {
            if(this.busy||this.applying||!this.visible()||(this.owned&&this.dirty))return;
            this.busy=true;const intent=this.intent;
            try {await this.receive((await this.request()).playback,intent);} catch (_) { /* Playback remains local while offline. */ }
            finally {this.busy=false;if(this.dirty)this.changed(0);}
        }
        tick() {
            if(this.owned&&this.snapshot()?.playing){this.dirty=true;return this.flush();}
            if(this.dirty&&this.owned)return this.flush();
            return this.poll();
        }
        start() {
            this.receive(this.initial,this.intent);
            this.interval=setInterval(()=>this.tick(),5000);
            this.wake=()=>{if(this.visible())this.tick();};
            this.leave=()=>{if(this.owned){this.dirty=true;this.flush(true);}};
            root.addEventListener('online',this.wake);root.addEventListener('focus',this.wake);root.addEventListener('pagehide',this.leave);
            root.document.addEventListener('visibilitychange',this.wake);
        }
        stop() {
            clearTimeout(this.timer);clearInterval(this.interval);
            root.removeEventListener?.('online',this.wake);root.removeEventListener?.('focus',this.wake);root.removeEventListener?.('pagehide',this.leave);
            root.document?.removeEventListener('visibilitychange',this.wake);
        }
    }
    root.TapeRoomPlaybackSync=TapeRoomPlaybackSync;
    if(typeof module!=='undefined'&&module.exports)module.exports=TapeRoomPlaybackSync;
})(typeof window!=='undefined'?window:globalThis);
