#!/usr/bin/env node
'use strict';
const assert = require('node:assert/strict');
const { environment, deferred, flush, stream } = require('./fireplayer_native_startup.cjs');
const tests = [];
const test = (name, run) => tests.push({ name, run });
const ready = () => ({ ok: true, status: 200, json: async () => ({ success: true, ready: true }) });

test('shared wake: one request, one consumer abort does not cancel the other', async () => {
    const wake = deferred(); let calls = 0, signal;
    const env = environment({ fetch: (_, options) => { calls++; signal = options.signal; return wake.promise; } });
    try {
        const a = env.player(), b = env.player();
        const first = a.load(stream), second = b.load(stream);
        await flush(); assert.equal(calls, 1);
        a.destroy(); assert.equal(signal.aborted, false);
        wake.resolve(ready()); await Promise.all([first, second]);
        assert.equal(b.controller.engine, 'native');
    } finally { env.dispose(); }
});

test('last wake consumer aborts fetch and rejected entry is evicted', async () => {
    let calls = 0, signal;
    const env = environment({ fetch: (_, options) => {
        calls++; signal = options.signal;
        return new Promise((resolve, reject) => signal.addEventListener('abort', () => reject(Object.assign(new Error(), { name: 'AbortError' }))));
    } });
    try {
        const a = env.player(), b = env.player();
        const loads = [a.load(stream), b.load(stream)]; await flush();
        a.destroy(); b.destroy(); await Promise.all(loads); assert.equal(signal.aborted, true);
        const c = env.player(); const loading = c.load(stream); await flush(); assert.equal(calls, 2);
        c.destroy(); await loading;
    } finally { env.dispose(); }
});

test('structured starting response retries inside one logical wake', async () => {
    let calls = 0;
    const env = environment({ fetch: async () => ++calls === 1
        ? { ok: true, json: async () => ({ state: 'starting', ready: false, retryable: true }) } : ready() });
    try {
        const p = env.player(); const loading = p.load(stream); await flush();
        assert.equal(p.media.getAttribute('src'), null); await env.advance(1500); await loading;
        assert.equal(calls, 2); assert.equal(p.controller.engine, 'native');
    } finally { env.dispose(); }
});

test('explicit streamId never probes an extensionless camera URL before wake', async () => {
    let calls = 0;
    const env = environment({ fetch: async url => { assert.equal(url, '/api/streams/wake'); calls++; return ready(); } });
    try {
        const p = env.player({ options: { src: 'https://camera.example/source', streamId: 'test', probe: true } });
        p._lazySourcePending = true;
        assert.equal(calls, 0); await p.play(); assert.equal(calls, 1); assert.equal(p.info.protocol, 'hls');
    } finally { env.dispose(); }
});

test('explicit HLS VOD does not run the LIVE stall watchdog', async () => {
    const env = environment({ modules: ['fireplayer-live.js'] });
    try {
        const p = env.player({ options: { mode: 'vod' } }); await p.load(stream); await p.play();
        let recoveries = 0; p.reconnect = async () => { recoveries++; };
        await env.advance(60000); assert.equal(p.info.mode, 'vod'); assert.equal(recoveries, 0);
    } finally { env.dispose(); }
});

test('offline blocks wake/recovery, online retains intent, Pause cancels intent', async () => {
    let calls = 0;
    const env = environment({ fetch: async () => { calls++; return ready(); } });
    try {
        const p = env.player(); p._lazySourcePending = true;
        env.window.navigator.onLine = false; await p.play(); await p.reconnect('network');
        assert.equal(calls, 0); assert.equal(p._reconnectAttempts, 0); assert.equal(p._state, 'offline');
        env.window.navigator.onLine = true; env.window.emit('online'); await p.ready;
        assert.equal(calls, 1);
        env.window.navigator.onLine = false; env.window.emit('offline');
        assert.equal(p.controller, null); p.pause();
        env.window.navigator.onLine = true; env.window.emit('online'); await flush(); assert.equal(calls, 1);
    } finally { env.dispose(); }
});

test('recovery budget is not reset by playing or play promise', async () => {
    const env = environment();
    try {
        const p = env.player({ prepared: true }); p._playRequested = true;
        let rebuilds = 0; p.controller.reconnect = async () => { rebuilds++; };
        for (let index = 0; index < 5; index++) { await p.reconnect('stall'); await flush(); }
        assert.equal(rebuilds, 4); assert.equal(p._state, 'error');
        await p.reconnect('manual'); await flush(); assert.equal(rebuilds, 5);
        p.media.readyState = 4; p._settleLoading(true);
        p._markHealthy(); await env.advance(2000); p._markHealthy(); assert.equal(p._reconnectAttempts, 0);
        p.pause(); await p.reconnect('network'); assert.equal(rebuilds, 5);
    } finally { env.dispose(); }
});

test('decoded frames frozen while clock moves triggers recovery; hidden and paused do not', async () => {
    const env = environment({ modules: ['fireplayer-live.js'] });
    try {
        const p = env.player(); p.media.getVideoPlaybackQuality = () => ({ totalVideoFrames: 0 });
        await p.load(stream); await p.play(); let reconnects = 0;
        p.reconnect = async () => { reconnects++; };
        for (let i = 0; i < 16; i++) { p.media.currentTime += 2; await env.advance(2000); }
        assert.equal(reconnects, 1, 'clock movement cannot hide frozen frames');
        env.document.hidden = true; await env.advance(60000); assert.equal(reconnects, 1);
        env.document.hidden = false; p.pause(); await env.advance(60000); assert.equal(reconnects, 1);
    } finally { env.dispose(); }
});

test('managed idle detach releases source then next Play attaches again', async () => {
    const env = environment();
    try {
        const p = env.player({ options: { managedIdleDetach: 1000 } });
        await p.load(stream); await p.play(); p.pause(); await env.advance(1000);
        assert.equal(p.controller, null); assert.equal(p.media.getAttribute('src'), null);
        await p.play(); assert.equal(p.controller.engine, 'native');
    } finally { env.dispose(); }
});

test('fatal HLS network/media recovery is bounded and paused failures wait for Play', async () => {
    let starts = 0, repairs = 0, instances = 0;
    class Hls {
        static Events = { MEDIA_ATTACHED: 'attached', MANIFEST_PARSED: 'manifest', LEVEL_LOADED: 'level', ERROR: 'error' };
        static ErrorTypes = { NETWORK_ERROR: 'network', MEDIA_ERROR: 'media' };
        static isSupported() { return true; }
        constructor() { instances++; this.handlers = new Map(); }
        on(event, callback) { this.handlers.set(event, callback); }
        attachMedia() { this.handlers.get('attached')(); }
        loadSource() {}
        startLoad() { starts++; }
        stopLoad() {}
        recoverMediaError() { repairs++; }
        destroy() { this.handlers.clear(); }
        fail(type, details) { this.handlers.get('error')('error', { type, details, fatal: true }); }
    }
    const env = environment({ safari: false, native: false, Hls });
    try {
        const p = env.player(); await p.load(stream); await p.play(); p.media.readyState = 4;
        p.controller.hls.fail('network', 'fragLoadError'); await env.advance(1800);
        assert.equal(starts, 1); assert.equal(instances, 1, 'local recovery before rebuild');
        p.controller.hls.fail('media', 'decodeError'); assert.equal(repairs, 1);
        p.pause(); p.controller.hls.fail('network', 'manifestLoadError'); await env.advance(20000);
        assert.equal(instances, 1); assert.equal(p.controller.needsRecovery, true);
        await p.play(); await flush(); assert.equal(instances, 2);
    } finally { env.dispose(); }
});

test('MediaSession seekto and metadata belong only to the active audio player', async () => {
    const env = environment({ modules: ['fireplayer-audio.js'] });
    const handlers = new Map();
    env.window.MediaMetadata = class { constructor(data) { Object.assign(this, data); } };
    env.window.navigator.mediaSession = { metadata: null, setActionHandler(action, handler) {
        if (action === 'seekbackward') { throw new Error('unsupported'); }
        handlers.set(action, handler);
    } };
    try {
        const a = env.player({ audio: true }), b = env.player({ audio: true, options: { title: 'Song', artist: 'Artist', album: 'Album' } });
        await a.load('/a.mp3'); await a.play(); a._emit('play');
        await b.load('/b.mp3'); await b.play(); b._emit('play'); b.media.duration = 60;
        handlers.get('seekto')({ seekTime: 15 }); assert.equal(b.media.currentTime, 15);
        a.destroy(); assert.equal(env.window.navigator.mediaSession.metadata.artist, 'Artist');
        handlers.get('seekto')({ seekTime: 100 }); assert.equal(b.media.currentTime, 60);
        b.destroy(); assert.equal(env.window.navigator.mediaSession.metadata, null);
        assert.equal(handlers.get('seekto'), null);
    } finally { env.dispose(); }
});

test('100 create/load/play/pause/unload/destroy cycles leave no Hls, timers or global listeners', async () => {
    let active = 0;
    class Hls {
        static Events = { MEDIA_ATTACHED: 'attached', MANIFEST_PARSED: 'manifest', LEVEL_LOADED: 'level', ERROR: 'error' };
        static ErrorTypes = { NETWORK_ERROR: 'network', MEDIA_ERROR: 'media' };
        static isSupported() { return true; }
        constructor() { active++; this.handlers = new Map(); }
        on(event, callback) { this.handlers.set(event, callback); }
        attachMedia(media) { this.media = media; this.handlers.get('attached')(); }
        loadSource() {}
        stopLoad() {}
        destroy() { active--; this.handlers.clear(); }
    }
    const env = environment({ safari: false, native: false, Hls, modules: ['fireplayer-live.js', 'fireplayer-audio.js'] });
    try {
        for (let i = 0; i < 100; i++) {
            const p = env.player(); await p.load(stream); await p.play(); p.pause(); p.unload(); p.destroy();
            assert.equal(env.timers.size, 0);
            assert.equal(active, 0);
            for (const target of [env.window, env.document, p.media, p.root]) {
                for (const listeners of target.listeners.values()) { assert.equal(listeners.size, 0); }
            }
        }
    } finally { env.dispose(); }
});

(async () => {
    let failed = 0;
    for (const { name, run } of tests) {
        try { await run(); console.log('PASS ' + name); }
        catch (error) { failed++; console.error('FAIL ' + name, error); }
    }
    console.log(`${tests.length - failed}/${tests.length} reliability groups passed`);
    process.exitCode = failed ? 1 : 0;
})();
