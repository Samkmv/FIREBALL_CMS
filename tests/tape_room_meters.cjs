'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs');
const Meters=require('../themes/tape-room/assets/js/meters.js');
const checks=[];
const check=(name,fn)=>{fn();checks.push(name);};
function fixture(){
    let enabled=false,clock=0,contexts=0,copies=0,mainChanges=0;
    const node=()=>({connect(){},disconnect(){},gain:{value:1}});
    class Context{
        constructor(){contexts++;this.state='running';this.currentTime=10;this.sampleRate=44100;this.destination=node();}
        createGain(){return node();}createChannelSplitter(){return node();}createAnalyser(){return node();}
        createMediaElementSource(){return node();}addEventListener(){}
        createBuffer(){return {};}createBufferSource(){return {...node(),start(){}};}
        resume(){return Promise.resolve();}close(){this.state='closed';return Promise.resolve();}
    }
    class Copy{
        constructor(){copies++;this.src='';this.currentSrc='';this.currentTime=0;this.readyState=4;this.paused=true;this.playbackRate=1;}
        setAttribute(){}hasAttribute(name){return name==='src'&&Boolean(this.src);}
        removeAttribute(name){if(name==='src'){this.src='';this.currentSrc='';}}
        addEventListener(){}removeEventListener(){}load(){}pause(){this.paused=true;}
        play(){this.paused=false;return Promise.resolve();}
    }
    const audio={src:'https://fixture.test/song.flac',currentTime:2,readyState:4,paused:false,ended:false,seeking:false,playbackRate:1,pause(){mainChanges++;},play(){mainChanges++;}};
    const env={performance:{now:()=>clock},document:{hidden:false},navigator:{onLine:true,userActivation:{isActive:true}},location:{href:'https://fixture.test/'},URL,Audio:Copy,window:{AudioContext:Context},setInterval:()=>1,clearInterval(){}};
    const meters=new Meters({audio,generation:()=>1,source:()=>'drive',bufferedAhead:()=>15,enabled:()=>enabled,env});
    return {meters,audio,env,setEnabled:v=>{enabled=v;},stats:()=>({contexts,copies,mainChanges}),advance:ms=>{clock+=ms;}};
}
const f=fixture(),tap={isTrusted:true,type:'click'};
check('Mini mode creates no analyser context or second Drive download',()=>{f.meters.resume('user',tap);assert.deepEqual(f.stats(),{contexts:0,copies:0,mainChanges:0});});
check('The visible deck can activate the Safari analysis copy',()=>{f.setEnabled(true);f.meters.resume('user',tap);assert.equal(f.stats().contexts,1);assert.equal(f.stats().copies,1);assert.ok(f.meters.engine.mirror.src.includes('meter=1'));});
const engine=f.meters.engine,copy=engine.mirror;
check('Switching to mini cancels the hidden copy resource',()=>{f.setEnabled(false);f.meters.tick();assert.equal(copy.src,'');assert.equal(copy.paused,true);assert.equal(f.meters.timer,null);});
check('Lifecycle events cannot reload hidden indicators',()=>{f.meters.resume('lifecycle');assert.equal(copy.src,'');assert.equal(f.stats().copies,1);});
check('Switching back reuses the unlocked context and copy',()=>{f.setEnabled(true);f.meters.resume('lifecycle');assert.equal(f.meters.engine,engine);assert.equal(f.meters.engine.mirror,copy);assert.ok(copy.src.includes('meter=1'));assert.equal(f.stats().contexts,1);});
check('An explicit tap recovers an exhausted analyser',()=>{f.meters.terminal=true;f.meters.resume('user',tap);assert.equal(f.meters.terminal,false);});
check('Analysis never changes audible playback or its position',()=>{assert.equal(f.stats().mainChanges,0);assert.equal(f.audio.currentTime,2);assert.equal(f.audio.src,'https://fixture.test/song.flac');});
check('Background mode unloads analysis without touching the song',()=>{f.env.document.hidden=true;f.meters.resume('lifecycle');assert.equal(copy.src,'');assert.equal(f.stats().mainChanges,0);});
const folder=__dirname+'/artifacts/tape-room-drive-safari';fs.mkdirSync(folder,{recursive:true});
const report={passed:checks.length,checks};fs.writeFileSync(folder+'/meters.json',JSON.stringify(report,null,2)+'\n');console.log(JSON.stringify(report,null,2));
