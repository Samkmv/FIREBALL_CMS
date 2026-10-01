'use strict';
// Production adapter with deterministic media/transport doubles, not real iPhone decoding.
const assert = require('node:assert/strict');
const { environment, bounded, flush, stream, Target } = require('./fireplayer_native_startup.cjs');

const iphone = { userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Version/18.0 Mobile Safari/604.1', platform: 'iPhone', maxTouchPoints: 5 };
function hlsConstructor(supported = true) {
    return class Hls {
        static Events = { MEDIA_ATTACHED: 'attached', MANIFEST_PARSED: 'manifest', LEVEL_LOADED: 'level', ERROR: 'error' };
        static ErrorTypes = { NETWORK_ERROR: 'network', MEDIA_ERROR: 'media' };
        static instances = [];
        static isSupported() { return supported; }
        constructor() { this.handlers = new Map(); this.loaded = []; this.constructor.instances.push(this); }
        on(event, handler) { this.handlers.set(event, handler); }
        attachMedia(media) { this.media = media; this.handlers.get('attached')(); }
        loadSource(source) { this.loaded.push(source); }
        stopLoad() {}
        destroy() { this.destroyed = true; }
    };
}

(async () => {
    for (const scenario of [
        { name: 'site off: native iPhone', enabled: false, expected: 'native', requested: 'native' },
        { name: 'site on: hls.js iPhone', enabled: true, expected: 'hls.js', requested: 'hls.js' },
        { name: 'site on: hls.js desktop-mode iPad', enabled: true, navigator: { userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X) Version/18.0 Safari/605.1.15', platform: 'MacIntel', maxTouchPoints: 5 }, expected: 'hls.js', requested: 'hls.js' },
        { name: 'unsupported hls.js: safe native fallback', enabled: true, supported: false, expected: 'native', requested: 'hls.js', fallback: 'hls-unsupported' },
        { name: 'explicit false overrides site preference', enabled: true, options: { forceHlsJs: false }, expected: 'native', requested: 'native' },
        { name: 'explicit true overrides site off', enabled: false, options: { forceHlsJs: true }, expected: 'hls.js', requested: 'hls.js' },
        { name: 'explicit force stays strict on unsupported devices', enabled: true, supported: false, options: { forceHlsJs: true }, unhandled: true },
        { name: 'unsupported platform without native HLS stays unhandled', enabled: true, supported: false, native: false, unhandled: true },
        { name: 'non-Apple playback remains automatic', enabled: true, navigator: { userAgent: 'Chrome/130.0', platform: 'Linux' }, expected: 'hls.js', requested: 'auto' }
    ]) {
        const Hls = hlsConstructor(scenario.supported !== false);
        const env = environment({ Hls, native: scenario.native !== false });
        Object.assign(env.window.navigator, scenario.navigator || iphone);
        env.window.firePlayerConfig = { forceHlsJsOnApple: scenario.enabled };
        const player = env.player({ options: scenario.options });
        try {
            const result = await bounded(env.adapter(player, { src: stream }), scenario.name);
            if (scenario.unhandled) {
                assert.equal(result.handled, false);
                assert.equal(Hls.instances.length, 0);
                assert.equal(player.media.loadCalls.length, 0);
            } else {
                player._cleanups.push(result.cleanup);
                assert.equal(result.controller.engine, scenario.expected);
                assert.equal(result.controller.engineRequested, scenario.requested);
                assert.equal(result.controller.engineFallback, scenario.fallback || '');
                assert.equal(Hls.instances.length, scenario.expected === 'hls.js' ? 1 : 0);
                if (Hls.instances.length) { assert.deepEqual(Hls.instances[0].loaded, [stream]); }
            }
            console.log('PASS ' + scenario.name);
        } finally { env.dispose(); }
        assert.ok(Hls.instances.every(instance => instance.destroyed));
    }

    for (const cancel of [false, true]) {
        const env = environment();
        Object.assign(env.window.navigator, iphone);
        env.window.firePlayerConfig = { forceHlsJsOnApple: true, hlsScriptUrl: '/missing-hls.js' };
        const create = env.document.createElement;
        env.document.createElement = name => {
            const element = create(name); element.remove = () => {}; return element;
        };
        env.document.head = new Target();
        env.document.head.appendChild = script => { if (!cancel) { script.emit('error'); } };
        const player = env.player();
        try {
            const pending = env.adapter(player, { src: stream });
            await flush();
            if (cancel) {
                player._loadAbortController.abort();
                await assert.rejects(bounded(pending, 'cancelled script'), error => error.name === 'AbortError');
                assert.equal(player.media.loadCalls.length, 0);
                // The shared script loader outlives individual players until its own timeout.
                await env.advance(15000);
                console.log('PASS cancellation never triggers native fallback');
            } else {
                const result = await bounded(pending, 'failed script');
                player._cleanups.push(result.cleanup);
                assert.equal(result.controller.engine, 'native');
                assert.equal(result.controller.engineRequested, 'hls.js');
                assert.equal(result.controller.engineFallback, 'hls-load-failed');
                console.log('PASS script loading failure uses native fallback');
            }
        } finally { env.dispose(); }
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
