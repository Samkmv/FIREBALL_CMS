import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
const read = name => readFileSync(new URL('../public/assets/default/js/' + name, import.meta.url), 'utf8');
let checks = 0;
const check = (value, message) => { assert.ok(value, message); checks++; };
class Element extends EventTarget {
    constructor(attrs = {}) { super(); this.attrs = {...attrs}; this.dataset = {}; this.hidden = false; this.complete = true; this.naturalWidth = 640; }
    getAttribute(k) { return this.attrs[k] ?? null; }
    setAttribute(k, v) { this.attrs[k] = String(v); }
    removeAttribute(k) { delete this.attrs[k]; }
    hasAttribute(k) { return k in this.attrs; }
    matches() { return false; }
    querySelector() { return null; }
    querySelectorAll() { return []; }
    remove() {}
}
class Media extends Element {
    constructor() { super(); this.currentTime = 0; this.paused = true; this.seeking = false; this.ended = false; this.muted = true; this.volume = .4; this.playsInline = true; this.seekable = {length:0}; this.loads = 0; }
    set src(v) { this.attrs.src = v; }
    get src() { return this.attrs.src || ''; }
    set poster(v) { this.attrs.poster = v; }
    get poster() { return this.attrs.poster || ''; }
    canPlayType() { return 'probably'; }
    pause() { this.paused = true; this.dispatchEvent(new Event('pause')); }
    load() { this.loads++; }
}
async function flush() { for (let i = 0; i < 25; i++) await Promise.resolve(); }
function runtime({apple = true, supported = true, lazy = false, autoplay = false, hidden = false, diagnostic = false, deferLoader = false, src = 'https://media.test/stream-123/index.m3u8'} = {}) {
    let now = 0, next = 0, adapter, pendingScript, scriptLoads = 0, wakes = 0;
    const timers = new Map(), instances = [], actions = [];
    const schedule = (fn, ms, repeat) => { const id = ++next; timers.set(id, {fn, ms, repeat, at:now + ms}); return id; };
    class Hls {
        static isSupported() { return supported; }
        static Events = {MEDIA_ATTACHED:'attached', MANIFEST_PARSED:'manifest', LEVEL_LOADED:'level', ERROR:'error'};
        static ErrorTypes = {NETWORK_ERROR:'network', MEDIA_ERROR:'media'};
        constructor() { this.events = {}; this.destroyed = false; instances.push(this); }
        on(k, fn) { this.events[k] = fn; }
        attachMedia(media) { actions.push(['attach', media.src]); this.media = media; this.events.attached(); }
        loadSource(source) { this.source = source; }
        destroy() { this.destroyed = true; }
        stopLoad() {}
    }
    const document = new Element();
    document.hidden = hidden;
    document.documentElement = {lang:'en'};
    document.scripts = [];
    document.createElement = () => new Element();
    const window = {
        location:{href:'https://cms.test/post'}, navigator:{userAgent:apple ? 'iPhone Safari' : 'Firefox', platform:apple ? 'iPhone' : 'Linux'},
        canViewVideoDiagnostics:diagnostic, innerHeight:800,
        firePlayerConfig:{hlsScriptUrl:'https://cms.test/hls.js'},
        setTimeout:(fn, ms)=>schedule(fn, ms, false), clearTimeout:id=>timers.delete(id),
        setInterval:(fn, ms)=>schedule(fn, ms, true), clearInterval:id=>timers.delete(id),
        fetch:async()=>{wakes++; return {ok:true, json:async()=>({ready:true})};}
    };
    document.head = {appendChild(script) { scriptLoads++; pendingScript=script; if(!deferLoader){window.Hls = Hls; script.dispatchEvent(new Event('load'));} }};
    const context = vm.createContext({window, document, Element, Document:Element, HTMLMediaElement:Media, HTMLVideoElement:Media, HTMLAudioElement:class extends Media {}, URL, AbortController, Event, CustomEvent:class extends Event {constructor(k, options){super(k); this.detail=options.detail;}}, Date:class extends Date {static now(){return now;}}});
    vm.runInContext(read('fireplayer.js'), context);
    const FirePlayer = window.FirePlayer;
    const register = FirePlayer.registerAdapter;
    FirePlayer.registerAdapter = (name, fn) => { adapter = fn; register.call(FirePlayer, name, fn); };
    vm.runInContext(read('fireplayer-hls.js'), context);
    const media = new Media(); media.poster = 'https://cms.test/poster.jpg';
    const classes = new Set();
    const events = [], errors = [];
    const player = {
        media, info:{src, protocol:'hls', mode:'live'}, options:{src, poster:media.poster, nativeStartupTimeout:7500, stallTimeout:7000, reconnect:true, lazyStart:lazy, autoplay},
        _loadToken:1, _loadAbortController:new AbortController(), _playRequested:!lazy || autoplay, _sourcePrepared:true,
        root:{classList:{contains:k=>classes.has(k), add:k=>classes.add(k), remove:k=>classes.delete(k)}, getBoundingClientRect:()=>({top:0,bottom:300,width:500})},
        _emit:(...v)=>events.push(v), setStatus(){}, setMode(mode){this.info.mode=mode;},
        _showError(message, error){errors.push(error);classes.add('fireplayer--error');},
        _recoverMedia(fn){this._recoveringMedia=true; this._playPromise=null; fn();},
        async _playMedia(){this.media.paused=false;},
        reconnect(){return this.controller.reconnect();}
    };
    media.addEventListener('pause', ()=>{if (!player._recoveringMedia && !player._reconnectPromise) player._playRequested=false;});
    return {window, document, context, FirePlayer, media, player, instances, actions, events, errors,
        async completeLoader(){window.Hls=Hls;pendingScript.dispatchEvent(new Event('load'));await flush();},
        get scriptLoads(){return scriptLoads;}, get wakes(){return wakes;}, get timers(){return timers.size;},
        async init(){ const result=await adapter(player, player.info); player.controller=result.controller; this.cleanup=result.cleanup; if(player._playRequested) media.paused=false; return this; },
        async advance(ms){const end=now+ms; while(true){const entry=[...timers].filter(([,t])=>t.at<=end).sort((a,b)=>a[1].at-b[1].at)[0];if(!entry)break;const [id,t]=entry;now=t.at;if(t.repeat)t.at+=t.ms;else timers.delete(id);t.fn();await flush();}now=end;await flush();}
    };
}
const r = runtime();
const fp = r.FirePlayer;
check(fp.inferStreamId('/stream-a/index.m3u8?token=1') === 'a', 'Shared stream ID helper');
check(fp.resolvePoster('/stream-123/index.m3u8', {}) === 'https://cms.test/tn-123.jpg', 'Established camera thumbnail path');
r.window.firePlayerConfig.streams = {'123':{poster:'/metadata.jpg'}};
check(fp.resolvePoster('/stream-123/index.m3u8', {}) === '/metadata.jpg', 'Metadata wins over path inference');
check(fp.resolvePoster('/stream-123/index.m3u8', {poster:'/wrapper.jpg'}, new Element({poster:'/video.jpg'})) === '/wrapper.jpg', 'Wrapper poster wins');
check(fp.resolvePoster('/stream-123/index.m3u8', {}, new Element({poster:'/video.jpg','data-poster':'/data.jpg'})) === '/video.jpg', 'Video poster wins over video data-poster');
check(fp.resolvePoster('/movie.m3u8', {poster:'undefined'}) === '', 'Missing poster stays empty');
check(fp.resolvePoster('/movie.m3u8', {poster:'javascript:alert(1)'}) === '', 'Unsafe poster rejected');
const image = new Element();
const preview = {media:new Media(), elements:{poster:image}, _hasPlayableFrame:false};
fp.prototype._updatePoster.call(preview, '/preview.jpg');
check(!image.hidden && preview.media.poster === '/preview.jpg', 'Poster visible before any media startup');
preview.media.src = '/stream.m3u8'; preview.media.load();
check(!image.hidden, 'Assigning source does not hide preview');
fp.prototype._markPlayableFrame.call(preview);
check(image.hidden, 'Only playable frame hides overlay');
fp.prototype._updatePoster.call(preview, '/new-preview.jpg');
check(image.hidden, 'Poster does not flash again after playback');
fp.prototype._updatePoster.call(preview, 'undefined');
check(!image.hasAttribute('src') && !preview.media.hasAttribute('poster'), 'Empty poster removes invalid attributes');
// Real legacy upgrader, including priority, metadata and no-poster cases.
const init = read('fireplayer-init.js');
const upgrade = init.slice(init.indexOf('    const legacyOptions'), init.indexOf('    const initialize'));
for (const [attrs, expected] of [[{src:'/stream-123/index.m3u8',poster:'/explicit.jpg'},'/explicit.jpg'],[{'data-hls-src':'/stream-123/index.m3u8'},'/metadata.jpg'],[{src:'/other.m3u8'},'']]) {
    const media = new Media(); Object.assign(media.attrs, attrs); media.tagName='VIDEO'; media.closest=()=>null; media.parentNode={insertBefore(){}};
    const wrapper = new Element();wrapper.classList={add(){}};wrapper.appendChild=()=>{};
    r.document.createElement=()=>wrapper;r.document.querySelectorAll=()=>[media];
    vm.runInContext('(function(){'+upgrade+'\nupgradeLegacyContentMedia(document);})();', r.context);
    check((wrapper.getAttribute('data-poster')||'')===expected, 'Legacy poster resolution: '+expected);
    if(expected) check(wrapper.getAttribute('data-preload')==='none' && wrapper.getAttribute('data-lazy-start')==='true', 'Live poster defers HLS preparation');
}
{
 const h=await runtime().init();h.media.dispatchEvent(new Event('playing'));for(let i=0;i<6;i++){h.media.currentTime+=2;await h.advance(2000);}
 check(h.scriptLoads===0 && h.player.controller.engine==='native', 'Fast Safari startup does not load HLS vendor');h.cleanup();
}
{
 const h=await runtime({diagnostic:true}).init();h.media.dispatchEvent(new Event('loadedmetadata'));await h.advance(7500);
 check(h.scriptLoads===1 && h.player.controller.engine==='hls.js', 'Metadata alone cannot prevent startup failover');
 check(h.actions.length===1 && h.actions[0][1]==='' && h.instances.length===1, 'Native pipeline cleared before sole HLS attachment');
 check(h.media.poster==='https://cms.test/poster.jpg' && h.media.muted && h.media.volume===.4 && h.media.playsInline, 'Switch preserves poster and playback attributes');
 check(h.wakes===1, 'Switch does not repeat wake');
 check(h.events.some(e=>e[0]==='enginechange'), 'Permitted diagnostics receive enginechange');
 await h.player.controller.reconnect();
 check(h.player.controller.engine==='hls.js' && h.instances[0].destroyed && h.instances.length===2, 'Reconnect remains HLS and destroys previous instance');
 h.cleanup();h.player._loadToken++;h.player._loadAbortController=new AbortController();await h.init();
 check(h.player.controller.engine==='hls.js', 'Same player retains HLS preference across source recreation');
 h.cleanup();
}
{
 const h=await runtime({supported:false}).init();await h.advance(7500);
 check(h.player.controller.engine==='native' && h.media.src===h.player.info.src && h.instances.length===0, 'Old iPhone retries native without unsupported HLS instance');
 await h.advance(8000);check(h.errors.length===1, 'Unsupported fallback reaches graceful final error');h.cleanup();
}
for(const agent of [false]) {
 const h=await runtime({apple:agent}).init();check(h.scriptLoads===1 && h.player.controller.engine==='hls.js', 'Non-Apple immediately uses dynamic HLS');h.cleanup();
}
{
 const h=await runtime().init();h.player._destroyed=true;h.player._loadAbortController.abort();await h.advance(10000);
 check(h.scriptLoads===0 && h.timers===0, 'Destroy cancels watchdog and fallback');
}
{
 const h=await runtime().init();h.player._playRequested=false;h.media.pause();await h.advance(20000);
 check(h.scriptLoads===0, 'User pause is not startup failure or stall');h.cleanup();
}
{
 const h=await runtime({hidden:true}).init();await h.advance(20000);check(h.scriptLoads===0, 'Hidden document does not trigger fallback');
 h.document.hidden=false;h.document.dispatchEvent(new Event('visibilitychange'));await h.advance(7000);check(h.scriptLoads===0, 'Returning tab receives fresh budget');h.cleanup();
}
{
 const h=await runtime().init();h.media.dispatchEvent(new Event('playing'));await h.advance(4000);check(h.scriptLoads===0, 'Short buffering leaves native');
 await h.advance(3000);check(h.scriptLoads===0 && h.media.loads>=3, 'First persistent stall retries native once');
 await h.advance(7000);check(h.scriptLoads===1 && h.player.controller.engine==='hls.js', 'Second persistent stall switches to HLS');h.cleanup();
}
{
 const h=await runtime({lazy:true}).init();check(h.wakes===0 && h.scriptLoads===0 && !h.player.controller.started, 'Poster-only idle player performs no wake or HLS request');
 h.player._playRequested=true;await h.player.controller.start();check(h.wakes===1 && h.player.controller.started, 'Play prepares deferred source once');h.cleanup();
}
{
 const h=await runtime({lazy:true,autoplay:true}).init();check(h.wakes===1 && h.player.controller.started, 'Autoplay bypasses deferred start');h.cleanup();
}
{
 const h=await runtime().init();await h.advance(7500);check(!h.events.some(e=>['enginechange','nativefallback','startuptimeout'].includes(e[0])), 'No technical events without diagnostics permission');h.cleanup();
}
{
 const h=await runtime().init();h.media.seeking=true;await h.advance(16000);
 check(h.scriptLoads===0, 'Seeking is not a native failure');h.cleanup();
}
{
 const h=runtime({lazy:true});let live;
 h.FirePlayer.use = extension => {live=extension;};
 vm.runInContext(read('fireplayer-live.js'), h.context);
 const urls=[];h.player._updatePoster=url=>urls.push(url);h.player.options.posterCacheBust=true;h.player.on=()=>{};h.player.off=()=>{};
 const cleanup=live.setup(h.player);await h.advance(15000);
 check(new Set(urls).size===1, 'Poster refresh uses stable cache bucket');
 h.document.hidden=true;const before=urls.length;await h.advance(10000);check(urls.length===before, 'Hidden poster does not refresh');
 h.document.hidden=false;h.player.root.getBoundingClientRect=()=>({top:1000,bottom:1300,width:500});await h.advance(10000);check(urls.length===before, 'Offscreen poster does not refresh');
 h.player.root.getBoundingClientRect=()=>({top:0,bottom:300,width:500});h.player._hasPlayableFrame=true;await h.advance(10000);check(urls.length===before, 'Poster refresh stops after first frame');
 cleanup();await h.advance(10000);check(urls.length===before && h.timers===0, 'Destroy removes live timers');
}
{
 const h=await runtime({deferLoader:true}).init();await h.advance(7500);
 h.player._destroyed=true;h.player._loadAbortController.abort();await h.completeLoader();
 check(h.instances.length===0, 'Destroy during vendor load cannot attach an engine');
}
{
 const h=await runtime({lazy:true}).init();h.window.fetch=async()=>{throw Error('Wake unavailable');};
 h.player._playRequested=true;await assert.rejects(h.player.controller.start());
 check(h.player.controller===null, 'Failed deferred wake permits a fresh adapter on Retry');
}
console.log(`FirePlayer runtime: ${checks} checks passed.`);
