(() => {
    'use strict';
    const app = document.querySelector('[data-player]');
    if (!app) return;
    const config = JSON.parse(document.querySelector('[data-player-config]').textContent);
    const $ = selector => app.querySelector(selector);
    const audio = $('[data-audio]');
    const dialog = $('[data-dialog]');
    let data = config.state, saved = {};
    try { saved = JSON.parse(localStorage.getItem(config.storageKey) || '{}'); } catch (_) {}
    let selected = Number(saved.playlist) || 0;
    let current = 0, queue = [], queueName = '', queuePlaylist = Number(saved.queuePlaylist) || 0, repeat = ['off', 'all', 'one'].includes(saved.repeat) ? saved.repeat : 'off';
    let shuffle = Boolean(saved.shuffle), bag = [], history = [], seekActive = false, restoreTime = 0;
    let toastTimer, frameId = 0, frameTime = 0, angles = [0, 0], graph, uploading = false, dialogHandler;
    let loading = false, generation = 0, lastSave = 0, mutations = 0;
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
    const selectedTracks = () => selected ? (playlist(selected)?.tracks || []).map(track).filter(Boolean) : data.tracks;
    const element = (tag, className, text) => { const node = document.createElement(tag); node.className = className; if (text !== undefined) node.textContent = text; return node; };
    const setIcon = (node, name) => node.querySelector('use').setAttribute('href', `#rp-i-${name}`);
    const rangeFill = (node, fraction) => node.style.setProperty('--fill', `${Math.max(0, Math.min(1, fraction)) * 100}%`);

    function toast(message, error = false) {
        const node = $('[data-toast]'); node.textContent = message; node.classList.toggle('is-error', error); node.hidden = false;
        clearTimeout(toastTimer); toastTimer = setTimeout(() => { node.hidden = true; }, error ? 7000 : 3500);
    }
    function save() {
        try { localStorage.setItem(config.storageKey, JSON.stringify({playlist:selected, current, queue, queueName, queuePlaylist, time:audio.currentTime || 0, volume:audio.volume, muted:audio.muted, shuffle, repeat})); } catch (_) {}
    }
    function render() {
        if (selected && !playlist(selected)) selected = 0;
        queue = queue.filter(id => Boolean(track(id)));
        if (queuePlaylist) {
            const playingPlaylist = playlist(queuePlaylist);
            if (playingPlaylist) { queue = [...playingPlaylist.tracks]; queueName = playingPlaylist.name; }
            else { queuePlaylist = 0; queueName = 'Вся музыка'; }
        }
        bag = bag.filter(id => queue.includes(id)); history = history.filter(id => Boolean(track(id)));
        if (current && !activeTrack()) { audio.pause(); audio.removeAttribute('src'); audio.load(); current = 0; updateNow(); }
        const nav = $('[data-playlists]'); nav.replaceChildren();
        for (const item of [{id:0, name:'Вся музыка', tracks:data.tracks}, ...data.playlists]) {
            const node = element('button', `rp-playlist${item.id === selected ? ' is-selected' : ''}`);
            node.type = 'button'; node.dataset.action = 'select-playlist'; node.dataset.id = item.id;
            node.setAttribute('aria-current', item.id === selected ? 'page' : 'false');
            node.innerHTML = icon(item.id ? 'list' : 'music');
            node.append(element('span', 'rp-playlist-name', item.name), element('span', 'rp-playlist-count', item.tracks.length));
            nav.append(node);
        }
        const list = selectedTracks(), query = $('[data-search]').value.trim().toLocaleLowerCase();
        const visible = list.filter(t => `${t.title} ${t.artist} ${t.filename}`.toLocaleLowerCase().includes(query));
        $('[data-list-title]').textContent = playlist(selected)?.name || 'Вся музыка';
        $('[data-list-summary]').textContent = `${list.length} ${list.length % 10 === 1 && list.length % 100 !== 11 ? 'трек' : list.length % 10 >= 2 && list.length % 10 <= 4 && !(list.length % 100 >= 12 && list.length % 100 <= 14) ? 'трека' : 'треков'}${list.some(t => t.duration) ? ' · ' + formatTime(list.reduce((total, t) => total + t.duration, 0)) : ''}`;
        $('[data-playlist-actions]').hidden = !selected;
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
            actions.innerHTML = (selected ? button('move-up', 'Переместить выше', 'up', t.id, 'rp-reorder') + button('move-down', 'Переместить ниже', 'down', t.id, 'rp-reorder') : '') + button('add-to-playlist', 'Добавить в плейлист', 'plus', t.id) + button('edit-track', 'Изменить трек', 'edit', t.id) + button(selected ? 'remove-track' : 'delete-track', selected ? 'Убрать из плейлиста' : 'Удалить из библиотеки', 'trash', t.id);
            if (selected) {
                actions.querySelector('[data-action="move-up"]').disabled = index === 0;
                actions.querySelector('[data-action="move-down"]').disabled = index === list.length - 1;
            }
            row.append(play, cover, info, element('span', 'rp-track-duration', t.duration ? formatTime(t.duration) : '—'), actions); rows.append(row);
        }
        $('[data-empty]').hidden = visible.length > 0;
        $('[data-empty-title]').textContent = query ? 'Ничего не найдено' : selected ? 'Эта лента пока пустая' : 'Здесь начинается ваша коллекция';
        $('[data-empty-description]').textContent = query ? 'Попробуйте другое название или имя исполнителя.' : selected ? 'Загрузите музыку сюда или добавьте треки из общей библиотеки.' : 'Перетащите аудиофайлы сюда или добавьте их кнопкой выше.';
        $('[data-action="play"]').disabled = !current && list.length === 0;
        $('[data-action="prev"]').disabled = !current;
        $('[data-action="next"]').disabled = !current;
        $('[data-action="edit-current"]').disabled = !current;
        updateNow(); updateButtons();
    }
    function updateNow() {
        const t = activeTrack();
        $('[data-title]').textContent = t?.title || 'Ваша первая лента';
        $('[data-artist]').textContent = t?.artist || (t ? 'НЕИЗВЕСТНЫЙ ИСПОЛНИТЕЛЬ' : 'ДОБАВЬТЕ ЛЮБИМУЮ МУЗЫКУ');
        const cover = t?.cover || config.defaultCover;
        if ($('[data-cover]').getAttribute('src') !== cover) $('[data-cover]').src = cover;
        $('[data-source]').textContent = current ? (queueName || 'Вся музыка') + (t?.source === 'drive' ? ' · GOOGLE DRIVE' : ' · STEREO') : 'ВАША МУЗЫКА. ВАШ РИТМ.';
        if (t && 'mediaSession' in navigator && 'MediaMetadata' in window) {
            navigator.mediaSession.metadata = new MediaMetadata({title:t.title, artist:t.artist, album:queueName || 'Tape Room', artwork:[{src:new URL(cover, location.href).href}]});
        }
    }
    function updateButtons() {
        const playing = !audio.paused && !audio.ended;
        document.body.classList.toggle('is-playing', playing);
        const play = $('[data-action="play"]'); setIcon(play, playing ? 'pause' : 'play');
        play.setAttribute('aria-label', playing ? 'Пауза' : 'Воспроизвести'); play.title = playing ? 'Пауза' : 'Воспроизвести';
        $('[data-play-status]').textContent = loading ? 'ЗАГРУЖАЕМ ЛЕНТУ' : playing ? 'ЛЕНТА В ДВИЖЕНИИ' : current ? 'ПАУЗА' : 'ГОТОВ К ПРОСЛУШИВАНИЮ';
        const sh = $('[data-action="shuffle"]'); sh.classList.toggle('is-active', shuffle); sh.setAttribute('aria-pressed', String(shuffle));
        sh.setAttribute('aria-label', `Случайный порядок: ${shuffle ? 'включён' : 'выключен'}`); sh.title = sh.getAttribute('aria-label');
        const re = $('[data-action="repeat"]'); re.classList.toggle('is-active', repeat !== 'off'); re.setAttribute('aria-label', `Повтор: ${repeat === 'off' ? 'выключен' : repeat === 'one' ? 'одного трека' : 'плейлиста'}`); re.title = re.getAttribute('aria-label'); $('[data-repeat-one]').hidden = repeat !== 'one';
        const mute = $('[data-action="mute"]'); setIcon(mute, audio.muted || audio.volume === 0 ? 'mute' : 'volume'); mute.setAttribute('aria-label', audio.muted ? 'Включить звук' : 'Выключить звук');
        for (const row of $('[data-tracks]').children) {
            row.classList.toggle('is-current', Number(row.dataset.trackId) === current);
            const control = row.querySelector('.rp-track-play'); setIcon(control, Number(row.dataset.trackId) === current && playing ? 'pause' : 'play');
            control.setAttribute('aria-label', `${Number(row.dataset.trackId) === current && playing ? 'Приостановить' : 'Воспроизвести'} ${track(row.dataset.trackId)?.title || ''}`);
        }
        if ('mediaSession' in navigator) navigator.mediaSession.playbackState = playing ? 'playing' : 'paused';
    }
    function drawProgress() {
        const duration = Number.isFinite(audio.duration) ? audio.duration : activeTrack()?.duration || 0;
        const progress = duration > 0 ? Math.max(0, Math.min(1, audio.currentTime / duration)) : 0;
        if (!seekActive) { $('[data-seek]').value = Math.round(progress * 1000); rangeFill($('[data-seek]'), progress); }
        $('[data-seek]').disabled = !current || !duration;
        $('[data-elapsed]').textContent = formatTime(audio.currentTime);
        $('[data-duration]').textContent = formatTime(duration);
        const inner = 78, outer = 174;
        const radii = [Math.sqrt(inner ** 2 + (outer ** 2 - inner ** 2) * (1 - progress)), Math.sqrt(inner ** 2 + (outer ** 2 - inner ** 2) * progress)];
        ['left','right'].forEach((side, i) => {
            $(`[data-pack="${side}"]`).setAttribute('r', radii[i].toFixed(2));
            $(`[data-pack-clip="${side}"]`).setAttribute('r', radii[i].toFixed(2));
        });
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
        $('[data-tape-left]').setAttribute('d', run(230, radii[0], 1));
        $('[data-tape-right]').setAttribute('d', run(970, radii[1], -1));
        if ('mediaSession' in navigator && navigator.mediaSession.setPositionState && duration > 0) {
            try { navigator.mediaSession.setPositionState({duration, playbackRate:audio.playbackRate, position:Math.min(duration,audio.currentTime)}); } catch (_) {}
        }
        return radii;
    }
    function initGraph() {
        if (graph || !(window.AudioContext || window.webkitAudioContext)) return;
        try {
            const context = new (window.AudioContext || window.webkitAudioContext)();
            const source = context.createMediaElementSource(audio), splitter = context.createChannelSplitter(2);
            // Mono tracks must reach both meters, while genuine stereo stays separate.
            source.channelCount = 2; source.channelCountMode = 'explicit';
            source.connect(context.destination); source.connect(splitter);
            const analysers = [context.createAnalyser(), context.createAnalyser()];
            analysers.forEach((a,i) => { a.fftSize = 256; a.smoothingTimeConstant = .78; splitter.connect(a, i); });
            graph = {context, analysers, samples:new Float32Array(256)};
        } catch (_) { graph = null; }
    }
    function drawMeters(playing) {
        ['left','right'].forEach((side,i) => {
            let level = 0;
            if (playing && graph && !audio.muted && audio.volume > 0) {
                graph.analysers[i].getFloatTimeDomainData(graph.samples);
                const rms = Math.sqrt(graph.samples.reduce((sum,value) => sum + value * value, 0) / graph.samples.length);
                level = Math.max(0, Math.min(15, (20 * Math.log10(Math.max(rms, .00001)) + 45) / 3));
            }
            $(`[data-meter="${side}"]`).querySelectorAll('i').forEach((bar, n) => bar.classList.toggle('is-lit', n < level));
        });
    }
    function animate(time) {
        frameId = 0;
        const playing = !audio.paused && !audio.ended;
        const elapsed = frameTime ? Math.min(.1, (time - frameTime) / 1000) : 0; frameTime = time;
        const radii = drawProgress(); drawMeters(playing && !loading);
        if (playing && !loading && !reducedMotion.matches) {
            ['left','right'].forEach((side,i) => { angles[i] = (angles[i] - elapsed * 95 * 126 / radii[i] * audio.playbackRate) % 360; $(`[data-rotor="${side}"]`).style.transform = `rotate(${angles[i]}deg)`; });
        }
        if (playing) frameId = requestAnimationFrame(animate);
    }
    function startAnimation() { frameTime = 0; if (!frameId) frameId = requestAnimationFrame(animate); }
    async function play() {
        if (!current) {
            const first = selectedTracks()[0]; if (!first) return;
            setQueue(); load(first.id);
        }
        initGraph(); if (graph?.context.state === 'suspended') await graph.context.resume();
        const version = generation;
        try { await audio.play(); }
        catch (error) { if (version === generation && error.name !== 'AbortError') { loading = false; updateButtons(); toast('Не удалось воспроизвести трек. Проверьте формат, соединение и доступ к файлу.', true); } }
    }
    function setQueue() { queue = selectedTracks().map(t => t.id); queuePlaylist = selected; queueName = playlist(selected)?.name || 'Вся музыка'; bag = []; history = []; }
    function load(id, time = 0) {
        const t = track(id); if (!t) return;
        generation++; audio.pause(); current = t.id; restoreTime = time; loading = false;
        audio.src = t.url; audio.load(); render(); drawProgress(); save();
    }
    function next(automatic = false) {
        if (!current || !queue.length) return;
        if (automatic && repeat === 'one') { audio.currentTime = 0; play(); return; }
        let id;
        if (shuffle) {
            if (!bag.length) {
                if (automatic && repeat === 'off' && history.length >= queue.length - 1) return;
                bag = queue.filter(t => t !== current).sort(() => Math.random() - .5);
                if (!bag.length) { if (automatic && repeat === 'off') return; bag = [current]; }
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
        if (id) { load(id); play(); }
    }
    function applyState(result) { data = {tracks:result.tracks, playlists:result.playlists}; render(); save(); }
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
        mutations++;
        try { const result = await api('action', form); if (result.tracks) applyState(result); return result; }
        finally { mutations--; }
    }
    function openDialog(title, fields, handler, description = '', submit = 'Сохранить', danger = false) {
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
        openDialog('О композиции', field('title','Название',t.title) + field('artist','Исполнитель',t.artist) + '<label class="rp-field">Обложка · JPG, PNG, WebP<input type="file" name="cover" accept="image/jpeg,image/png,image/webp"></label>', form => action('track.update',{id,title:form.get('title'),artist:form.get('artist')},form.get('cover')));
    }
    async function uploadFiles(files) {
        if (uploading) { toast('Дождитесь завершения текущей загрузки.'); return; }
        if (!files.length) return;
        uploading = true; const destination = selected; const progress = $('[data-upload-progress]'); progress.hidden = false;
        let completed = 0, failed = 0;
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            $('[data-upload-message]').textContent = `${i+1} / ${files.length} · ${file.name}`;
            try {
                if (file.size > config.maxUpload) throw new Error(`${file.name}: файл превышает лимит ${(config.maxUpload/1048576).toFixed(1)} МБ.`);
                if (!/\.(mp3|flac|wav|m4a|ogg|opus|aac|webm)$/i.test(file.name)) throw new Error(`${file.name}: неподдерживаемый формат.`);
                const form = new FormData(); form.set('audio',file); form.set('playlist_id', destination); form.set('needCSRFToken',config.csrf);
                const result = await new Promise((resolve,reject) => {
                    const xhr = new XMLHttpRequest(); xhr.open('POST',`${config.api}/upload`); xhr.timeout = 300000;
                    xhr.setRequestHeader('X-CSRF-Token',config.csrf); xhr.setRequestHeader('X-Requested-With','XMLHttpRequest'); xhr.setRequestHeader('Accept','application/json');
                    xhr.upload.onprogress = event => { if (event.lengthComputable) progress.querySelector('progress').value = event.loaded / event.total * 100; };
                    xhr.onload = () => { try { const r = JSON.parse(xhr.responseText); if (xhr.status >= 200 && xhr.status < 300 && r.status) resolve(r); else reject(new Error(r.message || 'Ошибка загрузки.')); } catch (_) { reject(new Error('Сессия завершена или сервер недоступен. Обновите страницу.')); } };
                    xhr.onerror = () => reject(new Error('Загрузка прервана. Проверьте соединение.')); xhr.ontimeout = () => reject(new Error('Истекло время загрузки. Попробуйте ещё раз.'));
                    xhr.send(form);
                });
                applyState(result); completed++;
                if (!current) { setQueue(); load(result.id); }
            } catch (error) { failed++; toast(error.message,true); }
        }
        uploading = false; progress.hidden = true; $('[data-audio-files]').value = '';
        if (!failed) toast(`Добавлено треков: ${completed}`);
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
                case 'shuffle': shuffle = !shuffle; bag = []; history = []; updateButtons(); save(); break;
                case 'repeat': repeat = {off:'all',all:'one',one:'off'}[repeat]; updateButtons(); save(); break;
                case 'mute': audio.muted = !audio.muted; updateButtons(); save(); break;
                case 'focus': document.body.classList.toggle('is-focus'); control.setAttribute('aria-pressed', String(document.body.classList.contains('is-focus'))); break;
                case 'select-playlist': selected = id; $('[data-search]').value = ''; render(); save(); break;
                case 'upload': $('[data-audio-files]').click(); break;
                case 'create-playlist': createPlaylist(); break;
                case 'close-dialog': if (!$('[data-dialog-submit]').disabled) dialog.close(); break;
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
                case 'drive-connect': {
                    if (!config.drive?.configured) { await driveSettings(true); break; }
                    control.disabled = true; const result = await action('drive.connect'); location.assign(result.authUrl); break;
                }
                case 'drive-disconnect': openDialog('Отключить Google Drive?', '', async () => { await action('drive.disconnect'); config.drive.connected = false; toast('Google Drive отключён.'); }, 'Ленты сохранятся. Чтобы слушать облачные треки, подключите аккаунт снова.', 'Отключить'); break;
                case 'drive-more': await driveFiles(control.dataset.page); break;
            }
        } catch (error) { toast(error.message,true); if (control.isConnected) control.disabled = false; }
    });
    $('[data-dialog-form]').addEventListener('submit', async event => {
        event.preventDefault(); const control = $('[data-dialog-submit]'); if (control.disabled) return;
        control.disabled = true; $('[data-dialog-error]').hidden = true;
        try { await dialogHandler(new FormData(event.currentTarget)); dialog.close(); }
        catch (error) { $('[data-dialog-error]').textContent = error.message; $('[data-dialog-error]').hidden = false; }
        finally { control.disabled = false; }
    });
    dialog.addEventListener('cancel', event => { if ($('[data-dialog-submit]').disabled) event.preventDefault(); });
    $('[data-search]').addEventListener('input', render);
    $('[data-volume]').addEventListener('input', event => { audio.volume = Number(event.target.value); audio.muted = false; rangeFill(event.target, audio.volume); updateButtons(); save(); });
    $('[data-seek]').addEventListener('input', event => { seekActive = true; const fraction = Number(event.target.value) / 1000; rangeFill(event.target,fraction); $('[data-elapsed]').textContent = formatTime(audio.duration * fraction); });
    $('[data-seek]').addEventListener('change', event => { if (Number.isFinite(audio.duration)) audio.currentTime = audio.duration * Number(event.target.value) / 1000; seekActive = false; drawProgress(); save(); });
    $('[data-audio-files]').addEventListener('change', event => uploadFiles([...event.target.files]));
    audio.addEventListener('loadedmetadata', () => {
        if (restoreTime) { audio.currentTime = Math.min(restoreTime, Math.max(0,audio.duration - .1)); restoreTime = 0; }
        drawProgress();
        const t = activeTrack();
        if (t && Number.isFinite(audio.duration) && Math.abs(t.duration - audio.duration) > 1) {
            t.duration = audio.duration;
            action('track.update',{id:t.id,duration:audio.duration}).catch(() => {});
        }
    });
    audio.addEventListener('play', () => { updateButtons(); startAnimation(); });
    audio.addEventListener('playing', () => { loading = false; updateButtons(); startAnimation(); });
    audio.addEventListener('waiting', () => { loading = true; updateButtons(); });
    audio.addEventListener('pause', () => { loading = false; updateButtons(); drawMeters(false); save(); });
    audio.addEventListener('timeupdate', () => { drawProgress(); if (Date.now() - lastSave > 3000) { save(); lastSave = Date.now(); } });
    audio.addEventListener('seeked', drawProgress);
    audio.addEventListener('ended', () => { updateButtons(); drawProgress(); drawMeters(false); next(true); });
    audio.addEventListener('error', () => { if (!current) return; loading = false; updateButtons(); toast(activeTrack()?.source === 'drive' ? 'Не удалось открыть Google Drive. Проверьте подключение аккаунта и доступ к файлу.' : 'Не удалось прочитать аудиофайл. Возможно, браузер не поддерживает его кодек.',true); });
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
        const handlers = {play, pause:() => audio.pause(), previoustrack:previous, nexttrack:() => next(), seekto:event => { if (Number.isFinite(audio.duration)) audio.currentTime = Math.max(0,Math.min(audio.duration,event.seekTime)); }, seekbackward:event => { audio.currentTime = Math.max(0,audio.currentTime-(event.seekOffset || 10)); }, seekforward:event => { if (Number.isFinite(audio.duration)) audio.currentTime = Math.min(audio.duration,audio.currentTime+(event.seekOffset || 10)); }};
        for (const [name,handler] of Object.entries(handlers)) { try { navigator.mediaSession.setActionHandler(name,handler); } catch (_) {} }
    }
    window.addEventListener('pagehide', save);

    let driveSelection = new Set();
    async function driveSettings(connectAfter = false) {
        const status = await api('drive/status'); config.drive = status.drive;
        openDialog('Разовая настройка Google', field('client_id','OAuth Client ID',status.drive.clientId,500) + '<label class="rp-field">OAuth Client Secret<input type="password" name="client_secret" autocomplete="off" placeholder="' + (status.drive.configured ? 'Оставьте пустым, чтобы сохранить текущий' : 'Введите секрет клиента') + '"></label><p class="rp-drive-description">В Google Cloud включите Drive API, создайте OAuth-клиент типа «Веб-приложение» и добавьте этот адрес в разрешённые URI перенаправления:<code>' + esc(status.drive.callback) + '</code><a href="https://developers.google.com/identity/protocols/oauth2/web-server#prerequisites" target="_blank" rel="noopener noreferrer">Инструкция Google ↗</a></p>', async form => {
            const result = await action('drive.settings',{client_id:form.get('client_id'),client_secret:form.get('client_secret')}); config.drive = result.drive;
            if (connectAfter) { const connection = await action('drive.connect'); location.assign(connection.authUrl); }
            else toast('Настройки сохранены. Теперь можно войти через Google.');
        }, 'Google просит зарегистрировать приложение один раз. Дальше вы будете входить обычной кнопкой через свой аккаунт.', connectAfter ? 'Сохранить и войти' : 'Сохранить');
    }
    async function openDrive() {
        const status = await api('drive/status'); config.drive = status.drive;
        if (!status.drive.connected) {
            openDialog('Музыка из Google Drive', '<button type="button" class="rp-button rp-google-button" data-action="drive-connect"><span class="rp-google-mark" aria-hidden="true">G</span>Войти через Google</button>' + (!status.drive.configured ? '<p>Для первого входа потребуется разовая настройка подключения этого сайта к Google.</p>' : '') + '<button type="button" class="rp-drive-settings-link" data-action="drive-settings">Настройки подключения</button>', async () => {}, 'Выберите свой Google-аккаунт и разрешите чтение Drive. Затем добавьте любимую музыку прямо из облака.');
            $('[data-dialog-submit]').hidden = true;
            return;
        }
        driveSelection = new Set();
        openDialog('Музыка из Google Drive', '<label class="rp-field">Ссылка на файл или папку<input name="link" placeholder="https://drive.google.com/…"></label><div class="rp-drive-files" data-drive-files>Загружаем список…</div><button type="button" class="rp-drive-settings-link" data-action="drive-settings">Настройки</button> · <button type="button" class="rp-drive-settings-link" data-action="drive-disconnect">Отключить аккаунт</button>', async form => {
            if (!driveSelection.size && !form.get('link').trim()) throw new Error('Выберите хотя бы один трек или вставьте ссылку.');
            const result = await action('drive.import',{ids:[...driveSelection],link:form.get('link'),playlist_id:selected});
            toast(`Добавлено треков из Google Drive: ${result.imported}`);
        }, 'Выберите файлы или вставьте ссылку на аудиофайл / папку. Треки появятся в открытой ленте.', 'Добавить');
        await driveFiles();
    }
    async function driveFiles(page = '') {
        const result = await api('drive/files' + (page ? '?page=' + encodeURIComponent(page) : ''));
        const list = $('[data-drive-files]'); if (!list) return;
        if (!page) list.replaceChildren(); else list.querySelector('[data-action="drive-more"]')?.remove();
        for (const file of result.files) {
            const label = element('label','rp-drive-file'), box = element('input',''); box.type = 'checkbox'; box.value = file.id; box.checked = driveSelection.has(file.id);
            box.addEventListener('change', () => box.checked ? driveSelection.add(file.id) : driveSelection.delete(file.id));
            label.append(box,element('span','',file.name),element('small','',file.size ? `${(Number(file.size)/1048576).toFixed(1)} МБ` : '')); list.append(label);
        }
        if (!result.files.length && !page) list.textContent = 'Аудиофайлы не найдены. Можно вставить ссылку на файл или папку выше.';
        if (result.nextPageToken) { const more = element('button','rp-button','Показать ещё'); more.type = 'button'; more.dataset.action = 'drive-more'; more.dataset.page = result.nextPageToken; list.append(more); }
    }
    dialog.addEventListener('close', () => { $('[data-dialog-submit]').hidden = false; });
    audio.volume = Number.isFinite(Number(saved.volume)) ? Math.max(0,Math.min(1,Number(saved.volume))) : .8;
    audio.muted = Boolean(saved.muted); $('[data-volume]').value = audio.volume; rangeFill($('[data-volume]'),audio.volume);
    $('[data-upload-limit]').textContent = `${(config.maxUpload / 1048576).toFixed(0)} МБ`;
    render();
    if (track(saved.current)) { queue = Array.isArray(saved.queue) ? saved.queue.filter(id => Boolean(track(id))) : []; if (!queue.length) setQueue(); queueName = saved.queueName || 'Вся музыка'; load(saved.current,Number(saved.time) || 0); }
    else if (data.tracks.length) { setQueue(); load(selectedTracks()[0]?.id || data.tracks[0].id); }
    drawProgress(); drawMeters(false);
    const params = new URLSearchParams(location.search);
    if (params.has('drive')) { toast(params.get('drive') === 'connected' ? 'Google Drive подключён. Можно добавить музыку.' : 'Google Drive не подключён. Проверьте OAuth-настройки и попробуйте снова.',params.get('drive') !== 'connected'); window.history.replaceState({},'',location.pathname); }
})();
