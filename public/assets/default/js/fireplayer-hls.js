(function (window, document) {
    'use strict';

    if (!window.FirePlayer) { return; }

    let hlsLoaderPromise = null;
    const config = function () {
        return window.firePlayerConfig && typeof window.firePlayerConfig === 'object' ? window.firePlayerConfig : {};
    };
    const boundedNumber = function (value, fallback, min, max) {
        const number = Number(value);
        return Number.isFinite(number) ? Math.max(min, Math.min(max, number)) : fallback;
    };
    const abortError = function () {
        const error = new Error('Media load was cancelled.');
        error.name = 'AbortError';
        return error;
    };
    const waitWithSignal = function (promise, signal) {
        if (!signal) { return promise; }
        return new Promise(function (resolve, reject) {
            const onAbort = function () { reject(abortError()); };
            if (signal.aborted) { onAbort(); return; }
            signal.addEventListener('abort', onAbort, { once: true });
            promise.then(resolve, reject).finally(function () {
                signal.removeEventListener('abort', onAbort);
            });
        });
    };
    const hlsScriptUrl = function () {
        if (config().hlsScriptUrl) { return config().hlsScriptUrl; }
        const ownScript = Array.from(document.scripts).find(function (script) {
            return /\/fireplayer-hls\.js(?:\?|$)/.test(script.src || '');
        });
        return ownScript ? ownScript.src.replace(/\/js\/fireplayer-hls\.js(?:\?.*)?$/, '/vendor/hls.js/hls.min.js') : '';
    };
    const loadHls = function () {
        if (typeof window.Hls === 'function') { return Promise.resolve(window.Hls); }
        if (hlsLoaderPromise) { return hlsLoaderPromise; }
        hlsLoaderPromise = new Promise(function (resolve, reject) {
            const url = hlsScriptUrl();
            if (!url) { reject(new Error('Hls.js URL is not configured.')); return; }
            const existing = document.querySelector('script[data-fireplayer-hls-loader]');
            const script = existing || document.createElement('script');
            let timer = null;
            const finish = function (error) {
                window.clearTimeout(timer);
                script.removeEventListener('load', onLoad);
                script.removeEventListener('error', onError);
                if (error) {
                    script.remove();
                    reject(error);
                } else { resolve(window.Hls); }
            };
            const onLoad = function () {
                finish(typeof window.Hls === 'function' ? null : new Error('Hls.js loaded without a global Hls constructor.'));
            };
            const onError = function () { finish(new Error('Unable to load Hls.js.')); };
            script.addEventListener('load', onLoad, { once: true });
            script.addEventListener('error', onError, { once: true });
            timer = window.setTimeout(function () { finish(new Error('Hls.js loading timed out.')); }, 15000);
            if (!existing) {
                script.src = url;
                script.async = true;
                script.dataset.fireplayerHlsLoader = 'true';
                document.head.appendChild(script);
            }
        }).catch(function (error) {
            // A failed script request must not poison Retry for every player.
            hlsLoaderPromise = null;
            throw error;
        });
        return hlsLoaderPromise;
    };
    const canPlayNatively = function (media) {
        return typeof media.canPlayType === 'function' && ['application/vnd.apple.mpegurl', 'application/x-mpegURL'].some(function (mime) {
            const answer = media.canPlayType(mime);
            return answer === 'probably' || answer === 'maybe';
        });
    };
    const isAppleBrowser = function () {
        const navigator = window.navigator || {};
        const agent = navigator.userAgent || '';
        return /iPad|iPhone|iPod/.test(agent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
            || (/Safari/.test(agent) && !/Chrome|Chromium|CriOS|Edg|OPR|FxiOS|YaBrowser/.test(agent));
    };
    const inferStreamId = function (source) {
        const match = String(source || '').match(/\/stream-([^/]+)\/index\.m3u8(?:[?#].*)?$/i);
        return match ? match[1] : '';
    };
    const wakeStream = async function (source, options, signal) {
        const streamId = options.streamId || inferStreamId(source);
        if (!streamId) { return; }
        if (typeof window.fetch !== 'function') {
            throw new Error('Camera readiness checks require Fetch support.');
        }
        const frontend = window.hlsStreamConfig && typeof window.hlsStreamConfig === 'object' ? window.hlsStreamConfig : {};
        // The final backend pass may probe manifest + segment with HEAD/GET fallbacks.
        const timeout = boundedNumber(frontend.readyTimeoutMs, 30000, 1000, 120000)
            + boundedNumber(frontend.readyIntervalMs, 1500, 500, 10000)
            + (boundedNumber(frontend.httpTimeoutMs, 5000, 1000, 15000) * 4) + 2000;
        const controller = typeof AbortController === 'function' ? new AbortController() : null;
        let timedOut = false;
        const onAbort = function () { if (controller) { controller.abort(); } };
        if (signal && signal.aborted) { throw abortError(); }
        if (signal) { signal.addEventListener('abort', onAbort, { once: true }); }
        let timer = null;
        try {
            const request = (async function () {
                const response = await window.fetch((typeof window.baseUrl === 'string' ? window.baseUrl : (typeof baseUrl === 'string' ? baseUrl : '')) + '/api/streams/wake', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ stream_id: streamId, hls_url: source }),
                    signal: controller ? controller.signal : signal
                });
                const payload = await response.json().catch(function () { return {}; });
                if (!response.ok || payload.success === false || payload.ready !== true) {
                    throw new Error('Camera stream is not ready (HTTP ' + response.status + ').');
                }
            })();
            const deadline = new Promise(function (_, reject) {
                timer = window.setTimeout(function () {
                    timedOut = true;
                    onAbort();
                    reject(new Error('Camera readiness check timed out.'));
                }, timeout);
            });
            await waitWithSignal(Promise.race([request, deadline]), signal);
        } catch (error) {
            if (timedOut) { throw new Error('Camera readiness check timed out.'); }
            throw error;
        } finally {
            window.clearTimeout(timer);
            if (signal) { signal.removeEventListener('abort', onAbort); }
        }
    };

    window.FirePlayer.registerAdapter('hls', async function (player, info) {
        const media = player.media;
        const source = info.src;
        const token = player._loadToken;
        const loadSignal = player._loadAbortController && player._loadAbortController.signal;
        const lifetime = typeof AbortController === 'function' ? new AbortController() : null;
        const signal = lifetime ? lifetime.signal : loadSignal;
        const options = Object.assign({}, player.options);
        let destroyed = false;
        let retryTimer = null;
        let reconnectPromise = null;
        let hls = null;
        let mediaRecoveries = 0;
        let lastHealthyTime = media.currentTime || 0;
        const active = function () {
            return !destroyed && !player._destroyed && player._loadToken === token
                && player.media === media && !(signal && signal.aborted);
        };
        const assertActive = function () { if (!active()) { throw abortError(); } };
        const clearRetry = function () {
            window.clearTimeout(retryTimer);
            retryTimer = null;
        };
        const onProgress = function () {
            const current = media.currentTime || 0;
            if (active() && !media.paused && !media.seeking && Math.abs(current - lastHealthyTime) >= 2) {
                mediaRecoveries = 0;
                lastHealthyTime = current;
            }
        };
        const cleanup = function () {
            if (destroyed) { return; }
            destroyed = true;
            clearRetry();
            if (loadSignal) { loadSignal.removeEventListener('abort', cleanup); }
            if (lifetime) { lifetime.abort(); }
            media.removeEventListener('timeupdate', onProgress);
            if (hls) { hls.destroy(); hls = null; }
        };
        media.addEventListener('timeupdate', onProgress);
        if (loadSignal) {
            loadSignal.addEventListener('abort', cleanup, { once: true });
            if (loadSignal.aborted) { cleanup(); }
        }
        const wake = async function () {
            assertActive();
            try {
                await wakeStream(source, options, signal);
                assertActive();
            } catch (error) {
                if (active() && error.name !== 'AbortError') { player._emit('wakeerror', { error: error }); }
                throw error;
            }
        };
        const liveSyncPosition = function () {
            if (hls && Number.isFinite(hls.liveSyncPosition)) { return hls.liveSyncPosition; }
            const ranges = media.seekable;
            if (!ranges || !ranges.length) { return null; }
            const last = ranges.length - 1;
            return Math.max(ranges.start(last), ranges.end(last) - 3);
        };

        try {
            await wake();
            let Hls = null;
            let useNative = options.forceHlsJs !== true && isAppleBrowser() && canPlayNatively(media);
            if (!useNative) {
                try {
                    Hls = await waitWithSignal(loadHls(), signal);
                } catch (error) {
                    assertActive();
                    if (options.forceHlsJs === true || !canPlayNatively(media)) { throw error; }
                    useNative = true;
                }
                assertActive();
                if (!useNative && typeof Hls.isSupported === 'function' && !Hls.isSupported()) {
                    if (options.forceHlsJs === true || !canPlayNatively(media)) {
                        cleanup();
                        return { handled: false };
                    }
                    useNative = true;
                }
            }
            assertActive();

            const reportFatal = function (error) {
                if (active()) { player._showError(window.FirePlayer.translate('failed'), error); }
            };
            const scheduleReconnect = function (error) {
                if (!active() || reconnectPromise || player._reconnectPromise || retryTimer !== null) { return; }
                if (!player.options.reconnect) { reportFatal(error); return; }
                player.setStatus(window.FirePlayer.translate('reconnecting'), 'warning');
                retryTimer = window.setTimeout(function () {
                    retryTimer = null;
                    if (!active()) { return; }
                    if (!player.options.reconnect) { reportFatal(error); return; }
                    player.reconnect('network').catch(reportFatal);
                }, boundedNumber(player.options.reconnectDelay, 2500, 500, 30000));
            };
            const attachHls = function () {
                const instance = new Hls(Object.assign({
                    enableWorker: true,
                    lowLatencyMode: true,
                    liveSyncDurationCount: 3,
                    liveMaxLatencyDurationCount: 8,
                    backBufferLength: 45,
                    maxBufferLength: 30,
                    manifestLoadingMaxRetry: 4,
                    levelLoadingMaxRetry: 4,
                    fragLoadingMaxRetry: 6
                }, config().hls || {}, options.hls || {}));
                hls = instance;
                let sourceLoaded = false;
                const current = function () { return active() && hls === instance; };
                instance.on(Hls.Events.MEDIA_ATTACHED, function () {
                    // Decoder recovery reattaches this same instance. Its manifest and
                    // resume position must survive rather than starting the source over.
                    if (current() && !sourceLoaded) {
                        sourceLoaded = true;
                        instance.loadSource(source);
                    }
                });
                instance.on(Hls.Events.MANIFEST_PARSED, function (event, data) {
                    if (!current()) { return; }
                    const levels = data && Array.isArray(data.levels) ? data.levels : [];
                    player._emit('manifest', { levels: levels.length });
                });
                instance.on(Hls.Events.LEVEL_LOADED, function (event, data) {
                    if (current() && data && data.details) {
                        player.setMode(data.details.live ? (data.details.type === 'EVENT' ? 'event' : 'live') : 'vod');
                    }
                });
                instance.on(Hls.Events.ERROR, function (event, data) {
                    if (!current() || !data || !data.fatal) { return; }
                    const error = new Error(data.details || 'Fatal HLS error');
                    if (!player.options.reconnect) { reportFatal(error); return; }
                    if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                        scheduleReconnect(error);
                    } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                        if (reconnectPromise || player._reconnectPromise || retryTimer !== null) { return; }
                        mediaRecoveries += 1;
                        if (mediaRecoveries > 2) { reportFatal(error); return; }
                        try {
                            if (mediaRecoveries === 2 && typeof instance.swapAudioCodec === 'function') { instance.swapAudioCodec(); }
                            player._recoverMedia(function () { instance.recoverMediaError(); });
                        } catch (recoveryError) { reportFatal(recoveryError); }
                    } else { reportFatal(error); }
                });
                instance.attachMedia(media);
            };
            const prepare = function () {
                assertActive();
                if (useNative) {
                    media.src = source;
                    media.load();
                } else {
                    if (hls) { hls.destroy(); hls = null; }
                    attachHls();
                }
            };
            const reconnect = function () {
                if (reconnectPromise) { return reconnectPromise; }
                clearRetry();
                reconnectPromise = (async function () {
                    assertActive();
                    if (hls) { hls.stopLoad(); }
                    await wake();
                    assertActive();
                    mediaRecoveries = 0;
                    lastHealthyTime = media.currentTime || 0;
                    prepare();
                })().finally(function () { reconnectPromise = null; });
                return reconnectPromise;
            };
            prepare();
            return {
                handled: true,
                controller: {
                    engine: useNative ? 'native' : 'hls.js',
                    get hls() { return hls; },
                    get liveSyncPosition() { return liveSyncPosition(); },
                    reconnect: reconnect
                },
                cleanup: cleanup
            };
        } catch (error) {
            cleanup();
            throw error;
        }
    });
})(window, document);
