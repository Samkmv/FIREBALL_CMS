'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../plugins/reel-player/assets/player.js'),'utf8');
const code=source.slice(source.indexOf('    function startListening()'),source.indexOf('    async function action('));
assert.ok(code.includes('function sampleListening(')&&code.includes('function finishListening('),'The actual listening implementation is loaded');
let checks=0;
const eq=(actual,expected,message)=>{checks++;assert.deepEqual(actual,expected,message);};
const flush=async()=>{for(let step=0;step<8;step++)await Promise.resolve();};
function setup(){
    let now=0,serial=0,renders=0;
    const timers=new Map(),requests=[];
    const tracks=[{id:1,listen_count:0},{id:2,listen_count:0}],catalogTracks=[{id:1,listen_count:0,shared:true}];
    const audio={paused:false,ended:false,seeking:false,error:null,currentTime:0,playbackRate:1,muted:false,volume:.8,pause(){throw new Error('Optional statistics must never pause audio');}};
    const context=vm.createContext({audio,current:1,generation:1,listening:null,data:{tracks,catalog:{tracks:catalogTracks}},config:{drive:{canManage:false}},libraryView:{type:'overview'},track:id=>tracks.find(track=>track.id===id),$(){return {value:''};},catalogUI:{render(){renders++;}},performance:{now:()=>now},setTimeout(callback,delay=0){const id=++serial;timers.set(id,{callback,due:now+Math.max(0,Number(delay)||0)});return id;},clearTimeout(id){timers.delete(id);},listeningRequest(values){return new Promise((resolve,reject)=>requests.push({values:{...values},resolve,reject,settled:false}));}});
    vm.runInContext(code,context);
    const settle=(request,value,error=false)=>{assert.ok(request&&!request.settled,'A request is pending');request.settled=true;error?request.reject(value):request.resolve(value);};
    const timerTick=()=>{for(let step=0;step<100;step++){const due=[...timers].find(([,timer])=>timer.due<=now);if(!due)return;timers.delete(due[0]);due[1].callback();}throw new Error('Statistics created an unbounded immediate timer loop');};
    return {context,audio,requests,tracks,catalogTracks,timers,now:()=>now,renders:()=>renders,settle,
        advance(ms,delta=ms/1000*audio.playbackRate,ended=false){now+=ms;audio.currentTime+=delta;context.sampleListening(ended);timerTick();},
        pending(action){return requests.find(request=>!request.settled&&request.values.action===action);},
        finishes(){return requests.filter(request=>request.values.action==='finish');},
        async token(threshold=10){context.startListening();settle(this.pending('start'),{token:'a'.repeat(64),threshold});await flush();},
    };
}
(async()=>{
    const normal=setup();normal.context.startListening();normal.context.startListening();eq(normal.requests.length,1,'Repeated playing notifications start one token for this source generation');
    normal.settle(normal.pending('start'),{token:'a'.repeat(64),threshold:2});await flush();normal.advance(1000);eq(normal.finishes().length,0,'A play click or partial listening below the threshold does not finish a listen');
    normal.advance(1000);eq(normal.finishes().length,1,'Actual audible elapsed listening reaches the server threshold');
    normal.settle(normal.pending('finish'),{counted:true,listens:7});await flush();
    eq([normal.tracks[0].listen_count,normal.catalogTracks[0].listen_count],[7,7],'The server count updates both owned and catalog metadata');eq(normal.renders(),1,'A counted listen refreshes catalog popularity');
    normal.advance(5000);eq(normal.finishes().length,1,'A processed token cannot repeatedly increment a song during continued playback');
    const pause=setup();await pause.token();pause.advance(1000);pause.audio.paused=true;pause.advance(20000,0);eq(pause.context.listening.seconds,1,'Paused wall time is excluded from listening');pause.audio.paused=false;pause.advance(1000);eq(pause.context.listening.seconds,2,'Listening continues from the same token after resume');
    const mute=setup();await mute.token();mute.audio.muted=true;mute.advance(1000);mute.audio.muted=false;mute.audio.volume=0;mute.advance(1000);eq(mute.context.listening.seconds,0,'Muted and zero-volume intervals cannot count as audible listening');mute.audio.volume=.8;mute.advance(1000);eq(mute.context.listening.seconds,1,'Unmuting resumes real elapsed listening');
    const seek=setup();await seek.token();seek.audio.seeking=true;seek.advance(1000,180);eq(seek.context.listening.seconds,0,'An explicit seek cannot pretend the skipped audio was heard');seek.audio.seeking=false;seek.advance(500,.5);eq(seek.context.listening.seconds,.5,'Listening continues normally after seeking');seek.advance(500,100);seek.advance(500,-80);eq(seek.context.listening.seconds,.5,'Impossible forward jumps and backward jumps do not add listening duration');
    const rate=setup();await rate.token();rate.audio.playbackRate=2;rate.advance(1000,2);eq(rate.context.listening.seconds,1,'Double-speed audio counts elapsed listening rather than skipped source seconds');rate.audio.playbackRate=.5;rate.advance(1000,.5);eq(rate.context.listening.seconds,2,'Half-speed audio also counts actual elapsed listening');
    const buffer=setup();await buffer.token();buffer.advance(5000,0);eq(buffer.context.listening.seconds,0,'A buffering interval without media progress does not count');buffer.advance(250,.25);eq(buffer.context.listening.seconds,.25,'Only decoded audio progress after buffering counts');buffer.audio.error={code:2};buffer.advance(1000,1);eq(buffer.context.listening.seconds,.25,'Native audio errors do not produce listens');
    const ended=setup();await ended.token();ended.audio.paused=true;ended.audio.ended=true;ended.advance(500,.5,true);eq(ended.context.listening.seconds,.5,'The final audible interval is retained when ended also sets native paused');
    const race=setup();race.context.startListening();const oldStart=race.pending('start');const oldJob=race.context.listening;
    race.context.generation=2;race.context.current=2;race.advance(1000);eq(oldJob.seconds,0,'Stale generation samples cannot attach listening to another source');race.context.startListening();eq(race.requests.length,2,'A new source receives its own listening token');
    race.settle(oldStart,{token:'b'.repeat(64),threshold:2});await flush();eq(race.finishes().length,0,'A late unused token from a previous source cannot create a count');eq(race.context.listening.id,2,'A late previous start response cannot overwrite the current listening job');
    const rejectedStart=setup();rejectedStart.context.startListening();rejectedStart.settle(rejectedStart.pending('start'),new Error('fixture offline'),true);await flush();eq(rejectedStart.audio.paused,false,'A statistics start error leaves native playback running');
    // Model the server's actual elapsed/seconds limits. The token arrives after
    // >2 seconds, so several honest attempts need to wait for the server clock.
    const delayed=setup();delayed.context.startListening();delayed.advance(6500);eq(delayed.finishes().length,0,'Audio progress before receiving a token cannot submit an empty token');
    const serverStarted=delayed.now();delayed.settle(delayed.pending('start'),{token:'c'.repeat(64),threshold:1});await flush();
    for(let step=0;step<40&&!delayed.context.listening.counted;step++){
        const pending=delayed.pending('finish');
        if(pending){const elapsed=(delayed.now()-serverStarted)/1000;const counted=elapsed+.2>=1&&pending.values.seconds<=elapsed*2+1;delayed.settle(pending,{counted,listens:counted?1:0});await flush();}
        if(!delayed.context.listening.counted){delayed.advance(250);await flush();}
    }
    const finalDelayed=delayed.pending('finish');if(finalDelayed){const elapsed=(delayed.now()-serverStarted)/1000;delayed.settle(finalDelayed,{counted:elapsed+.2>=1&&finalDelayed.values.seconds<=elapsed*2+1,listens:1});await flush();}
    eq(delayed.context.listening.counted,true,'Delayed token/server clock catches up without exhausting retries on legitimate counted:false replies');
    checks++;assert.ok(delayed.finishes().length>=4&&delayed.finishes().length<=7,'Legitimate negative replies retry at a bounded cadence, not on every timeupdate');
    const failures=setup();await failures.token(1);failures.advance(1000);
    for(let step=0;step<20;step++){const pending=failures.pending('finish');if(pending){failures.settle(pending,new Error('fixture offline'),true);await flush();}failures.advance(1000,0);await flush();}
    eq(failures.finishes().length,3,'Network failures are bounded to three submissions per token');eq(failures.audio.paused,false,'Statistics network failures cannot interrupt native playback');
    const bounded=setup();await bounded.token(1);bounded.advance(1000);
    for(let step=0;step<130;step++){const pending=bounded.pending('finish');if(pending){bounded.settle(pending,{counted:false,listens:0});await flush();}bounded.advance(1000,0);await flush();}
    const requestsAtDeadline=bounded.finishes().length;
    for(let step=0;step<10;step++){bounded.advance(1000,0);await flush();}
    eq(bounded.finishes().length,requestsAtDeadline,'Persistent uncounted replies have a finite retry deadline');checks++;assert.ok(requestsAtDeadline<=65,'Statistics cannot keep a rapid timer active indefinitely');
    console.log(`Passed ${checks} Tape Room listening checks: real audible intervals, delayed tokens, buffering/pause/seek/mute/rate, generation races, idempotence and bounded retries.`);
})().catch(error=>{console.error(error);process.exitCode=1;});
