(function () {
    const plyrAssetBase = (typeof baseUrl === 'string' ? baseUrl : '') + '/assets/default/vendor/plyr';
    const plyrBlankVideoUrl = plyrAssetBase + '/blank.mp4';
    const hlsScriptUrl = 'https://cdn.jsdelivr.net/npm/hls.js@latest';
    const hlsWarmupDurationMs = 15000;
    const hlsRetryDelayMs = 2000;
    const hlsPlayRetryDurationMs = 12000;
    const hlsPlayRetryDelayMs = 1000;
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
            stream_error_load: 'Ошибка потока: не удалось загрузить HLS источник{details}',
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
            stream_error_load: 'Stream error: failed to load HLS source{details}',
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
                    resolve(window.Hls);
                    return;
                }

                reject(new Error('Hls.js loaded but window.Hls is unavailable'));
            }, { once: true });

            script.addEventListener('error', function () {
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
        if (!canPlayHlsNatively(element)) {
            return false;
        }

        const userAgent = window.navigator.userAgent || '';
        const vendor = window.navigator.vendor || '';
        const isSafari = /Safari/i.test(userAgent)
            && /Apple/i.test(vendor)
            && !/Chrome|CriOS|Chromium|Edg|OPR|YaBrowser|FxiOS|DuckDuckGo/i.test(userAgent);
        const isMobileSafari = /iPad|iPhone|iPod/i.test(userAgent);

        if (!isSafari) {
            return false;
        }

        // Prefer hls.js on desktop Safari because this stream fails in native HLS,
        // while keeping native playback as a fallback on iOS where MSE support varies.
        if (!isMobileSafari && canUseHlsJsPlayback()) {
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
        const container = element.closest('[data-plyr-player-wrap]') || element.parentElement;
        if (!container) {
            console.error(message);
            return;
        }

        let messageNode = container.querySelector('[data-plyr-hls-message]');
        if (!messageNode) {
            messageNode = document.createElement('div');
            messageNode.dataset.plyrHlsMessage = 'true';
            messageNode.className = 'alert mt-3';
            container.appendChild(messageNode);
        }

        const classMap = {
            info: 'alert-secondary',
            success: 'alert-success',
            warning: 'alert-warning',
            error: 'alert-danger',
        };

        messageNode.className = 'alert mt-3 ' + (classMap[type] || classMap.info);
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
        const container = element.closest('[data-plyr-player-wrap]') || element.parentElement;
        const messageNode = container ? container.querySelector('[data-plyr-hls-message]') : null;
        if (messageNode) {
            messageNode.remove();
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
        element.hlsPlaybackStarted = true;
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

        if (element.hlsBackgroundWarmupPromise) {
            return element.hlsBackgroundWarmupPromise;
        }

        element.hlsBackgroundWarmupPromise = waitForHlsSource(element, element.hlsSource, {
            requirePlayIntent: false,
            emitStatus: false,
        }).then(function (isReady) {
            element.hlsSourcePrewarmed = isReady;
            return isReady;
        }).finally(function () {
            element.hlsBackgroundWarmupPromise = null;
        });

        return element.hlsBackgroundWarmupPromise;
    };

    const destroyHlsInstance = function (element) {
        if (element.hlsInstance && typeof element.hlsInstance.destroy === 'function') {
            element.hlsInstance.destroy();
        }

        cleanupPlayRetryTimer(element);
        element.hlsInstance = null;
        element.hlsMediaReady = false;
        element.hlsPlaybackStarted = false;
        element.dataset.hlsPlaybackBound = 'false';
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

            if ((Date.now() - element.hlsPlayRetryStartedAt) > hlsPlayRetryDurationMs) {
                showHlsError(element, t('native_error', { state: describeMediaState(element) }));
                return;
            }

            showHlsWarning(element, t('native_stalled'));
            cleanupPlayRetryTimer(element);
            element.hlsPlayRetryTimer = setTimeout(function () {
                prepareNativeHlsPlayback(element);
                attemptDeferredPlay(element);
            }, hlsRetryDelayMs);
        });
    };

    const bindHlsPlaybackEvents = function (element, hls) {
        if (element.dataset.hlsPlaybackBound === 'true') {
            return;
        }

        element.dataset.hlsPlaybackBound = 'true';

        hls.on(Hls.Events.ERROR, function (_, data) {
            if (!data || !data.fatal) {
                return;
            }

            const details = data.details ? ' (' + data.details + ')' : '';

            switch (data.type) {
                case Hls.ErrorTypes.NETWORK_ERROR:
                    showHlsError(element, t('stream_error_load', { details }));
                    break;
                case Hls.ErrorTypes.MEDIA_ERROR:
                    showHlsWarning(element, t('media_recover'));
                    hls.recoverMediaError();
                    break;
                default:
                    showHlsError(element, t('stream_error_play', { details }));
                    destroyHlsInstance(element);
                    break;
            }
        });

        hls.on(Hls.Events.MANIFEST_LOADING, function () {
            showHlsInfo(element, t('loading_manifest'));
        });

        hls.on(Hls.Events.MEDIA_ATTACHED, function () {
            releasePrimedPlayback(element, true);
            showHlsInfo(element, t('media_attached'));
        });

        hls.on(Hls.Events.MANIFEST_PARSED, function () {
            showHlsSuccess(element, t('manifest_loaded'));
            scheduleDeferredPlay(element);
        });

        hls.on(Hls.Events.LEVEL_LOADING, function () {
            showHlsInfo(element, t('loading_segments'));
        });

        hls.on(Hls.Events.LEVEL_LOADED, function () {
            showHlsSuccess(element, t('stream_buffered'));
            scheduleDeferredPlay(element);
        });

        element.addEventListener('playing', function () {
            if (isPrimerPlaybackActive(element)) {
                showHlsInfo(element, t('primed_waiting'));
                return;
            }

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

            const sourceReady = element.hlsSourcePrewarmed
                ? true
                : (element.hlsBackgroundWarmupPromise
                    ? await element.hlsBackgroundWarmupPromise
                    : await waitForHlsSource(element, element.hlsSource));
            if (!sourceReady) {
                return false;
            }

            destroyHlsInstance(element);
            detachNativeHlsSource(element);

            const hls = new Hls({
                autoStartLoad: true,
                enableWorker: true,
                lowLatencyMode: true,
                backBufferLength: 90,
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
        element.hlsBackgroundWarmupPromise = null;
        element.hlsPlayRetryStartedAt = 0;
        element.hlsPlaybackStarted = false;
        element.dataset.hlsNativeSourceDetached = 'false';
        element.dataset.hlsPrimerActive = 'false';
        element.hlsWarmupActive = false;
        bootstrapPlayIntent(element);
        bindNativePlaybackEvents(element);
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
            }, options);

            const finalizePlyr = function () {
                element.dataset.plyrInitialized = 'true';
                element.plyr = new window.Plyr(element, options);

                if (!shouldUseNativeHls(element)) {
                    forceDetachNativeHlsSource(element);
                }

                bindPlyrPlayButton(element);
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
