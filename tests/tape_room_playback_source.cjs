'use strict';
const assert = require('node:assert/strict');
const Collections = require('../themes/tape-room/assets/js/collections.js');
const checks = [];
function check(label,run) { run(); checks.push(label); }
const published = {tracks:[],playlists:[],catalog:{tracks:[{id:3,shared:true},{id:4,shared:true}],albums:[{id:1,published:true,artist:'Tape Room',tracks:[4,3]}]}};
for (const type of ['overview','albums','new','artists','genres']) {
    check(`First play on ${type} finds a published album`,() => assert.deepEqual(Collections.playbackSource(published,{type},{type:'songs'}),{type:'album',id:1}));
}
check('Album queue preserves its order',() => assert.deepEqual(Collections.collection(published,Collections.playbackSource(published,{type:'overview'},{type:'songs'})).map(track=>track.id),[4,3]));
for (const source of [{type:'favorites'},{type:'playlist',id:9},{type:'album',id:9},{type:'artist',artist:'Missing'},{type:'genre',id:9},{type:'popular'},{type:'songs'}]) {
    check(`Empty explicit ${source.type} does not play unrelated music`,() => assert.deepEqual(Collections.playbackSource(published,source,source),source));
}
check('Private albums do not become the guest fallback',() => assert.deepEqual(Collections.playbackSource({...published,catalog:{...published.catalog,albums:[{id:2,published:false,tracks:[3]}]}},{type:'overview'},{type:'songs'}),{type:'songs'}));
check('Missing audio references do not enable playback',() => assert.deepEqual(Collections.playbackSource({...published,catalog:{...published.catalog,albums:[{id:2,published:true,tracks:[99]}]}},{type:'overview'},{type:'songs'}),{type:'songs'}));
check('An existing personal queue takes precedence',() => assert.deepEqual(Collections.playbackSource({...published,tracks:[{id:1,shared:false}]},{type:'overview'},{type:'songs'}),{type:'songs'}));
check('Existing playlist selection is retained',() => assert.deepEqual(Collections.playbackSource({...published,playlists:[{id:5,tracks:[3]}]},{type:'playlist',id:5},{type:'playlist',id:5}),{type:'playlist',id:5}));
check('Empty library remains empty',() => assert.deepEqual(Collections.playbackSource({tracks:[],catalog:{albums:[],tracks:[]}},{type:'overview'},{type:'songs'}),{type:'songs'}));
check('Personal albums view does not pick a public album',() => assert.deepEqual(Collections.playbackSource(published,{type:'mine'},{type:'songs'}),{type:'songs'}));
console.log(JSON.stringify({passed:checks.length,checks},null,2));
