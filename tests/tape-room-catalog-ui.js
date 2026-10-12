'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

class Node {
    constructor(dataset = {}) { this.dataset = dataset; this.hidden = false; this.value = ''; this.attributes = {}; this.html = ''; this.textContent = ''; this.classes = new Set(); this.classList = {toggle:(name,on) => on ? this.classes.add(name) : this.classes.delete(name)}; }
    get innerHTML() { return this.html; }
    set innerHTML(value) { this.html = value; }
    get childNodes() { return this.html ? [{}] : []; }
    setAttribute(name,value) { this.attributes[name] = value; }
    replaceChildren() { this.html = ''; }
}

const elements = new Map(['[data-catalog-content]','[data-catalog-album-count]','[data-catalog-saved-count]','[data-catalog-create]','[data-search]','[data-tracks]','[data-empty]','[data-sort]','.rp-drop-hint'].map(selector => [selector,new Node()]));
const navigation = ['overview','albums','artists','new','popular','mine'].map(view => new Node({view}));
const app = {querySelector:selector => elements.get(selector),querySelectorAll:selector => selector === '[data-catalog-nav] [data-view]' ? navigation : []};
const context = {window:{},Map,Set,Date,Number,String,Boolean};
vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../plugins/reel-player/assets/catalog.js'),'utf8'),context);
const escape = value => String(value).replace(/[&<>"']/g,char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
let theme = 'light';
const ui = new context.window.TapeRoomCatalogUI({app,icon:name => `<i data-icon="${name}"></i>`,escape,defaultCover:() => `cover-${theme}.png`});
let assertions = 0;
const check = (value, message) => { assert.ok(value,message); assertions++; };
const html = () => elements.get('[data-catalog-content]').innerHTML;
const data = {
    tracks:[{id:1,title:'My first song',artist:'Public artist',duration:60},{id:9,title:'Private song',artist:'Private artist',duration:120}],
    playlists:[],
    catalog:{
        albums:[
            {id:1,title:'Released public album',artist:'Public artist',published:true,saved:false,editable:false,release_date:'2026-10-11',created_at:'2026-09-01',tracks:[2,1]},
            {id:2,title:'Private album',artist:'Private artist',published:false,saved:true,editable:true,created_at:'2026-10-12',tracks:[9]},
            {id:3,title:'Added public album',artist:'Another artist',published:true,saved:true,editable:false,created_at:'2026-10-12',tracks:[3]},
            {id:4,title:'<script>alert(1)</script>',artist:'<img src=x onerror=alert(1)>',published:true,saved:false,editable:true,release_date:'2020-01-01',tracks:[]}
        ],
        tracks:[
            {id:1,title:'My first song',artist:'Public artist',duration:60,listen_count:3},
            {id:2,title:'Popular song',artist:'Public artist',duration:120,listen_count:7},
            {id:3,title:'Never played song',artist:'Another artist',duration:200,listen_count:0},
            {id:9,title:'Private song',artist:'Private artist',duration:120,listen_count:10000}
        ]
    }
};

let result = ui.render({data,view:{type:'overview'},query:''});
check(!result.listMode,'Overview uses cards rather than an empty personal track list.');
check(!html().includes('Private album') && !html().includes('Private song'),'Shared overview excludes private songs and albums.');
check(html().includes('Popular song') && !html().includes('Never played song'),'Popular preview requires actual listening history.');
check(html().includes('3 <i data-icon="play">'),'Owned track data retains the catalogue listen count.');
check(!html().includes('<script>') && html().includes('&lt;script&gt;'),'Album titles are escaped.');
check(!html().includes('<img src=x') && html().includes('&lt;img src=x'),'Artist names are escaped in text and attributes.');
check(html().includes('cover-light.png') && html().includes('data-default-cover'),'Fallback images are theme aware and marked for later theme changes.');
check(elements.get('[data-catalog-album-count]').textContent === 3,'Site album count excludes private albums.');
check(elements.get('[data-catalog-saved-count]').textContent === 3,'Personal album count includes saved and owned albums.');
check(navigation.find(node => node.dataset.view === 'overview').attributes['aria-current'] === 'page','Navigation exposes the selected section accessibly.');
check(elements.get('[data-sort]').hidden && elements.get('[data-tracks]').hidden,'Cards hide track-only controls.');

result = ui.render({data,view:{type:'mine'},query:''});
check(html().includes('Private album') && html().includes('Added public album') && !html().includes('Released public album'),'Personal collection contains saved and owned albums only.');
check(html().includes('Все мои песни'),'Personal collection links to the existing personal songs list.');
check(html().includes('Личный альбом'),'Unpublished personal albums have an honest visibility label.');

result = ui.render({data,view:{type:'popular'},query:''});
check(result.listMode && result.tracks.length === 2 && result.tracks[0].id === 2 && result.tracks[1].id === 1,'Popularity orders only eligible shared songs by actual listens.');
check(elements.get('[data-sort]').hidden && !elements.get('[data-tracks]').hidden,'Popularity keeps the existing row renderer without overriding its rating order.');

result = ui.render({data,view:{type:'album',id:1},current:2,playing:true,query:''});
check(result.listMode && result.tracks.map(track => track.id).join(',') === '2,1','Album playback order follows membership order.');
check(html().includes('Слушать альбом') && html().includes('В коллекцию'),'Album detail offers listening and saving.');
check(!html().includes('catalog-edit') && !html().includes('catalog-publish'),'Foreign albums expose no edit or publication action.');
check(html().includes('3 мин'),'Album metadata reflects total known duration.');

result = ui.render({data,view:{type:'album',id:2},query:'',canPublish:false});
check(html().includes('catalog-edit') && !html().includes('catalog-publish'),'An album owner can edit while only a creator can publish.');
ui.render({data,view:{type:'album',id:2},query:'',canPublish:true});
check(html().includes('catalog-publish') && html().includes('data-published="1"'),'Creator publication is exposed explicitly.');

result = ui.render({data,view:{type:'artists'},query:''});
check(!html().includes('Private artist'),'Shared artist cards exclude private music.');
check(html().includes('Public artist') && html().includes('Another artist'),'Artist cards come from public album and song credits.');
result = ui.render({data,view:{type:'artist',artist:'Public artist'},query:''});
check(result.listMode && result.tracks.length === 2 && html().includes('Released public album'),'Artist detail combines discography and songs in the existing renderer.');

result = ui.render({data,view:{type:'new'},query:''});
check(html().includes('Новые релизы') && html().includes('Недавно добавлены'),'News distinguishes release dates from upload dates.');
check(!html().includes('Private album'),'News is scoped to the published catalogue.');

result = ui.render({data,view:{type:'albums'},query:'not a match'});
check(html().includes('Ничего не найдено') && !html().includes('rp-album-card'),'Album search has an honest empty state.');
result = ui.render({data,view:{type:'artists'},query:'PUBLIC'});
check(html().includes('Public artist') && !html().includes('Another artist'),'Artist search is case insensitive.');

for (const type of ['songs','favorites','playlist']) {
    result = ui.render({data,view:{type,id:1},query:''});
    check(result.listMode && !Object.hasOwn(result,'tracks'),'Personal track and playlist views leave sorting to the established player.');
    check(elements.get('[data-catalog-content]').hidden && !elements.get('[data-sort]').hidden,'Personal rows have their original controls.');
}

theme = 'dark';
ui.render({data,view:{type:'albums'},query:''});
check(html().includes('cover-dark.png') && !html().includes('cover-light.png'),'Theme changes refresh only fallback catalogue artwork.');
result = ui.render({data,view:{type:'album',id:999},query:''});
check(!result.listMode && html().includes('больше недоступен'),'Removed albums do not leave a stale playable row list.');
result = ui.render({data:{tracks:[],playlists:[],catalog:{albums:[],tracks:[]}},view:{type:'overview'},query:''});
check(!result.listMode && html().includes('каталог пуст') && html().includes('после первых прослушиваний'),'Empty libraries show no invented catalogue or popularity.');

process.stdout.write(`Tape Room catalogue UI: ${assertions} checks passed.\n`);
