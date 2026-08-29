(function (window, document) {
    'use strict';

    if (!window.FirePlayer) {
        return;
    }

    const readMediaSource = function (media) {
        const source = media.querySelector('source[src]');
        return media.getAttribute('data-hls-src')
            || media.getAttribute('data-src')
            || media.getAttribute('src')
            || (source ? source.getAttribute('src') : '')
            || '';
    };

    const upgradeLegacyContentMedia = function (scope) {
        const mediaElements = [];
        const root = scope instanceof Element ? scope : document;

        if (root instanceof Element && root.matches('.post-content video, .post-content audio')) {
            mediaElements.push(root);
        }
        root.querySelectorAll('.post-content video, .post-content audio').forEach(function (media) {
            mediaElements.push(media);
        });

        mediaElements.forEach(function (media) {
            if (media.closest('.fireplayer, .fire-player, [data-fire-player]')) {
                return;
            }

            const src = readMediaSource(media).trim();
            if (!src) {
                return;
            }

            const legacyWrap = media.closest('[data-plyr-player-wrap]');
            const wrapper = legacyWrap || document.createElement('div');
            const type = media.tagName.toLowerCase() === 'audio' ? 'audio' : 'video';
            const poster = media.getAttribute('poster') || '';
            const hlsSource = media.getAttribute('data-hls-src') || '';

            if (!legacyWrap) {
                media.parentNode.insertBefore(wrapper, media);
                wrapper.appendChild(media);
            }

            wrapper.classList.add('fire-player');
            wrapper.setAttribute('data-fire-player', '');
            wrapper.setAttribute('data-src', src);
            wrapper.setAttribute('data-media', type);
            wrapper.setAttribute('data-controls', media.hasAttribute('controls') ? 'true' : 'false');
            wrapper.setAttribute('data-autoplay', media.autoplay ? 'true' : 'false');
            wrapper.setAttribute('data-muted', media.muted || media.defaultMuted ? 'true' : 'false');
            wrapper.setAttribute('data-loop', media.loop ? 'true' : 'false');
            if (poster) {
                wrapper.setAttribute('data-poster', poster);
            }
            if (hlsSource || /\.m3u8(?:$|[?#])/i.test(src)) {
                wrapper.setAttribute('data-protocol', 'hls');
            }

            wrapper.removeAttribute('data-plyr-player-wrap');
            wrapper.removeAttribute('data-plyr-media');
            wrapper.removeAttribute('data-plyr-lazy');
            media.removeAttribute('data-plyr-player');
            media.removeAttribute('data-plyr-options');
        });
    };

    const initialize = function (scope) {
        try {
            upgradeLegacyContentMedia(scope || document);
            window.FirePlayer.bootstrap(scope || document);
        } catch (error) {
            if (window.console && typeof window.console.error === 'function') {
                window.console.error('FirePlayer initialization failed', error);
            }
        }
    };

    const start = function () {
        initialize(document);
        if (typeof MutationObserver === 'function' && document.body) {
            new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node instanceof Element) {
                            initialize(node);
                        }
                    });
                });
            }).observe(document.body, { childList: true, subtree: true });
        }
        document.dispatchEvent(new CustomEvent('fireplayer:bootstrap'));
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})(window, document);
