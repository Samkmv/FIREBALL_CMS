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
            };

            const healthCheck = function () {
                if (!isLive() || !player._playRequested || player.media.ended || player.media.seeking || document.hidden || !player.options.reconnect
                    || player._reconnectPromise || player.root.classList.contains('fireplayer--error')) {
                    lastTime = player.media.currentTime || 0;
                    lastAdvanceAt = Date.now();
                    return;
                }
                const current = player.media.currentTime || 0;
                if (Math.abs(current - lastTime) > 0.08) {
                    hasAdvanced = true;
                    lastTime = current;
                    lastAdvanceAt = Date.now();
                    if (!media.paused && !media.error) { player._settleLoading(true); }
                    return;
                }
                const stalledFor = Date.now() - lastAdvanceAt;
                const cooldown = Date.now() - lastReconnectAt;
                const threshold = hasAdvanced ? Number(player.options.stallTimeout || 7000) : Number(player.options.startupTimeout || 30000);
                if (stalledFor >= threshold && cooldown >= Number(player.options.stallTimeout || 7000)) {
                    lastReconnectAt = Date.now();
                    lastAdvanceAt = Date.now();
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
                if (isLive() && !document.hidden && player._playRequested && !player.media.paused) {
                    player.goLive();
                }
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
