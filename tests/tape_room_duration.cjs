'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs');
const quality=require('../themes/tape-room/assets/js/quality.js');
const checks=[];
function check(label,run){run();checks.push(label);}
const flac={filename:'song.flac',mime:'audio/flac',duration:182};
const stream={format:'FLAC',lossless:true,duration:267.25,sampleRate:44100};
check('FLAC sample count wins over a short native estimate',()=>assert.equal(quality.duration(flac,stream,182),267.25));
check('FLAC sample count also wins over an excessive estimate',()=>assert.equal(quality.duration(flac,stream,500),267.25));
check('A stored technical header is usable before another request',()=>assert.equal(quality.duration({...flac,quality:stream},null,182),267.25));
check('Wrong native FLAC estimate is never saved while the header is pending',()=>assert.equal(quality.durationToSave(flac,null,182),0));
check('Exact FLAC duration repairs old library values',()=>assert.equal(quality.durationToSave(flac,stream,182),267.25));
check('Unknown total sample count leaves the browser fallback usable',()=>assert.equal(quality.duration(flac,{...stream,duration:0},200),200));
check('Unknown total sample count cannot become a persisted estimate',()=>assert.equal(quality.durationToSave(flac,{...stream,duration:0},200),0));
check('A zero native duration does not erase a stored duration',()=>assert.equal(quality.duration(flac,null,0),182));
check('Infinite native duration falls back to the library',()=>assert.equal(quality.duration(flac,null,Infinity),182));
check('Native metadata remains authoritative for other formats',()=>assert.equal(quality.duration({filename:'song.mp3',duration:182},null,267.25),267.25));
check('Later native corrections can be saved for other formats',()=>assert.equal(quality.durationToSave({filename:'song.wav',duration:182},null,267.25),267.25));
check('A MIME-only FLAC is protected from a native overwrite',()=>assert.equal(quality.durationToSave({filename:'audio',mime:'audio/flac'},null,182),0));
for(const value of [NaN,Infinity,-1,604801])check('Invalid sample duration is ignored: '+String(value),()=>assert.equal(quality.duration(flac,{...stream,duration:value},200),200));
check('Late header arrival restores a valid progress fraction beyond the old end',()=>{
    const position=190;
    assert.ok(position/quality.duration(flac,null,182)>1);
    assert.ok(position/quality.duration(flac,stream,182)<1);
});
const folder=__dirname+'/artifacts/tape-room-flac-duration';fs.mkdirSync(folder,{recursive:true});
const report={passed:checks.length,checks};fs.writeFileSync(folder+'/unit.json',JSON.stringify(report,null,2)+'\n');console.log(JSON.stringify(report,null,2));
