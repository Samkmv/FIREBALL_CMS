'use strict';

// Run with Node + Playwright. Missing browser/dependency is a failure, never a pass.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '..');
const assets = ['fireplayer.js', 'fireplayer-video.js', 'fireplayer-audio.js', 'fireplayer-hls.js', 'fireplayer-live.js'];
let browser;
let server;
const checks = [];
const probeRequests = [];
const probeFixtures = {
    '/detect/master.m3u8': { type: 'application/vnd.apple.mpegurl', body: '#EXTM3U\n#EXT-X-STREAM-INF:BANDWIDTH=128000\nvariants/vod.m3u8?token=test\n' },
    '/detect/variants/vod.m3u8': { type: 'application/vnd.apple.mpegurl', body: '#EXTM3U\n#EXT-X-TARGETDURATION:4\n#EXTINF:4,\nsegment.ts\n#EXT-X-ENDLIST\n' },
    '/detect/audio.php': { type: 'audio/mpeg', body: 'Not downloaded for a successful audio HEAD probe' },
    '/detect/sniff.php': { type: 'application/octet-stream', headStatus: 405, body: '#EXTM3U\n#EXT-X-PLAYLIST-TYPE:EVENT\n#EXT-X-TARGETDURATION:4\n#EXTINF:4,\nsegment.ts\n' },
    '/detect/failed.php': { type: 'application/vnd.apple.mpegurl', status: 503, body: '#EXTM3U\n#EXT-X-PLAYLIST-TYPE:EVENT\n' },
    '/detect/missing.m3u8': { type: 'text/html', status: 404, body: 'Not found' }
};

(async () => {
    server = http.createServer((req, res) => {
        const pathname = new URL(req.url, 'http://localhost').pathname;
        if (probeFixtures[pathname]) {
            const fixture = probeFixtures[pathname];
            probeRequests.push({ path: pathname, method: req.method });
            res.writeHead(req.method === 'HEAD' && fixture.headStatus ? fixture.headStatus : fixture.status || 200, { 'Content-Type': fixture.type });
            res.end(req.method === 'HEAD' ? '' : fixture.body);
        } else if (pathname === '/') {
            res.setHeader('Content-Type', 'text/html');
            res.end('<!doctype html><html lang="ru"><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/public/assets/default/css/fireplayer.css"></head><body style="margin:50px;width:800px;min-height:1600px;background:#171c24"><main></main></body></html>');
        } else if (pathname.startsWith('/public/assets/default/') || pathname.startsWith('/tests/fixtures/')) {
            const file = path.resolve(root, '.' + pathname);
            if (!file.startsWith(root + path.sep) || !fs.existsSync(file)) { res.writeHead(404); res.end(); return; }
            res.setHeader('Content-Type', file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : 'application/vnd.apple.mpegurl');
            res.end(fs.readFileSync(file));
        } else { res.writeHead(404); res.end(); }
    });
    await new Promise((resolve, reject) => { server.once('error', reject); server.listen(0, '127.0.0.1', resolve); });
    const origin = 'http://127.0.0.1:' + server.address().port;
    browser = await chromium.launch({ headless: true });
    const page = await browser.newPage({ viewport: { width: 1100, height: 900 } });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(origin);
    for (const file of assets) { await page.addScriptTag({ url: origin + '/public/assets/default/js/' + file }); }

    const detected = await page.evaluate(async () => {
        const cases = [
            ['/detect/master.m3u8'], ['/detect/audio.php'], ['/detect/sniff.php'],
            ['/detect/failed.php'], ['/detect/missing.m3u8'],
            ['/detect/master.m3u8', { media: 'audio', protocol: 'file', mode: 'event' }],
            ['/unknown.php', { probe: false, contentType: 'audio/wav' }]
        ];
        return Promise.all(cases.map(async ([source, hints]) => {
            const info = await FirePlayer.detect(source, hints);
            return [info.media, info.protocol, info.mode, info.detectedBy];
        }));
    });
    assert.deepEqual(detected, [
        ['video', 'hls', 'vod', 'manifest'], ['audio', 'file', 'vod', 'content-type'],
        ['video', 'hls', 'event', 'manifest'], ['video', 'file', 'vod', 'fallback'],
        ['video', 'hls', 'live', 'extension'], ['audio', 'file', 'event', 'fallback'],
        ['audio', 'file', 'vod', 'fallback']
    ]);
    assert.equal(probeRequests.some(request => request.path === '/detect/variants/vod.m3u8' && request.method === 'GET'), true, 'Master playlist resolves its relative media playlist');
    assert.deepEqual(probeRequests.filter(request => request.path === '/detect/audio.php').map(request => request.method), ['HEAD']);
    assert.deepEqual(probeRequests.filter(request => request.path === '/detect/sniff.php').map(request => request.method), ['HEAD', 'GET']);
    checks.push('HLS master resolves VOD, PHP MIME/sniff, HEAD fallback, HTTP errors, explicit hints');

    const real = await page.evaluate(async () => {
        const canvas = document.createElement('canvas');
        canvas.width = 160; canvas.height = 90;
        const ctx = canvas.getContext('2d');
        const stream = canvas.captureStream(15);
        const chunks = [];
        const recorder = new MediaRecorder(stream, { mimeType: 'video/webm;codecs=vp8' });
        recorder.ondataavailable = event => chunks.push(event.data);
        const stopped = new Promise(resolve => { recorder.onstop = resolve; });
        recorder.start();
        for (let i = 0; i < 24; i += 1) {
            ctx.fillStyle = i % 2 ? '#ff5c35' : '#173b56'; ctx.fillRect(0, 0, 160, 90);
            await new Promise(resolve => setTimeout(resolve, 65));
        }
        recorder.stop(); await stopped; stream.getTracks().forEach(track => track.stop());
        const blob = new Blob(chunks, { type: 'video/webm' });
        window.recordedVideoBytes = Array.from(new Uint8Array(await blob.arrayBuffer()));
        const source = URL.createObjectURL(blob);
        const node = document.createElement('div'); document.querySelector('main').append(node);
        const player = new FirePlayer(node, { src: source, media: 'video', probe: false, muted: true, rememberPosition: false });
        await player.ready;
        await player.play();
        await new Promise(resolve => setTimeout(resolve, 450));
        const advanced = player.media.currentTime > 0.15;
        const clean = player.elements.status.hidden && !player.root.classList.contains('fireplayer--loading');
        player.pause();
        const before = player.media.currentTime;
        await new Promise(resolve => setTimeout(resolve, 180));
        const paused = player.media.paused && Math.abs(player.media.currentTime - before) < 0.05;
        await player.play();
        player.media.currentTime = 0.2;
        await new Promise(resolve => setTimeout(resolve, 250));
        const seek = player.media.currentTime >= 0.2;
        player.pause();
        window.realPlayer = player;
        return { advanced, clean, paused, seek };
    });
    assert.deepEqual(real, { advanced: true, clean: true, paused: true, seek: true });
    checks.push('real WebM playback, pause, resume, seek, loading clears');

    const panel = page.locator('.fireplayer__controls');
    const video = page.locator('.fireplayer').first();
    await page.mouse.move(5, 5); await page.waitForTimeout(220);
    assert.equal(await panel.evaluate(node => getComputedStyle(node).opacity), '0');
    await video.hover(); await page.waitForTimeout(220);
    assert.equal(await panel.evaluate(node => getComputedStyle(node).opacity), '1');
    await page.locator('[data-fp-action="mute"]').click();
    await page.mouse.move(5, 5); await page.waitForTimeout(220);
    assert.equal(await panel.evaluate(node => getComputedStyle(node).opacity), '0', 'Mouse-click focus must not pin controls');
    await page.keyboard.press('Tab'); await page.waitForTimeout(220);
    assert.equal(await panel.evaluate(node => getComputedStyle(node).opacity), '1', 'Keyboard focus reveals controls');
    await video.hover();
    const widthBefore = await page.locator('[data-fp-seek]').evaluate(node => node.getBoundingClientRect().width);
    await page.locator('[data-fp-action="mute"]').hover(); await page.waitForTimeout(220);
    const widthAfter = await page.locator('[data-fp-seek]').evaluate(node => node.getBoundingClientRect().width);
    assert.equal(widthBefore, widthAfter, 'Volume hover must not shift timeline');
    await page.locator('[data-fp-action="settings"]').click();
    await page.locator('[data-fp-speed]').selectOption('1.5');
    assert.equal(await page.evaluate(() => realPlayer.media.playbackRate), 1.5);
    await page.screenshot({ path: '/private/tmp/fireplayer-settings-desktop.png' });
    await page.locator('[data-fp-zoom-range]').fill('2');
    assert.equal(await page.evaluate(() => realPlayer.root.classList.contains('fireplayer--zoomed')), true);
    await page.locator('[data-fp-zoom-reset]').click();
    assert.equal(await page.evaluate(() => realPlayer.root.classList.contains('fireplayer--zoomed')), false);
    checks.push('desktop hover after click, keyboard, stable volume layout, speed and zoom');

    await page.evaluate(() => { realPlayer.setMode('live'); realPlayer._syncCapabilities(); });
    assert.equal(await page.locator('[data-fp-live-badge]').count(), 0, 'Only timeline LIVE indicator is present');
    assert.equal(await page.locator('[data-fp-action="live"]').count(), 1);
    await page.screenshot({ path: '/private/tmp/fireplayer-live-desktop.png' });

    const nativeAudio = await page.evaluate(async () => {
        const rate = 8000, frames = rate * 4;
        const buffer = new ArrayBuffer(44 + frames * 2), view = new DataView(buffer);
        const text = (offset, value) => Array.from(value).forEach((char, index) => view.setUint8(offset + index, char.charCodeAt(0)));
        text(0, 'RIFF'); view.setUint32(4, 36 + frames * 2, true); text(8, 'WAVE'); text(12, 'fmt ');
        view.setUint32(16, 16, true); view.setUint16(20, 1, true); view.setUint16(22, 1, true); view.setUint32(24, rate, true);
        view.setUint32(28, rate * 2, true); view.setUint16(32, 2, true); view.setUint16(34, 16, true); text(36, 'data'); view.setUint32(40, frames * 2, true);
        const source = URL.createObjectURL(new Blob([buffer], { type: 'audio/wav' }));
        const node = document.createElement('div'); document.querySelector('main').append(node);
        const audio = new FirePlayer(node, { src: source, media: 'audio', contentType: 'audio/wav', probe: false, muted: true, rememberPosition: false });
        await audio.ready; await audio.play();
        await new Promise(resolve => setTimeout(resolve, 500));
        const result = { advanced: audio.media.currentTime > 0.2, duration: audio.media.duration === 4, clean: audio.elements.status.hidden && !audio.root.classList.contains('fireplayer--loading'), visible: getComputedStyle(audio.elements.controls).opacity === '1' };
        audio.destroy(); URL.revokeObjectURL(source);
        return result;
    });
    assert.deepEqual(nativeAudio, { advanced: true, duration: true, clean: true, visible: true });
    checks.push('real WAV decoding, audio time advances, duration, loading clears, controls visible');

    const mobileContext = await browser.newContext({ viewport: { width: 320, height: 720 }, hasTouch: true, isMobile: true });
    const mobile = await mobileContext.newPage();
    mobile.on('pageerror', error => errors.push('mobile: ' + error.message));
    await mobile.goto(origin);
    await mobile.addStyleTag({ content: 'body { margin:12px!important; width:calc(100% - 24px)!important; min-height:700px!important; }' });
    for (const file of assets) { await mobile.addScriptTag({ url: origin + '/public/assets/default/js/' + file }); }
    await mobile.evaluate(async bytes => {
        const source = URL.createObjectURL(new Blob([new Uint8Array(bytes)], { type: 'video/webm' }));
        const node = document.createElement('div'); document.querySelector('main').append(node);
        window.mobilePlayer = new FirePlayer(node, { src: source, probe: false, muted: true, loop: true, rememberPosition: false });
        await mobilePlayer.ready; await mobilePlayer.play();
    }, await page.evaluate(() => recordedVideoBytes));
    await mobile.locator('.fireplayer__stage').tap({ position: { x: 80, y: 35 } });
    await mobile.waitForTimeout(220);
    assert.equal(await mobile.locator('.fireplayer__controls').evaluate(node => getComputedStyle(node).opacity), '1', 'Touch reveals controls');
    const narrowLayout = await mobile.evaluate(() => {
        const root = mobilePlayer.root.getBoundingClientRect();
        const controls = mobilePlayer.elements.controls.getBoundingClientRect();
        return { inside: controls.left >= root.left && controls.right <= root.right, scroll: document.documentElement.scrollWidth <= innerWidth, seekWidth: mobilePlayer.elements.seek.getBoundingClientRect().width > 0 };
    });
    assert.deepEqual(narrowLayout, { inside: true, scroll: true, seekWidth: true });
    await mobile.waitForTimeout(2800);
    assert.equal(await mobile.locator('.fireplayer__controls').evaluate(node => getComputedStyle(node).opacity), '0', 'Touch panel hides during playback without a sticky hover');
    await mobile.locator('.fireplayer__stage').tap({ position: { x: 80, y: 35 } });
    await mobile.locator('[data-fp-action="settings"]').tap();
    const mobileMenu = await mobile.evaluate(() => {
        const root = mobilePlayer.root.getBoundingClientRect(), menu = mobilePlayer.elements.settingsMenu.getBoundingClientRect();
        return { top: menu.top >= root.top, left: menu.left >= root.left, right: menu.right <= root.right, open: !mobilePlayer.elements.settingsMenu.hidden };
    });
    assert.deepEqual(mobileMenu, { top: true, left: true, right: true, open: true });
    await mobile.screenshot({ path: '/private/tmp/fireplayer-settings-mobile.png' });
    await mobile.waitForTimeout(2800);
    assert.equal(await mobile.locator('.fireplayer__controls').evaluate(node => getComputedStyle(node).opacity), '1', 'Settings stay visible while editing');
    await mobile.evaluate(() => mobilePlayer.setMode('live'));
    await mobile.screenshot({ path: '/private/tmp/fireplayer-live-mobile.png' });
    await mobileContext.close();
    checks.push('320px touch layout, timed reveal/hide, settings stay open and inside player');

    await page.evaluate(() => { realPlayer.destroy(); document.querySelector('main').innerHTML = ''; });
    await page.evaluate(() => {
        // Deterministic media model for races/errors that a live server cannot reliably reproduce.
        const state = new WeakMap();
        const get = media => {
            if (!state.has(media)) { state.set(media, { paused: true, ended: false, readyState: 0, currentTime: 0, duration: 120, error: null, seeking: false, source: '', plays: 0 }); }
            return state.get(media);
        };
        ['paused', 'ended', 'readyState', 'currentTime', 'duration', 'error', 'seeking'].forEach(key => {
            Object.defineProperty(HTMLMediaElement.prototype, key, { configurable: true, get() { return get(this)[key]; }, set(value) { get(this)[key] = value; } });
        });
        Object.defineProperty(HTMLMediaElement.prototype, 'src', { configurable: true, get() { return get(this).source; }, set(value) { get(this).source = value; } });
        HTMLMediaElement.prototype.load = function () { get(this).readyState = 0; get(this).error = null; get(this).currentTime = 0; };
        HTMLMediaElement.prototype.play = function () {
            const s = get(this); s.plays += 1;
            if (s.rejectPlay) { return Promise.reject(new DOMException('Test rejection', s.rejectPlay)); }
            s.paused = false; s.readyState = 4;
            this.dispatchEvent(new Event('play')); this.dispatchEvent(new Event('playing'));
            return Promise.resolve();
        };
        HTMLMediaElement.prototype.pause = function () { get(this).paused = true; this.dispatchEvent(new Event('pause')); };
        window.mediaState = get;
        window.makePlayer = function (options) {
            const node = document.createElement('div'); document.querySelector('main').append(node);
            return new FirePlayer(node, Object.assign({ probe: false, rememberPosition: false }, options));
        };
        window.Hls = class {
            static Events = { MEDIA_ATTACHED: 'attached', MANIFEST_PARSED: 'manifest', LEVEL_LOADED: 'level', ERROR: 'error' };
            static ErrorTypes = { NETWORK_ERROR: 'network', MEDIA_ERROR: 'media' };
            static isSupported() { return true; }
            static instances = [];
            constructor() { this.events = new Map(); this.recoveries = 0; this.destroyed = false; this.liveSyncPosition = 18; Hls.instances.push(this); }
            on(name, fn) { this.events.set(name, fn); }
            emit(name, data) { if (this.events.has(name)) { this.events.get(name)(name, data); } }
            loadSource(source) { this.source = source; }
            attachMedia(media) { this.media = media; this.emit('attached'); }
            stopLoad() {}
            recoverMediaError() { this.recoveries += 1; }
            swapAudioCodec() {}
            destroy() { this.destroyed = true; }
        };
    });
    const stateTests = await page.evaluate(async () => {
        const p = makePlayer({ src: '/movie.mp4' }); await p.ready;
        const idleClean = p.elements.status.hidden;
        await p.play();
        p.media.dispatchEvent(new Event('stalled'));
        await new Promise(resolve => setTimeout(resolve, 260));
        const bufferedStallClean = !p.root.classList.contains('fireplayer--loading');
        mediaState(p.media).readyState = 2;
        p.media.dispatchEvent(new Event('waiting'));
        await new Promise(resolve => setTimeout(resolve, 260));
        const waitingVisible = p.root.classList.contains('fireplayer--loading');
        p.media.dispatchEvent(new Event('timeupdate'));
        const frozenStillLoading = p.root.classList.contains('fireplayer--loading');
        p.media.currentTime = 1; p.media.dispatchEvent(new Event('timeupdate'));
        const advancedClears = p.elements.status.hidden && !p.root.classList.contains('fireplayer--loading');
        p.pause(); mediaState(p.media).rejectPlay = 'NotAllowedError';
        await p.play().catch(() => {});
        const deniedNonfatal = !p.root.classList.contains('fireplayer--error') && p.elements.status.hidden;
        mediaState(p.media).rejectPlay = null;
        p._showError('test', new Error('test'));
        await p.retry();
        const retryPlays = !p.media.paused && p.elements.retry.hidden && !p.root.classList.contains('fireplayer--error');
        p.setMode('live'); p._syncCapabilities();
        const liveSettings = !p.elements.settings.hidden && p.elements.speed.parentElement.hidden && Boolean(p.elements.settingsMenu.querySelector('[data-fp-zoom-range]'));
        p.unload();
        const unloaded = p.controller === null && p.elements.status.hidden && p.elements.retry.hidden && !p.root.classList.contains('fireplayer--reconnecting');
        p.destroy();
        return { idleClean, bufferedStallClean, waitingVisible, frozenStillLoading, advancedClears, deniedNonfatal, retryPlays, liveSettings, unloaded };
    });
    for (const [name, passed] of Object.entries(stateTests)) { assert.equal(passed, true, name); }
    checks.push('loading state, frozen vs advancing time, autoplay denial, retry plays, LIVE settings, unload');

    const races = await page.evaluate(async () => {
        const originalFetch = window.fetch;
        let resolveWake;
        let wakeCount = 0;
        window.fetch = async (url) => {
            if (String(url).includes('/api/streams/wake')) { wakeCount += 1; return new Promise(resolve => { resolveWake = resolve; }); }
            return originalFetch(url);
        };
        const wait = async predicate => { for (let i = 0; i < 100 && !predicate(); i++) { await new Promise(resolve => setTimeout(resolve, 1)); } };
        const p = makePlayer({ src: '/stream-old/index.m3u8' });
        await wait(() => resolveWake);
        const playing = p.play();
        const oldLoad = p.ready;
        await p.load('/new.mp4');
        resolveWake({ ok: true, status: 200, json: async () => ({ ready: true }) });
        await oldLoad; await playing;
        const staleIgnored = p.info.src === '/new.mp4' && !p.controller && p.media.paused;
        p.destroy();
        resolveWake = null;
        const early = makePlayer({ src: '/stream-early/index.m3u8' });
        await wait(() => resolveWake);
        const earlyPlay = early.play();
        resolveWake({ ok: true, status: 200, json: async () => ({ ready: true }) });
        await earlyPlay;
        const earlyPlays = early.controller.engine === 'hls.js' && !early.media.paused;
        early.destroy();
        window.fetch = async () => ({ ok: true, status: 200, json: async () => ({ ready: false }) });
        const before = Hls.instances.length;
        const bad = makePlayer({ src: '/stream-bad/index.m3u8' }); await bad.ready.catch(() => {});
        const readinessGate = Hls.instances.length === before && bad.root.classList.contains('fireplayer--error');
        bad.destroy();
        window.fetch = originalFetch;
        return { staleIgnored, earlyPlays, readinessGate, wakeCount };
    });
    assert.equal(races.staleIgnored, true); assert.equal(races.earlyPlays, true); assert.equal(races.readinessGate, true); assert.equal(races.wakeCount, 2);
    checks.push('early play, stale wake cancellation, failed readiness blocks attachment');

    const hls = await page.evaluate(async () => {
        const p = makePlayer({ src: '/hls/index.m3u8', reconnect: false }); await p.ready;
        const first = p.controller.hls;
        first.emit('error', { fatal: true, type: 'network' });
        await new Promise(resolve => setTimeout(resolve, 550));
        const disabled = p.root.classList.contains('fireplayer--error') && p.controller.hls === first;
        await p.retry();
        const manualWorks = p.controller.hls !== first && first.destroyed && !p.media.paused;
        p.destroy();
        const recover = makePlayer({ src: '/hls/index.m3u8' }); await recover.ready;
        const engine = recover.controller.hls;
        for (let i = 0; i < 3; i++) { engine.emit('error', { fatal: true, type: 'media' }); }
        const boundedRecovery = engine.recoveries === 2 && recover.root.classList.contains('fireplayer--error');
        recover.destroy();
        return { disabled, manualWorks, boundedRecovery };
    });
    for (const [name, passed] of Object.entries(hls)) { assert.equal(passed, true, name); }
    checks.push('HLS reconnect opt-out, manual retry, bounded decoder recovery');

    const decoderRace = await page.evaluate(async () => {
        const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
        const installDetachRecovery = engine => {
            engine.recoverMediaError = function () {
                this.recoveries += 1;
                this.media.pause(); this.media.load();
                setTimeout(() => {
                    mediaState(this.media).readyState = 4;
                    this.media.dispatchEvent(new Event('canplay'));
                }, 20);
            };
        };
        const playing = makePlayer({ src: '/hls/playing.m3u8' }); await playing.ready; await playing.play();
        installDetachRecovery(playing.controller.hls);
        playing.controller.hls.emit('error', { fatal: true, type: 'media' });
        await wait(60);
        const resumesIntent = !playing.media.paused && playing._playRequested && playing.elements.status.hidden && mediaState(playing.media).plays === 2;
        playing.controller.hls.emit('error', { fatal: true, type: 'media' });
        playing.pause(); await wait(60);
        const explicitPauseWins = playing.media.paused && !playing._playRequested && mediaState(playing.media).plays === 2 && playing.elements.status.hidden;
        playing.destroy();

        const idle = makePlayer({ src: '/hls/idle.m3u8' }); await idle.ready;
        installDetachRecovery(idle.controller.hls);
        idle.controller.hls.emit('error', { fatal: true, type: 'media' }); await wait(60);
        const pausedStaysPaused = idle.media.paused && mediaState(idle.media).plays === 0;
        idle.destroy();

        const pending = makePlayer({ src: '/hls/pending.m3u8' }); await pending.ready;
        let rejectOld;
        const ordinaryPlay = HTMLMediaElement.prototype.play;
        pending.media.play = function () {
            pending.media.play = ordinaryPlay;
            return new Promise((resolve, reject) => { rejectOld = reject; });
        };
        const oldAttempt = pending.play();
        installDetachRecovery(pending.controller.hls);
        pending.controller.hls.emit('error', { fatal: true, type: 'media' });
        await wait(50);
        rejectOld(new DOMException('Old detached request rejected', 'NotAllowedError'));
        await oldAttempt;
        const staleRejectionIgnored = !pending.media.paused && pending._playRequested && pending.elements.status.hidden && !pending.root.classList.contains('fireplayer--error');
        pending.destroy();
        return { resumesIntent, explicitPauseWins, pausedStaysPaused, staleRejectionIgnored };
    });
    for (const [name, passed] of Object.entries(decoderRace)) { assert.equal(passed, true, name); }
    checks.push('HLS decoder detach/load preserves play intent, explicit pause wins, stale promise rejection ignored');

    const startupTimeout = await page.evaluate(async () => {
        const p = makePlayer({ src: '/never-starts.mp4', startupTimeout: 1000 }); await p.ready;
        p.media.play = () => new Promise(() => {});
        p.play();
        await new Promise(resolve => setTimeout(resolve, 1150));
        const result = p.root.classList.contains('fireplayer--error') && !p.elements.retry.hidden && !p.root.classList.contains('fireplayer--loading');
        p.destroy();
        return result;
    });
    assert.equal(startupTimeout, true, 'A never-settled play request ends with a usable retry instead of an infinite spinner');
    checks.push('bounded startup timeout for a never-settled play request');

    await page.addScriptTag({ url: origin + '/public/assets/default/js/fireplayer-init.js' });
    const lifecycle = await page.evaluate(async () => {
        const tick = () => new Promise(resolve => setTimeout(resolve, 20));
        const p = makePlayer({ src: '/track.mp3', media: 'audio' }); await p.ready; await tick();
        window.audioPlayer = p;
        const audioVisible = getComputedStyle(p.elements.controls).opacity === '1';
        const other = document.createElement('section'); document.body.append(other); other.append(p.root); await tick();
        const movedLives = !p._destroyed;
        p.root.remove(); await tick();
        const removedDestroyed = p._destroyed && p.media.paused;
        other.remove();
        return { audioVisible, movedLives, removedDestroyed };
    });
    for (const [name, passed] of Object.entries(lifecycle)) { assert.equal(passed, true, name); }
    checks.push('audio controls always visible, DOM move retained, removed player disposed');
    assert.deepEqual(errors, [], 'No uncaught browser exceptions');
    process.stdout.write(JSON.stringify({ status: 'ok', checks }, null, 2) + '\n');
})().catch(error => {
    process.stderr.write((error.stack || String(error)) + '\n'); process.exitCode = 1;
}).finally(async () => {
    if (browser) { await browser.close(); }
    if (server) { await new Promise(resolve => server.close(resolve)); }
});
