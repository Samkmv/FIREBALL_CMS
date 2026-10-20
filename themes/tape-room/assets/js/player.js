(() => {
    'use strict';
    const app = document.querySelector('[data-player]');
    if (!app) return;
    const config = JSON.parse(document.querySelector('[data-player-config]').textContent);
    const $ = selector => app.querySelector(selector);
    const audio = $('[data-audio]');
    const dialog = $('[data-dialog]'), dialogShell=$('[data-player-modal]');
    const dialogModal=bootstrap.Modal.getOrCreateInstance(dialogShell);
    const queuePanel=$('[data-playback-queue]'), queueToggle=$('[data-action="playback-queue"]');
    const queueDrawer=queuePanel ? bootstrap.Offcanvas.getOrCreateInstance(queuePanel) : null;
    const dialogOpen=()=>dialogShell.classList.contains('show');
    const closeDialog=()=>dialogModal.hide();
    let dialogReturnFocus=null;
    const sortDropdown=bootstrap.Dropdown.getOrCreateInstance($('[data-sort-trigger]'));
    $('[data-sort]').addEventListener('hidden.bs.dropdown',()=>{$('[data-sort-options]').hidden=true;setIcon($('[data-sort-trigger]'),'down');});
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
    let data = config.state, serverState = config.state, browserLibrary = {tracks:[],albums:[]}, browserRestored = false, sourceJob = null, saved = {};
    try { saved = JSON.parse(localStorage.getItem(config.storageKey) || '{}'); } catch (_) {}
    if (!saved || typeof saved !== 'object' || Array.isArray(saved)) saved = {};
    if(config.playback?.state)saved={...saved,...config.playback.state,time:window.TapeRoomPlaybackSync.position(config.playback)};
    let playbackSync=null, remotePlayback=config.playback?.state ? {...config.playback,received:performance.now()} : null;
    app.addEventListener('tape-room:csrf',event=>{
        if(typeof event.detail?.token!=='string'||!event.detail.token)return;
        config.csrf=event.detail.token;if(playbackSync)playbackSync.csrf=config.csrf;
    });
    app.addEventListener('tape-room:navigate',event=>{
        const url=new URL(event.detail.url,location.href),albumId=Number(url.searchParams.get('album')),view=new URLSearchParams(url.hash.slice(1)).get('room');
        if(albumId>0)browse({type:'album',id:albumId});
        else if(['overview','albums','artists','genres','new','popular','mine'].includes(view))browse({type:view});
    });
    let audibleVolume = Number(saved.audibleVolume) > 0 ? Math.min(1, Number(saved.audibleVolume)) : Number(saved.volume) > 0 ? Math.min(1, Number(saved.volume)) : .8;
    let selected = Number(saved.playlist) || 0;
    let current = 0, queue = [], queueName = '', queuePlaylist = Number(saved.queuePlaylist) || 0, repeat = ['off', 'all', 'one'].includes(saved.repeat) ? saved.repeat : 'off';
    let librarySort = ['new','old'].includes(saved.librarySort) ? saved.librarySort : 'new';
    let playlistSort = ['playlist','new','old'].includes(saved.playlistSort) ? saved.playlistSort : 'playlist';
    let queueSort = ['playlist','new','old'].includes(saved.queueSort) ? saved.queueSort : (queuePlaylist > 0 ? 'playlist' : librarySort);
    const collections = window.TapeRoomCollections;
    let libraryView = saved.libraryView && ['overview','albums','artists','new','popular','genres','genre','mine','songs','favorites','playlist','album','artist'].includes(saved.libraryView.type) ? saved.libraryView : {type:'overview'};
    if(config.authenticated===false && !['overview','albums','artists','genres','genre','new','popular','album','artist'].includes(libraryView.type))libraryView={type:'overview'};
    let queueSource = collections.descriptor(saved.queueSource, {type:queuePlaylist === -1 ? 'favorites' : queuePlaylist > 0 ? 'playlist' : 'songs', ...(queuePlaylist > 0 ? {id:queuePlaylist} : {})});
    let trackPool = collections.tracks(data), trackMap = new Map(trackPool.map(t => [t.id,t]));
    let shuffle = Boolean(saved.shuffle), bag = [], history = [], seekActive = false, restoreTime = 0;
    let toastTimer, frameId = 0, frameTime = 0, frameInProgress = false, angles = [0, 0], graph, uploading = false, dialogHandler, dialogSubmitting = false;
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
    const icon = name => `<i class="rp-icon ci-${window.TapeRoomIconMap[name] || 'music'}" data-icon="${name}" aria-hidden="true"></i>`;
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
    const remotePosition = () => Math.min(playbackDuration()||Infinity,window.TapeRoomPlaybackSync.position(remotePlayback,(performance.now()-remotePlayback.received)/1000));
    const playbackPosition = () => remotePlayback?.state.current===current ? remotePosition() : audio.readyState === 0 && restoreTime ? restoreTime : audio.currentTime || 0;
    const remotePlaying = () => remotePlayback?.playing && (remotePlayback.serverTime-remotePlayback.updatedAt+performance.now()-remotePlayback.received)<30000;
    function claimPlayback() {
        if(remotePlayback){const time=playbackPosition();remotePlayback=null;if(current){if(audio.readyState===0)restoreTime=time;else audio.currentTime=time;}}
        if(!activeTrack()?.browserLocal)playbackSync?.claim();
    }
    let chapterKey = '', chapterIndex = -2, chapterDuration = -1, chapterRows = [], chapterMarkers = [];
    let chapterJob = null;
    const qualityCache = new Map();
    let qualityJob = null;
    const playbackDuration = () => window.TapeRoomQuality.duration(activeTrack() || {},qualityCache.get(current),audio.readyState > 0 ? audio.duration : 0);
    const descriptorForPlaylist = id => ({type:id === -1 ? 'favorites' : id > 0 ? 'playlist' : 'songs', ...(id > 0 ? {id} : {})});
    const collectionName = id => collections.name(data,descriptorForPlaylist(id));
    const collectionTracks = id => collections.collection(data,descriptorForPlaylist(id),'playlist');
    const sortedTracks = (tracks, mode) => mode === 'playlist' ? [...tracks] : [...tracks].sort((a,b) => mode === 'old' ? a.id - b.id : b.id - a.id);
    const selectedSort = () => selected > 0 ? playlistSort : librarySort;
    const selectedSource = () => collections.descriptor(libraryView,descriptorForPlaylist(selected));
    const selectedTracks = () => collections.collection(data,selectedSource(),selectedSort());
    const playbackSource = () => collections.playbackSource(data,libraryView,selectedSource(),selectedSort());
    const element = (tag, className, text) => { const node = document.createElement(tag); node.className = className; if (text !== undefined) node.textContent = text; return node; };
    const setIcon = (node, name) => { const item=node.querySelector('.rp-icon');if(!item)return;for(const value of [...item.classList])if(value.startsWith('ci-'))item.classList.remove(value);item.classList.add(`ci-${window.TapeRoomIconMap[name] || 'music'}`);item.dataset.icon=name; };
    const rangeFill = (node, fraction) => node.style.setProperty('--fill', `${Math.max(0, Math.min(1, fraction)) * 100}%`);
    const catalogUI = new window.TapeRoomCatalogUI({app,icon,escape:esc,defaultCover,defaultArtist,imageSources,renderTrack:(item,options)=>createTrackRow(track(item.id)||item,options).outerHTML});

    let recoveryReady=false;
    const recovery=new window.TapeRoomMediaRecovery({
        retry:async({position,valid})=>{
            if(!valid())return;
            if(recovery.maintenance){
                const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),10000);
                try{
                    const probe=await fetch(activeTrack().url,{credentials:'same-origin',cache:'no-store',headers:{Range:'bytes=0-1'},signal:controller.signal});
                    await probe.body?.cancel();
                    if(probe.status===503 && probe.headers.get('X-Fireball-Maintenance')==='1'){
                        const error=new Error('Site maintenance');error.maintenance=true;error.retryAfter=probe.headers.get('Retry-After');throw error;
                    }
                    if(!valid())return;recovery.maintenance=false;
                }finally{clearTimeout(timeout);}
            }
            const url=new URL(activeTrack().url,location.href);url.searchParams.set('_tr_retry',String(Date.now()));
            preloader.cancel();stopMeters('reconnect');restoreTime=position;errorProbe=-1;
            audio.src=url.href;audio.load();if(!valid())return;
            configureAudioSession();await audio.play();resumeMeters();
        },
        onState:()=>{if(recoveryReady)updateButtons();},
        onStop:(gesture,reason)=>toast(reason==='maintenance'?'Сайт ещё недоступен после обновления. Нажмите воспроизведение, чтобы повторить.':gesture?'Браузер ожидает нажатия. Нажмите воспроизведение, чтобы продолжить.':'Не удалось восстановить соединение после трёх попыток. Нажмите воспроизведение, чтобы повторить.',true)
    });
    recoveryReady=true;
    function pausePlayback(){recovery.pause();audio.pause();updateButtons();}

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
        const node=$('[data-toast]');$('[data-toast-text]').textContent=message;
        node.classList.toggle('is-error',error);node.setAttribute('aria-live',error?'assertive':'polite');
        bootstrap.Toast.getOrCreateInstance(node,{delay:5000}).show();
    }
    function save() {
        try { localStorage.setItem(config.storageKey, JSON.stringify({playlist:selected, libraryView, queueSource, current, queue, queueName, queuePlaylist, queueSort, librarySort, playlistSort, time:playbackPosition(), volume:audio.volume, audibleVolume, muted:audio.muted, shuffle, repeat})); } catch (_) {}
        playbackSync?.changed();
    }
    function closeSort() {
        const options = $('[data-sort-options]'), restoreFocus = options.contains(document.activeElement);
        sortDropdown.hide();options.hidden = true;
        $('[data-sort-trigger]').setAttribute('aria-expanded', 'false');
        setIcon($('[data-sort-trigger]'),'down');
        if (restoreFocus) $('[data-sort-trigger]').focus();
    }
    function openSort() {
        $('[data-sort-options]').hidden = false;sortDropdown.show();
        $('[data-sort-trigger]').setAttribute('aria-expanded', 'true');
        setIcon($('[data-sort-trigger]'),'down');
        $('[data-sort-options] [aria-selected="true"]')?.focus();
    }
    function renderSort() {
        closeSort();
        const modes = [...(selected > 0 ? [['playlist','Порядок в ленте']] : []), ['new','Сначала новые'], ['old','Сначала старые']];
        const options = $('[data-sort-options]'); options.replaceChildren();
        for (const [value,label] of modes) {
            const option = element('button','rp-sort-option dropdown-item');
            option.append(element('span','',label));
            if(value===selectedSort())option.insertAdjacentHTML('beforeend',icon('check'));
            option.type = 'button'; option.tabIndex = -1; option.dataset.sortMode = value;
            option.setAttribute('role','option'); option.setAttribute('aria-selected', String(value === selectedSort()));
            options.append(option);
        }
        const label = modes.find(([value]) => value === selectedSort())[1];
        $('[data-sort-label]').textContent = label;
        $('[data-sort-trigger]').setAttribute('aria-label', `Сортировка музыки: ${label}`);
    }
    function createTrackRow(t,{index=0,source='',genre='',canReorder=false,playlistId=0,viewType='',listLength=0}={}) {
        const row = element('article', `rp-track${t.id === current ? ' is-current' : ''}`);
        row.dataset.trackId = t.id;
        const play = element('button', 'rp-track-play'); play.type = 'button'; play.dataset.action = 'play-track'; play.dataset.id = t.id;
        if(source)play.dataset.queueSource=source;
        play.setAttribute('aria-label', `Воспроизвести ${t.title}`);
        play.innerHTML = `<span class="rp-track-number">${String(index + 1).padStart(2, '0')}</span>${icon(t.id === current && !audio.paused ? 'pause' : 'play')}`;
        const cover = element('img', 'rp-track-cover'); cover.alt = ''; cover.loading = 'lazy'; cover.decoding = 'async'; cover.width=48; cover.height=48;
        cover.dataset.libraryImage='cover'; cover.dataset.compactImage='';
        window.TapeRoomImages.apply(cover,t.cover,config,theme,'cover',true);
        if (!t.cover) cover.dataset.defaultCover = '';
        const info = element('div', 'rp-track-info');
        info.append(element('span', 'rp-track-title', t.title), element('span', 'rp-track-artist', `${t.artist || 'Неизвестный исполнитель'}${t.source === 'drive' ? ' · Google Drive' : t.browserLocal ? ' · Torrent' : ''}`));
        const labelGenre=t.genre || genre;
        if(labelGenre){const label=element('span','rp-track-genre',labelGenre);label.title=`Жанр: ${labelGenre}`;info.append(label);}
        if(t.shared && t.uploader?.url && t.uploader.name){
            const uploader=element('a','rp-track-uploader',`Добавил: ${t.uploader.name}`);
            uploader.href=t.uploader.url;info.append(uploader);
        }
        const actions = element('div', 'rp-track-actions');
        if (canReorder) {
            const reorder = element('div', 'rp-track-reorder');
            reorder.setAttribute('role', 'group'); reorder.setAttribute('aria-label', 'Порядок песни');
            reorder.innerHTML = button('move-up', 'Переместить выше', 'up', t.id, 'rp-reorder') + button('move-down', 'Переместить ниже', 'down', t.id, 'rp-reorder');
            actions.append(reorder);
        }
        const tools = element('div', 'rp-track-tools');
        tools.innerHTML = button('favorite', t.favorite ? 'Убрать из избранного' : 'Добавить в избранное', 'bookmark', t.id, `rp-favorite${t.favorite ? ' is-active' : ''}`) + button('share-track','Поделиться песней','share',t.id) + button('add-to-playlist', 'Добавить в плейлист', 'plus', t.id) + (!t.shared ? button('edit-track', 'Изменить трек', 'edit', t.id) : '') + (playlistId > 0 ? button('remove-track','Убрать из плейлиста','trash',t.id) : !t.shared && viewType === 'songs' ? button('delete-track','Удалить из библиотеки','trash',t.id) : '');
        actions.append(tools);
        actions.querySelector('[data-action="favorite"]').setAttribute('aria-pressed', String(t.favorite));
        if(t.browserLocal)actions.querySelector('[data-action="add-to-playlist"]')?.remove();
        if (canReorder) {
            actions.querySelector('[data-action="move-up"]').disabled = index === 0;
            actions.querySelector('[data-action="move-down"]').disabled = index === listLength - 1;
    }
        row.append(play, cover, info, element('span', 'rp-track-duration', t.duration ? formatTime(t.duration) : '—'), actions);
        return row;
    }
    function syncPlaylistNavigation() {
        const visible=app.dataset.sitePage!=='1'&&['songs','favorites','playlist'].includes(libraryView.type);
        for(const button of app.querySelectorAll('[data-playlists] [data-action="select-playlist"]')){
            const active=visible&&Number(button.dataset.id)===selected;
            button.classList.toggle('is-selected',active);
            button.setAttribute('aria-current',active?'page':'false');
        }
    }
    document.addEventListener('tape-room:page',syncPlaylistNavigation);
    function render() {
        for(const item of trackPool)if(qualityCache.has(item.id))item.quality=qualityCache.get(item.id);
        if (selected > 0 && !playlist(selected)) selected = 0;
        if ((libraryView.type === 'album' && !album(libraryView.id)) || (libraryView.type === 'playlist' && !playlist(libraryView.id))) libraryView = {type:'mine'};
        if ((queueSource.type === 'album' && !album(queueSource.id)) || (queueSource.type === 'playlist' && !playlist(queueSource.id))) { queueSource = {type:'songs'}; queuePlaylist = 0; queueSort = librarySort; }
        queue = collections.collection(data,queueSource,queueSort).map(t => t.id);
        queueName = collections.name(data,queueSource);
        bag = bag.filter(id => queue.includes(id)); futureBag = futureBag.filter(id => queue.includes(id)); history = history.filter(id => Boolean(track(id)));
        if (current && !activeTrack()) { window.TapeRoomTorrents?.release();audio.pause(); audio.removeAttribute('src'); audio.load(); current = 0; updateNow(); }
        const nav = $('[data-playlists]'); nav.replaceChildren();
        for (const item of [{id:0, name:'Все мои песни', tracks:collections.myTracks(data)}, {id:-1, name:'Избранное', tracks:collectionTracks(-1)}, ...data.playlists]) {
            const active = app.dataset.sitePage!=='1' && ['songs','favorites','playlist'].includes(libraryView.type) && item.id === selected;
            const node = element('button', `rp-playlist nav-link${active ? ' is-selected' : ''}`);
            node.type = 'button'; node.dataset.action = 'select-playlist'; node.dataset.id = item.id;
            node.setAttribute('aria-current', active ? 'page' : 'false');
            node.innerHTML = icon(item.id === -1 ? 'bookmark' : item.id ? 'list' : 'music');
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
            fragment.append(createTrackRow(t,{index:list.indexOf(t),genre:libraryView.type==='album'?album(libraryView.id)?.genre:libraryView.type==='genre'?collections.name(data,selectedSource()):'',canReorder:selected>0&&playlistSort==='playlist',playlistId:selected,viewType:libraryView.type,listLength:list.length}));
        }
        window.TapeRoomUi?.decorate(fragment);
        window.TapeRoomImages.patch(rows,fragment);
        $('[data-empty]').hidden = !catalog.listMode || visible.length > 0;
        $('[data-empty-title]').textContent = query ? 'Ничего не найдено' : catalog.emptyTitle || (selected === -1 ? 'Любимые песни будут здесь' : selected > 0 ? 'Эта лента пока пустая' : 'Здесь начинается ваша коллекция');
        $('[data-empty-description]').textContent = query ? 'Попробуйте другое название или имя исполнителя.' : catalog.emptyDescription || (selected === -1 ? 'Нажмите закладку рядом с песней, чтобы добавить её в избранное.' : selected > 0 ? 'Загрузите музыку сюда или добавьте треки из общей библиотеки.' : 'Перетащите аудиофайлы сюда или добавьте их кнопкой выше.');
        $('[data-action="play"]').disabled = !current && collections.collection(data,playbackSource(),selectedSort()).length === 0;
        $('[data-action="prev"]').disabled = !current;
        $('[data-action="next"]').disabled = !current;
        $('[data-action="edit-current"]').disabled = !current || Boolean(activeTrack()?.shared);
        updateNow(); updateButtons(); renderChapters(); renderPlaybackQueue(); prepareUpcoming();
    }
    function playbackQueue() {
        const source = current || queue.length ? queueSource : playbackSource();
        const items = current || queue.length ? queue.map(track).filter(Boolean) : collections.collection(data,source,selectedSort());
        if (!items.length && activeTrack()) items.push(activeTrack());
        return {source,items,name:(current || queue.length ? queueName : '') || collections.name(data,source)};
    }
    function syncPlaybackQueue() {
        if (!queuePanel) return;
        const playing = (!audio.paused && !audio.ended) || recovery.pending;
        queuePanel.querySelectorAll('[data-action="queue-play"]').forEach(control => {
            const item=track(control.dataset.id), active=Number(control.dataset.id)===current;
            control.classList.toggle('is-current',active);
            control.setAttribute('aria-current',String(active));
            control.setAttribute('aria-pressed',String(active && playing));
            control.setAttribute('aria-label',`${active && playing ? 'Приостановить' : 'Воспроизвести'} ${item?.title || ''}`);
            setIcon(control,active && playing ? 'pause' : 'play');
        });
    }
    function renderPlaybackQueue(force = false) {
        if (!queuePanel || (!force && !queuePanel.classList.contains('show'))) return;
        const entry=playbackQueue(), fragment=document.createDocumentFragment();
        $('[data-queue-name]').textContent=entry.name;
        $('[data-queue-summary]').textContent=`${entry.items.length} ${entry.items.length % 10 === 1 && entry.items.length % 100 !== 11 ? 'песня' : entry.items.length % 10 >= 2 && entry.items.length % 10 <= 4 && !(entry.items.length % 100 >= 12 && entry.items.length % 100 <= 14) ? 'песни' : 'песен'}${entry.items.some(item=>item.duration) ? ' · '+formatTime(entry.items.reduce((sum,item)=>sum+(Number(item.duration)||0),0)) : ''}`;
        for (const item of entry.items) {
            const row=element('li',''); row.dataset.trackId=item.id;
            const control=element('button','btn tr-queue-song'); control.type='button'; control.dataset.action='queue-play'; control.dataset.id=item.id;
            const mark=element('span','tr-queue-play'); mark.innerHTML=icon('play');
            const image=element('img','tr-queue-cover'); image.alt=''; image.width=image.height=36; image.loading='lazy'; image.decoding='async';
            image.dataset.libraryImage='cover'; image.dataset.compactImage='';
            const cover=item.cover || (entry.source.type==='album' ? album(entry.source.id)?.cover : '') || '';
            if (!cover) image.dataset.defaultCover='';
            window.TapeRoomImages.apply(image,cover,config,theme,'cover',true);
            const info=element('span','tr-queue-info');
            info.append(element('strong','',item.title),element('small','',item.artist || 'Неизвестный исполнитель'));
            control.append(mark,image,info,element('span','tr-queue-duration',item.duration ? formatTime(item.duration) : '—'));
            row.append(control); fragment.append(row);
        }
        window.TapeRoomImages.patch($('[data-queue-list]'),fragment);
        $('[data-queue-empty]').hidden=entry.items.length>0;
        syncPlaybackQueue();
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
    function syncDuration() {
        const t=activeTrack();if(!t)return;
        const duration=window.TapeRoomQuality.durationToSave(t,qualityCache.get(t.id),audio.readyState > 0 ? audio.duration : 0);
        if(!duration || Math.abs((Number(t.duration)||0)-duration)<(window.TapeRoomQuality.format(t)==='FLAC'?.05:1))return;
        for(const item of [...trackPool,...data.tracks,...(data.catalog?.tracks || [])])if(Number(item.id)===t.id)item.duration=duration;
        render();
        if(!t.shared && !t.browserLocal)action('track.duration',{id:t.id,duration}).catch(()=>{});
    }
    async function requestQuality() {
        cancelQuality();
        const t = activeTrack();
        if (!t || t.browserLocal) return;
        if (qualityCache.has(t.id)) { syncDuration();return; }
        const version = generation, id = t.id, controller = new AbortController();
        const job = {controller,timer:setTimeout(() => controller.abort(),9000)};
        qualityJob = job;
        try {
            const result = await api(`${t.shared ? 'catalog/' : ''}quality/${id}`,undefined,controller.signal);
            if (generation !== version || current !== id || controller.signal.aborted) return;
            qualityCache.set(id,result.quality || {});const item=track(id);if(item)item.quality=result.quality;
            if (qualityCache.size > 64) qualityCache.delete(qualityCache.keys().next().value);
            syncDuration();drawProgress(true);
            if(libraryView.type==='album'){const entry=album(libraryView.id),items=(entry?.tracks||[]).map(track).filter(Boolean),info=app.querySelector('.rp-album-detail-info'),badge=window.TapeRoomQuality.albumBadge(items);if(info&&badge&&!info.querySelector('.tr-lossless-badge'))info.querySelector('.rp-album-detail-meta')?.insertAdjacentHTML('afterend',badge);}
        } catch (_) { /* Optional technical metadata never gates music or opens an error dialog. */ }
        finally { clearTimeout(job.timer); if (qualityJob === job) qualityJob = null; }
    }
    function updateNow() {
        const t = activeTrack();
        drawAudioQuality();
        $('[data-title]').textContent = t?.title || 'Ваша первая лента';
        const artist=$('[data-artist]'), artistText=artist.querySelector('[data-tr-marquee-text]') || artist;
        const artistName=t?.artist || (t ? 'НЕИЗВЕСТНЫЙ ИСПОЛНИТЕЛЬ' : 'ДОБАВЬТЕ ЛЮБИМУЮ МУЗЫКУ');
        if (artistText.textContent!==artistName) artistText.textContent=artistName;
        const albumArtwork = queueSource.type === 'album' ? album(queueSource.id)?.cover : '';
        const customArtwork = t?.cover || albumArtwork || '';
        if (customArtwork) window.TapeRoomImages.apply($('[data-cover]'),customArtwork,config,theme);
        else { $('[data-cover]').removeAttribute('srcset'); $('[data-cover]').removeAttribute('src'); }
        $('[data-cover]').closest('.rp-cover-button').classList.toggle('has-custom-cover',Boolean(customArtwork));
        $('[data-player-source]').textContent = current ? (queueName || 'Вся музыка') + (t?.source === 'drive' ? ' · GOOGLE DRIVE' : ' · STEREO') : 'ВАША МУЗЫКА. ВАШ РИТМ.';
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
        const playing = (!audio.paused && !audio.ended)||recovery.pending;
        document.body.classList.toggle('is-playing', playing && !loading);
        document.body.classList.toggle('is-buffering', bufferVisible);
        const play = $('[data-action="play"]'); setIcon(play, playing ? 'pause' : 'play');
        play.setAttribute('aria-busy', String(loading||recovery.pending));
        play.setAttribute('aria-label', playing ? 'Пауза' : 'Воспроизвести'); play.title = playing ? 'Пауза' : 'Воспроизвести';
        $('[data-play-status]').textContent = remotePlaying() ? 'ИГРАЕТ НА ДРУГОМ УСТРОЙСТВЕ' : recovery.pending ? 'ВОССТАНАВЛИВАЕМ СОЕДИНЕНИЕ…' : bufferVisible ? 'БУФЕРИЗАЦИЯ…' : playing ? 'ЛЕНТА В ДВИЖЕНИИ' : current ? 'ПАУЗА' : 'ГОТОВ К ПРОСЛУШИВАНИЮ';
        const sh = $('[data-action="shuffle"]'); sh.classList.toggle('is-active', shuffle); sh.setAttribute('aria-pressed', String(shuffle));
        sh.setAttribute('aria-label', `Случайный порядок: ${shuffle ? 'включён' : 'выключен'}`); sh.title = sh.getAttribute('aria-label');
        const re = $('[data-action="repeat"]'); re.classList.toggle('is-active', repeat !== 'off'); re.setAttribute('aria-label', `Повтор: ${repeat === 'off' ? 'выключен' : repeat === 'one' ? 'одного трека' : 'плейлиста'}`); re.title = re.getAttribute('aria-label'); $('[data-repeat-one]').hidden = repeat !== 'one';
        re.setAttribute('aria-pressed', String(repeat !== 'off'));
        syncVolume();
        syncPlaybackQueue();
        catalogUI.syncPlayback(current,playing);
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
        const duration = playbackDuration();
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
        if (meterReport.status && dialogOpen() && meterReport.status.isConnected) {
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
            const source = playbackSource(), first = collections.collection(data,source,selectedSort())[0]; if (!first) return;
            setQueue(source); load(first.id);
        }
        if(origin==='user'||origin==='system')claimPlayback();
        configureAudioSession();
        if (audio.error || (activeTrack()?.browserLocal && !audio.hasAttribute('src'))) load(current,playbackPosition());
        recovery.play(origin==='user'||origin==='system');
        if (audio.ended) audio.currentTime = 0;
        if(activeTrack()?.browserLocal && !audio.hasAttribute('src')){
            const t=activeTrack(),sourceVersion=generation;
            sourceJob=window.TapeRoomTorrents.resolve(t).then(url=>{if(sourceVersion===generation){audio.src=url;audio.load();}});
            sourceJob.catch(()=>{});
        }
        const version = generation;
        if (sourceJob) {
            const pending=sourceJob;
            try { await pending; }
            catch(error) { if(version===generation){setBuffering(false);toast(error.message,true);}return; }
            finally {if(sourceJob===pending)sourceJob=null;}
            if(version!==generation)return;
        }
        // Keep play() inside the tap's activation; meter setup never gates sound.
        try { const playback = audio.play(); resumeMeters(origin,event); await playback; playbackSync?.changed(0); }
        catch (error) { if (version === generation && error.name !== 'AbortError') { if(error.name==='NotAllowedError')recovery.pause();else if(activeTrack()?.source==='drive'&&audio.error)return;setBuffering(false); stopMeters('native-error'); toast(error.name==='NotAllowedError'?'Браузер ожидает подтверждения воспроизведения. Нажмите кнопку запуска ещё раз.':activeTrack()?.browserLocal?window.TapeRoomTorrents.playbackError(audio.error?.code||2):'Не удалось воспроизвести трек. Проверьте формат, соединение и доступ к файлу.', true); } }
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
        const enough = bufferedAhead() >= 8 || (playbackDuration() > 0 && bufferedAhead() >= playbackDuration()-audio.currentTime-.25 && audio.readyState >= 3);
        const allowed = !document.hidden && !audio.paused && !audio.ended && !audio.seeking && !loading && enough && !connection?.saveData && !['slow-2g','2g'].includes(connection?.effectiveType);
        preloader.schedule(upcoming(),allowed && config.authenticated!==false);
    }
    function setQueue(source = selectedSource()) { preloader.reset(); queueSource = collections.descriptor(source); queueSort = selectedSort(); queue = collections.collection(data,queueSource,queueSort).map(t => t.id); queuePlaylist = queueSource.type === 'playlist' ? queueSource.id : queueSource.type === 'favorites' ? -1 : 0; queueName = collections.name(data,queueSource); bag = []; futureBag = []; shuffleCycleStarted = false; history = []; }
    function load(id, time = 0) {
        const t = track(id); if (!t) return;
        sampleListening(); listening = null;
        recovery.select(t.id,t.source==='drive'&&!t.browserLocal);
        preloader.cancel(); generation++; audio.pause(); stopMeters('track-change'); current = t.id; restoreTime = time; setBuffering(false);
        chapterKey = ''; chapterIndex = -2; chapterDuration = -1;
        if(t.browserLocal){
            window.TapeRoomTorrents?.release();sourceJob=null;audio.removeAttribute('src');audio.load();
        }else{
            window.TapeRoomTorrents?.release();sourceJob=null;audio.src=t.url;audio.load();
        }
        render(); drawProgress(); save(); requestQuality();
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
        if (playbackPosition() > 3) { seekTo(0); return; }
        const id = shuffle && history.length ? history.pop() : queue[(Math.max(0, queue.indexOf(current)) - 1 + queue.length) % queue.length];
        if (id) { futureBag = []; load(id); play(origin,event); }
    }
    function applyState(result) {
        serverState=result;
        data={...result,tracks:[...result.tracks,...browserLibrary.tracks],playlists:result.playlists,catalog:{...(result.catalog || {}),albums:[...(result.catalog?.albums || []),...browserLibrary.albums]}};
        trackPool=collections.tracks(data);trackMap=new Map(trackPool.map(t=>[t.id,t]));render();save();
    }
    app.addEventListener('tape-room:browser-library',event=>{
        browserLibrary=event.detail.library;applyState(serverState);
        if(!browserRestored){
            browserRestored=true;
            if(saved.libraryView?.id>=4e15 && album(saved.libraryView.id))browse(saved.libraryView);
            if(saved.current>=4e15 && track(saved.current)){queueSource=collections.descriptor(saved.queueSource);load(saved.current,Number(saved.time)||0);}
        }
        if(event.detail.select){browse({type:'album',id:event.detail.select});app.querySelector('.rp-library').scrollIntoView({block:'start'});}
    });
    app.addEventListener('tape-room:library-refresh', async event => {
        try {
            await mutate(async () => {
                applyState(await api('state'));
                const albumId=Number(event.detail?.albumId);
                if(event.detail?.select!==false)browse(albumId && album(albumId) ? {type:'album',id:albumId} : {type:'songs'});
            });
            if(event.detail?.select!==false)app.querySelector('.rp-library').scrollIntoView({block:'start'});
        } catch (error) { toast(error.message,true); }
    });
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
        if (config.authenticated===false || !current || activeTrack()?.browserLocal || listening?.generation===generation) return;
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
        if(config.authenticated===false){showSignIn();return {status:false};}
        if(track(values.id)?.browserLocal)return window.TapeRoomTorrents.action(name,values,cover);
        const form = new FormData(); form.set('action', name); form.set('needCSRFToken', config.csrf);
        for (const [key,value] of Object.entries(values)) {
            if (Array.isArray(value)) value.forEach(item => form.append(`${key}[]`, item)); else form.set(key, value);
        }
        if (cover?.size) form.set('cover', cover);
        return mutate(async () => { const result = await api('action', form); if (result.tracks) applyState(result); return result; });
    }
    function shareMusic(kind,id){
        const item=kind==='album'?album(id):track(id);if(!item)return;
        const published=kind==='album'?item.published:(data.catalog?.albums||[]).some(album=>album.published&&album.tracks.includes(Number(id)));
        if(!published||item.browserLocal){openDialog('Поделиться музыкой','',async()=>{},item.browserLocal?'Torrent хранится только в вашем браузере. Для общей ссылки добавьте музыку в каталог сайта.':'Ссылка и код для вставки появятся после публикации альбома и одобрения модератором.');$('[data-dialog-submit]').hidden=true;return;}
        const url=`${config.shareBase}${kind}/${id}`,base=`${config.embedBase}${kind}/${id}`,height=kind==='album'?520:250;
        openDialog(kind==='album'?'Поделиться альбомом':'Поделиться песней',`<label class="rp-field">Ссылка<input type="url" name="share_url" value="${esc(url)}" readonly></label><button class="btn btn-outline-secondary" type="button" data-action="share-copy" data-target="share_url">${icon('link')}Копировать ссылку</button><hr><label class="rp-field">Тема плеера<select name="embed_theme"><option value="auto">Как на устройстве</option><option value="light">Светлая</option><option value="dark">Тёмная</option></select></label><label class="rp-field">Код для вставки на сайт<textarea name="embed_code" rows="4" readonly></textarea></label><button class="btn btn-outline-secondary" type="button" data-action="share-copy" data-target="embed_code">${icon('share')}Копировать код iframe</button>`,async()=>{},'Плеер встраивается без автоматического запуска. Слушателям не нужен аккаунт.');
        const update=()=>{const theme=dialog.querySelector('[name="embed_theme"]').value;dialog.querySelector('[name="embed_code"]').value=`<iframe src="${base}?theme=${theme}" title="${esc(item.title)} — Tape Room" width="100%" height="${height}" style="display:block;border:0;border-radius:18px;overflow:hidden" loading="lazy" allow="autoplay" referrerpolicy="strict-origin-when-cross-origin"></iframe>`;};
        dialog.querySelector('[name="embed_theme"]').addEventListener('change',update);update();$('[data-dialog-submit]').hidden=true;
    }
    function showSignIn(){
        openDialog('Войдите в Tape Room',`<div class="d-flex flex-wrap gap-2"><a class="btn btn-primary" href="${esc(config.login)}">Войти</a><a class="btn btn-outline-secondary" href="${esc(config.register)}">Регистрация</a></div>`,async()=>{},'Слушать музыку можно без аккаунта. Войдите, чтобы добавлять альбомы, песни и сохранять свою коллекцию.');
        $('[data-dialog-submit]').hidden=true;
    }
    function openDialog(title, fields, handler, description = '', submit = 'Сохранить', danger = false) {
        dialogRequest++;
        dialog.querySelectorAll('[data-drive-footer-control]').forEach(control=>control.remove());
        dialog.querySelector('.modal-footer').classList.remove('tr-drive-footer');
        dialog.querySelector('.modal-footer [data-action="close-dialog"]').hidden=false;
        chapterJob?.abort(); chapterJob = null;
        if (coverPreviewUrl) { URL.revokeObjectURL(coverPreviewUrl); coverPreviewUrl = undefined; }
        dialogSubmitting = false;
        dialog.classList.remove('rp-dialog--drive','rp-dialog--chapters','rp-dialog--album','rp-dialog--artist','rp-dialog--album-delete');
        $('[data-dialog-title]').textContent = title;
        $('[data-dialog-fields]').innerHTML = fields;
        $('[data-dialog-description]').textContent = description; $('[data-dialog-description]').hidden = !description;
        $('[data-dialog-error]').hidden = true;
        const control = $('[data-dialog-submit]'); control.textContent = submit; control.disabled = false; control.hidden = false; control.classList.toggle('rp-button--danger', danger);control.classList.toggle('btn-danger',danger);control.classList.toggle('btn-primary',!danger);
        dialogHandler = handler;
        window.TapeRoomUi.decorate(dialog);
        syncDialogViewport();
        if(!dialogOpen())dialogReturnFocus=document.activeElement;
        dialogModal.show();
    }
    function syncDialogViewport() {
        const viewport = window.visualViewport;
        const height = parseFloat(dialogShell.style.getPropertyValue('--tr-viewport-height')) || window.innerHeight;
        dialog.style.setProperty('--rp-dialog-viewport-height',`${height}px`);
        dialog.style.setProperty('--rp-dialog-viewport-top',`${parseFloat(dialogShell.style.getPropertyValue('--tr-viewport-top')) || 0}px`);
        dialog.classList.toggle('is-compact',height < 520);
        dialog.classList.toggle('is-short',height < 340);
    }
    window.visualViewport?.addEventListener('resize',() => { if (dialogOpen()) syncDialogViewport(); });
    window.visualViewport?.addEventListener('scroll',() => { if (dialogOpen()) syncDialogViewport(); });
    window.addEventListener('resize',() => { if (dialogOpen()) syncDialogViewport(); });
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
        panel.querySelector('.rp-chapter-tools').hidden = Boolean(t?.shared || t?.browserLocal);
        $('[data-chapter-source]').textContent = t ? [t.artist,t.title].filter(Boolean).join(' — ') : '';
        const query = $('[data-chapter-search]').value.trim().toLocaleLowerCase();
        const visible = items.map((item,index) => ({item,index})).filter(({item}) => `${item.title} ${item.artist || ''}`.toLocaleLowerCase().includes(query));
        $('[data-chapter-list]').innerHTML = visible.map(({item,index}) => `<article class="rp-chapter-row" data-chapter-row="${item.id}"><button type="button" class="rp-chapter-play" data-action="chapter-play" data-id="${item.id}" aria-label="Воспроизвести ${esc(item.title)} с ${chapters.formatTime(item.start)}"><span class="rp-chapter-number">${index+1}</span><span class="rp-chapter-info"><span class="rp-chapter-title">${esc(item.title)}</span>${item.artist ? `<span class="rp-chapter-artist">${esc(item.artist)}</span>` : ''}</span><span class="rp-chapter-start">${chapters.formatTime(item.start)}</span></button>${t?.shared ? '' : `<div class="rp-chapter-actions">${button('chapter-edit','Изменить таймкод','edit',item.id)}${button('chapter-delete','Удалить таймкод','trash',item.id)}</div>`}</article>`).join('');
        chapterRows = [...$('[data-chapter-list]').children];
        if ($('[data-chapter-empty]')) $('[data-chapter-empty]').hidden = visible.length !== 0;
        chapterIndex = -2;
        syncChapter(playbackDuration());
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
        claimPlayback();
        const duration = playbackDuration();
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
        const container = $('[data-chapter-markers]'), duration = playbackDuration();
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
            if (request !== dialogRequest || !dialogOpen()) return;
            const entries = result.entries || result.chapters || [];
            if (!entries.length) { if (status) status.textContent = 'Подходящие паузы не найдены. Добавьте таймкоды вручную или импортируйте CUE.'; return; }
            showChapterPreview(trackId,entries,result.warnings || [],Boolean(file),result.revision);
        } catch (error) {
            if (request === dialogRequest && dialogOpen()) {
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
        const rows = order.map(track).filter(t=>t && !t.shared && !t.browserLocal).map(t=>`<div class="rp-album-member" data-album-member="${t.id}"><label><input type="checkbox" name="album_tracks[]" value="${t.id}" ${members.has(t.id) ? 'checked' : ''}><span><strong>${esc(t.title)}</strong><small>${esc(t.artist || t.filename)}</small></span></label><div>${button('album-order-up','Переместить выше','up',t.id)}${button('album-order-down','Переместить ниже','down',t.id)}</div></div>`).join('');
        const fields = field('title','Название альбома',value.title || '') + field('artist','Артист / исполнитель альбома',value.artist || '') + genrePicker.markup(value.genreIds || value.genreId || value.genre_id || 0) + `<label class="rp-field">Дата релиза (если известна)<input type="date" name="release_date" value="${esc(value.release_date || '')}"></label><label class="rp-field">Описание<textarea name="description" maxlength="4000" rows="3">${esc(value.description || '')}</textarea></label><div class="rp-cover-field"><span class="rp-field-label">Обложка альбома</span><label class="rp-cover-picker"><input class="rp-file-input" type="file" name="cover" accept="image/jpeg,image/png,image/webp" aria-label="Выбрать обложку альбома">${previewImage(value.cover,'Обложка альбома')}<span class="rp-cover-picker-info"><span class="rp-cover-picker-button">${icon('upload')}Выбрать обложку</span><span data-cover-filename>JPG, PNG или WebP · до 8 МБ</span></span></label></div><div class="rp-album-members-heading"><h3>Песни и порядок</h3><small>Используются ваши исходные файлы</small></div><label class="rp-search">${icon('search')}<input type="search" data-album-track-search placeholder="Найти песню для альбома" aria-label="Найти песню для альбома"></label><div class="rp-album-members">${rows || '<p>Сначала добавьте музыку в CMS или из Google Drive.</p>'}</div>`;
        openDialog(existing ? 'Редактировать альбом' : 'Новый альбом',fields,async form => {
            const ids = [...dialog.querySelectorAll('[data-album-member] input:checked')].map(input=>Number(input.value));
            const result = await action(`catalog.album.${existing ? 'update' : 'create'}`,{id,title:form.get('title'),artist:form.get('artist'),release_date:form.get('release_date'),description:form.get('description'),genre_ids:JSON.stringify(genrePicker.values()),tracks:JSON.stringify(ids)},form.get('cover'));
            browse({type:'album',id:existing?.id || result.id}); toast(existing ? 'Альбом сохранён.' : 'Альбом добавлен в вашу коллекцию.');
        },data.catalog?.autoApprove ? 'Ваши альбомы публикуются без дополнительной модерации.' : 'Альбом объединяет песни и не создаёт копий аудио. После отправки публикацию проверит модератор.');
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
        openDialog('О композиции', field('title','Название',t.title) + field('artist','Исполнитель',t.artist) + genrePicker.markup(t.genreIds || t.genreId) + `<div class="rp-cover-field"><span class="rp-field-label">Обложка</span><label class="rp-cover-picker"><input class="rp-file-input" type="file" name="cover" accept="image/jpeg,image/png,image/webp" aria-label="Выбрать обложку">${previewImage(t.cover,'Предварительный просмотр обложки')}<span class="rp-cover-picker-info"><span class="rp-cover-picker-button">${icon('upload')}Выбрать обложку</span><span data-cover-filename>JPG, PNG или WebP · до 8 МБ</span></span></label></div><div class="rp-chapter-editor-actions"><button type="button" class="rp-button" data-action="chapter-add" data-track-id="${t.id}">${icon('plus')}Добавить таймкод</button><button type="button" class="rp-button" data-action="chapter-tools" data-track-id="${t.id}">${icon('list')}CUE и автопоиск</button></div>`, form => action('track.update',{id,title:form.get('title'),artist:form.get('artist'),genre_ids:JSON.stringify(genrePicker.values())},form.get('cover')));
        genrePicker.mount(dialog);
        if(t.browserLocal)dialog.querySelector('.rp-chapter-editor-actions')?.remove();
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
        if(config.authenticated===false){showSignIn();return;}
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
            if(config.authenticated===false && (['upload','create-playlist','favorite','add-to-playlist','catalog-create','catalog-save','drive','player-settings'].includes(name) || (name==='catalog-view' && ['mine','songs','favorites','playlist'].includes(control.dataset.view)))){showSignIn();return;}
            switch (name) {
                case 'auth':showSignIn();break;
                case 'play': if (audio.paused&&!recovery.pending) await play('user',event); else pausePlayback(); break;
                case 'playback-queue': queueDrawer?.toggle(); break;
                case 'queue-play': {
                    const entry=playbackQueue(); if (!entry.items.some(item=>Number(item.id)===id)) break;
                    if (!current) setQueue(entry.source);
                    if (current === id) { if (audio.paused&&!recovery.pending) await play('user',event); else pausePlayback(); }
                    else { load(id); await play('user',event); }
                    break;
                }
                case 'prev': previous('user',event); break;
                case 'next': next(false,'user',event); break;
                case 'play-track': setQueue(control.dataset.queueSource === 'popular' ? {type:'popular'} : selectedSource()); if (current === id) { if (audio.paused&&!recovery.pending) await play('user',event); else pausePlayback(); } else { load(id); await play('user',event); } break;
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
                    if(item.tracks.some(trackId=>Number(trackId)===current)){if(audio.paused&&!recovery.pending)await play('user',event);else pausePlayback();break;}
                    setQueue({type:'album',id}); load(item.tracks[0]); await play('user',event); break;
                }
                case 'catalog-save': control.disabled=true; await action('catalog.album.save',{id,saved:album(id)?.saved ? 0 : 1}); toast(album(id)?.saved ? 'Альбом в вашей коллекции.' : 'Альбом убран из коллекции.'); break;
                case 'catalog-create': editAlbum(); break;
                case 'catalog-edit': editAlbum(id); break;
                case 'catalog-publish': {
                    const item=album(id); if (!item?.editable || !item.canPublish) break;
                    openDialog(item.submitted ? 'Снять альбом с публикации?' : item.requiresModeration===false ? 'Опубликовать альбом?' : 'Отправить альбом на модерацию?', '', () => action('catalog.album.publish',{id,published:item.submitted ? 0 : 1}),item.submitted ? 'Альбом останется в вашей библиотеке. В общем каталоге его больше не будет.' : item.requiresModeration===false ? 'Ваш альбом сразу появится в общем каталоге.' : 'Администратор или создатель проверит альбом. После одобрения он появится в общем каталоге.',item.submitted ? 'Снять с публикации' : item.requiresModeration===false ? 'Опубликовать' : 'Отправить'); break;
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
                case 'close-dialog': if (!dialogSubmitting) closeDialog(); break;
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
                case 'delete-track': openDialog('Удалить композицию?', '', () => action('track.delete',{id}), `«${track(id).title}» будет удалена из библиотеки.${track(id).browserLocal ? ' Ссылка на torrent удалится только в этом браузере.' : track(id).source === 'drive' ? ' Файл на Google Drive останется.' : ' Загруженный аудиофайл будет удалён с сервера.'}`, 'Удалить', true); break;
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
                case 'share-album': shareMusic('album',id);break;
                case 'share-track': shareMusic('track',id);break;
                case 'share-copy': {const input=dialog.querySelector(`[name="${control.dataset.target}"]`);try{await navigator.clipboard.writeText(input.value);toast('Скопировано.');}catch(_){input.focus();input.select();toast('Выделено. Скопируйте текст вручную.');}break;}
                case 'drive': await openDrive(); break;
                case 'drive-settings': location.assign(config.driveSettings); break;
                case 'player-settings': location.assign(config.driveSettings); break;
                case 'drive-connect': {
                    if (!config.drive?.configured) { toast('Создатель сайта ещё не настроил подключение Google Drive.',true); break; }
                    control.disabled = true; const result = await action('drive.connect'); location.assign(result.authUrl); break;
                }
                case 'drive-disconnect': openDialog('Отключить Google Drive?', '', async () => { await action('drive.disconnect'); config.drive.connected = false; toast('Google Drive отключён.'); }, 'Ленты сохранятся. Чтобы слушать облачные треки, подключите аккаунт снова.', 'Отключить'); break;
                case 'drive-source': driveMode=control.dataset.mode;dialog.querySelector('[name="link"]').disabled=driveMode!=='link';dialog.querySelector('[data-drive-link-field]').hidden=driveMode!=='link';dialog.querySelectorAll('[data-action="drive-source"]').forEach(button=>{const active=button===control;button.classList.toggle('is-selected',active);button.setAttribute('aria-pressed',String(active));});break;
                case 'drive-next': control.disabled=true;try{await nextDriveStep();}finally{if(control.isConnected)updateDriveSelection();}break;
                case 'drive-back':setDriveStep(Math.max(1,driveStep-1));break;
                case 'drive-more': await driveFiles(control.dataset.page); break;
                case 'drive-select-all': {
                    const ids = [...driveKnownFiles.keys()], allSelected = ids.every(id => driveSelection.has(id));
                    for (const fileId of ids) {
                        if (allSelected) driveSelection.delete(fileId);
                        else if (driveSelection.size < 1000) driveSelection.add(fileId);
                    }
                    dialog.querySelectorAll('[data-drive-file]').forEach(box => { box.checked = driveSelection.has(box.value); });
                    updateDriveSelection(); break;
                }
                case 'drive-clear': driveSelection.clear(); dialog.querySelectorAll('[data-drive-file]').forEach(box => { box.checked = false; }); updateDriveSelection(); break;
                case 'drive-folder': drivePath.push({id:control.dataset.folderId,name:control.dataset.folderName}); await navigateDrive(); break;
                case 'drive-root': drivePath = [{id:'root',name:'Мой Drive'}]; await navigateDrive(); break;
                case 'drive-parent': drivePath = drivePath.slice(0,Number(control.dataset.depth)+1); await navigateDrive(); break;
                case 'drive-all': drivePath = []; await navigateDrive(); break;
            }
        } catch (error) { toast(error.message,true); if (control.isConnected) control.disabled = false; }
    });
    $('[data-dialog-form]').addEventListener('submit', async event => {
        event.preventDefault();
        if(dialog.classList.contains('rp-dialog--drive') && driveStep<3){try{await nextDriveStep();}catch(error){$('[data-dialog-error]').textContent=error.message;$('[data-dialog-error]').hidden=false;}return;}
        const control = $('[data-dialog-submit]'); if (control.disabled) return;
        const request = dialogRequest;
        dialogSubmitting = true; control.disabled = true; $('[data-dialog-error]').hidden = true;
        try { await dialogHandler(new FormData(event.currentTarget)); if (request === dialogRequest){dialogSubmitting=false;closeDialog();} }
        catch (error) { if (request === dialogRequest) { $('[data-dialog-error]').textContent = error.message; $('[data-dialog-error]').hidden = false; } }
        finally { if (request === dialogRequest) { dialogSubmitting = false; control.disabled = false; } }
    });
    dialogShell.addEventListener('hide.bs.modal',event=>{if(dialogSubmitting)event.preventDefault();});
    dialogShell.addEventListener('shown.bs.modal',()=>{dialog.querySelector('input:not([type=hidden]):not([type=file]),textarea,a.btn')?.focus({preventScroll:true});});
    new MutationObserver(()=>{dialogShell.querySelector('.modal-dialog').classList.toggle('modal-lg',dialog.matches('.rp-dialog--drive,.rp-dialog--chapters,.rp-dialog--album,.rp-dialog--artist'));}).observe(dialog,{attributes:true,attributeFilter:['class']});
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
    $('[data-seek]').addEventListener('input', event => { seekActive = true; const fraction = Number(event.target.value) / 1000; rangeFill(event.target,fraction); $('[data-elapsed]').textContent = formatTime(playbackDuration() * fraction); });
    $('[data-seek]').addEventListener('change', event => { const duration = playbackDuration(); seekActive = false; if (duration) seekTo(duration * Number(event.target.value) / 1000); });
    $('[data-audio-files]').addEventListener('change', event => uploadFiles([...event.target.files]));
    audio.addEventListener('loadedmetadata', () => {
        if (restoreTime) {
            const duration=window.TapeRoomQuality.durationToSave(activeTrack() || {},qualityCache.get(current),audio.duration);
            audio.currentTime=duration ? (recovery.pending ? Math.min(Math.max(0,duration-.1),restoreTime) : restoreTime>=duration-.25 ? 0 : Math.max(0,restoreTime)) : Math.max(0,restoreTime);
            restoreTime = 0;
        }
        syncDuration();
        drawProgress();
    });
    audio.addEventListener('durationchange', () => { syncDuration();drawProgress(true); });
    audio.addEventListener('play', () => { recovery.play();setBuffering(audio.readyState < 3); startAnimation(); });
    audio.addEventListener('playing', () => { setBuffering(false); resumeMeters(); startAnimation(); startListening(); });
    audio.addEventListener('waiting', () => { recovery.waiting();setBuffering(true); });
    // stalled concerns downloading; only waiting proves playback is starved.
    audio.addEventListener('stalled', () => { drawProgress(); });
    audio.addEventListener('canplay', () => { if (audio.readyState >= 3) setBuffering(false); });
    audio.addEventListener('progress', () => { drawProgress(); prepareUpcoming(); });
    audio.addEventListener('seeking', () => { if (listening) { listening.position=audio.currentTime; listening.wall=performance.now(); } preloader.cancel(); if (audio.readyState < 3) setBuffering(true); drawProgress(); });
    audio.addEventListener('pause', () => { if(audio.paused&&!audio.error&&!recovery.pending)recovery.pause();preloader.cancel(); setBuffering(false); stopMeters(); save(); });
    audio.addEventListener('timeupdate', () => { if(!audio.paused&&!audio.seeking)recovery.progress(audio.currentTime);sampleListening(); drawProgress(); prepareUpcoming(); if (Date.now() - lastSave > 3000) { save(); lastSave = Date.now(); } });
    audio.addEventListener('seeked', () => { if (listening) { listening.position=audio.currentTime; listening.wall=performance.now(); } meterController.seek(); drawProgress(true); syncMirror(); });
    audio.addEventListener('ratechange', () => { drawProgress(true); syncMirror(); });
    audio.addEventListener('ended', () => { recovery.pause();sampleListening(true); listening=null; setBuffering(false); stopMeters(); drawProgress(); next(true); });
    let errorProbe = -1;
    audio.addEventListener('error', async () => {
        if (!current) return; preloader.cancel(); setBuffering(false); stopMeters('native-error'); meterController.nativeFailure(audio.error?.code || 0);
        const t = activeTrack(), version = generation;
        if(t?.browserLocal){toast(window.TapeRoomTorrents.playbackError(audio.error?.code||2),true);return;}
        if (errorProbe === version) return; errorProbe = version;
        const failurePosition=playbackPosition(),failureCode=audio.error?.code||2;let status=0,terminal=false,maintenance=false,retryAfter=12;
        let message = t?.source==='drive'?'Не удалось загрузить песню из Google Drive. Повторите воспроизведение или проверьте подключение.':'Не удалось прочитать аудиофайл. Проверьте формат и подключение.';
        const controller = new AbortController(), timeout = setTimeout(() => controller.abort(),10000);
        try {
            const response = await fetch(t.url,{credentials:'same-origin',cache:'no-store',headers:{Range:'bytes=0-1'},signal:controller.signal});
            status=response.status;terminal=response.redirected;
            maintenance=status===503&&response.headers.get('X-Fireball-Maintenance')==='1';retryAfter=Number(response.headers.get('Retry-After'))||12;
            if (version === generation) meterController.nativeFailure(audio.error?.code || 0,response.status);
            if (response.redirected) message = 'Сессия завершена. Обновите страницу и войдите в CMS.';
            else if (!response.ok && response.headers.get('Content-Type')?.startsWith('text/plain')) message = (await response.text()).slice(0,500) || message;
            else if (response.status === 401) message = 'Сессия завершена. Обновите страницу и войдите в CMS.';
            else if (t?.source==='drive' && response.ok && [3,4].includes(audio.error?.code)) message = 'Google Drive отдал начало файла, но воспроизведение прервалось. Возможны неполные данные или неподдерживаемый формат.';
            else if (response.ok) message = 'Соединение прервалось. Нажмите воспроизведение, чтобы повторить.';
            await response.body?.cancel();
        } catch (_) {} finally { clearTimeout(timeout); }
        if(version===generation&&!terminal&&recovery.failed({code:failureCode,status,position:failurePosition,maintenance,retryAfter})){
            if(maintenance)toast('Сайт обновляется. Воспроизведение продолжится автоматически.');return;
        }
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
    window.addEventListener('dragenter', event => { if (app.dataset.sitePage!=='1' && [...(event.dataTransfer?.types || [])].includes('Files')) { event.preventDefault(); dragDepth++; $('[data-drop-overlay]').hidden = false; } });
    window.addEventListener('dragover', event => { if (app.dataset.sitePage!=='1' && [...(event.dataTransfer?.types || [])].includes('Files')) event.preventDefault(); });
    window.addEventListener('dragleave', event => { if (--dragDepth <= 0) { dragDepth = 0; $('[data-drop-overlay]').hidden = true; } });
    window.addEventListener('drop', event => { if(app.dataset.sitePage==='1')return;event.preventDefault(); dragDepth = 0; $('[data-drop-overlay]').hidden = true; uploadFiles([...(event.dataTransfer?.files || [])]); });
    document.addEventListener('keydown', event => {
        if(app.dataset.sitePage==='1' && !event.target.closest('[data-mini-player],[data-playback-queue]'))return;
        if (dialogOpen() || /^(INPUT|TEXTAREA|SELECT|BUTTON)$/.test(event.target.tagName) || event.target.isContentEditable || event.altKey || event.ctrlKey || event.metaKey) return;
        if (event.code === 'Space') { event.preventDefault(); if (audio.paused&&!recovery.pending) play('user',event); else pausePlayback(); }
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
            play:() => play('system'), pause:pausePlayback, previoustrack:() => previous('system'), nexttrack:() => next(false,'system'),
            seekto:event => seekTo(event.seekTime)
        };
        for (const [name,handler] of Object.entries(handlers)) { try { navigator.mediaSession.setActionHandler(name,handler); } catch (_) {} }
    }
    window.addEventListener('pagehide', () => {
        recovery.pause();save(); cancelQuality(); transport.reset(); visuals.reels.forEach(reel => reel.motion?.pause());
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
    setInterval(()=>{recovery.tick();if(remotePlayback){drawProgress();updateButtons();}},1000);
    window.addEventListener('offline', () => { preloader.connectivity(false); meterController.network(false); });
    window.addEventListener('online', () => { recovery.connected();preloader.connectivity(true); meterController.network(true); prepareUpcoming(); if (!audio.paused) resumeMeters(); });
    window.addEventListener('pageshow', () => { drawProgress(); if (!audio.paused) { resumeMeters(); startAnimation(); } });
    window.addEventListener('focus', () => { if (!audio.paused) { resumeMeters(); startAnimation(); } });
    if ((!config.torrent || config.authenticated===false) && config.pwa?.enabled && config.pwa.worker && 'serviceWorker' in navigator && window.isSecureContext) {
        navigator.serviceWorker.register(config.pwa.worker, {scope:'/', updateViaCache:'none'}).catch(() => {});
    }

    let driveSelection=new Set(),driveKnownFiles=new Map(),driveChosenFiles=new Map(),driveFolders=new Set(),drivePath=[],driveBrowseGeneration=0,driveStep=1,driveMode='browse',driveGenrePicker;
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
        bulk.textContent = driveKnownFiles.size && [...driveKnownFiles.keys()].every(id => driveSelection.has(id)) ? 'Снять выбор в папке' : driveKnownFiles.size > 1000 ? 'Выбрать до 1000' : 'Выбрать показанные';
        dialog.querySelector('[data-action="drive-clear"]').hidden = !driveSelection.size;
        const submit=$('[data-dialog-submit]'),asAlbum=dialog.querySelector('[name="as_album"]')?.checked;
        submit.hidden=driveStep!==3;submit.disabled=dialogSubmitting||!driveSelection.size;
        submit.textContent=asAlbum?'Создать альбом':`Добавить песни (${driveSelection.size})`;
        const albumFields=dialog.querySelector('[data-drive-album-fields]');if(albumFields)albumFields.hidden=!asAlbum;
        const title=dialog.querySelector('[name="title"]');if(title)title.required=driveStep===3&&asAlbum;
        const next=dialog.querySelector('[data-action="drive-next"]');if(next)next.disabled=driveStep===2&&!driveSelection.size;

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

    }
    async function navigateDrive() {
        driveBrowseGeneration++; driveKnownFiles = new Map(); driveFolders = new Set();
        const list = dialog.querySelector('[data-drive-files]'); list.replaceChildren(element('p','rp-drive-loading','Загружаем папки и музыку…'));
        dialog.querySelector('[data-drive-count]').textContent = 'Загрузка…';
        renderDrivePath(); updateDriveSelection();
        await driveFiles();
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
    async function openDrive() {
        const status = await driveDialogStatus(++dialogRequest); if (!status) return;
        config.drive = status.drive;
        if (!status.drive.connected) {
            openDialog('Музыка из Google Drive', '<button type="button" class="rp-button rp-google-button" data-action="drive-connect"' + (!status.drive.configured ? ' disabled' : '') + '><span class="rp-google-mark" aria-hidden="true">G</span>Войти в Google Drive</button>' + (!status.drive.configured ? '<p>' + (status.drive.canManage ? 'Настройте Google Drive в админ-панели: Tape Room → Настройки.' : 'Создатель сайта ещё не настроил подключение Google Drive.') + '</p>' : ''), async () => {}, 'Ваша музыка доступна только вашему аккаунту в плеере. Выберите свой Google-аккаунт и разрешите чтение Drive.');
            $('[data-dialog-submit]').hidden = true;
            return;
        }
        driveSelection=new Set();driveChosenFiles=new Map();drivePath=[{id:'root',name:'Мой Drive'}];driveStep=1;driveMode='browse';
        driveGenrePicker=new window.TapeRoomGenrePicker(data.catalog?.genres || []);
        const fields=`<div data-drive-steps>${window.TapeRoomDriveUi.steps(1)}</div>
            <section data-drive-step="1"><h3 class="tr-import-title">Откуда добавить музыку?</h3><p class="tr-import-note">Музыка останется на Google Drive. В библиотеке сохранятся ссылки на выбранные песни.</p>
                <div class="tr-import-sources"><button type="button" class="btn tr-import-source is-selected" data-action="drive-source" data-mode="browse" aria-pressed="true">${icon('folder')}<span><strong>Выбрать на Drive</strong><small>Открыть папку и отметить песни</small></span></button><button type="button" class="btn tr-import-source" data-action="drive-source" data-mode="link" aria-pressed="false">${icon('link')}<span><strong>Вставить ссылку</strong><small>На папку или отдельную песню</small></span></button></div>
                <label class="rp-field tr-import-link" data-drive-link-field hidden>Ссылка Google Drive<input name="link" type="url" placeholder="https://drive.google.com/…" inputmode="url" autocomplete="off"></label>
                <div class="tr-import-account"><span>${icon('check')} Google Drive подключён</span><button type="button" class="btn btn-link" data-action="drive-disconnect">Отключить</button></div></section>
            <section data-drive-step="2" hidden><h3 class="tr-import-title">Выберите песни</h3><div class="rp-drive-location"><nav class="rp-drive-path" data-drive-path aria-label="Папки Google Drive"></nav><button type="button" class="btn btn-outline-secondary btn-sm" data-action="drive-all">Вся музыка</button></div><div class="rp-drive-heading"><small data-drive-count>Загрузка…</small></div><div class="rp-drive-selection"><span data-drive-selected>Выбрано: 0</span><div class="rp-drive-selection-buttons"><button type="button" class="btn btn-link btn-sm" data-action="drive-clear" hidden>Сбросить</button><button type="button" class="btn btn-outline-secondary btn-sm" data-action="drive-select-all" disabled>Выбрать показанные</button></div></div><div class="rp-drive-files" data-drive-files aria-live="polite"></div></section>
            <section data-drive-step="3" hidden><h3 class="tr-import-title">Оформление коллекции</h3><p class="tr-import-note" data-drive-review></p><div class="form-check form-switch tr-import-album-choice"><input class="form-check-input" type="checkbox" id="tr-drive-as-album" name="as_album" value="1" checked><label class="form-check-label" for="tr-drive-as-album">Создать альбом из выбранных песен</label></div><div data-drive-album-fields>${field('title','Название альбома','')}${field('artist','Исполнитель альбома','')}${driveGenrePicker.markup()}<p class="tr-import-note">Название, длительность и обложка песни будут уточнены из тегов, если они есть. Альбом появится в «Моя музыка» и останется личным до публикации.</p></div></section>
            `;
        openDialog('Добавить из Google Drive',fields,async form=>{
            if(driveStep!==3)return;
            if(!driveSelection.size)throw new Error('Выберите хотя бы одну песню.');
            const asAlbum=form.get('as_album')==='1';
            const ids=[...driveSelection].sort((a,b)=>(driveChosenFiles.get(a)?.name||a).localeCompare(driveChosenFiles.get(b)?.name||b,'ru',{numeric:true}));
            const result=await action('drive.import',{ids,link:'',playlist_id:asAlbum?0:selected>0?selected:0,as_album:asAlbum?'1':'0',title:form.get('title')||'',artist:form.get('artist')||'',genre_ids:JSON.stringify(driveGenrePicker.values())});
            if(result.album_id)browse({type:'album',id:result.album_id});else if(selected===-1)browse({type:'songs'});
            toast(result.album_id?'Альбом создан в «Моя музыка».':`Добавлено песен: ${result.imported}`);
            refreshDriveMetadata(result.imported_ids||[],true).catch(()=>{});
        },'', 'Создать альбом');
        dialog.classList.add('rp-dialog--drive');driveGenrePicker.mount(dialog);
        const footer=dialog.querySelector('.modal-footer');footer.classList.add('tr-drive-footer');
        for(const [actionName,label,primary] of [['drive-back','Назад',false],['drive-next','Продолжить',true]]){const control=element('button',`btn rp-button ${primary?'btn-primary':'btn-outline-secondary'}`,label);control.type='button';control.dataset.action=actionName;control.dataset.driveFooterControl='';footer.insertBefore(control,$('[data-dialog-submit]'));}

        dialog.querySelector('[name="as_album"]').addEventListener('change',updateDriveSelection);
        setDriveStep(1);
    }
    function setDriveStep(step){
        driveStep=step;dialog.querySelector('.modal-footer [data-action="close-dialog"]').hidden=step>1;dialog.querySelector('[name="link"]').disabled=step!==1||driveMode!=='link';dialog.querySelector('[data-drive-steps]').innerHTML=window.TapeRoomDriveUi.steps(step);
        dialog.querySelectorAll('[data-drive-step]').forEach(panel=>panel.hidden=Number(panel.dataset.driveStep)!==step);
        dialog.querySelector('[data-action="drive-back"]').hidden=step===1;dialog.querySelector('[data-action="drive-next"]').hidden=step===3;
        dialog.querySelector('[name="title"]').required=step===3&&dialog.querySelector('[name="as_album"]').checked;
        updateDriveSelection();dialog.querySelector('.modal-body')?.scrollTo({top:0});
    }
    async function nextDriveStep(){
        if(driveStep===1){
            const request=dialogRequest;
            if(driveMode==='link'){
                const link=dialog.querySelector('[name="link"]');if(!link.value.trim())throw new Error('Вставьте ссылку на папку или песню Google Drive.');
                if(!link.checkValidity()){link.reportValidity();return;}
                const result=await action('drive.preview',{link:link.value.trim()});if(request!==dialogRequest)return;
                drivePath=[];driveKnownFiles=new Map();driveFolders=new Set();driveSelection.clear();driveChosenFiles.clear();
                renderDriveEntries(result.files||[],dialog.querySelector('[data-drive-files]'),true);
                dialog.querySelector('[data-drive-path]').textContent='По ссылке';dialog.querySelector('[data-action="drive-all"]').hidden=true;
                dialog.querySelector('[data-drive-count]').textContent=`Песен: ${driveKnownFiles.size}`;
                for(const file of result.files||[])driveSelection.add(file.id);
                dialog.querySelectorAll('[data-drive-file]').forEach(box=>box.checked=true);
                if(result.name)dialog.querySelector('[name="title"]').value=result.name;
                setDriveStep(2);
            }else{setDriveStep(2);await navigateDrive();}
        }else if(driveStep===2){
            if(!driveSelection.size)throw new Error('Выберите хотя бы одну песню.');
            const title=dialog.querySelector('[name="title"]');if(!title.value)title.value=drivePath.length>1?drivePath.at(-1).name:'Мой альбом';
            dialog.querySelector('[data-drive-review]').textContent=`Выбрано песен: ${driveSelection.size}. Проверьте название и жанры перед добавлением.`;setDriveStep(3);
        }
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
                if ($('[data-drive-files]') !== list || !dialogOpen() || browseGeneration !== driveBrowseGeneration) return;
                files = (result.files || []).filter(file => isDriveAudio(file) || (folder && isDriveFolder(file)));
                const next = result.nextPageToken || '';
                if (!next || next === token || files.length) break;
                token = next;
            } while (++attempts < 3);
        } catch (error) {
            if ($('[data-drive-files]') !== list || !dialogOpen() || browseGeneration !== driveBrowseGeneration) return;
            if (more) { more.disabled = false; more.textContent = 'Повторить загрузку'; }
            else { list.replaceChildren(element('p','rp-drive-empty',error.message)); const retry = element('button','rp-button','Повторить загрузку'); retry.type = 'button'; retry.dataset.action = 'drive-more'; list.append(retry); }
            throw error;
        }
        if (!page) list.replaceChildren(); else more?.remove();
        list.querySelector('.rp-drive-empty')?.remove();
        renderDriveEntries(files,list);
        if (!driveKnownFiles.size && !driveFolders.size) list.prepend(element('p','rp-drive-empty',result.nextPageToken ? 'В этой порции файлов нет папок и музыки. Продолжите поиск.' : 'Здесь нет папок и поддерживаемой музыки. Вернитесь назад или вставьте ссылку ниже.'));
        dialog.querySelector('[data-drive-count]').textContent = `${folder ? `Папок: ${driveFolders.size} · ` : ''}песен: ${driveKnownFiles.size}${result.nextPageToken ? ' · есть ещё' : ''}`;
        if (result.nextPageToken) { const next = element('button','rp-button rp-drive-more','Показать ещё'); next.type = 'button'; next.dataset.action = 'drive-more'; next.dataset.page = result.nextPageToken; list.append(next); }
        updateDriveSelection();
    }
    function renderDriveEntries(files,list,clear=false){
        if(clear)list.replaceChildren();
        for (const file of window.TapeRoomDriveUi.sort(files)) {
            if (isDriveFolder(file)) {
                if (driveFolders.has(file.id)) continue;
                driveFolders.add(file.id);
                const entry = element('button','rp-drive-folder'); entry.type = 'button'; entry.dataset.action = 'drive-folder'; entry.dataset.folderId = file.id; entry.dataset.folderName = file.name; entry.setAttribute('aria-label',`Открыть папку ${file.name}`);
                const mark = element('span','rp-drive-folder-icon'); mark.innerHTML = icon('folder');
                entry.append(mark,element('span','rp-drive-folder-name',file.name),element('span','rp-drive-folder-arrow','›'));
                list.insertBefore(entry,list.querySelector('.rp-drive-file')); continue;
            }
            if (driveKnownFiles.has(file.id)) continue;
            driveKnownFiles.set(file.id,file);driveChosenFiles.set(file.id,file);
            const label = element('label','rp-drive-file'), box = element('input',''); box.type = 'checkbox'; box.value = file.id; box.checked = driveSelection.has(file.id); box.dataset.driveFile = '';
            box.setAttribute('aria-label',`Выбрать ${file.name}`);
            box.addEventListener('change', () => {
                if (box.checked && driveSelection.size >= 1000) { box.checked = false; toast('За один раз можно выбрать до 1000 песен.',true); return; }
                box.checked ? driveSelection.add(file.id) : driveSelection.delete(file.id); updateDriveSelection();
            });
            const mark = element('span','rp-drive-file-icon'); mark.innerHTML = icon('music');
            const info = element('span','rp-drive-file-info'); info.append(element('span','rp-drive-file-name',file.name),element('small','rp-drive-file-format',file.name.split('.').pop().toUpperCase()));
            label.append(box,mark,info,element('small','rp-drive-file-size',driveSize(file.size))); list.append(label);
        }
    }
    if (queuePanel) {
        let queueReturnFocus=false;
        queuePanel.addEventListener('show.bs.offcanvas',()=>{renderPlaybackQueue(true);queueToggle.setAttribute('aria-expanded','true');});
        queuePanel.addEventListener('shown.bs.offcanvas',()=>{
            if (queueToggle.getAttribute('aria-expanded')!=='true' || $('[data-mini-player]').hidden) return;
            queuePanel.removeAttribute('aria-modal');
            const active=queuePanel.querySelector('.tr-queue-song.is-current') || queuePanel.querySelector('.tr-queue-song');
            active?.focus({preventScroll:true}); active?.scrollIntoView({block:'nearest'});
        });
        queuePanel.addEventListener('hide.bs.offcanvas',()=>{queueReturnFocus=queuePanel.contains(document.activeElement);queueToggle.setAttribute('aria-expanded','false');});
        queuePanel.addEventListener('hidden.bs.offcanvas',()=>{if(queueReturnFocus && !$('[data-mini-player]').hidden)queueToggle.focus({preventScroll:true});});
        document.addEventListener('keydown',event=>{if(event.key==='Escape' && !dialogOpen() && queuePanel.classList.contains('show'))queueDrawer.hide();});
        document.addEventListener('click',event=>{if(queuePanel.classList.contains('show') && !queuePanel.contains(event.target) && !$('[data-mini-player]').contains(event.target))queueDrawer.hide();});
    }
    dialogShell.addEventListener('hidden.bs.modal', () => { if(dialogReturnFocus?.isConnected)dialogReturnFocus.focus({preventScroll:true}); dialogRequest++; driveBrowseGeneration++; chapterJob?.abort(); chapterJob = null; $('[data-dialog-submit]').hidden = false; if (coverPreviewUrl) { URL.revokeObjectURL(coverPreviewUrl); coverPreviewUrl = undefined; } });
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
    const params = new URLSearchParams(location.search);
    const linkedAlbum=Number(params.get('album'));
    if(Number.isSafeInteger(linkedAlbum)&&linkedAlbum>0)libraryView=album(linkedAlbum)?{type:'album',id:linkedAlbum}:{type:'albums'};
    applyTheme();
    render();
    if (track(saved.current)) { queue = Array.isArray(saved.queue) ? saved.queue.filter(id => Boolean(track(id))) : []; if (!queue.length) setQueue(); queueName = saved.queueName || 'Вся музыка'; load(saved.current,Number(saved.time) || 0); }
    else if (data.tracks.length && !(saved.current>=4e15)) { setQueue(); load(selectedTracks()[0]?.id || data.tracks[0].id); }
    if(config.authenticated!==false){
        playbackSync=new window.TapeRoomPlaybackSync({url:`${config.api}/playback`,csrf:config.csrf,initial:config.playback,
            snapshot:()=>current&&!activeTrack()?.browserLocal ? {playing:!audio.paused&&!audio.ended,state:{current,queue:queue.filter(id=>!track(id)?.browserLocal),queueSource,queueName,queueSort,queuePlaylist,time:playbackPosition(),shuffle,repeat}} : null,
            apply:async(value,valid)=>{
                if(activeTrack()?.browserLocal&&!audio.paused)return;
                const state=value.state;
                if(!track(state.current)||state.queue.some(id=>!track(id))){const result=await api('state');if(!valid())return;applyState(result);}
                if(!valid()||!track(state.current))return;
                remotePlayback={...value,received:performance.now()};pausePlayback();
                queue=state.queue.filter(id=>Boolean(track(id)));queueSource=collections.descriptor(state.queueSource);queueName=state.queueName;queueSort=state.queueSort;queuePlaylist=state.queuePlaylist;shuffle=state.shuffle;repeat=state.repeat;
                bag=[];futureBag=[];history=[];
                if(current!==state.current||audio.error)load(state.current,window.TapeRoomPlaybackSync.position(value));
                else{updateNow();updateButtons();renderPlaybackQueue();drawProgress();save();}
            }});
        playbackSync.start();
    }
    drawProgress(); drawMeters(false);
    if(linkedAlbum>0)requestAnimationFrame(()=>$('.rp-library').scrollIntoView({block:'start'}));
    if (params.has('drive')) { toast(params.get('drive') === 'connected' ? 'Google Drive подключён. Можно добавить музыку.' : 'Google Drive не подключён. Проверьте OAuth-настройки и попробуйте снова.',params.get('drive') !== 'connected'); window.history.replaceState({},'',location.pathname); }
})();
