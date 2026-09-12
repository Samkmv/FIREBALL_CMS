(function (window, document) {
    'use strict';

    if (!window.FirePlayer || window.FirePlayer.autoInitRegistered) {
        return;
    }
    window.FirePlayer.autoInitRegistered = true;

    const legacyOptions = function (media) {
        const raw = media.getAttribute('data-plyr-options');
        if (!raw) {
            return {};
        }
        try {
            const options = JSON.parse(raw);
            return options && typeof options === 'object' && !Array.isArray(options) ? options : {};
        } catch (error) {
            return {};
        }
    };

    const setDefaultAttribute = function (element, name, value) {
        if (value !== undefined && value !== null && !element.hasAttribute(name)) {
            element.setAttribute(name, String(value));
        }
    };

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
            const poster = media.getAttribute('poster') || media.getAttribute('data-poster') || '';
            const hlsSource = media.getAttribute('data-hls-src') || '';
            const options = legacyOptions(media);
            const hlsOptions = options.hls && typeof options.hls === 'object' ? options.hls : {};

            if (!legacyWrap) {
                media.parentNode.insertBefore(wrapper, media);
                wrapper.appendChild(media);
            }

            wrapper.classList.add('fire-player');
            wrapper.setAttribute('data-fire-player', '');
            wrapper.setAttribute('data-src', src);
            wrapper.setAttribute('data-media', type);
            setDefaultAttribute(wrapper, 'data-controls', options.controls !== undefined ? options.controls !== false : media.hasAttribute('controls'));
            setDefaultAttribute(wrapper, 'data-autoplay', options.autoplay !== undefined ? options.autoplay : media.autoplay);
            setDefaultAttribute(wrapper, 'data-muted', options.muted !== undefined ? options.muted : media.muted || media.defaultMuted);
            setDefaultAttribute(wrapper, 'data-loop', options.loop && typeof options.loop === 'object' ? options.loop.active : (options.loop !== undefined ? options.loop : media.loop));
            setDefaultAttribute(wrapper, 'data-preload', media.getAttribute('preload'));
            if (media.hasAttribute('crossorigin')) {
                setDefaultAttribute(wrapper, 'data-crossorigin', media.getAttribute('crossorigin') || 'anonymous');
            }
            setDefaultAttribute(wrapper, 'data-title', media.getAttribute('aria-label') || media.getAttribute('title'));
            setDefaultAttribute(wrapper, 'data-aspect-ratio', media.getAttribute('data-aspect-ratio') || options.ratio);
            setDefaultAttribute(wrapper, 'data-lazy-start', options.hlsLazyStart !== undefined ? options.hlsLazyStart : hlsOptions.lazyStart);
            setDefaultAttribute(wrapper, 'data-poster-cache-bust', options.posterCacheBust !== undefined ? options.posterCacheBust : hlsOptions.posterCacheBust);
            if (poster) {
                setDefaultAttribute(wrapper, 'data-poster', poster);
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

    const disposeRemovedPlayers = function (nodes) {
        const players = new Set();
        nodes.forEach(function (node) {
            if (!(node instanceof Element)) {
                return;
            }
            const collect = function (element) {
                const player = window.FirePlayer.get(element);
                if (player) {
                    players.add(player);
                }
            };
            collect(node);
            node.querySelectorAll('[data-fire-player-initialized="true"]').forEach(collect);
        });
        players.forEach(function (player) {
            // A DOM move reports a removal too; a still-connected player keeps its stream.
            if (!player.root.isConnected) {
                player.destroy();
            }
        });
    };

    const start = function () {
        initialize(document);
        if (typeof MutationObserver === 'function' && document.body) {
            new MutationObserver(function (mutations) {
                const removedNodes = [];
                const addedNodes = new Set();
                mutations.forEach(function (mutation) {
                    mutation.removedNodes.forEach(function (node) { removedNodes.push(node); });
                    mutation.addedNodes.forEach(function (node) {
                        if (node instanceof Element) {
                            addedNodes.add(node);
                        }
                    });
                });
                disposeRemovedPlayers(removedNodes);
                addedNodes.forEach(function (node) {
                    if (node.isConnected && !node.closest('[data-fire-player-initialized="true"]')) {
                        initialize(node);
                    }
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
