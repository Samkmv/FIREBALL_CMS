(function (window, document) {
    'use strict';

    const initialize = function () {
        const modal = document.getElementById('cameraManagerPlayerModal');
        const container = modal ? modal.querySelector('[data-camera-player]') : null;
        if (!modal || !container || typeof window.FirePlayer !== 'function') {
            return;
        }

        let player = null;
        let selectedButton = null;

        const getPlayer = function () {
            if (!player) {
                player = new window.FirePlayer(container, {
                    media: 'video',
                    mode: 'live',
                    autoplay: true,
                    muted: true,
                    reconnect: true,
                    lazyStart: true,
                    rememberPosition: false,
                    posterCacheBust: true
                });
            }
            return player;
        };

        document.querySelectorAll('[data-camera-player-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                selectedButton = button;
                const title = button.dataset.playerTitle || 'Камера';
                const titleNode = modal.querySelector('[data-camera-player-title]');
                if (titleNode) {
                    titleNode.textContent = title;
                }

                const instance = getPlayer();
                instance.load(button.dataset.playerSrc || '', {
                    poster: button.dataset.playerPoster || '',
                    streamId: button.dataset.playerStreamId || '',
                    title: title,
                    media: 'video',
                    protocol: 'auto',
                    mode: 'live',
                    autoplay: true,
                    muted: true
                }).catch(function (error) {
                    if (window.console && typeof window.console.error === 'function') {
                        window.console.error('Camera Manager could not open the live stream', error);
                    }
                });
            });
        });

        modal.addEventListener('hidden.bs.modal', function () {
            if (player) {
                player.unload();
            }
            if (selectedButton) {
                selectedButton.focus();
            }
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})(window, document);
