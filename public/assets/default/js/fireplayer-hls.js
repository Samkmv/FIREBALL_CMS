(function (window, document) {
    'use strict';

    if (!window.FirePlayer) {
        return;
    }

    let hlsLoaderPromise = null;

    const config = function () {
        return window.firePlayerConfig && typeof window.firePlayerConfig === 'object' ? window.firePlayerConfig : {};
    };

    const hlsScriptUrl = function () {
        if (config().hlsScriptUrl) {
            return config().hlsScriptUrl;
        }
        const ownScript = Array.from(document.scripts).find(function (script) {
            return /\/fireplayer-hls\.js(?:\?|$)/.test(script.src || '');
        });
        return ownScript ? ownScript.src.replace(/\/js\/fireplayer-hls\.js(?:\?.*)?$/, '/vendor/hls.js/hls.min.js') : '';
    };

    const loadHls = function () {
        if (typeof window.Hls === 'function') {
            return Promise.resolve(window.Hls);
        }
        if (hlsLoaderPromise) {
            return hlsLoaderPromise;
        }
        hlsLoaderPromise = new Promise(function (resolve, reject) {
            const url = hlsScriptUrl();
            if (!url) {
                reject(new Error('Hls.js URL is not configured.'));
                return;
            }
            const existing = document.querySelector('script[data-fireplayer-hls-loader]');
            const script = existing || document.createElement('script');
            const done = function () {
                if (typeof window.Hls === 'function') {
                    resolve(window.Hls);
                } else {
                    reject(new Error('Hls.js loaded without a global Hls constructor.'));
                }
            };
            script.addEventListener('load', done, { once: true });
            script.addEventListener('error', function () { reject(new Error('Unable to load Hls.js.')); }, { once: true });
            if (!existing) {
                script.src = url;
                script.async = true;
                script.dataset.fireplayerHlsLoader = 'true';
                document.head.appendChild(script);
            }
        });
        return hlsLoaderPromise;
    };

    const canPlayNatively = function (media) {
        if (!(media instanceof HTMLVideoElement)) {
            return false;
        }
        return ['application/vnd.apple.mpegurl', 'application/x-mpegURL'].some(function (mime) {
            const answer = media.canPlayType(mime);
            return answer === 'probably' || answer === 'maybe';
        });
    };

    const inferStreamId = function (source) {
        const match = String(source || '').match(/\/stream-([^/]+)\/index\.m3u8(?:[?#].*)?$/i);
        return match ? match[1] : '';
    };

    const wakeStream = async function (player) {
        if (!player.options.lazyStart || typeof window.fetch !== 'function') {
            return true;
        }
        const streamId = player.options.streamId || inferStreamId(player.info.src);
        if (!streamId) {
            return true;
        }
        const frontend = window.hlsStreamConfig && typeof window.hlsStreamConfig === 'object' ? window.hlsStreamConfig : {};
        const timeout = Math.max(3000, Number(frontend.readyTimeoutMs || 30000) + Number(frontend.httpTimeoutMs || 5000));
        const controller = typeof AbortController === 'function' ? new AbortController() : null;
        const timer = controller ? window.setTimeout(function () { controller.abort(); }, timeout) : null;
        try {
            const response = await window.fetch((typeof window.baseUrl === 'string' ? window.baseUrl : (typeof baseUrl === 'string' ? baseUrl : '')) + '/api/streams/wake', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: JSON.stringify({ stream_id: streamId, hls_url: player.info.src }),
                signal: controller ? controller.signal : undefined
            });
            const payload = await response.json().catch(function () { return {}; });
            return Boolean(response.ok && payload.success !== false && payload.ready === true);
        } finally {
            if (timer) {
                window.clearTimeout(timer);
            }
        }
    };

    window.FirePlayer.registerAdapter('hls', async function (player, info) {
        const media = player.media;
        const source = info.src;
        const useNative = canPlayNatively(media) && player.options.forceHlsJs !== true;
        let destroyed = false;
        let retryTimer = null;
        let hls = null;

        const wake = async function () {
            try {
                return await wakeStream(player);
            } catch (error) {
                player._emit('wakeerror', { error: error });
                return false;
            }
        };

        await wake();

        if (useNative) {
            media.src = source;
            media.load();
            const reconnectNative = async function () {
                if (destroyed) {
                    return;
                }
                await wake();
                const wasPlaying = !media.paused || player.options.autoplay;
                media.src = source;
                media.load();
                if (wasPlaying) {
                    await media.play().catch(function () {});
                }
            };
            return {
                handled: true,
                controller: { engine: 'native', reconnect: reconnectNative },
                cleanup: function () {
                    destroyed = true;
                    if (retryTimer) {
                        window.clearTimeout(retryTimer);
                    }
                }
            };
        }

        const Hls = await loadHls();
        if (typeof Hls.isSupported === 'function' && !Hls.isSupported()) {
            return { handled: false };
        }

        hls = new Hls(Object.assign({
            enableWorker: true,
            lowLatencyMode: true,
            liveSyncDurationCount: 3,
            liveMaxLatencyDurationCount: 8,
            backBufferLength: 45,
            maxBufferLength: 30,
            manifestLoadingMaxRetry: 4,
            levelLoadingMaxRetry: 4,
            fragLoadingMaxRetry: 6
        }, config().hls || {}, player.options.hls || {}));

        const reconnectHls = async function () {
            if (destroyed || !hls) {
                return;
            }
            await wake();
            hls.stopLoad();
            hls.loadSource(source);
            hls.startLoad(-1);
        };

        hls.on(Hls.Events.MEDIA_ATTACHED, function () {
            if (!destroyed) {
                hls.loadSource(source);
            }
        });
        hls.on(Hls.Events.MANIFEST_PARSED, function (event, data) {
            const levels = data && Array.isArray(data.levels) ? data.levels : [];
            player._emit('manifest', { levels: levels.length });
            if (player.options.autoplay) {
                media.play().catch(function () {});
            }
        });
        hls.on(Hls.Events.LEVEL_LOADED, function (event, data) {
            if (data && data.details) {
                player.setMode(data.details.live ? (data.details.type === 'EVENT' ? 'event' : 'live') : 'vod');
            }
        });
        hls.on(Hls.Events.ERROR, function (event, data) {
            if (!data || !data.fatal || destroyed) {
                return;
            }
            if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                player.setStatus(window.FirePlayer.translate('reconnecting'), 'warning');
                if (retryTimer) {
                    window.clearTimeout(retryTimer);
                }
                retryTimer = window.setTimeout(function () {
                    reconnectHls().catch(function (error) { player._showError(window.FirePlayer.translate('failed'), error); });
                }, Math.max(500, Number(player.options.reconnectDelay || 2500)));
            } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                hls.recoverMediaError();
            } else {
                player._showError(window.FirePlayer.translate('failed'), new Error(data.details || 'Fatal HLS error'));
            }
        });
        hls.attachMedia(media);

        return {
            handled: true,
            controller: { engine: 'hls.js', hls: hls, reconnect: reconnectHls },
            cleanup: function () {
                destroyed = true;
                if (retryTimer) {
                    window.clearTimeout(retryTimer);
                }
                if (hls) {
                    hls.destroy();
                    hls = null;
                }
            }
        };
    });
})(window, document);
