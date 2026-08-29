'use strict';

const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('playwright');

(async function () {
    const root = path.resolve(__dirname, '..');
    const executablePath = chromium.executablePath();
    const fs = require('node:fs');
    if (!fs.existsSync(executablePath)) {
        process.stdout.write(JSON.stringify({ status: 'skipped', reason: 'Playwright browser is not installed; use tests/fireplayer_fixture.html for in-app browser verification.' }) + '\n');
        return;
    }
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();

    await page.setContent('<!doctype html><html lang="ru"><head></head><body><div id="manual" class="fire-player" data-fire-player-manual></div><div id="auto" class="fire-player" data-src="/media/test.mp4" data-probe="false"></div></body></html>');
    await page.evaluate(function () {
        HTMLMediaElement.prototype.load = function () {};
        HTMLMediaElement.prototype.play = function () {
            this.dispatchEvent(new Event('play'));
            this.dispatchEvent(new Event('playing'));
            return Promise.resolve();
        };
        HTMLMediaElement.prototype.pause = function () {
            this.dispatchEvent(new Event('pause'));
        };
    });

    for (const file of ['fireplayer.js', 'fireplayer-video.js', 'fireplayer-audio.js', 'fireplayer-live.js']) {
        await page.addScriptTag({ path: path.join(root, 'public/assets/default/js', file) });
    }

    const result = await page.evaluate(async function () {
        const video = await FirePlayer.detect('/media/movie.mp4', { probe: false });
        const audio = await FirePlayer.detect('/media/song.mp3', { probe: false });
        const hls = await FirePlayer.detect('/stream/camera/index.m3u8', { probe: false });
        const dash = await FirePlayer.detect('/stream/movie/manifest.mpd', { probe: false });
        const player = new FirePlayer('#manual', { probe: false });
        await player.load('/media/song.mp3', { probe: false });
        const loadedAsAudio = player.media instanceof HTMLAudioElement;
        await player.load('/media/movie.mp4', { probe: false });
        const loadedAsVideo = player.media instanceof HTMLVideoElement;
        player.mute(true);
        const muted = player.media.muted;
        player.destroy();
        return {
            video: video.media + ':' + video.protocol,
            audio: audio.media + ':' + audio.protocol,
            hls: hls.protocol + ':' + hls.mode,
            dash: dash.protocol,
            loadedAsAudio: loadedAsAudio,
            loadedAsVideo: loadedAsVideo,
            muted: muted,
            destroyed: document.getElementById('manual').innerHTML === ''
        };
    });

    assert.equal(result.video, 'video:file');
    assert.equal(result.audio, 'audio:file');
    assert.equal(result.hls, 'hls:live');
    assert.equal(result.dash, 'dash');
    assert.equal(result.loadedAsAudio, true);
    assert.equal(result.loadedAsVideo, true);
    assert.equal(result.muted, true);
    assert.equal(result.destroyed, true);

    await browser.close();
    process.stdout.write(JSON.stringify({ status: 'ok', ...result }) + '\n');
})().catch(function (error) {
    process.stderr.write((error && error.stack ? error.stack : String(error)) + '\n');
    process.exitCode = 1;
});
