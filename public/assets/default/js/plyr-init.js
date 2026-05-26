(function () {
    const plyrAssetBase = (typeof baseUrl === 'string' ? baseUrl : '') + '/assets/default/vendor/plyr';
    const hlsAssetBase = (typeof baseUrl === 'string' ? baseUrl : '') + '/assets/default/vendor/hls.js';
    const plyrBlankVideoUrl = plyrAssetBase + '/blank.mp4';
    const hlsScriptUrl = hlsAssetBase + '/hls.min.js';
    const hlsWarmupDurationMs = 15000;
    const hlsRetryDelayMs = 2000;
    const hlsPlayRetryDurationMs = 12000;
    const hlsPlayRetryDelayMs = 1000;
    const hlsHealthCheckIntervalMs = 2000;
    const hlsStuckResetAfterMs = 6000;
    const hlsRestartCooldownMs = 10000;
    const hlsPrewarmTtlMs = 45000;
    const hlsStartTimeEpsilon = 0.08;
    const canViewVideoStatus = window.canViewVideoStatus === true;
    let hlsScriptPromise = null;
    const hlsLocale = ((document.documentElement.getAttribute('lang') || 'en').toLowerCase().startsWith('ru')) ? 'ru' : 'en';
    const hlsMessages = {
        ru: {
            holding_session: 'Сохраняем сессию воспроизведения, пока поток просыпается...',
            preparing_native: 'Подготавливаем native HLS воспроизведение...',
            stream_playing: 'Поток воспроизводится.',
            checking_attempt: 'Проверяем доступность потока... попытка {attempt}',
            manifest_available: 'Манифест потока доступен. Инициализируем плеер...',
            stream_responded: 'Поток ответил. Инициализируем плеер...',
            stream_sleeping: 'Поток еще спит. Ждем перед повторной попыткой...',
            playback_pending: 'Поток готов, но воспроизведение еще не началось. {state}',
            native_metadata: 'Метаданные native HLS загружены. Повторяем запуск...',
            native_first_data: 'Native HLS получил первые данные видео. Повторяем запуск...',
            native_ready: 'Native HLS готов к воспроизведению.',
            native_error: 'Ошибка native HLS. {state}',
            native_stalled: 'Native HLS остановился. Перезагружаем поток...',
            native_fallback: 'Native HLS не стартовал. Переключаемся на HLS.js...',
            stream_error_load: 'Ошибка потока: не удалось загрузить HLS источник{details}',
            stream_restarting: 'Поток завис в состоянии загрузки. Пересоздаем HLS... {state}',
            media_recover: 'Обнаружена ошибка медиа. Пытаемся восстановить...',
            stream_error_play: 'Ошибка потока: не удалось воспроизвести HLS источник{details}',
            loading_manifest: 'Загружаем манифест потока...',
            media_attached: 'HLS подключен к видеоэлементу. Переключаемся с прогрева на live поток...',
            manifest_loaded: 'Манифест загружен. Запускаем воспроизведение...',
            loading_segments: 'Загружаем сегменты потока...',
            stream_buffered: 'Поток буферизован. Ожидаем воспроизведение...',
            primed_waiting: 'Сессия воспроизведения подготовлена. Ждем live поток...',
            metadata_loaded: 'Метаданные потока загружены. Повторяем запуск...',
            first_video_data: 'Получены первые данные видео. Повторяем запуск...',
            stream_ready: 'Поток готов к воспроизведению.',
            attaching_hls: 'Подключаем HLS к видеоэлементу...',
            hls_unavailable: 'Ошибка потока: HLS воспроизведение недоступно',
            first_play: 'Первое нажатие Play получено. Будим поток...',
            native_available: 'Native HLS доступен. Запускаем воспроизведение...',
            source_timeout: 'Ошибка потока: источник не успел проснуться',
            stream_initialized: 'Поток инициализирован. Запрашиваем воспроизведение...'
        },
        en: {
            holding_session: 'Holding playback session while stream wakes...',
            preparing_native: 'Preparing native HLS playback...',
            stream_playing: 'Stream is playing.',
            checking_attempt: 'Checking stream availability... attempt {attempt}',
            manifest_available: 'Stream manifest is available. Initializing player...',
            stream_responded: 'Stream responded. Initializing player...',
            stream_sleeping: 'Stream is still sleeping. Waiting before retry...',
            playback_pending: 'Stream is ready but playback is still pending. {state}',
            native_metadata: 'Native HLS metadata loaded. Retrying playback...',
            native_first_data: 'Native HLS received first video data. Retrying playback...',
            native_ready: 'Native HLS is ready to play.',
            native_error: 'Native HLS error. {state}',
            native_stalled: 'Native HLS stalled. Reloading stream...',
            native_fallback: 'Native HLS did not start. Switching to HLS.js...',
            stream_error_load: 'Stream error: failed to load HLS source{details}',
            stream_restarting: 'Stream is stuck while loading. Recreating HLS... {state}',
            media_recover: 'Media error detected. Trying to recover...',
            stream_error_play: 'Stream error: failed to play HLS source{details}',
            loading_manifest: 'Loading stream manifest...',
            media_attached: 'HLS media attached. Switching from wake-up playback to live stream...',
            manifest_loaded: 'Manifest loaded. Starting playback...',
            loading_segments: 'Loading stream segments...',
            stream_buffered: 'Stream buffered. Waiting for playback...',
            primed_waiting: 'Playback session primed. Waiting for live stream...',
            metadata_loaded: 'Stream metadata loaded. Retrying playback...',
            first_video_data: 'First video data received. Retrying playback...',
            stream_ready: 'Stream is ready to play.',
            attaching_hls: 'Attaching HLS to video element...',
            hls_unavailable: 'Stream error: HLS playback is unavailable',
            first_play: 'First play captured. Waking stream...',
            native_available: 'Native HLS is available. Starting playback...',
            source_timeout: 'Stream error: source did not wake up in time',
            stream_initialized: 'Stream initialized. Requesting playback...'
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

    const shouldUseNativeHls = function (element) {
        if (element && element.dataset && element.dataset.hlsForceJsPlayback === 'true') {
            return false;
        }

        if (!canPlayHlsNatively(element)) {
            return false;
        }

        const userAgent = window.navigator.userAgent || '';
        const vendor = window.navigator.vendor || '';
        const isSafari = /Safari/i.test(userAgent)
            && /Apple/i.test(vendor)
            && !/Chrome|CriOS|Chromium|Edg|OPR|YaBrowser|FxiOS|DuckDuckGo/i.test(userAgent);
        const isIosLike = /iPad|iPhone|iPod/i.test(userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

        if (isIosLike) {
            return true;
        }

        if (!isSafari) {
            return false;
        }

        // Prefer hls.js on desktop Safari because this stream fails in native HLS,
        // while keeping native playback as a fallback on iOS where MSE support varies.
        if (canUseHlsJsPlayback()) {
            return false;
        }

        return true;
    };

    const isHlsUrl = function (url) {
        return /\.m3u8(?:$|\?)/i.test(url || '');
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
        if (!(element instanceof HTMLMediaElement) || shouldUseNativeHls(element)) {
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
        if (!canViewVideoStatus) {
            return;
        }

        const container = element.closest('[data-plyr-player-wrap]') || element.parentElement;
        if (!container) {
            console.error(message);
            return;
        }

        let messageNode = container.querySelector('[data-plyr-hls-message]');
        if (!messageNode) {
            messageNode = document.createElement('div');
            messageNode.dataset.plyrHlsMessage = 'true';
            messageNode.className = 'alert mt-3 mb-0 fb-plyr-hls-message';
            container.appendChild(messageNode);
        }

        messageNode.style.removeProperty('height');
        messageNode.style.removeProperty('overflow');
        messageNode.style.removeProperty('display');
        messageNode.style.removeProperty('align-items');

        const classMap = {
            info: 'alert-secondary',
            success: 'alert-success',
            warning: 'alert-warning',
            error: 'alert-danger',
        };

        messageNode.className = 'alert mt-3 mb-0 fb-plyr-hls-message ' + (classMap[type] || classMap.info);
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

    const clearHlsMessage = function (element) {
        if (!canViewVideoStatus) {
            return;
        }

        const container = element.closest('[data-plyr-player-wrap]') || element.parentElement;
        const messageNode = container ? container.querySelector('[data-plyr-hls-message]') : null;
        if (messageNode) {
            messageNode.className = 'alert mt-3 mb-0 fb-plyr-hls-message alert-secondary';
            messageNode.hidden = true;
            messageNode.textContent = '';
        }
    };

    const sleep = function (ms) {
        return new Promise(function (resolve) {
            setTimeout(resolve, ms);
        });
    };

    const getMediaStateLabel = function (value, map) {
        return Object.prototype.hasOwnProperty.call(map, value) ? map[value] : 'unknown';
    };

    const describeMediaState = function (element) {
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

    const prepareNativeHlsPlayback = function (element) {
        if (element.getAttribute('src') !== element.hlsSource) {
            element.setAttribute('src', element.hlsSource);
        }

        element.load();
        element.hlsMediaReady = true;
        showHlsInfo(element, t('preparing_native'));
    };

    const markPlaybackStarted = function (element) {
        cleanupPlayRetryTimer(element);
        element.hlsPlayRetryStartedAt = 0;
        element.hlsStuckSince = 0;
        element.hlsPlaybackStarted = true;
        syncFullVolume(element, true);
        showHlsSuccess(element, t('stream_playing'));

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

    const urlExists = function (url, method) {
        return new Promise(function (resolve) {
            let settled = false;
            const xhr = new XMLHttpRequest();

            const finish = function (result) {
                if (settled) {
                    return;
                }

                settled = true;
                resolve(result);
            };

            xhr.open(method, url, true);
            xhr.timeout = 4000;
            xhr.onreadystatechange = function () {
                if (xhr.readyState !== 4) {
                    return;
                }

                if (xhr.status >= 200 && xhr.status < 400 && xhr.status !== 404) {
                    finish(true);
                    return;
                }

                finish(false);
            };
            xhr.onerror = function () {
                finish(false);
            };
            xhr.ontimeout = function () {
                finish(false);
            };

            try {
                xhr.send();
            } catch (error) {
                finish(false);
            }
        });
    };

    const waitForHlsSource = async function (element, url, options) {
        const settings = Object.assign({
            requirePlayIntent: true,
            emitStatus: true,
        }, options || {});
        const startedAt = Date.now();
        let attempt = 0;

        while ((Date.now() - startedAt) < hlsWarmupDurationMs) {
            if (settings.requirePlayIntent && !element.hlsAutoplayRequested) {
                return false;
            }

            attempt += 1;
            if (settings.emitStatus) {
                showHlsInfo(element, t('checking_attempt', { attempt }));
            }

            const headExists = await urlExists(url, 'HEAD');
            if (headExists) {
                if (settings.emitStatus) {
                    showHlsSuccess(element, t('manifest_available'));
                }
                return true;
            }

            const getExists = await urlExists(url, 'GET');
            if (getExists) {
                if (settings.emitStatus) {
                    showHlsSuccess(element, t('stream_responded'));
                }
                return true;
            }

            if (settings.emitStatus) {
                showHlsWarning(element, t('stream_sleeping'));
            }
            await sleep(hlsRetryDelayMs);
        }

        return false;
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
        releasePrimedPlayback(element);
        resetMediaSource(element);
        element.hlsInstance = null;
        element.hlsMediaReady = false;
        element.hlsPlaybackStarted = false;
        element.hlsMediaRecoverAttempts = 0;
        element.dataset.hlsPlaybackBound = 'false';
    };

    const resetNativePlaybackState = function (element) {
        cleanupPlayRetryTimer(element);
        cleanupWarmupTimer(element);
        releasePrimedPlayback(element);
        element.hlsMediaReady = false;
        element.hlsPlaybackStarted = false;
        element.hlsMediaRecoverAttempts = 0;
        resetMediaSource(element);
    };

    const fallbackToHlsJsPlayback = async function (element, reason) {
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
        showHlsWarning(element, t('stream_restarting', { state: reason || describeMediaState(element) }));

        if (shouldUseNativeHls(element)) {
            resetNativePlaybackState(element);
            element.hlsAutoplayRequested = true;
            prepareNativeHlsPlayback(element);
            scheduleDeferredPlay(element, t('stream_initialized'));
            element.hlsRestarting = false;
            return;
        }

        destroyHlsInstance(element);
        element.hlsAutoplayRequested = true;
        prepareHlsPlayback(element).then(function (isReady) {
            if (!isReady) {
                showHlsError(element, t('source_timeout'));
                return;
            }

            scheduleDeferredPlay(element, t('stream_initialized'));
        }).finally(function () {
            element.hlsRestarting = false;
        });
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

            if (!element.hlsPlayRetryStartedAt) {
                element.hlsPlayRetryStartedAt = Date.now();
            }

            if ((Date.now() - element.hlsPlayRetryStartedAt) > hlsPlayRetryDurationMs) {
                const state = describeMediaState(element);
                fallbackToHlsJsPlayback(element, state).then(function (switched) {
                    if (!switched) {
                        showHlsError(element, t('native_error', { state }));
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
                prepareNativeHlsPlayback(element);
                attemptDeferredPlay(element);
            }, hlsRetryDelayMs);
        });

        element.addEventListener('stalled', function () {
            if (!shouldUseNativeHls(element) || !element.hlsAutoplayRequested || element.hlsPlaybackStarted) {
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
                    showHlsError(element, t('stream_error_play', { details }));
                    destroyHlsInstance(element);
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

            const sourcePrewarmedFresh = element.hlsSourcePrewarmed
                && element.hlsSourcePrewarmedAt
                && (Date.now() - element.hlsSourcePrewarmedAt) < hlsPrewarmTtlMs;
            const sourceReady = sourcePrewarmedFresh
                ? true
                : (element.hlsBackgroundWarmupPromise
                    ? await element.hlsBackgroundWarmupPromise
                    : await waitForHlsSource(element, element.hlsSource));
            if (!sourceReady) {
                return false;
            }

            element.hlsSourcePrewarmed = true;
            element.hlsSourcePrewarmedAt = Date.now();
            destroyHlsInstance(element);
            detachNativeHlsSource(element);

            const hls = new Hls({
                autoStartLoad: true,
                enableWorker: true,
                lowLatencyMode: true,
                backBufferLength: 90,
                capLevelToPlayerSize: true,
                manifestLoadingMaxRetry: 3,
                manifestLoadingTimeOut: 15000,
                levelLoadingMaxRetry: 4,
                fragLoadingMaxRetry: 4,
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

        if (!shouldUseNativeHls(element)) {
            forceDetachNativeHlsSource(element);
        }

        element.hlsAutoplayRequested = true;
        element.hlsPlaybackStarted = false;
        element.hlsPlayRetryStartedAt = Date.now();
        element.dataset.hlsForceMutedAutoplay = 'true';
        element.hlsWarmupActive = true;
        showHlsInfo(element, t('first_play'));
        if (!shouldUseNativeHls(element)) {
            primePlaybackSession(element);
        }

        let isReady = false;

        try {
            if (shouldUseNativeHls(element)) {
                prepareNativeHlsPlayback(element);
                showHlsSuccess(element, t('native_available'));
                isReady = true;
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
            showHlsError(element, t('source_timeout'));
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
        const hlsSource = getHlsSource(element);
        if (!hlsSource) {
            return Promise.resolve();
        }

        element.hlsSource = hlsSource;
        element.hlsAutoplayRequested = false;
        element.hlsMediaReady = shouldUseNativeHls(element);
        element.hlsPreparePromise = null;
        element.hlsSourcePrewarmed = false;
        element.hlsSourcePrewarmedAt = 0;
        element.hlsBackgroundWarmupPromise = null;
        element.hlsPlayRetryStartedAt = 0;
        element.hlsStuckSince = 0;
        element.hlsLastRestartAt = 0;
        element.hlsRestarting = false;
        element.hlsPlaybackStarted = false;
        element.dataset.hlsNativeSourceDetached = 'false';
        element.dataset.hlsPrimerActive = 'false';
        element.dataset.hlsForceJsPlayback = 'false';
        element.hlsWarmupActive = false;
        syncFullVolume(element, !element.muted);
        bootstrapPlayIntent(element);
        bindNativePlaybackEvents(element);
        startHlsHealthMonitor(element);
        clearHlsMessage(element);

        if (!shouldUseNativeHls(element)) {
            detachNativeHlsSource(element);
        }

        prewarmHlsSource(element);

        if (!shouldUseNativeHls(element)) {
            loadHlsScript().catch(function (error) {
                console.error('Preload HLS init failed', error);
            });
        }

        return Promise.resolve();
    };

    const initPlyrPlayers = function () {
        if (typeof window.Plyr !== 'function') {
            return;
        }

        const syncFullscreenUiState = function () {
            const fullscreenElement = document.fullscreenElement || document.webkitFullscreenElement || null;
            const fullscreenPlyr = fullscreenElement
                ? (fullscreenElement.matches && fullscreenElement.matches('.plyr') ? fullscreenElement : fullscreenElement.querySelector && fullscreenElement.querySelector('.plyr'))
                : document.querySelector('.plyr.plyr--fullscreen, .plyr.plyr--fullscreen-fallback');

            document.body.classList.toggle('is-site-fullscreen', Boolean(fullscreenPlyr));
        };

        document.addEventListener('fullscreenchange', syncFullscreenUiState);
        document.addEventListener('webkitfullscreenchange', syncFullscreenUiState);

        document.querySelectorAll('[data-plyr-player]').forEach(function (element) {
            element = createCleanMediaElement(element);

            if (element.dataset.plyrInitialized === 'true') {
                return;
            }

            let options = {};
            const rawOptions = element.getAttribute('data-plyr-options');

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
                volume: 1,
                muted: false,
            }, options);

            const finalizePlyr = function () {
                element.dataset.plyrInitialized = 'true';
                element.plyr = new window.Plyr(element, options);
                syncFullVolume(element, !isPrimerPlaybackActive(element));

                ['loadedmetadata', 'canplay', 'play', 'playing'].forEach(function (eventName) {
                    element.addEventListener(eventName, function () {
                        syncFullVolume(element, !isPrimerPlaybackActive(element));
                    });
                });

                if (typeof element.plyr.on === 'function') {
                    element.plyr.on('enterfullscreen', syncFullscreenUiState);
                    element.plyr.on('exitfullscreen', syncFullscreenUiState);
                }

                if (!shouldUseNativeHls(element)) {
                    forceDetachNativeHlsSource(element);
                }

                bindPlyrPlayButton(element);
                initPlyrZoom(element);

                const playerRoot = element.closest('.plyr');
                if (playerRoot && typeof MutationObserver === 'function') {
                    const fullscreenObserver = new MutationObserver(syncFullscreenUiState);
                    fullscreenObserver.observe(playerRoot, { attributes: true, attributeFilter: ['class'] });
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
