#!/usr/bin/env node
'use strict';
// Set NODE_PATH to an external Playwright installation if it is not a project dependency.
// Real browser DOM, mocked transport/decoder: not a Safari or camera acceptance test.
const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium, firefox } = require('playwright');
const root = path.resolve(__dirname, '../public/assets/default');

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
                await audio.ready; await audio.play(); audio.pause(); audio.destroy();
            });
            assert.equal(await page.evaluate(() => window.activeHls), 0);
            assert.deepEqual(errors, []);
            console.log(`PASS ${name}: lazy wake, controls, play/pause, quality Auto/manual, subtitles, status/error, source replacement, audio, cleanup`);
        } finally { await browser.close(); }
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
