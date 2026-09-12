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
            const activateSession = function () {
                if (!('mediaSession' in window.navigator) || player._destroyed || player.media !== media) { return; }
                try {
                    sessionOwner = player;
                    window.navigator.mediaSession.metadata = new window.MediaMetadata({
                        title: player.options.title || window.FirePlayer.translate('audio'),
                        artwork: player.options.poster ? [{ src: player.options.poster }] : []
                    });
                    window.navigator.mediaSession.setActionHandler('play', function () { player.play().catch(function () {}); });
                    window.navigator.mediaSession.setActionHandler('pause', function () { player.pause(); });
                    window.navigator.mediaSession.setActionHandler('seekbackward', function (details) {
                        player.media.currentTime = Math.max(0, player.media.currentTime - (details.seekOffset || 10));
                    });
                    window.navigator.mediaSession.setActionHandler('seekforward', function (details) {
                        player.media.currentTime = Math.min(player.media.duration || Infinity, player.media.currentTime + (details.seekOffset || 10));
                    });
                } catch (error) {
                    // Browsers expose different Media Session action subsets.
                }
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
                        ['play', 'pause', 'seekbackward', 'seekforward'].forEach(function (action) {
                            window.navigator.mediaSession.setActionHandler(action, null);
                        });
                    } catch (error) {
                        // No cleanup is needed when an action is unsupported.
                    }
                }
            };
        }
    });
})(window, document);
