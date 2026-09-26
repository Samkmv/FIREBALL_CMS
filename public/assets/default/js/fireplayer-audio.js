(function (window, document) {
    'use strict';

    if (!window.FirePlayer) {
        return;
    }
    let sessionOwner = null;

    window.FirePlayer.use({
        name: 'audio',
        test: function (player, info) {
            return info.media === 'audio';
        },
        setup: function (player) {
            player.root.classList.add('fireplayer--audio-ready');
            player.elements.pip.hidden = true;
            player.elements.fullscreen.hidden = true;

            const media = player.media;
            const actions = ['play', 'pause', 'seekbackward', 'seekforward', 'seekto'];
            const seek = function (position) {
                if (Number.isFinite(position) && Number.isFinite(media.duration)) {
                    media.currentTime = Math.max(0, Math.min(media.duration, position));
                }
            };
            const activateSession = function () {
                if (!('mediaSession' in window.navigator) || player._destroyed || player.media !== media) { return; }
                try {
                    sessionOwner = player;
                    window.navigator.mediaSession.metadata = new window.MediaMetadata({
                        title: player.options.title || window.FirePlayer.translate('audio'),
                        artist: player.options.artist || '',
                        album: player.options.album || '',
                        artwork: Array.isArray(player.options.artwork) ? player.options.artwork : (player.options.poster ? [{ src: player.options.poster }] : [])
                    });
                } catch (error) {
                    // Metadata is optional; action support is checked separately.
                }
                const handlers = {
                    play: function () { player.play().catch(function () {}); },
                    pause: function () { player.pause(); },
                    seekbackward: function (details) { seek(media.currentTime - (details.seekOffset || 10)); },
                    seekforward: function (details) { seek(media.currentTime + (details.seekOffset || 10)); },
                    seekto: function (details) { seek(details.seekTime); }
                };
                actions.forEach(function (action) {
                    try { window.navigator.mediaSession.setActionHandler(action, function (details) {
                        if (sessionOwner === player && !player._destroyed && player.media === media) { handlers[action](details || {}); }
                    }); } catch (error) { /* Unsupported actions must not prevent the remaining ones. */ }
                });
            };
            player.on('play', activateSession);

            return function () {
                player.root.classList.remove('fireplayer--audio-ready');
                player.off('play', activateSession);
                // Removing an inactive audio block must not erase another player's controls.
                if ('mediaSession' in window.navigator && sessionOwner === player) {
                    sessionOwner = null;
                    try {
                        window.navigator.mediaSession.metadata = null;
                    } catch (error) {
                        // No cleanup is needed when an action is unsupported.
                    }
                    actions.forEach(function (action) {
                        try { window.navigator.mediaSession.setActionHandler(action, null); } catch (error) { /* Optional action. */ }
                    });
                }
            };
        }
    });
})(window, document);
