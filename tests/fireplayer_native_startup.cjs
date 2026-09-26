#!/usr/bin/env node
'use strict';

// No packages, browser, server, or network required. Production lifecycle methods
// run against a media double which deliberately emits no readiness events until
// a test requests them; this catches a native-HLS startup gate deterministically.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { performance } = require('node:perf_hooks');
const root = path.resolve(__dirname, '..');
const sources = ['fireplayer.js', 'fireplayer-hls.js'].map(name => ({
    name,
    code: fs.readFileSync(path.join(root, 'public/assets/default/js', name), 'utf8')
}));
const stream = 'https://camera.example/stream-test/index.m3u8';
const deferred = () => {
    let resolve, reject;
    const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
};
const namedError = name => Object.assign(new Error(name), { name });
const flush = async () => { for (let i = 0; i < 30; i += 1) { await Promise.resolve(); } };
const bounded = async (promise, description) => {
    let timer;
    try {
        return await Promise.race([promise, new Promise((_, reject) => {
            timer = setTimeout(() => reject(new Error(description + ' did not settle without readiness events')), 400);
        })]);
    } finally { clearTimeout(timer); }
};

class Target {
    constructor() {
        this.listeners = new Map(); this.attributes = new Map(); this.dataset = {};
        this.style = { setProperty() {} }; this.children = []; this.hidden = false;
        this.textContent = ''; this.className = ''; this.innerHTML = '';
        const classes = new Set();
        this.classList = {
            add: (...names) => names.forEach(name => classes.add(name)),
            remove: (...names) => names.forEach(name => classes.delete(name)),
            contains: name => classes.has(name),
            toggle(name, force) {
                const on = force === undefined ? !classes.has(name) : force;
                if (on) { classes.add(name); } else { classes.delete(name); }
                return on;
            }
        };
    }
    addEventListener(name, fn, options = {}) {
        if (!this.listeners.has(name)) { this.listeners.set(name, new Map()); }
        this.listeners.get(name).set(fn, options);
    }
    removeEventListener(name, fn) { if (this.listeners.has(name)) { this.listeners.get(name).delete(fn); } }
    dispatchEvent(event) {
        if (!event.target) { event.target = this; }
        for (const [fn, options] of [...(this.listeners.get(event.type) || [])]) {
            if (options && options.once) { this.removeEventListener(event.type, fn); }
            fn.call(this, event);
        }
        return true;
    }
    emit(type) { this.dispatchEvent({ type }); }
    listenerCount(name) { return (this.listeners.get(name) || new Map()).size; }
    setAttribute(name, value) { this.attributes.set(name, String(value)); }
    getAttribute(name) { return this.attributes.get(name) ?? null; }
    removeAttribute(name) { this.attributes.delete(name); }
    contains(target) { return target === this; }
    closest() { return null; }
    querySelector() { return null; }
    querySelectorAll() { return []; }
}

function environment({ native = true, safari = true, fetch: fetchImpl, Hls, modules = [] } = {}) {
    let now = 0, nextTimer = 0;
    const timers = new Map(), trace = [], values = new Map(), writes = [];
    class Media extends Target {
        constructor() {
            super(); this._src = ''; this.currentSrc = ''; this.readyState = 0;
            this.currentTime = 0; this.duration = Infinity; this.error = null;
            this.paused = true; this.ended = false; this.muted = false; this.volume = 1;
            this.seeking = false; this.seekable = { length: 0 }; this.firstChild = null;
            this.playCalls = []; this.loadCalls = []; this.playResults = [];
        }
        canPlayType() { return native ? 'probably' : ''; }
        get src() { return this._src; }
        set src(value) { this.setAttribute('src', value); }
        setAttribute(name, value) {
            super.setAttribute(name, value);
            if (name === 'src') { this._src = String(value); trace.push({ event: 'src', value, at: now }); }
        }
        removeAttribute(name) {
            super.removeAttribute(name);
            if (name === 'src') { this._src = ''; trace.push({ event: 'remove-src', at: now }); }
        }
        load() {
            this.loadCalls.push(this._src); trace.push({ event: 'load', src: this._src, at: now });
            this.currentSrc = this._src; this.readyState = 0; this.error = null;
        }
        play() {
            this.playCalls.push({ muted: this.muted, readyState: this.readyState });
            trace.push({ event: 'play', muted: this.muted, readyState: this.readyState, at: now });
            const result = this.playResults.length ? this.playResults.shift() : undefined;
            if (result instanceof Error) { return Promise.reject(result); }
            if (result && typeof result.then === 'function') {
                return result.then(() => { this.paused = false; });
            }
            this.paused = false;
            return Promise.resolve();
        }
        pause() { this.paused = true; trace.push({ event: 'pause', at: now }); }
    }
    class Video extends Media {}
    class Audio extends Media {}
    const document = new Target();
    document.documentElement = { lang: 'en' };
    document.scripts = [];
    document.createElement = name => name === 'video' ? new Video() : name === 'audio' ? new Audio() : new Target();
    const window = new Target();
    Object.assign(window, {
        location: { href: 'https://cms.example/' },
        navigator: { userAgent: safari ? 'Version/17.0 Safari/605.1.15' : 'Chrome/130.0', onLine: true },
        setTimeout(fn, milliseconds) {
            const id = ++nextTimer; timers.set(id, { fn, due: now + Number(milliseconds || 0) }); return id;
        },
        clearTimeout(id) { timers.delete(id); },
        setInterval(fn, milliseconds) {
            const id = ++nextTimer; timers.set(id, { fn, due: now + milliseconds, interval: milliseconds }); return id;
        },
        clearInterval(id) { timers.delete(id); },
        localStorage: {
            getItem: key => values.get(key) ?? null,
            setItem(key, value) { values.set(key, value); writes.push({ key, value }); },
            removeItem: key => values.delete(key)
        },
        fetch: fetchImpl || (async () => ({ ok: true, status: 200, json: async () => ({ ready: true }) }))
    });
    if (Hls) { window.Hls = Hls; }
    class TestDate extends Date { static now() { return now; } }
    const context = vm.createContext({
        window, document, URL, AbortController, TextDecoder, Date: TestDate,
        Element: Target, Document: Target, HTMLMediaElement: Media,
        HTMLVideoElement: Video, HTMLAudioElement: Audio,
        HTMLInputElement: class extends Target {}, HTMLSelectElement: class extends Target {},
        HTMLTextAreaElement: class extends Target {},
        CustomEvent: class { constructor(type, options) { this.type = type; Object.assign(this, options); } }
    });
    vm.runInContext(sources[0].code, context, { filename: sources[0].name });
    const FirePlayer = window.FirePlayer;
    let adapter;
    const register = FirePlayer.registerAdapter;
    FirePlayer.registerAdapter = function (protocol, factory) {
        if (protocol === 'hls') { adapter = factory; }
        return register.call(this, protocol, factory);
    };
    vm.runInContext(sources[1].code, context, { filename: sources[1].name });
    modules.forEach(name => vm.runInContext(fs.readFileSync(path.join(root, 'public/assets/default/js', name), 'utf8'), context, { filename: name }));
    const players = [];
    function player({ audio = false, prepared = false, options = {} } = {}) {
        const p = Object.create(FirePlayer.prototype);
        Object.assign(p, {
            originalElement: new Target(), root: new Target(), media: audio ? new Audio() : new Video(),
            options: Object.assign({ src: stream, autoplay: false, muted: false, rememberVolume: true,
                rememberPosition: false, reconnect: true, startupTimeout: 30000, keyboard: true,
                probe: false, media: audio ? 'audio' : 'video', protocol: 'auto', mode: 'auto' }, options),
            info: prepared ? { src: stream, media: audio ? 'audio' : 'video', protocol: 'hls', mode: 'live' } : null,
            controller: prepared ? { engine: 'native' } : null,
            _loadToken: 1, _loadAbortController: new AbortController(), _loadTokenAtPlay: 0,
            _events: new Map(), _listeners: [], _cleanups: [], _originalTracks: [],
            _destroyed: false, _playRequested: false, _playPromise: null, _playAttemptId: 0,
            _sourcePrepared: prepared, _lazySourcePending: false, _nativeHlsPreparing: false,
            _nativeHlsMutedFallback: false, _nativePlayRecoveries: 0, _reconnectAttempts: 0,
            _reconnectPromise: null, _lastPlaybackTime: 0,
            elements: Object.fromEntries(['retry', 'mute', 'seek', 'volume', 'speed', 'settings', 'settingsMenu',
                'live', 'pip', 'fullscreen', 'controls', 'status'].map(name => [name, new Target()]))
        });
        p.elements.playButtons = [];
        p.elements.status.hidden = true;
        p.elements.settingsMenu.hidden = true;
        // Rendering, position persistence and purely visual synchronizers do not
        // influence source preparation, playback, cancellation or sound storage.
        for (const name of ['_syncPlayButtons', '_syncTimeline', '_syncCapabilities', '_syncLiveUi',
            '_syncVolume', '_storePosition', '_restorePosition']) { p[name] = () => {}; }
        p._bindUi();
        p.ready = Promise.resolve(p);
        players.push(p);
        return p;
    }
    return {
        window, document, player, adapter, trace, timers, writes, values,
        async advance(milliseconds) {
            const end = now + milliseconds;
            for (;;) {
                const next = [...timers].filter(([, timer]) => timer.due <= end).sort((a, b) => a[1].due - b[1].due)[0];
                if (!next) { break; }
                now = next[1].due;
                if (next[1].interval) { next[1].due += next[1].interval; } else { timers.delete(next[0]); }
                next[1].fn(); await flush();
            }
            now = end; await flush();
        },
        dispose() { players.forEach(p => { if (!p._destroyed) { p.destroy(); } }); assert.equal(timers.size, 0, 'all timers cleaned'); }
    };
}

const tests = [];
const test = (name, run) => tests.push({ name, run });

test('deferred wake -> native attach -> play at readyState 0, without metadata/canplay', async () => {
    const wake = deferred();
    let calls = 0;
    const env = environment({ fetch(url, options) {
        assert.equal(url, '/api/streams/wake'); assert.equal(options.method, 'POST');
        calls += 1; env.trace.push({ event: 'wake-request' }); return wake.promise;
    } });
    const p = env.player();
    try {
        const loading = p.load(stream);
        const playing = p.play();
        await flush();
        assert.equal(calls, 1); assert.equal(p.media.playCalls.length, 0);
        assert.equal(env.trace.filter(e => e.event === 'src').length, 0, 'must not attach before backend readiness');
        assert.equal(p.media.loadCalls.length, 0, 'pristine media must not receive a blank teardown load');
        env.trace.length = 0;
        env.trace.push({ event: 'wake-ready' });
        wake.resolve({ ok: true, status: 200, json: async () => ({ success: true, ready: true }) });
        await bounded(Promise.all([loading, playing]), 'native initial load/play');
        assert.equal(p.controller.engine, 'native');
        assert.equal(p.media.readyState, 0);
        assert.equal(env.trace.filter(e => e.event === 'src').length, 1);
        assert.equal(env.trace.filter(e => e.event === 'load').length, 1, 'no native reset cycle during initial attach');
        assert.equal(env.trace.filter(e => e.event === 'remove-src').length, 0);
        assert.equal(p.media.playCalls.length, 1);
        assert.equal(p.media.playCalls[0].muted, false);
        assert.deepEqual(env.trace.filter(e => ['wake-ready', 'src', 'load', 'play'].includes(e.event)).map(e => e.event),
            ['wake-ready', 'src', 'load', 'play']);
        assert.equal(p.media.listenerCount('loadedmetadata'), 2, 'only core metadata listeners remain, no native wait');
        console.log('  observed order: wake ready -> src -> load -> play (readyState=0; no metadata event)');
    } finally { env.dispose(); }
});

test('unchanged currentSrc skips native reset/load; explicit reconnect still waits for readiness', async () => {
    const env = environment(); const p = env.player();
    p.media.src = stream; p.media.currentSrc = stream; env.trace.length = 0;
    try {
        const result = await bounded(env.adapter(p, { src: stream }), 'same-source adapter');
        p._cleanups.push(result.cleanup); p.controller = result.controller;
        assert.equal(p.media.loadCalls.length, 0);
        assert.equal(env.trace.filter(e => e.event === 'src').length, 0);
        let settled = false;
        const reconnecting = result.controller.reconnect('network').then(() => { settled = true; });
        await flush();
        assert.equal(settled, false); assert.equal(p._nativeHlsPreparing, true);
        assert.deepEqual(p.media.loadCalls, ['', stream]);
        assert.equal(p.media.listenerCount('loadedmetadata'), 3, 'recovery has a temporary readiness listener');
        p.media.readyState = 1; p.media.emit('loadedmetadata');
        await bounded(reconnecting, 'native recovery');
        assert.equal(p._nativeHlsPreparing, false);
        assert.equal(p.media.listenerCount('loadedmetadata'), 2);
    } finally { env.dispose(); }
});

test('legacy Plyr native preparation and FirePlayer both attach immediately after readiness gate', async () => {
    const legacySource = fs.readFileSync(path.join(root, 'themes/default/assets/js/plyr-init.js'), 'utf8');
    const start = legacySource.indexOf('    const prepareNativeHlsPlayback = async function (element) {');
    const end = legacySource.indexOf('\n    const markPlaybackStarted =', start);
    assert.ok(start >= 0 && end > start, 'extract the actual legacy function, never a reimplementation');
    for (const implementation of ['legacy', 'FirePlayer']) {
        const gate = deferred();
        const env = environment({ fetch: () => gate.promise.then(() => ({
            ok: true, status: 200, json: async () => ({ ready: true })
        })) });
        const p = env.player();
        try {
            let preparing;
            if (implementation === 'legacy') {
                const prepareLegacy = vm.runInNewContext(legacySource.slice(start, end) + '\nprepareNativeHlsPlayback;', {
                    isCrossOriginUrl: () => false,
                    ensureHlsSourceAwake: () => gate.promise,
                    showHlsInfo() {}, t: key => key
                });
                p.media.hlsSource = stream;
                preparing = prepareLegacy(p.media);
            } else {
                preparing = env.adapter(p, { src: stream }).then(result => {
                    p._cleanups.push(result.cleanup); return result.handled;
                });
            }
            await flush(); assert.equal(p.media.loadCalls.length, 0);
            gate.resolve(true);
            assert.equal(await bounded(preparing, implementation + ' source preparation'), true);
            assert.equal(p.media.readyState, 0);
            assert.deepEqual(p.media.loadCalls, [stream]);
            assert.deepEqual(env.trace.filter(e => ['src', 'load'].includes(e.event)).map(e => e.event), ['src', 'load']);
            assert.equal(p.media.listenerCount('loadedmetadata'), 2);
        } finally { env.dispose(); }
    }
    console.log('  legacy/new comparison: both src -> load -> prepared without metadata; browser latency is not measured');
});

test('source replacement and destruction still reset an existing native media session', async () => {
    const env = environment(); const p = env.player();
    try {
        await bounded(p.load(stream), 'initial load');
        env.trace.length = 0;
        await p.load('https://cms.example/movie.mp4');
        assert.deepEqual(env.trace.filter(e => ['remove-src', 'load', 'src'].includes(e.event)).map(e => [e.event, e.src ?? e.value ?? '']), [
            ['remove-src', ''], ['load', ''], ['src', 'https://cms.example/movie.mp4'], ['load', 'https://cms.example/movie.mp4']
        ]);
        env.trace.length = 0;
        p.destroy();
        assert.deepEqual(env.trace.filter(e => ['remove-src', 'load'].includes(e.event)).map(e => [e.event, e.src ?? '']), [
            ['remove-src', ''], ['load', '']
        ]);
    } finally { env.dispose(); }
});

test('native recovery abort removes readiness listeners and retry timer', async () => {
    const env = environment(); const p = env.player();
    try {
        const result = await bounded(env.adapter(p, { src: stream }), 'initial adapter');
        p._cleanups.push(result.cleanup);
        const recovering = result.controller.reconnect('network');
        const rejected = assert.rejects(recovering, error => error.name === 'AbortError');
        await flush();
        p._loadAbortController.abort();
        await bounded(rejected, 'aborted recovery');
        assert.equal(p._nativeHlsPreparing, false);
        assert.equal(p.media.listenerCount('loadedmetadata'), 2);
        const sourceWrites = env.trace.filter(e => e.event === 'src').length;
        await env.advance(20000);
        assert.equal(env.trace.filter(e => e.event === 'src').length, sourceWrites);
        assert.equal(env.timers.size, 0);
    } finally { env.dispose(); }
});

test('wake ready=false refuses attachment; source change and destroy cancel deferred wake', async () => {
    const failed = environment({ fetch: async () => ({ ok: true, status: 200, json: async () => ({ ready: false }) }) });
    try {
        const p = failed.player();
        await assert.rejects(p.load(stream), /not ready/);
        assert.equal(failed.trace.filter(e => e.event === 'src').length, 0);
        assert.equal(p.media.playCalls.length, 0);
    } finally { failed.dispose(); }
    for (const action of ['source-change', 'destroy']) {
        const wake = deferred(); let signal;
        const env = environment({ fetch(url, options) { signal = options.signal; return wake.promise; } });
        const p = env.player();
        try {
            const loading = p.load(stream);
            await flush();
            if (action === 'source-change') { await p.load('https://cms.example/movie.mp4'); }
            else { p.destroy(); }
            await flush(); assert.equal(signal.aborted, true);
            wake.resolve({ ok: true, status: 200, json: async () => ({ ready: true }) });
            await bounded(loading, 'cancelled wake load');
            await flush();
            assert.equal(env.trace.filter(e => e.event === 'src' && e.value === stream).length, 0);
            assert.equal(p.media.playCalls.length, 0);
        } finally { env.dispose(); }
    }
});

test('hls.js path still attaches and loads through MEDIA_ATTACHED without native load', async () => {
    const instances = [];
    class Hls {
        static Events = { MEDIA_ATTACHED: 'attached', MANIFEST_PARSED: 'manifest', LEVEL_LOADED: 'level', ERROR: 'error' };
        static ErrorTypes = { NETWORK_ERROR: 'network', MEDIA_ERROR: 'media' };
        static isSupported() { return true; }
        constructor() { this.handlers = new Map(); this.loaded = []; instances.push(this); }
        on(event, callback) { this.handlers.set(event, callback); }
        attachMedia(media) { this.media = media; this.handlers.get('attached')(); }
        loadSource(src) { this.loaded.push(src); }
        destroy() { this.destroyed = true; }
        stopLoad() {}
    }
    const env = environment({ safari: false, native: false, Hls }); const p = env.player();
    try {
        const result = await bounded(env.adapter(p, { src: stream }), 'hls.js adapter');
        p._cleanups.push(result.cleanup);
        assert.equal(result.controller.engine, 'hls.js');
        assert.equal(instances.length, 1); assert.deepEqual(instances[0].loaded, [stream]);
        instances[0].handlers.get('attached')();
        assert.deepEqual(instances[0].loaded, [stream], 'decoder reattach must not reload manifest');
        assert.equal(p.media.loadCalls.length, 0);
    } finally { env.dispose(); }
    assert.equal(instances[0].destroyed, true);
});

test('native video makes one audible attempt then one muted attempt in the same pending promise', async () => {
    const env = environment(); const p = env.player({ prepared: true });
    const retry = deferred();
    p.media.playResults.push(namedError('NotAllowedError'), retry.promise);
    try {
        const playing = p.play(); await flush();
        assert.deepEqual(p.media.playCalls.map(call => call.muted), [false, true]);
        assert.equal(p._nativeHlsMutedFallback, true);
        const pending = p._playPromise;
        assert.ok(pending); assert.equal(p._playMedia(p._loadToken), pending);
        assert.equal(p.media.playCalls.length, 2, 'no extra play while muted retry is pending');
        assert.equal(env.writes.length, 0, 'temporary fallback is not a user sound preference');
        retry.resolve(); await bounded(playing, 'muted fallback');
        assert.equal(p.media.muted, true, 'no automatic unmute without a user action');
        assert.equal(p.root.classList.contains('fireplayer--awaiting-gesture'), false);
        assert.equal(env.writes.length, 0);
        p.pause(); await p.play();
        assert.equal(p.media.playCalls.at(-1).muted, false, 'explicit play restores audible intent');
        assert.equal(p._nativeHlsMutedFallback, false);
    } finally { env.dispose(); }
});

test('explicit unmute, volume slider and volume key clear temporary mute and persist user choice', async () => {
    for (const action of ['mute', 'slider', 'key']) {
        const env = environment(); const p = env.player({ prepared: true });
        try {
            p.media.playResults.push(namedError('NotAllowedError'));
            await p.play(); assert.equal(p._nativeHlsMutedFallback, true);
            if (action === 'mute') { p.mute(false); }
            else if (action === 'slider') { p.elements.volume.value = '0.6'; p.elements.volume.emit('input'); }
            else { p._handleKey({ key: 'ArrowUp', target: p.root, preventDefault() {} }); }
            assert.equal(p.media.muted, false); assert.equal(p._nativeHlsMutedFallback, false, action);
            assert.equal(env.writes.length, 1);
            assert.equal(JSON.parse(env.values.get('fireplayer.volume')).muted, false);
        } finally { env.dispose(); }
    }
});

test('policy retry does not affect audio/files/hls.js/already-muted or other errors', async () => {
    for (const variant of ['audio', 'file', 'hls.js', 'already-muted', 'other-error']) {
        const env = environment(); const p = env.player({ audio: variant === 'audio', prepared: true });
        try {
            if (variant === 'file') { p.info.protocol = 'file'; }
            if (variant === 'hls.js') { p.controller.engine = 'hls.js'; }
            if (variant === 'already-muted') { p.media.muted = true; }
            const error = namedError(variant === 'other-error' ? 'NetworkError' : 'NotAllowedError');
            p.media.playResults.push(error);
            await assert.rejects(p.play(), failure => failure === error);
            assert.equal(p.media.playCalls.length, 1, variant);
            assert.equal(p._nativeHlsMutedFallback, false, variant);
            assert.equal(env.writes.length, 0);
        } finally { env.dispose(); }
    }
});

test('pause, destroy and source change prevent a late NotAllowedError from retrying', async () => {
    for (const action of ['pause', 'destroy', 'source-change']) {
        const env = environment(); const p = env.player({ prepared: true }); const first = deferred();
        try {
            p.media.playResults.push(first.promise); const playing = p.play();
            if (action === 'pause') { p.pause(); }
            else if (action === 'destroy') { p.destroy(); }
            else { await p.load('https://cms.example/new.mp4'); }
            first.reject(namedError('NotAllowedError'));
            await bounded(playing, 'cancelled play');
            assert.equal(p.media.playCalls.length, 1, action);
            assert.equal(p._nativeHlsMutedFallback, false, action);
            assert.equal(p.media.muted, false);
            assert.equal(env.writes.length, 0);
        } finally { env.dispose(); }
    }
});

test('failed muted retry reports autoplayblocked once and never loops', async () => {
    const env = environment(); const p = env.player({ prepared: true });
    let blocked = 0; p.on('autoplayblocked', () => { blocked += 1; });
    p.media.playResults.push(namedError('NotAllowedError'), namedError('NotAllowedError'));
    try {
        await assert.rejects(p.play(), error => error.name === 'NotAllowedError');
        assert.equal(p.media.playCalls.length, 2); assert.equal(blocked, 1);
        assert.equal(p._playRequested, false);
        assert.equal(p.root.classList.contains('fireplayer--awaiting-gesture'), true);
        await env.advance(60000);
        assert.equal(p.media.playCalls.length, 2); assert.equal(env.writes.length, 0);
    } finally { env.dispose(); }
});

module.exports = { environment, deferred, namedError, flush, bounded, Target, stream };

if (require.main === module) (async () => {
    const start = performance.now();
    let failures = 0;
    for (const { name, run } of tests) {
        try { await run(); console.log('PASS ' + name); }
        catch (error) { failures += 1; console.error('FAIL ' + name + '\n' + error.stack); }
    }
    console.log(`${tests.length - failures}/${tests.length} groups passed in ${Math.round(performance.now() - start)} ms.`);
    console.log('Scope: production JavaScript lifecycle with deterministic media doubles; this does not certify Safari decoding or real camera latency.');
    process.exitCode = failures ? 1 : 0;
})().catch(error => { console.error(error); process.exitCode = 1; });
