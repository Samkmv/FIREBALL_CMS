(() => {
    'use strict';
    const app = document.querySelector('[data-player]');
    if (!app) return;
    const config = JSON.parse(document.querySelector('[data-player-config]').textContent);
    const $ = selector => app.querySelector(selector);
    const audio = $('[data-audio]');
    const dialog = $('[data-dialog]');
    // These elements stay in the page for its lifetime. Keep DOM searches out
    // of the animation loop, including the 120 Hz loop on newer displays.
    const visuals = {
        seek:$('[data-seek]'), elapsed:$('[data-elapsed]'), duration:$('[data-duration]'),
        reels:['left','right'].map(side => ({rotor:$(`[data-rotor="${side}"]`), pack:$(`[data-pack="${side}"]`), clip:$(`[data-pack-clip="${side}"]`), bars:[...$(`[data-meter="${side}"]`).querySelectorAll('i')]})),
        tape:[$('[data-tape-left]'),$('[data-tape-right]')]
    };
    let theme = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
    let data = config.state, saved = {};
    try { saved = JSON.parse(localStorage.getItem(config.storageKey) || '{}'); } catch (_) {}
    let audibleVolume = Number(saved.audibleVolume) > 0 ? Math.min(1, Number(saved.audibleVolume)) : Number(saved.volume) > 0 ? Math.min(1, Number(saved.volume)) : .8;
    let selected = Number(saved.playlist) || 0;
    let current = 0, queue = [], queueName = '', queuePlaylist = Number(saved.queuePlaylist) || 0, repeat = ['off', 'all', 'one'].includes(saved.repeat) ? saved.repeat : 'off';
    let librarySort = ['new','old'].includes(saved.librarySort) ? saved.librarySort : 'new';
    let playlistSort = ['playlist','new','old'].includes(saved.playlistSort) ? saved.playlistSort : 'playlist';
    let queueSort = ['playlist','new','old'].includes(saved.queueSort) ? saved.queueSort : (queuePlaylist > 0 ? 'playlist' : librarySort);
    let shuffle = Boolean(saved.shuffle), bag = [], history = [], seekActive = false, restoreTime = 0;
    let toastTimer, frameId = 0, frameTime = 0, angles = [0, 0], graph, meterTimer, uploading = false, dialogHandler, dialogSubmitting = false;
    let loading = false, bufferVisible = false, bufferTimer, coverPreviewUrl, generation = 0, lastSave = 0, mutations = 0;
    let meterBufferTimer, progressFrame = -Infinity, meterFrame = -Infinity, packRadii = [174,78], positionKey = '';
    const meterLevels = [0,0];
    const meterRecovery = {generation:-1, rebuilds:0, blocked:false, forceMirror:false};
    let mutationQueue = Promise.resolve();
    let futureBag = [], shuffleCycleStarted = false;
    const preloader = new window.TapeRoomPreloader({url:`${config.api}/prepare`,csrf:config.csrf});
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    const formatTime = value => {
        if (!Number.isFinite(value) || value < 0) return '0:00';
        const s = Math.floor(value);
        return s >= 3600 ? `${Math.floor(s / 3600)}:${String(Math.floor(s / 60) % 60).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}` : `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
    };
    const esc = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));
    const icon = name => `<svg class="rp-icon" aria-hidden="true"><use href="#rp-i-${name}"></use></svg>`;
    const button = (action, title, name, id, extra = '') => `<button type="button" class="rp-icon-button ${extra}" data-action="${action}" data-id="${id}" title="${title}" aria-label="${title}">${icon(name)}</button>`;
    const track = id => data.tracks.find(t => t.id === Number(id));
    const playlist = id => data.playlists.find(p => p.id === Number(id));
    const activeTrack = () => track(current);
    const collectionName = id => id === -1 ? 'Избранное' : playlist(id)?.name || 'Вся музыка';
    const collectionTracks = id => id === -1 ? data.tracks.filter(t => t.favorite) : id > 0 ? (playlist(id)?.tracks || []).map(track).filter(Boolean) : data.tracks;
    const sortedTracks = (tracks, mode) => mode === 'playlist' ? [...tracks] : [...tracks].sort((a,b) => mode === 'old' ? a.id - b.id : b.id - a.id);
    const selectedSort = () => selected > 0 ? playlistSort : librarySort;
    const selectedTracks = () => sortedTracks(collectionTracks(selected), selectedSort());
    const element = (tag, className, text) => { const node = document.createElement(tag); node.className = className; if (text !== undefined) node.textContent = text; return node; };
    const setIcon = (node, name) => node.querySelector('use').setAttribute('href', `#rp-i-${name}`);
    const rangeFill = (node, fraction) => node.style.setProperty('--fill', `${Math.max(0, Math.min(1, fraction)) * 100}%`);

    function applyTheme() {
        const dark = theme === 'dark', control = $('[data-action="theme"]');
        document.documentElement.dataset.theme = theme;
        document.querySelector('meta[name="color-scheme"]').content = theme;
        document.querySelectorAll('meta[name="theme-color"]').forEach(meta => { meta.content = dark ? '#191c1a' : '#f7f5f0'; });
        const label = dark ? 'Включить светлую тему' : 'Включить тёмную тему';
        control.setAttribute('aria-label', label); control.title = label;
        control.setAttribute('aria-pressed', String(dark));
        setIcon(control, dark ? 'sun' : 'moon');
        try { localStorage.setItem(`${config.storageKey}:theme`, theme); } catch (_) {}
    }

    function toast(message, error = false) {
        const node = $('[data-toast]'); node.textContent = message; node.classList.toggle('is-error', error); node.hidden = false;
        clearTimeout(toastTimer); toastTimer = setTimeout(() => { node.hidden = true; }, error ? 7000 : 3500);
    }
    function save() {
        try { localStorage.setItem(config.storageKey, JSON.stringify({playlist:selected, current, queue, queueName, queuePlaylist, queueSort, librarySort, playlistSort, time:audio.currentTime || 0, volume:audio.volume, audibleVolume, muted:audio.muted, shuffle, repeat})); } catch (_) {}
    }
    function closeSort() {
        const options = $('[data-sort-options]'), restoreFocus = options.contains(document.activeElement);
        options.hidden = true;
        $('[data-sort-trigger]').setAttribute('aria-expanded', 'false');
        if (restoreFocus) $('[data-sort-trigger]').focus();
    }
    function openSort() {
        $('[data-sort-options]').hidden = false;
        $('[data-sort-trigger]').setAttribute('aria-expanded', 'true');
        $('[data-sort-options] [aria-selected="true"]')?.focus();
    }
    function renderSort() {
        closeSort();
        const modes = [...(selected > 0 ? [['playlist','Порядок в ленте']] : []), ['new','Сначала новые'], ['old','Сначала старые']];
        const options = $('[data-sort-options]'); options.replaceChildren();
        for (const [value,label] of modes) {
            const option = element('button','rp-sort-option',label);
            option.type = 'button'; option.tabIndex = -1; option.dataset.sortMode = value;
            option.setAttribute('role','option'); option.setAttribute('aria-selected', String(value === selectedSort()));
            options.append(option);
        }
        const label = modes.find(([value]) => value === selectedSort())[1];
        $('[data-sort-label]').textContent = label;
        $('[data-sort-trigger]').setAttribute('aria-label', `Сортировка музыки: ${label}`);
    }
    function render() {
        if (selected > 0 && !playlist(selected)) selected = 0;
        if (queuePlaylist > 0 && !playlist(queuePlaylist)) { queuePlaylist = 0; queueSort = librarySort; }
        queue = sortedTracks(collectionTracks(queuePlaylist), queueSort).map(t => t.id);
        queueName = collectionName(queuePlaylist);
        bag = bag.filter(id => queue.includes(id)); futureBag = futureBag.filter(id => queue.includes(id)); history = history.filter(id => Boolean(track(id)));
        if (current && !activeTrack()) { audio.pause(); audio.removeAttribute('src'); audio.load(); current = 0; updateNow(); }
        const nav = $('[data-playlists]'); nav.replaceChildren();
        for (const item of [{id:0, name:'Вся музыка', tracks:data.tracks}, {id:-1, name:'Избранное', tracks:collectionTracks(-1)}, ...data.playlists]) {
            const node = element('button', `rp-playlist${item.id === selected ? ' is-selected' : ''}`);
            node.type = 'button'; node.dataset.action = 'select-playlist'; node.dataset.id = item.id;
            node.setAttribute('aria-current', item.id === selected ? 'page' : 'false');
            node.innerHTML = icon(item.id === -1 ? 'heart' : item.id ? 'list' : 'music');
            node.append(element('span', 'rp-playlist-name', item.name), element('span', 'rp-playlist-count', item.tracks.length));
            nav.append(node);
        }
        const list = selectedTracks(), query = $('[data-search]').value.trim().toLocaleLowerCase();
        const visible = list.filter(t => `${t.title} ${t.artist} ${t.filename}`.toLocaleLowerCase().includes(query));
        $('[data-list-title]').textContent = collectionName(selected);
        $('[data-list-summary]').textContent = `${list.length} ${list.length % 10 === 1 && list.length % 100 !== 11 ? 'трек' : list.length % 10 >= 2 && list.length % 10 <= 4 && !(list.length % 100 >= 12 && list.length % 100 <= 14) ? 'трека' : 'треков'}${list.some(t => t.duration) ? ' · ' + formatTime(list.reduce((total, t) => total + t.duration, 0)) : ''}`;
        $('[data-playlist-actions]').hidden = selected <= 0;
        renderSort();
        const rows = $('[data-tracks]'); rows.replaceChildren();
        for (const t of visible) {
            const index = list.indexOf(t), row = element('article', `rp-track${t.id === current ? ' is-current' : ''}`);
            row.dataset.trackId = t.id;
            const play = element('button', 'rp-track-play'); play.type = 'button'; play.dataset.action = 'play-track'; play.dataset.id = t.id;
            play.setAttribute('aria-label', `Воспроизвести ${t.title}`);
            play.innerHTML = `<span class="rp-track-number">${String(index + 1).padStart(2, '0')}</span>${icon(t.id === current && !audio.paused ? 'pause' : 'play')}`;
            const cover = element('img', 'rp-track-cover'); cover.src = t.cover || config.defaultCover; cover.alt = ''; cover.loading = 'lazy';
            const info = element('div', 'rp-track-info');
            info.append(element('span', 'rp-track-title', t.title), element('span', 'rp-track-artist', `${t.artist || 'Неизвестный исполнитель'}${t.source === 'drive' ? ' · Google Drive' : ''}`));
            const actions = element('div', 'rp-track-actions');
            const canReorder = selected > 0 && playlistSort === 'playlist';
            actions.innerHTML = (canReorder ? button('move-up', 'Переместить выше', 'up', t.id, 'rp-reorder') + button('move-down', 'Переместить ниже', 'down', t.id, 'rp-reorder') : '') + button('favorite', t.favorite ? 'Убрать из избранного' : 'Добавить в избранное', 'heart', t.id, `rp-favorite${t.favorite ? ' is-active' : ''}`) + button('add-to-playlist', 'Добавить в плейлист', 'plus', t.id) + button('edit-track', 'Изменить трек', 'edit', t.id) + button(selected > 0 ? 'remove-track' : 'delete-track', selected > 0 ? 'Убрать из плейлиста' : 'Удалить из библиотеки', 'trash', t.id);
            actions.querySelector('[data-action="favorite"]').setAttribute('aria-pressed', String(t.favorite));
            if (canReorder) {
                actions.querySelector('[data-action="move-up"]').disabled = index === 0;
                actions.querySelector('[data-action="move-down"]').disabled = index === list.length - 1;
            }
            row.append(play, cover, info, element('span', 'rp-track-duration', t.duration ? formatTime(t.duration) : '—'), actions); rows.append(row);
        }
        $('[data-empty]').hidden = visible.length > 0;
        $('[data-empty-title]').textContent = query ? 'Ничего не найдено' : selected === -1 ? 'Любимые песни будут здесь' : selected > 0 ? 'Эта лента пока пустая' : 'Здесь начинается ваша коллекция';
        $('[data-empty-description]').textContent = query ? 'Попробуйте другое название или имя исполнителя.' : selected === -1 ? 'Нажмите сердечко рядом с песней, чтобы добавить её в избранное.' : selected > 0 ? 'Загрузите музыку сюда или добавьте треки из общей библиотеки.' : 'Перетащите аудиофайлы сюда или добавьте их кнопкой выше.';
        $('[data-action="play"]').disabled = !current && list.length === 0;
        $('[data-action="prev"]').disabled = !current;
        $('[data-action="next"]').disabled = !current;
        $('[data-action="edit-current"]').disabled = !current;
        updateNow(); updateButtons(); prepareUpcoming();
    }
    function updateNow() {
        const t = activeTrack();
        $('[data-title]').textContent = t?.title || 'Ваша первая лента';
        $('[data-artist]').textContent = t?.artist || (t ? 'НЕИЗВЕСТНЫЙ ИСПОЛНИТЕЛЬ' : 'ДОБАВЬТЕ ЛЮБИМУЮ МУЗЫКУ');
        const cover = t?.cover || config.defaultCover;
        if ($('[data-cover]').getAttribute('src') !== cover) $('[data-cover]').src = cover;
        $('[data-source]').textContent = current ? (queueName || 'Вся музыка') + (t?.source === 'drive' ? ' · GOOGLE DRIVE' : ' · STEREO') : 'ВАША МУЗЫКА. ВАШ РИТМ.';
        if (t && 'mediaSession' in navigator && 'MediaMetadata' in window) {
            const artwork = t.cover ? {src:new URL(t.cover,location.href).href,...(t.cover_mime ? {type:t.cover_mime} : {})}
                : {src:new URL(config.defaultArtwork || config.defaultCover,location.href).href,sizes:'512x512',type:'image/png'};
            try { navigator.mediaSession.metadata = new MediaMetadata({title:t.title, artist:t.artist, album:queueName || 'Tape Room', artwork:[artwork]}); } catch (_) {}
        }
    }
    function syncVolume() {
        const silent = audio.muted || audio.volume === 0, volume = $('[data-volume]');
        volume.value = silent ? 0 : audio.volume; rangeFill(volume, Number(volume.value));
        const mute = $('[data-action="mute"]'), label = silent ? 'Включить звук' : 'Выключить звук';
        setIcon(mute, silent ? 'mute' : 'volume'); mute.setAttribute('aria-label', label); mute.title = label;
        mute.setAttribute('aria-pressed', String(silent)); mute.classList.toggle('is-active', silent);
    }
    function toggleMute() {
        if (audio.muted || audio.volume === 0) {
            if (audio.volume === 0) audio.volume = audibleVolume;
            audio.muted = false;
        } else { audibleVolume = audio.volume; audio.muted = true; }
        syncVolume(); save();
    }
    function updateButtons() {
        const playing = !audio.paused && !audio.ended;
        document.body.classList.toggle('is-playing', playing && !loading);
        document.body.classList.toggle('is-buffering', bufferVisible);
        const play = $('[data-action="play"]'); setIcon(play, playing ? 'pause' : 'play');
        play.setAttribute('aria-busy', String(loading));
        play.setAttribute('aria-label', playing ? 'Пауза' : 'Воспроизвести'); play.title = playing ? 'Пауза' : 'Воспроизвести';
        $('[data-play-status]').textContent = bufferVisible ? 'БУФЕРИЗАЦИЯ…' : playing ? 'ЛЕНТА В ДВИЖЕНИИ' : current ? 'ПАУЗА' : 'ГОТОВ К ПРОСЛУШИВАНИЮ';
        const sh = $('[data-action="shuffle"]'); sh.classList.toggle('is-active', shuffle); sh.setAttribute('aria-pressed', String(shuffle));
        sh.setAttribute('aria-label', `Случайный порядок: ${shuffle ? 'включён' : 'выключен'}`); sh.title = sh.getAttribute('aria-label');
        const re = $('[data-action="repeat"]'); re.classList.toggle('is-active', repeat !== 'off'); re.setAttribute('aria-label', `Повтор: ${repeat === 'off' ? 'выключен' : repeat === 'one' ? 'одного трека' : 'плейлиста'}`); re.title = re.getAttribute('aria-label'); $('[data-repeat-one]').hidden = repeat !== 'one';
        syncVolume();
        for (const row of $('[data-tracks]').children) {
            row.classList.toggle('is-current', Number(row.dataset.trackId) === current);
            const control = row.querySelector('.rp-track-play'); setIcon(control, Number(row.dataset.trackId) === current && playing ? 'pause' : 'play');
            control.setAttribute('aria-label', `${Number(row.dataset.trackId) === current && playing ? 'Приостановить' : 'Воспроизвести'} ${track(row.dataset.trackId)?.title || ''}`);
        }
        if ('mediaSession' in navigator) navigator.mediaSession.playbackState = playing ? 'playing' : 'paused';
    }
    function setBuffering(active) {
        loading = Boolean(active && current && !audio.paused && !audio.ended);
        if (!loading) { clearTimeout(bufferTimer); bufferTimer = undefined; bufferVisible = false; clearTimeout(meterBufferTimer); meterBufferTimer = undefined; }
        else if (!bufferVisible && !bufferTimer) {
            bufferTimer = setTimeout(() => {
                bufferTimer = undefined;
                if (loading && !audio.paused) { bufferVisible = true; updateButtons(); }
            }, 350);
        }
        if (loading) {
            drawMeters(false);
            // A brief initial wait must not cancel Safari's just-unlocked
            // analysis request and restart its metadata download from scratch.
            if (!meterBufferTimer) meterBufferTimer = setTimeout(() => { meterBufferTimer = undefined; if (loading) stopMeters(true); }, 1500);
        }
        syncReels(); updateButtons(); prepareUpcoming();
    }
    function drawBuffered(duration, progress) {
        let end = audio.currentTime; const buffered = audio.buffered;
        for (let i = 0; i < buffered.length; i++) {
            if (buffered.start(i) <= audio.currentTime + .25 && buffered.end(i) >= audio.currentTime) end = buffered.end(i);
        }
        const fraction = duration > 0 ? Math.max(progress,Math.min(1,end/duration)) : 0;
        const value = `${(fraction * 100).toFixed(2)}%`;
        if (visuals.seek.style.getPropertyValue('--buffered') !== value) visuals.seek.style.setProperty('--buffered',value);
    }
    function drawProgress(forcePosition = false) {
        const duration = Number.isFinite(audio.duration) ? audio.duration : activeTrack()?.duration || 0;
        const progress = duration > 0 ? Math.max(0, Math.min(1, audio.currentTime / duration)) : 0;
        if (!seekActive) { visuals.seek.value = Math.round(progress * 1000); rangeFill(visuals.seek, progress); }
        drawBuffered(duration,seekActive ? Number(visuals.seek.value)/1000 : progress);
        visuals.seek.disabled = !current || !duration;
        const elapsed = formatTime(audio.currentTime), total = formatTime(duration);
        if (!seekActive && visuals.elapsed.textContent !== elapsed) visuals.elapsed.textContent = elapsed;
        if (visuals.duration.textContent !== total) visuals.duration.textContent = total;
        const inner = 78, outer = 174;
        const radii = [Math.sqrt(inner ** 2 + (outer ** 2 - inner ** 2) * (1 - progress)), Math.sqrt(inner ** 2 + (outer ** 2 - inner ** 2) * progress)];
        // A common outer tangent keeps the tape taut against both the changing
        // pack and the guide. These branches match counterclockwise reel motion:
        // the left reel pays out below, and the right reel takes up on its right.
        const run = (cx, radius, direction) => {
            const rollerX = direction === 1 ? 378 : 822;
            const rollerRadius = 28, tapeRadius = radius + 4;
            const dx = rollerX - cx, dy = 425 - 210, length = Math.hypot(dx, dy);
            const ux = dx / length, uy = dy / length;
            const along = (tapeRadius - rollerRadius) / length;
            const across = direction * Math.sqrt(1 - along ** 2);
            const nx = along * ux - across * uy, ny = along * uy + across * ux;
            const start = [cx + tapeRadius * nx, 210 + tapeRadius * ny];
            const end = [rollerX + rollerRadius * nx, 425 + rollerRadius * ny];
            // Wrap around the lower guide rim; the remaining head path is hidden.
            const innerX = rollerX + direction * rollerRadius;
            const sweep = direction === 1 ? 0 : 1;
            return `M${start[0].toFixed(2)} ${start[1].toFixed(2)} L${end[0].toFixed(2)} ${end[1].toFixed(2)} A${rollerRadius} ${rollerRadius} 0 0 ${sweep} ${innerX} 425 L${rollerX} 425`;
        };
        visuals.reels.forEach((reel,i) => {
            const radius = radii[i].toFixed(2);
            if (reel.pack.getAttribute('r') === radius) return;
            reel.pack.setAttribute('r',radius); reel.clip.setAttribute('r',radius);
            visuals.tape[i].setAttribute('d',run(i ? 970 : 230,radii[i],i ? -1 : 1));
        });
        packRadii = radii;
        syncReels();
        const key = `${generation}:${duration}:${Math.floor(audio.currentTime)}:${audio.playbackRate}`;
        if ((forcePosition || key !== positionKey) && 'mediaSession' in navigator && navigator.mediaSession.setPositionState && duration > 0) {
            try { navigator.mediaSession.setPositionState({duration, playbackRate:audio.playbackRate, position:Math.min(duration,audio.currentTime)}); } catch (_) {}
            positionKey = key;
        }
        return radii;
    }
    function syncReels() {
        const playing = !audio.paused && !audio.ended && !loading && !document.hidden && !reducedMotion.matches;
        visuals.reels.forEach((reel,i) => {
            if (!reel.motionInitialized) {
                reel.motionInitialized = true;
                try {
                    if (typeof reel.rotor.animate === 'function') {
                        reel.motion = reel.rotor.animate([{transform:'rotate(0deg)'},{transform:'rotate(-360deg)'}],{duration:360 / 95 * 1000,iterations:Infinity});
                        reel.motion.pause();
                    }
                } catch (_) { /* Older browsers retain the frame-based fallback. */ }
            }
            if (!reel.motion) return;
            const rate = 126 / packRadii[i] * audio.playbackRate;
            // updatePlaybackRate preserves the angle as the pack changes size.
            // The compositor then rotates without waiting for JS meter samples.
            if (Math.abs(reel.motion.playbackRate - rate) > .001) {
                if (reel.motion.updatePlaybackRate) reel.motion.updatePlaybackRate(rate);
                else reel.motion.playbackRate = rate;
            }
            if (playing) { if (reel.motion.playState === 'paused' && !reel.motion.pending) reel.motion.play(); }
            else if (reel.motion.playState !== 'paused') reel.motion.pause();
        });
    }
    function initGraph() {
        const capture = audio.captureStream || audio.mozCaptureStream;
        if (graph?.context.state === 'closed') { releaseGraph(); }
        if (graph) { if (graph.capture) { try { refreshCapture(); } catch (_) { initMirror(); } } return; }
        if (!(window.AudioContext || window.webkitAudioContext)) return;
        let context;
        try {
            // Observe a copy of the signal. The native audio element remains the
            // only audible output, including when iOS suspends Web Audio in PWA.
            context = new (window.AudioContext || window.webkitAudioContext)();
            const splitter = context.createChannelSplitter(2);
            const analysers = [context.createAnalyser(), context.createAnalyser()];
            analysers.forEach((a,i) => { a.fftSize = 256; a.smoothingTimeConstant = .78; splitter.connect(a, i); });
            // Keep both analysis branches pulled by the audio engine. The sink
            // is silent; native HTML Audio remains the only audible output.
            const silent = context.createGain(); silent.gain.value = 0;
            analysers.forEach(a => a.connect(silent)); silent.connect(context.destination);
            graph = {context, splitter, analysers, source:null, capturedTrack:null, samples:new Float32Array(256), capture:meterRecovery.forceMirror ? null : capture, captureGeneration:-1};
            context.addEventListener('statechange', () => { if (graph?.context === context && !graph.resumePending && !document.hidden && !audio.paused && !audio.ended) resumeMeters(); });
            if (graph.capture) { try { refreshCapture(); } catch (_) { initMirror(); } }
            else initMirror();
        } catch (_) { graph = null; context?.close().catch(() => {}); }
    }
    function initMirror() {
        detachCapture(); graph.capture = null; meterRecovery.forceMirror = true;
        // A separate, silent output keeps the native player responsible for
        // sound and background playback. Never mute the source before analysis.
        const active = graph, mirror = new Audio(); mirror.preload = 'auto'; mirror.setAttribute('playsinline','');
        const source = active.context.createMediaElementSource(mirror);
        source.channelCount = 2; source.channelCountMode = 'explicit'; source.connect(active.splitter);
        active.mirror = mirror; active.source = source;
        for (const event of ['loadedmetadata','canplay','playing','seeked','ended']) mirror.addEventListener(event, () => { if (graph === active) syncMirror(); });
        mirror.addEventListener('error', () => { if (graph === active && mirror.error && mirror.error.code !== 1) blockMeters(); });
    }
    function detachCapture() {
        if (!graph?.stream) return;
        graph.stream.removeEventListener('addtrack', bindCapture);
        graph.stream.removeEventListener('removetrack', bindCapture);
        graph.capturedTrack?.removeEventListener('ended', bindCapture);
        graph.source?.disconnect(); graph.source = null; graph.capturedTrack = null;
        graph.stream.getTracks().forEach(track => track.stop()); graph.stream = null;
    }
    function releaseGraph() {
        if (!graph) return;
        detachCapture();
        const previous = graph; graph = null;
        // Ignore events and late promises from the discarded engine.
        if (previous.mirror) { previous.mirror.pause(); previous.mirror.removeAttribute('src'); previous.mirror.load(); }
        previous.source?.disconnect(); previous.context.close().catch(() => {});
    }
    function refreshCapture() {
        if (!graph?.capture) return;
        if (graph.captureGeneration === generation && graph.stream?.getAudioTracks().some(track => track.readyState === 'live')) { bindCapture(); return; }
        detachCapture();
        graph.stream = graph.capture.call(audio); graph.captureGeneration = generation;
        graph.stream.addEventListener('addtrack', bindCapture);
        graph.stream.addEventListener('removetrack', bindCapture);
        bindCapture();
    }
    function bindCapture() {
        if (!graph?.stream) return;
        const capturedTrack = graph.stream.getAudioTracks().find(t => t.readyState === 'live');
        if (capturedTrack === graph.capturedTrack) return;
        graph.capturedTrack?.removeEventListener('ended', bindCapture);
        graph.source?.disconnect(); graph.source = null; graph.capturedTrack = capturedTrack;
        if (!capturedTrack) return;
        capturedTrack.addEventListener('ended', bindCapture);
        try {
            const source = graph.context.createMediaStreamSource(new MediaStream([capturedTrack]));
            source.channelCount = 2; source.channelCountMode = 'explicit';
            source.connect(graph.splitter); graph.source = source;
        } catch (_) { graph.capturedTrack = null; }
    }
    function resumeMeters(userGesture = false) {
        userGesture = userGesture === true;
        if (!document.hidden && !userGesture && audio.paused && !audio.ended && graph?.gestureUntil > performance.now()) return;
        if (document.hidden || (!userGesture && (audio.paused || audio.ended))) { stopMeters(); return; }
        if (!audio.src) return;
        if (meterRecovery.generation !== generation) {
            meterRecovery.generation = generation; meterRecovery.rebuilds = 0; setMeterBlocked(false);
            if (graph) graph.watch = null;
        }
        if (userGesture) {
            // iOS may leave a resume() promise pending forever, even while the
            // native song plays. A new tap must not be held by that old promise.
            if (meterRecovery.blocked || graph?.resuming || (graph && graph.context.state !== 'running')) releaseGraph();
            meterRecovery.rebuilds = 0; setMeterBlocked(false);
        } else if (meterRecovery.blocked) { setMeterBlocked(true); return; }
        try { initGraph(); } catch (_) { return; }
        if (!graph) { blockMeters(); return; }
        if (userGesture) graph.gestureUntil = performance.now() + 1500;
        if (!checkMeterHealth(userGesture)) return;
        if (userGesture) {
            // WebKit can queue resume() until after the trusted tap ends.
            // Starting a scheduled source removes its gesture restriction now.
            // One zero sample is silent and never feeds the music analysers.
            try {
                const wake = graph.context.createBufferSource();
                wake.buffer = graph.context.createBuffer(1,1,graph.context.sampleRate);
                wake.connect(graph.context.destination); wake.onended = () => wake.disconnect(); wake.start();
            } catch (_) {}
        }
        if (graph.context.state !== 'running' && !graph.resuming) {
            const active = graph;
            active.resumePending = true;
            try {
                const pending = active.context.resume().then(() => { if (graph === active && !audio.paused) syncMirror(); }).catch(() => {}).finally(() => { if (active.resuming === pending) { active.resuming = null; active.resumePending = false; } });
                active.resuming = pending;
            } catch (_) { active.resumePending = false; }
        }
        syncMirror(userGesture);
        if (!meterTimer) meterTimer = setInterval(resumeMeters, 500);
    }
    function setMeterBlocked(blocked) {
        meterRecovery.blocked = blocked;
        const notice = $('[data-meter-recovery]'); if (notice) notice.hidden = !blocked || audio.paused || audio.ended;
    }
    function blockMeters() { stopMeters(true); releaseGraph(); setMeterBlocked(true); }
    function checkMeterHealth(userGesture = false) {
        const active = graph, now = performance.now(), clock = active.context.currentTime;
        if (!active.watch || loading || audio.seeking || audio.readyState < 2) {
            active.watch = {time:now, native:audio.currentTime, clock, mirror:active.mirror?.currentTime}; return true;
        }
        const watch = active.watch;
        if (now - watch.time < 2000) return true;
        const progressing = audio.currentTime - watch.native > .05;
        // Use clocks, not sample amplitudes: a genuinely silent song is valid.
        const stoppedEngine = progressing && clock - watch.clock < .01;
        const requested = active.mirror?.hasAttribute('src') && (audio.readyState >= 3 || bufferedAhead() >= .25);
        const stoppedMirror = progressing && requested && active.mirror.readyState >= 2 && !active.mirror.seeking && active.mirror.currentTime - watch.mirror < .01;
        const stuckRequest = progressing && requested && now - active.mirrorLoadStarted > 15000 && (active.mirror.readyState < 2 || active.mirror.seeking);
        if (stoppedEngine || stoppedMirror || stuckRequest) {
            releaseGraph(); drawMeters(false);
            if (meterRecovery.rebuilds++ < 1) resumeMeters(userGesture); else blockMeters();
            return false;
        }
        // captureStream can exist yet provide no live audio track. Fall back
        // once rather than constructing a new context on every timer tick.
        if (progressing && active.capture && (!active.capturedTrack || active.capturedTrack.muted)) { initMirror(); syncMirror(); }
        active.watch = {time:now, native:audio.currentTime, clock, mirror:active.mirror?.currentTime};
        return true;
    }
    function stopMeters(unload = false) {
        unload ||= activeTrack()?.source === 'drive';
        clearInterval(meterTimer); meterTimer = undefined;
        if (graph) { graph.watch = null; graph.gestureUntil = 0; }
        const notice = $('[data-meter-recovery]'); if (notice) notice.hidden = true;
        if (graph?.mirror) {
            graph.mirror.pause();
            if (unload) { graph.mirrorStarting = null; graph.mirrorAligned = ''; graph.mirror.removeAttribute('src'); graph.mirror.load(); }
        }
        drawMeters(false);
    }
    function syncMirror(userGesture = false) {
        const mirror = graph?.mirror; if (!mirror) return;
        if (userGesture !== true && audio.paused && !audio.ended && !document.hidden && graph.gestureUntil > performance.now()) return;
        const cloud = activeTrack()?.source === 'drive', connection = navigator.connection;
        const enough = audio.readyState >= 3 || bufferedAhead() >= .25;
        // Unlock Safari's analysis element in the same user gesture as native
        // play(). Later resumes yield while the main player is actually waiting.
        const waiting = userGesture !== true && (loading || audio.seeking || !enough);
        if (document.hidden || (userGesture !== true && (audio.paused || audio.ended)) || (cloud && (connection?.saveData || ['slow-2g','2g'].includes(connection?.effectiveType)))) {
            mirror.pause();
            if (mirror.hasAttribute('src')) { graph.mirrorAligned = ''; mirror.removeAttribute('src'); mirror.load(); }
            return;
        }
        if (cloud && waiting) {
            // Preserve the initial request during a short main-player wait.
            // Persistent buffering unloads it after the grace period above.
            if (!loading || !meterBufferTimer || audio.seeking) mirror.pause();
            return;
        }
        const url = new URL(audio.src,location.href); if (cloud) url.searchParams.set('meter','1');
        if (mirror.src !== url.href) { graph.mirrorAligned = ''; graph.mirrorLoadStarted = performance.now(); mirror.src = url.href; mirror.load(); }
        const drift = audio.currentTime - mirror.currentTime;
        // Align once at metadata, then let a pending seek finish decoding. A
        // moving target at every canplay/timer used to repeatedly discard it.
        if (mirror.readyState >= 1 && !mirror.seeking && (graph.mirrorAligned !== url.href || (mirror.readyState >= 3 && !mirror.paused && !graph.mirrorStarting && Math.abs(drift) > 1))) {
            try { if (Math.abs(drift) > .1) mirror.currentTime = audio.currentTime; graph.mirrorAligned = url.href; } catch (_) {}
        }
        // Small drift is caught up by the silent copy without another Range
        // request. Never change the audible player's position or speed.
        mirror.playbackRate = audio.playbackRate * (Math.abs(drift) < 1 ? 1 + Math.max(-.1,Math.min(.1,drift * .2)) : 1);
        if (mirror.paused && !graph.mirrorStarting) {
            const active = graph, version = generation;
            const pending = mirror.play().catch(error => { if (error.name !== 'AbortError' && graph === active && active.mirrorStarting === pending && version === generation && mirror.src === url.href) blockMeters(); }).finally(() => { if (active.mirrorStarting === pending) active.mirrorStarting = null; });
            active.mirrorStarting = pending;
        }
    }
    function configureAudioSession() {
        try { if (navigator.audioSession) navigator.audioSession.type = 'playback'; } catch (_) {}
    }
    function drawMeters(playing) {
        visuals.reels.forEach((reel,i) => {
            let level = 0;
            if (playing && graph?.context.state === 'running' && !audio.muted && audio.volume > 0 && (!graph.mirror || (!graph.mirror.paused && !graph.mirror.seeking && graph.mirror.readyState >= 2 && Math.abs(graph.mirror.currentTime-audio.currentTime) < .75))) {
                try {
                    graph.analysers[i].getFloatTimeDomainData(graph.samples);
                    let sum = 0; for (let n=0; n<graph.samples.length; n++) sum += graph.samples[n] * graph.samples[n];
                    const rms = Math.sqrt(sum / graph.samples.length) * audio.volume;
                    level = Math.max(0, Math.min(15, (20 * Math.log10(Math.max(rms, .00001)) + 45) / 3));
                } catch (_) {}
            }
            const lit = Math.ceil(level);
            if (lit === meterLevels[i]) return;
            reel.bars.forEach((bar,n) => { if ((n < lit) !== (n < meterLevels[i])) bar.classList.toggle('is-lit',n < lit); });
            meterLevels[i] = lit;
        });
    }
    function animate(time) {
        frameId = 0;
        const playing = !audio.paused && !audio.ended;
        const elapsed = frameTime ? Math.min(.1, (time - frameTime) / 1000) : 0; frameTime = time;
        // Rotation follows the display cadence; geometry and audio sampling do
        // not need to repaint the entire deck on every display frame.
        if (time - progressFrame >= 100) { drawProgress(); progressFrame = time; }
        if (time - meterFrame >= 1000 / 30) { drawMeters(playing && !loading); meterFrame = time; }
        if (playing && !loading && !reducedMotion.matches) {
            visuals.reels.forEach((reel,i) => { if (!reel.motion) { angles[i] = (angles[i] - elapsed * 95 * 126 / packRadii[i] * audio.playbackRate) % 360; reel.rotor.style.transform = `rotate(${angles[i]}deg)`; } });
        }
        if (playing && !document.hidden) frameId = requestAnimationFrame(animate);
    }
    function startAnimation() { syncReels(); if (!frameId && !document.hidden) { frameTime = 0; progressFrame = meterFrame = -Infinity; frameId = requestAnimationFrame(animate); } }
    async function play() {
        if (!current) {
            const first = selectedTracks()[0]; if (!first) return;
            setQueue(); load(first.id);
        }
        configureAudioSession();
        if (audio.error) load(current,audio.currentTime || 0);
        if (audio.ended) audio.currentTime = 0;
        const version = generation;
        // Keep play() inside the tap's activation; meter setup never gates sound.
        try { const playback = audio.play(); resumeMeters(true); await playback; }
        catch (error) { if (version === generation && error.name !== 'AbortError') { setBuffering(false); stopMeters(true); toast('Не удалось воспроизвести трек. Проверьте формат, соединение и доступ к файлу.', true); } }
    }
    function shuffled(exclude) {
        const ids = queue.filter(id => id !== exclude);
        for (let i = ids.length-1; i > 0; i--) { const j = Math.floor(Math.random()*(i+1)); [ids[i],ids[j]] = [ids[j],ids[i]]; }
        return ids.length ? ids : [exclude];
    }
    function upcoming() {
        if (!current || !queue.length || repeat === 'one') return [];
        if (shuffle) {
            if (!bag.length && !shuffleCycleStarted) { bag = shuffled(current); shuffleCycleStarted = true; }
            const ids = bag.slice(-2).reverse();
            if (repeat === 'all' && ids.length < 2) {
                if (!futureBag.length) futureBag = shuffled(ids.at(-1) || current);
                ids.push(...futureBag.slice(-(2-ids.length)).reverse());
            }
            return [...new Set(ids)].filter(id => id !== current).map(track).filter(Boolean);
        }
        const index = queue.indexOf(current), ids = [];
        for (let step=1; step<=2; step++) {
            if (index+step >= queue.length && repeat === 'off') break;
            const id = queue[(index+step)%queue.length]; if (id !== current && !ids.includes(id)) ids.push(id);
        }
        return ids.map(track).filter(Boolean);
    }
    function bufferedAhead() {
        for (let i=0; i<audio.buffered.length; i++) if (audio.buffered.start(i) <= audio.currentTime+.1 && audio.buffered.end(i) >= audio.currentTime) return audio.buffered.end(i)-audio.currentTime;
        return 0;
    }
    function prepareUpcoming() {
        const connection = navigator.connection;
        const enough = bufferedAhead() >= 8 || (Number.isFinite(audio.duration) && bufferedAhead() >= audio.duration-audio.currentTime-.25 && audio.readyState >= 3);
        const allowed = !document.hidden && !audio.paused && !audio.ended && !audio.seeking && !loading && enough && !connection?.saveData && !['slow-2g','2g'].includes(connection?.effectiveType);
        preloader.schedule(upcoming(),allowed);
    }
    function setQueue() { preloader.reset(); queue = selectedTracks().map(t => t.id); queuePlaylist = selected; queueSort = selectedSort(); queueName = collectionName(selected); bag = []; futureBag = []; shuffleCycleStarted = false; history = []; }
    function load(id, time = 0) {
        const t = track(id); if (!t) return;
        preloader.cancel(); generation++; audio.pause(); stopMeters(true); current = t.id; restoreTime = time; setBuffering(false);
        audio.src = t.url; audio.load(); render(); drawProgress(); save();
    }
    function next(automatic = false) {
        if (!current || !queue.length) return;
        if (automatic && repeat === 'one') { audio.currentTime = 0; play(); return; }
        let id;
        if (shuffle) {
            if (!bag.length) {
                if (automatic && repeat === 'off' && shuffleCycleStarted) return;
                bag = futureBag.length ? futureBag : shuffled(current); futureBag = []; shuffleCycleStarted = true;
                if (queue.length === 1 && automatic && repeat === 'off') return;
            }
            id = bag.pop();
        } else {
            const index = queue.indexOf(current);
            if (automatic && index >= queue.length - 1 && repeat === 'off') return;
            id = queue[(index + 1) % queue.length];
        }
        history.push(current); load(id); play();
    }
    function previous() {
        if (!current) return;
        if (audio.currentTime > 3) { audio.currentTime = 0; drawProgress(); return; }
        const id = shuffle && history.length ? history.pop() : queue[(Math.max(0, queue.indexOf(current)) - 1 + queue.length) % queue.length];
        if (id) { futureBag = []; load(id); play(); }
    }
    function applyState(result) { data = {tracks:result.tracks, playlists:result.playlists}; render(); save(); }
    function mutate(operation) {
        // Library snapshots must be applied in commit order. Metadata, uploads
        // and button actions share this queue so an older reply cannot erase rows.
        mutations++;
        const pending = mutationQueue.then(operation);
        mutationQueue = pending.catch(() => {});
        return pending.finally(() => { mutations--; });
    }
    async function api(path, form) {
        const response = await fetch(`${config.api}/${path}`, {method:form ? 'POST' : 'GET', credentials:'same-origin', headers:form ? {'X-CSRF-Token':config.csrf,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'} : {'Accept':'application/json'}, body:form});
        let result;
        try { result = await response.json(); } catch (_) { throw new Error(response.status === 401 || response.status === 403 || response.redirected ? 'Сессия завершена. Обновите страницу и войдите в CMS.' : 'Сервер не ответил. Попробуйте ещё раз.'); }
        if (!response.ok || !result.status) throw new Error(result.message || (response.status === 419 ? 'Обновите страницу: срок действия сессии истёк.' : 'Не удалось выполнить действие.'));
        return result;
    }
    async function action(name, values = {}, cover) {
        const form = new FormData(); form.set('action', name); form.set('needCSRFToken', config.csrf);
        for (const [key,value] of Object.entries(values)) {
            if (Array.isArray(value)) value.forEach(item => form.append(`${key}[]`, item)); else form.set(key, value);
        }
        if (cover?.size) form.set('cover', cover);
        return mutate(async () => { const result = await api('action', form); if (result.tracks) applyState(result); return result; });
    }
    function openDialog(title, fields, handler, description = '', submit = 'Сохранить', danger = false) {
        if (coverPreviewUrl) { URL.revokeObjectURL(coverPreviewUrl); coverPreviewUrl = undefined; }
        dialogSubmitting = false;
        dialog.classList.remove('rp-dialog--drive');
        $('[data-dialog-title]').textContent = title;
        $('[data-dialog-fields]').innerHTML = fields;
        $('[data-dialog-description]').textContent = description; $('[data-dialog-description]').hidden = !description;
        $('[data-dialog-error]').hidden = true;
        const control = $('[data-dialog-submit]'); control.textContent = submit; control.disabled = false; control.hidden = false; control.classList.toggle('rp-button--danger', danger);
        dialogHandler = handler; dialog.showModal();
    }
    const field = (name, label, value = '', max = 240) => `<label class="rp-field">${label}<input name="${name}" value="${esc(value)}" maxlength="${max}" ${name === 'artist' ? '' : 'required'}></label>`;
    function createPlaylist() {
        openDialog('Новая лента', field('name','Название плейлиста','',160), async form => { const result = await action('playlist.create',{name:form.get('name')}); selected = result.id; render(); save(); });
    }
    function editTrack(id) {
        const t = track(id); if (!t) return;
        openDialog('О композиции', field('title','Название',t.title) + field('artist','Исполнитель',t.artist) + `<div class="rp-cover-field"><span class="rp-field-label">Обложка</span><label class="rp-cover-picker"><input class="rp-file-input" type="file" name="cover" accept="image/jpeg,image/png,image/webp" aria-label="Выбрать обложку"><img data-cover-preview src="${esc(t.cover || config.defaultCover)}" alt="Предварительный просмотр обложки"><span class="rp-cover-picker-info"><span class="rp-cover-picker-button">${icon('upload')}Выбрать обложку</span><span data-cover-filename>JPG, PNG или WebP · до 8 МБ</span></span></label></div>`, form => action('track.update',{id,title:form.get('title'),artist:form.get('artist')},form.get('cover')));
        dialog.querySelector('[name="cover"]').addEventListener('change', event => {
            const file = event.target.files[0]; if (!file) return;
            if (file.size > 8 * 1048576 || !/\.(jpe?g|png|webp)$/i.test(file.name)) {
                event.target.value = '';
                if (coverPreviewUrl) { URL.revokeObjectURL(coverPreviewUrl); coverPreviewUrl = undefined; }
                dialog.querySelector('[data-cover-preview]').src = t.cover || config.defaultCover;
                dialog.querySelector('[data-cover-filename]').textContent = 'JPG, PNG или WebP · до 8 МБ';
                toast('Выберите JPG, PNG или WebP размером до 8 МБ.',true); return;
            }
            if (coverPreviewUrl) URL.revokeObjectURL(coverPreviewUrl);
            coverPreviewUrl = URL.createObjectURL(file);
            dialog.querySelector('[data-cover-preview]').src = coverPreviewUrl;
            dialog.querySelector('[data-cover-filename]').textContent = file.name;
        });
    }
    function readDuration(source) {
        return new Promise(resolve => {
            const probe = new Audio(); probe.preload = 'metadata';
            const local = source instanceof Blob, url = local ? URL.createObjectURL(source) : source;
            let timer;
            const finish = () => {
                const duration = Number.isFinite(probe.duration) && probe.duration > 0 && probe.duration <= 604800 ? probe.duration : 0;
                clearTimeout(timer); probe.onloadedmetadata = probe.onerror = null;
                probe.removeAttribute('src'); probe.load(); if (local) URL.revokeObjectURL(url);
                resolve(duration);
            };
            probe.onloadedmetadata = probe.onerror = finish;
            timer = setTimeout(finish, 5000); probe.src = url;
        });
    }
    async function uploadFiles(files) {
        if (uploading) { toast('Дождитесь завершения текущей загрузки.'); return; }
        if (!files.length) return;
        uploading = true; const destination = selected > 0 ? selected : 0, favorite = selected === -1; const progress = $('[data-upload-progress]'); progress.hidden = false;
        let completed = 0, failed = 0;
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            $('[data-upload-message]').textContent = `${i+1} / ${files.length} · ${file.name}`;
            try {
                if (file.size > config.maxUpload) throw new Error(`${file.name}: файл превышает лимит ${(config.maxUpload/1048576).toFixed(1)} МБ.`);
                if (!/\.(mp3|flac|wav|m4a|ogg|opus|aac|webm)$/i.test(file.name)) throw new Error(`${file.name}: неподдерживаемый формат.`);
                const duration = await readDuration(file);
                const form = new FormData(); form.set('audio',file); form.set('playlist_id', destination); form.set('duration', duration); form.set('favorite', favorite ? '1' : '0'); form.set('needCSRFToken',config.csrf);
                progress.querySelector('progress').value = 0;
                const result = await mutate(async () => {
                    const response = await new Promise((resolve,reject) => {
                        const xhr = new XMLHttpRequest(); xhr.open('POST',`${config.api}/upload`); xhr.timeout = 300000;
                        xhr.setRequestHeader('X-CSRF-Token',config.csrf); xhr.setRequestHeader('X-Requested-With','XMLHttpRequest'); xhr.setRequestHeader('Accept','application/json');
                        xhr.upload.onprogress = event => { if (event.lengthComputable) progress.querySelector('progress').value = event.loaded / event.total * 100; };
                        xhr.onload = () => { try { const r = JSON.parse(xhr.responseText); if (xhr.status >= 200 && xhr.status < 300 && r.status) resolve(r); else reject(new Error(r.message || 'Ошибка загрузки.')); } catch (_) { reject(new Error('Сессия завершена или сервер недоступен. Обновите страницу.')); } };
                        xhr.onerror = () => reject(new Error('Загрузка прервана. Проверьте соединение.')); xhr.ontimeout = () => reject(new Error('Истекло время загрузки. Попробуйте ещё раз.'));
                        xhr.send(form);
                    });
                    applyState(response); return response;
                });
                completed++;
                if (!current) { setQueue(); load(result.id); }
            } catch (error) { failed++; toast(error.message,true); }
        }
        uploading = false; progress.hidden = true; $('[data-audio-files]').value = '';
        if (!failed) toast(`Добавлено треков: ${completed}`);
        else if (completed) toast(`Добавлено: ${completed}. Не удалось загрузить: ${failed}.`,true);
    }

    app.addEventListener('click', async event => {
        const control = event.target.closest('[data-action]'); if (!control || control.disabled) return;
        const name = control.dataset.action, id = Number(control.dataset.id);
        try {
            switch (name) {
                case 'play': if (audio.paused) await play(); else audio.pause(); break;
                case 'prev': previous(); break;
                case 'next': next(); break;
                case 'play-track': setQueue(); if (current === id) { if (audio.paused) await play(); else audio.pause(); } else { load(id); await play(); } break;
                case 'shuffle': shuffle = !shuffle; bag = []; futureBag = []; shuffleCycleStarted = false; history = []; preloader.reset(); updateButtons(); prepareUpcoming(); save(); break;
                case 'repeat': repeat = {off:'all',all:'one',one:'off'}[repeat]; preloader.cancel(); futureBag = []; updateButtons(); prepareUpcoming(); save(); break;
                case 'mute': toggleMute(); break;
                case 'meters': if (!audio.paused && !audio.ended) resumeMeters(true); break;
                case 'theme': theme = theme === 'dark' ? 'light' : 'dark'; applyTheme(); break;
                case 'select-playlist': selected = id; $('[data-search]').value = ''; render(); save(); break;
                case 'favorite': control.disabled = true; await action('track.favorite',{id,favorite:track(id).favorite ? 0 : 1}); break;
                case 'upload': $('[data-audio-files]').click(); break;
                case 'create-playlist': createPlaylist(); break;
                case 'close-dialog': if (!dialogSubmitting) dialog.close(); break;
                case 'edit-current': editTrack(current); break;
                case 'edit-track': editTrack(id); break;
                case 'rename-playlist': openDialog('Название ленты', field('name','Название плейлиста',playlist(selected).name,160), form => action('playlist.rename',{id:selected,name:form.get('name')})); break;
                case 'delete-playlist': openDialog('Удалить ленту?', '', () => action('playlist.delete',{id:selected}), `Плейлист «${playlist(selected).name}» будет удалён. Музыка останется в общей библиотеке.`, 'Удалить', true); break;
                case 'delete-track': openDialog('Удалить композицию?', '', () => action('track.delete',{id}), `«${track(id).title}» будет удалена из библиотеки и всех плейлистов.${track(id).source === 'drive' ? ' Файл на Google Drive останется.' : ' Загруженный аудиофайл будет удалён с сервера.'}`, 'Удалить', true); break;
                case 'remove-track': control.disabled = true; await action('playlist.remove',{id:selected,track_id:id}); break;
                case 'add-to-playlist':
                    if (!data.playlists.length) { createPlaylist(); break; }
                    openDialog('Добавить на ленту', `<label class="rp-field">Плейлист<select name="playlist">${data.playlists.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('')}</select></label>`, async form => { await action('playlist.add',{id:form.get('playlist'),track_id:id}); toast('Трек добавлен в плейлист.'); }); break;
                case 'move-up': case 'move-down': {
                    if (mutations) break;
                    control.disabled = true; const ids = [...playlist(selected).tracks], index = ids.indexOf(id), target = index + (name === 'move-up' ? -1 : 1);
                    if (target >= 0 && target < ids.length) { [ids[index],ids[target]] = [ids[target],ids[index]]; await action('playlist.reorder',{id:selected,tracks:ids}); }
                    break;
                }
                case 'drive': await openDrive(); break;
                case 'drive-settings': await driveSettings(); break;
                case 'player-settings': await driveSettings(); break;
                case 'drive-connect': {
                    if (!config.drive?.configured) { toast('Создатель сайта ещё не настроил подключение Google Drive.',true); break; }
                    control.disabled = true; const result = await action('drive.connect'); location.assign(result.authUrl); break;
                }
                case 'drive-disconnect': openDialog('Отключить Google Drive?', '', async () => { await action('drive.disconnect'); config.drive.connected = false; toast('Google Drive отключён.'); }, 'Ленты сохранятся. Чтобы слушать облачные треки, подключите аккаунт снова.', 'Отключить'); break;
                case 'drive-more': await driveFiles(control.dataset.page); break;
                case 'drive-select-all': {
                    const ids = [...driveKnownFiles.keys()], allSelected = ids.every(id => driveSelection.has(id));
                    for (const fileId of ids) {
                        if (allSelected) driveSelection.delete(fileId);
                        else if (driveSelection.size < 100) driveSelection.add(fileId);
                    }
                    dialog.querySelectorAll('[data-drive-file]').forEach(box => { box.checked = driveSelection.has(box.value); });
                    updateDriveSelection(); break;
                }
                case 'drive-clear': driveSelection.clear(); dialog.querySelectorAll('[data-drive-file]').forEach(box => { box.checked = false; }); updateDriveSelection(); break;
                case 'drive-folder': drivePath.push({id:control.dataset.folderId,name:control.dataset.folderName}); await navigateDrive(); break;
                case 'drive-root': drivePath = [{id:'root',name:'Мой Drive'}]; await navigateDrive(); break;
                case 'drive-parent': drivePath = drivePath.slice(0,Number(control.dataset.depth)+1); await navigateDrive(); break;
                case 'drive-all': drivePath = []; await navigateDrive(); break;
                case 'drive-add-folder':
                    dialog.querySelector('[name="link"]').value = `https://drive.google.com/drive/folders/${drivePath.at(-1).id}`;
                    updateDriveSelection(); toast('Ссылка на текущую папку подставлена. Нажмите «Добавить музыку».'); break;
            }
        } catch (error) { toast(error.message,true); if (control.isConnected) control.disabled = false; }
    });
    $('[data-dialog-form]').addEventListener('submit', async event => {
        event.preventDefault(); const control = $('[data-dialog-submit]'); if (control.disabled) return;
        dialogSubmitting = true; control.disabled = true; $('[data-dialog-error]').hidden = true;
        try { await dialogHandler(new FormData(event.currentTarget)); dialog.close(); }
        catch (error) { $('[data-dialog-error]').textContent = error.message; $('[data-dialog-error]').hidden = false; }
        finally { dialogSubmitting = false; control.disabled = false; }
    });
    dialog.addEventListener('cancel', event => { if (dialogSubmitting) event.preventDefault(); });
    $('[data-search]').addEventListener('input', render);
    $('[data-sort]').addEventListener('click', event => {
        if (event.target.closest('[data-sort-trigger]')) {
            $('[data-sort-options]').hidden ? openSort() : closeSort();
            return;
        }
        const option = event.target.closest('[data-sort-mode]'); if (!option) return;
        if (selected > 0) playlistSort = option.dataset.sortMode; else librarySort = option.dataset.sortMode;
        render(); save();
    });
    $('[data-sort]').addEventListener('keydown', event => {
        const keys = ['ArrowDown','ArrowUp','Home','End','Escape']; if (!keys.includes(event.key)) return;
        event.preventDefault(); event.stopPropagation();
        if (event.key === 'Escape') { closeSort(); return; }
        if ($('[data-sort-options]').hidden) { openSort(); return; }
        const options = [...$('[data-sort-options]').children], index = options.indexOf(document.activeElement);
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? options.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
        options[next]?.focus();
    });
    $('[data-sort]').addEventListener('focusout', event => { if (!$('[data-sort]').contains(event.relatedTarget)) closeSort(); });
    document.addEventListener('click', event => { if (!$('[data-sort]').contains(event.target)) closeSort(); });
    $('[data-volume]').addEventListener('input', event => { const volume = Number(event.target.value); audio.volume = volume; if (volume > 0) audibleVolume = volume; audio.muted = volume === 0; syncVolume(); save(); });
    audio.addEventListener('volumechange', syncVolume);
    $('[data-seek]').addEventListener('input', event => { seekActive = true; const fraction = Number(event.target.value) / 1000; rangeFill(event.target,fraction); $('[data-elapsed]').textContent = formatTime(audio.duration * fraction); });
    $('[data-seek]').addEventListener('change', event => { if (Number.isFinite(audio.duration)) audio.currentTime = audio.duration * Number(event.target.value) / 1000; seekActive = false; drawProgress(); save(); });
    $('[data-audio-files]').addEventListener('change', event => uploadFiles([...event.target.files]));
    audio.addEventListener('loadedmetadata', () => {
        if (restoreTime) {
            if (Number.isFinite(audio.duration)) audio.currentTime = restoreTime >= audio.duration - .25 ? 0 : Math.max(0, restoreTime);
            restoreTime = 0;
        }
        drawProgress();
        const t = activeTrack();
        if (t && Number.isFinite(audio.duration) && Math.abs(t.duration - audio.duration) > 1) {
            t.duration = audio.duration;
            action('track.duration',{id:t.id,duration:audio.duration}).catch(() => {});
        }
    });
    audio.addEventListener('play', () => { setBuffering(audio.readyState < 3); startAnimation(); });
    audio.addEventListener('playing', () => { setBuffering(false); resumeMeters(); startAnimation(); });
    audio.addEventListener('waiting', () => { setBuffering(true); });
    audio.addEventListener('stalled', () => { if (audio.readyState < 3) setBuffering(true); });
    audio.addEventListener('canplay', () => { if (audio.readyState >= 3) setBuffering(false); });
    audio.addEventListener('progress', () => { drawProgress(); prepareUpcoming(); });
    audio.addEventListener('seeking', () => { preloader.cancel(); if (audio.readyState < 3) setBuffering(true); drawProgress(); });
    audio.addEventListener('pause', () => { preloader.cancel(); setBuffering(false); stopMeters(); save(); });
    audio.addEventListener('timeupdate', () => { drawProgress(); prepareUpcoming(); if (Date.now() - lastSave > 3000) { save(); lastSave = Date.now(); } });
    audio.addEventListener('seeked', () => { if (graph) graph.mirrorAligned = ''; drawProgress(true); syncMirror(); });
    audio.addEventListener('ratechange', () => { drawProgress(true); syncMirror(); });
    audio.addEventListener('ended', () => { setBuffering(false); stopMeters(); drawProgress(); next(true); });
    let errorProbe = -1;
    audio.addEventListener('error', async () => {
        if (!current) return; preloader.cancel(); setBuffering(false); stopMeters(true);
        const t = activeTrack(), version = generation;
        if (t?.source !== 'drive') { toast('Не удалось прочитать аудиофайл. Возможно, браузер не поддерживает его кодек.',true); return; }
        if (errorProbe === version) return; errorProbe = version;
        let message = 'Не удалось загрузить песню из Google Drive. Повторите воспроизведение или проверьте подключение.';
        const controller = new AbortController(), timeout = setTimeout(() => controller.abort(),10000);
        try {
            const response = await fetch(t.url,{credentials:'same-origin',cache:'no-store',headers:{Range:'bytes=0-1'},signal:controller.signal});
            if (response.redirected) message = 'Сессия завершена. Обновите страницу и войдите в CMS.';
            else if (!response.ok && response.headers.get('Content-Type')?.startsWith('text/plain')) message = (await response.text()).slice(0,500) || message;
            else if (response.status === 401) message = 'Сессия завершена. Обновите страницу и войдите в CMS.';
            else if (response.ok && [3,4].includes(audio.error?.code)) message = 'Файл Google Drive доступен, но браузер не смог прочитать аудио. Проверьте кодек файла.';
            else if (response.ok) message = 'Соединение с Google Drive прервалось. Нажмите воспроизведение, чтобы повторить.';
        } catch (_) {} finally { clearTimeout(timeout); }
        if (version === generation) toast(message,true);
    });
    $('[data-cover]').addEventListener('error', event => { if (event.target.getAttribute('src') !== config.defaultCover) event.target.src = config.defaultCover; });
    let dragDepth = 0;
    window.addEventListener('dragenter', event => { if ([...(event.dataTransfer?.types || [])].includes('Files')) { event.preventDefault(); dragDepth++; $('[data-drop-overlay]').hidden = false; } });
    window.addEventListener('dragover', event => { if ([...(event.dataTransfer?.types || [])].includes('Files')) event.preventDefault(); });
    window.addEventListener('dragleave', event => { if (--dragDepth <= 0) { dragDepth = 0; $('[data-drop-overlay]').hidden = true; } });
    window.addEventListener('drop', event => { event.preventDefault(); dragDepth = 0; $('[data-drop-overlay]').hidden = true; uploadFiles([...(event.dataTransfer?.files || [])]); });
    document.addEventListener('keydown', event => {
        if (dialog.open || /^(INPUT|TEXTAREA|SELECT|BUTTON)$/.test(event.target.tagName) || event.target.isContentEditable || event.altKey || event.ctrlKey || event.metaKey) return;
        if (event.code === 'Space') { event.preventDefault(); if (audio.paused) play(); else audio.pause(); }
        if (['ArrowLeft','ArrowRight'].includes(event.code)) {
            event.preventDefault();
            if (event.shiftKey) event.code === 'ArrowLeft' ? previous() : next();
            else if (Number.isFinite(audio.duration)) { audio.currentTime = Math.max(0,Math.min(audio.duration,audio.currentTime + (event.code === 'ArrowLeft' ? -5 : 5))); drawProgress(); }
        }
    });
    if ('mediaSession' in navigator) {
        // iOS uses the same lock-screen slots for track skipping and timed
        // seeking. Disable timed seek actions so this music player shows tracks.
        const handlers = {
            seekbackward:null, seekforward:null,
            play, pause:() => audio.pause(), previoustrack:previous, nexttrack:() => next(),
            seekto:event => { if (Number.isFinite(audio.duration)) audio.currentTime = Math.max(0,Math.min(audio.duration,event.seekTime)); }
        };
        for (const [name,handler] of Object.entries(handlers)) { try { navigator.mediaSession.setActionHandler(name,handler); } catch (_) {} }
    }
    window.addEventListener('pagehide', save);
    document.addEventListener('visibilitychange', () => {
        save(); syncReels();
        if (document.hidden) {
            preloader.cancel(); cancelAnimationFrame(frameId); frameId = 0; frameTime = 0; stopMeters(true);
        } else {
            drawProgress(); updateButtons();
            if (!audio.paused) { configureAudioSession(); resumeMeters(); startAnimation(); }
        }
    });
    reducedMotion.addEventListener('change',syncReels);
    window.addEventListener('pageshow', () => { drawProgress(); if (!audio.paused) { resumeMeters(); startAnimation(); } });
    window.addEventListener('focus', () => { if (!audio.paused) { resumeMeters(); startAnimation(); } });
    if (config.pwa?.enabled && config.pwa.worker && 'serviceWorker' in navigator && window.isSecureContext) {
        navigator.serviceWorker.register(config.pwa.worker, {scope:'/', updateViaCache:'none'}).catch(() => {});
    }

    let driveSelection = new Set(), driveKnownFiles = new Map(), driveFolders = new Set(), drivePath = [], driveBrowseGeneration = 0;
    const isDriveFolder = file => file.mimeType === 'application/vnd.google-apps.folder';
    const isDriveAudio = file => /\.(mp3|flac|wav|m4a|ogg|opus|aac|webm)$/i.test(file.name || '') && Number(file.size) > 0 && file.capabilities?.canDownload !== false;
    const driveSize = value => {
        const size = Number(value);
        if (size < 1024) return 'Меньше 1 КБ';
        const unit = size >= 1073741824 ? [1073741824,'ГБ'] : size >= 1048576 ? [1048576,'МБ'] : [1024,'КБ'];
        return `${(size / unit[0]).toLocaleString('ru-RU',{maximumFractionDigits:1})} ${unit[1]}`;
    };
    function updateDriveSelection() {
        const summary = dialog.querySelector('[data-drive-selected]'); if (!summary) return;
        summary.textContent = `Выбрано: ${driveSelection.size}`;
        const bulk = dialog.querySelector('[data-action="drive-select-all"]');
        bulk.disabled = driveKnownFiles.size === 0;
        bulk.textContent = driveKnownFiles.size && [...driveKnownFiles.keys()].every(id => driveSelection.has(id)) ? 'Снять выбор в папке' : driveKnownFiles.size > 100 ? 'Выбрать до 100' : 'Выбрать показанные';
        dialog.querySelector('[data-action="drive-clear"]').hidden = !driveSelection.size;
        const hasLink = Boolean(dialog.querySelector('[name="link"]').value.trim());
        const submit = $('[data-dialog-submit]');
        submit.disabled = dialogSubmitting || (!driveSelection.size && !hasLink);
        submit.textContent = hasLink ? 'Добавить музыку' : driveSelection.size ? `Добавить (${driveSelection.size})` : 'Выберите песни';
    }
    function renderDrivePath() {
        const nav = dialog.querySelector('[data-drive-path]'); nav.replaceChildren();
        const root = element('button','rp-drive-crumb','Мой Drive'); root.type = 'button'; root.dataset.action = 'drive-root'; root.disabled = drivePath.length === 1; nav.append(root);
        const crumbs = drivePath.length ? drivePath.slice(1) : [{id:'',name:'Вся музыка'}];
        crumbs.forEach((folder,index) => {
            nav.append(element('span','rp-drive-path-separator','›'));
            const crumb = element('button','rp-drive-crumb',folder.name); crumb.type = 'button'; crumb.title = folder.name; crumb.dataset.action = 'drive-parent'; crumb.dataset.depth = index+1; crumb.disabled = index === crumbs.length-1; nav.append(crumb);
        });
        const all = dialog.querySelector('[data-action="drive-all"]'); all.hidden = !drivePath.length;
        dialog.querySelector('[data-action="drive-add-folder"]').hidden = drivePath.length <= 1;
    }
    async function navigateDrive() {
        driveBrowseGeneration++; driveKnownFiles = new Map(); driveFolders = new Set();
        const list = dialog.querySelector('[data-drive-files]'); list.replaceChildren(element('p','rp-drive-loading','Загружаем папки и музыку…'));
        dialog.querySelector('[data-drive-count]').textContent = 'Загрузка…';
        renderDrivePath(); updateDriveSelection();
        await driveFiles();
    }
    function driveSetupHelp(callback) {
        return `<div class="rp-drive-description">
            <p>Адрес возврата после входа в Google. Добавьте его без изменений в <strong>Authorized redirect URIs</strong>:</p>
            <code>${esc(callback)}</code>
            <details class="rp-drive-help"><summary>Как подключить свой Google Drive</summary>
                <ol>
                    <li>Откройте <a href="https://console.cloud.google.com/" target="_blank" rel="noopener noreferrer">Google Cloud Console ↗</a> и создайте проект <strong>Tape Room</strong>.</li>
                    <li>В выбранном проекте откройте <a href="https://console.cloud.google.com/apis/library/drive.googleapis.com" target="_blank" rel="noopener noreferrer">Google Drive API ↗</a> и нажмите <strong>Enable / Включить</strong>.</li>
                    <li>В <strong>Google Auth Platform</strong> укажите название приложения и свою почту. Выберите <strong>External</strong>; в <strong>Audience → Test users</strong> добавьте почты Google-аккаунтов, которые будут пользоваться плеером, включая администраторов. В <strong>Data Access</strong> добавьте разрешение <strong>drive.readonly</strong> для чтения файлов.</li>
                    <li>В <strong>Clients → Create client</strong> выберите <strong>Web application</strong>. В <strong>Authorized redirect URIs</strong> вставьте адрес, показанный выше.</li>
                    <li>Вставьте полученные <strong>Client ID</strong> и <strong>Client Secret</strong> в поля этой формы и нажмите <strong>Сохранить</strong>. Это общая настройка сайта. В плеере каждый пользователь нажимает <strong>Войти в Google Drive</strong> и выбирает свой аккаунт.</li>
                    <li>Выберите аккаунт и разрешите чтение Drive. Затем отметьте песни в списке или вставьте ссылку на папку и нажмите <strong>Добавить</strong>.</li>
                </ol>
                <p>В режиме Testing Google выдаёт доступ на 7 дней, затем нужно подключиться снова. Для постоянного использования настройте публикацию приложения в Google Auth Platform.</p>
                <a href="https://developers.google.com/identity/protocols/oauth2/web-server#prerequisites" target="_blank" rel="noopener noreferrer">Официальная инструкция Google ↗</a>
            </details>
        </div>`;
    }
    async function driveSettings() {
        const status = await api('drive/status'); config.drive = status.drive;
        if (!status.drive.canManage) { toast('Настройки доступны только создателю сайта.',true); return; }
        openDialog('Настройки плеера', '<div class="rp-settings-section"><h3>Google Drive</h3><p>Подключение для всего сайта. Каждый администратор входит в собственный Google-аккаунт.</p></div>' + field('client_id','OAuth Client ID',status.drive.clientId,500) + '<label class="rp-field">OAuth Client Secret<input type="password" name="client_secret" autocomplete="off" placeholder="' + (status.drive.configured ? 'Оставьте пустым, чтобы сохранить текущий' : 'Введите секрет клиента') + '"></label>' + driveSetupHelp(status.drive.callback), async form => {
            const result = await action('drive.settings',{client_id:form.get('client_id'),client_secret:form.get('client_secret')}); config.drive = result.drive;
            toast('Настройки сайта сохранены. Теперь можно войти в Google Drive.');
        }, 'Эти настройки доступны только создателю сайта и сохраняются один раз для всех пользователей.');
    }
    async function openDrive() {
        const status = await api('drive/status'); config.drive = status.drive;
        if (!status.drive.connected) {
            openDialog('Музыка из Google Drive', '<button type="button" class="rp-button rp-google-button" data-action="drive-connect"' + (!status.drive.configured ? ' disabled' : '') + '><span class="rp-google-mark" aria-hidden="true">G</span>Войти в Google Drive</button>' + (!status.drive.configured ? '<p>' + (status.drive.canManage ? 'Откройте шестерёнку «Настройки плеера» и настройте подключение Google для сайта.' : 'Создатель сайта ещё не настроил подключение Google Drive.') + '</p>' : ''), async () => {}, 'Ваша музыка доступна только вашему аккаунту в плеере. Выберите свой Google-аккаунт и разрешите чтение Drive.');
            $('[data-dialog-submit]').hidden = true;
            return;
        }
        driveSelection = new Set(); drivePath = [{id:'root',name:'Мой Drive'}];
        openDialog('Добавить музыку из Drive', '<div class="rp-drive-location"><nav class="rp-drive-path" data-drive-path aria-label="Папки Google Drive"></nav><button type="button" class="rp-drive-settings-link" data-action="drive-all">Вся музыка</button></div><div class="rp-drive-heading"><span>Папки и музыка</span><small data-drive-count>Загрузка…</small></div><button type="button" class="rp-button rp-drive-add-folder" data-action="drive-add-folder" hidden>Добавить эту папку целиком</button><div class="rp-drive-selection"><span data-drive-selected>Выбрано: 0</span><div class="rp-drive-selection-buttons"><button type="button" class="rp-drive-settings-link" data-action="drive-clear" hidden>Сбросить</button><button type="button" class="rp-button" data-action="drive-select-all" disabled>Выбрать показанные</button></div></div><div class="rp-drive-files" data-drive-files aria-live="polite">Загружаем папки и музыку…</div><label class="rp-field rp-drive-link-field">Или вставьте ссылку на песню / папку<input name="link" type="url" placeholder="https://drive.google.com/…" inputmode="url" autocomplete="off"><small>Ссылка на папку добавит все поддерживаемые песни из самой папки.</small></label><div class="rp-drive-account-actions"><button type="button" class="rp-drive-settings-link" data-action="drive-disconnect">Отключить аккаунт</button></div>', async form => {
            if (!driveSelection.size && !form.get('link').trim()) throw new Error('Выберите хотя бы один трек или вставьте ссылку.');
            const result = await action('drive.import',{ids:[...driveSelection],link:form.get('link'),playlist_id:selected > 0 ? selected : 0});
            if (selected === -1) { selected = 0; render(); save(); }
            toast(`Добавлено треков из Google Drive: ${result.imported}`);
        }, 'Откройте папку с музыкой, отметьте песни и нажмите «Добавить». Музыка останется на Google Drive.', 'Выберите песни');
        dialog.classList.add('rp-dialog--drive');
        dialog.querySelector('[name="link"]').addEventListener('input', updateDriveSelection);
        await navigateDrive();
    }
    async function driveFiles(page = '') {
        const list = $('[data-drive-files]'); if (!list) return;
        const folder = drivePath.at(-1)?.id || '', browseGeneration = driveBrowseGeneration;
        const more = list.querySelector('[data-action="drive-more"]'); if (more) { more.disabled = true; more.textContent = 'Ищем музыку…'; }
        let result, files, token = page, attempts = 0;
        try {
            do {
                const params = new URLSearchParams(); if (token) params.set('page',token); if (folder) params.set('folder',folder);
                result = await api('drive/files' + (params.size ? '?' + params : ''));
                if ($('[data-drive-files]') !== list || !dialog.open || browseGeneration !== driveBrowseGeneration) return;
                files = (result.files || []).filter(file => isDriveAudio(file) || (folder && isDriveFolder(file)));
                const next = result.nextPageToken || '';
                if (!next || next === token || files.length) break;
                token = next;
            } while (++attempts < 3);
        } catch (error) {
            if ($('[data-drive-files]') !== list || browseGeneration !== driveBrowseGeneration) return;
            if (more) { more.disabled = false; more.textContent = 'Повторить загрузку'; }
            else { list.replaceChildren(element('p','rp-drive-empty',error.message)); const retry = element('button','rp-button','Повторить загрузку'); retry.type = 'button'; retry.dataset.action = 'drive-more'; list.append(retry); }
            throw error;
        }
        if (!page) list.replaceChildren(); else more?.remove();
        list.querySelector('.rp-drive-empty')?.remove();
        for (const file of files) {
            if (isDriveFolder(file)) {
                if (driveFolders.has(file.id)) continue;
                driveFolders.add(file.id);
                const entry = element('button','rp-drive-folder'); entry.type = 'button'; entry.dataset.action = 'drive-folder'; entry.dataset.folderId = file.id; entry.dataset.folderName = file.name; entry.setAttribute('aria-label',`Открыть папку ${file.name}`);
                const mark = element('span','rp-drive-folder-icon'); mark.innerHTML = icon('folder');
                entry.append(mark,element('span','rp-drive-folder-name',file.name),element('span','rp-drive-folder-arrow','›'));
                list.insertBefore(entry,list.querySelector('.rp-drive-file')); continue;
            }
            if (driveKnownFiles.has(file.id)) continue;
            driveKnownFiles.set(file.id,file);
            const label = element('label','rp-drive-file'), box = element('input',''); box.type = 'checkbox'; box.value = file.id; box.checked = driveSelection.has(file.id); box.dataset.driveFile = '';
            box.setAttribute('aria-label',`Выбрать ${file.name}`);
            box.addEventListener('change', () => {
                if (box.checked && driveSelection.size >= 100) { box.checked = false; toast('За один раз можно выбрать до 100 песен. Папку целиком можно добавить по ссылке.',true); return; }
                box.checked ? driveSelection.add(file.id) : driveSelection.delete(file.id); updateDriveSelection();
            });
            const mark = element('span','rp-drive-file-icon'); mark.innerHTML = icon('music');
            const info = element('span','rp-drive-file-info'); info.append(element('span','rp-drive-file-name',file.name),element('small','rp-drive-file-format',file.name.split('.').pop().toUpperCase()));
            label.append(box,mark,info,element('small','rp-drive-file-size',driveSize(file.size))); list.append(label);
        }
        if (!driveKnownFiles.size && !driveFolders.size) list.prepend(element('p','rp-drive-empty',result.nextPageToken ? 'В этой порции файлов нет папок и музыки. Продолжите поиск.' : 'Здесь нет папок и поддерживаемой музыки. Вернитесь назад или вставьте ссылку ниже.'));
        dialog.querySelector('[data-drive-count]').textContent = `${folder ? `Папок: ${driveFolders.size} · ` : ''}песен: ${driveKnownFiles.size}${result.nextPageToken ? ' · есть ещё' : ''}`;
        if (result.nextPageToken) { const next = element('button','rp-button rp-drive-more','Показать ещё'); next.type = 'button'; next.dataset.action = 'drive-more'; next.dataset.page = result.nextPageToken; list.append(next); }
        updateDriveSelection();
    }
    dialog.addEventListener('close', () => { $('[data-dialog-submit]').hidden = false; if (coverPreviewUrl) { URL.revokeObjectURL(coverPreviewUrl); coverPreviewUrl = undefined; } });
    audio.volume = Number.isFinite(Number(saved.volume)) ? Math.max(0,Math.min(1,Number(saved.volume))) : .8;
    audio.muted = Boolean(saved.muted); syncVolume();
    $('[data-upload-limit]').textContent = `${(config.maxUpload / 1048576).toFixed(0)} МБ`;
    applyTheme();
    render();
    if (track(saved.current)) { queue = Array.isArray(saved.queue) ? saved.queue.filter(id => Boolean(track(id))) : []; if (!queue.length) setQueue(); queueName = saved.queueName || 'Вся музыка'; load(saved.current,Number(saved.time) || 0); }
    else if (data.tracks.length) { setQueue(); load(selectedTracks()[0]?.id || data.tracks[0].id); }
    drawProgress(); drawMeters(false);
    const params = new URLSearchParams(location.search);
    if (params.has('drive')) { toast(params.get('drive') === 'connected' ? 'Google Drive подключён. Можно добавить музыку.' : 'Google Drive не подключён. Проверьте OAuth-настройки и попробуйте снова.',params.get('drive') !== 'connected'); window.history.replaceState({},'',location.pathname); }
})();
