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
            volume: 'Громкость', seek: 'Позиция', speed: 'Скорость', settings: 'Настройки', pip: 'Картинка в картинке',
            fullscreen: 'Полный экран', exitFullscreen: 'Выйти из полного экрана', live: 'LIVE',
            goLive: 'Перейти в LIVE', detecting: 'Определяем источник…', connecting: 'Подключение…',
            loading: 'Загрузка медиа…', reconnecting: 'Повторное подключение…',
            unsupported: 'Этот формат не поддерживается браузером', failed: 'Не удалось воспроизвести медиа',
            retry: 'Повторить', audio: 'Аудиоплеер', video: 'Видеоплеер'
        },
        en: {
            play: 'Play', pause: 'Pause', mute: 'Mute', unmute: 'Unmute', volume: 'Volume',
            seek: 'Seek', speed: 'Speed', settings: 'Settings', pip: 'Picture in Picture', fullscreen: 'Fullscreen',
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
        retry: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18.4 5.6A9 9 0 1 0 21 12h-2a7 7 0 1 1-2-4.9L14 10h7V3z"/></svg>',
        settings: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19.43 12.98c.04-.32.07-.65.07-.98s-.02-.66-.07-.98l2.11-1.65a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.61-.22l-2.49 1a7.2 7.2 0 0 0-1.69-.98L14.5 2.42A.49.49 0 0 0 14.02 2h-4a.49.49 0 0 0-.49.42L9.15 5.07c-.6.25-1.17.58-1.69.98l-2.49-1a.5.5 0 0 0-.61.22l-2 3.46a.5.5 0 0 0 .12.64l2.11 1.65c-.04.32-.08.65-.08.98s.03.66.08.98l-2.11 1.65a.5.5 0 0 0-.12.64l2 3.46c.12.21.37.3.61.22l2.49-1c.52.4 1.09.73 1.69.98l.38 2.65c.04.24.24.42.49.42h4c.24 0 .45-.18.49-.42l.38-2.65c.6-.25 1.17-.58 1.69-.98l2.49 1c.23.08.49-.01.61-.22l2-3.46a.5.5 0 0 0-.12-.64l-2.11-1.65ZM12 15.5A3.5 3.5 0 1 1 12 8a3.5 3.5 0 0 1 0 7.5Z"/></svg>'
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
        assignText('preload', data.preload);
        assignText('crossorigin', data.crossorigin);
        assignText('contentType', data.contentType);
        assignBoolean('autoplay', data.autoplay, false);
        assignBoolean('muted', data.muted, false);
        assignBoolean('loop', data.loop, false);
        assignBoolean('controls', data.controls, true);
        assignBoolean('reconnect', data.reconnect, true);
        assignBoolean('lazyStart', data.lazyStart !== undefined ? data.lazyStart : data.hlsLazyStart, false);
        assignBoolean('posterCacheBust', data.posterCacheBust, false);
        if (data.rememberPosition === 'auto') {
            result.rememberPosition = 'auto';
        } else {
            assignBoolean('rememberPosition', data.rememberPosition, true);
        }
        assignBoolean('rememberVolume', data.rememberVolume, true);
        assignBoolean('probe', data.probe, true);
        assignBoolean('playsinline', data.playsinline, true);
        assignBoolean('forceHlsJs', data.forceHlsJs, false);
        ['probeTimeout', 'reconnectDelay', 'stallTimeout', 'liveEdgeTolerance', 'posterRefreshInterval', 'startupTimeout', 'maxReconnectAttempts'].forEach(function (key) {
            if (data[key] !== undefined && Number.isFinite(Number(data[key]))) {
                result[key] = Number(data[key]);
            }
        });

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
            loop: media ? media.loop : false, playsinline: true,
            preload: media ? media.getAttribute('preload') || 'metadata' : 'metadata',
            crossorigin: media ? media.getAttribute('crossorigin') || '' : '',
            contentType: media && media.querySelector('source[type]') ? media.querySelector('source[type]').type : '',
            reconnect: true, reconnectDelay: 2500, stallTimeout: 7000, liveEdgeTolerance: 4,
            startupTimeout: 30000, maxReconnectAttempts: 4,
            posterRefreshInterval: 5000, posterCacheBust: false, lazyStart: false,
            rememberPosition: 'auto', rememberVolume: true, keyboard: true, gestures: true,
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
        } else if (/#EXTINF\s*:/i.test(text)) {
            result.mode = 'live';
        }
        return result;
    };

    const fetchWithTimeout = async function (url, options, timeout, readText) {
        const controller = typeof AbortController === 'function' ? new AbortController() : null;
        const timer = controller ? window.setTimeout(function () { controller.abort(); }, timeout) : null;
        const signal = options && options.signal;
        const abort = function () { if (controller) { controller.abort(); } };
        if (signal) {
            if (signal.aborted) { abort(); }
            signal.addEventListener('abort', abort, { once: true });
        }
        try {
            const response = await window.fetch(url, Object.assign({
                credentials: 'same-origin', cache: 'no-store'
            }, options || {}, { signal: controller ? controller.signal : signal }));
            if (!readText || !response.ok) { return response; }
            // Bound probes even when a server ignores Range and returns a media file.
            let body = '';
            if (response.body && response.body.getReader) {
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let bytes = 0;
                try {
                    while (bytes < 131072) {
                        const chunk = await reader.read();
                        if (chunk.done) { break; }
                        body += decoder.decode(chunk.value.subarray(0, 131072 - bytes), { stream: true });
                        bytes += chunk.value.length;
                    }
                    body += decoder.decode();
                } finally { await reader.cancel().catch(function () {}); }
            } else {
                body = (await response.text()).slice(0, 131072);
            }
            return { ok: response.ok, url: response.url, headers: response.headers, text: async function () { return body; } };
        } finally {
            if (signal) { signal.removeEventListener('abort', abort); }
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
            contentType: options.contentType || mimeByExtension[extension] || '',
            extension: extension,
            nativeSupport: false,
            detectedBy: 'fallback'
        };
        if (options.contentType) { inferFromMime(options.contentType, result); }

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

        if (result.protocol === 'hls' && options.mode === 'auto') { result.mode = 'live'; }
        const inspectResponse = async function (response) {
            if (!response.ok) { return; }
            inferFromMime(response.headers.get('Content-Type'), result);
            const manifest = await response.text();
            inspectManifest(manifest, result);
            // A master playlist says nothing about LIVE/VOD; inspect a media playlist.
            const variant = manifest.match(/#EXT-X-STREAM-INF[^\r\n]*[\r\n]+([^#\s][^\r\n]*)/i);
            if (variant) {
                const child = await fetchWithTimeout(new URL(variant[1].trim(), response.url || new URL(src, window.location.href)).href,
                    { method: 'GET', signal: options.signal }, options.probeTimeout, true);
                if (child.ok) { inspectManifest(await child.text(), result); }
            }
            result.detectedBy = /^\s*#EXTM3U/.test(manifest) ? 'manifest' : 'content-type';
        };
        if (options.probe !== false && typeof window.fetch === 'function' && (result.protocol === 'hls' || !mimeByExtension[extension])) {
            try {
                if (result.protocol === 'hls') {
                    await inspectResponse(await fetchWithTimeout(src, { method: 'GET', signal: options.signal }, options.probeTimeout, true));
                } else {
                    const response = await fetchWithTimeout(src, { method: 'HEAD', signal: options.signal }, options.probeTimeout);
                    if (response.ok) { inferFromMime(response.headers.get('Content-Type'), result); }
                    result.detectedBy = response.ok ? 'content-type' : result.detectedBy;
                    if (result.protocol === 'hls' || !response.ok || !/^(?:audio|video)\//.test(result.contentType)) {
                        await inspectResponse(await fetchWithTimeout(src, { method: 'GET', headers: { Range: 'bytes=0-131071' }, signal: options.signal }, options.probeTimeout, true));
                    }
                }
            } catch (error) {
                result.probeError = error && error.message ? error.message : String(error);
            }
        }

        if (options.media === 'audio' || options.media === 'video') {
            result.media = options.media;
        }
        if (options.protocol !== 'auto') { result.protocol = options.protocol; }
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
            this._originalTracks = this.originalMedia ? Array.from(this.originalMedia.querySelectorAll('track')).map(function (track) { return track.cloneNode(true); }) : [];
            if (this.originalMedia) { this.originalMedia.pause(); }
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
            this._playRequested = false;
            this._playPromise = null;
            this._playAttemptId = 0;
            this._recoveringMedia = false;
            this._sourcePrepared = false;
            this._loadAbortController = null;
            this._reconnectPromise = null;
            this._reconnectAttempts = 0;
            this._loadingTimer = null;
            this._startupTimer = null;
            this._lastPlaybackTime = 0;
            this._restoredPosition = false;
            this._render(this.options.media === 'audio' ? 'audio' : 'video');

            instances.set(this.root, this);
            instances.set(element, this);
            this.root.firePlayer = this;
            this.root.dataset.firePlayerInitialized = 'true';

            this._emit('init');
            this.ready = this.options.src ? this.load(this.options.src) : Promise.resolve(this);
            this.ready.catch(function () {});
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
                '<div class="fireplayer__settings"><button class="fireplayer__button" type="button" data-fp-action="settings" aria-label="' + t('settings') + '" aria-expanded="false" aria-haspopup="true">' + icons.settings + '</button>' +
                    '<div class="fireplayer__settings-menu" data-fp-settings-menu hidden><label class="fireplayer__speed-wrap"><span class="fireplayer__settings-label">' + t('speed') + '</span><select class="fireplayer__speed" data-fp-speed aria-label="' + t('speed') + '"><option value="0.5">0.5×</option><option value="0.75">0.75×</option><option value="1" selected>1×</option><option value="1.25">1.25×</option><option value="1.5">1.5×</option><option value="2">2×</option></select></label></div></div>' +
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
                pip: controls.querySelector('[data-fp-action="pip"]'),
                fullscreen: controls.querySelector('[data-fp-action="fullscreen"]'),
                settings: controls.querySelector('[data-fp-action="settings"]'),
                settingsMenu: controls.querySelector('[data-fp-settings-menu]')
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
                player._listen(button, 'click', function () { player.toggle().catch(function () {}); });
            });
            this._listen(this.elements.retry, 'click', function () {
                player.retry().catch(function () {});
            });
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
                player._storeVolume();
            });
            this._listen(this.elements.speed, 'change', function () {
                player.media.playbackRate = numberValue(player.elements.speed.value, 1, 0.25, 4);
            });
            this._listen(this.elements.settings, 'click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                player._setSettingsOpen(player.elements.settingsMenu.hidden);
                player._showControls();
            });
            this._listen(this.elements.settingsMenu, 'click', function (event) { event.stopPropagation(); });
            this._listen(document, 'pointerdown', function (event) {
                if (!player.root.contains(event.target)) {
                    player._setSettingsOpen(false);
                }
            });
            this._listen(this.elements.live, 'click', function () { player.goLive(); });
            this._listen(this.elements.pip, 'click', function () { player.pictureInPicture().catch(function (error) { player._emit('controlerror', { error: error }); }); });
            this._listen(this.elements.fullscreen, 'click', function () { player.fullscreen().catch(function (error) { player._emit('controlerror', { error: error }); }); });

            this._listen(this.media, 'play', function () {
                if (player._destroyed || player.media.paused) { return; }
                const wasPlaying = player.root.classList.contains('fireplayer--playing');
                player.root.classList.add('fireplayer--playing');
                player.root.classList.remove('fireplayer--ended', 'fireplayer--error');
                if (!wasPlaying) { player._showControls(); }
                player._syncPlayButtons();
                player._emit('play');
            });
            this._listen(this.media, 'pause', function () {
                if (!player.media.paused) { return; }
                player.root.classList.remove('fireplayer--playing');
                if (player._sourcePrepared && !player._reconnectPromise && !player._recoveringMedia && !player.media.error) { player._playRequested = false; }
                if (!player.media.error && !player._recoveringMedia) { player._settleLoading(); }
                player._syncPlayButtons();
                player._storePosition(false, true);
                player._emit('pause');
            });
            this._listen(this.media, 'playing', function () {
                if (player._destroyed || player.media.paused) { return; }
                player._recoveringMedia = false;
                player.root.classList.add('fireplayer--playing');
                player._playRequested = true;
                player._settleLoading(true);
                player._emit('playing');
            });
            ['waiting', 'stalled'].forEach(function (eventName) {
                player._listen(player.media, eventName, function () {
                    player._queueLoading();
                });
            });
            ['loadedmetadata', 'loadeddata', 'canplay', 'canplaythrough'].forEach(function (eventName) {
                player._listen(player.media, eventName, function () {
                    if (!player.media.error && !player._recoveringMedia && (player.media.readyState >= 3 || (player.media.paused && player.media.readyState >= 1))) {
                        player._settleLoading();
                    }
                    player._syncTimeline();
                    if (eventName === 'canplay') {
                        player._recoveringMedia = false;
                        if (!player._playRequested && !player.media.error) { player._settleLoading(); }
                    }
                    if (eventName === 'canplay' && player._sourcePrepared && player._playRequested && player.media.paused && !player._playPromise) {
                        player._playMedia(player._loadToken).catch(function () {});
                    }
                });
            });
            ['durationchange', 'timeupdate', 'progress'].forEach(function (eventName) {
                player._listen(player.media, eventName, function () { player._syncTimeline(); });
            });
            this._listen(this.media, 'loadedmetadata', function () {
                player._restorePosition();
                player._emit('loadedmetadata', { duration: player.media.duration });
            });
            this._listen(this.media, 'volumechange', function () {
                player._syncVolume();
            });
            this._listen(this.media, 'ratechange', function () {
                player.elements.speed.value = String(player.media.playbackRate);
            });
            this._listen(this.media, 'ended', function () {
                player._playRequested = false;
                player.root.classList.remove('fireplayer--playing');
                player.root.classList.add('fireplayer--ended');
                player._settleLoading();
                player._syncPlayButtons();
                player._storePosition(true);
                player._emit('ended');
            });
            this._listen(this.media, 'error', function () { player._handleMediaError(); });
            this._listen(this.media, 'timeupdate', function () {
                const current = player.media.currentTime;
                if (!player.media.error && !player.media.paused && !player.media.seeking && current > player._lastPlaybackTime + 0.02) {
                    player._settleLoading(true);
                }
                player._lastPlaybackTime = current;
                player._storePosition(false);
            });
            this._listen(document, 'fullscreenchange', function () { player._syncFullscreen(); });
            this._listen(document, 'webkitfullscreenchange', function () { player._syncFullscreen(); });
            this._listen(this.root, 'keydown', function (event) { player._handleKey(event); });
            this._listen(this.root, 'pointerdown', function (event) {
                player.root.classList.toggle('fireplayer--touch', event.pointerType === 'touch');
                player.root.classList.remove('fireplayer--keyboard-focus');
                if (event.pointerType === 'touch') { player._showControls(); }
            });
            ['pointerenter', 'pointermove'].forEach(function (eventName) {
                player._listen(player.root, eventName, function (event) {
                    if (event.pointerType !== 'touch') { player.root.classList.remove('fireplayer--touch'); }
                }, { passive: true });
            });
            this._listen(this.root, 'pointerleave', function (event) {
                if (event.pointerType === 'touch') { return; }
                player._hideControls();
                if (!player.root.classList.contains('fireplayer--keyboard-focus')) { player._setSettingsOpen(false); }
            });
            this._listen(document, 'keydown', function (event) {
                if (event.key === 'Tab') { player._keyboardInput = true; }
            });
            this._listen(document, 'pointerdown', function () { player._keyboardInput = false; });
            this._listen(this.root, 'focusin', function () {
                player.root.classList.toggle('fireplayer--keyboard-focus', Boolean(player._keyboardInput));
            });
            this._listen(this.root, 'focusout', function (event) {
                if (!player.root.contains(event.relatedTarget)) {
                    player.root.classList.remove('fireplayer--keyboard-focus');
                    player._setSettingsOpen(false);
                }
            });

            this._restoreVolume();
            this._syncPlayButtons();
            this._syncTimeline();
            this._syncCapabilities();
        }

        async load(source, overrides) {
            const pending = this._loadSource(source, overrides);
            this.ready = pending;
            pending.catch(function () {});
            return pending;
        }

        async _loadSource(source, overrides) {
            if (this._destroyed) {
                throw new Error('FirePlayer instance was destroyed.');
            }
            const src = String(source || '').trim();
            if (!src) {
                throw new Error('FirePlayer requires a media source.');
            }
            const token = ++this._loadToken;
            this._storePosition(false, true);
            this._teardownPlayback();
            this.options = Object.assign({}, this.options, overrides || {}, { src: src });
            this._loadAbortController = typeof AbortController === 'function' ? new AbortController() : null;
            this._playRequested = Boolean(this.options.autoplay);
            this._reconnectAttempts = 0;
            this._restoredPosition = false;
            this._resumePosition = null;
            this._lastPlaybackTime = 0;
            this.info = null;
            this.root.classList.remove('fireplayer--ready', 'fireplayer--ended', 'fireplayer--playing', 'fireplayer--reconnecting', 'fireplayer--error', 'fireplayer--live', 'fireplayer--event', 'fireplayer--vod');
            this.root.classList.add('fireplayer--loading');
            this.elements.retry.hidden = true;
            this.setStatus(t('detecting'), 'info');
            this._emit('loadstart');

            // Known camera endpoints are prepared by the backend/HLS adapter, not a duplicate probe.
            const cameraSource = /\/stream-[^/]+\/index\.m3u8(?:[?#].*)?$/i.test(src);
            const info = await detect(src, Object.assign({}, this.options, {
                probe: cameraSource ? false : this.options.probe,
                signal: this._loadAbortController ? this._loadAbortController.signal : undefined
            }));
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
            this.media.autoplay = false;
            this._restoreVolume();
            this.media.loop = Boolean(this.options.loop);
            if (info.mode === 'live' || info.mode === 'event') { this.media.playbackRate = 1; }
            this.media.preload = this.options.preload || 'metadata';
            if (this.options.crossorigin) {
                this.media.crossOrigin = this.options.crossorigin;
            } else {
                this.media.removeAttribute('crossorigin');
            }
            if (this.media instanceof HTMLVideoElement) {
                this.media.poster = this.options.poster || '';
                this._originalTracks.forEach((track) => this.media.appendChild(track.cloneNode(true)));
            }
            this.elements.controls.hidden = this.options.controls === false;
            this.root.setAttribute('aria-label', this.options.title || t(info.media));
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
                    if (token !== this._loadToken || this._destroyed) {
                        if (typeof cleanup === 'function') { cleanup(); }
                        return this;
                    }
                    if (typeof cleanup === 'function') {
                        this._cleanups.push(cleanup);
                    }
                }
            }
            if (token !== this._loadToken || this._destroyed) {
                return this;
            }

            this.root.classList.add('fireplayer--ready');
            this._sourcePrepared = true;
            if (!this._playRequested && !this.media.error) { this._settleLoading(); }
            this._syncCapabilities();
            this._emit('ready', info);
            if (info.mode === 'live' || info.mode === 'event') {
                this._emit('live', info);
            }
            if (this._playRequested) { this._playMedia(token).catch(function () {}); }
            return this;
        }

        unload() {
            ++this._loadToken;
            this._teardownPlayback();
            this.info = null;
            this.root.classList.remove('fireplayer--ready', 'fireplayer--playing', 'fireplayer--loading', 'fireplayer--reconnecting', 'fireplayer--error', 'fireplayer--ended', 'fireplayer--live', 'fireplayer--event', 'fireplayer--vod');
            this.elements.retry.hidden = true;
            this._setSettingsOpen(false);
            this._syncTimeline();
            this.setStatus('');
            this._emit('unload');
            return this;
        }

        _teardownPlayback() {
            this._sourcePrepared = false;
            this._playRequested = false;
            this._playPromise = null;
            ++this._playAttemptId;
            this._recoveringMedia = false;
            this._reconnectPromise = null;
            if (this._loadAbortController) { this._loadAbortController.abort(); }
            this._loadAbortController = null;
            this._clearLoadingTimers();
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
            if (this._destroyed) { throw new Error('FirePlayer instance was destroyed.'); }
            if (!this.options.src) {
                throw new Error('FirePlayer has no source.');
            }
            const token = this._loadToken;
            this._playRequested = true;
            if (!this._sourcePrepared) { await this.ready; }
            if (token !== this._loadToken || this._destroyed || !this._playRequested) { return this; }
            await this._playMedia(token);
            return this;
        }

        _playMedia(token) {
            if (this._playPromise) { return this._playPromise; }
            const media = this.media;
            const attemptId = ++this._playAttemptId;
            this._armStartupTimeout(token);
            this._queueLoading();
            // Call play synchronously when prepared so a click retains browser activation.
            let attempt;
            try { attempt = media.play(); } catch (error) { attempt = Promise.reject(error); }
            const pending = Promise.resolve(attempt).then(() => {
                if (token !== this._loadToken || attemptId !== this._playAttemptId || this._destroyed || media !== this.media) { return this; }
                if (!media.paused && !media.ended) {
                    this.root.classList.add('fireplayer--playing');
                    this._settleLoading(true);
                }
                this._syncPlayButtons();
                return this;
            }).catch((error) => {
                if (token !== this._loadToken || attemptId !== this._playAttemptId || this._destroyed || error.name === 'AbortError') { return this; }
                if (error.name === 'NotAllowedError') {
                    this._playRequested = false;
                    this._settleLoading();
                    this._emit('autoplayblocked', { error: error });
                } else { this._showError(t('failed'), error); }
                throw error;
            }).finally(() => {
                if (this._playPromise === pending) { this._playPromise = null; }
            });
            this._playPromise = pending;
            return pending;
        }

        pause() {
            this._playRequested = false;
            this._recoveringMedia = false;
            ++this._playAttemptId;
            this._playPromise = null;
            this.media.pause();
            this._settleLoading();
            return this;
        }

        _recoverMedia(recover) {
            // Decoder recovery detaches the same source. Its native pause is not a user pause.
            this._recoveringMedia = true;
            ++this._playAttemptId;
            this._playPromise = null;
            if (this._playRequested) {
                this.root.classList.add('fireplayer--reconnecting');
                this.setStatus(t('reconnecting'), 'warning');
                this._armStartupTimeout(this._loadToken);
            }
            recover();
        }

        toggle() {
            if (this.root.classList.contains('fireplayer--error')) { return this.retry(); }
            return this._playRequested || !this.media.paused ? Promise.resolve(this.pause()) : this.play();
        }

        mute(force) {
            this.media.muted = typeof force === 'boolean' ? force : !this.media.muted;
            if (!this.media.muted && this.media.volume === 0) { this.media.volume = 1; }
            this._storeVolume();
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
                const start = this.media.seekable.start(this.media.seekable.length - 1);
                const sync = this.controller ? this.controller.liveSyncPosition : NaN;
                const target = Number.isFinite(sync) ? sync : edge - Math.min(3, (edge - start) / 2);
                this.media.currentTime = Math.max(start, Math.min(edge - 0.1, target));
                this.play().catch(function () {});
                this._syncLiveUi();
            }
            return this;
        }

        async reconnect(reason) {
            if (!this.info || this._destroyed) {
                return this;
            }
            if (this._reconnectPromise) { return this._reconnectPromise; }
            const token = this._loadToken;
            if (reason === 'manual') { this._reconnectAttempts = 0; this._playRequested = true; }
            ++this._playAttemptId;
            this._playPromise = null;
            this._recoveringMedia = false;
            if (++this._reconnectAttempts > Number(this.options.maxReconnectAttempts || 4)) {
                this._showError(t('failed'), new Error('Reconnect attempts exhausted.'));
                return this;
            }
            if (this.info.mode === 'vod' && Number.isFinite(this.media.currentTime)) { this._resumePosition = this.media.currentTime; }
            this.root.classList.remove('fireplayer--error');
            this.elements.retry.hidden = true;
            this.root.classList.add('fireplayer--reconnecting');
            this.setStatus(t('reconnecting'), 'warning');
            this._emit('reconnect', { reason: reason || 'unknown' });
            const pending = Promise.resolve().then(async () => { try {
                if (this.controller && typeof this.controller.reconnect === 'function') {
                    await this.controller.reconnect(reason || 'unknown');
                } else {
                    const position = this.media.currentTime;
                    this.media.load();
                    if (this.info.mode === 'vod' && Number.isFinite(position)) {
                        this.media.currentTime = position;
                    }
                }
                if (token !== this._loadToken || this._destroyed) { return this; }
                if (this._playRequested) {
                    this._playPromise = null;
                    this._playMedia(token).catch(function () {});
                } else {
                    this._settleLoading();
                }
            } catch (error) {
                if (token === this._loadToken && !this._destroyed && error.name !== 'AbortError') { this._showError(t('failed'), error); }
            } finally {
                if (this._reconnectPromise === pending) { this._reconnectPromise = null; }
            } return this; });
            this._reconnectPromise = pending;
            return pending;
        }

        async retry() {
            if (this.controller && typeof this.controller.reconnect === 'function') {
                return this.reconnect('manual');
            }
            const pending = this.load(this.options.src);
            const token = this._loadToken;
            this._playRequested = true;
            await pending;
            if (token === this._loadToken && this._playRequested && !this._destroyed) { await this._playMedia(token); }
            return this;
        }

        _clearLoadingTimers() {
            window.clearTimeout(this._loadingTimer);
            window.clearTimeout(this._startupTimer);
            this._loadingTimer = null;
            this._startupTimer = null;
        }

        _settleLoading(recovered) {
            if (this.root.classList.contains('fireplayer--error') && !recovered) { return; }
            this._clearLoadingTimers();
            this.root.classList.remove('fireplayer--loading', 'fireplayer--reconnecting');
            if (recovered) {
                this.root.classList.remove('fireplayer--error');
                this._reconnectAttempts = 0;
            }
            this.elements.retry.hidden = true;
            if (!this.elements.status.hidden || this.elements.status.textContent) { this.setStatus(''); }
        }

        _queueLoading() {
            if (this._loadingTimer || this.media.paused || this.media.readyState >= 3 || this.media.error) { return; }
            const token = this._loadToken;
            this._loadingTimer = window.setTimeout(() => {
                this._loadingTimer = null;
                if (token === this._loadToken && !this._destroyed && !this.media.paused && this.media.readyState < 3 && !this.media.error) {
                    this.root.classList.add('fireplayer--loading');
                    this.setStatus(t('loading'), 'info');
                }
            }, 220);
        }

        _armStartupTimeout(token) {
            window.clearTimeout(this._startupTimer);
            this._startupTimer = window.setTimeout(() => {
                this._startupTimer = null;
                if (token !== this._loadToken || this._destroyed || !this._playRequested) { return; }
                this._showError(t('failed'), new Error('Playback did not start before the timeout.'));
            }, numberValue(this.options.startupTimeout, 30000, 1000, 120000));
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

        _setSettingsOpen(open) {
            if (!this.elements || !this.elements.settings || !this.elements.settingsMenu) {
                return;
            }
            const visible = Boolean(open) && !this.elements.settings.hidden;
            this.elements.settingsMenu.hidden = !visible;
            this.elements.settings.setAttribute('aria-expanded', visible ? 'true' : 'false');
            this.root.classList.toggle('fireplayer--settings-open', visible);
        }

        setMode(mode) {
            if (!this.info || !['live', 'event', 'vod'].includes(mode)) {
                return;
            }
            const wasLive = this.info.mode === 'live' || this.info.mode === 'event';
            if (!wasLive && (mode === 'live' || mode === 'event')) { this.media.playbackRate = 1; }
            ['live', 'event', 'vod'].forEach((name) => this.root.classList.toggle('fireplayer--' + name, name === mode));
            this.info.mode = mode;
            this.root.classList.toggle('fireplayer--live', mode === 'live' || mode === 'event');
            this._syncLiveUi();
        }

        _showError(message, error) {
            if (this._destroyed) { return; }
            this._recoveringMedia = false;
            this._clearLoadingTimers();
            this.root.classList.add('fireplayer--error');
            this.root.classList.remove('fireplayer--loading', 'fireplayer--playing', 'fireplayer--reconnecting');
            this.setStatus(message || t('failed'), 'error');
            this.elements.retry.hidden = false;
            this._emit('error', { error: error || this.media.error, message: message || t('failed') });
        }

        _handleMediaError() {
            if (this._destroyed || !this.info || !this.media.error || this.media.error.code === 1) { return; }
            if (this.root.classList.contains('fireplayer--reconnecting') || this.root.classList.contains('fireplayer--error')) {
                return;
            }
            // hls.js owns media recovery; a second recovery here races its ERROR handler.
            if (this.controller && this.controller.engine === 'hls.js') { return; }
            if (this.info && this.info.protocol === 'hls' && this.options.reconnect && this.controller) {
                this.reconnect('media').catch(function () {});
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
            this.elements.speed.parentElement.hidden = isLive;
            const hasSettings = Array.from(this.elements.settingsMenu.children).some(function (element) { return !element.hidden; });
            this.elements.settings.hidden = !hasSettings;
            this.elements.settings.parentElement.hidden = !hasSettings;
            if (this.elements.settings.hidden) {
                this._setSettingsOpen(false);
            }
            if (!isLive) {
                return;
            }
            let behind = false;
            if (this.media.seekable && this.media.seekable.length) {
                const sync = this.controller ? this.controller.liveSyncPosition : NaN;
                const edge = Number.isFinite(sync) ? sync : this.media.seekable.end(this.media.seekable.length - 1) - 3;
                behind = Number.isFinite(edge) && edge - this.media.currentTime > Number(this.options.liveEdgeTolerance || 4);
            }
            this.elements.live.classList.toggle('is-behind', behind);
            // Keep the button footprint stable; the full action remains available to assistive tech.
            this.elements.live.querySelector('span').textContent = t('live');
            this.elements.live.setAttribute('aria-label', behind ? t('goLive') : t('live'));
            this.elements.live.setAttribute('title', behind ? t('goLive') : t('live'));
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
            this._syncLiveUi();
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
            if (String(event.key || '').toLowerCase() === 'escape') {
                this._setSettingsOpen(false);
                return;
            }
            this._keyboardInput = true;
            this.root.classList.add('fireplayer--keyboard-focus');
            if (event.target instanceof HTMLInputElement || event.target instanceof HTMLSelectElement || event.target instanceof HTMLTextAreaElement) {
                return;
            }
            const key = String(event.key || '').toLowerCase();
            if ((key === ' ' || key === 'enter') && event.target.closest('button, a')) { return; }
            if (key === ' ' || key === 'k') {
                event.preventDefault();
                this.toggle().catch(function () {});
            } else if (key === 'm') {
                this.mute();
            } else if (key === 'f') {
                this.fullscreen().catch(function () {});
            } else if (key === 'p') {
                this.pictureInPicture().catch(function () {});
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
                this._storeVolume();
            } else if (/^[0-9]$/.test(key) && Number.isFinite(this.media.duration)) {
                this.media.currentTime = this.media.duration * (Number(key) / 10);
            }
        }

        _showControls() {
            if (!this.root.classList.contains('fireplayer--touch')) { return; }
            this.root.classList.add('fireplayer--controls-visible');
            if (this._controlsTimer) {
                window.clearTimeout(this._controlsTimer);
            }
            if (!this.media.paused && !this.root.classList.contains('fireplayer--settings-open')) {
                this._controlsTimer = window.setTimeout(() => {
                    this._hideControls();
                }, 2600);
            }
        }

        _hideControls() {
            window.clearTimeout(this._controlsTimer);
            this._controlsTimer = null;
            this.root.classList.remove('fireplayer--controls-visible');
        }

        _positionKey() {
            return 'position.' + hash(String(this.options.src || ''));
        }

        _restoreVolume() {
            const saved = this.options.rememberVolume ? storage.get('volume', null) : null;
            // Older records also captured forced autoplay mute. Only explicit user choices
            // in the new format may silence another player; retain legacy positive volume.
            const userPreference = Boolean(saved && saved.version === 2);
            const validVolume = saved && Number.isFinite(saved.volume) && (saved.volume > 0 || userPreference);
            this.media.volume = validVolume ? numberValue(saved.volume, 1, 0, 1) : 1;
            this.media.muted = Boolean(this.options.muted || (userPreference && saved.muted));
            this._syncVolume();
        }

        _storeVolume() {
            if (this.options.rememberVolume) {
                storage.set('volume', { version: 2, volume: this.media.volume, muted: this.media.muted });
            }
        }

        _rememberPositionEnabled() {
            return this.options.rememberPosition === true
                || (this.options.rememberPosition === 'auto' && this.info && this.info.media !== 'audio');
        }

        _restorePosition() {
            if (Number.isFinite(this._resumePosition) && this.info && this.info.mode === 'vod') {
                const position = this._resumePosition;
                this._resumePosition = null;
                if (Number.isFinite(this.media.duration) && position < this.media.duration) { this.media.currentTime = position; }
                return;
            }
            if (this._restoredPosition) { return; }
            this._restoredPosition = true;
            if (!this._rememberPositionEnabled() || !this.info || this.info.mode !== 'vod') {
                return;
            }
            const position = Number(storage.get(this._positionKey(), 0));
            if (Number.isFinite(position) && position > 3 && Number.isFinite(this.media.duration) && position < this.media.duration - 5) {
                this.media.currentTime = position;
            }
        }

        _storePosition(completed, force) {
            if (!this._rememberPositionEnabled() || !this.info || this.info.mode !== 'vod' || this.media.readyState < 1 || !this._restoredPosition) {
                return;
            }
            if (!completed && !force && Date.now() - this._lastPositionStoreAt < 3000) {
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
            this._storePosition(false, true);
            ++this._loadToken;
            this._teardownPlayback();
            this._clearListeners();
            this._emit('destroy');
            this._events.clear();
            instances.delete(this.root);
            instances.delete(this.originalElement);
            delete this.root.firePlayer;
            delete this.root.dataset.firePlayerInitialized;
            if (this.originalElement instanceof HTMLMediaElement && this.root.parentNode) {
                this.root.parentNode.replaceChild(this.originalElement, this.root);
            } else {
                this.root.innerHTML = '';
                this.root.className = this.root.className.split(/\s+/).filter(function (name) { return name !== 'fireplayer' && !name.startsWith('fireplayer--'); }).join(' ');
                this.root.removeAttribute('tabindex');
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

    FirePlayer.version = '1.0.3';
    FirePlayer.icons = icons;
    FirePlayer.labels = labels;
    FirePlayer.translate = t;
    window.FirePlayer = FirePlayer;
})(window, document);
