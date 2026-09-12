(function (window, document) {
    'use strict';

    const FirePlayer = window.FirePlayer;
    if (!FirePlayer || window.canViewVideoDiagnostics !== true || FirePlayer.diagnosticsRegistered) {
        return;
    }
    FirePlayer.diagnosticsRegistered = true;

    const panels = new WeakMap();
    const russian = (document.documentElement.lang || '').toLowerCase().startsWith('ru');
    const labels = russian ? {
        title: 'Техническая информация', version: 'Версия плеера', source: 'Сервер источника',
        media: 'Медиа / протокол / режим', engine: 'Движок', resolution: 'Разрешение',
        time: 'Позиция / длительность', buffer: 'Буфер впереди', level: 'Качество / битрейт',
        codecs: 'Кодеки', frames: 'Пропущено / всего кадров', reconnects: 'Переподключения', error: 'Последняя ошибка',
        idle: 'Не загружено', loading: 'Загрузка', ready: 'Готов', play: 'Запуск', playing: 'Воспроизведение',
        paused: 'Пауза', ended: 'Завершено', reconnecting: 'Переподключение', failed: 'Ошибка',
        seconds: 'с', bitrate: 'кбит/с'
    } : {
        title: 'Technical information', version: 'Player version', source: 'Source host',
        media: 'Media / protocol / mode', engine: 'Engine', resolution: 'Resolution',
        time: 'Position / duration', buffer: 'Buffer ahead', level: 'Quality / bitrate',
        codecs: 'Codecs', frames: 'Dropped / total frames', reconnects: 'Reconnects', error: 'Last error',
        idle: 'Unloaded', loading: 'Loading', ready: 'Ready', play: 'Starting', playing: 'Playing',
        paused: 'Paused', ended: 'Ended', reconnecting: 'Reconnecting', failed: 'Error',
        seconds: 's', bitrate: 'kbps'
    };
    const token = function (value) {
        return typeof value === 'string' && /^[a-zA-Z0-9_. ,/-]{1,100}$/.test(value) ? value : '—';
    };
    const clock = function (value) {
        if (value === Infinity) { return 'LIVE'; }
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
        ['version', 'source', 'media', 'engine', 'resolution', 'time', 'buffer', 'level', 'codecs', 'frames', 'reconnects', 'error'].forEach(function (key) {
            const row = document.createElement('div');
            const term = document.createElement('dt');
            term.textContent = labels[key];
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
            status.textContent = labels[next === 'error' ? 'failed' : next] || next;
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
            const engine = controller && controller.engine || 'native';
            values.version.textContent = token(FirePlayer.version);
            values.source.textContent = source ? host(source) : '—';
            values.media.textContent = [info.media, info.protocol, info.mode].map(token).join(' / ');
            values.engine.textContent = token(engine) + (hls ? ' ' + token((hls.constructor && hls.constructor.version) || (window.Hls && window.Hls.version)) : '');
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
            if (media.error || player.root.classList.contains('fireplayer--error')) { setState('error'); }
            else if (media.ended) { setState('ended'); }
            else if (player.root.classList.contains('fireplayer--reconnecting')) { setState('reconnecting'); }
            else if (player.root.classList.contains('fireplayer--loading')) { setState('loading'); }
            else if (!media.paused && media.readyState >= 3) { setState('playing'); }
            else if (state === 'playing' && media.paused) { setState('paused'); }
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
