(function (window, document) {
    'use strict';

    if (!window.FirePlayer) {
        return;
    }

    window.FirePlayer.use({
        name: 'audio',
        test: function (player, info) {
            return info.media === 'audio';
        },
        setup: function (player) {
            player.root.classList.add('fireplayer--audio-ready');
            player.elements.pip.hidden = true;
            player.elements.fullscreen.hidden = true;

            if ('mediaSession' in window.navigator && player.options.title) {
                try {
                    window.navigator.mediaSession.metadata = new window.MediaMetadata({
                        title: player.options.title,
                        artwork: player.options.poster ? [{ src: player.options.poster }] : []
                    });
                    window.navigator.mediaSession.setActionHandler('play', function () { player.play(); });
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
            }

            return function () {
                player.root.classList.remove('fireplayer--audio-ready');
                if ('mediaSession' in window.navigator) {
                    try {
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
