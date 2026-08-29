(function (window, document) {
    'use strict';

    const adapters = new Map();
    const extensions = [];
    const instances = new WeakMap();
    const storagePrefix = 'fireplayer.';
    const audioExtensions = new Set(['aac', 'flac', 'm4a', 'mp3', 'oga', 'ogg', 'opus', 'wav', 'weba']);
    const videoExtensions = new Set(['m4v', 'mov', 'mp4', 'ogv', 'webm']);
    const mimeByExtension = {
        aac: 'audio/aac', flac: 'audio/flac', m4a: 'audio/mp4', mp3: 'audio/mpeg',
        oga: 'audio/ogg', ogg: 'audio/ogg', opus: 'audio/ogg', wav: 'audio/wav', weba: 'audio/webm',
        m4v: 'video/mp4', mov: 'video/quicktime', mp4: 'video/mp4', ogv: 'video/ogg', webm: 'video/webm',
        m3u8: 'application/vnd.apple.mpegurl', mpd: 'application/dash+xml'
    };
    const labels = {
        ru: {
            play: 'Воспроизвести', pause: 'Пауза', mute: 'Выключить звук', unmute: 'Включить звук',
            volume: 'Громкость', seek: 'Позиция', speed: 'Скорость', pip: 'Картинка в картинке',
            fullscreen: 'Полный экран', exitFullscreen: 'Выйти из полного экрана', live: 'LIVE',
            goLive: 'Перейти в LIVE', detecting: 'Определяем источник…', connecting: 'Подключение…',
            loading: 'Загрузка медиа…', reconnecting: 'Повторное подключение…',
            unsupported: 'Этот формат не поддерживается браузером', failed: 'Не удалось воспроизвести медиа',
            retry: 'Повторить', audio: 'Аудиоплеер', video: 'Видеоплеер'
        },
        en: {
            play: 'Play', pause: 'Pause', mute: 'Mute', unmute: 'Unmute', volume: 'Volume',
            seek: 'Seek', speed: 'Speed', pip: 'Picture in Picture', fullscreen: 'Fullscreen',
            exitFullscreen: 'Exit fullscreen', live: 'LIVE', goLive: 'Go live', detecting: 'Detecting source…',
            connecting: 'Connecting…', loading: 'Loading media…', reconnecting: 'Reconnecting…',
            unsupported: 'This format is not supported by the browser', failed: 'Unable to play media',
            retry: 'Retry', audio: 'Audio player', video: 'Video player'
        }
    };
    const icons = {
        play: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13l10-6.5z"/></svg>',
        pause: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5h4v14H7zm6 0h4v14h-4z"/></svg>',
        volume: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9zm11.4-.9v7.8a5 5 0 0 0 0-7.8zm0-3.2v2.2a7 7 0 0 1 0 9.8v2.2a9 9 0 0 0 0-14.2z"/></svg>',
        muted: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9zm11.6 1.6L18.2 8l1.4 1.4L17 12l2.6 2.6-1.4 1.4-2.6-2.6L13 16l-1.4-1.4 2.6-2.6-2.6-2.6L13 8z"/></svg>',
        pip: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18v14H3zm2 2v10h14V7zm7 4h6v5h-6z"/></svg>',
        fullscreen: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h5v2H7v3H5zm9 0h5v5h-2V7h-3zM5 14h2v3h3v2H5zm12 0h2v5h-5v-2h3z"/></svg>',
        exitFullscreen: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7V4H5v5h5V7zm10 0h-3v2h5V4h-2zM7 17h3v-2H5v5h2zm10 0v3h2v-5h-5v2z"/></svg>',
        retry: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.4 5.6A9 9 0 1 0 21 12h-2a7 7 0 1 1-2-4.9L14 10h7V3z"/></svg>'
    };

    const locale = function () {
        return String(document.documentElement.lang || 'en').toLowerCase().startsWith('ru') ? 'ru' : 'en';
    };

    const t = function (key) {
        const dictionary = labels[locale()] || labels.en;
        return dictionary[key] || labels.en[key] || key;
    };

    const booleanValue = function (value, fallback) {
        if (value === undefined || value === null || value === '') {
            return fallback;
        }
        if (value === true || value === 1 || value === '1' || value === 'true') {
            return true;
        }
        if (value === false || value === 0 || value === '0' || value === 'false') {
            return false;
        }
        return fallback;
    };

    const numberValue = function (value, fallback, minimum, maximum) {
        const number = Number(value);
        if (!Number.isFinite(number)) {
            return fallback;
        }
        return Math.max(minimum, Math.min(maximum, number));
    };

    const sourceExtension = function (source) {
        try {
            const pathname = new URL(source, window.location.href).pathname;
            const match = pathname.match(/\.([a-z0-9]+)$/i);
            return match ? match[1].toLowerCase() : '';
        } catch (error) {
            const clean = String(source || '').split(/[?#]/)[0];
            const match = clean.match(/\.([a-z0-9]+)$/i);
            return match ? match[1].toLowerCase() : '';
        }
    };

    const sourceFromMedia = function (media) {
        if (!(media instanceof HTMLMediaElement)) {
            return '';
        }
        const direct = media.dataset.hlsSrc || media.dataset.src || media.currentSrc || media.getAttribute('src') || '';
        if (direct) {
            return direct;
        }
        const source = media.querySelector('source[src]');
        return source ? source.getAttribute('src') || '' : '';
    };

    const parseDataset = function (element) {
        if (!(element instanceof Element)) {
            return {};
        }
        const data = element.dataset || {};
        const result = {};
        const assignText = function (key, value) {
            if (value !== undefined && value !== '') {
                result[key] = value;
            }
        };
        const assignBoolean = function (key, value, fallback) {
            if (value !== undefined) {
                result[key] = booleanValue(value === '' ? true : value, fallback);
            }
        };

        assignText('src', data.src || data.firePlayerSrc || data.hlsSrc);
        assignText('poster', data.poster);
        assignText('media', data.media || data.type);
        assignText('protocol', data.protocol);
        assignText('mode', data.mode);
        assignText('title', data.title || element.getAttribute('aria-label'));
        assignText('streamId', data.streamId);
        assignBoolean('autoplay', data.autoplay, false);
        assignBoolean('muted', data.muted, false);
        assignBoolean('loop', data.loop, false);
        assignBoolean('controls', data.controls, true);
        assignBoolean('reconnect', data.reconnect, true);
        assignBoolean('lazyStart', data.lazyStart !== undefined ? data.lazyStart : data.hlsLazyStart, false);
        assignBoolean('posterCacheBust', data.posterCacheBust, false);
        assignBoolean('rememberPosition', data.rememberPosition, true);
        assignBoolean('rememberVolume', data.rememberVolume, true);

        return result;
    };

    const mergeOptions = function (element, options) {
        const media = element instanceof HTMLMediaElement ? element : element.querySelector('video, audio');
        const dataset = Object.assign({}, parseDataset(element), parseDataset(media));
        const mediaType = media instanceof HTMLAudioElement ? 'audio' : (media instanceof HTMLVideoElement ? 'video' : 'auto');
        return Object.assign({
            src: sourceFromMedia(media), poster: media ? media.getAttribute('poster') || '' : '',
            media: mediaType, protocol: 'auto', mode: 'auto', title: '', controls: true,
            autoplay: media ? media.autoplay : false, muted: media ? media.muted : false,
            loop: media ? media.loop : false, playsinline: true, preload: 'metadata', crossorigin: '',
            reconnect: true, reconnectDelay: 2500, stallTimeout: 7000, liveEdgeTolerance: 4,
            posterRefreshInterval: 5000, posterCacheBust: false, lazyStart: false,
            rememberPosition: true, rememberVolume: true, keyboard: true, gestures: true,
            probe: true, probeTimeout: 6000
        }, dataset, options || {});
    };

    const inferFromMime = function (contentType, result) {
        const mime = String(contentType || '').split(';')[0].trim().toLowerCase();
        if (!mime) {
            return result;
        }
        result.contentType = mime;
        if (mime === 'application/vnd.apple.mpegurl' || mime === 'application/x-mpegurl' || mime === 'audio/mpegurl') {
            result.media = 'video';
            result.protocol = 'hls';
        } else if (mime === 'application/dash+xml') {
            result.media = 'video';
            result.protocol = 'dash';
        } else if (mime.startsWith('audio/')) {
            result.media = 'audio';
            result.protocol = 'file';
        } else if (mime.startsWith('video/')) {
            result.media = 'video';
            result.protocol = 'file';
        }
        return result;
    };

    const inspectManifest = function (manifest, result) {
        const text = String(manifest || '');
        if (!/^\s*#EXTM3U/m.test(text)) {
            return result;
        }
        result.media = 'video';
        result.protocol = 'hls';
        if (/#EXT-X-ENDLIST\b/i.test(text) || /#EXT-X-PLAYLIST-TYPE\s*:\s*VOD\b/i.test(text)) {
            result.mode = 'vod';
        } else if (/#EXT-X-PLAYLIST-TYPE\s*:\s*EVENT\b/i.test(text)) {
            result.mode = 'event';
        } else if (/#EXTINF\s*:/i.test(text) || /#EXT-X-STREAM-INF\s*:/i.test(text)) {
            result.mode = 'live';
        }
        return result;
    };

    const fetchWithTimeout = async function (url, options, timeout) {
        const controller = typeof AbortController === 'function' ? new AbortController() : null;
        const timer = controller ? window.setTimeout(function () { controller.abort(); }, timeout) : null;
        try {
            return await window.fetch(url, Object.assign({
                credentials: 'same-origin', cache: 'no-store', signal: controller ? controller.signal : undefined
            }, options || {}));
        } finally {
            if (timer) {
                window.clearTimeout(timer);
            }
        }
    };

    const nativeSupport = function (media, protocol, contentType) {
        const element = document.createElement(media === 'audio' ? 'audio' : 'video');
        const mime = protocol === 'hls' ? 'application/vnd.apple.mpegurl'
            : (protocol === 'dash' ? 'application/dash+xml' : contentType);
        if (!mime || typeof element.canPlayType !== 'function') {
            return false;
        }
        const answer = element.canPlayType(mime);
        return answer === 'probably' || answer === 'maybe';
    };

    const detect = async function (source, hints) {
        const options = Object.assign({ media: 'auto', protocol: 'auto', mode: 'auto', probe: true, probeTimeout: 6000 }, hints || {});
        const src = String(source || '').trim();
        if (!src) {
            throw new Error('FirePlayer requires a media source.');
        }
        const extension = sourceExtension(src);
        const result = {
            src: src,
            media: options.media === 'audio' || options.media === 'video' ? options.media : 'video',
            protocol: options.protocol !== 'auto' ? options.protocol : 'file',
            mode: options.mode !== 'auto' ? options.mode : 'vod',
            contentType: mimeByExtension[extension] || '',
            extension: extension,
            nativeSupport: false,
            detectedBy: 'fallback'
        };

        if (options.protocol === 'auto') {
            if (extension === 'm3u8') {
                result.protocol = 'hls';
                result.media = 'video';
                result.mode = options.mode === 'auto' ? 'live' : options.mode;
                result.detectedBy = 'extension';
            } else if (extension === 'mpd') {
                result.protocol = 'dash';
                result.media = 'video';
                result.detectedBy = 'extension';
            } else if (audioExtensions.has(extension)) {
                result.protocol = 'file';
                result.media = 'audio';
                result.detectedBy = 'extension';
            } else if (videoExtensions.has(extension)) {
                result.protocol = 'file';
                result.media = 'video';
                result.detectedBy = 'extension';
            }
        }

        if (options.probe !== false && typeof window.fetch === 'function' && (result.protocol === 'hls' || !extension)) {
            try {
                if (result.protocol === 'hls') {
                    const response = await fetchWithTimeout(src, { method: 'GET', headers: { Accept: 'application/vnd.apple.mpegurl, application/x-mpegURL, text/plain;q=0.8, */*;q=0.1' } }, options.probeTimeout);
                    inferFromMime(response.headers.get('Content-Type'), result);
                    if (response.ok) {
                        inspectManifest(await response.text(), result);
                        result.detectedBy = 'manifest';
                    }
                } else {
                    const response = await fetchWithTimeout(src, { method: 'HEAD' }, options.probeTimeout);
                    inferFromMime(response.headers.get('Content-Type'), result);
                    result.detectedBy = response.ok ? 'content-type' : result.detectedBy;
                    if (result.protocol === 'hls' && response.ok) {
                        const manifestResponse = await fetchWithTimeout(src, { method: 'GET' }, options.probeTimeout);
                        inspectManifest(await manifestResponse.text(), result);
                    }
                }
            } catch (error) {
                result.probeError = error && error.message ? error.message : String(error);
            }
        }

        if (options.media === 'audio' || options.media === 'video') {
            result.media = options.media;
        }
        if (options.mode !== 'auto') {
            result.mode = options.mode;
        }
        result.nativeSupport = nativeSupport(result.media, result.protocol, result.contentType);
        return result;
    };

    const formatTime = function (seconds, forceHours) {
        const value = Number.isFinite(seconds) && seconds > 0 ? Math.floor(seconds) : 0;
        const hours = Math.floor(value / 3600);
        const minutes = Math.floor((value % 3600) / 60);
        const rest = value % 60;
        if (hours > 0 || forceHours) {
            return hours + ':' + String(minutes).padStart(2, '0') + ':' + String(rest).padStart(2, '0');
        }
        return minutes + ':' + String(rest).padStart(2, '0');
    };

    const storage = {
        get: function (key, fallback) {
            try {
                const value = window.localStorage.getItem(storagePrefix + key);
                return value === null ? fallback : JSON.parse(value);
            } catch (error) {
                return fallback;
            }
        },
        set: function (key, value) {
            try {
                window.localStorage.setItem(storagePrefix + key, JSON.stringify(value));
            } catch (error) {
                // Storage can be disabled in privacy mode.
            }
        }
    };

    const hash = function (value) {
        let result = 2166136261;
        for (let index = 0; index < value.length; index += 1) {
            result ^= value.charCodeAt(index);
            result = Math.imul(result, 16777619);
        }
        return (result >>> 0).toString(36);
    };

    class FirePlayer {
        constructor(target, options) {
            const element = typeof target === 'string' ? document.querySelector(target) : target;
            if (!(element instanceof Element)) {
                throw new TypeError('FirePlayer target was not found.');
            }

            const existing = instances.get(element) || element.firePlayer;
            if (existing instanceof FirePlayer) {
                return existing;
            }

            this.originalElement = element;
            this.originalMedia = element instanceof HTMLMediaElement ? element : element.querySelector('video, audio');
            this.options = mergeOptions(element, options);
            this.root = this._prepareRoot(element);
            this.media = null;
            this.info = null;
            this.controller = null;
            this._events = new Map();
            this._listeners = [];
            this._cleanups = [];
            this._loadToken = 0;
            this._controlsTimer = null;
            this._lastPositionStoreAt = 0;
            this._destroyed = false;
            this._wasPlayingBeforeReconnect = false;
            this._render(this.options.media === 'audio' ? 'audio' : 'video');

            instances.set(this.root, this);
            instances.set(element, this);
            this.root.firePlayer = this;
            this.root.dataset.firePlayerInitialized = 'true';

            this.ready = this.options.src ? this.load(this.options.src) : Promise.resolve(this);
        }

        _prepareRoot(element) {
            if (element instanceof HTMLMediaElement) {
                const root = document.createElement('div');
                root.className = 'fire-player';
                element.parentNode.insertBefore(root, element);
                root.appendChild(element);
                return root;
            }
            element.classList.add('fire-player');
            return element;
        }

        _render(mediaType) {
            this._clearListeners();
            const media = document.createElement(mediaType === 'audio' ? 'audio' : 'video');
            media.className = 'fireplayer__media';
            media.preload = this.options.preload || 'metadata';
            media.controls = false;
            if (media instanceof HTMLVideoElement) {
                media.playsInline = this.options.playsinline !== false;
                media.setAttribute('webkit-playsinline', '');
            }

            this.root.className = this.root.className
                .split(/\s+/)
                .filter(function (name) { return name && !name.startsWith('fireplayer--'); })
                .join(' ');
            this.root.classList.add('fireplayer', mediaType === 'audio' ? 'fireplayer--audio' : 'fireplayer--video');
            this.root.setAttribute('role', 'region');
            this.root.setAttribute('aria-label', this.options.title || t(mediaType));
            this.root.tabIndex = 0;
            this.root.innerHTML = '';

            const stage = document.createElement('div');
            stage.className = 'fireplayer__stage';
            stage.dataset.fpStage = '';
            stage.appendChild(media);
            stage.insertAdjacentHTML('beforeend',
                '<div class="fireplayer__shade" aria-hidden="true"></div>' +
                '<span class="fireplayer__live-badge" data-fp-live-badge hidden><i></i>' + t('live') + '</span>' +
                '<button class="fireplayer__center-play" type="button" data-fp-action="play" aria-label="' + t('play') + '">' + icons.play + '</button>' +
                '<button class="fireplayer__retry" type="button" data-fp-action="retry" aria-label="' + t('retry') + '" hidden>' + icons.retry + '<span>' + t('retry') + '</span></button>' +
                '<div class="fireplayer__status" data-fp-status role="status" aria-live="polite" hidden></div>'
            );

            const controls = document.createElement('div');
            controls.className = 'fireplayer__controls';
            controls.dataset.fpControls = '';
            controls.innerHTML =
                '<button class="fireplayer__button" type="button" data-fp-action="play" aria-label="' + t('play') + '">' + icons.play + '</button>' +
                '<span class="fireplayer__time fireplayer__time--current" data-fp-current>0:00</span>' +
                '<label class="fireplayer__seek-wrap"><span class="fireplayer__sr-only">' + t('seek') + '</span><input class="fireplayer__range fireplayer__seek" data-fp-seek type="range" min="0" max="100" step="0.05" value="0"></label>' +
                '<span class="fireplayer__time fireplayer__time--duration" data-fp-duration>0:00</span>' +
                '<button class="fireplayer__live-button" type="button" data-fp-action="live" hidden><i></i><span>' + t('live') + '</span></button>' +
                '<div class="fireplayer__volume"><button class="fireplayer__button" type="button" data-fp-action="mute" aria-label="' + t('mute') + '">' + icons.volume + '</button>' +
                '<label><span class="fireplayer__sr-only">' + t('volume') + '</span><input class="fireplayer__range fireplayer__volume-range" data-fp-volume type="range" min="0" max="1" step="0.02" value="1"></label></div>' +
                '<label class="fireplayer__speed-wrap"><span class="fireplayer__sr-only">' + t('speed') + '</span><select class="fireplayer__speed" data-fp-speed aria-label="' + t('speed') + '"><option value="0.5">0.5×</option><option value="0.75">0.75×</option><option value="1" selected>1×</option><option value="1.25">1.25×</option><option value="1.5">1.5×</option><option value="2">2×</option></select></label>' +
                '<button class="fireplayer__button" type="button" data-fp-action="pip" aria-label="' + t('pip') + '">' + icons.pip + '</button>' +
                '<button class="fireplayer__button" type="button" data-fp-action="fullscreen" aria-label="' + t('fullscreen') + '">' + icons.fullscreen + '</button>';

            this.root.appendChild(stage);
            this.root.appendChild(controls);
            this.media = media;
            this.elements = {
                stage: stage,
                controls: controls,
                status: stage.querySelector('[data-fp-status]'),
                retry: stage.querySelector('[data-fp-action="retry"]'),
                playButtons: this.root.querySelectorAll('[data-fp-action="play"]'),
                mute: controls.querySelector('[data-fp-action="mute"]'),
                seek: controls.querySelector('[data-fp-seek]'),
                volume: controls.querySelector('[data-fp-volume]'),
                speed: controls.querySelector('[data-fp-speed]'),
                current: controls.querySelector('[data-fp-current]'),
                duration: controls.querySelector('[data-fp-duration]'),
                live: controls.querySelector('[data-fp-action="live"]'),
                liveBadge: stage.querySelector('[data-fp-live-badge]'),
                pip: controls.querySelector('[data-fp-action="pip"]'),
                fullscreen: controls.querySelector('[data-fp-action="fullscreen"]')
            };
            this.elements.controls.hidden = this.options.controls === false;
            this._bindUi();
        }

        _listen(element, eventName, callback, options) {
            if (!element || typeof element.addEventListener !== 'function') {
                return;
            }
            element.addEventListener(eventName, callback, options);
            this._listeners.push(function () { element.removeEventListener(eventName, callback, options); });
        }

        _clearListeners() {
            this._listeners.splice(0).forEach(function (remove) { remove(); });
            if (this._controlsTimer) {
                window.clearTimeout(this._controlsTimer);
                this._controlsTimer = null;
            }
        }

        _bindUi() {
            const player = this;
            this.elements.playButtons.forEach(function (button) {
                player._listen(button, 'click', function () { player.toggle(); });
            });
            this._listen(this.elements.retry, 'click', function () { player.reconnect('manual'); });
            this._listen(this.elements.mute, 'click', function () { player.mute(); });
            this._listen(this.elements.seek, 'input', function () {
                const value = Number(player.elements.seek.value);
                if (Number.isFinite(value)) {
                    player.media.currentTime = value;
                }
            });
            this._listen(this.elements.volume, 'input', function () {
                player.media.volume = numberValue(player.elements.volume.value, 1, 0, 1);
                player.media.muted = player.media.volume === 0;
            });
            this._listen(this.elements.speed, 'change', function () {
                player.media.playbackRate = numberValue(player.elements.speed.value, 1, 0.25, 4);
            });
            this._listen(this.elements.live, 'click', function () { player.goLive(); });
            this._listen(this.elements.pip, 'click', function () { player.pictureInPicture(); });
            this._listen(this.elements.fullscreen, 'click', function () { player.fullscreen(); });

            this._listen(this.media, 'play', function () {
                player.root.classList.add('fireplayer--playing');
                player.root.classList.remove('fireplayer--ended', 'fireplayer--error');
                player._syncPlayButtons();
                player._emit('play');
            });
            this._listen(this.media, 'pause', function () {
                player.root.classList.remove('fireplayer--playing');
                player._syncPlayButtons();
                player._emit('pause');
            });
            this._listen(this.media, 'playing', function () {
                player.root.classList.remove('fireplayer--loading', 'fireplayer--reconnecting', 'fireplayer--error');
                player.elements.retry.hidden = true;
                player.setStatus('');
                player._emit('playing');
            });
            ['waiting', 'stalled', 'seeking'].forEach(function (eventName) {
                player._listen(player.media, eventName, function () {
                    if (!player.media.paused) {
                        player.root.classList.add('fireplayer--loading');
                        player.setStatus(t('loading'), 'info');
                    }
                });
            });
            ['loadedmetadata', 'durationchange', 'timeupdate', 'progress'].forEach(function (eventName) {
                player._listen(player.media, eventName, function () { player._syncTimeline(); });
            });
            this._listen(this.media, 'loadedmetadata', function () {
                player._restorePosition();
                player._emit('loadedmetadata', { duration: player.media.duration });
            });
            this._listen(this.media, 'volumechange', function () {
                player._syncVolume();
                if (player.options.rememberVolume) {
                    storage.set('volume', { volume: player.media.volume, muted: player.media.muted });
                }
            });
            this._listen(this.media, 'ratechange', function () {
                player.elements.speed.value = String(player.media.playbackRate);
            });
            this._listen(this.media, 'ended', function () {
                player.root.classList.add('fireplayer--ended');
                player._storePosition(true);
                player._emit('ended');
            });
            this._listen(this.media, 'error', function () { player._handleMediaError(); });
            this._listen(this.media, 'timeupdate', function () { player._storePosition(false); });
            this._listen(document, 'fullscreenchange', function () { player._syncFullscreen(); });
            this._listen(document, 'webkitfullscreenchange', function () { player._syncFullscreen(); });
            this._listen(this.root, 'keydown', function (event) { player._handleKey(event); });
            ['mousemove', 'pointermove', 'touchstart', 'focusin'].forEach(function (eventName) {
                player._listen(player.root, eventName, function () { player._showControls(); }, { passive: true });
            });

            this._restoreVolume();
            this._syncPlayButtons();
            this._syncTimeline();
            this._syncCapabilities();
        }

        async load(source, overrides) {
            if (this._destroyed) {
                throw new Error('FirePlayer instance was destroyed.');
            }
            const src = String(source || '').trim();
            if (!src) {
                throw new Error('FirePlayer requires a media source.');
            }
            const token = ++this._loadToken;
            this.options = Object.assign({}, this.options, overrides || {}, { src: src });
            this._teardownPlayback();
            this.root.classList.remove('fireplayer--ready', 'fireplayer--error', 'fireplayer--live', 'fireplayer--event', 'fireplayer--vod');
            this.root.classList.add('fireplayer--loading');
            this.elements.retry.hidden = true;
            this.setStatus(t('detecting'), 'info');

            const info = await detect(src, this.options);
            if (token !== this._loadToken || this._destroyed) {
                return this;
            }
            this.info = info;
            if ((info.media === 'audio') !== (this.media instanceof HTMLAudioElement)) {
                this._render(info.media);
                this.root.classList.add('fireplayer--loading');
            }
            this.root.classList.add('fireplayer--' + info.mode);
            this.root.classList.toggle('fireplayer--live', info.mode === 'live' || info.mode === 'event');
            this.media.autoplay = Boolean(this.options.autoplay);
            if (this.options.muted === true) {
                this.media.muted = true;
            } else if (!this.options.rememberVolume) {
                this.media.muted = false;
            }
            this.media.loop = Boolean(this.options.loop);
            this.media.preload = this.options.preload || 'metadata';
            if (this.options.crossorigin) {
                this.media.crossOrigin = this.options.crossorigin;
            }
            if (this.media instanceof HTMLVideoElement && this.options.poster) {
                this.media.poster = this.options.poster;
            }
            this._syncLiveUi();
            this.setStatus(info.protocol === 'hls' ? t('connecting') : t('loading'), 'info');

            const adapter = adapters.get(info.protocol);
            let adapterResult = null;
            try {
                if (adapter) {
                    adapterResult = await adapter(this, info);
                } else if (info.protocol === 'file' || info.nativeSupport) {
                    this.media.src = src;
                    this.media.load();
                    adapterResult = { handled: true };
                }
            } catch (error) {
                if (token !== this._loadToken || this._destroyed) {
                    return this;
                }
                this._showError(t('failed'), error);
                throw error;
            }
            if (token !== this._loadToken || this._destroyed) {
                if (adapterResult && typeof adapterResult.cleanup === 'function') {
                    adapterResult.cleanup();
                }
                return this;
            }
            if (!adapterResult || adapterResult.handled !== true) {
                this._showError(t('unsupported'), new Error('Unsupported protocol: ' + info.protocol));
                throw new Error('Unsupported protocol: ' + info.protocol);
            }
            this.controller = adapterResult.controller || null;
            if (typeof adapterResult.cleanup === 'function') {
                this._cleanups.push(adapterResult.cleanup);
            }

            for (const extension of extensions) {
                if (!extension.test || extension.test(this, info)) {
                    const cleanup = await extension.setup(this, info);
                    if (typeof cleanup === 'function') {
                        this._cleanups.push(cleanup);
                    }
                }
            }
            if (token !== this._loadToken || this._destroyed) {
                return this;
            }

            this.root.classList.add('fireplayer--ready');
            this.root.classList.remove('fireplayer--loading');
            this._syncCapabilities();
            this._emit('ready', info);
            if (info.mode === 'live' || info.mode === 'event') {
                this._emit('live', info);
            }
            if (this.options.autoplay) {
                try {
                    await this.play();
                } catch (error) {
                    this._emit('autoplayblocked', { error: error });
                }
            }
            return this;
        }

        unload() {
            ++this._loadToken;
            this._teardownPlayback();
            this.info = null;
            this.root.classList.remove('fireplayer--ready', 'fireplayer--playing', 'fireplayer--loading', 'fireplayer--error', 'fireplayer--live', 'fireplayer--event', 'fireplayer--vod');
            this.setStatus('');
            return this;
        }

        _teardownPlayback() {
            this._cleanups.splice(0).reverse().forEach(function (cleanup) {
                try { cleanup(); } catch (error) { /* A module cleanup must not block another one. */ }
            });
            this.controller = null;
            if (this.media) {
                try { this.media.pause(); } catch (error) { /* The media may not be attached yet. */ }
                this.media.removeAttribute('src');
                while (this.media.firstChild) {
                    this.media.removeChild(this.media.firstChild);
                }
                try { this.media.load(); } catch (error) { /* Some test DOMs do not implement load(). */ }
            }
        }

        async play() {
            if (!this.options.src) {
                throw new Error('FirePlayer has no source.');
            }
            const promise = this.media.play();
            if (promise && typeof promise.then === 'function') {
                await promise;
            }
            return this;
        }

        pause() {
            this.media.pause();
            return this;
        }

        toggle() {
            return this.media.paused || this.media.ended ? this.play() : Promise.resolve(this.pause());
        }

        mute(force) {
            this.media.muted = typeof force === 'boolean' ? force : !this.media.muted;
            return this;
        }

        async fullscreen() {
            const fullscreenElement = document.fullscreenElement || document.webkitFullscreenElement;
            if (fullscreenElement === this.root) {
                const exit = document.exitFullscreen || document.webkitExitFullscreen;
                if (exit) {
                    await exit.call(document);
                }
                return this;
            }
            const request = this.root.requestFullscreen || this.root.webkitRequestFullscreen;
            if (request) {
                await request.call(this.root);
            } else if (this.media.webkitEnterFullscreen) {
                this.media.webkitEnterFullscreen();
            }
            return this;
        }

        async pictureInPicture() {
            if (!(this.media instanceof HTMLVideoElement)) {
                return this;
            }
            if (document.pictureInPictureElement === this.media && document.exitPictureInPicture) {
                await document.exitPictureInPicture();
            } else if (document.pictureInPictureEnabled && this.media.requestPictureInPicture) {
                await this.media.requestPictureInPicture();
            } else if (this.media.webkitSetPresentationMode) {
                const mode = this.media.webkitPresentationMode === 'picture-in-picture' ? 'inline' : 'picture-in-picture';
                this.media.webkitSetPresentationMode(mode);
            }
            return this;
        }

        goLive() {
            if (!this.media.seekable || !this.media.seekable.length) {
                return this;
            }
            const edge = this.media.seekable.end(this.media.seekable.length - 1);
            if (Number.isFinite(edge)) {
                this.media.currentTime = Math.max(0, edge - 0.15);
                this.play().catch(function () {});
                this._syncLiveUi();
            }
            return this;
        }

        async reconnect(reason) {
            if (!this.info || this._destroyed) {
                return this;
            }
            this._wasPlayingBeforeReconnect = !this.media.paused || Boolean(this.options.autoplay);
            this.root.classList.add('fireplayer--reconnecting');
            this.setStatus(t('reconnecting'), 'warning');
            this._emit('reconnect', { reason: reason || 'unknown' });
            try {
                if (this.controller && typeof this.controller.reconnect === 'function') {
                    await this.controller.reconnect(reason || 'unknown');
                } else {
                    const position = this.media.currentTime;
                    this.media.load();
                    if (this.info.mode === 'vod' && Number.isFinite(position)) {
                        this.media.currentTime = position;
                    }
                }
                if (this._wasPlayingBeforeReconnect) {
                    await this.play().catch(function () {});
                }
            } catch (error) {
                this._showError(t('failed'), error);
            } finally {
                this.root.classList.remove('fireplayer--reconnecting');
            }
            return this;
        }

        setStatus(message, tone) {
            if (!this.elements || !this.elements.status) {
                return;
            }
            const text = String(message || '');
            const visible = text !== '' && (window.canViewVideoStatus !== false || tone === 'error');
            this.elements.status.textContent = visible ? text : '';
            this.elements.status.hidden = !visible;
            this.elements.status.dataset.tone = tone || 'info';
        }

        setMode(mode) {
            if (!this.info || !['live', 'event', 'vod'].includes(mode)) {
                return;
            }
            ['live', 'event', 'vod'].forEach((name) => this.root.classList.toggle('fireplayer--' + name, name === mode));
            this.info.mode = mode;
            this.root.classList.toggle('fireplayer--live', mode === 'live' || mode === 'event');
            this._syncLiveUi();
        }

        _showError(message, error) {
            this.root.classList.add('fireplayer--error');
            this.root.classList.remove('fireplayer--loading', 'fireplayer--playing');
            this.setStatus(message || t('failed'), 'error');
            this.elements.retry.hidden = false;
            this._emit('error', { error: error || this.media.error, message: message || t('failed') });
        }

        _handleMediaError() {
            if (this.root.classList.contains('fireplayer--reconnecting')) {
                return;
            }
            this._showError(t('failed'), this.media.error);
        }

        _syncPlayButtons() {
            if (!this.elements) {
                return;
            }
            const playing = !this.media.paused && !this.media.ended;
            this.elements.playButtons.forEach(function (button) {
                button.innerHTML = playing ? icons.pause : icons.play;
                button.setAttribute('aria-label', playing ? t('pause') : t('play'));
                button.setAttribute('aria-pressed', playing ? 'true' : 'false');
            });
        }

        _syncTimeline() {
            if (!this.elements) {
                return;
            }
            const duration = this.media.duration;
            const current = this.media.currentTime || 0;
            const finiteDuration = Number.isFinite(duration) && duration > 0;
            const isLive = Boolean(this.info && (this.info.mode === 'live' || this.info.mode === 'event'));
            let maximum = finiteDuration ? duration : 0;
            let minimum = 0;
            if (isLive && this.media.seekable && this.media.seekable.length) {
                minimum = this.media.seekable.start(0);
                maximum = this.media.seekable.end(this.media.seekable.length - 1);
            }
            this.elements.seek.min = String(minimum);
            this.elements.seek.max = String(Math.max(minimum, maximum));
            this.elements.seek.value = String(Math.max(minimum, Math.min(maximum || current, current)));
            const progress = maximum > minimum ? ((current - minimum) / (maximum - minimum)) * 100 : 0;
            this.elements.seek.style.setProperty('--fireplayer-progress', Math.max(0, Math.min(100, progress)) + '%');
            this.elements.current.textContent = formatTime(current, finiteDuration && duration >= 3600);
            this.elements.duration.textContent = finiteDuration ? formatTime(duration, duration >= 3600) : '';
            this.elements.duration.hidden = !finiteDuration || isLive;
            this.elements.seek.disabled = maximum <= minimum;
            this._syncLiveUi();
        }

        _syncVolume() {
            if (!this.elements) {
                return;
            }
            const muted = this.media.muted || this.media.volume === 0;
            this.elements.volume.value = String(this.media.volume);
            this.elements.volume.style.setProperty('--fireplayer-progress', (this.media.volume * 100) + '%');
            this.elements.mute.innerHTML = muted ? icons.muted : icons.volume;
            this.elements.mute.setAttribute('aria-label', muted ? t('unmute') : t('mute'));
            this.elements.mute.setAttribute('aria-pressed', muted ? 'true' : 'false');
        }

        _syncLiveUi() {
            if (!this.elements) {
                return;
            }
            const isLive = Boolean(this.info && (this.info.mode === 'live' || this.info.mode === 'event'));
            this.elements.live.hidden = !isLive;
            this.elements.liveBadge.hidden = !isLive;
            this.elements.speed.parentElement.hidden = isLive;
            if (!isLive) {
                return;
            }
            let behind = false;
            if (this.media.seekable && this.media.seekable.length) {
                const edge = this.media.seekable.end(this.media.seekable.length - 1);
                behind = Number.isFinite(edge) && edge - this.media.currentTime > Number(this.options.liveEdgeTolerance || 4);
            }
            this.elements.live.classList.toggle('is-behind', behind);
            this.elements.live.querySelector('span').textContent = behind ? t('goLive') : t('live');
        }

        _syncCapabilities() {
            if (!this.elements) {
                return;
            }
            const isVideo = this.media instanceof HTMLVideoElement;
            const pipSupported = isVideo && (Boolean(document.pictureInPictureEnabled && this.media.requestPictureInPicture) || Boolean(this.media.webkitSetPresentationMode));
            const fullscreenSupported = isVideo && Boolean(this.root.requestFullscreen || this.root.webkitRequestFullscreen || this.media.webkitEnterFullscreen);
            this.elements.pip.hidden = !pipSupported;
            this.elements.fullscreen.hidden = !fullscreenSupported;
        }

        _syncFullscreen() {
            const active = document.fullscreenElement === this.root || document.webkitFullscreenElement === this.root;
            this.root.classList.toggle('fireplayer--fullscreen', active);
            this.elements.fullscreen.innerHTML = active ? icons.exitFullscreen : icons.fullscreen;
            this.elements.fullscreen.setAttribute('aria-label', active ? t('exitFullscreen') : t('fullscreen'));
        }

        _handleKey(event) {
            if (!this.options.keyboard || event.altKey || event.ctrlKey || event.metaKey) {
                return;
            }
            if (event.target instanceof HTMLInputElement || event.target instanceof HTMLSelectElement || event.target instanceof HTMLTextAreaElement) {
                return;
            }
            const key = String(event.key || '').toLowerCase();
            if (key === ' ' || key === 'k') {
                event.preventDefault();
                this.toggle();
            } else if (key === 'm') {
                this.mute();
            } else if (key === 'f') {
                this.fullscreen();
            } else if (key === 'p') {
                this.pictureInPicture();
            } else if (key === 'l' && this.info && this.info.mode !== 'vod') {
                this.goLive();
            } else if (key === 'arrowleft' && Number.isFinite(this.media.duration)) {
                event.preventDefault();
                this.media.currentTime = Math.max(0, this.media.currentTime - 10);
            } else if (key === 'arrowright' && Number.isFinite(this.media.duration)) {
                event.preventDefault();
                this.media.currentTime = Math.min(this.media.duration, this.media.currentTime + 10);
            } else if (key === 'arrowup' || key === 'arrowdown') {
                event.preventDefault();
                this.media.volume = Math.max(0, Math.min(1, this.media.volume + (key === 'arrowup' ? 0.05 : -0.05)));
                this.media.muted = false;
            } else if (/^[0-9]$/.test(key) && Number.isFinite(this.media.duration)) {
                this.media.currentTime = this.media.duration * (Number(key) / 10);
            }
        }

        _showControls() {
            this.root.classList.add('fireplayer--controls-visible');
            if (this._controlsTimer) {
                window.clearTimeout(this._controlsTimer);
            }
            if (!this.media.paused) {
                this._controlsTimer = window.setTimeout(() => {
                    this.root.classList.remove('fireplayer--controls-visible');
                }, 2600);
            }
        }

        _positionKey() {
            return 'position.' + hash(String(this.options.src || ''));
        }

        _restoreVolume() {
            const saved = this.options.rememberVolume ? storage.get('volume', null) : null;
            this.media.volume = saved && Number.isFinite(saved.volume) ? numberValue(saved.volume, 1, 0, 1) : 1;
            this.media.muted = Boolean(this.options.muted || (saved && saved.muted));
            this._syncVolume();
        }

        _restorePosition() {
            if (!this.options.rememberPosition || !this.info || this.info.mode !== 'vod') {
                return;
            }
            const position = Number(storage.get(this._positionKey(), 0));
            if (Number.isFinite(position) && position > 3 && Number.isFinite(this.media.duration) && position < this.media.duration - 5) {
                this.media.currentTime = position;
            }
        }

        _storePosition(completed) {
            if (!this.options.rememberPosition || !this.info || this.info.mode !== 'vod') {
                return;
            }
            if (!completed && Date.now() - this._lastPositionStoreAt < 3000) {
                return;
            }
            this._lastPositionStoreAt = Date.now();
            storage.set(this._positionKey(), completed ? 0 : Math.floor(this.media.currentTime || 0));
        }

        on(eventName, callback) {
            if (typeof callback !== 'function') {
                return this;
            }
            if (!this._events.has(eventName)) {
                this._events.set(eventName, new Set());
            }
            this._events.get(eventName).add(callback);
            return this;
        }

        off(eventName, callback) {
            if (this._events.has(eventName)) {
                this._events.get(eventName).delete(callback);
            }
            return this;
        }

        _emit(eventName, detail) {
            const payload = Object.assign({ player: this }, detail && typeof detail === 'object' ? detail : { value: detail });
            if (this._events.has(eventName)) {
                this._events.get(eventName).forEach(function (callback) {
                    try { callback(payload); } catch (error) { window.setTimeout(function () { throw error; }, 0); }
                });
            }
            this.root.dispatchEvent(new CustomEvent('fireplayer:' + eventName, { detail: payload, bubbles: true }));
        }

        destroy() {
            if (this._destroyed) {
                return;
            }
            this._destroyed = true;
            ++this._loadToken;
            this._teardownPlayback();
            this._clearListeners();
            this._events.clear();
            instances.delete(this.root);
            instances.delete(this.originalElement);
            delete this.root.firePlayer;
            delete this.root.dataset.firePlayerInitialized;
            if (this.originalElement instanceof HTMLMediaElement && this.root.parentNode) {
                this.root.parentNode.replaceChild(this.originalElement, this.root);
            } else {
                this.root.innerHTML = '';
                this.root.classList.remove('fireplayer');
            }
        }

        static mount(target, options) {
            return new FirePlayer(target, options);
        }

        static detect(source, hints) {
            return detect(source, hints);
        }

        static registerAdapter(protocol, factory) {
            if (typeof protocol === 'string' && typeof factory === 'function') {
                adapters.set(protocol, factory);
            }
            return FirePlayer;
        }

        static use(extension) {
            if (extension && typeof extension.setup === 'function' && !extensions.some(function (item) { return item.name && item.name === extension.name; })) {
                extensions.push(extension);
            }
            return FirePlayer;
        }

        static bootstrap(scope) {
            const root = scope instanceof Element || scope instanceof Document ? scope : document;
            const elements = [];
            if (root instanceof Element && root.matches('.fire-player, [data-fire-player]')) {
                elements.push(root);
            }
            root.querySelectorAll('.fire-player, [data-fire-player]').forEach(function (element) { elements.push(element); });
            return elements.filter(function (element) {
                const media = element instanceof HTMLMediaElement ? element : element.querySelector('video, audio');
                return element.dataset.firePlayerManual === undefined
                    && element.dataset.firePlayerInitialized !== 'true'
                    && Boolean(parseDataset(element).src || sourceFromMedia(media));
            }).map(function (element) { return new FirePlayer(element); });
        }

        static get(element) {
            return instances.get(element) || (element && element.firePlayer) || null;
        }
    }

    FirePlayer.version = '1.0.0';
    FirePlayer.icons = icons;
    FirePlayer.labels = labels;
    FirePlayer.translate = t;
    window.FirePlayer = FirePlayer;
})(window, document);
