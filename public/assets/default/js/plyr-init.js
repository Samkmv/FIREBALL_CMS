(function () {
    const plyrAssetBase = (typeof baseUrl === 'string' ? baseUrl : '') + '/assets/default/vendor/plyr';
    const hlsAssetBase = (typeof baseUrl === 'string' ? baseUrl : '') + '/assets/default/vendor/hls.js';
    const plyrBlankVideoUrl = plyrAssetBase + '/blank.mp4';
    const hlsScriptUrl = hlsAssetBase + '/hls.min.js';
    const hlsStartupMaxAttempts = 15;
    const hlsRetryDelayMs = 1000;
    const hlsReconnectDelayMs = 5000;
    const hlsPlayRetryDurationMs = 10000;
    const hlsPlayRetryDelayMs = 1000;
    const hlsHealthCheckIntervalMs = 2000;
    const hlsStuckResetAfterMs = 5000;
    const hlsStartupGraceMs = 30000;
    const hlsRestartCooldownMs = 6000;
    const hlsPrewarmTtlMs = 45000;
    const hlsStartTimeEpsilon = 0.08;
    const hlsStatusHideDelayMs = 1400;
    const hlsPosterRefreshIntervalMs = 5000;
    const canViewVideoStatus = window.canViewVideoDiagnostics === true || window.canViewVideoStatus === true;
    let hlsScriptPromise = null;
    const hlsLocale = ((document.documentElement.getAttribute('lang') || 'en').toLowerCase().startsWith('ru')) ? 'ru' : 'en';
    const hlsMessages = {
        ru: {
            holding_session: 'Подключение...',
            preparing_native: 'Подключение...',
            stream_playing: 'Загрузка видео...',
            checking_attempt: 'Подключение...',
            manifest_available: 'Загрузка видео...',
            stream_responded: 'Загрузка видео...',
            stream_sleeping: 'Подключение...',
            playback_pending: 'Загрузка видео...',
            native_metadata: 'Загрузка видео...',
            native_first_data: 'Загрузка видео...',
            native_ready: 'Загрузка видео...',
            native_error: 'Не удалось воспроизвести видео',
            native_stalled: 'Повторное подключение...',
            native_fallback: 'Повторное подключение...',
            stream_error_load: 'Повторное подключение...',
            stream_restarting: 'Повторное подключение...',
            media_recover: 'Повторное подключение...',
            stream_error_play: 'Не удалось воспроизвести видео',
            loading_manifest: 'Загрузка видео...',
            media_attached: 'Загрузка видео...',
            manifest_loaded: 'Загрузка видео...',
            loading_segments: 'Загрузка видео...',
            stream_buffered: 'Загрузка видео...',
            primed_waiting: 'Загрузка видео...',
            metadata_loaded: 'Загрузка видео...',
            first_video_data: 'Загрузка видео...',
            stream_ready: 'Загрузка видео...',
            attaching_hls: 'Подключение...',
            hls_unavailable: 'Не удалось воспроизвести видео',
            first_play: 'Подключение...',
            native_available: 'Загрузка видео...',
            source_timeout: 'Источник временно недоступен',
            retry_manual: 'Нажмите, чтобы попробовать снова',
            poster_fallback: 'Источник временно недоступен',
            stream_initialized: 'Загрузка видео...'
        },
        en: {
            holding_session: 'Connecting...',
            preparing_native: 'Connecting...',
            stream_playing: 'Loading video...',
            checking_attempt: 'Connecting...',
            manifest_available: 'Loading video...',
            stream_responded: 'Loading video...',
            stream_sleeping: 'Connecting...',
            playback_pending: 'Loading video...',
            native_metadata: 'Loading video...',
            native_first_data: 'Loading video...',
            native_ready: 'Loading video...',
            native_error: 'Unable to play video',
            native_stalled: 'Reconnecting...',
            native_fallback: 'Reconnecting...',
            stream_error_load: 'Reconnecting...',
            stream_restarting: 'Reconnecting...',
            media_recover: 'Reconnecting...',
            stream_error_play: 'Unable to play video',
            loading_manifest: 'Loading video...',
            media_attached: 'Loading video...',
            manifest_loaded: 'Loading video...',
            loading_segments: 'Loading video...',
            stream_buffered: 'Loading video...',
            primed_waiting: 'Loading video...',
            metadata_loaded: 'Loading video...',
            first_video_data: 'Loading video...',
            stream_ready: 'Loading video...',
            attaching_hls: 'Connecting...',
            hls_unavailable: 'Unable to play video',
            first_play: 'Connecting...',
            native_available: 'Loading video...',
            source_timeout: 'Source is temporarily unavailable',
            retry_manual: 'Click to try again',
            poster_fallback: 'Source is temporarily unavailable',
            stream_initialized: 'Loading video...'
        }
    };

    const t = function (key, replacements) {
        const messages = hlsMessages[hlsLocale] || hlsMessages.en;
        let message = messages[key] || hlsMessages.en[key] || key;

        Object.entries(replacements || {}).forEach(function ([name, value]) {
            message = message.replace(new RegExp('\\{' + name + '\\}', 'g'), String(value));
        });

        return message;
    };

    const loadHlsScript = function () {
        if (typeof window.Hls === 'function') {
            return Promise.resolve(window.Hls);
        }

        if (hlsScriptPromise) {
            return hlsScriptPromise;
        }

        hlsScriptPromise = new Promise(function (resolve, reject) {
            const existing = document.querySelector('script[data-hls-js-loader="true"]');
            if (existing) {
                if (existing.dataset.hlsJsLoaded === 'true') {
                    reject(new Error('Hls.js loaded but window.Hls is unavailable'));
                    return;
                }

                if (existing.dataset.hlsJsFailed === 'true') {
                    reject(new Error('Failed to load Hls.js'));
                    return;
                }

                existing.addEventListener('load', function () {
                    if (typeof window.Hls === 'function') {
                        resolve(window.Hls);
                        return;
                    }

                    reject(new Error('Hls.js loaded but window.Hls is unavailable'));
                }, { once: true });
                existing.addEventListener('error', function () {
                    reject(new Error('Failed to load Hls.js'));
                }, { once: true });
                return;
            }

            const script = document.createElement('script');
            script.src = hlsScriptUrl;
            script.async = true;
            script.dataset.hlsJsLoader = 'true';

            script.addEventListener('load', function () {
                if (typeof window.Hls === 'function') {
                    script.dataset.hlsJsLoaded = 'true';
                    resolve(window.Hls);
                    return;
                }

                script.dataset.hlsJsFailed = 'true';
                reject(new Error('Hls.js loaded but window.Hls is unavailable'));
            }, { once: true });

            script.addEventListener('error', function () {
                script.dataset.hlsJsFailed = 'true';
                reject(new Error('Failed to load Hls.js'));
            }, { once: true });

            document.head.appendChild(script);
        });

        return hlsScriptPromise;
    };

    const getHlsSource = function (element) {
        if (!(element instanceof HTMLMediaElement)) {
            return '';
        }

        const directSource = element.dataset.hlsSrc || element.dataset.src || element.getAttribute('src') || '';
        if (/\.m3u8(?:$|\?)/i.test(directSource)) {
            return directSource;
        }

        const sourceNodes = element.querySelectorAll('source');
        for (let index = 0; index < sourceNodes.length; index += 1) {
            const sourceNode = sourceNodes[index];
            const sourceType = (sourceNode.getAttribute('type') || '').toLowerCase();
            const sourceSrc = sourceNode.getAttribute('src') || '';

            if (sourceType === 'application/vnd.apple.mpegurl' || /\.m3u8(?:$|\?)/i.test(sourceSrc)) {
                return sourceSrc;
            }
        }

        return '';
    };

    const canPlayHlsNatively = function (element) {
        if (!(element instanceof HTMLVideoElement)) {
            return false;
        }

        const mimeChecks = [
            'application/vnd.apple.mpegurl',
            'application/x-mpegURL',
        ];

        return mimeChecks.some(function (mimeType) {
            const result = element.canPlayType(mimeType);
            return result === 'probably' || result === 'maybe';
        });
    };

    const canUseHlsJsPlayback = function () {
        const hasMediaSource = typeof window.MediaSource !== 'undefined'
            && typeof window.MediaSource.isTypeSupported === 'function';
        if (!hasMediaSource) {
            return false;
        }

        if (typeof window.Hls === 'function' && typeof window.Hls.isSupported === 'function') {
            return window.Hls.isSupported();
        }

        return true;
    };

    const isSafariBrowser = function () {
        const userAgent = window.navigator.userAgent || '';
        const vendor = window.navigator.vendor || '';
        return /Safari/i.test(userAgent)
            && /Apple/i.test(vendor)
            && !/Chrome|CriOS|Chromium|Edg|OPR|YaBrowser|FxiOS|DuckDuckGo/i.test(userAgent);
    };

    const isIosLikeBrowser = function () {
        const userAgent = window.navigator.userAgent || '';
        const isIosLike = /iPad|iPhone|iPod/i.test(userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

        return isIosLike;
    };

    const isDesktopSafariBrowser = function () {
        return isSafariBrowser() && !isIosLikeBrowser();
    };

    if (isDesktopSafariBrowser()) {
        document.documentElement.classList.add('is-desktop-safari');
    }

    const shouldUseNativeHls = function (element) {
        if (!(element instanceof HTMLVideoElement)) {
            return false;
        }

        if (isSafariBrowser()) {
            return true;
        }

        if (element && element.dataset && element.dataset.hlsForceJsPlayback === 'true') {
            return false;
        }

        return isIosLikeBrowser() && canPlayHlsNatively(element) && !canUseHlsJsPlayback();
    };

    const isHlsUrl = function (url) {
        return /\.m3u8(?:$|\?)/i.test(url || '');
    };

    const isTruthyOption = function (value) {
        return value === true || value === 'true' || value === '1' || value === 1;
    };

    const getElementPlyrOptions = function (element) {
        return (element && element.fbPlyrOptions && typeof element.fbPlyrOptions === 'object')
            ? element.fbPlyrOptions
            : {};
    };

    const isLazyHlsStartEnabled = function (element) {
        const options = getElementPlyrOptions(element);
        const hlsOptions = options.hls || {};

        return isTruthyOption(element && element.dataset ? element.dataset.hlsLazyStart : false)
            || isTruthyOption(options.hlsLazyStart)
            || isTruthyOption(hlsOptions.lazyStart);
    };

    const isPosterCacheBustEnabled = function (element) {
        const options = getElementPlyrOptions(element);
        const hlsOptions = options.hls || {};

        return isTruthyOption(element && element.dataset ? element.dataset.posterCacheBust : false)
            || isTruthyOption(options.posterCacheBust)
            || isTruthyOption(hlsOptions.posterCacheBust);
    };

    const getNumericOption = function (value, fallback, min, max) {
        const number = Number(value);
        if (!Number.isFinite(number)) {
            return fallback;
        }

        return Math.max(min, Math.min(max, number));
    };

    const getFrontendHlsConfig = function () {
        return (window.hlsStreamConfig && typeof window.hlsStreamConfig === 'object')
            ? window.hlsStreamConfig
            : {};
    };

    const getHlsWaitOptions = function (element) {
        const options = getElementPlyrOptions(element);
        const hlsOptions = options.hls || {};
        const config = getFrontendHlsConfig();

        return {
            timeoutMs: getNumericOption(
                element && element.dataset && element.dataset.hlsWaitTimeout !== undefined
                    ? element.dataset.hlsWaitTimeout
                    : (hlsOptions.waitTimeout || options.hlsWaitTimeout),
                config.readyTimeoutMs || 30000,
                1000,
                120000
            ),
            intervalMs: getNumericOption(
                element && element.dataset && element.dataset.hlsWaitInterval !== undefined
                    ? element.dataset.hlsWaitInterval
                    : (hlsOptions.waitInterval || options.hlsWaitInterval),
                config.readyIntervalMs || 1500,
                500,
                10000
            ),
            requestTimeoutMs: getNumericOption(
                element && element.dataset && element.dataset.hlsHttpTimeout !== undefined
                    ? element.dataset.hlsHttpTimeout
                    : (hlsOptions.httpTimeout || options.hlsHttpTimeout),
                config.httpTimeoutMs || 5000,
                1000,
                15000
            ),
        };
    };

    const debugHls = function () {
        if (!canViewVideoStatus || !window.console || typeof window.console.debug !== 'function') {
            return;
        }

        window.console.debug.apply(window.console, arguments);
    };

    const inferStreamIdFromHlsUrl = function (url) {
        const match = String(url || '').match(/\/stream-([^/]+)\/index\.m3u8(?:[?#].*)?$/i);
        return match ? match[1] : '';
    };

    const getBackendWakeDebugText = function (key, replacements) {
        const messages = hlsLocale === 'ru' ? {
            pending: 'ожидание',
            skipped: 'не требуется',
            success: 'OK HTTP {status}',
            http_error: 'HTTP {status}',
            timeout: 'timeout',
            network_error: 'ошибка сети',
        } : {
            pending: 'pending',
            skipped: 'not required',
            success: 'OK HTTP {status}',
            http_error: 'HTTP {status}',
            timeout: 'timeout',
            network_error: 'network error',
        };
        let message = messages[key] || key;

        Object.entries(replacements || {}).forEach(function ([name, value]) {
            message = message.replace(new RegExp('\\{' + name + '\\}', 'g'), String(value));
        });

        return message;
    };

    const wakeBackendStream = async function (element) {
        const streamId = element.dataset.streamId || inferStreamIdFromHlsUrl(element.hlsSource);

        if (!streamId) {
            if (element.hlsSource) {
                updateVideoDebug(element, {
                    streamId: '',
                    backendWake: getBackendWakeDebugText('skipped'),
                    backendReady: '',
                    backendMessage: '',
                    attempt: '—',
                });
            }
            return true;
        }

        if (element.hlsBackendWakePromise) {
            return element.hlsBackendWakePromise;
        }

        updateVideoDebug(element, {
            streamId: streamId,
            backendWake: getBackendWakeDebugText('pending'),
            backendReady: '',
            backendMessage: '',
            attempt: '1/1',
        });

        element.hlsBackendWakePromise = (async function () {
            let timeoutId = null;
            let timedOut = false;
            const controller = typeof AbortController === 'function' ? new AbortController() : null;

            if (controller) {
                timeoutId = setTimeout(function () {
                    timedOut = true;
                    controller.abort();
                }, 5000);
            }

            try {
                const response = await fetch((typeof baseUrl === 'string' ? baseUrl : '') + '/api/streams/wake', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        stream_id: streamId,
                        hls_url: element.hlsSource || ''
                    }),
                    signal: controller ? controller.signal : undefined,
                });
                let data = {};

                try {
                    data = await response.json();
                } catch (error) {
                    data = {};
                }

                updateVideoDebug(element, {
                    streamId: data.stream_id || streamId,
                    backendWake: getBackendWakeDebugText(response.ok && data.success !== false ? 'success' : 'http_error', {
                        status: response.status || 0,
                    }),
                    backendReady: data.ready === undefined ? '' : String(data.ready),
                    backendMessage: data.message || '',
                    attempt: '1/1',
                });
            } catch (error) {
                const isTimeout = timedOut || (error && error.name === 'AbortError');
                updateVideoDebug(element, {
                    streamId: streamId,
                    backendWake: getBackendWakeDebugText(isTimeout ? 'timeout' : 'network_error'),
                    backendReady: '',
                    backendMessage: isTimeout ? 'Backend wake timeout' : (error && error.message ? error.message : ''),
                    attempt: '1/1',
                });
                console.warn(isTimeout ? 'Backend stream wake timed out' : 'Backend stream wake failed', error);
            } finally {
                if (timeoutId) {
                    clearTimeout(timeoutId);
                }
                element.hlsBackendWakePromise = null;
            }

            return true;
        })();

        return element.hlsBackendWakePromise;
    };

    const isCrossOriginUrl = function (url) {
        try {
            return new URL(url, window.location.href).origin !== window.location.origin;
        } catch (error) {
            return false;
        }
    };

    const inferHlsPoster = function (url) {
        if (!url) {
            return '';
        }

        try {
            const parsedUrl = new URL(url, window.location.href);
            const match = parsedUrl.pathname.match(/\/stream-([^/]+)\/index\.m3u8$/i);
            if (!match) {
                return '';
            }

            parsedUrl.pathname = parsedUrl.pathname.replace(/\/stream-([^/]+)\/index\.m3u8$/i, '/tn-$1.jpg');
            parsedUrl.search = '';
            parsedUrl.hash = '';

            return parsedUrl.href;
        } catch (error) {
            const match = String(url).match(/^(.*\/)stream-([^/]+)\/index\.m3u8(?:[?#].*)?$/i);
            return match ? (match[1] + 'tn-' + match[2] + '.jpg') : '';
        }
    };

    const cacheBustPosterUrl = function (url) {
        if (!url || /^(?:data|blob):/i.test(url)) {
            return url;
        }

        try {
            const parsedUrl = new URL(url, window.location.href);
            parsedUrl.searchParams.set('v', String(Date.now()));
            return parsedUrl.href;
        } catch (error) {
            const separator = String(url).includes('?') ? '&' : '?';
            return String(url) + separator + 'v=' + Date.now();
        }
    };

    const normalizeHlsPoster = function (element, hlsSource) {
        if (!(element instanceof HTMLVideoElement)) {
            return;
        }

        const existingPoster = element.getAttribute('poster') || element.dataset.poster || '';
        const poster = element.dataset.posterCacheBustBase || existingPoster || inferHlsPoster(hlsSource);

        if (!poster) {
            return;
        }

        if (isPosterCacheBustEnabled(element)) {
            element.dataset.posterCacheBustBase = poster;
        }

        const nextPoster = isPosterCacheBustEnabled(element) ? cacheBustPosterUrl(poster) : poster;
        const currentPoster = element.getAttribute('poster') || element.dataset.poster || '';
        if (currentPoster !== nextPoster) {
            element.setAttribute('poster', nextPoster);
            element.dataset.poster = nextPoster;
        }

        if (element.plyr && element.dataset.hlsPlyrPosterSynced !== nextPoster) {
            try {
                element.plyr.poster = nextPoster;
                element.dataset.hlsPlyrPosterSynced = nextPoster;
            } catch (error) {
                // Plyr may not be fully ready while HLS is initializing.
            }
        }
    };

    const getClientInfo = function () {
        const userAgent = window.navigator.userAgent || '';
        const isIos = /iPhone|iPad|iPod/i.test(userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        let browser = 'Unknown';

        if (/Edg\//i.test(userAgent)) {
            browser = 'Edge';
        } else if (/FxiOS/i.test(userAgent)) {
            browser = 'Firefox iOS';
        } else if (/Firefox\//i.test(userAgent)) {
            browser = 'Firefox';
        } else if (/CriOS/i.test(userAgent)) {
            browser = 'Chrome iOS';
        } else if (/Chrome\//i.test(userAgent)) {
            browser = 'Chrome';
        } else if (/Safari/i.test(userAgent) && /Apple/i.test(window.navigator.vendor || '')) {
            browser = isIos
                ? (/iPad/i.test(userAgent) || navigator.maxTouchPoints > 1 ? 'iPad Safari' : 'iPhone Safari')
                : 'Safari macOS';
        }

        let device = 'Desktop';
        if (/iPhone|iPod/i.test(userAgent)) {
            device = 'iPhone';
        } else if (/iPad/i.test(userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)) {
            device = 'iPad';
        } else if (/Android/i.test(userAgent)) {
            device = /Mobile/i.test(userAgent) ? 'Android phone' : 'Android tablet';
        }

        return { browser, device, userAgent };
    };

    const getSourceType = function (url) {
        const value = String(url || '').split('#')[0].split('?')[0].toLowerCase();
        if (value.endsWith('.m3u8')) {
            return 'hls';
        }
        if (value.endsWith('.webm')) {
            return 'webm';
        }
        if (value.endsWith('.mp4') || value.endsWith('.m4v')) {
            return 'mp4';
        }
        return value ? 'external' : 'unknown';
    };

    const getPlaybackMode = function (element) {
        if (element && element.hlsSource) {
            return shouldUseNativeHls(element) ? 'native_hls' : 'hls.js';
        }
        return 'html5_video';
    };

    const videoDebugLabels = hlsLocale === 'ru' ? {
        source: 'Источник',
        streamId: 'ID потока',
        backendWake: 'Backend wake',
        backendReady: 'Backend ready',
        backendMessage: 'Backend message',
        poster: 'Постер',
        sourceType: 'Тип',
        playbackMode: 'Режим',
        httpStatus: 'HTTP',
        errorType: 'Ошибка',
        attempt: 'Попытка',
        checkedAt: 'Последняя проверка',
        browser: 'Браузер',
        device: 'Устройство',
        userAgent: 'User-Agent',
    } : {
        source: 'Source',
        streamId: 'Stream ID',
        backendWake: 'Backend wake',
        backendReady: 'Backend ready',
        backendMessage: 'Backend message',
        poster: 'Poster',
        sourceType: 'Type',
        playbackMode: 'Mode',
        httpStatus: 'HTTP',
        errorType: 'Error',
        attempt: 'Attempt',
        checkedAt: 'Last check',
        browser: 'Browser',
        device: 'Device',
        userAgent: 'User-Agent',
    };

    const renderVideoDebug = function (element) {
        if (!canViewVideoStatus || !element.fbVideoDebugNode) {
            return;
        }

        const state = element.fbVideoDebugState || {};
        Object.keys(videoDebugLabels).forEach(function (key) {
            const target = element.fbVideoDebugNode.querySelector('[data-video-debug-value="' + key + '"]');
            if (target) {
                target.textContent = state[key] === undefined || state[key] === '' ? '—' : String(state[key]);
            }
        });
    };

    const updateVideoDebug = function (element, values) {
        if (!canViewVideoStatus || !(element instanceof HTMLMediaElement)) {
            return;
        }

        const client = getClientInfo();
        element.fbVideoDebugState = Object.assign({
            source: element.hlsSource || element.currentSrc || element.getAttribute('src') || '',
            streamId: inferStreamIdFromHlsUrl(element.hlsSource || element.currentSrc || element.getAttribute('src') || ''),
            backendWake: '',
            backendReady: '',
            backendMessage: '',
            poster: element.getAttribute('poster') || element.dataset.poster || '',
            sourceType: getSourceType(element.hlsSource || element.currentSrc || element.getAttribute('src') || ''),
            playbackMode: getPlaybackMode(element),
            httpStatus: '',
            errorType: '',
            attempt: '0/' + hlsStartupMaxAttempts,
            checkedAt: '',
            browser: client.browser,
            device: client.device,
            userAgent: client.userAgent,
        }, element.fbVideoDebugState || {}, values || {});
        renderVideoDebug(element);
    };

    const ensureVideoDebugBlock = function (element) {
        if (!canViewVideoStatus || !(element instanceof HTMLMediaElement)) {
            return;
        }

        let playerContainer = element.closest('[data-plyr-player-wrap]') || element.closest('.plyr') || element;
        let block = playerContainer.closest('.fb-video-block');
        if (!block) {
            block = document.createElement('div');
            block.className = 'fb-video-block';
            playerContainer.parentNode.insertBefore(block, playerContainer);
            block.appendChild(playerContainer);
        }

        let details = block.querySelector(':scope > .fb-video-debug');
        if (!details) {
            details = document.createElement('details');
            details.className = 'fb-video-debug';
            const summary = document.createElement('summary');
            summary.textContent = hlsLocale === 'ru' ? 'Техническая информация' : 'Technical information';
            const content = document.createElement('div');
            content.className = 'fb-video-debug__content';

            Object.keys(videoDebugLabels).forEach(function (key) {
                const label = document.createElement('div');
                label.className = 'fb-video-debug__label';
                label.textContent = videoDebugLabels[key] + ':';
                const value = document.createElement('div');
                value.className = 'fb-video-debug__value';
                value.dataset.videoDebugValue = key;
                content.appendChild(label);
                content.appendChild(value);
            });

            details.appendChild(summary);
            details.appendChild(content);
            block.appendChild(details);
        }

        element.fbVideoDebugNode = details;
        updateVideoDebug(element);
    };

    const detachNativeHlsSource = function (element) {
        if (element.dataset.hlsNativeSourceDetached === 'true') {
            return;
        }

        const directSource = element.getAttribute('src') || '';
        if (isHlsUrl(directSource)) {
            element.dataset.hlsOriginalSrc = directSource;
            element.src = '';
            element.removeAttribute('src');
        }

        element.querySelectorAll('source').forEach(function (sourceNode, index) {
            const sourceType = (sourceNode.getAttribute('type') || '').toLowerCase();
            const sourceSrc = sourceNode.getAttribute('src') || '';

            if (sourceType === 'application/vnd.apple.mpegurl' || isHlsUrl(sourceSrc)) {
                sourceNode.dataset.hlsOriginalSrc = sourceSrc;
                sourceNode.dataset.hlsOriginalType = sourceNode.getAttribute('type') || '';
                sourceNode.dataset.hlsDetachedIndex = String(index);
                sourceNode.remove();
            }
        });

        element.dataset.hlsNativeSourceDetached = 'true';
        element.removeAttribute('src');
        element.srcObject = null;
        element.load();
    };

    const forceDetachNativeHlsSource = function (element) {
        element.dataset.hlsNativeSourceDetached = 'false';
        detachNativeHlsSource(element);
    };

    const createCleanMediaElement = function (element) {
        if (!(element instanceof HTMLVideoElement) || shouldUseNativeHls(element)) {
            return element;
        }

        if (element.dataset.hlsElementReplaced === 'true') {
            return element;
        }

        const cleanElement = document.createElement(element.tagName.toLowerCase());
        const originalSrc = element.getAttribute('src') || '';

        Array.from(element.attributes).forEach(function (attribute) {
            if (attribute.name.toLowerCase() === 'src') {
                return;
            }

            cleanElement.setAttribute(attribute.name, attribute.value);
        });

        if (originalSrc) {
            cleanElement.dataset.hlsSrc = originalSrc;
        }

        cleanElement.dataset.hlsElementReplaced = 'true';

        element.querySelectorAll('source').forEach(function (sourceNode) {
            cleanElement.appendChild(sourceNode.cloneNode(true));
        });

        element.querySelectorAll('track').forEach(function (trackNode) {
            cleanElement.appendChild(trackNode.cloneNode(true));
        });

        element.parentNode.replaceChild(cleanElement, element);

        return cleanElement;
    };

    const renderHlsMessage = function (element, message, type) {
        const playerRoot = element.closest('.plyr');
        const playerWrap = element.closest('[data-plyr-player-wrap]');
        const container = playerRoot || playerWrap || element.parentElement;
        if (!container) {
            console.error(message);
            return;
        }

        if (element.hlsStatusClearTimer) {
            clearTimeout(element.hlsStatusClearTimer);
            element.hlsStatusClearTimer = null;
        }

        let messageNode = (playerWrap || container).querySelector('[data-plyr-hls-message]');
        if (!messageNode) {
            messageNode = document.createElement('div');
            messageNode.dataset.plyrHlsMessage = 'true';
            messageNode.className = 'fb-plyr-hls-message';
            messageNode.setAttribute('aria-live', 'polite');
            messageNode.setAttribute('role', 'status');
            container.appendChild(messageNode);
        } else if (messageNode.parentElement !== container) {
            container.appendChild(messageNode);
        }

        messageNode.style.removeProperty('display');
        messageNode.classList.remove('fb-plyr-hls-message--retry');
        messageNode.onclick = null;
        messageNode.onkeydown = null;
        messageNode.removeAttribute('tabindex');

        const statusType = type || 'info';
        container.classList.add('has-hls-status');
        container.dataset.plyrHlsStatus = statusType;
        messageNode.className = 'fb-plyr-hls-message fb-plyr-hls-message--' + statusType;
        messageNode.setAttribute('role', statusType === 'error' ? 'alert' : 'status');
        messageNode.hidden = false;
        messageNode.textContent = message;
    };

    const showHlsError = function (element, message) {
        renderHlsMessage(element, message, 'error');
    };

    const showHlsInfo = function (element, message) {
        renderHlsMessage(element, message, 'info');
    };

    const showHlsSuccess = function (element, message) {
        renderHlsMessage(element, message, 'success');
    };

    const showHlsWarning = function (element, message) {
        renderHlsMessage(element, message, 'warning');
    };

    const showHlsUnavailable = function (element, errorType) {
        renderHlsMessage(element, t('source_timeout') + '\n' + t('retry_manual'), 'error');
        const playerRoot = element.closest('.plyr');
        const playerWrap = element.closest('[data-plyr-player-wrap]');
        const container = playerRoot || playerWrap || element.parentElement;
        const messageNode = container ? (playerWrap || container).querySelector('[data-plyr-hls-message]') : null;

        updateVideoDebug(element, {
            errorType: errorType || 'source_unavailable',
            checkedAt: new Date().toLocaleString(),
        });

        if (!messageNode) {
            return;
        }

        messageNode.classList.add('fb-plyr-hls-message--retry');
        messageNode.setAttribute('role', 'button');
        messageNode.setAttribute('tabindex', '0');
        const retry = function () {
            messageNode.onclick = null;
            messageNode.removeAttribute('tabindex');
            element.hlsSourcePrewarmed = false;
            element.hlsSourcePrewarmedAt = 0;
            element.hlsAutoplayRequested = false;
            wakeAndPlay(element);
        };
        messageNode.onclick = retry;
        messageNode.onkeydown = function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                retry();
            }
        };
    };

    const clearHlsMessage = function (element) {
        if (element.hlsStatusClearTimer) {
            clearTimeout(element.hlsStatusClearTimer);
            element.hlsStatusClearTimer = null;
        }

        const playerRoot = element.closest('.plyr');
        const playerWrap = element.closest('[data-plyr-player-wrap]');
        const container = playerRoot || playerWrap || element.parentElement;
        const messageNode = (playerWrap || container) ? (playerWrap || container).querySelector('[data-plyr-hls-message]') : null;

        [playerRoot, playerWrap].forEach(function (node) {
            if (!node) {
                return;
            }

            node.classList.remove('has-hls-status');
            delete node.dataset.plyrHlsStatus;
        });

        if (messageNode) {
            messageNode.className = 'fb-plyr-hls-message';
            messageNode.onclick = null;
            messageNode.onkeydown = null;
            messageNode.removeAttribute('tabindex');
            messageNode.hidden = true;
            messageNode.textContent = '';
        }
    };

    const scheduleHlsMessageClear = function (element) {
        if (element.hlsStatusClearTimer) {
            clearTimeout(element.hlsStatusClearTimer);
        }

        element.hlsStatusClearTimer = setTimeout(function () {
            clearHlsMessage(element);
        }, hlsStatusHideDelayMs);
    };

    const sleep = function (ms, signal) {
        return new Promise(function (resolve) {
            if (signal && signal.aborted) {
                resolve(false);
                return;
            }

            const timeoutId = setTimeout(function () {
                if (signal) {
                    signal.removeEventListener('abort', abort);
                }
                resolve(true);
            }, ms);

            const abort = function () {
                clearTimeout(timeoutId);
                resolve(false);
            };

            if (signal) {
                signal.addEventListener('abort', abort, { once: true });
            }
        });
    };

    const getMediaStateLabel = function (value, map) {
        return Object.prototype.hasOwnProperty.call(map, value) ? map[value] : 'unknown';
    };

    const describeMediaState = function (element) {
        if (!canViewVideoStatus) {
            return '';
        }

        const readyStates = {
            0: 'HAVE_NOTHING',
            1: 'HAVE_METADATA',
            2: 'HAVE_CURRENT_DATA',
            3: 'HAVE_FUTURE_DATA',
            4: 'HAVE_ENOUGH_DATA',
        };
        const networkStates = {
            0: 'NETWORK_EMPTY',
            1: 'NETWORK_IDLE',
            2: 'NETWORK_LOADING',
            3: 'NETWORK_NO_SOURCE',
        };
        const mediaError = element.error ? ('mediaError=' + element.error.code) : 'mediaError=none';

        return 'paused=' + element.paused
            + ', readyState=' + getMediaStateLabel(element.readyState, readyStates)
            + ', networkState=' + getMediaStateLabel(element.networkState, networkStates)
            + ', currentTime=' + element.currentTime.toFixed(2)
            + ', currentSrc=' + (element.currentSrc || 'none')
            + ', ' + mediaError;
    };

    const isMediaPlaybackActive = function (element) {
        if (!(element instanceof HTMLMediaElement)) {
            return false;
        }

        const networkNoSource = typeof HTMLMediaElement !== 'undefined'
            ? element.networkState === HTMLMediaElement.NETWORK_NO_SOURCE
            : element.networkState === 3;
        const hasUsableData = typeof HTMLMediaElement !== 'undefined'
            ? element.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA
            : element.readyState >= 2;

        return Boolean(element.currentSrc || element.getAttribute('src'))
            && !element.paused
            && !element.ended
            && !element.error
            && !networkNoSource
            && hasUsableData;
    };

    const cleanupWarmupTimer = function (element) {
        if (element.hlsWarmupTimer) {
            clearTimeout(element.hlsWarmupTimer);
            element.hlsWarmupTimer = null;
        }
    };

    const cleanupPlayRetryTimer = function (element) {
        if (element.hlsPlayRetryTimer) {
            clearTimeout(element.hlsPlayRetryTimer);
            element.hlsPlayRetryTimer = null;
        }
    };

    const cleanupHlsReconnectTimers = function (element) {
        if (element.hlsReconnectTimer) {
            clearTimeout(element.hlsReconnectTimer);
            element.hlsReconnectTimer = null;
        }

        if (element.hlsAutoReconnectTimer) {
            clearTimeout(element.hlsAutoReconnectTimer);
            element.hlsAutoReconnectTimer = null;
        }
    };

    const revokeBlobMediaUrl = function (url) {
        if (!/^blob:/i.test(url || '') || typeof window.URL === 'undefined' || typeof window.URL.revokeObjectURL !== 'function') {
            return;
        }

        try {
            window.URL.revokeObjectURL(url);
        } catch (error) {
            // A browser-owned blob URL may already be released.
        }
    };

    const resetMediaSource = function (element) {
        const currentSource = element.currentSrc || element.getAttribute('src') || element.src || '';

        revokeBlobMediaUrl(currentSource);

        try {
            element.pause();
        } catch (error) {
            // The media element can be detached during an HLS reset.
        }

        element.removeAttribute('src');
        element.src = '';
        element.srcObject = null;
        element.load();
    };

    const syncFullVolume = function (element, allowUnmute) {
        if (!(element instanceof HTMLMediaElement)) {
            return;
        }

        try {
            element.volume = 1;
        } catch (error) {
            // iOS exposes hardware volume only; assigning volume can be ignored.
        }

        if (allowUnmute) {
            try {
                element.muted = false;
                element.defaultMuted = false;
                element.removeAttribute('muted');
            } catch (error) {
                // Mobile browsers can reject script-driven unmute outside a user gesture.
            }
        }

        if (element.plyr) {
            try {
                element.plyr.volume = 1;
                if (allowUnmute) {
                    element.plyr.muted = false;
                }
            } catch (error) {
                // Plyr can be mid-destroy while HLS is resetting.
            }
        }
    };

    const isPrimerPlaybackActive = function (element) {
        return element.dataset.hlsPrimerActive === 'true';
    };

    const primePlaybackSession = function (element) {
        if (isPrimerPlaybackActive(element)) {
            return;
        }

        element.dataset.hlsPrimerActive = 'true';
        element.dataset.hlsPrimerPreviousPreload = element.getAttribute('preload') || '';
        element.muted = true;
        element.autoplay = true;
        element.playsInline = true;
        element.loop = true;
        element.setAttribute('preload', 'auto');

        if (element.plyr) {
            element.plyr.muted = true;
        }

        if (element.getAttribute('src') !== plyrBlankVideoUrl) {
            element.setAttribute('src', plyrBlankVideoUrl);
            element.load();
        }

        showHlsInfo(element, t('holding_session'));
        attemptDeferredPlay(element);
    };

    const prepareNativeHlsPlayback = async function (element) {
        let isAwake = true;

        if (element.hlsMediaReady
            && !element.error
            && (element.currentSrc === element.hlsSource || element.getAttribute('src') === element.hlsSource)) {
            return true;
        }

        // Native Safari can load cross-origin HLS without exposing it to XHR.
        // Probing it first would require CORS and some stream servers reject cache-busting queries.
        if (!isCrossOriginUrl(element.hlsSource)) {
            isAwake = await ensureHlsSourceAwake(element, {
                emitStatus: true,
                requirePlayIntent: true,
            });
        }

        if (!isAwake) {
            return false;
        }

        const sourceChanged = element.getAttribute('src') !== element.hlsSource;
        if (sourceChanged) {
            element.setAttribute('src', element.hlsSource);
        }

        if (sourceChanged || !element.currentSrc) {
            element.load();
        }
        element.hlsMediaReady = true;
        showHlsInfo(element, t('preparing_native'));
        return true;
    };

    const markPlaybackStarted = function (element) {
        cleanupPlayRetryTimer(element);
        if (element.hlsAutoReconnectTimer) {
            clearTimeout(element.hlsAutoReconnectTimer);
            element.hlsAutoReconnectTimer = null;
        }
        element.hlsPlayRetryStartedAt = 0;
        element.hlsStuckSince = 0;
        element.hlsPlaybackStarted = true;
        element.hlsEverPlayed = true;
        updateVideoDebug(element, {
            httpStatus: element.fbVideoDebugState && element.fbVideoDebugState.httpStatus
                ? element.fbVideoDebugState.httpStatus
                : 200,
            errorType: '',
            playbackMode: getPlaybackMode(element),
            checkedAt: new Date().toLocaleString(),
        });
        syncFullVolume(element, true);
        clearHlsMessage(element);

        if (element.dataset.hlsForceMutedAutoplay === 'true') {
            element.dataset.hlsForceMutedAutoplay = 'false';
        }
    };

    const releasePrimedPlayback = function (element, keepMediaState) {
        if (!isPrimerPlaybackActive(element)) {
            return;
        }

        element.dataset.hlsPrimerActive = 'false';
        element.loop = false;

        if (element.dataset.hlsPrimerPreviousPreload) {
            element.setAttribute('preload', element.dataset.hlsPrimerPreviousPreload);
        } else {
            element.removeAttribute('preload');
        }

        if (!keepMediaState && element.getAttribute('src') === plyrBlankVideoUrl) {
            element.removeAttribute('src');
            element.load();
        }
    };

    const cacheBustUrl = function (url) {
        if (!url) {
            return url;
        }

        try {
            const parsedUrl = new URL(url, window.location.href);
            parsedUrl.searchParams.set('_hls_wake', String(Date.now()));
            return parsedUrl.href;
        } catch (error) {
            const separator = String(url).includes('?') ? '&' : '?';
            return String(url) + separator + '_hls_wake=' + Date.now();
        }
    };

    const urlExists = function (url, method, options) {
        return new Promise(function (resolve) {
            const settings = Object.assign({
                cacheBust: false,
            }, options || {});
            let settled = false;
            const xhr = new XMLHttpRequest();

            const finish = function (result) {
                if (settled) {
                    return;
                }

                settled = true;
                resolve(result);
            };

            xhr.open(method, settings.cacheBust ? cacheBustUrl(url) : url, true);
            xhr.timeout = 2500;
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) {
                    return;
                }

                if (xhr.status >= 200 && xhr.status < 400 && xhr.status !== 404) {
                    finish({ ok: true, status: xhr.status, errorType: '' });
                    return;
                }

                finish({
                    ok: false,
                    status: xhr.status,
                    errorType: xhr.status === 404 ? 'manifest_not_found' : 'http_error',
                });
            };
            xhr.onerror = function () {
                finish({ ok: false, status: xhr.status || 0, errorType: 'network_error' });
            };
            xhr.ontimeout = function () {
                finish({ ok: false, status: xhr.status || 0, errorType: 'timeout' });
            };

            try {
                xhr.send();
            } catch (error) {
                finish({ ok: false, status: 0, errorType: 'request_error' });
            }
        });
    };

    const requestHlsManifest = function (url, method, options) {
        return new Promise(function (resolve) {
            const settings = Object.assign({
                timeoutMs: 2500,
                signal: null,
            }, options || {});
            let settled = false;
            const xhr = new XMLHttpRequest();

            const finish = function (result) {
                if (settled) {
                    return;
                }

                settled = true;
                if (settings.signal) {
                    settings.signal.removeEventListener('abort', abort);
                }
                resolve(Object.assign({
                    method,
                    ok: false,
                    status: 0,
                    errorType: '',
                }, result || {}));
            };

            const abort = function () {
                try {
                    xhr.abort();
                } catch (error) {
                    // The request may already be settled.
                }
                finish({ errorType: 'aborted' });
            };

            if (settings.signal) {
                if (settings.signal.aborted) {
                    finish({ errorType: 'aborted' });
                    return;
                }
                settings.signal.addEventListener('abort', abort, { once: true });
            }

            try {
                xhr.open(method, url, true);
                xhr.timeout = settings.timeoutMs;
                xhr.setRequestHeader('Cache-Control', 'no-cache');
                xhr.setRequestHeader('Pragma', 'no-cache');
            } catch (error) {
                finish({ errorType: 'request_error' });
                return;
            }

            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) {
                    return;
                }

                if (xhr.status >= 200 && xhr.status < 300) {
                    finish({ ok: true, status: xhr.status, errorType: '' });
                    return;
                }

                finish({
                    ok: false,
                    status: xhr.status || 0,
                    errorType: xhr.status === 404 ? 'manifest_not_found' : 'http_error',
                });
            };
            xhr.onerror = function () {
                finish({ status: xhr.status || 0, errorType: 'network_error' });
            };
            xhr.ontimeout = function () {
                finish({ status: xhr.status || 0, errorType: 'timeout' });
            };
            xhr.onabort = function () {
                finish({ status: xhr.status || 0, errorType: 'aborted' });
            };

            try {
                xhr.send();
            } catch (error) {
                finish({ status: 0, errorType: 'request_error' });
            }
        });
    };

    const shouldFallbackToGetAfterHead = function (result) {
        return !result
            || result.status === 0
            || result.status === 403
            || result.status === 404
            || result.status === 405
            || result.status === 500
            || result.status === 501
            || result.status === 502
            || result.status === 503
            || result.errorType === 'network_error'
            || result.errorType === 'timeout'
            || result.errorType === 'request_error';
    };

    const isFatalHlsReadyStatus = function (status) {
        return status === 403
            || status === 500
            || status === 502
            || status === 503;
    };

    const waitForHlsReady = async function (url, options) {
        const settings = Object.assign({
            timeoutMs: 30000,
            intervalMs: 2500,
            requestTimeoutMs: 2500,
            signal: null,
            onAttempt: null,
        }, options || {});
        const startedAt = Date.now();
        let attempt = 0;
        let lastResult = {
            ok: false,
            status: 0,
            errorType: 'timeout',
            method: 'HEAD',
        };

        while (!settings.signal || !settings.signal.aborted) {
            const elapsedMs = Date.now() - startedAt;
            if (elapsedMs >= settings.timeoutMs) {
                break;
            }

            attempt += 1;
            const remainingMs = Math.max(1, settings.timeoutMs - elapsedMs);
            const requestTimeoutMs = Math.max(1, Math.min(settings.requestTimeoutMs, remainingMs));
            let result = await requestHlsManifest(url, 'HEAD', {
                timeoutMs: requestTimeoutMs,
                signal: settings.signal,
            });

            const afterHeadMs = Date.now() - startedAt;
            if (shouldFallbackToGetAfterHead(result)
                && afterHeadMs < settings.timeoutMs
                && (!settings.signal || !settings.signal.aborted)) {
                result = await requestHlsManifest(url, 'GET', {
                    timeoutMs: Math.max(1, Math.min(settings.requestTimeoutMs, settings.timeoutMs - afterHeadMs)),
                    signal: settings.signal,
                });
            }

            lastResult = result;
            if (typeof settings.onAttempt === 'function') {
                settings.onAttempt(result, attempt);
            }
            debugHls('HLS lazy readiness check', {
                url,
                attempt,
                method: result.method,
                status: result.status,
                errorType: result.errorType,
            });

            if (result.ok) {
                return {
                    ready: true,
                    status: result.status,
                    errorType: '',
                    attempts: attempt,
                    timedOut: false,
                };
            }

            if (isFatalHlsReadyStatus(result.status)) {
                break;
            }

            const afterAttemptMs = Date.now() - startedAt;
            if (afterAttemptMs >= settings.timeoutMs) {
                break;
            }

            const waited = await sleep(Math.min(settings.intervalMs, settings.timeoutMs - afterAttemptMs), settings.signal);
            if (!waited) {
                break;
            }
        }

        return {
            ready: false,
            status: lastResult.status || 0,
            errorType: settings.signal && settings.signal.aborted ? 'aborted' : (lastResult.errorType || 'timeout'),
            attempts: attempt,
            timedOut: !(settings.signal && settings.signal.aborted),
        };
    };

    window.waitForHlsReady = window.waitForHlsReady || waitForHlsReady;

    const waitForHlsSource = async function (element, url, options) {
        const settings = Object.assign({
            requirePlayIntent: true,
            emitStatus: true,
            wake: false,
        }, options || {});
        let attempt = 0;

        while (attempt < hlsStartupMaxAttempts) {
            if (settings.requirePlayIntent && !element.hlsAutoplayRequested) {
                return false;
            }

            attempt += 1;
            if (settings.emitStatus) {
                showHlsInfo(element, t('checking_attempt', { attempt }));
            }

            const result = await urlExists(url, 'GET', { cacheBust: settings.wake });
            updateVideoDebug(element, {
                httpStatus: result.status || 0,
                errorType: result.errorType,
                attempt: attempt + '/' + hlsStartupMaxAttempts,
                checkedAt: new Date().toLocaleString(),
            });

            if (result.ok) {
                if (settings.emitStatus) {
                    showHlsSuccess(element, t('stream_responded'));
                }
                return true;
            }

            if (settings.emitStatus) {
                showHlsWarning(element, t('stream_sleeping'));
            }
            if (attempt < hlsStartupMaxAttempts) {
                await sleep(hlsRetryDelayMs);
            }
        }

        return false;
    };

    const cleanupHlsLazyWait = function (element) {
        if (!element) {
            return;
        }

        if (element.hlsLazyWaitController) {
            try {
                element.hlsLazyWaitController.abort();
            } catch (error) {
                // The readiness check may already be completed.
            }
        }

        element.hlsLazyWaitController = null;
        element.hlsLazyWaitPromise = null;
        element.hlsWakePromise = null;
    };

    const waitForElementHlsReady = function (element, options) {
        const settings = Object.assign({
            emitStatus: true,
            requirePlayIntent: true,
        }, options || {});

        if (!element.hlsSource) {
            return Promise.resolve(false);
        }

        if (settings.requirePlayIntent && !element.hlsAutoplayRequested) {
            return Promise.resolve(false);
        }

        if (element.hlsLazyWaitPromise) {
            return element.hlsLazyWaitPromise;
        }

        const waitOptions = getHlsWaitOptions(element);
        const controller = typeof AbortController === 'function' ? new AbortController() : null;
        element.hlsLazyWaitController = controller;
        element.hlsLazyWaitPromise = waitForHlsReady(element.hlsSource, {
            timeoutMs: waitOptions.timeoutMs,
            intervalMs: waitOptions.intervalMs,
            requestTimeoutMs: waitOptions.requestTimeoutMs,
            signal: controller ? controller.signal : null,
            onAttempt: function (result, attempt) {
                const maxAttempts = Math.max(1, Math.ceil(waitOptions.timeoutMs / waitOptions.intervalMs));
                updateVideoDebug(element, {
                    httpStatus: result.status || 0,
                    errorType: result.errorType,
                    attempt: attempt + '/' + maxAttempts,
                    checkedAt: new Date().toLocaleString(),
                });

                if (!settings.emitStatus) {
                    return;
                }

                if (result.ok) {
                    showHlsSuccess(element, t('stream_responded'));
                    return;
                }

                showHlsWarning(element, t('stream_sleeping'));
            },
        }).then(function (result) {
            if (result.ready) {
                element.hlsSourcePrewarmed = true;
                element.hlsSourcePrewarmedAt = Date.now();
                if (settings.emitStatus) {
                    showHlsSuccess(element, t('manifest_available'));
                }
                return true;
            }

            updateVideoDebug(element, {
                httpStatus: result.status || 0,
                errorType: result.errorType || 'source_unavailable',
                checkedAt: new Date().toLocaleString(),
            });

            return false;
        }).finally(function () {
            if (element.hlsLazyWaitController === controller) {
                element.hlsLazyWaitController = null;
            }
            element.hlsLazyWaitPromise = null;
        });

        if (settings.emitStatus) {
            showHlsInfo(element, t('checking_attempt'));
        }

        return element.hlsLazyWaitPromise;
    };

    const ensureHlsSourceAwake = function (element, options) {
        const settings = Object.assign({
            emitStatus: true,
            requirePlayIntent: true,
        }, options || {});

        if (!element.hlsSource) {
            return Promise.resolve(false);
        }

        if (element.hlsSourcePrewarmed && element.hlsSourcePrewarmedAt && (Date.now() - element.hlsSourcePrewarmedAt) < hlsPrewarmTtlMs) {
            return Promise.resolve(true);
        }

        if (isCrossOriginUrl(element.hlsSource)) {
            element.hlsSourcePrewarmed = true;
            element.hlsSourcePrewarmedAt = Date.now();
            return Promise.resolve(true);
        }

        if (element.hlsWakePromise) {
            return element.hlsWakePromise;
        }

        if (isLazyHlsStartEnabled(element)) {
            element.hlsWakePromise = waitForElementHlsReady(element, {
                emitStatus: settings.emitStatus,
                requirePlayIntent: settings.requirePlayIntent,
            }).finally(function () {
                element.hlsWakePromise = null;
            });

            return element.hlsWakePromise;
        }

        element.hlsWakePromise = waitForHlsSource(element, element.hlsSource, {
            requirePlayIntent: settings.requirePlayIntent,
            emitStatus: settings.emitStatus,
            wake: true,
        }).then(function (isReady) {
            if (isReady) {
                element.hlsSourcePrewarmed = true;
                element.hlsSourcePrewarmedAt = Date.now();
                if (settings.emitStatus) {
                    showHlsSuccess(element, t('manifest_available'));
                }
            }

            return isReady;
        }).finally(function () {
            element.hlsWakePromise = null;
        });

        return element.hlsWakePromise;
    };

    const prewarmHlsSource = function (element) {
        if (!element.hlsSource) {
            return Promise.resolve(false);
        }

        if (element.hlsSourcePrewarmed && element.hlsSourcePrewarmedAt && (Date.now() - element.hlsSourcePrewarmedAt) < hlsPrewarmTtlMs) {
            return Promise.resolve(true);
        }

        if (element.hlsBackgroundWarmupPromise) {
            return element.hlsBackgroundWarmupPromise;
        }

        element.hlsBackgroundWarmupPromise = waitForHlsSource(element, element.hlsSource, {
            requirePlayIntent: false,
            emitStatus: false,
            wake: true,
        }).then(function (isReady) {
            element.hlsSourcePrewarmed = isReady;
            element.hlsSourcePrewarmedAt = isReady ? Date.now() : 0;
            return isReady;
        }).finally(function () {
            element.hlsBackgroundWarmupPromise = null;
        });

        return element.hlsBackgroundWarmupPromise;
    };

    const destroyHlsInstance = function (element) {
        cleanupHlsLazyWait(element);

        const hls = element.hlsInstance;

        if (hls) {
            try {
                if (typeof hls.stopLoad === 'function') {
                    hls.stopLoad();
                }
            } catch (error) {
                // Ignore cleanup failures from already-detached HLS instances.
            }

            try {
                if (typeof hls.detachMedia === 'function') {
                    hls.detachMedia();
                }
            } catch (error) {
                // Ignore cleanup failures from already-detached HLS instances.
            }

            try {
                if (typeof hls.destroy === 'function') {
                    hls.destroy();
                }
            } catch (error) {
                // Ignore cleanup failures from already-destroyed HLS instances.
            }
        }

        cleanupPlayRetryTimer(element);
        cleanupHlsReconnectTimers(element);
        releasePrimedPlayback(element);
        resetMediaSource(element);
        element.hlsInstance = null;
        element.hlsMediaReady = false;
        element.hlsPlaybackStarted = false;
        element.hlsMediaRecoverAttempts = 0;
        element.dataset.hlsPlaybackBound = 'false';
    };

    const resetNativePlaybackState = function (element) {
        cleanupHlsLazyWait(element);
        cleanupPlayRetryTimer(element);
        cleanupWarmupTimer(element);
        cleanupHlsReconnectTimers(element);
        releasePrimedPlayback(element);
        element.hlsMediaReady = false;
        element.hlsPlaybackStarted = false;
        element.hlsMediaRecoverAttempts = 0;
        resetMediaSource(element);
    };

    const cleanupDetachedHlsElement = function (element) {
        cleanupHlsLazyWait(element);
        cleanupPlayRetryTimer(element);
        cleanupWarmupTimer(element);
        cleanupHlsReconnectTimers(element);

        if (element.hlsHealthTimer) {
            clearInterval(element.hlsHealthTimer);
            element.hlsHealthTimer = null;
        }

        if (element.hlsInstance) {
            destroyHlsInstance(element);
            return;
        }

        if (element.hlsSource) {
            resetMediaSource(element);
        }
    };

    const bindHlsRemovalCleanup = function (element) {
        if (element.dataset.hlsRemovalCleanupBound === 'true' || typeof MutationObserver !== 'function') {
            return;
        }

        element.dataset.hlsRemovalCleanupBound = 'true';
        const observer = new MutationObserver(function () {
            if (document.documentElement.contains(element)) {
                return;
            }

            observer.disconnect();
            element.hlsRemovalObserver = null;
            cleanupDetachedHlsElement(element);
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
        });
        element.hlsRemovalObserver = observer;
    };

    const fallbackToHlsJsPlayback = async function (element, reason) {
        if (isSafariBrowser()) {
            return false;
        }

        if (element.dataset.hlsForceJsPlayback === 'true' || !canUseHlsJsPlayback()) {
            return false;
        }

        element.dataset.hlsForceJsPlayback = 'true';
        element.hlsJsFallbackReason = reason || '';
        showHlsWarning(element, t('native_fallback'));
        resetNativePlaybackState(element);

        const isReady = await prepareHlsPlayback(element);
        if (!isReady) {
            return false;
        }

        scheduleDeferredPlay(element, t('stream_initialized'));
        return true;
    };

    const attemptDeferredPlay = function (element) {
        if (!element.hlsAutoplayRequested || typeof element.play !== 'function') {
            return;
        }

        if (element.dataset.hlsForceMutedAutoplay === 'true') {
            element.muted = true;
            element.autoplay = true;
            element.playsInline = true;

            if (element.plyr) {
                element.plyr.muted = true;
            }
        }

        let playPromise = null;

        try {
            playPromise = element.play();
        } catch (error) {
            playPromise = null;
        }

        if ((!playPromise || typeof playPromise.catch !== 'function') && element.plyr && typeof element.plyr.play === 'function') {
            try {
                playPromise = element.plyr.play();
            } catch (error) {
                playPromise = null;
            }
        }

        if (playPromise && typeof playPromise.catch === 'function') {
            playPromise.catch(function () {
                // Ignore autoplay interruption while the user-initiated wake-up is in progress.
            });
        }
    };

    const scheduleDeferredPlay = function (element, reason) {
        if (!element.hlsAutoplayRequested || element.hlsPlaybackStarted) {
            return;
        }

        if (isMediaPlaybackActive(element)) {
            markPlaybackStarted(element);
            return;
        }

        cleanupPlayRetryTimer(element);

        if (!element.hlsPlayRetryStartedAt) {
            element.hlsPlayRetryStartedAt = Date.now();
        }

        if ((Date.now() - element.hlsPlayRetryStartedAt) > hlsPlayRetryDurationMs) {
            showHlsWarning(element, t('playback_pending', { state: describeMediaState(element) }));
            return;
        }

        if (reason) {
            showHlsInfo(element, reason);
        }

        attemptDeferredPlay(element);

        element.hlsPlayRetryTimer = setTimeout(function () {
            scheduleDeferredPlay(element);
        }, hlsPlayRetryDelayMs);
    };

    const isHlsStuckLoadingState = function (element) {
        if (!(element instanceof HTMLMediaElement) || !element.hlsSource || !element.hlsAutoplayRequested) {
            return false;
        }

        const haveNothing = typeof HTMLMediaElement !== 'undefined'
            ? HTMLMediaElement.HAVE_NOTHING
            : 0;
        const haveCurrentData = typeof HTMLMediaElement !== 'undefined'
            ? HTMLMediaElement.HAVE_CURRENT_DATA
            : 2;
        const networkLoading = typeof HTMLMediaElement !== 'undefined'
            ? HTMLMediaElement.NETWORK_LOADING
            : 2;
        const networkIdle = typeof HTMLMediaElement !== 'undefined'
            ? HTMLMediaElement.NETWORK_IDLE
            : 1;
        const mediaErrDecode = typeof MediaError !== 'undefined'
            ? MediaError.MEDIA_ERR_DECODE
            : 3;

        if (element.paused
            || element.ended
            || element.hlsWarmupActive
            || isPrimerPlaybackActive(element)) {
            return false;
        }

        const decodeErrorAtStart = element.error
            && element.error.code === mediaErrDecode
            && element.readyState >= haveCurrentData
            && element.networkState === networkIdle
            && element.currentTime <= hlsStartTimeEpsilon;
        const idleAtStartWithoutProgress = !element.error
            && !element.hlsPlaybackStarted
            && element.readyState === haveCurrentData
            && element.networkState === networkIdle
            && element.currentTime <= hlsStartTimeEpsilon;
        const loadingWithoutData = !element.error
            && !element.ended
            && element.readyState === haveNothing
            && element.networkState === networkLoading;

        if (loadingWithoutData) {
            const startedAt = element.hlsStartupStartedAt || element.hlsPlayRetryStartedAt || 0;
            return startedAt > 0 && (Date.now() - startedAt) >= hlsStartupGraceMs;
        }

        return decodeErrorAtStart || idleAtStartWithoutProgress || loadingWithoutData;
    };

    const restartHlsPlayback = function (element, reason) {
        if (element.hlsRestarting || !element.hlsSource || !element.hlsAutoplayRequested) {
            return;
        }

        const now = Date.now();
        if (element.hlsLastRestartAt && (now - element.hlsLastRestartAt) < hlsRestartCooldownMs) {
            return;
        }

        element.hlsRestarting = true;
        element.hlsLastRestartAt = now;
        element.hlsStuckSince = 0;
        element.hlsPlayRetryStartedAt = now;
        showHlsWarning(element, t('stream_restarting'));
        updateVideoDebug(element, {
            errorType: reason || 'playback_error',
            checkedAt: new Date().toLocaleString(),
        });

        element.hlsReconnectTimer = setTimeout(function () {
            const useNative = shouldUseNativeHls(element);
            if (useNative) {
                resetNativePlaybackState(element);
            } else {
                destroyHlsInstance(element);
            }

            element.hlsAutoplayRequested = true;
            element.hlsSourcePrewarmed = false;
            element.hlsSourcePrewarmedAt = 0;
            const prepare = useNative ? prepareNativeHlsPlayback(element) : prepareHlsPlayback(element);

            prepare.then(function (isReady) {
                if (!isReady) {
                    showHlsUnavailable(element, 'reconnect_failed');
                    if (element.hlsEverPlayed) {
                        element.hlsAutoReconnectTimer = setTimeout(function () {
                            element.hlsRestarting = false;
                            restartHlsPlayback(element, 'automatic_reconnect');
                        }, hlsReconnectDelayMs);
                    }
                    return;
                }

                scheduleDeferredPlay(element, t('stream_initialized'));
            }).finally(function () {
                element.hlsReconnectTimer = null;
                if (!element.hlsAutoReconnectTimer) {
                    element.hlsRestarting = false;
                }
            });
        }, hlsReconnectDelayMs);
    };

    const startHlsHealthMonitor = function (element) {
        if (element.hlsHealthTimer) {
            return;
        }

        element.hlsHealthTimer = setInterval(function () {
            if (!element.hlsAutoplayRequested || isPrimerPlaybackActive(element)) {
                element.hlsStuckSince = 0;
                return;
            }

            if (isMediaPlaybackActive(element)) {
                if (!element.hlsPlaybackStarted) {
                    markPlaybackStarted(element);
                }
                element.hlsStuckSince = 0;
                return;
            }

            if (!isHlsStuckLoadingState(element)) {
                element.hlsStuckSince = 0;
                return;
            }

            if (!element.hlsStuckSince) {
                element.hlsStuckSince = Date.now();
                return;
            }

            if ((Date.now() - element.hlsStuckSince) >= hlsStuckResetAfterMs) {
                restartHlsPlayback(element, describeMediaState(element));
            }
        }, hlsHealthCheckIntervalMs);
    };

    const bindNativePlaybackEvents = function (element) {
        if (element.dataset.hlsNativePlaybackBound === 'true') {
            return;
        }

        element.dataset.hlsNativePlaybackBound = 'true';

        element.addEventListener('loadedmetadata', function () {
            if (!shouldUseNativeHls(element) || !element.hlsAutoplayRequested) {
                return;
            }

            showHlsSuccess(element, t('native_metadata'));
            scheduleDeferredPlay(element);
        });

        element.addEventListener('loadeddata', function () {
            if (!shouldUseNativeHls(element) || !element.hlsAutoplayRequested) {
                return;
            }

            showHlsSuccess(element, t('native_first_data'));
            scheduleDeferredPlay(element);
        });

        element.addEventListener('canplay', function () {
            if (!shouldUseNativeHls(element) || !element.hlsAutoplayRequested) {
                return;
            }

            showHlsSuccess(element, t('native_ready'));
            scheduleDeferredPlay(element);
        });

        element.addEventListener('playing', function () {
            if (!shouldUseNativeHls(element) || !element.hlsAutoplayRequested) {
                return;
            }

            markPlaybackStarted(element);
        });

        element.addEventListener('timeupdate', function () {
            if (!shouldUseNativeHls(element) || element.hlsPlaybackStarted || !element.hlsAutoplayRequested) {
                return;
            }

            if (isMediaPlaybackActive(element)) {
                markPlaybackStarted(element);
            }
        });

        element.addEventListener('error', function () {
            if (!shouldUseNativeHls(element) || !element.hlsAutoplayRequested) {
                return;
            }

            if (element.hlsEverPlayed) {
                restartHlsPlayback(element, 'native_network_or_media_error');
                return;
            }

            if (!element.hlsPlayRetryStartedAt) {
                element.hlsPlayRetryStartedAt = Date.now();
            }

            if ((Date.now() - element.hlsPlayRetryStartedAt) > hlsPlayRetryDurationMs) {
                const state = describeMediaState(element);
                fallbackToHlsJsPlayback(element, state).then(function (switched) {
                    if (!switched) {
                        updateVideoDebug(element, {
                            errorType: 'native_media_error',
                            checkedAt: new Date().toLocaleString(),
                        });
                        restartHlsPlayback(element, 'native_media_error');
                    }
                });
                return;
            }

            showHlsWarning(element, t('native_stalled'));
            cleanupPlayRetryTimer(element);
            element.hlsPlayRetryTimer = setTimeout(function () {
                if (!shouldUseNativeHls(element)) {
                    return;
                }
                resetNativePlaybackState(element);
                element.hlsAutoplayRequested = true;
                prepareNativeHlsPlayback(element).then(function (isReady) {
                    if (isReady) {
                        attemptDeferredPlay(element);
                    }
                });
            }, hlsRetryDelayMs);
        });

        element.addEventListener('stalled', function () {
            if (!shouldUseNativeHls(element) || !element.hlsAutoplayRequested) {
                return;
            }

            restartHlsPlayback(element, describeMediaState(element));
        });
    };

    const bindHlsPlaybackEvents = function (element, hls) {
        if (!hls) {
            return;
        }

        element.dataset.hlsPlaybackBound = 'true';

        hls.on(Hls.Events.ERROR, function (_, data) {
            if (element.hlsInstance !== hls) {
                return;
            }

            if (!data || !data.fatal) {
                return;
            }

            const details = data.details ? ' (' + data.details + ')' : '';
            updateVideoDebug(element, {
                httpStatus: data.response && data.response.code ? data.response.code : '',
                errorType: data.details || data.type || 'hls_error',
                checkedAt: new Date().toLocaleString(),
            });

            switch (data.type) {
                case Hls.ErrorTypes.NETWORK_ERROR:
                    showHlsWarning(element, t('stream_error_load', { details }));
                    restartHlsPlayback(element, details || describeMediaState(element));
                    break;
                case Hls.ErrorTypes.MEDIA_ERROR:
                    element.hlsMediaRecoverAttempts = (element.hlsMediaRecoverAttempts || 0) + 1;
                    showHlsWarning(element, t('media_recover'));

                    if (element.hlsMediaRecoverAttempts === 1) {
                        hls.recoverMediaError();
                        break;
                    }

                    if (element.hlsMediaRecoverAttempts === 2) {
                        if (typeof hls.swapAudioCodec === 'function') {
                            hls.swapAudioCodec();
                        }
                        hls.recoverMediaError();
                        break;
                    }

                    restartHlsPlayback(element, details || describeMediaState(element));
                    break;
                default:
                    updateVideoDebug(element, {
                        errorType: data.details || data.type || 'hls_error',
                        checkedAt: new Date().toLocaleString(),
                    });
                    restartHlsPlayback(element, data.details || data.type || 'hls_error');
                    break;
            }
        });

        hls.on(Hls.Events.MANIFEST_LOADING, function () {
            if (element.hlsInstance !== hls) {
                return;
            }

            showHlsInfo(element, t('loading_manifest'));
        });

        hls.on(Hls.Events.MEDIA_ATTACHED, function () {
            if (element.hlsInstance !== hls) {
                return;
            }

            releasePrimedPlayback(element, true);
            showHlsInfo(element, t('media_attached'));
        });

        hls.on(Hls.Events.MANIFEST_PARSED, function () {
            if (element.hlsInstance !== hls) {
                return;
            }

            element.hlsMediaRecoverAttempts = 0;
            showHlsSuccess(element, t('manifest_loaded'));
            scheduleDeferredPlay(element);
        });

        hls.on(Hls.Events.LEVEL_LOADING, function () {
            if (element.hlsInstance !== hls) {
                return;
            }

            showHlsInfo(element, t('loading_segments'));
        });

        hls.on(Hls.Events.LEVEL_LOADED, function () {
            if (element.hlsInstance !== hls) {
                return;
            }

            element.hlsMediaRecoverAttempts = 0;
            showHlsSuccess(element, t('stream_buffered'));
            scheduleDeferredPlay(element);
        });

        if (element.dataset.hlsMediaEventsBound === 'true') {
            return;
        }

        element.dataset.hlsMediaEventsBound = 'true';

        element.addEventListener('playing', function () {
            if (isPrimerPlaybackActive(element)) {
                showHlsInfo(element, t('primed_waiting'));
                return;
            }

            element.hlsMediaRecoverAttempts = 0;
            markPlaybackStarted(element);
        });

        element.addEventListener('loadedmetadata', function () {
            if (isPrimerPlaybackActive(element)) {
                return;
            }

            showHlsSuccess(element, t('metadata_loaded'));
            scheduleDeferredPlay(element);
        });

        element.addEventListener('loadeddata', function () {
            if (isPrimerPlaybackActive(element)) {
                return;
            }

            showHlsSuccess(element, t('first_video_data'));
            scheduleDeferredPlay(element);
        });

        element.addEventListener('canplay', function () {
            if (isPrimerPlaybackActive(element)) {
                return;
            }

            showHlsSuccess(element, t('stream_ready'));
            scheduleDeferredPlay(element);
        });

        element.addEventListener('pause', function () {
            if (element.hlsWarmupActive || !element.hlsPlaybackStarted || isPrimerPlaybackActive(element)) {
                return;
            }

            cleanupPlayRetryTimer(element);
            element.hlsAutoplayRequested = false;
        });
    };

    const prepareHlsPlayback = async function (element) {
        if (!element.hlsSource) {
            return false;
        }

        if (element.hlsMediaReady && element.hlsInstance) {
            return true;
        }

        if (element.hlsPreparePromise) {
            return element.hlsPreparePromise;
        }

        element.hlsPreparePromise = loadHlsScript().then(async function (Hls) {
            if (typeof Hls.isSupported === 'function' && !Hls.isSupported()) {
                throw new Error('This browser does not support HLS playback');
            }

            const isAwake = await ensureHlsSourceAwake(element, {
                emitStatus: Boolean(element.hlsAutoplayRequested),
                requirePlayIntent: Boolean(element.hlsAutoplayRequested),
            });

            if (!isAwake) {
                return false;
            }

            destroyHlsInstance(element);
            detachNativeHlsSource(element);

            const hls = new Hls({
                autoStartLoad: true,
                enableWorker: true,
                lowLatencyMode: false,
                liveSyncDurationCount: 3,
                liveMaxLatencyDurationCount: 8,
                maxLiveSyncPlaybackRate: 1,
                backBufferLength: 90,
                capLevelToPlayerSize: true,
                manifestLoadingMaxRetry: 3,
                manifestLoadingRetryDelay: 1000,
                manifestLoadingMaxRetryTimeout: 5000,
                manifestLoadingTimeOut: 8000,
                levelLoadingMaxRetry: 4,
                levelLoadingRetryDelay: 1000,
                levelLoadingMaxRetryTimeout: 5000,
                fragLoadingMaxRetry: 4,
                fragLoadingRetryDelay: 1000,
                fragLoadingMaxRetryTimeout: 5000,
                startFragPrefetch: true,
            });

            element.hlsInstance = hls;
            element.hlsMediaReady = true;
            bindHlsPlaybackEvents(element, hls);
            showHlsInfo(element, t('attaching_hls'));

            hls.loadSource(element.hlsSource);
            hls.attachMedia(element);

            return true;
        }).catch(function (error) {
            console.error('HLS init failed', error);
            updateVideoDebug(element, {
                errorType: 'hls_initialization_error',
                checkedAt: new Date().toLocaleString(),
            });
            showHlsError(element, t('hls_unavailable'));
            return false;
        }).finally(function () {
            element.hlsPreparePromise = null;
        });

        return element.hlsPreparePromise;
    };

    const wakeAndPlay = async function (element) {
        if (element.hlsWarmupActive) {
            return;
        }

        if (isMediaPlaybackActive(element)) {
            markPlaybackStarted(element);
            return;
        }

        if (!shouldUseNativeHls(element)) {
            forceDetachNativeHlsSource(element);
        }

        element.hlsAutoplayRequested = true;
        element.hlsPlaybackStarted = false;
        element.hlsPlayRetryStartedAt = Date.now();
        element.hlsStartupStartedAt = Date.now();
        element.dataset.hlsForceMutedAutoplay = 'true';
        element.hlsWarmupActive = true;
        showHlsInfo(element, t('first_play'));
        if (!shouldUseNativeHls(element)) {
            primePlaybackSession(element);
        }

        let isReady = false;

        try {
            wakeBackendStream(element);

            if (shouldUseNativeHls(element)) {
                isReady = await prepareNativeHlsPlayback(element);
                if (isReady) {
                    showHlsSuccess(element, t('native_available'));
                }
            } else {
                isReady = await prepareHlsPlayback(element);
            }
        } finally {
            element.hlsWarmupActive = false;
        }

        if (!isReady) {
            if (!shouldUseNativeHls(element)) {
                releasePrimedPlayback(element);
            }
            showHlsUnavailable(element, 'source_unavailable');
            return;
        }

        scheduleDeferredPlay(element, t('stream_initialized'));
    };

    const bootstrapPlayIntent = function (element) {
        if (element.dataset.hlsIntentBound === 'true') {
            return;
        }

        element.dataset.hlsIntentBound = 'true';

        element.addEventListener('play', function () {
            if (isPrimerPlaybackActive(element) || element.hlsWarmupActive || element.hlsAutoplayRequested) {
                return;
            }

            if (!element.hlsMediaReady && element.hlsSource) {
                element.pause();
                wakeAndPlay(element);
            }
        }, true);
    };

    const bindPlyrPlayButton = function (element) {
        if (element.dataset.hlsPlyrBound === 'true') {
            return;
        }

        const wrapper = element.closest('.plyr');
        if (!wrapper) {
            return;
        }

        element.dataset.hlsPlyrBound = 'true';

        const handleWakeGesture = function (event) {
            if (!element.hlsSource || element.hlsPlaybackStarted) {
                return;
            }

            if (!(event.target instanceof Element)) {
                return;
            }

            const gestureTarget = event.target.closest('[data-plyr="play"], .plyr__control--overlaid, .plyr__poster, .plyr__video-wrapper, video');
            if (!gestureTarget) {
                return;
            }

            if (element.hlsWarmupActive) {
                event.preventDefault();
                event.stopPropagation();
                attemptDeferredPlay(element);
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            wakeAndPlay(element);
        };

        wrapper.addEventListener('click', handleWakeGesture, true);
    };

    const getZoomLocale = function () {
        const lang = (document.documentElement.getAttribute('lang') || 'en').toLowerCase();

        if (lang.startsWith('ru')) {
            return {
                in: 'Увеличить',
                out: 'Уменьшить',
                reset: 'Сбросить zoom',
            };
        }

        return {
            in: 'Zoom in',
            out: 'Zoom out',
            reset: 'Reset zoom',
        };
    };

    const createPlyrZoomIcon = function (type) {
        const common = 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"';

        if (type === 'out') {
            return '<svg aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path ' + common + ' d="M11 19a8 8 0 1 1 5.66-2.34L21 21"/><path ' + common + ' d="M8 11h6"/></svg>';
        }

        if (type === 'reset') {
            return '<svg aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path ' + common + ' d="M3 12a9 9 0 1 0 3-6.7"/><path ' + common + ' d="M3 4v6h6"/></svg>';
        }

        return '<svg aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path ' + common + ' d="M11 19a8 8 0 1 1 5.66-2.34L21 21"/><path ' + common + ' d="M11 8v6"/><path ' + common + ' d="M8 11h6"/></svg>';
    };

    const initPlyrZoom = function (element) {
        if (!(element instanceof HTMLVideoElement)) {
            return;
        }

        const playerRoot = element.closest('.plyr');
        const videoWrapper = playerRoot ? playerRoot.querySelector('.plyr__video-wrapper') : null;
        const controls = playerRoot ? playerRoot.querySelector('.plyr__controls') : null;

        if (!playerRoot || !videoWrapper || !controls || playerRoot.dataset.plyrZoomInitialized === 'true') {
            return;
        }

        playerRoot.dataset.plyrZoomInitialized = 'true';
        playerRoot.dataset.plyrZoomReady = 'true';
        element.dataset.plyrZoomable = 'true';

        const labels = getZoomLocale();
        const levels = [1, 1.25, 1.5, 2, 2.5, 3];
        const minScale = levels[0];
        const maxScale = levels[levels.length - 1];
        const activePointers = new Map();
        const state = {
            index: 0,
            scale: 1,
            x: 0,
            y: 0,
            dragging: false,
            pinching: false,
            startX: 0,
            startY: 0,
            pointerX: 0,
            pointerY: 0,
            pinchStartDistance: 0,
            pinchStartScale: 1,
            pinchStartX: 0,
            pinchStartY: 0,
            pinchStartLocalX: 0,
            pinchStartLocalY: 0,
        };

        const makeButton = function (type, label) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'plyr__control plyr__controls__item';
            button.dataset.plyrZoom = type;
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
            button.innerHTML = createPlyrZoomIcon(type) + '<span class="plyr__tooltip">' + label + '</span>';
            return button;
        };

        const zoomOutButton = makeButton('out', labels.out);
        const zoomResetButton = makeButton('reset', labels.reset);
        const zoomInButton = makeButton('in', labels.in);
        const zoomButtons = [zoomOutButton, zoomResetButton, zoomInButton];
        const fullscreenButton = controls.querySelector('[data-plyr="fullscreen"]');

        zoomButtons.forEach(function (button) {
            controls.insertBefore(button, fullscreenButton || null);
        });

        const getScale = function () {
            return state.scale || 1;
        };

        const clampOffsets = function () {
            const scale = getScale();

            if (scale <= 1) {
                state.x = 0;
                state.y = 0;
                return;
            }

            const maxX = videoWrapper.clientWidth * (scale - 1) / 2;
            const maxY = videoWrapper.clientHeight * (scale - 1) / 2;
            state.x = Math.max(-maxX, Math.min(maxX, state.x));
            state.y = Math.max(-maxY, Math.min(maxY, state.y));
        };

        const updateButtons = function () {
            const scale = getScale();
            zoomOutButton.disabled = scale <= minScale + 0.01;
            zoomResetButton.disabled = scale <= minScale + 0.01;
            zoomInButton.disabled = scale >= maxScale - 0.01;
        };

        const applyZoom = function () {
            const scale = getScale();
            clampOffsets();

            if (scale <= 1) {
                element.style.transform = '';
            } else {
                element.style.transform = 'translate3d(' + state.x + 'px, ' + state.y + 'px, 0) scale(' + scale + ')';
            }

            playerRoot.classList.toggle('is-plyr-zoomed', scale > 1);
            updateButtons();
        };

        const setZoomIndex = function (index) {
            state.index = Math.max(0, Math.min(levels.length - 1, index));
            state.scale = levels[state.index] || 1;
            applyZoom();
        };

        const syncZoomIndex = function () {
            let closestIndex = 0;
            let closestDistance = Math.abs(levels[0] - getScale());

            levels.forEach(function (level, index) {
                const distance = Math.abs(level - getScale());
                if (distance < closestDistance) {
                    closestIndex = index;
                    closestDistance = distance;
                }
            });

            state.index = closestIndex;
        };

        const setZoomScale = function (scale) {
            state.scale = Math.max(minScale, Math.min(maxScale, scale));

            if (state.scale <= minScale + 0.01) {
                state.scale = minScale;
                state.x = 0;
                state.y = 0;
            }

            syncZoomIndex();
            applyZoom();
        };

        const getNextScale = function () {
            const scale = getScale();
            return levels.find(function (level) {
                return level > scale + 0.01;
            }) || maxScale;
        };

        const getPreviousScale = function () {
            const scale = getScale();
            return levels.slice().reverse().find(function (level) {
                return level < scale - 0.01;
            }) || minScale;
        };

        const getPointerDistance = function (first, second) {
            const deltaX = first.clientX - second.clientX;
            const deltaY = first.clientY - second.clientY;
            return Math.sqrt((deltaX * deltaX) + (deltaY * deltaY));
        };

        const getPointerCenter = function (first, second) {
            return {
                clientX: (first.clientX + second.clientX) / 2,
                clientY: (first.clientY + second.clientY) / 2,
            };
        };

        const getLocalPoint = function (point) {
            const rect = videoWrapper.getBoundingClientRect();
            return {
                x: point.clientX - rect.left - (rect.width / 2),
                y: point.clientY - rect.top - (rect.height / 2),
            };
        };

        const getTrackedPointers = function () {
            return Array.from(activePointers.values());
        };

        const startPanning = function (pointer) {
            if (!pointer || getScale() <= 1) {
                return;
            }

            state.dragging = true;
            state.pinching = false;
            state.startX = state.x;
            state.startY = state.y;
            state.pointerX = pointer.clientX;
            state.pointerY = pointer.clientY;
            videoWrapper.classList.add('is-panning');
        };

        const startPinching = function () {
            const pointers = getTrackedPointers();
            if (pointers.length < 2) {
                return;
            }

            const first = pointers[0];
            const second = pointers[1];
            const center = getPointerCenter(first, second);
            const local = getLocalPoint(center);

            state.dragging = false;
            state.pinching = true;
            state.pinchStartDistance = Math.max(1, getPointerDistance(first, second));
            state.pinchStartScale = getScale();
            state.pinchStartX = state.x;
            state.pinchStartY = state.y;
            state.pinchStartLocalX = local.x;
            state.pinchStartLocalY = local.y;
            videoWrapper.classList.add('is-panning');
        };

        const updatePinchZoom = function () {
            const pointers = getTrackedPointers();
            if (!state.pinching || pointers.length < 2 || state.pinchStartDistance <= 0) {
                return;
            }

            const first = pointers[0];
            const second = pointers[1];
            const center = getPointerCenter(first, second);
            const local = getLocalPoint(center);
            const nextScale = Math.max(
                minScale,
                Math.min(maxScale, state.pinchStartScale * (getPointerDistance(first, second) / state.pinchStartDistance))
            );

            if (nextScale <= minScale + 0.01) {
                state.x = 0;
                state.y = 0;
                setZoomScale(minScale);
                return;
            }

            const contentX = (state.pinchStartLocalX - state.pinchStartX) / state.pinchStartScale;
            const contentY = (state.pinchStartLocalY - state.pinchStartY) / state.pinchStartScale;
            state.scale = nextScale;
            state.x = local.x - (contentX * nextScale);
            state.y = local.y - (contentY * nextScale);
            syncZoomIndex();
            applyZoom();
        };

        const capturePointer = function (event) {
            if (typeof videoWrapper.setPointerCapture !== 'function') {
                return;
            }

            try {
                videoWrapper.setPointerCapture(event.pointerId);
            } catch (error) {
                // Pointer capture can be unavailable for a finished pointer.
            }
        };

        const releasePointer = function (event) {
            if (typeof videoWrapper.releasePointerCapture !== 'function') {
                return;
            }

            try {
                videoWrapper.releasePointerCapture(event.pointerId);
            } catch (error) {
                // Pointer capture may already be released by the browser.
            }
        };

        zoomInButton.addEventListener('click', function () {
            setZoomScale(getNextScale());
        });

        zoomOutButton.addEventListener('click', function () {
            setZoomScale(getPreviousScale());
        });

        zoomResetButton.addEventListener('click', function () {
            state.x = 0;
            state.y = 0;
            setZoomScale(minScale);
        });

        videoWrapper.addEventListener('pointerdown', function (event) {
            if (!(event.target instanceof Element)) {
                return;
            }

            if (event.target.closest('.plyr__controls, .plyr__control, button, a, input, select, textarea')) {
                return;
            }

            activePointers.set(event.pointerId, {
                clientX: event.clientX,
                clientY: event.clientY,
            });
            capturePointer(event);

            if (activePointers.size >= 2) {
                startPinching();
                event.preventDefault();
                return;
            }

            if (getScale() > 1) {
                startPanning({ clientX: event.clientX, clientY: event.clientY });
            }
        });

        videoWrapper.addEventListener('pointermove', function (event) {
            if (activePointers.has(event.pointerId)) {
                activePointers.set(event.pointerId, {
                    clientX: event.clientX,
                    clientY: event.clientY,
                });
            }

            if (state.pinching) {
                updatePinchZoom();
                event.preventDefault();
                return;
            }

            if (!state.dragging) {
                return;
            }

            state.x = state.startX + (event.clientX - state.pointerX);
            state.y = state.startY + (event.clientY - state.pointerY);
            applyZoom();
            event.preventDefault();
        });

        const stopPanning = function (event) {
            activePointers.delete(event.pointerId);
            releasePointer(event);

            if (state.pinching && activePointers.size >= 2) {
                startPinching();
                return;
            }

            if (state.pinching && activePointers.size === 1) {
                state.pinching = false;
                startPanning(getTrackedPointers()[0]);
                return;
            }

            state.dragging = false;
            state.pinching = false;
            videoWrapper.classList.remove('is-panning');
        };

        videoWrapper.addEventListener('pointerup', stopPanning);
        videoWrapper.addEventListener('pointercancel', stopPanning);
        window.addEventListener('resize', applyZoom);
        element.addEventListener('emptied', function () {
            state.x = 0;
            state.y = 0;
            setZoomIndex(0);
        });

        applyZoom();
    };

    const attachHls = function (element) {
        cleanupHlsLazyWait(element);
        cleanupHlsReconnectTimers(element);

        const hlsSource = getHlsSource(element);
        if (!hlsSource) {
            return Promise.resolve();
        }

        element.hlsSource = hlsSource;
        normalizeHlsPoster(element, hlsSource);
        element.hlsAutoplayRequested = false;
        element.hlsMediaReady = false;
        element.hlsPreparePromise = null;
        element.hlsSourcePrewarmed = false;
        element.hlsSourcePrewarmedAt = 0;
        element.hlsBackgroundWarmupPromise = null;
        element.hlsPlayRetryStartedAt = 0;
        element.hlsStuckSince = 0;
        element.hlsLastRestartAt = 0;
        element.hlsRestarting = false;
        element.hlsPlaybackStarted = false;
        element.hlsEverPlayed = false;
        element.dataset.hlsNativeSourceDetached = 'false';
        element.dataset.hlsPrimerActive = 'false';
        element.dataset.hlsForceJsPlayback = 'false';
        element.hlsWarmupActive = false;
        syncFullVolume(element, !element.muted);
        bootstrapPlayIntent(element);
        bindNativePlaybackEvents(element);
        bindHlsRemovalCleanup(element);
        startHlsHealthMonitor(element);
        clearHlsMessage(element);
        detachNativeHlsSource(element);
        updateVideoDebug(element, {
            source: hlsSource,
            poster: element.getAttribute('poster') || element.dataset.poster || '',
            sourceType: 'hls',
            playbackMode: getPlaybackMode(element),
        });

        return Promise.resolve();
    };

    const initPlyrPlayers = function () {
        if (typeof window.Plyr !== 'function') {
            return;
        }

        const getDocumentFullscreenElement = function () {
            return document.fullscreenElement
                || document.webkitFullscreenElement
                || document.mozFullScreenElement
                || document.msFullscreenElement
                || null;
        };

        const getPlayerRoot = function (element) {
            return element ? element.closest('.plyr') : null;
        };

        const getPlayerWrap = function (element) {
            const playerRoot = getPlayerRoot(element);
            return playerRoot ? playerRoot.closest('[data-plyr-player-wrap]') : (element ? element.closest('[data-plyr-player-wrap]') : null);
        };

        const getPlyrRootFromNode = function (node) {
            if (!(node instanceof Element)) {
                return null;
            }

            if (node.matches('.plyr')) {
                return node;
            }

            return (typeof node.closest === 'function' && node.closest('.plyr'))
                || (typeof node.querySelector === 'function' && node.querySelector('.plyr'))
                || null;
        };

        const syncFullscreenUiState = function () {
            const fullscreenPlyr = getPlyrRootFromNode(getDocumentFullscreenElement())
                || document.querySelector('.plyr.plyr--fullscreen, .plyr.plyr--fullscreen-active, .plyr.plyr--fullscreen-fallback, [data-plyr-player-wrap].plyr--fullscreen-fallback .plyr');
            const activeNodes = [];

            if (fullscreenPlyr) {
                activeNodes.push(fullscreenPlyr);

                const fullscreenWrap = fullscreenPlyr.closest('[data-plyr-player-wrap]');
                if (fullscreenWrap) {
                    activeNodes.push(fullscreenWrap);
                }
            }

            document.querySelectorAll('.plyr.is-site-plyr-fullscreen, [data-plyr-player-wrap].is-site-plyr-fullscreen').forEach(function (node) {
                if (activeNodes.indexOf(node) === -1) {
                    node.classList.remove('is-site-plyr-fullscreen');
                }
            });

            activeNodes.forEach(function (node) {
                if (!node.classList.contains('is-site-plyr-fullscreen')) {
                    node.classList.add('is-site-plyr-fullscreen');
                }
            });

            document.body.classList.toggle('is-site-fullscreen', Boolean(fullscreenPlyr));
            document.documentElement.classList.toggle('is-site-fullscreen', Boolean(fullscreenPlyr));
        };

        const setNativeVideoFullscreenState = function (element, enabled) {
            const playerRoot = getPlayerRoot(element);
            const playerWrap = getPlayerWrap(element);

            [playerRoot, playerWrap].forEach(function (node) {
                if (node) {
                    node.classList.toggle('is-site-plyr-fullscreen', enabled);
                }
            });

            document.body.classList.toggle('is-site-fullscreen', enabled);
            document.documentElement.classList.toggle('is-site-fullscreen', enabled);
        };

        const bindFullscreenStateSync = function (element) {
            const playerRoot = getPlayerRoot(element);
            if (!playerRoot || playerRoot.dataset.siteFullscreenStateBound === 'true') {
                return;
            }

            playerRoot.dataset.siteFullscreenStateBound = 'true';
            playerRoot.classList.add('plyr--fullscreen-enabled');

            if (element.plyr && typeof element.plyr.on === 'function') {
                element.plyr.on('enterfullscreen', syncFullscreenUiState);
                element.plyr.on('exitfullscreen', syncFullscreenUiState);
            }

            element.addEventListener('webkitbeginfullscreen', function () {
                setNativeVideoFullscreenState(element, true);
            });
            element.addEventListener('webkitendfullscreen', function () {
                setNativeVideoFullscreenState(element, false);
                syncFullscreenUiState();
            });

            if (typeof MutationObserver === 'function') {
                const fullscreenObserver = new MutationObserver(syncFullscreenUiState);
                fullscreenObserver.observe(playerRoot, { attributes: true, attributeFilter: ['class'] });
            }
        };

        if (document.documentElement.dataset.sitePlyrFullscreenStateBound !== 'true') {
            document.documentElement.dataset.sitePlyrFullscreenStateBound = 'true';
            document.addEventListener('fullscreenchange', syncFullscreenUiState);
            document.addEventListener('webkitfullscreenchange', syncFullscreenUiState);
            document.addEventListener('mozfullscreenchange', syncFullscreenUiState);
            document.addEventListener('MSFullscreenChange', syncFullscreenUiState);
        }

        document.querySelectorAll('[data-plyr-player], .post-content video, .post-content audio, [data-plyr-player-wrap] video, [data-plyr-player-wrap] audio').forEach(function (element) {
            if (!element.hasAttribute('data-plyr-player')) {
                element.setAttribute('data-plyr-player', '');
            }

            element = createCleanMediaElement(element);

            if (element.dataset.plyrInitialized === 'true') {
                return;
            }

            let options = {};
            const rawOptions = element.getAttribute('data-plyr-options');
            const playerWrap = element.closest('[data-plyr-player-wrap]');
            const isAudioPlayer = element instanceof HTMLAudioElement;

            if (playerWrap && isAudioPlayer) {
                playerWrap.dataset.plyrMedia = 'audio';
            }

            if (rawOptions) {
                try {
                    options = JSON.parse(rawOptions);
                } catch (error) {
                    console.warn('Invalid data-plyr-options JSON', error);
                }
            }

            options = Object.assign({
                iconUrl: plyrAssetBase + '/plyr.svg',
                blankVideo: plyrAssetBase + '/blank.mp4',
                ratio: '16:9',
                volume: 1,
                muted: false,
                fullscreen: {
                    enabled: true,
                    fallback: true,
                    iosNative: false,
                    container: '.plyr',
                },
            }, options);

            if (isAudioPlayer && !Array.isArray(options.controls)) {
                options.controls = ['play', 'progress', 'current-time', 'duration', 'mute', 'volume'];
            }

            options.fullscreen = Object.assign({
                enabled: true,
                fallback: true,
                iosNative: false,
                container: '.plyr',
            }, options.fullscreen || {});
            options.fullscreen.enabled = true;
            options.fullscreen.fallback = true;
            options.fullscreen.iosNative = false;
            options.fullscreen.container = options.fullscreen.container || '.plyr';
            element.fbPlyrOptions = options;

            const finalizePlyr = function () {
                element.dataset.plyrInitialized = 'true';
                try {
                    element.plyr = new window.Plyr(element, options);
                } catch (error) {
                    console.error('Plyr initialization failed', error);
                    if (element instanceof HTMLVideoElement) {
                        updateVideoDebug(element, {
                            errorType: 'plyr_initialization_error',
                            checkedAt: new Date().toLocaleString(),
                        });
                        ensureVideoDebugBlock(element);
                    }
                    return;
                }
                syncFullVolume(element, !isPrimerPlaybackActive(element));

                ['loadedmetadata', 'loadeddata', 'canplay', 'play', 'playing'].forEach(function (eventName) {
                    element.addEventListener(eventName, function () {
                        syncFullVolume(element, !isPrimerPlaybackActive(element));
                    });
                });

                if (!shouldUseNativeHls(element)) {
                    forceDetachNativeHlsSource(element);
                }

                bindPlyrPlayButton(element);
                initPlyrZoom(element);
                if (element instanceof HTMLVideoElement) {
                    bindFullscreenStateSync(element);
                    ensureVideoDebugBlock(element);
                }
            };

            attachHls(element).finally(finalizePlyr);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPlyrPlayers, { once: true });
        return;
    }

    initPlyrPlayers();
})();
