(function (window, document) {
    'use strict';

    const FirePlayer = window.FirePlayer;
    if (!FirePlayer || window.canViewVideoDiagnostics !== true || FirePlayer.diagnosticsRegistered) {
        return;
    }
    FirePlayer.diagnosticsRegistered = true;

    const panels = new WeakMap();
    const language = (document.documentElement.lang || 'en').toLowerCase().replace('_', '-');
    const locale = language.startsWith('ru') ? 'ru' : language.startsWith('de') ? 'de' : language.startsWith('zh') ? 'zh-cn' : 'en';
    const dictionaries = { ru: {
        title: 'Техническая информация', version: 'Версия плеера', source: 'Сервер источника',
        media: 'Медиа / протокол / режим', engine: 'Движок', resolution: 'Разрешение',
        time: 'Позиция / длительность', buffer: 'Буфер впереди', level: 'Качество / битрейт',
        codecs: 'Кодеки', frames: 'Пропущено / всего кадров', reconnects: 'Переподключения', error: 'Последняя ошибка',
        idle: 'Не загружено', loading: 'Загрузка', ready: 'Готов', play: 'Запуск', playing: 'Воспроизведение',
        paused: 'Пауза', ended: 'Завершено', reconnecting: 'Переподключение', failed: 'Ошибка',
        seconds: 'с', bitrate: 'кбит/с'
    }, en: {
        title: 'Technical information', version: 'Player version', source: 'Source host',
        media: 'Media / protocol / mode', engine: 'Engine', resolution: 'Resolution',
        time: 'Position / duration', buffer: 'Buffer ahead', level: 'Quality / bitrate',
        codecs: 'Codecs', frames: 'Dropped / total frames', reconnects: 'Reconnects', error: 'Last error',
        idle: 'Unloaded', loading: 'Loading', ready: 'Ready', play: 'Starting', playing: 'Playing',
        paused: 'Paused', ended: 'Ended', reconnecting: 'Reconnecting', failed: 'Error',
        seconds: 's', bitrate: 'kbps'
    }, de: {
        title: 'Technische Informationen', version: 'Player-Version', source: 'Quellserver',
        media: 'Medien / Protokoll / Modus', engine: 'Wiedergabe-Engine', resolution: 'Auflösung',
        time: 'Position / Dauer', buffer: 'Vorausgepuffert', level: 'Qualität / Bitrate',
        codecs: 'Codecs', frames: 'Verworfene / gesamte Frames', reconnects: 'Neu-Verbindungen', error: 'Letzter Fehler',
        idle: 'Nicht geladen', loading: 'Laden', ready: 'Bereit', play: 'Starten', playing: 'Wiedergabe',
        paused: 'Pausiert', ended: 'Beendet', reconnecting: 'Erneut verbinden', failed: 'Fehler',
        seconds: 's', bitrate: 'kbit/s'
    }, 'zh-cn': {
        title: '技术信息', version: '播放器版本', source: '媒体服务器', media: '媒体 / 协议 / 模式',
        engine: '播放引擎', resolution: '分辨率', time: '位置 / 时长', buffer: '前向缓冲', level: '画质 / 比特率',
        codecs: '编解码器', frames: '丢帧 / 总帧数', reconnects: '重连次数', error: '最近错误',
        idle: '未加载', loading: '加载中', ready: '就绪', play: '启动中', playing: '播放中',
        paused: '已暂停', ended: '已结束', reconnecting: '重新连接中', failed: '错误', seconds: '秒', bitrate: '千比特/秒'
    } };
    // Each row has the same locale order so additions cannot silently fall back to field IDs.
    const locales = ['ru', 'en', 'de', 'zh-cn'];
    const extraLabels = {
        engineRequested: ['Выбранный режим движка', 'Requested engine mode', 'Gewählter Engine-Modus', '所选播放引擎模式'],
        engineFallback: ['Причина резервного движка', 'Engine fallback reason', 'Grund für Ersatz-Engine', '备用引擎原因'],
        state: ['Состояние', 'State', 'Status', '状态'],
        managed: ['Управляемый поток', 'Managed stream', 'Verwalteter Stream', '受管流'],
        online: ['Подключение к сети', 'Network connection', 'Netzwerkverbindung', '网络连接'],
        liveEdge: ['Край прямого эфира', 'Live edge', 'Live-Endpunkt', '直播边缘'],
        latency: ['Задержка эфира', 'Live latency', 'Live-Verzögerung', '直播延迟'],
        liveSyncPosition: ['Целевая позиция эфира', 'Live sync position', 'Live-Synchronisationsposition', '直播同步位置'],
        wakeMs: ['Время запуска камеры', 'Camera wake time', 'Kamera-Startzeit', '摄像头启动耗时'],
        manifestMs: ['Время до манифеста', 'Time to manifest', 'Zeit bis zum Manifest', '清单加载耗时'],
        firstFrameMs: ['Время до первого кадра', 'Time to first frame', 'Zeit bis zum ersten Bild', '首帧耗时'],
        frameAge: ['Время с последнего кадра', 'Time since last frame', 'Zeit seit dem letzten Bild', '距上一帧时间'],
        recoveryStage: ['Этап восстановления', 'Recovery stage', 'Wiederherstellungsschritt', '恢复阶段'],
        recoveryReason: ['Причина восстановления', 'Recovery reason', 'Wiederherstellungsgrund', '恢复原因'],
        lazy: ['Ожидание запуска', 'Waiting to start', 'Warten auf Start', '等待启动'],
        detecting: ['Определение источника', 'Detecting source', 'Quelle erkennen', '正在识别媒体'],
        waking: ['Запуск камеры', 'Starting camera', 'Kamera starten', '正在启动摄像头'],
        connecting: ['Подключение', 'Connecting', 'Verbindung herstellen', '正在连接'],
        buffering: ['Буферизация', 'Buffering', 'Puffern', '缓冲中'],
        offline: ['Нет сети', 'Offline', 'Offline', '离线'],
        'awaiting-gesture': ['Ожидание нажатия Play', 'Waiting for Play', 'Warten auf Abspielen', '等待点击播放'],
        destroyed: ['Плеер закрыт', 'Player closed', 'Player geschlossen', '播放器已关闭'],
        yes: ['Да', 'Yes', 'Ja', '是'], no: ['Нет', 'No', 'Nein', '否'],
        connected: ['Есть подключение', 'Connected', 'Verbunden', '已连接'],
        milliseconds: ['мс', 'ms', 'ms', '毫秒']
    };
    Object.entries(extraLabels).forEach(function ([key, translations]) {
        locales.forEach(function (lang, index) { dictionaries[lang][key] = translations[index]; });
    });
    const labels = dictionaries[locale];
    const stateLabel = function (value) { return labels[value === 'error' ? 'failed' : value] || '—'; };
    const terms = {
        video: ['Видео', 'Video', 'Video', '视频'], audio: ['Аудио', 'Audio', 'Audio', '音频'],
        file: ['Файл', 'File', 'Datei', '文件'], hls: ['HLS', 'HLS', 'HLS', 'HLS'], dash: ['DASH', 'DASH', 'DASH', 'DASH'],
        live: ['Прямой эфир', 'Live', 'Live', '直播'], vod: ['Запись', 'On demand', 'Auf Abruf', '点播'],
        event: ['Событийная трансляция', 'Event stream', 'Ereignisstream', '活动直播'],
        native: ['Встроенный в браузер', 'Browser native', 'Browserintern', '浏览器原生'],
        'hls.js': ['hls.js', 'hls.js', 'hls.js', 'hls.js'],
        auto: ['Автоматически', 'Automatic', 'Automatisch', '自动'],
        'hls-unsupported': ['HLS.js не поддерживается устройством', 'HLS.js is not supported on this device', 'HLS.js wird auf diesem Gerät nicht unterstützt', '此设备不支持 HLS.js'],
        'hls-load-failed': ['Не удалось загрузить HLS.js', 'HLS.js could not be loaded', 'HLS.js konnte nicht geladen werden', '无法加载 HLS.js'],
        initial: ['Первоначальный запуск', 'Initial start', 'Erster Start', '首次启动'],
        manual: ['Ручной повтор', 'Manual retry', 'Manueller Neuversuch', '手动重试'],
        resume: ['Возобновление', 'Resume', 'Fortsetzen', '恢复播放'],
        network: ['Ошибка сети', 'Network error', 'Netzwerkfehler', '网络错误'],
        media: ['Ошибка декодирования', 'Decoding error', 'Dekodierungsfehler', '解码错误'],
        stall: ['Зависание потока', 'Playback stalled', 'Wiedergabe stockt', '播放停滞'],
        online: ['Сеть восстановлена', 'Network restored', 'Netzwerk wieder verfügbar', '网络已恢复'],
        autoplay: ['Ограничение автозапуска', 'Autoplay restriction', 'Autoplay-Beschränkung', '自动播放受限'],
        unknown: ['Неизвестно', 'Unknown', 'Unbekannt', '未知'],
        'native-play': ['Запуск встроенного плеера', 'Native playback start', 'Start der nativen Wiedergabe', '启动原生播放'],
        'native-prepare': ['Подготовка встроенного плеера', 'Preparing native playback', 'Native Wiedergabe vorbereiten', '准备原生播放'],
        'native-attach': ['Подключение встроенного плеера', 'Native source attachment', 'Native Quelle verbinden', '连接原生媒体源'],
        'native-reattach': ['Повторное подключение встроенного плеера', 'Native source reattachment', 'Native Quelle erneut verbinden', '重新连接原生媒体源'],
        'native-muted-play': ['Попытка запуска без звука', 'Muted playback attempt', 'Stummer Wiedergabeversuch', '尝试静音播放'],
        'play-not-supported': ['Повтор после отказа воспроизведения', 'Retry after playback rejection', 'Neuversuch nach Wiedergabeablehnung', '播放被拒后重试'],
        'wake-cooldown': ['Пауза между запусками камеры', 'Camera wake cooldown', 'Wartezeit zwischen Kamerastarts', '摄像头启动冷却期'],
        'wake-skipped': ['Повторный запуск камеры пропущен', 'Camera wake skipped', 'Kamerastart übersprungen', '已跳过摄像头启动'],
        'hls-start-load': ['Возобновление загрузки HLS', 'Resuming HLS loading', 'HLS-Laden fortsetzen', '恢复 HLS 加载'],
        'recover-media-error': ['Восстановление декодера', 'Decoder recovery', 'Decoder wiederherstellen', '恢复解码器'],
        'live-health-check': ['Проверка состояния эфира', 'Live health check', 'Live-Zustandsprüfung', '直播状态检查'],
        'wake-rebuild': ['Перезапуск движка после запуска камеры', 'Rebuild after camera wake', 'Neuaufbau nach Kamerastart', '启动摄像头后重建引擎'],
        'soft-rebuild': ['Перезапуск движка без запуска камеры', 'Rebuild without camera wake', 'Neuaufbau ohne Kamerastart', '不启动摄像头而重建引擎']
    };
    const termLabel = function (value) {
        return Object.prototype.hasOwnProperty.call(terms, value) ? terms[value][locales.indexOf(locale)] : '—';
    };
    const token = function (value) {
        return typeof value === 'string' && /^[a-zA-Z0-9_. ,/-]{1,100}$/.test(value) ? value : '—';
    };
    const clock = function (value) {
        if (value === Infinity) { return termLabel('live'); }
        if (!Number.isFinite(value) || value < 0) { return '—'; }
        const seconds = Math.floor(value);
        return (seconds >= 3600 ? Math.floor(seconds / 3600) + ':' : '') +
            String(Math.floor(seconds / 60) % 60).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0');
    };
    const host = function (source) {
        try {
            const url = new URL(source, document.baseURI);
            return /^https?:$/.test(url.protocol) ? url.host : (url.protocol === 'blob:' ? 'blob' : '—');
        } catch (error) { return '—'; }
    };
    const errorCode = function (error) {
        if (!error || typeof error !== 'object') { return '—'; }
        const name = typeof error.name === 'string' && /^[a-zA-Z][a-zA-Z0-9_]{0,47}$/.test(error.name) ? error.name : 'Error';
        const code = typeof error.code === 'number' && Number.isFinite(error.code) ? String(error.code)
            : (typeof error.code === 'string' && /^[a-zA-Z][a-zA-Z0-9_]{0,47}$/.test(error.code) ? error.code : '');
        return name + (code ? ' (' + code + ')' : '');
    };

    const attach = function (player) {
        if (!player || player._destroyed || !player.root || panels.has(player) || window.canViewVideoDiagnostics !== true) {
            return;
        }
        const panel = document.createElement('details');
        panel.className = 'fireplayer-diagnostics';
        panel.open = true;
        const summary = document.createElement('summary');
        const title = document.createElement('span');
        title.textContent = labels.title;
        const status = document.createElement('span');
        status.setAttribute('data-fp-diagnostic-state', 'idle');
        summary.appendChild(title);
        summary.appendChild(status);
        panel.appendChild(summary);
        const grid = document.createElement('dl');
        grid.className = 'fireplayer-diagnostics__grid';
        const values = {};
        ['version', 'source', 'media', 'engine', 'engineRequested', 'engineFallback', 'resolution', 'time', 'buffer', 'level', 'codecs', 'frames', 'reconnects', 'error',
            'state', 'managed', 'online', 'liveEdge', 'latency', 'liveSyncPosition', 'wakeMs', 'manifestMs', 'firstFrameMs', 'frameAge', 'recoveryStage', 'recoveryReason'].forEach(function (key) {
            const row = document.createElement('div');
            const term = document.createElement('dt');
            term.textContent = labels[key] || key;
            const value = document.createElement('dd');
            value.setAttribute('data-fp-diagnostic', key);
            value.textContent = '—';
            values[key] = value;
            row.appendChild(term);
            row.appendChild(value);
            grid.appendChild(row);
        });
        panel.appendChild(grid);
        panels.set(player, panel);

        let source = String(player.options.src || '');
        let reconnects = 0;
        let lastError = '—';
        let unloaded = !source;
        let disposed = false;
        let state = 'idle';
        const handlers = [];
        const setState = function (next) {
            state = next;
            status.setAttribute('data-fp-diagnostic-state', next);
            status.textContent = stateLabel(next);
        };
        const reset = function () {
            reconnects = 0;
            lastError = '—';
            Object.keys(values).forEach(function (key) { values[key].textContent = '—'; });
        };
        const dispose = function () {
            if (disposed) { return; }
            disposed = true;
            window.clearInterval(timer);
            handlers.forEach(function (entry) { player.off(entry[0], entry[1]); });
            panel.removeEventListener('toggle', update);
            document.removeEventListener('visibilitychange', update);
            panel.remove();
            panels.delete(player);
        };
        const update = function () {
            if (disposed) { return; }
            if (window.canViewVideoDiagnostics !== true || player._destroyed) { dispose(); return; }
            if (!player.root.isConnected) { panel.remove(); return; }
            if (player.root.nextSibling !== panel) {
                player.root.parentNode.insertBefore(panel, player.root.nextSibling);
            }
            panel.hidden = unloaded;
            if (unloaded || !panel.open || document.hidden) { return; }

            const media = player.media;
            const info = player.info || {};
            const controller = player.controller;
            const hls = controller && controller.hls;
            const engine = controller && controller.engine || (info.protocol === 'file' ? 'native' : null);
            values.version.textContent = token(FirePlayer.version);
            values.source.textContent = source ? host(source) : '—';
            values.media.textContent = [info.media, info.protocol, info.mode].map(termLabel).join(' / ');
            values.engine.textContent = termLabel(engine) + (hls ? ' ' + token((hls.constructor && hls.constructor.version) || (window.Hls && window.Hls.version)) : '');
            values.engineRequested.textContent = termLabel(controller && controller.engineRequested);
            values.engineFallback.textContent = termLabel(controller && controller.engineFallback);
            values.resolution.textContent = media.videoWidth > 0 && media.videoHeight > 0 ? media.videoWidth + ' × ' + media.videoHeight : '—';
            values.time.textContent = clock(media.currentTime) + ' / ' + clock(media.duration);
            let ahead = 0;
            try {
                for (let index = 0; index < media.buffered.length; index += 1) {
                    if (media.currentTime >= media.buffered.start(index) - 0.05 && media.currentTime <= media.buffered.end(index)) {
                        ahead = Math.max(0, media.buffered.end(index) - media.currentTime);
                        break;
                    }
                }
            } catch (error) { /* A buffer may be replaced while recovering a stream. */ }
            values.buffer.textContent = ahead.toFixed(1) + ' ' + labels.seconds;
            const levelIndex = hls && Number.isInteger(hls.currentLevel) ? hls.currentLevel : -1;
            const level = hls && hls.levels && levelIndex >= 0 ? hls.levels[levelIndex] : null;
            values.level.textContent = level ? String(levelIndex + 1) + '/' + hls.levels.length +
                (Number.isFinite(level.bitrate) ? ' · ' + Math.round(level.bitrate / 1000) + ' ' + labels.bitrate : '') : '—';
            values.codecs.textContent = level ? [level.videoCodec, level.audioCodec].filter(Boolean).map(token).join(' / ') || '—' : '—';
            let quality = null;
            try { quality = typeof media.getVideoPlaybackQuality === 'function' ? media.getVideoPlaybackQuality() : null; } catch (error) { /* Optional browser metric. */ }
            const dropped = quality ? quality.droppedVideoFrames : media.webkitDroppedFrameCount;
            const total = quality ? quality.totalVideoFrames : media.webkitDecodedFrameCount;
            values.frames.textContent = Number.isFinite(dropped) && Number.isFinite(total) ? dropped + ' / ' + total : '—';
            values.reconnects.textContent = String(reconnects);
            values.error.textContent = lastError;
            values.state.textContent = stateLabel(player._state || 'idle');
            values.managed.textContent = (player.options.streamId || /\/stream-[^/]+\/index\.m3u8(?:[?#]|$)/i.test(source)) ? labels.yes : labels.no;
            values.online.textContent = window.navigator.onLine !== false ? labels.connected : labels.offline;
            const edge = media.seekable && media.seekable.length ? media.seekable.end(media.seekable.length - 1) : null;
            values.liveEdge.textContent = Number.isFinite(edge) ? edge.toFixed(1) + ' ' + labels.seconds : '—';
            values.latency.textContent = Number.isFinite(edge) ? Math.max(0, edge - media.currentTime).toFixed(1) + ' ' + labels.seconds : '—';
            const sync = controller && controller.liveSyncPosition;
            values.liveSyncPosition.textContent = Number.isFinite(sync) ? sync.toFixed(1) + ' ' + labels.seconds : '—';
            const metrics = player._metrics || {};
            ['wakeMs', 'manifestMs', 'firstFrameMs'].forEach(function (key) { values[key].textContent = Number.isFinite(metrics[key]) ? Math.round(metrics[key]) + ' ' + labels.milliseconds : '—'; });
            ['recoveryStage', 'recoveryReason'].forEach(function (key) { values[key].textContent = termLabel(metrics[key]); });
            const frameAt = player._health && player._health.lastFrameAt;
            values.frameAge.textContent = Number.isFinite(frameAt) ? ((Date.now() - frameAt) / 1000).toFixed(1) + ' ' + labels.seconds : '—';
            if (media.error || player.root.classList.contains('fireplayer--error')) { setState('error'); }
            else if (media.ended) { setState('ended'); }
            else if (player.root.classList.contains('fireplayer--reconnecting')) { setState('reconnecting'); }
            else if (player.root.classList.contains('fireplayer--loading')) { setState('loading'); }
            else if (!media.paused && media.readyState >= 3) { setState('playing'); }
            else if (state === 'playing' && media.paused) { setState('paused'); }
            if (player._state) { setState(player._state); }
        };
        const on = function (name, handler) {
            player.on(name, handler);
            handlers.push([name, handler]);
        };
        on('loadstart', function () {
            const nextSource = String(player.options.src || '');
            if (source !== nextSource || unloaded) { reset(); }
            source = nextSource;
            unloaded = false;
            setState('loading');
            update();
        });
        ['ready', 'play', 'playing', 'pause', 'loadedmetadata'].forEach(function (name) {
            on(name, function () {
                if (unloaded) { return; }
                if (name !== 'loadedmetadata') { setState(name === 'pause' ? 'paused' : name); }
                update();
            });
        });
        on('error', function (event) { lastError = errorCode(event && event.error || player.media.error); setState('error'); update(); });
        on('reconnect', function () { reconnects += 1; setState('reconnecting'); update(); });
        on('unload', function () { unloaded = true; source = ''; reset(); setState('idle'); panel.hidden = true; });
        on('destroy', dispose);
        const timer = window.setInterval(update, 1000);
        panel.addEventListener('toggle', update);
        document.addEventListener('visibilitychange', update);
        setState(player.root.classList.contains('fireplayer--error') ? 'error' : (player.info ? 'ready' : (source ? 'loading' : 'idle')));
        if (player.media.error) { lastError = errorCode(player.media.error); }
        update();
    };

    document.addEventListener('fireplayer:init', function (event) { attach(event.detail && event.detail.player); });
    const bootstrap = function () {
        document.querySelectorAll('.fireplayer, [data-fire-player-initialized="true"]').forEach(function (root) { attach(FirePlayer.get(root)); });
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
    } else {
        bootstrap();
    }
})(window, document);
