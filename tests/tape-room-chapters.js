'use strict';
const assert = require('node:assert/strict'), fs = require('node:fs'), vm = require('node:vm');
const chapters = require('../plugins/reel-player/assets/chapters.js');
const source = fs.readFileSync(require('node:path').join(__dirname,'../plugins/reel-player/assets/player.js'),'utf8');
let checks=0;
function equal(actual,expected,message) { assert.deepEqual(actual,expected,message); checks++; }
for (const [text,time] of [['00:00',0],['03:25',205],['120:00',7200],['2:00:00',7200],['01:00:01.493333',3601.493333],['10080:00',604800]]) equal(chapters.parseTime(text),time,'Valid long/fractional timestamp');
for (const text of ['', '3', '-1:00', '00:60', '1:60:00', '1:00:60', '1:2', 'Infinity', '10081:00', '00:00.1234567']) { assert.throws(()=>chapters.parseTime(text)); checks++; }
for (const time of [0,205,7200,3601.493333,604800,1/75]) equal(chapters.parseTime(chapters.formatTime(time)),Math.round(time*1e6)/1e6,'CUE fractions survive editor round trip');
for (const [time,text] of [[1.9999997,'00:02'],[59.9999997,'01:00'],[3599.9999997,'1:00:00']]) equal(chapters.formatTime(time),text,'Rounding carries seconds/minutes/hours');
equal(chapters.indexAt([],10),-1); equal(chapters.destination([],10,1),null);
const single=[{start:0}]; equal(chapters.indexAt(single,3000),0);equal(chapters.destination(single,4,-1),0);equal(chapters.destination(single,100,1),null);
const mix=Array.from({length:30},(_,index)=>({start:index*240,id:index+1,title:`Composition ${index}`}));
for (let index=0;index<30;index++) {
    equal(chapters.indexAt(mix,index*240),index,'Exact boundary');
    equal(chapters.indexAt(mix,index*240+239.9),index,'Within chapter, natural playback/seek');
    equal(chapters.destination(mix,index*240+4,-1),index*240,'Restart current after three seconds');
    equal(chapters.destination(mix,index*240+1,-1),Math.max(0,index-1)*240,'Go to previous near start');
    equal(chapters.destination(mix,index*240+1,1),mix[index+1]?.start ?? null,'Next chapter or source queue');
}
equal(chapters.indexAt([{start:20},{start:40}],0),-1);equal(chapters.destination([{start:20}],0,1),20);
equal(chapters.nearest([],100),null);
equal(chapters.nearest(mix,-10),mix[0]);equal(chapters.nearest(mix,9000),mix[29]);
equal(chapters.nearest(mix,120),mix[0],'Equal distance prefers earlier marker');
for (const count of [30,1000]) {
    const dense=Array.from({length:count},(_,index)=>({start:index*7200/count,id:index+1}));
    for (let index=0;index<count;index++) equal(chapters.nearest(dense,dense[index].start+7200/count*0.4).id,index+1,'Overlapping mobile targets resolve to nearest timestamp');
}
// Exercise the actual player navigation with a two-hour source and ordinary queue.
const navigation=source.slice(source.indexOf('    function next(automatic = false,'),source.indexOf('    function applyState(result)'));
const seek=source.slice(source.indexOf('    function seekChapter('),source.indexOf('    function chapterTrack('));
let reloads=0,plays=0;
const context=vm.createContext({chapters,playbackPosition:()=>context.audio.currentTime,currentChapters:()=>context.items,items:mix,audio:{currentTime:481},current:1,queue:[1,2],repeat:'off',shuffle:false,history:[],bag:[],futureBag:[],drawProgress(){},jumpChapter(time){context.audio.currentTime=time;plays++;},play(){plays++;},load(id){reloads++;context.current=id;context.audio.currentTime=0;}});
vm.runInContext(seek+navigation,context);
context.next(false,'user');equal(context.audio.currentTime,720);equal(reloads,0,'Next chapter keeps native source');
context.audio.currentTime=729;context.previous('user');equal(context.audio.currentTime,720);equal(reloads,0,'Chapter restart keeps native source');
context.audio.currentTime=721;context.previous('user');equal(context.audio.currentTime,480);
context.audio.currentTime=7199;context.next(true);equal(context.current,2,'Actual ended advances source queue');equal(reloads,1);
context.current=1;context.items=[];context.next(false);equal(context.current,2,'No-chapter file unchanged');
context.current=1;context.items=mix;context.audio.currentTime=7190;context.repeat='one';context.next(true);equal(context.audio.currentTime,0,'Repeat one repeats whole recording');equal(context.current,1);
context.repeat='off';context.next(false);equal(context.audio.currentTime,240,'Next button seeks chapter');
context.queue=[];context.next(false);equal(context.audio.currentTime,480,'Chapters work even when source was removed from its playlist');
console.log(`Tape Room chapters: ${checks} checks passed (timestamp round trip, 2-hour / 30 chapters, source isolation, ordinary queue and repeat).`);
