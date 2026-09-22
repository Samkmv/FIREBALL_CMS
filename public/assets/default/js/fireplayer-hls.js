(function (window, document) {
    'use strict';

    if (!window.FirePlayer) { return; }

    let hlsLoaderPromise = null;
    const wakeRequests = new Map();
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
    const nativeHlsError = function (code, message, cause) {
        const error = new Error(message || code || 'Native HLS error');
        error.name = 'NativeHlsError';
        error.code = code || 'NATIVE_HLS_ERROR';
        if (cause) { error.cause = cause; }
        return error;
    };
    const delayWithSignal = function (milliseconds, signal) {
        return new Promise(function (resolve, reject) {
            let timer = null;
            const finish = function (error) {
                if (timer !== null) { window.clearTimeout(timer); timer = null; }
                if (signal) { signal.removeEventListener('abort', onAbort); }
                if (error) { reject(error); } else { resolve(); }
            };
            const onAbort = function () { finish(abortError()); };
            if (signal && signal.aborted) { onAbort(); return; }
            if (signal) { signal.addEventListener('abort', onAbort, { once: true }); }
            timer = window.setTimeout(function () { finish(); }, Math.max(0, Number(milliseconds) || 0));
        });
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
        if (signal && signal.aborted) { throw abortError(); }

        let entry = wakeRequests.get(streamId);
        if (!entry) {
            const frontend = window.hlsStreamConfig && typeof window.hlsStreamConfig === 'object' ? window.hlsStreamConfig : {};
            // The backend may probe both the manifest and its first segment.
            const timeout = boundedNumber(frontend.readyTimeoutMs, 30000, 1000, 120000)
                + boundedNumber(frontend.readyIntervalMs, 1500, 500, 10000)
                + (boundedNumber(frontend.httpTimeoutMs, 5000, 1000, 15000) * 4) + 2000;
            const controller = typeof AbortController === 'function' ? new AbortController() : null;
            entry = { controller: controller, consumers: 0, settled: false, promise: null };
            const currentEntry = entry;
            entry.promise = (async function () {
                let timer = null;
                let timedOut = false;
                try {
                    const request = (async function () {
                        const response = await window.fetch((typeof window.baseUrl === 'string' ? window.baseUrl : (typeof baseUrl === 'string' ? baseUrl : '')) + '/api/streams/wake', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                            body: JSON.stringify({ stream_id: streamId, hls_url: source }),
                            signal: controller ? controller.signal : undefined
                        });
                        const payload = await response.json().catch(function () { return {}; });
                        if (!response.ok || payload.success === false || payload.ready !== true) {
                            throw new Error('Camera stream is not ready (HTTP ' + response.status + ').');
                        }
                    })();
                    const deadline = new Promise(function (_, reject) {
                        timer = window.setTimeout(function () {
                            timedOut = true;
                            if (controller) { controller.abort(); }
                            reject(new Error('Camera readiness check timed out.'));
                        }, timeout);
                    });
                    await Promise.race([request, deadline]);
                } catch (error) {
                    if (timedOut) { throw new Error('Camera readiness check timed out.'); }
                    throw error;
                } finally {
                    window.clearTimeout(timer);
                }
            })().finally(function () {
                currentEntry.settled = true;
                if (wakeRequests.get(streamId) === currentEntry) {
                    wakeRequests.delete(streamId);
                }
            });
            // A player may disappear while the shared wake is still unwinding.
            entry.promise.catch(function () {});
            wakeRequests.set(streamId, entry);
        }

        entry.consumers += 1;
        try {
            await waitWithSignal(entry.promise, signal);
        } finally {
            entry.consumers = Math.max(0, entry.consumers - 1);
            if (!entry.settled && entry.consumers === 0 && entry.controller) {
                if (wakeRequests.get(streamId) === entry) { wakeRequests.delete(streamId); }
                entry.controller.abort();
            }
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
        let networkRecoveries = 0;
        let softReconnects = 0;
        let lastWakeAt = 0;
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
                networkRecoveries = 0;
                softReconnects = 0;
                lastHealthyTime = current;
                player._emit('recovered', { protocol: 'hls' });
            }
        };
        const cleanup = function () {
            if (destroyed) { return; }
            destroyed = true;
            clearRetry();
            if (player._loadToken === token) { player._nativeHlsPreparing = false; }
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
        const wake = async function (force) {
            assertActive();
            const streamId = options.streamId || inferStreamId(source);
            const wakeCooldown = 15000;

            if (streamId && !force && lastWakeAt && Date.now() - lastWakeAt < wakeCooldown) {
                player._emit('recovery', { reason: 'wake-cooldown', stage: 'wake-skipped' });
                return false;
            }

            try {
                if (streamId) { player.setStatus(window.FirePlayer.translate('waking'), 'info'); }
                await wakeStream(source, options, signal);
                assertActive();
                if (streamId) {
                    lastWakeAt = Date.now();
                    player.setStatus(window.FirePlayer.translate('connecting'), 'info');
                }
                return Boolean(streamId);
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
            await wake(true);
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
            const scheduleReconnect = function (error, instance) {
                if (!active() || reconnectPromise || player._reconnectPromise || retryTimer !== null) { return; }
                if (!player.options.reconnect) { reportFatal(error); return; }
                if (window.navigator && window.navigator.onLine === false) {
                    player.root.classList.add('fireplayer--offline');
                    player.setStatus(window.FirePlayer.translate('offline'), 'warning');
                    return;
                }

                const localRecovery = Boolean(
                    instance && hls === instance && networkRecoveries < 1
                    && typeof instance.startLoad === 'function'
                );
                if (localRecovery) { networkRecoveries += 1; }

                player.setStatus(window.FirePlayer.translate('reconnecting'), 'warning');
                const baseDelay = boundedNumber(player.options.reconnectDelay, 2500, 500, 30000);
                const attempt = localRecovery ? 0 : Math.max(0, Number(player._reconnectAttempts || 0));
                const delayBase = localRecovery ? Math.min(baseDelay, 1500) : baseDelay;
                const delay = Math.min(15000, delayBase * Math.pow(2, Math.min(attempt, 3)));
                const jitter = Math.round(delay * 0.15 * Math.random());

                retryTimer = window.setTimeout(function () {
                    retryTimer = null;
                    if (!active()) { return; }
                    if (!player.options.reconnect) { reportFatal(error); return; }
                    if (window.navigator && window.navigator.onLine === false) {
                        player.root.classList.add('fireplayer--offline');
                        player.setStatus(window.FirePlayer.translate('offline'), 'warning');
                        return;
                    }

                    if (localRecovery && hls === instance) {
                        try {
                            player._emit('recovery', {
                                reason: 'network',
                                stage: 'hls-start-load',
                                attempt: networkRecoveries
                            });
                            instance.startLoad(-1);
                            return;
                        } catch (recoveryError) {
                            player.reconnect('network').catch(reportFatal);
                            return;
                        }
                    }

                    player.reconnect('network').catch(reportFatal);
                }, delay + jitter);
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
                    const details = String(data.details || '');

                    if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                        error.code = /frag|segment/i.test(details)
                            ? 'HLS_SEGMENT_ERROR'
                            : (/manifest|level/i.test(details) ? 'HLS_MANIFEST_ERROR' : 'HLS_NETWORK_ERROR');
                    } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                        error.code = 'HLS_MEDIA_ERROR';
                    } else {
                        error.code = 'HLS_FATAL_ERROR';
                    }

                    player._emit('hlserror', {
                        code: error.code,
                        details: details,
                        fatal: true
                    });

                    if (!player.options.reconnect) { reportFatal(error); return; }

                    if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                        scheduleReconnect(error, instance);
                    } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                        if (reconnectPromise || player._reconnectPromise || retryTimer !== null) { return; }

                        mediaRecoveries += 1;

                        if (mediaRecoveries > 2) {
                            player.reconnect('media').catch(reportFatal);
                            return;
                        }

                        try {
                            if (mediaRecoveries === 2 && typeof instance.swapAudioCodec === 'function') {
                                instance.swapAudioCodec();
                            }

                            player._emit('recovery', {
                                reason: 'media',
                                stage: 'recover-media-error',
                                attempt: mediaRecoveries
                            });

                            player._recoverMedia(function () {
                                instance.recoverMediaError();
                            });
                        } catch (recoveryError) {
                            player.reconnect('media').catch(reportFatal);
                        }
                    } else {
                        reportFatal(error);
                    }
                });
                instance.attachMedia(media);
            };
            const waitForNativeReady = function () {
                const timeout = Math.min(15000, boundedNumber(options.startupTimeout, 30000, 3000, 120000));

                return new Promise(function (resolve, reject) {
                    let settled = false;
                    let timer = null;
                    const events = ['loadedmetadata', 'loadeddata', 'canplay', 'canplaythrough'];

                    const cleanupWait = function () {
                        if (timer !== null) { window.clearTimeout(timer); timer = null; }
                        events.forEach(function (eventName) { media.removeEventListener(eventName, onReady); });
                        media.removeEventListener('error', onError);
                        if (signal) { signal.removeEventListener('abort', onAbort); }
                    };
                    const finish = function (error) {
                        if (settled) { return; }
                        settled = true;
                        cleanupWait();
                        if (error) { reject(error); } else { resolve(); }
                    };
                    const onAbort = function () { finish(abortError()); };
                    const onError = function () {
                        const mediaError = media.error;
                        const message = mediaError && mediaError.message
                            ? mediaError.message
                            : 'Safari could not prepare the native HLS source.';
                        finish(nativeHlsError('NATIVE_HLS_MEDIA_ERROR', message, mediaError));
                    };
                    const onReady = function () {
                        if (!active()) { finish(abortError()); return; }
                        if (!media.error && media.readyState >= 1) { finish(); }
                    };

                    if (signal && signal.aborted) { onAbort(); return; }
                    if (signal) { signal.addEventListener('abort', onAbort, { once: true }); }
                    events.forEach(function (eventName) { media.addEventListener(eventName, onReady); });
                    media.addEventListener('error', onError, { once: true });
                    timer = window.setTimeout(function () {
                        finish(nativeHlsError(
                            'NATIVE_HLS_TIMEOUT',
                            'Safari did not recognize the native HLS source before the timeout.'
                        ));
                    }, timeout);

                    if (media.error) { onError(); return; }
                    onReady();
                });
            };

            const prepareNative = async function (reason) {
                // Initial attachment must not wait for metadata/canplay: Safari can need
                // play() to start loading them. Keep the reset/readiness path for recovery.
                if (reason === 'initial' && !media.error) {
                    assertActive();
                    const sourceChanged = media.getAttribute('src') !== source;
                    if (sourceChanged) { media.setAttribute('src', source); }
                    if (sourceChanged || !media.currentSrc) { media.load(); }
                    player._emit('recovery', { reason: 'initial', stage: 'native-attach', attempt: 1 });
                    return;
                }

                const maxAttempts = 2;
                player._nativeHlsPreparing = true;

                try {
                    for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
                        assertActive();
                        if (attempt > 1) {
                            await delayWithSignal(500 * attempt, signal);
                            assertActive();
                        }

                        try {
                            media.removeAttribute('src');
                            try { media.load(); } catch (error) {}
                            media.src = source;
                            media.load();

                            player._emit('recovery', {
                                reason: reason || 'native-prepare',
                                stage: attempt === 1 ? 'native-attach' : 'native-reattach',
                                attempt: attempt
                            });

                            await waitForNativeReady();
                            assertActive();
                            return;
                        } catch (error) {
                            if (error && error.name === 'AbortError') { throw error; }
                            player._emit('nativehlserror', {
                                code: error && error.code ? error.code : 'NATIVE_HLS_ERROR',
                                attempt: attempt
                            });
                            if (attempt >= maxAttempts) {
                                try { media.removeAttribute('src'); media.load(); } catch (cleanupError) {}
                                throw error;
                            }
                        }
                    }
                } finally {
                    if (player._loadToken === token && !player._destroyed) {
                        player._nativeHlsPreparing = false;
                    }
                }
            };

            const prepare = async function (reason) {
                assertActive();
                if (useNative) {
                    await prepareNative(reason);
                } else {
                    if (hls) { hls.destroy(); hls = null; }
                    attachHls();
                }
            };
            const reconnect = function (reason) {
                if (reconnectPromise) { return reconnectPromise; }
                clearRetry();

                reconnectPromise = (async function () {
                    assertActive();

                    const reconnectReason = reason || 'unknown';
                    const streamId = options.streamId || inferStreamId(source);
                    const managedStream = Boolean(streamId);

                    if (hls) { hls.stopLoad(); }

                    let shouldWake = reconnectReason === 'manual';

                    if (!shouldWake && managedStream) {
                        softReconnects += 1;
                        shouldWake = softReconnects >= 2;
                    }

                    let didWake = false;
                    if (shouldWake) {
                        didWake = await wake(reconnectReason === 'manual');
                        assertActive();

                        if (managedStream && didWake) {
                            softReconnects = 0;
                        }
                    }

                    mediaRecoveries = 0;
                    networkRecoveries = 0;
                    lastHealthyTime = media.currentTime || 0;

                    player._emit('recovery', {
                        reason: reconnectReason,
                        stage: didWake ? 'wake-rebuild' : 'soft-rebuild'
                    });

                    await prepare(reconnectReason);
                })().finally(function () {
                    reconnectPromise = null;
                });

                return reconnectPromise;
            };
            await prepare('initial');
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
