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
            const sharedOwner = {
                play: function () { player.play().catch(function () {}); },
                pause: function () { player.pause(); },
                seekbackward: function (details) { seek(media.currentTime - (details.seekOffset || 10)); },
                seekforward: function (details) { seek(media.currentTime + (details.seekOffset || 10)); },
                seekto: function (details) { seek(details.seekTime); },
                deactivate: function () { if (!player._destroyed && player.media === media) { player.pause(); } },
                activate: function () {
                    try {
                        window.FireballMediaSession.update(sharedOwner, {
                            playbackState: media.paused ? 'paused' : 'playing',
                            metadata: new window.MediaMetadata({
                                title: player.options.title || window.FirePlayer.translate('audio'),
                                artist: player.options.artist || '', album: player.options.album || '',
                                artwork: Array.isArray(player.options.artwork) ? player.options.artwork : (player.options.poster ? [{ src: player.options.poster }] : [])
                            })
                        });
                    } catch (error) { /* Optional metadata must never interrupt audio. */ }
                }
            };
            const activateSession = function () {
                if (player._destroyed || player.media !== media) { return; }
                if (window.FireballMediaSession) {
                    sessionOwner = player;
                    window.FireballMediaSession.claim(sharedOwner);
                    return;
                }
                if (!('mediaSession' in window.navigator)) { return; }
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
            const sharedSessionReady = function () { if (!media.paused && !media.ended) { activateSession(); } };
            document.addEventListener('fireball:media-session-ready', sharedSessionReady);

            return function () {
                player.root.classList.remove('fireplayer--audio-ready');
                player.off('play', activateSession);
                document.removeEventListener('fireball:media-session-ready', sharedSessionReady);
                if (window.FireballMediaSession) {
                    window.FireballMediaSession.release(sharedOwner);
                    if (sessionOwner === player) { sessionOwner = null; }
                    return;
                }
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
