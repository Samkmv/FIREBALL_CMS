(function (window, document) {
    'use strict';

    if (!window.FirePlayer) {
        return;
    }

    const cacheBustedPoster = function (poster) {
        if (!poster) {
            return '';
        }
        try {
            const url = new URL(poster, window.location.href);
            url.searchParams.set('_fireplayer', String(Date.now()));
            return url.href;
        } catch (error) {
            return poster + (poster.includes('?') ? '&' : '?') + '_fireplayer=' + Date.now();
        }
    };

    window.FirePlayer.use({
        name: 'live',
        test: function (player, info) {
            return info.protocol === 'hls' || info.mode === 'live' || info.mode === 'event';
        },
        setup: function (player) {
            const media = player.media;
            const token = player._loadToken;
            let destroyed = false;
            let lastTime = media.currentTime || 0;
            let lastAdvanceAt = Date.now();
            let lastReconnectAt = 0;
            let hasAdvanced = false;
            let frameCallback = null;
            let frameSerial = 0;
            let lastFrameCount = null;
            let lastBufferEnd = 0;
            const health = player._health = { lastFrameAt: null, lastFrameCount: 0, lastCurrentTime: lastTime, lastBufferProgressAt: null };
            const observeFrame = function () {
                frameCallback = media.requestVideoFrameCallback(function () {
                    if (destroyed || player._destroyed || token !== player._loadToken) { return; }
                    frameSerial += 1;
                    observeFrame();
                });
            };
            if (typeof media.requestVideoFrameCallback === 'function') { observeFrame(); }
            const decodedFrames = function () {
                if (typeof media.requestVideoFrameCallback === 'function') { return frameSerial; }
                if (typeof media.getVideoPlaybackQuality === 'function') {
                    try {
                        const count = media.getVideoPlaybackQuality().totalVideoFrames;
                        if (Number.isFinite(count)) { return count; }
                    } catch (error) { /* Optional browser metric; try the next available source. */ }
                }
                return Number.isFinite(media.webkitDecodedFrameCount) ? media.webkitDecodedFrameCount : null;
            };
            const isLive = function () {
                return !destroyed && !player._destroyed && token === player._loadToken && player.media === media
                    && player.info && (player.info.mode === 'live' || player.info.mode === 'event');
            };
            let healthTimer = null;
            let posterTimer = null;

            const resetStartup = function () {
                hasAdvanced = false;
                lastTime = media.currentTime || 0;
                lastAdvanceAt = Date.now();
                lastFrameCount = decodedFrames();
                player._healthySince = null;
            };

            const healthCheck = function () {
                if (!isLive() || !player._playRequested || media.paused || player.media.ended || player.media.seeking || document.hidden || !player.options.reconnect
                    || (window.navigator && window.navigator.onLine === false)
                    || player._nativeHlsPreparing || player._state === 'awaiting-gesture'
                    || player._reconnectPromise || player.root.classList.contains('fireplayer--error')) {
                    resetStartup();
                    return;
                }
                const current = player.media.currentTime || 0;
                const frames = decodedFrames();
                const progressing = frames !== null ? frames > (lastFrameCount == null ? 0 : lastFrameCount) : Math.abs(current - lastTime) > 0.08;
                lastFrameCount = frames;
                lastTime = current;
                health.lastCurrentTime = current;
                health.lastFrameCount = frames;
                if (media.buffered && media.buffered.length) {
                    const end = media.buffered.end(media.buffered.length - 1);
                    if (end > lastBufferEnd) { health.lastBufferProgressAt = Date.now(); }
                    lastBufferEnd = end;
                }
                if (progressing) {
                    hasAdvanced = true;
                    lastAdvanceAt = Date.now();
                    health.lastFrameAt = lastAdvanceAt;
                    if (player._metrics && player._metrics.firstFrameMs == null) { player._metrics.firstFrameMs = lastAdvanceAt - player._metrics.startedAt; }
                    if (!media.paused && !media.error) { player._settleLoading(true); player._markHealthy(); }
                    return;
                }
                player._healthySince = null;
                const stalledFor = Date.now() - lastAdvanceAt;
                const cooldown = Date.now() - lastReconnectAt;
                const threshold = hasAdvanced
                    ? Number(player.options.stallTimeout || 7000)
                    : Number(player.options.startupTimeout || 30000);

                const reconnectBase = Math.max(500, Number(player.options.reconnectDelay || 2500));
                const reconnectAttempt = Math.max(0, Number(player._reconnectAttempts || 0));
                const recoveryCooldown = Math.min(
                    30000,
                    Math.max(
                        Number(player.options.stallTimeout || 7000),
                        reconnectBase * Math.pow(2, Math.min(reconnectAttempt, 3))
                    )
                );

                if (stalledFor >= threshold && cooldown >= recoveryCooldown) {
                    lastReconnectAt = Date.now();
                    lastAdvanceAt = Date.now();

                    player._emit('recovery', {
                        code: hasAdvanced ? 'PLAYBACK_STALL' : 'FIRST_FRAME_TIMEOUT',
                        reason: 'stall',
                        stage: 'live-health-check',
                        attempt: reconnectAttempt + 1
                    });

                    player.reconnect('stall').catch(function () {});
                }
            };

            const refreshPoster = function () {
                if (!isLive() || !(player.media instanceof HTMLVideoElement) || !player.options.poster || !player.options.posterCacheBust || !player.media.paused) {
                    return;
                }
                player.media.poster = cacheBustedPoster(player.options.poster);
            };

            const onVisibility = function () {
                // Timers can be throttled while hidden; give the resumed source
                // its startup budget before deciding that it has stalled.
                resetStartup();
            };

            player.root.classList.add('fireplayer--live-ready');
            healthTimer = window.setInterval(healthCheck, 2000);
            if (player.options.posterCacheBust) {
                posterTimer = window.setInterval(refreshPoster, Math.max(3000, Number(player.options.posterRefreshInterval || 5000)));
            }
            document.addEventListener('visibilitychange', onVisibility);
            media.addEventListener('emptied', resetStartup);
            media.addEventListener('loadstart', resetStartup);
            player.on('reconnect', resetStartup);

            return function () {
                destroyed = true;
                if (frameCallback !== null && typeof media.cancelVideoFrameCallback === 'function') { media.cancelVideoFrameCallback(frameCallback); }
                if (healthTimer) {
                    window.clearInterval(healthTimer);
                }
                if (posterTimer) {
                    window.clearInterval(posterTimer);
                }
                document.removeEventListener('visibilitychange', onVisibility);
                media.removeEventListener('emptied', resetStartup);
                media.removeEventListener('loadstart', resetStartup);
                player.off('reconnect', resetStartup);
                player.root.classList.remove('fireplayer--live-ready');
            };
        }
    });
})(window, document);
