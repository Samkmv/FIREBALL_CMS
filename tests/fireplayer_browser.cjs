#!/usr/bin/env node
'use strict';
// Set NODE_PATH to an external Playwright installation if it is not a project dependency.
// Real browser DOM, mocked transport/decoder: not a Safari or camera acceptance test.
const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium, firefox } = require('playwright');
const root = path.resolve(__dirname, '../public/assets/default');

async function checkTimeDisplay(page, name) {
    await page.evaluate(name => {
        const instance = window[name];
        Object.defineProperty(instance.media, 'duration', { configurable: true, value: 180 });
        instance.media.currentTime = 15;
        instance.setMode('vod');
        instance._syncTimeline();
    }, name);
    const times = page.locator(name === 'player' ? '#player .fireplayer__times' : '.fireplayer--audio .fireplayer__times');
    assert.equal((await times.innerText()).replace(/\s+/g, ''), '0:15/3:00');
    assert.equal(await times.locator('[data-fp-time-separator]').isVisible(), true);
    await page.evaluate(name => { window[name].setMode('live'); window[name]._syncTimeline(); }, name);
    assert.equal(await times.locator('[data-fp-time-separator]').isVisible(), false);
    assert.equal(await times.locator('[data-fp-duration]').isVisible(), false);
    await page.evaluate(name => {
        const instance = window[name];
        instance.setMode('vod');
        Object.defineProperty(instance.media, 'duration', { configurable: true, value: NaN });
        instance._syncTimeline();
    }, name);
    assert.equal(await times.locator('[data-fp-time-separator]').isVisible(), false);
}

(async () => {
    for (const [name, browserType] of Object.entries({ chromium, firefox })) {
        const browser = await browserType.launch({ headless: true });
        try {
            const page = await browser.newPage({ viewport: { width: 1100, height: 800 } });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            let wakeCount = 0, manifests = 0;
            await page.route('**/*', async route => {
                const url = route.request().url();
                if (url.endsWith('/api/streams/wake')) {
                    wakeCount++; return route.fulfill({ json: { success: true, ready: true } });
                }
                if (url.includes('.m3u8')) { manifests++; }
                return route.fulfill({ contentType: 'text/html', body: '<html lang="en"><body><div id="player"></div></body></html>' });
            });
            await page.goto('https://cms.test/');
            await page.addStyleTag({ path: path.join(root, 'css/fireplayer.css') });
            await page.evaluate(() => {
                window.canViewVideoStatus = false;
                window.activeHls = 0;
                const playing = new WeakSet();
                Object.defineProperty(HTMLMediaElement.prototype, 'paused', { configurable: true, get() { return !playing.has(this); } });
                Object.defineProperty(HTMLMediaElement.prototype, 'readyState', { configurable: true, get() { return 4; } });
                HTMLMediaElement.prototype.play = function () { playing.add(this); this.dispatchEvent(new Event('play')); this.dispatchEvent(new Event('playing')); return Promise.resolve(); };
                HTMLMediaElement.prototype.pause = function () { playing.delete(this); this.dispatchEvent(new Event('pause')); };
                HTMLMediaElement.prototype.load = function () {};
                class Hls {
                    static Events = { MEDIA_ATTACHED: 'attached', MANIFEST_PARSED: 'manifest', LEVEL_LOADED: 'level', ERROR: 'error' };
                    static ErrorTypes = { NETWORK_ERROR: 'network', MEDIA_ERROR: 'media' };
                    static isSupported() { return true; }
                    constructor() {
                        window.activeHls++; this.handlers = new Map(); this.currentLevel = -1; this.autoLevelEnabled = true;
                        this.levels = [{ height: 720, bitrate: 2000000 }, { height: 1080, bitrate: 5000000 }];
                    }
                    on(event, fn) { this.handlers.set(event, fn); }
                    attachMedia(media) { this.media = media; this.handlers.get('attached')(); }
                    loadSource() { queueMicrotask(() => { this.handlers.get('manifest')('manifest', { levels: this.levels }); this.handlers.get('level')('level', { details: { live: true } }); }); }
                    startLoad() {}
                    stopLoad() {}
                    destroy() { window.activeHls--; this.handlers.clear(); }
                }
                window.Hls = Hls;
            });
            for (const file of ['fireplayer.js', 'fireplayer-hls.js', 'fireplayer-video.js', 'fireplayer-audio.js', 'fireplayer-live.js']) {
                await page.addScriptTag({ path: path.join(root, 'js', file) });
            }
            await page.evaluate(() => {
                window.player = new FirePlayer('#player', { src: 'https://camera.test/stream-one/index.m3u8', forceHlsJs: true, probe: false });
                player.root.style.width = '800px';
            });
            assert.equal(wakeCount, 0); assert.equal(manifests, 0);
            await page.evaluate(() => player.play());
            assert.equal(wakeCount, 1);
            assert.equal(await page.evaluate(() => player._state), 'playing');
            const quality = page.locator('[data-fp-setting="quality"]');
            await page.evaluate(() => player._setSettingsOpen(true));
            assert.equal(await quality.locator('option').count(), 3);
            await quality.selectOption('1'); assert.equal(await page.evaluate(() => player.controller.hls.currentLevel), 1);
            await quality.selectOption('-1'); assert.equal(await page.evaluate(() => player.controller.hls.currentLevel), -1);
            await page.evaluate(() => {
                const ru = player.media.addTextTrack('subtitles', 'Русский', 'ru'); ru.mode = 'showing';
                player.media.addTextTrack('subtitles', 'English', 'en');
            });
            const subtitles = page.locator('[data-fp-setting="subtitles"]');
            await subtitles.locator('option[value="1"]').waitFor({ state: 'attached' });
            await subtitles.selectOption('1');
            assert.deepEqual(await page.evaluate(() => Array.from(player.media.textTracks).map(track => track.mode)), ['disabled', 'showing']);
            await subtitles.selectOption('-1');
            assert.deepEqual(await page.evaluate(() => Array.from(player.media.textTracks).map(track => track.mode)), ['disabled', 'disabled']);
            await page.evaluate(() => { player.pause(); player._setSettingsOpen(false); });
            assert.equal(await page.evaluate(() => player._state), 'paused');
            assert.equal(await page.evaluate(() => typeof player.fullscreen), 'function');
            await page.evaluate(() => {
                window.pipCalls = 0;
                Object.defineProperty(document, 'pictureInPictureEnabled', { configurable: true, value: true });
                player.media.requestPictureInPicture = async () => { window.pipCalls++; };
                player._syncCapabilities();
                player._showControls();
            });
            for (const width of [800, 390, 320]) {
                await page.evaluate(width => { player.root.style.width = width + 'px'; }, width);
                await page.locator('#player').hover();
                await checkTimeDisplay(page, 'player');
                const pip = page.locator('#player [data-fp-action="pip"]');
                assert.equal(await pip.isVisible(), true, `${name}: PiP visible at ${width}px`);
                const bounds = await pip.boundingBox();
                const playerBounds = await page.locator('#player').boundingBox();
                assert.ok(bounds.x >= playerBounds.x && bounds.x + bounds.width <= playerBounds.x + playerBounds.width);
                await pip.click();
            }
            assert.equal(await page.evaluate(() => window.pipCalls), 3);
            await page.evaluate(() => {
                Object.defineProperty(document, 'pictureInPictureEnabled', { configurable: true, value: false });
                player.media.webkitSetPresentationMode = mode => { player.media.webkitPresentationMode = mode; };
                player._syncCapabilities();
            });
            await page.locator('#player [data-fp-action="pip"]').click();
            assert.equal(await page.evaluate(() => player.media.webkitPresentationMode), 'picture-in-picture');
            await page.locator('#player [data-fp-action="pip"]').click();
            assert.equal(await page.evaluate(() => player.media.webkitPresentationMode), 'inline');
            await page.evaluate(() => { delete player.media.webkitSetPresentationMode; player._syncCapabilities(); });
            assert.equal(await page.locator('#player [data-fp-action="pip"]').isVisible(), false);
            await page.evaluate(() => {
                player.setStatus('Connecting…');
                if (player.elements.status.hidden) { throw new Error('Normal status hidden from non-creator'); }
                player._showError('Unavailable', Object.assign(new Error(), { code: 'CAMERA_NOT_READY' }));
            });
            assert.equal(await page.locator('[data-fp-action="retry"]').isVisible(), true);
            await page.evaluate(async () => {
                await player.load('https://camera.test/stream-two/index.m3u8');
                if (window.activeHls !== 1) { throw new Error('Duplicate Hls instance'); }
                player.unload(); player.destroy();
                const container = document.createElement('div'); document.body.appendChild(container);
                window.audio = new FirePlayer(container, { src: '/song.mp3', media: 'audio', probe: false, title: 'Test', artist: 'Artist', album: 'Album' });
                await audio.ready; await audio.play(); audio.pause();
            });
            await checkTimeDisplay(page, 'audio');
            assert.equal(await page.locator('.fireplayer--audio [data-fp-action="pip"]').isVisible(), false);
            await page.evaluate(() => audio.destroy());
            assert.equal(await page.evaluate(() => window.activeHls), 0);
            await page.evaluate(() => {
                Object.defineProperty(navigator, 'userAgent', { configurable: true, value: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Version/18.0 Mobile Safari/604.1' });
                Object.defineProperty(navigator, 'platform', { configurable: true, value: 'iPhone' });
                HTMLMediaElement.prototype.canPlayType = () => 'probably';
                window.firePlayerConfig = { forceHlsJsOnApple: true };
                window.canViewVideoDiagnostics = true;
                const host = document.createElement('section');
                host.innerHTML = '<div id="engine-auto" class="fire-player" data-src="https://camera.test/stream-one/index.m3u8" data-probe="false"></div>' +
                    '<div class="post-content"><video controls data-hls-src="https://camera.test/stream-two/index.m3u8"></video></div>' +
                    '<div id="engine-api"></div>';
                document.body.appendChild(host);
            });
            await page.addScriptTag({ path: path.join(root, 'js/fireplayer-diagnostics.js') });
            await page.addScriptTag({ path: path.join(root, 'js/fireplayer-init.js') });
            await page.evaluate(async () => {
                window.enginePlayers = [FirePlayer.get(document.querySelector('#engine-auto')), FirePlayer.get(document.querySelector('.post-content .fireplayer')),
                    FirePlayer.mount('#engine-api', { src: 'https://camera.test/stream-three/index.m3u8', probe: false })];
                for (const instance of enginePlayers) { await instance.play(); }
            });
            assert.deepEqual(await page.evaluate(() => enginePlayers.map(instance => instance.controller.engine)), ['hls.js', 'hls.js', 'hls.js']);
            assert.equal(await page.locator('[data-fp-diagnostic="engine"]').count(), 3);
            assert.deepEqual(await page.locator('[data-fp-diagnostic="engineRequested"]').allTextContents(), ['hls.js', 'hls.js', 'hls.js']);
            await page.evaluate(async () => {
                window.firePlayerConfig.forceHlsJsOnApple = false;
                for (const instance of enginePlayers) { await instance.load(instance.options.src); await instance.play(); }
            });
            assert.deepEqual(await page.evaluate(() => enginePlayers.map(instance => instance.controller.engine)), ['native', 'native', 'native']);
            assert.deepEqual(await page.locator('[data-fp-diagnostic="engine"]').allTextContents(), ['Browser native', 'Browser native', 'Browser native']);
            assert.equal(await page.evaluate(() => window.activeHls), 0);
            await page.evaluate(() => enginePlayers.forEach(instance => instance.destroy()));
            assert.deepEqual(errors, []);
            console.log(`PASS ${name}: lazy wake, controls, play/pause, quality Auto/manual, subtitles, responsive time display, mobile PiP controls (standard/WebKit), status/error, source replacement, audio, Apple engine setting (auto/legacy/API), diagnostics, cleanup`);
        } finally { await browser.close(); }
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
