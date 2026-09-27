'use strict';
const assert = require('node:assert/strict');
const { environment, flush, stream } = require('./fireplayer_native_startup.cjs');
const tests = [];
const test = (name, run) => tests.push({ name, run });
function setup({ observer = true, live = false } = {}) {
    const env = environment({ modules: live ? ['fireplayer-live.js'] : [] });
    const images = [], observers = [];
    let active = 0, maximum = 0;
    env.window.innerHeight = 800; env.window.innerWidth = 1200;
    env.window.Image = class {
        set src(url) { this.url = url; this.pending = true; images.push(this); active++; maximum = Math.max(maximum, active); }
        removeAttribute() { if (this.pending) { this.pending = false; active--; } }
        finish(ok = true) {
            if (!this.pending) { return; }
            this.pending = false; active--;
            const callback = ok ? this.onload : this.onerror;
            if (callback) { callback(); }
        }
    };
    if (observer) {
        env.window.IntersectionObserver = class {
            constructor(callback, options) { assert.equal(options.rootMargin, '150px 0px'); this.callback = callback; this.roots = new Set(); observers.push(this); }
            observe(root) { this.roots.add(root); }
            unobserve(root) { this.roots.delete(root); }
            disconnect() { this.roots.clear(); }
        };
    }
    function player(top = 0, poster = '/poster.jpg') {
        const p = env.player({ options: { poster } }); p.root.isConnected = true; p.root.top = top;
        p.root.getBoundingClientRect = () => ({ top: p.root.top, bottom: p.root.top + 100, left: 0, right: 500, width: 500, height: 100 });
        return p;
    }
    function visible() {
        observers.forEach(io => io.callback([...io.roots].map(target => ({ target, isIntersecting: target.top < 950 }))));
    }
    function dispose() {
        env.dispose(); assert.equal(active, 0);
        observers.forEach(io => assert.equal(io.roots.size, 0));
        for (const target of [env.window, env.document]) {
            for (const listeners of target.listeners.values()) { assert.equal(listeners.size, 0); }
        }
    }
    return { env, images, player, visible, dispose, get active() { return active; }, get maximum() { return maximum; } };
}

test('ten pending posters create no immediate requests; only two visible slots; near comes after viewport', async () => {
    const s = setup();
    try {
        const players = [s.player(820, '/near.jpg'), ...Array.from({ length: 4 }, (_, i) => s.player(i * 100, '/' + i + '.jpg')),
            ...Array.from({ length: 5 }, (_, i) => s.player(2000 + i * 200, '/far-' + i + '.jpg'))];
        players.forEach(p => p._preparePoster(p.options.poster));
        await flush(); assert.equal(s.images.length, 0);
        s.visible(); await flush(); assert.equal(s.active, 2); assert.equal(s.images[0].url, '/0.jpg');
        s.images[0].finish(); await flush(); assert.equal(s.images.length, 3);
        assert.equal(players[1].media.poster, '/0.jpg');
        for (let i = 1; i < 5; i++) { s.images[i].finish(); await flush(); }
        assert.equal(s.images.length, 5); assert.equal(s.images[4].url, '/near.jpg'); assert.equal(s.maximum, 2);
        players[5].root.top = 100; s.visible(); await flush(); assert.equal(s.images.length, 6);
    } finally { s.dispose(); }
});

test('404 does not fail playback and is not retried by visibility or cache bust', async () => {
    const s = setup();
    try {
        const p = s.player(); p._preparePoster(p.options.poster); s.visible(); await flush();
        s.images[0].finish(false); assert.equal(p._posterState, 'failed'); assert.equal(p.media.getAttribute('poster'), null);
        for (let i = 0; i < 10; i++) { p._queuePoster('/poster.jpg?t=' + i, true); s.visible(); }
        await flush(); assert.equal(s.images.length, 1); assert.equal(p.root.classList.contains('fireplayer--error'), false);
        p._lazySourcePending = true; await p.play(); assert.equal(p.media.playCalls.length, 1);
        p.pause(); s.visible(); await flush(); assert.equal(s.images.length, 1);
    } finally { s.dispose(); }
});

test('Play cancels a loading poster without awaiting its result', async () => {
    const s = setup();
    try {
        const p = s.player(); p._preparePoster(p.options.poster); s.visible(); await flush();
        const lateLoad = s.images[0].onload;
        p._lazySourcePending = true; await p.play(); lateLoad();
        assert.equal(p.media.playCalls.length, 1); assert.equal(s.active, 0); assert.equal(p.media.poster, undefined);
    } finally { s.dispose(); }
});

test('source replacement infers a new poster, ignores old completion, explicit override wins', async () => {
    const s = setup();
    try {
        const p = s.player(); p._preparePoster(p.options.poster); s.visible(); await flush();
        const lateLoad = s.images[0].onload;
        await p.load('https://camera.example/stream-two/index.m3u8');
        assert.equal(p.options.poster, 'https://camera.example/tn-two.jpg'); lateLoad(); assert.equal(p.media.poster, undefined);
        s.visible(); await flush(); s.images[1].finish(); assert.equal(p.media.poster, 'https://camera.example/tn-two.jpg');
        await p.load(stream, { poster: '/explicit.jpg' }); s.visible(); await flush(); s.images[2].finish();
        assert.equal(p.media.poster, '/explicit.jpg');
    } finally { s.dispose(); }
});

test('destroy and unload cancel pending work, late callbacks are harmless', async () => {
    for (const method of ['destroy', 'unload']) {
        const s = setup();
        try {
            const p = s.player(); p._preparePoster(p.options.poster); s.visible(); await flush();
            const lateLoad = s.images[0].onload; p[method](); lateLoad();
            assert.equal(s.active, 0); assert.equal(p.media.poster, undefined);
        } finally { s.dispose(); }
    }
});

test('hung request times out and frees a queue slot without retry', async () => {
    const s = setup();
    try {
        const players = [s.player(), s.player(), s.player()]; players.forEach(p => p._preparePoster(p.options.poster));
        s.visible(); await flush(); await s.env.advance(15000);
        assert.equal(players[0]._posterState, 'failed'); assert.equal(s.images.length, 3); assert.equal(s.maximum, 2);
    } finally { s.dispose(); }
});

test('fallback without IntersectionObserver remains lazy, queued and cancellable', async () => {
    const s = setup({ observer: false });
    try {
        const p = s.player(4000); p._preparePoster(p.options.poster); await flush(); assert.equal(s.images.length, 0);
        p.root.top = 0; s.env.window.emit('scroll'); await flush(); assert.equal(s.images.length, 1);
        s.images[0].finish(); assert.equal(p._posterState, 'loaded');
    } finally { s.dispose(); }
});

test('LIVE refresh uses queue only after success, never hidden/offscreen/failed/playing', async () => {
    const s = setup({ live: true });
    try {
        const p = s.player(); p.options.posterCacheBust = true; await p.load(stream);
        s.visible(); await flush(); assert.equal(s.images.length, 1);
        s.images[0].finish();
        s.env.document.hidden = true; await s.env.advance(5000); assert.equal(s.images.length, 1);
        s.env.document.hidden = false; p.root.top = 850; await s.env.advance(5000); assert.equal(s.images.length, 1);
        p.root.top = 0; await s.env.advance(5000); s.visible(); await flush(); assert.equal(s.images.length, 2);
        assert.match(s.images[1].url, /_fireplayer=/); s.images[1].finish(false);
        await s.env.advance(30000); s.visible(); await flush(); assert.equal(s.images.length, 2);
        await p.play(); await s.env.advance(5000); assert.equal(s.images.length, 2);
    } finally { s.dispose(); }
});

(async () => {
    let failed = 0;
    for (const { name, run } of tests) {
        try { await run(); console.log('PASS ' + name); }
        catch (error) { failed++; console.error('FAIL ' + name, error); }
    }
    console.log(`${tests.length - failed}/${tests.length} poster groups passed`);
    process.exitCode = failed ? 1 : 0;
})();
