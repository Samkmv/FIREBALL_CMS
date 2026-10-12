'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs'), vm = require('node:vm');
const Collections = require('../plugins/reel-player/assets/collections.js');
let checks = 0;
const eq = (actual, expected, reason) => { checks++; assert.deepEqual(actual, expected, reason); };
const ids = tracks => tracks.map(track => Number(track.id));
const data = {
    tracks: [
        {id:3,title:'My edited title',artist:'My artist',shared:false,favorite:true,url:'/private/3',cover:'/private-cover/3'},
        {id:4,title:'My owned song',artist:'Orion',favorite:false,url:'/private/4'},
        {id:9,title:'Private demo',artist:'Private artist',favorite:false,url:'/private/9'},
    ],
    playlists: [{id:7,name:'Evening queue',tracks:[1,9,2,999]}],
    catalog: {
        tracks: [
            {id:1,title:'Public first',artist:'Orion',shared:true,favorite:false,collected:false,listen_count:12,url:'/catalog/1'},
            {id:2,title:'Saved song',artist:'Guest',shared:true,favorite:false,collected:true,listen_count:4,url:'/catalog/2'},
            {id:3,title:'Publisher title',artist:'Orion',shared:true,favorite:false,collected:false,listen_count:8,url:'/catalog/3',cover:'/catalog-cover/3'},
            {id:4,title:'Public owned song',artist:'Orion',shared:true,favorite:true,collected:true,listen_count:0,url:'/catalog/4'},
            {id:5,title:'Favorite public song',artist:'Guest',shared:true,favorite:true,collected:false,listen_count:4,url:'/catalog/5'},
        ],
        albums: [
            {id:10,title:'Ordered album',artist:'Orion',published:true,saved:true,tracks:[2,3,1]},
            {id:20,title:'Private album',artist:'Private artist',published:false,saved:false,tracks:[9]},
            {id:30,title:'Guest album',artist:'Guest',published:true,saved:false,tracks:[5,4]},
        ],
    },
};
const initial = JSON.stringify(data);
const pool = Collections.tracks(data);
eq(ids(pool),[1,2,3,4,5,9],'Catalog and personal rows are merged once per global track ID');
const own = pool.find(track=>track.id===3);
eq([own.title,own.artist,own.url,own.cover,own.shared,own.favorite,own.listen_count],
    ['My edited title','My artist','/private/3','/private-cover/3',false,true,8],
    'Owner metadata, URLs and favorite win while the real catalog count survives');
eq(pool.find(track=>track.id===4).favorite,false,'A publisher or catalog favorite cannot override the owner favorite');
eq(pool.find(track=>track.id===4).listen_count,0,'A real zero listening count remains zero');
eq(ids(Collections.myTracks(data)),[2,3,4,5,9],'Personal collection contains owned, saved and favorited songs, excluding unsaved public songs');
eq(ids(Collections.collection(data,{type:'songs'},'new')),[9,5,4,3,2],'Personal newest order');
eq(ids(Collections.collection(data,{type:'songs'},'old')),[2,3,4,5,9],'Personal oldest order');
eq(ids(Collections.collection(data,{type:'favorites'},'old')),[3,5],'Favorite order uses this viewer favorites only');
for (const mode of ['new','old','playlist']) {
    eq(ids(Collections.collection(data,{type:'album',id:10},mode)),[2,3,1],`Album canonical order survives ${mode} sorting`);
}
eq(ids(Collections.collection(data,{type:'playlist',id:7},'playlist')),[1,9,2],'Playlist order and shared songs survive; missing IDs disappear');
eq(ids(Collections.collection(data,{type:'playlist',id:7},'old')),[1,2,9],'Explicit playlist sorting remains available');
eq(ids(Collections.collection(data,{type:'popular'})),[1,3,5,2],'Popularity contains published songs with counted listens and deterministic tie order');
eq(ids(Collections.collection(data,{type:'artist',artist:'oRiOn'},'old')),[1,2,3,4],'Artist albums include guest tracks and matching artists on other published albums');
eq(ids(Collections.collection(data,{type:'artist',artist:'Private artist'})),[],'A private album does not appear in the public artist catalog');
eq(Collections.name(data,{type:'album',id:10}),'Ordered album','Album queue name');
eq(Collections.name(data,{type:'playlist',id:7}),'Evening queue','Playlist queue name');
eq(Collections.name(data,{type:'artist',artist:'Orion'}),'Orion','Artist queue name');
eq(Collections.name(data,{type:'popular'}),'Популярные песни','Popular queue name');
eq(Collections.name(data,{type:'favorites'}),'Избранное','Favorites queue name');
eq(Collections.name(data,{type:'songs'}),'Моя музыка','Personal queue name');
for (const value of [null,undefined,{},'album',{type:'unknown'},{type:'album',id:0},{type:'album',id:-1},{type:'playlist',id:1.5},{type:'playlist',id:Number.MAX_SAFE_INTEGER+1},{type:'artist',artist:'   '},{type:'artist',artist:1}]) {
    eq(Collections.descriptor(value),{type:'songs'},'Corrupt saved browse/queue state has a safe collection fallback');
}
eq(Collections.descriptor({type:'album',id:'10',injected:'field'}),{type:'album',id:10},'Saved numeric strings are normalized and unsupported fields omitted');
eq(Collections.descriptor({type:'artist',artist:'  Orion  '}),{type:'artist',artist:'Orion'},'Artist key is trimmed');
eq(Collections.descriptor({type:'artist',artist:'x'.repeat(300)}).artist.length,240,'Saved artist labels have a bounded length');
const fallback={type:'playlist',id:7};
const copiedFallback=Collections.descriptor(null,fallback);copiedFallback.id=999;
eq(fallback,{type:'playlist',id:7},'Fallback descriptors are copied');
// Browsing another collection must not rewrite the already selected queue.
const queueDescriptor={type:'album',id:10}, queue=Collections.collection(data,queueDescriptor);
const browseDescriptor={type:'favorites'};Collections.collection(data,browseDescriptor,'old');
eq(ids(queue),[2,3,1],'Browsing favorites has no side effect on an album queue');
eq(queueDescriptor,{type:'album',id:10},'Queue descriptor remains independent of browse descriptor');
eq(JSON.stringify(data),initial,'Collection rendering does not mutate API snapshots');
// The server removes foreign inaccessible rows on unpublish. The client must
// never revive their old queue IDs or show stale playlist references as songs.
const unpublished={tracks:data.tracks,playlists:data.playlists,catalog:{albums:[data.catalog.albums[1]],tracks:[]}};
eq(ids(Collections.collection(unpublished,{type:'album',id:10})),[],'Unpublished album cannot restore a foreign queue from its old descriptor');
eq(ids(Collections.collection(unpublished,{type:'playlist',id:7},'playlist')),[9],'Unavailable public tracks are dropped from personal playlist references');
eq(ids(Collections.collection(unpublished,{type:'favorites'})),[3],'Owned favorite remains available after public album unpublish');
eq(ids(Collections.collection(unpublished,{type:'popular'})),[],'No published album means no public popularity results');
eq(ids(Collections.myTracks({tracks:[],playlists:[],catalog:{tracks:[],albums:[]}})),[],'A new viewer has no imaginary personal tracks');
eq(ids(Collections.collection({tracks:[{id:1,title:'Legacy',favorite:false}],playlists:[]},{type:'songs'})),[1],'Libraries created before catalog migration remain usable');
eq(ids(Collections.collection(data,{type:'album',id:999})),[],'Deleted album has no fallback to unrelated songs');
eq(ids(Collections.collection(data,{type:'playlist',id:999})),[],'Deleted playlist has no fallback to unrelated songs');
// Exercise the actual player's queue/browse/save integration as well as the
// pure model. The render prefix has only queue reconciliation and access checks;
// no visual DOM, real user storage or native audio are needed in this harness.
const playerSource=fs.readFileSync(require('node:path').join(__dirname,'../plugins/reel-player/assets/player.js'),'utf8');
const queueCode=playerSource.slice(playerSource.indexOf('    function setQueue('),playerSource.indexOf('    function load('));
const browseCode=playerSource.slice(playerSource.indexOf('    function browse(view)'),playerSource.indexOf('    function editAlbum('));
const saveCode=playerSource.slice(playerSource.indexOf('    function save()'),playerSource.indexOf('    function closeSort('));
const renderStart=playerSource.indexOf('    function render()');
const renderCode=playerSource.slice(renderStart,playerSource.indexOf("        const nav = $('[data-playlists]');",renderStart))+'    }\n';
const navigation=playerSource.slice(playerSource.indexOf('    function next(automatic = false,'),playerSource.indexOf('    function applyState(result)'));
const stateCode=playerSource.slice(playerSource.indexOf('    function applyState(result)'),playerSource.indexOf('    function mutate('));
assert.ok(queueCode&&stateCode&&browseCode.includes('function browse('),'Actual integration helpers are loaded');
let savedState,pauses=0,sourceClears=0,queueResets=0;
const playerContext=vm.createContext({data,collections:Collections,trackPool:Collections.tracks(data),trackMap:new Map(Collections.tracks(data).map(track=>[track.id,track])),selected:0,libraryView:{type:'overview'},queueSource:{type:'songs'},queue:[],queueName:'',queuePlaylist:0,queueSort:'new',librarySort:'new',playlistSort:'playlist',current:2,bag:[],futureBag:[],history:[],shuffleCycleStarted:false,shuffle:false,repeat:'off',audibleVolume:.8,config:{storageKey:'fixture-player'},audio:{paused:false,currentTime:0,volume:.8,muted:false,pause(){this.paused=true;pauses++;},removeAttribute(name){assert.equal(name,'src');sourceClears++;},load(){}},preloader:{reset(){queueResets++;}},localStorage:{setItem(key,value){assert.equal(key,'fixture-player');savedState=JSON.parse(value);}},$(){return {value:''};},playbackPosition:()=>playerContext.audio.currentTime,selectedSort:()=>playerContext.selected>0?playerContext.playlistSort:playerContext.librarySort,selectedSource:()=>Collections.descriptor(playerContext.libraryView),playlist:id=>playerContext.data.playlists.find(playlist=>playlist.id===Number(id)),album:id=>Collections.album(playerContext.data,id),track:id=>playerContext.trackMap.get(Number(id)),activeTrack:()=>playerContext.trackMap.get(playerContext.current),updateNow(){},seekChapter(){return false;},drawProgress(){},play(){playerContext.audio.paused=false;},load(id){playerContext.current=id;playerContext.audio.currentTime=0;}});
vm.runInContext(queueCode+'\n'+saveCode+browseCode+renderCode+navigation+stateCode,playerContext);
playerContext.setQueue({type:'album',id:10});
eq(Array.from(playerContext.queue),[2,3,1],'Player queues the complete album in its authored order');
playerContext.next(true);eq(playerContext.current,3,'Automatic source transition advances within the album');
playerContext.previous();eq(playerContext.current,2,'Previous source remains within the playing album');
playerContext.browse({type:'playlist',id:7});
eq(Array.from(playerContext.queue),[2,3,1],'Actual browse/render leaves the playing album queue unchanged');
eq(playerContext.current,2,'Actual browse/render leaves the current audio track unchanged');
eq(queueResets,1,'Browsing another collection does not reset the playing queue preload state');
eq(savedState.libraryView,{type:'playlist',id:7},'The separately browsed collection is saved');
eq(savedState.queueSource,{type:'album',id:10},'The playing album descriptor is saved independently');
eq(savedState.queue,[2,3,1],'Saved album queue retains its authored order');
playerContext.bag=[1,3];playerContext.futureBag=[2];playerContext.history=[1,2,3,9];
playerContext.applyState(unpublished);
eq(playerContext.current,0,'A foreign current song removed by server access checks is stopped');
eq(pauses,1,'Access revocation pauses native playback');
eq(sourceClears,1,'Access revocation clears the old private media source');
eq(Array.from(playerContext.bag),[3],'Shuffle bags drop revoked foreign songs');
eq(Array.from(playerContext.futureBag),[],'Future shuffle cycles cannot revive withdrawn songs');
eq(Array.from(playerContext.history),[3,9],'Back history removes inaccessible foreign songs');
eq(savedState.current,0,'Reload does not restore a withdrawn current track');
console.log(`Passed ${checks} Tape Room collection checks: metadata, personal collection, canonical album order, popularity, artists, saved state and queue isolation.`);
