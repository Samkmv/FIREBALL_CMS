(() => {
    'use strict';
    class TapeRoomMediaRecovery {
        constructor({retry,onState=()=>{},onStop=()=>{},now=()=>performance.now(),online=()=>navigator.onLine!==false,schedule=(fn,ms)=>setTimeout(fn,ms),cancel=id=>clearTimeout(id)}) {
            Object.assign(this,{retry,onState,onStop,now,online,schedule,cancel});
            this.epoch=0;this.select('',false);
        }
        select(key,eligible) { this.pause();this.key=key;this.eligible=eligible;this.attempts=0;this.position=0;this.stableFrom=0;this.stalledAt=null; }
        play(manual=false) { if(manual){this.pause();this.attempts=0;}this.desired=true; }
        pause() { this.desired=false;this.epoch++;if(this.timer!=null)this.cancel(this.timer);this.timer=null;this.inflight=false;this.offlineWait=false;this.stalledAt=null;this.watch=null;this.maintenance=false;this.maintenanceUntil=0;this.onState(); }
        get pending() { return this.desired&&(this.timer!=null||this.inflight||this.offlineWait); }
        progress(position) {
            if(!this.desired)return;
            if(position>this.position+.05){this.stalledAt=null;if(position-this.stableFrom>=10)this.attempts=0;}
            this.position=position;
        }
        waiting() { if(this.eligible&&this.desired&&this.stalledAt==null)this.stalledAt=this.now(); }
        observe({position,paused,seeking,ended,visible=true}) {
            // Some WebKit stalls arrive without waiting/error. Observe the
            // native clock, never wall-clock-estimate the song's position.
            if(!this.eligible||!this.desired||this.pending||paused||seeking||ended||!visible){this.watch=null;return;}
            if(!this.watch||Math.abs(position-this.watch.position)>.05){this.watch={position,time:this.now()};return;}
            if(this.now()-this.watch.time>=12000){this.watch=null;this.failed({code:2,position});}
        }
        tick() {
            if(this.inflight&&this.now()-this.inflightAt>=15000){this.epoch++;this.inflight=false;this.failed({code:2,position:this.position});}
            else if(this.stalledAt!=null&&this.now()-this.stalledAt>=12000)this.failed({code:2,position:this.position});
        }
        connected() { if(this.offlineWait&&this.desired){this.offlineWait=false;this.failed({code:2,position:this.position});} }
        failed({code=2,status=0,position=this.position,maintenance=this.maintenance,retryAfter=12}={}) {
            if((!this.eligible&&!maintenance)||!this.desired)return false;
            if([401,403,404,410].includes(status)||([3,4].includes(code)&&position<.5&&status<500&&status!==429)){this.pause();return false;}
            if(this.timer!=null||this.inflight)return true;
            this.maintenance=maintenance;
            if(maintenance){
                if(!this.maintenanceUntil)this.maintenanceUntil=this.now()+30*60*1000;
                if(this.now()>=this.maintenanceUntil){this.pause();this.onStop(false,'maintenance');return false;}
            }
            this.position=Math.max(0,position);this.stableFrom=this.position;
            if(!this.online()){this.offlineWait=true;this.onState();return true;}
            if(!maintenance&&this.attempts>=3){this.pause();this.onStop();return false;}
            const delay=maintenance?Math.max(1,Math.min(60,Number(retryAfter)||12))*1000:[1000,2500,5000][this.attempts++],epoch=this.epoch;
            this.timer=this.schedule(async()=>{
                this.timer=null;if(epoch!==this.epoch||!this.desired)return;
                if(!this.online()){this.offlineWait=true;if(!maintenance)this.attempts--;this.onState();return;}
                this.inflight=true;this.inflightAt=this.now();this.stalledAt=null;this.onState();
                try { await this.retry({position:this.position,attempt:this.attempts,valid:()=>epoch===this.epoch&&this.desired}); }
                catch(error) {
                    if(epoch!==this.epoch)return;
                    this.inflight=false;
                    if(error.name==='NotAllowedError'){this.pause();this.onStop(true);}
                    else this.failed({code:error.code||2,status:error.status||0,position:this.position,maintenance:error.maintenance??this.maintenance,retryAfter:error.retryAfter});
                } finally {if(epoch===this.epoch){this.inflight=false;this.onState();}}
            },delay);
            this.onState();return true;
        }
    }
    if(typeof window!=='undefined')window.TapeRoomMediaRecovery=TapeRoomMediaRecovery;
    if(typeof module!=='undefined'&&module.exports)module.exports=TapeRoomMediaRecovery;
})();
