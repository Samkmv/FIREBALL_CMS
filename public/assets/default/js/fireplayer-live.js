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
            return info.mode === 'live' || info.mode === 'event';
        },
        setup: function (player) {
            let lastTime = player.media.currentTime || 0;
            let lastAdvanceAt = Date.now();
            let lastReconnectAt = 0;
            let healthTimer = null;
            let posterTimer = null;

            const healthCheck = function () {
                if (player.media.paused || player.media.ended || document.hidden || !player.options.reconnect) {
                    lastTime = player.media.currentTime || 0;
                    lastAdvanceAt = Date.now();
                    return;
                }
                const current = player.media.currentTime || 0;
                if (Math.abs(current - lastTime) > 0.08) {
                    lastTime = current;
                    lastAdvanceAt = Date.now();
                    return;
                }
                const stalledFor = Date.now() - lastAdvanceAt;
                const cooldown = Date.now() - lastReconnectAt;
                if (stalledFor >= Number(player.options.stallTimeout || 7000) && cooldown >= Number(player.options.stallTimeout || 7000)) {
                    lastReconnectAt = Date.now();
                    lastAdvanceAt = Date.now();
                    player.reconnect('stall');
                }
            };

            const refreshPoster = function () {
                if (!(player.media instanceof HTMLVideoElement) || !player.options.poster || !player.options.posterCacheBust || !player.media.paused) {
                    return;
                }
                player.media.poster = cacheBustedPoster(player.options.poster);
            };

            const onVisibility = function () {
                if (!document.hidden && !player.media.paused) {
                    player.goLive();
                    healthCheck();
                }
            };

            player.root.classList.add('fireplayer--live-ready');
            healthTimer = window.setInterval(healthCheck, 2000);
            if (player.options.posterCacheBust) {
                posterTimer = window.setInterval(refreshPoster, Math.max(3000, Number(player.options.posterRefreshInterval || 5000)));
            }
            document.addEventListener('visibilitychange', onVisibility);

            return function () {
                if (healthTimer) {
                    window.clearInterval(healthTimer);
                }
                if (posterTimer) {
                    window.clearInterval(posterTimer);
                }
                document.removeEventListener('visibilitychange', onVisibility);
                player.root.classList.remove('fireplayer--live-ready');
            };
        }
    });
})(window, document);
