'use strict';
// Regression checks use Web Audio doubles; they never play through the speakers.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const filename = path.join(__dirname, '../plugins/toy-car-rental/assets/toy-rental.js');
const source = fs.readFileSync(filename, 'utf8');
const block = source.slice(source.indexOf('    const prepareCustomSound ='), source.indexOf('    const request ='));
let checks = 0;
const check = (condition, message) => { checks++; assert.ok(condition, message); };
const settle = async () => { for (let i = 0; i < 10; i++) await Promise.resolve(); };

function fixture(options = {}) {
    const contexts = [];
    const listeners = {};
    const timers = new Map();
    const fetchCalls = [];
    let nextTimer = 0;
    class FakeAudioContext {
        constructor() {
            this.state = 'suspended'; this.currentTime = 40; this.destination = {};
            this.oscillators = []; this.sources = []; this.gains = []; this.resumes = 0;
            contexts.push(this);
        }
        resume() { this.resumes++; this.state = 'running'; return Promise.resolve(); }
        decodeAudioData() {
            if (options.decodeError) return Promise.reject(new Error('Unsupported audio'));
            return Promise.resolve({ duration: options.duration ?? 4 });
        }
        makeSource() {
            return {
                frequency: {}, starts: [], stops: [], disconnected: false,
                connect(target) { this.target = target; }, disconnect() { this.disconnected = true; },
                start(...args) { if (this.failStart) throw new Error('Audio start failed'); this.starts.push(args); },
                stop(time) { this.stops.push(time); },
            };
        }
        createOscillator() { const node = this.makeSource(); this.oscillators.push(node); return node; }
        createBufferSource() { const node = this.makeSource(); node.failStart = options.startError; this.sources.push(node); return node; }
        createGain() {
            const node = {
                gain: {
                    value: 1, events: [],
                    setValueAtTime(value, time) { this.events.push(['set', value, time]); },
                    linearRampToValueAtTime(value, time) { this.events.push(['linear', value, time]); },
                    exponentialRampToValueAtTime(value, time) { this.events.push(['exponential', value, time]); },
                },
                connect(target) { this.target = target; }, disconnect() { this.disconnected = true; },
            };
            this.gains.push(node); return node;
        }
    }
    const settings = { soundEnabled: options.enabled !== false, soundUrl: options.url ?? '' };
    const window = {
        location: { href: 'https://cms.example/profile/toy-rental', origin: 'https://cms.example' },
        setTimeout(callback, delay) { const id = ++nextTimer; timers.set(id, { callback, delay }); return id; },
        clearTimeout(id) { timers.delete(id); },
    };
    if (!options.noAudio) window[options.webkit ? 'webkitAudioContext' : 'AudioContext'] = FakeAudioContext;
    const response = () => ({
        ok: options.responseOk !== false,
        headers: { get: () => String(options.headerBytes ?? 100) },
        arrayBuffer: async () => new ArrayBuffer(options.bytes ?? 100),
    });
    const fetch = (...args) => {
        fetchCalls.push(args);
        if (options.fetch) return options.fetch(...args);
        if (options.networkError) return Promise.reject(new Error('Offline'));
        return Promise.resolve(response());
    };
    const api = vm.runInNewContext(`let audioContext, activeAudioCue, customSoundBuffer;
        let customSoundLoading = false, customSoundFailed = false;
        ${block}
        ({ unlockAudio, playSound, get active() { return activeAudioCue; }, get buffer() { return customSoundBuffer; } })`, {
        settings, window, fetch, URL, AbortController,
        document: { addEventListener(name, callback, listenerOptions) { listeners[name] = { callback, options: listenerOptions }; } },
    }, { filename });
    return { api, contexts, settings, fetchCalls, timers, listeners, response };
}

async function run() {
    const builtin = fixture();
    builtin.api.playSound(); check(builtin.contexts.length === 0, 'Expiry never creates an unapproved audio context');
    builtin.api.unlockAudio({ isTrusted: false }); check(builtin.contexts.length === 0, 'Synthetic gesture does not unlock audio');
    builtin.listeners.pointerdown.callback({ isTrusted: true });
    const context = builtin.contexts[0];
    check(context.resumes === 1 && context.state === 'running', 'Real gesture resumes context');
    check(context.oscillators.length === 0 && context.sources.length === 0, 'Gesture itself stays silent');
    builtin.api.playSound();
    check(context.oscillators.length === 14, 'Default chime has seven notes and subtle overtones');
    check(context.oscillators.every(node => node.type === 'sine'), 'Default voices are gentle sine waves');
    const duration = Math.max(...context.oscillators.map(node => node.stops[0])) - Math.min(...context.oscillators.map(node => node.starts[0][0]));
    check(Math.abs(duration - 3.02) < 1e-8, 'Default chime lasts approximately three seconds');
    check(Math.max(...context.gains.map(node => node.gain.events[1][1])) === .14, 'Default gain remains moderate');
    builtin.api.playSound(); check(context.oscillators.length === 14, 'Simultaneous expiries do not overlap');
    context.oscillators.forEach(node => node.onended());
    check(context.oscillators.every(node => node.disconnected) && context.gains.every(node => node.disconnected), 'Default cue cleans every node');
    check(!builtin.api.active, 'Default cue releases its overlap guard');
    context.state = 'suspended'; builtin.api.playSound(); check(context.oscillators.length === 14, 'Suspended context does not queue later sound');
    builtin.listeners.pointerup.callback({ isTrusted: true }); check(context.state === 'running', 'Touch release can resume audio');
    context.state = 'suspended'; builtin.listeners.keydown.callback({ isTrusted: true }); check(context.state === 'running', 'Keyboard interaction can resume audio');
    builtin.settings.soundEnabled = false; builtin.api.playSound(); check(context.oscillators.length === 14, 'Disabled sound stays silent');
    const disabled = fixture({ enabled: false, url: '/profile/toy-rental/sound?v=test.mp3' });
    disabled.api.unlockAudio({ isTrusted: true }); check(!disabled.contexts.length && !disabled.fetchCalls.length, 'Disabled sound does not load custom media');

    const custom = fixture({ url: '/profile/toy-rental/sound?v=test.mp3', duration: 45 });
    custom.api.unlockAudio({ isTrusted: true }); await settle();
    check(custom.fetchCalls.length === 1, 'Custom sound loads once after a trusted gesture');
    check(custom.fetchCalls[0][1].credentials === 'same-origin', 'Protected audio request carries same-origin credentials');
    check(custom.api.buffer?.duration === 45, 'Custom sound decodes into a reusable buffer');
    check(!custom.contexts[0].sources.length && !custom.contexts[0].oscillators.length, 'Finishing download does not play unexpectedly');
    custom.api.unlockAudio({ isTrusted: true }); await settle(); check(custom.fetchCalls.length === 1, 'Repeated gestures reuse the loaded buffer');
    custom.api.playSound();
    const customContext = custom.contexts[0];
    check(customContext.sources.length === 1 && !customContext.oscillators.length, 'Configured audio replaces the built-in chime');
    const customNode = customContext.sources[0];
    check(customNode.starts[0][2] === 30 && Math.abs(customNode.stops[0] - customNode.starts[0][0] - 30) < 1e-8, 'Long custom sound stops at thirty seconds');
    check(customContext.gains[0].gain.events[0][1] === 0 && customContext.gains[0].gain.events.at(-1)[1] === 0, 'Custom playback fades in and out');
    custom.api.playSound(); check(customContext.sources.length === 1, 'Custom playback suppresses concurrent expiry sounds');
    customNode.onended(); check(customNode.disconnected && customContext.gains[0].disconnected && !custom.api.active, 'Custom audio cleans nodes and guard');
    custom.api.playSound(); check(customContext.sources.length === 2, 'Later expiry reuses custom audio');

    let release;
    const loading = fixture({ url: '/profile/toy-rental/sound?v=slow.mp3', fetch: () => new Promise(resolve => { release = resolve; }) });
    loading.api.unlockAudio({ isTrusted: true }); loading.api.playSound();
    check(loading.contexts[0].oscillators.length === 14, 'Expiry during loading uses immediate fallback');
    release(loading.response()); await settle();
    check(loading.contexts[0].sources.length === 0, 'Late download never queues a custom cue');
    loading.contexts[0].oscillators.forEach(node => node.onended()); loading.api.playSound();
    check(loading.contexts[0].sources.length === 1, 'A future expiry uses audio that finished loading');

    const timedOut = fixture({ url: '/profile/toy-rental/sound?v=timeout.mp3', fetch: (url, options) => new Promise((resolve, reject) => {
        options.signal.addEventListener('abort', () => reject(new Error('Request aborted')));
    }) });
    timedOut.api.unlockAudio({ isTrusted: true });
    const loadTimer = [...timedOut.timers.values()].find(timer => timer.delay === 8000);
    check(Boolean(loadTimer), 'Custom sound loading has an eight-second timeout');
    loadTimer.callback(); await settle(); timedOut.api.playSound();
    check(timedOut.contexts[0].oscillators.length === 14 && !timedOut.timers.size, 'Timed-out download is aborted and falls back cleanly');

    for (const failure of [{ networkError: true }, { decodeError: true }, { responseOk: false }, { headerBytes: 6 * 1024 * 1024 }, { bytes: 0 }, { bytes: 5 * 1024 * 1024 + 1 }, { duration: 0 }, { url: 'https://other.example/sound.mp3' }]) {
        const failed = fixture({ url: '/profile/toy-rental/sound?v=bad.mp3', ...failure });
        failed.api.unlockAudio({ isTrusted: true }); await settle(); failed.api.playSound();
        check(failed.contexts[0].oscillators.length === 14 && failed.contexts[0].sources.length === 0, `Failed custom sound uses fallback: ${JSON.stringify(failure)}`);
    }
    const startFailure = fixture({ url: '/profile/toy-rental/sound?v=test.wav', startError: true });
    startFailure.api.unlockAudio({ isTrusted: true }); await settle(); startFailure.api.playSound();
    check(startFailure.contexts[0].oscillators.length === 14 && startFailure.contexts[0].sources[0].disconnected, 'Playback failure cleans custom nodes and immediately falls back');
    const safari = fixture({ webkit: true }); safari.api.unlockAudio({ isTrusted: true }); safari.api.playSound(); check(safari.contexts[0].oscillators.length === 14, 'WebKit audio context remains supported');
    const absent = fixture({ noAudio: true }); absent.api.unlockAudio({ isTrusted: true }); absent.api.playSound(); check(!absent.contexts.length, 'Missing audio APIs remain harmless');
    console.log(`${checks} rental audio checks passed`);
}
run().catch(error => { console.error(error); process.exitCode = 1; });
