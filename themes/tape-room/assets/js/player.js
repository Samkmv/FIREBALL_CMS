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
        reels:['left','right'].map(side => ({rotor:$(`[data-rotor="${side}"]`), pack:$(`[data-pack="${side}"]`), clip:$(`[data-pack-clip="${side}"]`), tapeMask:$(`[data-tape-mask="${side}"]`), tapeFade:$(`[data-tape-fade="${side}"]`), bars:[...$(`[data-meter="${side}"]`).querySelectorAll('i')]})),
        tape:[$('[data-tape-left]'),$('[data-tape-right]')]
    };
    let theme = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
    const defaultCover = () => config.defaultCovers?.[theme] || config.defaultCover;
    const defaultArtist = () => config.defaultArtists?.[theme] || '';
    const imageSources = (source,kind = 'cover',compact = false) => window.TapeRoomImages.sources(source,config,theme,kind,compact);
    let data = config.state, saved = {};
    try { saved = JSON.parse(localStorage.getItem(config.storageKey) || '{}'); } catch (_) {}
    if (!saved || typeof saved !== 'object' || Array.isArray(saved)) saved = {};
    let audibleVolume = Number(saved.audibleVolume) > 0 ? Math.min(1, Number(saved.audibleVolume)) : Number(saved.volume) > 0 ? Math.min(1, Number(saved.volume)) : .8;
    let selected = Number(saved.playlist) || 0;
    let current = 0, queue = [], queueName = '', queuePlaylist = Number(saved.queuePlaylist) || 0, repeat = ['off', 'all', 'one'].includes(saved.repeat) ? saved.repeat : 'off';
    let librarySort = ['new','old'].includes(saved.librarySort) ? saved.librarySort : 'new';
    let playlistSort = ['playlist','new','old'].includes(saved.playlistSort) ? saved.playlistSort : 'playlist';
    let queueSort = ['playlist','new','old'].includes(saved.queueSort) ? saved.queueSort : (queuePlaylist > 0 ? 'playlist' : librarySort);
    const collections = window.TapeRoomCollections;
    let libraryView = saved.libraryView && ['overview','albums','artists','new','popular','genres','genre','mine','songs','favorites','playlist','album','artist'].includes(saved.libraryView.type) ? saved.libraryView : {type:'overview'};
    let queueSource = collections.descriptor(saved.queueSource, {type:queuePlaylist === -1 ? 'favorites' : queuePlaylist > 0 ? 'playlist' : 'songs', ...(queuePlaylist > 0 ? {id:queuePlaylist} : {})});
    let trackPool = collections.tracks(data), trackMap = new Map(trackPool.map(t => [t.id,t]));
    let shuffle = Boolean(saved.shuffle), bag = [], history = [], seekActive = false, restoreTime = 0;
    let toastTimer, frameId = 0, frameTime = 0, frameInProgress = false, angles = [0, 0], graph, uploading = false, dialogHandler, dialogSubmitting = false;
    let dialogScroll = null;
    let listening = null;
    let loading = false, bufferVisible = false, bufferTimer, coverPreviewUrl, generation = 0, lastSave = 0, mutations = 0;
    let progressFrame = -Infinity, meterFrame = -Infinity, tapeFrame = -Infinity, packRadii = [174,78], positionKey = '';
    const transport = new window.TapeRoomTransport();
    const meterLevels = [0,0];
    const meterEnvelope = new window.TapeRoomMeterEnvelope();
    const meterSignal = {generation:-1, values:[0,0], rms:[0,0], sampled:[-Infinity,-Infinity], drawn:0};
    const meterReport = {samples:[], time:-Infinity, status:null};
    let mutationQueue = Promise.resolve();
    let dialogRequest = 0;
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
    const previewImage = (source,alt,kind = 'cover') => {
        const value=imageSources(source,kind,true);
        return `<img data-cover-preview data-library-image="${kind}" data-compact-image ${source ? '' : `data-default-${kind}`} ${value.srcset ? `srcset="${esc(value.srcset)}"` : ''} ${value.src ? `src="${esc(value.src)}"` : 'hidden'} alt="${esc(alt)}" width="72" height="72" decoding="async">`;
    };
    const button = (action, title, name, id, extra = '') => `<button type="button" class="rp-icon-button ${extra}" data-action="${action}" data-id="${id}" title="${title}" aria-label="${title}">${icon(name)}</button>`;
    const track = id => trackMap.get(Number(id));
    const album = id => collections.album(data,id);
    const playlist = id => data.playlists.find(p => p.id === Number(id));
    const activeTrack = () => track(current);
    const chapters = window.TapeRoomChapters;
    const currentChapters = () => activeTrack()?.chapters || [];
    const playbackPosition = () => audio.readyState === 0 && restoreTime ? restoreTime : audio.currentTime || 0;
    let chapterKey = '', chapterIndex = -2, chapterDuration = -1, chapterRows = [], chapterMarkers = [];
    let chapterJob = null;
    const qualityCache = new Map();
    let qualityJob = null;
    const descriptorForPlaylist = id => ({type:id === -1 ? 'favorites' : id > 0 ? 'playlist' : 'songs', ...(id > 0 ? {id} : {})});
    const collectionName = id => collections.name(data,descriptorForPlaylist(id));
    const collectionTracks = id => collections.collection(data,descriptorForPlaylist(id),'playlist');
    const sortedTracks = (tracks, mode) => mode === 'playlist' ? [...tracks] : [...tracks].sort((a,b) => mode === 'old' ? a.id - b.id : b.id - a.id);
    const selectedSort = () => selected > 0 ? playlistSort : librarySort;
    const selectedSource = () => collections.descriptor(libraryView,descriptorForPlaylist(selected));
    const selectedTracks = () => collections.collection(data,selectedSource(),selectedSort());
    const element = (tag, className, text) => { const node = document.createElement(tag); node.className = className; if (text !== undefined) node.textContent = text; return node; };
    const setIcon = (node, name) => node.querySelector('use').setAttribute('href', `#rp-i-${name}`);
    const rangeFill = (node, fraction) => node.style.setProperty('--fill', `${Math.max(0, Math.min(1, fraction)) * 100}%`);
    const catalogUI = new window.TapeRoomCatalogUI({app,icon,escape:esc,defaultCover,defaultArtist,imageSources});

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
        app.querySelectorAll('[data-default-cover]').forEach(image => { window.TapeRoomImages.apply(image,'',config,theme,'cover',image.hasAttribute('data-compact-image')); });
        app.querySelectorAll('[data-default-artist]').forEach(image => {
            window.TapeRoomImages.apply(image,'',config,theme,'artist',image.hasAttribute('data-compact-image'));
            image.hidden=!defaultArtist();if(defaultArtist()){image.removeAttribute('data-image-fallback');const placeholder=image.parentElement.querySelector('.rp-artist-placeholder');if(placeholder)placeholder.hidden=true;}
        });
        updateNow();
        catalogUI.render({data,view:libraryView,current,playing:!audio.paused,query:$('[data-search]').value,canPublish:Boolean(data.catalog?.canPublish)});
    }

    function toast(message, error = false) {
        const node = $('[data-toast]'); node.textContent = message; node.classList.toggle('is-error', error); node.hidden = false;
        clearTimeout(toastTimer); toastTimer = setTimeout(() => { node.hidden = true; }, error ? 7000 : 3500);
    }
    function save() {
        try { localStorage.setItem(config.storageKey, JSON.stringify({playlist:selected, libraryView, queueSource, current, queue, queueName, queuePlaylist, queueSort, librarySort, playlistSort, time:playbackPosition(), volume:audio.volume, audibleVolume, muted:audio.muted, shuffle, repeat})); } catch (_) {}
    }
    function closeSort() {
        const options = $('[data-sort-options]'), restoreFocus = options.contains(document.activeElement);
        options.hidden = true;
        $('[data-sort-trigger]').setAttribute('aria-expanded', 'false');
        setIcon($('[data-sort-trigger]'),'down');
        if (restoreFocus) $('[data-sort-trigger]').focus();
    }
    function openSort() {
        $('[data-sort-options]').hidden = false;
        $('[data-sort-trigger]').setAttribute('aria-expanded', 'true');
        setIcon($('[data-sort-trigger]'),'up');
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
        if ((libraryView.type === 'album' && !album(libraryView.id)) || (libraryView.type === 'playlist' && !playlist(libraryView.id))) libraryView = {type:'mine'};
        if ((queueSource.type === 'album' && !album(queueSource.id)) || (queueSource.type === 'playlist' && !playlist(queueSource.id))) { queueSource = {type:'songs'}; queuePlaylist = 0; queueSort = librarySort; }
        queue = collections.collection(data,queueSource,queueSort).map(t => t.id);
        queueName = collections.name(data,queueSource);
        bag = bag.filter(id => queue.includes(id)); futureBag = futureBag.filter(id => queue.includes(id)); history = history.filter(id => Boolean(track(id)));
        if (current && !activeTrack()) { audio.pause(); audio.removeAttribute('src'); audio.load(); current = 0; updateNow(); }
        const nav = $('[data-playlists]'); nav.replaceChildren();
        for (const item of [{id:0, name:'Все мои песни', tracks:collections.myTracks(data)}, {id:-1, name:'Избранное', tracks:collectionTracks(-1)}, ...data.playlists]) {
            const active = ['songs','favorites','playlist'].includes(libraryView.type) && item.id === selected;
            const node = element('button', `rp-playlist${active ? ' is-selected' : ''}`);
            node.type = 'button'; node.dataset.action = 'select-playlist'; node.dataset.id = item.id;
            node.setAttribute('aria-current', active ? 'page' : 'false');
            node.innerHTML = icon(item.id === -1 ? 'heart' : item.id ? 'list' : 'music');
            node.append(element('span', 'rp-playlist-name', item.name), element('span', 'rp-playlist-count', item.tracks.length));
            nav.append(node);
        }
        const query = $('[data-search]').value.trim().toLocaleLowerCase();
        const catalog = catalogUI.render({data,view:libraryView,current,playing:!audio.paused,query,canPublish:Boolean(data.catalog?.canPublish)});
        const list = libraryView.type === 'artist' ? selectedTracks() : catalog.tracks || selectedTracks();
        const visible = list.filter(t => `${t.title} ${t.artist} ${t.filename}`.toLocaleLowerCase().includes(query));
        $('[data-list-title]').textContent = catalog.title || collections.name(data,selectedSource());
        $('[data-list-summary]').textContent = catalog.summary ?? `${list.length} ${list.length % 10 === 1 && list.length % 100 !== 11 ? 'трек' : list.length % 10 >= 2 && list.length % 10 <= 4 && !(list.length % 100 >= 12 && list.length % 100 <= 14) ? 'трека' : 'треков'}${list.some(t => t.duration) ? ' · ' + formatTime(list.reduce((total, t) => total + t.duration, 0)) : ''}`;
        $('[data-playlist-actions]').hidden = selected <= 0;
        renderSort();
        const rows = $('[data-tracks]'), fragment = document.createDocumentFragment();
        for (const t of visible) {
            const index = list.indexOf(t), row = element('article', `rp-track${t.id === current ? ' is-current' : ''}`);
            row.dataset.trackId = t.id;
            const play = element('button', 'rp-track-play'); play.type = 'button'; play.dataset.action = 'play-track'; play.dataset.id = t.id;
            play.setAttribute('aria-label', `Воспроизвести ${t.title}`);
            play.innerHTML = `<span class="rp-track-number">${String(index + 1).padStart(2, '0')}</span>${icon(t.id === current && !audio.paused ? 'pause' : 'play')}`;
            const cover = element('img', 'rp-track-cover'); cover.alt = ''; cover.loading = 'lazy'; cover.decoding = 'async'; cover.width=48; cover.height=48;
            cover.dataset.libraryImage='cover'; cover.dataset.compactImage='';
            window.TapeRoomImages.apply(cover,t.cover,config,theme,'cover',true);
            if (!t.cover) cover.dataset.defaultCover = '';
            const info = element('div', 'rp-track-info');
            info.append(element('span', 'rp-track-title', t.title), element('span', 'rp-track-artist', `${t.artist || 'Неизвестный исполнитель'}${t.source === 'drive' ? ' · Google Drive' : ''}`));
            const genre=t.genre || (libraryView.type==='album' ? album(libraryView.id)?.genre : libraryView.type==='genre' ? collections.name(data,selectedSource()) : '');
            if(genre){const label=element('span','rp-track-genre',genre);label.title=`Жанр: ${genre}`;info.append(label);}
            const actions = element('div', 'rp-track-actions');
            const canReorder = selected > 0 && playlistSort === 'playlist';
            actions.innerHTML = (canReorder ? button('move-up', 'Переместить выше', 'up', t.id, 'rp-reorder') + button('move-down', 'Переместить ниже', 'down', t.id, 'rp-reorder') : '') + button('favorite', t.favorite ? 'Убрать из избранного' : 'Добавить в избранное', 'heart', t.id, `rp-favorite${t.favorite ? ' is-active' : ''}`) + button('add-to-playlist', 'Добавить в плейлист', 'plus', t.id) + (!t.shared ? button('edit-track', 'Изменить трек', 'edit', t.id) : '') + (selected > 0 ? button('remove-track','Убрать из плейлиста','trash',t.id) : !t.shared && libraryView.type === 'songs' ? button('delete-track','Удалить из библиотеки','trash',t.id) : '');
            actions.querySelector('[data-action="favorite"]').setAttribute('aria-pressed', String(t.favorite));
            if (canReorder) {
                actions.querySelector('[data-action="move-up"]').disabled = index === 0;
                actions.querySelector('[data-action="move-down"]').disabled = index === list.length - 1;
            }
            row.append(play, cover, info, element('span', 'rp-track-duration', t.duration ? formatTime(t.duration) : '—'), actions); fragment.append(row);
        }
        window.TapeRoomImages.patch(rows,fragment);
        $('[data-empty]').hidden = !catalog.listMode || visible.length > 0;
        $('[data-empty-title]').textContent = query ? 'Ничего не найдено' : catalog.emptyTitle || (selected === -1 ? 'Любимые песни будут здесь' : selected > 0 ? 'Эта лента пока пустая' : 'Здесь начинается ваша коллекция');
        $('[data-empty-description]').textContent = query ? 'Попробуйте другое название или имя исполнителя.' : catalog.emptyDescription || (selected === -1 ? 'Нажмите сердечко рядом с песней, чтобы добавить её в избранное.' : selected > 0 ? 'Загрузите музыку сюда или добавьте треки из общей библиотеки.' : 'Перетащите аудиофайлы сюда или добавьте их кнопкой выше.');
        $('[data-action="play"]').disabled = !current && list.length === 0;
        $('[data-action="prev"]').disabled = !current;
        $('[data-action="next"]').disabled = !current;
        $('[data-action="edit-current"]').disabled = !current || Boolean(activeTrack()?.shared);
        updateNow(); updateButtons(); renderChapters(); prepareUpcoming();
    }
    function drawAudioQuality() {
        const t = activeTrack(), node = $('[data-audio-quality]');
        if (!node) return;
        const value = t ? window.TapeRoomQuality.summary(t,qualityCache.get(t.id),audio.readyState > 0 ? audio.duration : 0) : 'АУДИО';
        if (node.textContent !== value) node.textContent = value;
        const title = value.includes('≈') ? `${value}. Битрейт оценён по размеру файла и длительности; это среднее значение.` : value;
        if (node.title !== title) node.title = title;
    }
    function cancelQuality() {
        if (!qualityJob) return;
        qualityJob.controller.abort(); clearTimeout(qualityJob.timer); qualityJob = null;
    }
    async function requestQuality() {
        cancelQuality();
        const t = activeTrack();
        if (!t || qualityCache.has(t.id)) return;
        const version = generation, id = t.id, controller = new AbortController();
        const job = {controller,timer:setTimeout(() => controller.abort(),9000)};
        qualityJob = job;
        try {
            const result = await api(`${t.shared ? 'catalog/' : ''}quality/${id}`,undefined,controller.signal);
            if (generation !== version || current !== id || controller.signal.aborted) return;
            qualityCache.set(id,result.quality || {});
            if (qualityCache.size > 64) qualityCache.delete(qualityCache.keys().next().value);
            drawAudioQuality();
        } catch (_) { /* Optional technical metadata never gates music or opens an error dialog. */ }
        finally { clearTimeout(job.timer); if (qualityJob === job) qualityJob = null; }
    }
    function updateNow() {
        const t = activeTrack();
        drawAudioQuality();
        $('[data-title]').textContent = t?.title || 'Ваша первая лента';
        $('[data-artist]').textContent = t?.artist || (t ? 'НЕИЗВЕСТНЫЙ ИСПОЛНИТЕЛЬ' : 'ДОБАВЬТЕ ЛЮБИМУЮ МУЗЫКУ');
        const albumArtwork = queueSource.type === 'album' ? album(queueSource.id)?.cover : '';
        const customArtwork = t?.cover || albumArtwork || '';
        if (customArtwork) window.TapeRoomImages.apply($('[data-cover]'),customArtwork,config,theme);
        else { $('[data-cover]').removeAttribute('srcset'); $('[data-cover]').removeAttribute('src'); }
        $('[data-cover]').closest('.rp-cover-button').classList.toggle('has-custom-cover',Boolean(customArtwork));
        $('[data-source]').textContent = current ? (queueName || 'Вся музыка') + (t?.source === 'drive' ? ' · GOOGLE DRIVE' : ' · STEREO') : 'ВАША МУЗЫКА. ВАШ РИТМ.';
        if (t && 'mediaSession' in navigator && 'MediaMetadata' in window) {
            const defaultArt = config.defaultCoverVariants?.[theme]?.[640];
            const artwork = customArtwork ? {src:new URL(window.TapeRoomImages.thumbnail(customArtwork,640),location.href).href}
                : {src:new URL(defaultArt || config.defaultCovers?.[theme] || config.defaultArtwork || config.defaultCover,location.href).href,sizes:defaultArt ? '640x640' : config.defaultArtworkSize || '512x512',type:defaultArt ? 'image/webp' : 'image/png'};
            const chapter = t.chapters?.length ? t.chapters[window.TapeRoomChapters.indexAt(t.chapters,playbackPosition())] : null;
            try { navigator.mediaSession.metadata = new MediaMetadata({title:chapter?.title || t.title, artist:chapter?.artist || t.artist, album:chapter ? t.title : queueName || 'Tape Room', artwork:[artwork]}); } catch (_) {}
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
        if (!loading) { clearTimeout(bufferTimer); bufferTimer = undefined; bufferVisible = false;  }
        else if (!bufferVisible && !bufferTimer) {
            bufferTimer = setTimeout(() => {
                bufferTimer = undefined;
                if (loading && !audio.paused) { bufferVisible = true; updateButtons(); }
            }, 350);
        }
        if (loading) drawMeters(false);
        meterController.buffering(loading);
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
        const position = playbackPosition();
        drawAudioQuality();
        const progress = duration > 0 ? Math.max(0, Math.min(1, position / duration)) : 0;
        if (!seekActive) { visuals.seek.value = Math.round(progress * 1000); rangeFill(visuals.seek, progress); }
        drawBuffered(duration,seekActive ? Number(visuals.seek.value)/1000 : progress);
        visuals.seek.disabled = !current || !duration;
        const elapsed = formatTime(position), total = formatTime(duration);
        if (!seekActive && visuals.elapsed.textContent !== elapsed) visuals.elapsed.textContent = elapsed;
        if (visuals.duration.textContent !== total) visuals.duration.textContent = total;
        syncChapter(duration);
        const inner = 78, outer = 174;
        const radii = [Math.sqrt(inner ** 2 + (outer ** 2 - inner ** 2) * (1 - progress)), Math.sqrt(inner ** 2 + (outer ** 2 - inner ** 2) * progress)];
        visuals.reels.forEach((reel,i) => {
            const radius = radii[i].toFixed(2);
            if (reel.pack.getAttribute('r') === radius) return;
            reel.pack.setAttribute('r',radius); reel.clip.setAttribute('r',radius); reel.tapeMask.setAttribute('r',radius);
        });
        packRadii = radii;
        syncReels(); drawTape();
        const key = `${generation}:${duration}:${Math.floor(position)}:${audio.playbackRate}`;
        if ((forcePosition || key !== positionKey) && 'mediaSession' in navigator && navigator.mediaSession.setPositionState && duration > 0) {
            try { navigator.mediaSession.setPositionState({duration, playbackRate:audio.playbackRate, position:Math.min(duration,position)}); } catch (_) {}
            positionKey = key;
        }
        return radii;
    }
    function drawTape() {
        const slack = reducedMotion.matches ? 0 : transport.slack;
        visuals.tape.forEach((path,i) => {
            const value = window.TapeRoomTransport.tapePath(i ? 970 : 230,packRadii[i],i ? -1 : 1,slack);
            if (path.getAttribute('d') !== value) path.setAttribute('d',value);
            const reel = visuals.reels[i];
            if (reel.tapeFade && reel.fadeRadius !== packRadii[i]) {
                const entry = window.TapeRoomTransport.tapeEntry(i ? 970 : 230,packRadii[i],i ? -1 : 1);
                const coords = {x1:entry.x - entry.tx * 2,y1:entry.y - entry.ty * 2,x2:entry.x + entry.tx * 16,y2:entry.y + entry.ty * 16};
                for (const [name,coordinate] of Object.entries(coords)) {
                    const text = coordinate.toFixed(2);
                    if (reel.tapeFade.getAttribute(name) !== text) reel.tapeFade.setAttribute(name,text);
                }
                reel.fadeRadius = packRadii[i];
            }
        });
    }
    function syncReels() {
        const visible = !document.hidden && !reducedMotion.matches;
        if (visible) transport.setRunning(!audio.paused && !audio.ended && !loading && !audio.error);
        else transport.reset();
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
            const rate = 126 / packRadii[i] * audio.playbackRate * transport.speed;
            // updatePlaybackRate preserves the angle as the pack changes size.
            // The compositor then rotates without waiting for JS meter samples.
            if ((rate === 0 && reel.motion.playbackRate !== 0) || Math.abs(reel.motion.playbackRate - rate) > .001) {
                if (reel.motion.updatePlaybackRate) reel.motion.updatePlaybackRate(rate);
                else reel.motion.playbackRate = rate;
            }
            if (visible && transport.speed > 0) { if (reel.motion.playState === 'paused' && !reel.motion.pending) reel.motion.play(); }
            else if (reel.motion.playState !== 'paused') reel.motion.pause();
        });
        if (visible && transport.moving && !frameInProgress) startAnimation();
    }
    const meterController = new window.TapeRoomMeters({audio,generation:()=>generation,source:()=>activeTrack()?.source,bufferedAhead,changed:()=>{
        graph = meterController.engine;
        const notice = $('[data-meter-recovery]');
        if (notice) notice.hidden = !meterController.needsGesture || audio.paused || audio.ended || document.hidden;
    }});
    function resumeMeters(origin = 'lifecycle', event) {
        try { meterController.resume(origin,event); }
        catch (_) { meterController.terminal = true; meterController.state('unavailable','analysis-command-failed'); }
        graph = meterController.engine;
    }
    function stopMeters(reason = 'paused') { meterController.stop(reason); graph = meterController.engine; drawMeters(false); }
    function syncMirror() { meterController.tick(); }
    function configureAudioSession() {
        try { if (navigator.audioSession) navigator.audioSession.type = 'playback'; } catch (_) {}
    }
    function drawMeters(playing) {
        const now = performance.now();
        const active = playing && !document.hidden && !audio.seeking && !audio.muted && audio.volume > 0 && !meterController.terminal && !meterController.needsGesture && graph?.context.state === 'running';
        const changedTrack = meterSignal.generation !== generation;
        if (!active || meterSignal.generation !== generation) { meterSignal.values.fill(0); meterSignal.rms.fill(0); meterSignal.sampled.fill(-Infinity); meterSignal.generation = generation; }
        const available = active && meterController.canSample() && (!graph.mirror || (!graph.mirror.paused && !graph.mirror.seeking && graph.mirror.readyState >= 2 && !graph.mirror.ended));
        const elapsed = Math.max(0, Math.min(1000, now - meterSignal.drawn)); meterSignal.drawn = now;
        const targets = [0,0];
        visuals.reels.forEach((reel,i) => {
            let level = 0, measured = false;
            if (available) {
                try {
                    const samples = graph.samples;
                    graph.analysers[i].getFloatTimeDomainData(samples);
                    let sum = 0; for (let n=0; n<samples.length; n++) sum += samples[n] * samples[n];
                    const raw = Math.sqrt(sum / samples.length);
                    meterController.sample(i,raw);
                    const rms = raw * audio.volume;
                    meterSignal.rms[i] = rms;
                    level = Math.max(0, Math.min(15, (20 * Math.log10(Math.max(rms, .00001)) + 45) / 3));
                    measured = true;
                } catch (_) { meterController.readFailure(i); }
            }
            // Fast attack and a short release use only measured music. A brief
            // analysis seek can decay the last reading, never invent a signal.
            if (active && (measured || now - meterSignal.sampled[i] <= 180)) level = Math.max(level,meterSignal.values[i] * Math.exp(-elapsed / 90));
            meterSignal.values[i] = level;
            if (measured) meterSignal.sampled[i] = now;
            if (level < .15) level = 0;
            targets[i] = level;
        });
        const displayActive = active && (available || meterSignal.sampled.some(time => now - time <= 180));
        const immediate = changedTrack || document.hidden || reducedMotion.matches || audio.seeking || audio.muted || audio.volume === 0 || Boolean(audio.error) || meterController.terminal || meterController.needsGesture || (playing && !displayActive);
        const displayed = meterEnvelope.step(targets,elapsed / 1000,{active:displayActive,immediate});
        visuals.reels.forEach((reel,i) => {
            const lit = displayed[i] < .15 ? 0 : Math.ceil(displayed[i]);
            if (lit === meterLevels[i]) return;
            reel.bars.forEach((bar,n) => { if ((n < lit) !== (n < meterLevels[i])) bar.classList.toggle('is-lit',n < lit); });
            meterLevels[i] = lit;
        });
        if (meterEnvelope.moving && !frameInProgress) startAnimation();
        recordMeterDiagnostic();
    }
    function recordMeterDiagnostic(force = false) {
        const now = performance.now();
        if (!force && now - meterReport.time < 500) return;
        meterReport.time = now;
        // A bounded, memory-only report. Never include track URLs, names, IDs,
        // cookies, OAuth credentials or account details; nothing is uploaded.
        const copy = graph?.mirror;
        meterReport.samples.push({ms:Math.round(now), hidden:document.hidden,
            native:{time:audio.currentTime,paused:audio.paused,ended:audio.ended,ready:audio.readyState,seeking:audio.seeking,waiting:loading,rate:audio.playbackRate,muted:audio.muted,volume:audio.volume},
            analysis:{mode:copy ? 'copy' : graph?.capture ? 'capture' : 'none',state:graph?.context.state || 'absent',clock:graph?.context.currentTime,resuming:Boolean(graph?.resumeJob),levels:[...meterLevels],rms:[...meterSignal.rms],sampleAgeMs:meterSignal.sampled.map(time => Number.isFinite(time) ? Math.round(now-time) : null),health:meterController.snapshot(),
                copy:copy ? {time:copy.currentTime,paused:copy.paused,ended:copy.ended,ready:copy.readyState,seeking:copy.seeking,rate:copy.playbackRate} : undefined}});
        while (meterReport.samples.length > 180 || now - meterReport.samples[0].ms > 90000) meterReport.samples.shift();
        if (meterReport.status && dialog.open && meterReport.status.isConnected) {
            const retry = $('[data-meter-report-retry]'); if (retry) retry.hidden = !meterController.needsGesture || audio.paused || audio.ended;
            meterReport.status.textContent = audio.paused ? 'Музыка на паузе.' : loading ? 'Музыка буферизуется.' : meterController.needsGesture ? 'Safari требует нажатия. Нажмите «Включить индикаторы».' : meterController.terminal ? 'Анализатор не смог продолжить работу. Причина записана в отчёте; воспроизведение песни от него не зависит.' : meterLevels.some(level => level > 0) ? 'Индикаторы получают сигнал музыки.' : 'Музыка играет. Сейчас анализатор не показывает уровень сигнала.';
        }
    }
    function openMeterReport() {
        openDialog('Проверка индикаторов', `<div class="rp-settings-section"><h3>Tape Room ${esc(config.version || '')}</h3><p data-meter-report-status></p><button type="button" class="rp-button" data-action="meters" data-meter-report-retry hidden>Включить индикаторы</button></div><div class="rp-settings-section"><p>Если индикаторы пропадают, сохраните отчёт во время воспроизведения. Файл остаётся на устройстве и содержит только состояние плеера за последние 90 секунд, без данных аккаунта и названий песен.</p><button type="button" class="rp-button" data-action="meter-report-download">Сохранить отчёт</button></div>`, async () => {}, '', 'Готово');
        meterReport.status = $('[data-meter-report-status]'); recordMeterDiagnostic(true);
    }
    function downloadMeterReport() {
        recordMeterDiagnostic(true);
        const report = {version:config.version || '',browser:navigator.userAgent,standalone:matchMedia('(display-mode: standalone)').matches || navigator.standalone === true,samples:meterReport.samples,events:meterController.events};
        const url = URL.createObjectURL(new Blob([JSON.stringify(report,null,2)],{type:'application/json'}));
        const link = document.createElement('a'); link.href = url; link.download = `tape-room-${config.version || 'report'}-meters.json`; document.body.append(link); link.click(); link.remove();
        setTimeout(() => URL.revokeObjectURL(url),60000);
    }
    function animate(time) {
        frameId = 0; frameInProgress = true;
        const playing = !audio.paused && !audio.ended && !audio.error;
        const elapsed = frameTime ? Math.max(0,Math.min(1, (time - frameTime) / 1000)) : 0; frameTime = time;
        // Rotation follows the display cadence; geometry and audio sampling do
        // not need to repaint the entire deck on every display frame.
        if (time - progressFrame >= 100) { drawProgress(); progressFrame = time; }
        if (time - meterFrame >= 1000 / 30) { drawMeters(playing && !loading); meterFrame = time; }
        transport.step(elapsed); syncReels();
        if (time - tapeFrame >= 1000 / 30 || !transport.moving) { drawTape(); tapeFrame = time; }
        if (!document.hidden && !reducedMotion.matches && transport.speed > 0) {
            visuals.reels.forEach((reel,i) => { if (!reel.motion) { angles[i] = (angles[i] - elapsed * 95 * 126 / packRadii[i] * audio.playbackRate * transport.speed) % 360; reel.rotor.style.transform = `rotate(${angles[i]}deg)`; } });
        }
        frameInProgress = false;
        if ((playing || transport.moving || meterEnvelope.moving) && !document.hidden) frameId = requestAnimationFrame(animate);
    }
    function startAnimation() { if (!frameId && !frameInProgress && !document.hidden) { frameTime = 0; progressFrame = meterFrame = tapeFrame = -Infinity; frameId = requestAnimationFrame(animate); } }
    async function play(origin = 'automatic', event) {
        if (!current) {
            const first = selectedTracks()[0]; if (!first) return;
            setQueue(); load(first.id);
        }
        configureAudioSession();
        if (audio.error) load(current,playbackPosition());
        if (audio.ended) audio.currentTime = 0;
        const version = generation;
        // Keep play() inside the tap's activation; meter setup never gates sound.
        try { const playback = audio.play(); resumeMeters(origin,event); await playback; }
        catch (error) { if (version === generation && error.name !== 'AbortError') { setBuffering(false); stopMeters('native-error'); toast('Не удалось воспроизвести трек. Проверьте формат, соединение и доступ к файлу.', true); } }
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
    function setQueue(source = selectedSource()) { preloader.reset(); queueSource = collections.descriptor(source); queueSort = selectedSort(); queue = collections.collection(data,queueSource,queueSort).map(t => t.id); queuePlaylist = queueSource.type === 'playlist' ? queueSource.id : queueSource.type === 'favorites' ? -1 : 0; queueName = collections.name(data,queueSource); bag = []; futureBag = []; shuffleCycleStarted = false; history = []; }
    function load(id, time = 0) {
        const t = track(id); if (!t) return;
        sampleListening(); listening = null;
        preloader.cancel(); generation++; audio.pause(); stopMeters('track-change'); current = t.id; restoreTime = time; setBuffering(false);
        chapterKey = ''; chapterIndex = -2; chapterDuration = -1;
        audio.src = t.url; audio.load(); render(); drawProgress(); save(); requestQuality();
    }
    function next(automatic = false, origin = 'automatic', event) {
        if (!current) return;
        if (!automatic && seekChapter(1,origin,event)) return;
        if (!queue.length) return;
        if (automatic && repeat === 'one') { audio.currentTime = 0; play(origin,event); return; }
        if (automatic && repeat === 'off' && queue.length === 1 && queue[0] === current) return;
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
        history.push(current); load(id); play(origin,event);
    }
    function previous(origin = 'automatic', event) {
        if (!current) return;
        if (seekChapter(-1,origin,event)) return;
        if (audio.currentTime > 3) { audio.currentTime = 0; drawProgress(); return; }
        const id = shuffle && history.length ? history.pop() : queue[(Math.max(0, queue.indexOf(current)) - 1 + queue.length) % queue.length];
        if (id) { futureBag = []; load(id); play(origin,event); }
    }
    function applyState(result) { data = {tracks:result.tracks, playlists:result.playlists,catalog:result.catalog || {albums:[],tracks:[]}}; trackPool = collections.tracks(data); trackMap = new Map(trackPool.map(t=>[t.id,t])); render(); save(); }
    function mutate(operation) {
        // Library snapshots must be applied in commit order. Metadata, uploads
        // and button actions share this queue so an older reply cannot erase rows.
        mutations++;
        const pending = mutationQueue.then(operation);
        mutationQueue = pending.catch(() => {});
        return pending.finally(() => { mutations--; });
    }
    async function api(path, form, signal) {
        const response = await fetch(`${config.api}/${path}`, {method:form ? 'POST' : 'GET', credentials:'same-origin', headers:form ? {'X-CSRF-Token':config.csrf,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'} : {'Accept':'application/json'}, body:form,signal});
        let result;
        try { result = await response.json(); } catch (_) { throw new Error(response.status === 401 || response.status === 403 || response.redirected ? 'Сессия завершена. Обновите страницу и войдите в CMS.' : 'Сервер не ответил. Попробуйте ещё раз.'); }
        if (!response.ok || !result.status) throw new Error(result.message || (response.status === 419 ? 'Обновите страницу: срок действия сессии истёк.' : 'Не удалось выполнить действие.'));
        return result;
    }
    async function listeningRequest(values) {
        const form=new FormData(); form.set('needCSRFToken',config.csrf);
        for (const [key,value] of Object.entries(values)) form.set(key,value);
        const controller=new AbortController(), timer=setTimeout(()=>controller.abort(),10000);
        try { return await api('catalog/listen',form,controller.signal); }
        finally { clearTimeout(timer); }
    }
    function startListening() {
        if (!current || listening?.generation===generation) return;
        const job={id:current,generation,seconds:0,position:audio.currentTime,wall:performance.now(),token:'',threshold:Infinity,counted:false,busy:false,errors:0,retryTimer:null,deadline:Infinity};
        listening=job;
        listeningRequest({action:'start',id:job.id}).then(result=>{
            job.token=result.token; job.threshold=Number(result.threshold) || 30;
            finishListening(job);
        }).catch(()=>{}); // Optional statistics must never interrupt playback.
    }
    function sampleListening(ended = false) {
        const job=listening; if (!job || job.generation!==generation) return;
        const now=performance.now(), wall=Math.max(0,(now-job.wall)/1000), delta=audio.currentTime-job.position;
        const rate=Math.max(.25,audio.playbackRate || 1);
        if ((!audio.paused || ended) && !audio.seeking && !audio.muted && audio.volume>0 && !audio.error && delta>0 && delta<=wall*rate+.5) job.seconds+=Math.min(delta/rate,wall+.1);
        job.position=audio.currentTime; job.wall=now;
        finishListening(job);
    }
    function finishListening(job) {
        if (!job.token || job.counted || job.busy || job.retryTimer || job.seconds<job.threshold || job.errors>=3 || performance.now()>job.deadline) return;
        if (!Number.isFinite(job.deadline)) job.deadline=performance.now()+60000;
        job.busy=true;
        listeningRequest({action:'finish',token:job.token,seconds:job.seconds}).then(result=>{
            job.counted=Boolean(result.counted);
            if (job.counted) {
                for (const t of [...data.tracks,...(data.catalog?.tracks || [])]) if (t.id===job.id) t.listen_count=Number(result.listens) || 0;
                const t=track(job.id); if (t) t.listen_count=Number(result.listens) || 0;
                catalogUI.render({data,view:libraryView,current,playing:!audio.paused,query:$('[data-search]').value,canPublish:Boolean(data.catalog?.canPublish)});
            }
        }).catch(()=>{job.errors++;}).finally(()=>{
            job.busy=false;
            if (!job.counted && job.errors<3 && performance.now()<job.deadline) job.retryTimer=setTimeout(()=>{job.retryTimer=null;finishListening(job);},1200);
        });
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
        dialogRequest++;
        chapterJob?.abort(); chapterJob = null;
        if (coverPreviewUrl) { URL.revokeObjectURL(coverPreviewUrl); coverPreviewUrl = undefined; }
        dialogSubmitting = false;
        dialog.classList.remove('rp-dialog--drive','rp-dialog--chapters','rp-dialog--album','rp-dialog--artist','rp-dialog--album-delete');
        $('[data-dialog-title]').textContent = title;
        $('[data-dialog-fields]').innerHTML = fields;
        $('[data-dialog-description]').textContent = description; $('[data-dialog-description]').hidden = !description;
        $('[data-dialog-error]').hidden = true;
        const control = $('[data-dialog-submit]'); control.textContent = submit; control.disabled = false; control.hidden = false; control.classList.toggle('rp-button--danger', danger);
        dialogHandler = handler;
        lockDialogScroll(); syncDialogViewport();
        try { dialog.showModal(); } catch (error) { unlockDialogScroll(); throw error; }
    }
    function lockDialogScroll() {
        if (dialogScroll) return;
        dialogScroll = {x:window.scrollX,y:window.scrollY,behavior:document.documentElement.style.scrollBehavior,rootLocked:document.documentElement.classList.contains('rp-dialog-open')};
        const padding = parseFloat(getComputedStyle(document.body).paddingRight) || 0;
        document.body.style.setProperty('--rp-dialog-page-offset',`${-dialogScroll.y}px`);
        document.body.style.setProperty('--rp-dialog-page-padding',`${padding+Math.max(0,window.innerWidth-document.documentElement.clientWidth)}px`);
        document.body.classList.add('is-dialog-open');
        document.documentElement.classList.add('rp-dialog-open');
    }
    function unlockDialogScroll() {
        if (!dialogScroll) return;
        const position=dialogScroll; dialogScroll=null;
        document.documentElement.style.scrollBehavior='auto';
        document.body.classList.remove('is-dialog-open');
        document.body.style.removeProperty('--rp-dialog-page-offset');
        document.body.style.removeProperty('--rp-dialog-page-padding');
        if (!position.rootLocked) document.documentElement.classList.remove('rp-dialog-open');
        window.scrollTo(position.x,position.y);
        document.documentElement.style.scrollBehavior=position.behavior;
    }
    function syncDialogViewport() {
        const viewport = window.visualViewport;
        const height = Math.min(window.innerHeight,viewport?.height || window.innerHeight);
        dialog.style.setProperty('--rp-dialog-viewport-height',`${height}px`);
        dialog.style.setProperty('--rp-dialog-viewport-top',`${viewport?.offsetTop || 0}px`);
        dialog.classList.toggle('is-compact',height < 520);
        dialog.classList.toggle('is-short',height < 340);
    }
    window.visualViewport?.addEventListener('resize',() => { if (dialog.open) syncDialogViewport(); });
    window.visualViewport?.addEventListener('scroll',() => { if (dialog.open) syncDialogViewport(); });
    window.addEventListener('resize',() => { if (dialog.open) syncDialogViewport(); });
    const field = (name, label, value = '', max = 240) => `<label class="rp-field">${label}<input name="${name}" value="${esc(value)}" maxlength="${max}" ${name === 'artist' ? '' : 'required'}></label>`;
    function resetCoverPreview(source,kind = 'cover') {
        if (coverPreviewUrl) { URL.revokeObjectURL(coverPreviewUrl);coverPreviewUrl=undefined; }
        const preview=dialog.querySelector('[data-cover-preview]');
        window.TapeRoomImages.apply(preview,source,config,theme,kind,true);
        preview.hidden=!imageSources(source,kind,true).src;
        preview.toggleAttribute(`data-default-${kind}`,!source);preview.removeAttribute('data-image-fallback');
        dialog.querySelector('[data-cover-filename]').textContent='JPG, PNG или WebP · до 8 МБ';
    }

    function renderChapters() {
        const t = activeTrack(), items = currentChapters(), panel = $('[data-chapter-panel]');
        if (!panel) return;
        const key = `${current}:${JSON.stringify(items)}`;
        if (key !== chapterKey) {
            chapterKey = key; chapterIndex = -2; chapterDuration = -1;
            $('[data-chapter-search]').value = '';
        }
        panel.hidden = !items.length;
        panel.querySelector('.rp-chapter-tools').hidden = Boolean(t?.shared);
        $('[data-chapter-source]').textContent = t ? [t.artist,t.title].filter(Boolean).join(' — ') : '';
        const query = $('[data-chapter-search]').value.trim().toLocaleLowerCase();
        const visible = items.map((item,index) => ({item,index})).filter(({item}) => `${item.title} ${item.artist || ''}`.toLocaleLowerCase().includes(query));
        $('[data-chapter-list]').innerHTML = visible.map(({item,index}) => `<article class="rp-chapter-row" data-chapter-row="${item.id}"><button type="button" class="rp-chapter-play" data-action="chapter-play" data-id="${item.id}" aria-label="Воспроизвести ${esc(item.title)} с ${chapters.formatTime(item.start)}"><span class="rp-chapter-number">${index+1}</span><span class="rp-chapter-info"><span class="rp-chapter-title">${esc(item.title)}</span>${item.artist ? `<span class="rp-chapter-artist">${esc(item.artist)}</span>` : ''}</span><span class="rp-chapter-start">${chapters.formatTime(item.start)}</span></button>${t?.shared ? '' : `<div class="rp-chapter-actions">${button('chapter-edit','Изменить таймкод','edit',item.id)}${button('chapter-delete','Удалить таймкод','trash',item.id)}</div>`}</article>`).join('');
        chapterRows = [...$('[data-chapter-list]').children];
        if ($('[data-chapter-empty]')) $('[data-chapter-empty]').hidden = visible.length !== 0;
        chapterIndex = -2;
        syncChapter(Number.isFinite(audio.duration) ? audio.duration : t?.duration || 0);
    }
    function syncChapter(duration) {
        const items = currentChapters(), markerContainer = $('[data-chapter-markers]'), status = $('[data-chapter-current]');
        if (!markerContainer || !status) return;
        markerContainer.hidden = !items.length || !duration;
        status.hidden = !items.length;
        if (chapterDuration !== duration) {
            chapterDuration = duration;
            markerContainer.innerHTML = duration > 0 ? items.filter(item => item.start < duration).map(item => `<button type="button" class="rp-chapter-marker" data-action="chapter-play" data-id="${item.id}" style="--chapter-position:${Math.max(0,Math.min(100,item.start/duration*100))}%" title="${esc(item.title)} · ${chapters.formatTime(item.start)}" aria-label="${esc(item.title)} · ${chapters.formatTime(item.start)}"></button>`).join('') : '';
            chapterMarkers = [...markerContainer.children];
            chapterIndex = -2;
        }
        const index = chapters.indexAt(items,playbackPosition());
        if (index === chapterIndex) return;
        chapterIndex = index;
        const item = items[index];
        status.textContent = item ? `Трек ${index+1} из ${items.length} · ${item.title}` : items.length ? 'До первого таймкода' : '';
        for (const row of chapterRows) {
            const active = Number(row.dataset.chapterRow) === item?.id;
            row.classList.toggle('is-current',active);
            const control = row.querySelector('[data-action="chapter-play"]');
            if (active) control.setAttribute('aria-current','true'); else control.removeAttribute('aria-current');
        }
        for (const marker of chapterMarkers) {
            const active = Number(marker.dataset.id) === item?.id;
            marker.classList.toggle('is-current',active);
            if (active) marker.setAttribute('aria-current','true'); else marker.removeAttribute('aria-current');
        }
        for (const [action,label] of [['prev','Предыдущая'],['next','Следующая']]) {
            const control = $(`[data-action="${action}"]`), text = items.length ? `${label} глава` : action === 'prev' ? 'Предыдущий трек' : 'Следующий трек';
            control.title = text; control.setAttribute('aria-label',text);
        }
        updateNow();
    }
    function seekTo(time) {
        if (!current || !Number.isFinite(Number(time))) return;
        const duration = Number.isFinite(audio.duration) ? audio.duration : activeTrack()?.duration || 0;
        const position = Math.max(0,duration > 0 ? Math.min(duration,Number(time)) : Number(time));
        if (audio.readyState === 0) restoreTime = position; else audio.currentTime = position;
        drawProgress(); save();
    }
    function jumpChapter(time, origin = 'user', event) {
        seekTo(time); play(origin,event);
    }
    function seekChapter(direction, origin, event) {
        const destination = chapters.destination(currentChapters(),playbackPosition(),direction);
        if (destination === null) return false;
        jumpChapter(destination,origin,event); return true;
    }
    function chapterTrack(control) { return Number(control.dataset.trackId) || current; }
    function chapterAtPointer(event) {
        const container = $('[data-chapter-markers]'), duration = Number.isFinite(audio.duration) ? audio.duration : activeTrack()?.duration || 0;
        const bounds = container.getBoundingClientRect();
        const time = bounds.width ? Math.max(0,Math.min(1,(event.clientX-bounds.left)/bounds.width))*duration : 0;
        return chapters.nearest(currentChapters().filter(item => item.start < duration),time);
    }
    function editChapter(trackId, chapterId = 0) {
        const t = track(trackId); if (!t) return;
        const item = t.chapters?.find(item => item.id === chapterId);
        if (chapterId && !item) return;
        const start = item?.start ?? (current === trackId ? playbackPosition() : 0);
        openDialog(item ? 'Изменить таймкод' : 'Добавить таймкод',
            field('title','Название композиции',item?.title || '') + field('artist','Исполнитель (необязательно)',item?.artist || '') +
            `<label class="rp-field">Время начала<input name="start" value="${chapters.formatTime(start)}" placeholder="03:25 или 1:03:25" maxlength="20" inputmode="text" required></label><button type="button" class="rp-button" data-action="chapter-current-time" data-track-id="${trackId}" ${current !== trackId ? 'disabled' : ''}>Использовать текущее время</button>`,
            async form => {
                const start = chapters.parseTime(form.get('start'));
                await action(item ? 'chapter.update' : 'chapter.create',{track_id:trackId,id:chapterId,title:form.get('title'),artist:form.get('artist'),start});
                if (!current) { setQueue(); load(trackId); }
                toast(item ? 'Таймкод изменён.' : 'Таймкод добавлен.');
            }, `Исходная запись: ${t.title}. Аудиофайл остаётся целым.`);
    }
    function chapterTools(trackId) {
        const t = track(trackId); if (!t) return;
        openDialog('Главы записи', `<div class="rp-settings-section"><h3>Импорт CUE</h3><p>В CUE должен быть указан этот файл: <strong>${esc(t.filename)}</strong>. Сначала вы увидите список для проверки.</p><label class="rp-cue-picker rp-button">${icon('upload')}<span>Выбрать .cue</span><input type="file" name="cue" accept=".cue" class="rp-file-input" aria-label="Выбрать файл CUE" data-chapter-cue data-track-id="${trackId}"></label></div><div class="rp-settings-section"><h3>Найти переходы</h3><p>Поиск пауз в аудиосигнале предложит возможные границы. Переходы в непрерывном DJ-миксе могут быть пропущены. Названия и исполнителей нужно указать самостоятельно.</p><button type="button" class="rp-button" data-action="chapter-analyze" data-track-id="${trackId}">Анализировать запись</button><p data-chapter-job-status role="status" hidden></p></div><p>Распознавание названий требует отдельного сервиса. Сервис не подключён; ваша музыка ему не передаётся.</p>`,async () => {},`Исходная запись: ${t.title}`,'Готово');
        dialog.querySelector('[data-chapter-cue]').addEventListener('change',async event => {
            const file = event.target.files[0]; if (!file) return;
            if (!/\.cue$/i.test(file.name) || file.size > 1048576) { toast('Выберите .cue размером до 1 МБ.',true); event.target.value = ''; return; }
            await requestChapterPreview(trackId,'chapters/cue',file);
        });
    }
    async function requestChapterPreview(trackId,path,file) {
        if (chapterJob) return;
        const request = dialogRequest, controller = new AbortController(); chapterJob = controller;
        const form = new FormData(); form.set('track_id',trackId); form.set('needCSRFToken',config.csrf); if (file) form.set('cue',file);
        $('[data-dialog-error]').hidden = true;
        const status = $('[data-chapter-job-status]');
        if (status) { status.hidden = false; status.textContent = file ? 'Читаем CUE…' : 'Анализируем сигнал. Это может занять до минуты. Окно можно закрыть, чтобы отменить.'; }
        dialog.querySelectorAll('[data-chapter-cue],[data-action="chapter-analyze"]').forEach(control => { control.disabled = true; });
        const timeout = setTimeout(() => controller.abort(),90000);
        try {
            const result = await api(path,form,controller.signal);
            if (request !== dialogRequest || !dialog.open) return;
            const entries = result.entries || result.chapters || [];
            if (!entries.length) { if (status) status.textContent = 'Подходящие паузы не найдены. Добавьте таймкоды вручную или импортируйте CUE.'; return; }
            showChapterPreview(trackId,entries,result.warnings || [],Boolean(file),result.revision);
        } catch (error) {
            if (request === dialogRequest && dialog.open) {
                if (status) status.hidden = true;
                const message = error.name === 'AbortError' ? 'Анализ отменён или превышено время ожидания. Сохранённая разметка не изменена.' : error.message;
                $('[data-dialog-error]').textContent = message; $('[data-dialog-error]').hidden = false;
            }
        } finally {
            clearTimeout(timeout); if (chapterJob === controller) chapterJob = null;
            if (request === dialogRequest) dialog.querySelectorAll('[data-chapter-cue],[data-action="chapter-analyze"]').forEach(control => { control.disabled = false; });
        }
    }
    function showChapterPreview(trackId,entries,warnings,isCue,revision) {
        const t = track(trackId); if (!t) return;
        const existing = t.chapters || [], mode = existing.length ? 'merge' : 'replace';
        const rows = entries.map((item,index) => `<div class="rp-chapter-preview-row"><label class="rp-chapter-selection"><input type="checkbox" name="chapter-selected" value="${index}" checked aria-label="Импортировать главу ${index+1}"><span>${index+1}</span></label><label class="rp-field">Начало<input name="chapter-start-${index}" value="${chapters.formatTime(item.start)}" maxlength="20" inputmode="text" aria-label="Начало главы ${index+1}"></label><label class="rp-field">Название<input name="chapter-title-${index}" value="${esc(item.title || '')}" maxlength="240" placeholder="Укажите название" aria-label="Название главы ${index+1}"></label><label class="rp-field">Исполнитель<input name="chapter-artist-${index}" value="${esc(item.artist || '')}" maxlength="240" aria-label="Исполнитель главы ${index+1}"></label></div>`).join('');
        openDialog(isCue ? 'Проверка CUE' : 'Возможные переходы', `<p>${esc(t.title)} · Найдено: ${entries.length}</p>${warnings.length ? `<ul class="rp-chapter-warnings">${warnings.map(warning => `<li>${esc(warning)}</li>`).join('')}</ul>` : ''}${!isCue ? '<p>Это предположения по сигналу. Исправьте время и заполните названия выбранных композиций.</p>' : ''}<div class="rp-chapter-import-mode"><label><input type="radio" name="chapter-mode" value="merge" ${mode === 'merge' ? 'checked' : ''}>Добавить к существующим</label><label><input type="radio" name="chapter-mode" value="replace" ${mode === 'replace' ? 'checked' : ''}>Заменить все таймкоды этой записи</label></div><div class="rp-chapter-preview">${rows}</div>`,async form => {
            const selected = form.getAll('chapter-selected').map(Number);
            if (!selected.length) throw new Error('Отметьте хотя бы одну композицию.');
            const selectedEntries = selected.map(index => ({title:String(form.get(`chapter-title-${index}`) || '').trim(),artist:String(form.get(`chapter-artist-${index}`) || '').trim(),start:chapters.parseTime(form.get(`chapter-start-${index}`))}));
            if (selectedEntries.some(item => !item.title)) throw new Error('Укажите название каждой выбранной композиции.');
            await action('chapter.import',{track_id:trackId,chapters:JSON.stringify(selectedEntries),mode:form.get('chapter-mode'),confirmed:'1',revision});
            if (!current) { setQueue(); load(trackId); }
            toast(`Сохранено таймкодов: ${selectedEntries.length}.`);
        },'Разметка сохранится только после нажатия «Сохранить таймкоды».','Сохранить таймкоды');
        dialog.classList.add('rp-dialog--chapters'); syncDialogViewport();
    }
    function createPlaylist() {
        openDialog('Новая лента', field('name','Название плейлиста','',160), async form => { const result = await action('playlist.create',{name:form.get('name')}); browse({type:'playlist',id:result.id}); });
    }
    function browse(view) {
        libraryView = view;
        selected = view.type === 'playlist' ? Number(view.id) : view.type === 'favorites' ? -1 : 0;
        $('[data-search]').value = ''; render(); save();
    }
    function editAlbum(id = 0, preset = {}) {
        const existing = id ? album(id) : null;
        if (id && !existing?.editable) return;
        const value = existing || preset;
        const genrePicker=new window.TapeRoomGenrePicker(data.catalog?.genres || []);
        const order = [...(value.tracks || []), ...data.tracks.map(t=>t.id).filter(trackId=>!(value.tracks || []).includes(trackId))];
        const members = new Set(value.tracks || []);
        const rows = order.map(track).filter(t=>t && !t.shared).map(t=>`<div class="rp-album-member" data-album-member="${t.id}"><label><input type="checkbox" name="album_tracks[]" value="${t.id}" ${members.has(t.id) ? 'checked' : ''}><span><strong>${esc(t.title)}</strong><small>${esc(t.artist || t.filename)}</small></span></label><div>${button('album-order-up','Переместить выше','up',t.id)}${button('album-order-down','Переместить ниже','down',t.id)}</div></div>`).join('');
        const fields = field('title','Название альбома',value.title || '') + field('artist','Артист / исполнитель альбома',value.artist || '') + genrePicker.markup(value.genreId || value.genre_id || 0) + `<label class="rp-field">Дата релиза (если известна)<input type="date" name="release_date" value="${esc(value.release_date || '')}"></label><label class="rp-field">Описание<textarea name="description" maxlength="4000" rows="3">${esc(value.description || '')}</textarea></label><div class="rp-cover-field"><span class="rp-field-label">Обложка альбома</span><label class="rp-cover-picker"><input class="rp-file-input" type="file" name="cover" accept="image/jpeg,image/png,image/webp" aria-label="Выбрать обложку альбома">${previewImage(value.cover,'Обложка альбома')}<span class="rp-cover-picker-info"><span class="rp-cover-picker-button">${icon('upload')}Выбрать обложку</span><span data-cover-filename>JPG, PNG или WebP · до 8 МБ</span></span></label></div><div class="rp-album-members-heading"><h3>Песни и порядок</h3><small>Используются ваши исходные файлы</small></div><label class="rp-search">${icon('search')}<input type="search" data-album-track-search placeholder="Найти песню для альбома" aria-label="Найти песню для альбома"></label><div class="rp-album-members">${rows || '<p>Сначала добавьте музыку в CMS или из Google Drive.</p>'}</div>`;
        openDialog(existing ? 'Редактировать альбом' : 'Новый альбом',fields,async form => {
            const ids = [...dialog.querySelectorAll('[data-album-member] input:checked')].map(input=>Number(input.value));
            const result = await action(`catalog.album.${existing ? 'update' : 'create'}`,{id,title:form.get('title'),artist:form.get('artist'),release_date:form.get('release_date'),description:form.get('description'),genre_id:genrePicker.value(),tracks:JSON.stringify(ids)},form.get('cover'));
            browse({type:'album',id:existing?.id || result.id}); toast(existing ? 'Альбом сохранён.' : 'Альбом добавлен в вашу коллекцию.');
        },'Альбом объединяет песни и не создаёт копий аудио. Публикация доступна зарегистрированному пользователю после одобрения модератором.');
        dialog.classList.add('rp-dialog--album');
        genrePicker.mount(dialog);
        setupAlbumMetadata(value,genrePicker);
        dialog.querySelector('[data-album-track-search]').addEventListener('input',event => {
            const query = event.target.value.trim().toLocaleLowerCase();
            dialog.querySelectorAll('[data-album-member]').forEach(row=>{row.hidden=!row.querySelector('label').textContent.toLocaleLowerCase().includes(query);});
        });
        dialog.querySelector('[name="cover"]').addEventListener('change',event => {
            const file = event.target.files[0]; if (!file) return;
            if (file.size>8*1048576 || !/\.(jpe?g|png|webp)$/i.test(file.name)) { event.target.value=''; resetCoverPreview(value.cover); toast('Выберите JPG, PNG или WebP размером до 8 МБ.',true); return; }
            if (coverPreviewUrl) URL.revokeObjectURL(coverPreviewUrl);
            coverPreviewUrl=URL.createObjectURL(file); const preview=dialog.querySelector('[data-cover-preview]'); preview.removeAttribute('srcset'); preview.removeAttribute('data-default-cover'); preview.src=coverPreviewUrl;
            dialog.querySelector('[data-cover-filename]').textContent=file.name;
        });
    }
    const metadataJobs=new Map();
    async function refreshDriveMetadata(ids,artwork=false) {
        return mutate(async()=>{
            const candidates=[...new Set(ids)].map(track).filter(item=>item && !item.shared && item.source==='drive' && (!item.metadata_loaded || (artwork && !item.cover)));
            if(!candidates.length)return 0;
            let index=0,failed=0;
            const worker=async()=>{
                while(index<candidates.length){
                    const item=candidates[index++],key=`${item.id}:${artwork}`;
                    if(!metadataJobs.has(key)) metadataJobs.set(key,(async()=>{
                        const form=new FormData();form.set('id',item.id);form.set('artwork',artwork?'1':'0');form.set('needCSRFToken',config.csrf);
                        const result=await api('drive/metadata',form);
                        for(const list of [data.tracks,data.catalog?.tracks || []])for(const existing of list)if(existing.id===result.track.id)Object.assign(existing,result.track);
                        trackPool=collections.tracks(data);trackMap=new Map(trackPool.map(t=>[t.id,t]));render();
                    })().finally(()=>metadataJobs.delete(key)));
                    try {await metadataJobs.get(key);}catch(_){failed++;}
                }
            };
            await Promise.all([worker(),worker()]);
            // Metadata can return a submitted album to moderation. Refresh the
            // catalogue in this same write queue before later edits take their turn.
            try {applyState(await api('state'));}catch(_){failed++;}
            return failed;
        });
    }
    function setupAlbumMetadata(value,genrePicker) {
        const holder=element('div','rp-album-metadata'),button=element('button','rp-button','Получить данные и обложку из песен'),note=element('small','','Если в файлах есть теги или обложка, они будут предложены для альбома.');
        button.type='button';holder.append(button,note);dialog.querySelector('.rp-album-members-heading').before(holder);
        button.addEventListener('click',async()=>{
            const revision=dialogRequest,ids=[...dialog.querySelectorAll('[data-album-member] input:checked')].map(input=>Number(input.value));
            if(!ids.length){note.textContent='Сначала выберите песни для альбома.';return;}
            button.disabled=true;note.textContent='Читаем метаданные…';
            const failed=await refreshDriveMetadata(ids,true);
            if(dialogRequest!==revision)return;
            const items=ids.map(track).filter(Boolean),first=items.find(item=>item.album_title)||items[0];
            if(first?.album_title)dialog.querySelector('[name="title"]').value=first.album_title;
            const artist=first?.album_artist || (items.every(item=>item.artist===first?.artist)?first?.artist:'');
            if(artist)dialog.querySelector('[name="artist"]').value=artist;
            if(first?.genre || first?.genre_tag)genrePicker.match(first.genre || first.genre_tag);
            const cover=items.find(item=>item.cover)?.cover;
            if(cover && !dialog.querySelector('[name="cover"]').files.length){window.TapeRoomImages.apply(dialog.querySelector('[data-cover-preview]'),cover,config,theme,'cover',true);dialog.querySelector('[data-cover-filename]').textContent='Обложка из выбранной песни';}
            note.textContent=failed ? 'Часть данных недоступна. Остальные поля можно заполнить вручную.' : 'Доступные данные подставлены. Проверьте их перед сохранением.';button.disabled=false;
        });
    }
    function editArtist(name) {
        if (!config.drive?.canManage || !name) return;
        const profile=(data.catalog?.artistProfiles || []).find(item=>String(item.key || item.name).trim().toLocaleLowerCase()===name.trim().toLocaleLowerCase());
        const preview=imageSources(profile?.image,'artist');
        const fields=`<div class="rp-cover-field"><span class="rp-field-label">${esc(name)}</span><label class="rp-cover-picker"><input class="rp-file-input" type="file" name="cover" accept="image/jpeg,image/png,image/webp" aria-label="Выбрать изображение артиста" required>${previewImage(profile?.image,'Изображение артиста','artist')}<span class="rp-cover-picker-info"><span class="rp-cover-picker-button">${icon('upload')}Выбрать фотографию</span><span data-cover-filename>JPG, PNG или WebP · до 8 МБ</span></span></label></div>${profile?.image ? `<button type="button" class="rp-button rp-button--danger" data-action="artist-delete" data-artist="${esc(name)}">${icon('trash')}Удалить фотографию</button>` : ''}`;
        openDialog('Изображение артиста',fields,async form=>{
            await action('catalog.artist.update',{name},form.get('cover'));
            toast('Изображение артиста сохранено.');
        },'Фотография относится к артисту. Обложки альбомов и песен сохраняются отдельно.');
        dialog.classList.add('rp-dialog--artist');
        const image=dialog.querySelector('[data-cover-preview]');
        if (!preview.src) { image.hidden=true; }
        dialog.querySelector('[name="cover"]').addEventListener('change',event=>{
            const file=event.target.files[0]; if (!file) return;
            if (file.size>8*1048576 || !/\.(jpe?g|png|webp)$/i.test(file.name)) {
                event.target.value=''; resetCoverPreview(profile?.image,'artist'); toast('Выберите JPG, PNG или WebP размером до 8 МБ.',true); return;
            }
            if (coverPreviewUrl) URL.revokeObjectURL(coverPreviewUrl);
            coverPreviewUrl=URL.createObjectURL(file);
            image.removeAttribute('srcset');image.removeAttribute('data-default-artist');image.src=coverPreviewUrl;image.hidden=false;
            dialog.querySelector('[data-cover-filename]').textContent=file.name;
        });
    }
    function editTrack(id) {
        const t = track(id); if (!t || t.shared) return;
        const genrePicker=new window.TapeRoomGenrePicker(data.catalog?.genres || []);
        openDialog('О композиции', field('title','Название',t.title) + field('artist','Исполнитель',t.artist) + genrePicker.markup(t.genreId) + `<div class="rp-cover-field"><span class="rp-field-label">Обложка</span><label class="rp-cover-picker"><input class="rp-file-input" type="file" name="cover" accept="image/jpeg,image/png,image/webp" aria-label="Выбрать обложку">${previewImage(t.cover,'Предварительный просмотр обложки')}<span class="rp-cover-picker-info"><span class="rp-cover-picker-button">${icon('upload')}Выбрать обложку</span><span data-cover-filename>JPG, PNG или WebP · до 8 МБ</span></span></label></div><div class="rp-chapter-editor-actions"><button type="button" class="rp-button" data-action="chapter-add" data-track-id="${t.id}">${icon('plus')}Добавить таймкод</button><button type="button" class="rp-button" data-action="chapter-tools" data-track-id="${t.id}">${icon('list')}CUE и автопоиск</button></div>`, form => action('track.update',{id,title:form.get('title'),artist:form.get('artist'),genre_id:genrePicker.value()},form.get('cover')));
        genrePicker.mount(dialog);
        if(!t.genreId && t.genre_tag)genrePicker.match(t.genre_tag);
        dialog.querySelector('[name="cover"]').addEventListener('change', event => {
            const file = event.target.files[0]; if (!file) return;
            if (file.size > 8 * 1048576 || !/\.(jpe?g|png|webp)$/i.test(file.name)) {
                event.target.value = '';
                if (coverPreviewUrl) { URL.revokeObjectURL(coverPreviewUrl); coverPreviewUrl = undefined; }
                const preview = dialog.querySelector('[data-cover-preview]');
                window.TapeRoomImages.apply(preview,t.cover,config,theme,'cover',true); preview.toggleAttribute('data-default-cover', !t.cover);
                dialog.querySelector('[data-cover-filename]').textContent = 'JPG, PNG или WebP · до 8 МБ';
                toast('Выберите JPG, PNG или WebP размером до 8 МБ.',true); return;
            }
            if (coverPreviewUrl) URL.revokeObjectURL(coverPreviewUrl);
            coverPreviewUrl = URL.createObjectURL(file);
            const preview = dialog.querySelector('[data-cover-preview]');
            preview.removeAttribute('data-default-cover'); preview.removeAttribute('srcset'); preview.src = coverPreviewUrl;
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
        if (dialogSubmitting && dialog.contains(control)) return;
        const name = control.dataset.action, id = Number(control.dataset.id);
        try {
            switch (name) {
                case 'play': if (audio.paused) await play('user',event); else audio.pause(); break;
                case 'prev': previous('user',event); break;
                case 'next': next(false,'user',event); break;
                case 'play-track': setQueue(control.dataset.source === 'popular' ? {type:'popular'} : selectedSource()); if (current === id) { if (audio.paused) await play('user',event); else audio.pause(); } else { load(id); await play('user',event); } break;
                case 'shuffle': shuffle = !shuffle; bag = []; futureBag = []; shuffleCycleStarted = false; history = []; preloader.reset(); updateButtons(); prepareUpcoming(); save(); break;
                case 'repeat': repeat = {off:'all',all:'one',one:'off'}[repeat]; preloader.cancel(); futureBag = []; updateButtons(); prepareUpcoming(); save(); break;
                case 'mute': toggleMute(); break;
                case 'meters': if (!audio.paused && !audio.ended) resumeMeters('user',event); break;
                case 'meter-report': openMeterReport(); break;
                case 'meter-report-download': downloadMeterReport(); break;
                case 'theme': theme = theme === 'dark' ? 'light' : 'dark'; applyTheme(); break;
                case 'select-playlist': browse(descriptorForPlaylist(id)); break;
                case 'catalog-view': browse({type:control.dataset.view}); break;
                case 'catalog-genre': browse({type:'genre',id}); break;
                case 'catalog-album': browse({type:'album',id}); break;
                case 'catalog-artist': browse({type:'artist',artist:control.dataset.artist}); break;
                case 'artist-edit': editArtist(control.dataset.artist); break;
                case 'artist-delete': {
                    const name=control.dataset.artist;
                    openDialog('Удалить фотографию артиста?', '',async()=>{
                        await action('catalog.artist.delete',{name}); toast('Фотография удалена.');
                    },`У «${name}» будет стандартное изображение с микрофоном. Альбомы и песни сохранятся.`,'Удалить',true);break;
                }
                case 'catalog-play-album': {
                    const item=album(id); if (!item?.tracks.length) break;
                    setQueue({type:'album',id}); load(item.tracks[0]); await play('user',event); break;
                }
                case 'catalog-save': control.disabled=true; await action('catalog.album.save',{id,saved:album(id)?.saved ? 0 : 1}); toast(album(id)?.saved ? 'Альбом в вашей коллекции.' : 'Альбом убран из коллекции.'); break;
                case 'catalog-create': editAlbum(); break;
                case 'catalog-edit': editAlbum(id); break;
                case 'catalog-publish': {
                    const item=album(id); if (!item?.editable || !item.canPublish) break;
                    openDialog(item.submitted ? 'Отозвать публикацию?' : 'Отправить альбом на модерацию?', '', () => action('catalog.album.publish',{id,published:item.submitted ? 0 : 1}),item.submitted ? 'Альбом останется в вашей библиотеке. Другие пользователи потеряют доступ к его песням через каталог.' : 'Администратор или создатель проверит альбом. После одобрения он появится в общем каталоге. Файлы Google Drive остаются на вашем диске.',item.submitted ? 'Отозвать' : 'Отправить'); break;
                }
                case 'catalog-delete': {
                    const item=album(id); if (!item?.editable) break;
                    openDialog('Удалить альбом?', '', () => action('catalog.album.delete',{id}),`«${item.title}» исчезнет из каталога и коллекций. Исходные песни останутся в вашей музыкальной библиотеке.`,'Удалить',true);
                    dialog.classList.add('rp-dialog--album-delete');break;
                }
                case 'album-order-up': case 'album-order-down': {
                    const row=control.closest('[data-album-member]'), adjacent=name==='album-order-up' ? row.previousElementSibling : row.nextElementSibling;
                    if (adjacent) name==='album-order-up' ? adjacent.before(row) : adjacent.after(row); break;
                }
                case 'favorite': control.disabled = true; await action(track(id)?.shared ? 'catalog.track.favorite' : 'track.favorite',{id,favorite:track(id).favorite ? 0 : 1}); break;
                case 'upload': $('[data-audio-files]').click(); break;
                case 'create-playlist': createPlaylist(); break;
                case 'close-dialog': if (!dialogSubmitting) dialog.close(); break;
                case 'edit-current': editTrack(current); break;
                case 'edit-track': editTrack(id); break;
                case 'chapter-play': { const item = control.classList.contains('rp-chapter-marker') && event.detail > 0 ? chapterAtPointer(event) : currentChapters().find(item => item.id === id); if (item) jumpChapter(item.start,'user',event); break; }
                case 'chapter-marker-seek': { const item = chapterAtPointer(event); if (item) jumpChapter(item.start,'user',event); break; }
                case 'chapter-add': editChapter(chapterTrack(control)); break;
                case 'chapter-edit': editChapter(current,id); break;
                case 'chapter-tools': chapterTools(chapterTrack(control)); break;
                case 'chapter-current-time': {
                    if (chapterTrack(control) === current) dialog.querySelector('[name="start"]').value = chapters.formatTime(playbackPosition());
                    break;
                }
                case 'chapter-analyze': await requestChapterPreview(chapterTrack(control),'chapters/analyze'); break;
                case 'chapter-delete': {
                    const t = activeTrack(), item = currentChapters().find(item => item.id === id); if (!item) break;
                    openDialog('Удалить таймкод?', '', () => action('chapter.delete',{track_id:t.id,id}), `«${item.title}» будет удалён из разметки. Исходная запись останется целой.`, 'Удалить', true); break;
                }
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
        const request = dialogRequest;
        dialogSubmitting = true; control.disabled = true; $('[data-dialog-error]').hidden = true;
        try { await dialogHandler(new FormData(event.currentTarget)); if (request === dialogRequest) dialog.close(); }
        catch (error) { if (request === dialogRequest) { $('[data-dialog-error]').textContent = error.message; $('[data-dialog-error]').hidden = false; } }
        finally { if (request === dialogRequest) { dialogSubmitting = false; control.disabled = false; } }
    });
    dialog.addEventListener('cancel', event => { if (dialogSubmitting) event.preventDefault(); });
    $('[data-search]').addEventListener('input', render);
    $('[data-chapter-search]')?.addEventListener('input',renderChapters);
    $('[data-chapter-markers]')?.addEventListener('pointermove',event => {
        if (event.pointerType !== 'mouse') return;
        const control = event.target.closest('.rp-chapter-marker'), item = chapterAtPointer(event);
        if (control && item) { const label = `${item.title} · ${chapters.formatTime(item.start)}`; if (control.title !== label) control.title = label; }
    });
    $('[data-chapter-markers]')?.addEventListener('focusin',event => { const label = event.target.getAttribute('aria-label'); if (label) event.target.title = label; });
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
    $('[data-seek]').addEventListener('input', event => { seekActive = true; const fraction = Number(event.target.value) / 1000; rangeFill(event.target,fraction); $('[data-elapsed]').textContent = formatTime((Number.isFinite(audio.duration) ? audio.duration : activeTrack()?.duration || 0) * fraction); });
    $('[data-seek]').addEventListener('change', event => { const duration = Number.isFinite(audio.duration) ? audio.duration : activeTrack()?.duration || 0; seekActive = false; if (duration) seekTo(duration * Number(event.target.value) / 1000); });
    $('[data-audio-files]').addEventListener('change', event => uploadFiles([...event.target.files]));
    audio.addEventListener('loadedmetadata', () => {
        if (restoreTime) {
            if (Number.isFinite(audio.duration)) audio.currentTime = restoreTime >= audio.duration - .25 ? 0 : Math.max(0, restoreTime);
            restoreTime = 0;
        }
        drawProgress();
        const t = activeTrack();
        if (t && !t.shared && Number.isFinite(audio.duration) && Math.abs(t.duration - audio.duration) > 1) {
            t.duration = audio.duration;
            action('track.duration',{id:t.id,duration:audio.duration}).catch(() => {});
        }
    });
    audio.addEventListener('play', () => { setBuffering(audio.readyState < 3); startAnimation(); });
    audio.addEventListener('playing', () => { setBuffering(false); resumeMeters(); startAnimation(); startListening(); });
    audio.addEventListener('waiting', () => { setBuffering(true); });
    // stalled concerns downloading; only waiting proves playback is starved.
    audio.addEventListener('stalled', () => { drawProgress(); });
    audio.addEventListener('canplay', () => { if (audio.readyState >= 3) setBuffering(false); });
    audio.addEventListener('progress', () => { drawProgress(); prepareUpcoming(); });
    audio.addEventListener('seeking', () => { if (listening) { listening.position=audio.currentTime; listening.wall=performance.now(); } preloader.cancel(); if (audio.readyState < 3) setBuffering(true); drawProgress(); });
    audio.addEventListener('pause', () => { preloader.cancel(); setBuffering(false); stopMeters(); save(); });
    audio.addEventListener('timeupdate', () => { sampleListening(); drawProgress(); prepareUpcoming(); if (Date.now() - lastSave > 3000) { save(); lastSave = Date.now(); } });
    audio.addEventListener('seeked', () => { if (listening) { listening.position=audio.currentTime; listening.wall=performance.now(); } meterController.seek(); drawProgress(true); syncMirror(); });
    audio.addEventListener('ratechange', () => { drawProgress(true); syncMirror(); });
    audio.addEventListener('ended', () => { sampleListening(true); listening=null; setBuffering(false); stopMeters(); drawProgress(); next(true); });
    let errorProbe = -1;
    audio.addEventListener('error', async () => {
        if (!current) return; preloader.cancel(); setBuffering(false); stopMeters('native-error'); meterController.nativeFailure(audio.error?.code || 0);
        const t = activeTrack(), version = generation;
        if (t?.source !== 'drive') { toast('Не удалось прочитать аудиофайл. Возможно, браузер не поддерживает его кодек.',true); return; }
        if (errorProbe === version) return; errorProbe = version;
        let message = 'Не удалось загрузить песню из Google Drive. Повторите воспроизведение или проверьте подключение.';
        const controller = new AbortController(), timeout = setTimeout(() => controller.abort(),10000);
        try {
            const response = await fetch(t.url,{credentials:'same-origin',cache:'no-store',headers:{Range:'bytes=0-1'},signal:controller.signal});
            if (version === generation) meterController.nativeFailure(audio.error?.code || 0,response.status);
            if (response.redirected) message = 'Сессия завершена. Обновите страницу и войдите в CMS.';
            else if (!response.ok && response.headers.get('Content-Type')?.startsWith('text/plain')) message = (await response.text()).slice(0,500) || message;
            else if (response.status === 401) message = 'Сессия завершена. Обновите страницу и войдите в CMS.';
            else if (response.ok && [3,4].includes(audio.error?.code)) message = 'Google Drive отдал начало файла, но воспроизведение прервалось. Возможны неполные данные или неподдерживаемый формат.';
            else if (response.ok) message = 'Соединение с Google Drive прервалось. Нажмите воспроизведение, чтобы повторить.';
        } catch (_) {} finally { clearTimeout(timeout); }
        if (version === generation) toast(message,true);
    });
    $('[data-cover]').addEventListener('error', event => {
        event.target.closest('.rp-cover-button').classList.remove('has-custom-cover');
        event.target.removeAttribute('srcset');event.target.removeAttribute('src');
    });
    app.addEventListener('error',event=>{
        const image=event.target;
        if (!image.matches?.('[data-library-image],[data-theme-cover]')) return;
        if (image.dataset.libraryImage==='artist') {
            // A removed or unavailable photo falls back to the owner's artwork,
            // then to the neutral mic only if that file also fails.
            if (!image.hasAttribute('data-default-artist') && defaultArtist()) {
                image.setAttribute('data-default-artist','');
                window.TapeRoomImages.apply(image,'',config,theme,'artist',image.hasAttribute('data-compact-image')); return;
            }
            if (image.dataset.imageFallback!=='original' && defaultArtist() && image.getAttribute('src')!==defaultArtist()) {
                image.dataset.imageFallback='original';image.removeAttribute('srcset');image.src=defaultArtist();return;
            }
            image.hidden=true;const placeholder=image.parentElement.querySelector('.rp-artist-placeholder');if(placeholder)placeholder.hidden=false;
        } else if (!image.hasAttribute('data-image-fallback')) {
            image.setAttribute('data-image-fallback','');image.removeAttribute('srcset');
            if (image.hasAttribute('data-library-image')) image.setAttribute('data-default-cover','');
            image.src=image.matches('[data-theme-cover]') ? config.defaultCovers[image.classList.contains('rp-theme-cover--dark') ? 'dark' : 'light'] : defaultCover();
        }
    },true);
    let dragDepth = 0;
    window.addEventListener('dragenter', event => { if ([...(event.dataTransfer?.types || [])].includes('Files')) { event.preventDefault(); dragDepth++; $('[data-drop-overlay]').hidden = false; } });
    window.addEventListener('dragover', event => { if ([...(event.dataTransfer?.types || [])].includes('Files')) event.preventDefault(); });
    window.addEventListener('dragleave', event => { if (--dragDepth <= 0) { dragDepth = 0; $('[data-drop-overlay]').hidden = true; } });
    window.addEventListener('drop', event => { event.preventDefault(); dragDepth = 0; $('[data-drop-overlay]').hidden = true; uploadFiles([...(event.dataTransfer?.files || [])]); });
    document.addEventListener('keydown', event => {
        if (dialog.open || /^(INPUT|TEXTAREA|SELECT|BUTTON)$/.test(event.target.tagName) || event.target.isContentEditable || event.altKey || event.ctrlKey || event.metaKey) return;
        if (event.code === 'Space') { event.preventDefault(); if (audio.paused) play('user',event); else audio.pause(); }
        if (['ArrowLeft','ArrowRight'].includes(event.code)) {
            event.preventDefault();
            if (event.shiftKey) event.code === 'ArrowLeft' ? previous('user',event) : next(false,'user',event);
            else seekTo(playbackPosition() + (event.code === 'ArrowLeft' ? -5 : 5));
        }
    });
    if ('mediaSession' in navigator) {
        // iOS uses the same lock-screen slots for track skipping and timed
        // seeking. Disable timed seek actions so this music player shows tracks.
        const handlers = {
            seekbackward:null, seekforward:null,
            play:() => play('system'), pause:() => audio.pause(), previoustrack:() => previous('system'), nexttrack:() => next(false,'system'),
            seekto:event => seekTo(event.seekTime)
        };
        for (const [name,handler] of Object.entries(handlers)) { try { navigator.mediaSession.setActionHandler(name,handler); } catch (_) {} }
    }
    window.addEventListener('pagehide', () => {
        save(); cancelQuality(); transport.reset(); visuals.reels.forEach(reel => reel.motion?.pause());
        cancelAnimationFrame(frameId); frameId = 0; frameTime = 0;
    });
    document.addEventListener('visibilitychange', () => {
        save(); syncReels();
        if (document.hidden) {
            preloader.cancel(); cancelAnimationFrame(frameId); frameId = 0; frameTime = 0; stopMeters('hidden');
        } else {
            drawProgress(); updateButtons();
            if (!audio.paused) { configureAudioSession(); resumeMeters(); startAnimation(); }
        }
    });
    reducedMotion.addEventListener('change', () => { syncReels(); drawTape(); });
    window.addEventListener('offline', () => { preloader.connectivity(false); meterController.network(false); });
    window.addEventListener('online', () => { preloader.connectivity(true); meterController.network(true); prepareUpcoming(); if (!audio.paused) resumeMeters(); });
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
    async function driveDialogStatus(request) {
        try {
            const status = await api('drive/status');
            return request === dialogRequest ? status : null;
        } catch (error) {
            if (request === dialogRequest) throw error;
            return null;
        }
    }
    async function driveSettings() {
        const status = await driveDialogStatus(++dialogRequest); if (!status) return;
        config.drive = status.drive;
        if (!status.drive.canManage) { toast('Настройки доступны только создателю сайта.',true); return; }
        openDialog('Настройки плеера', '<div class="rp-settings-section"><h3>Google Drive</h3><p>Подключение для всего сайта. Каждый администратор входит в собственный Google-аккаунт.</p></div>' + field('client_id','OAuth Client ID',status.drive.clientId,500) + '<label class="rp-field">OAuth Client Secret<input type="password" name="client_secret" autocomplete="off" placeholder="' + (status.drive.configured ? 'Оставьте пустым, чтобы сохранить текущий' : 'Введите секрет клиента') + '"></label>' + driveSetupHelp(status.drive.callback), async form => {
            const result = await action('drive.settings',{client_id:form.get('client_id'),client_secret:form.get('client_secret')}); config.drive = result.drive;
            toast('Настройки сайта сохранены. Теперь можно войти в Google Drive.');
        }, 'Эти настройки доступны только создателю сайта и сохраняются один раз для всех пользователей.');
    }
    async function openDrive() {
        const status = await driveDialogStatus(++dialogRequest); if (!status) return;
        config.drive = status.drive;
        if (!status.drive.connected) {
            openDialog('Музыка из Google Drive', '<button type="button" class="rp-button rp-google-button" data-action="drive-connect"' + (!status.drive.configured ? ' disabled' : '') + '><span class="rp-google-mark" aria-hidden="true">G</span>Войти в Google Drive</button>' + (!status.drive.configured ? '<p>' + (status.drive.canManage ? 'Откройте шестерёнку «Настройки плеера» и настройте подключение Google для сайта.' : 'Создатель сайта ещё не настроил подключение Google Drive.') + '</p>' : ''), async () => {}, 'Ваша музыка доступна только вашему аккаунту в плеере. Выберите свой Google-аккаунт и разрешите чтение Drive.');
            $('[data-dialog-submit]').hidden = true;
            return;
        }
        driveSelection = new Set(); drivePath = [{id:'root',name:'Мой Drive'}];
        openDialog('Добавить музыку из Drive', '<div class="rp-drive-location"><nav class="rp-drive-path" data-drive-path aria-label="Папки Google Drive"></nav><button type="button" class="rp-drive-settings-link" data-action="drive-all">Вся музыка</button></div><div class="rp-drive-heading"><span>Папки и музыка</span><small data-drive-count>Загрузка…</small></div><button type="button" class="rp-button rp-drive-add-folder" data-action="drive-add-folder" hidden>Добавить эту папку целиком</button><div class="rp-drive-selection"><span data-drive-selected>Выбрано: 0</span><div class="rp-drive-selection-buttons"><button type="button" class="rp-drive-settings-link" data-action="drive-clear" hidden>Сбросить</button><button type="button" class="rp-button" data-action="drive-select-all" disabled>Выбрать показанные</button></div></div><div class="rp-drive-files" data-drive-files aria-live="polite">Загружаем папки и музыку…</div><label class="rp-field rp-drive-link-field">Или вставьте ссылку на песню / папку<input name="link" type="url" placeholder="https://drive.google.com/…" inputmode="url" autocomplete="off"><small>Ссылка на папку добавит все поддерживаемые песни из самой папки.</small></label><label class="rp-album-import-choice"><input type="checkbox" name="as_album" value="1"><span>Собрать добавленные песни в альбом</span></label><div class="rp-drive-account-actions"><button type="button" class="rp-drive-settings-link" data-action="drive-disconnect">Отключить аккаунт</button></div>', async form => {
            if (!driveSelection.size && !form.get('link').trim()) throw new Error('Выберите хотя бы один трек или вставьте ссылку.');
            const result = await action('drive.import',{ids:[...driveSelection],link:form.get('link'),playlist_id:selected > 0 ? selected : 0});
            if (selected === -1) browse({type:'songs'});
            toast(`Добавлено треков из Google Drive: ${result.imported}`);
            const albumPreset={title:drivePath.length>1 ? drivePath.at(-1).name : '',tracks:result.imported_ids || []};
            refreshDriveMetadata(result.imported_ids || []).catch(()=>{});
            if (form.get('as_album') === '1' && result.imported_ids?.length) setTimeout(()=>editAlbum(0,albumPreset),0);
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
            if ($('[data-drive-files]') !== list || !dialog.open || browseGeneration !== driveBrowseGeneration) return;
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
    dialog.addEventListener('close', () => { unlockDialogScroll(); dialogRequest++; driveBrowseGeneration++; chapterJob?.abort(); chapterJob = null; $('[data-dialog-submit]').hidden = false; if (coverPreviewUrl) { URL.revokeObjectURL(coverPreviewUrl); coverPreviewUrl = undefined; } });
    app.querySelectorAll('[data-theme-cover]').forEach(image=>{ image.decode?.().catch(()=>{}); });
    // These small theme placeholders are reused across cards and editors.
    // Warming both variants avoids a blank artist card on its first theme swap.
    const artistArtwork = ['light','dark'].filter(value=>config.defaultArtists?.[value]).map(value=>{
        const image=new Image();image.decoding='async';image.fetchPriority='low';
        window.TapeRoomImages.apply(image,'',config,value,'artist');image.decode?.().catch(()=>{});return image;
    });
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
