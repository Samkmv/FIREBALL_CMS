(() => {
    'use strict';

    const countLabel = (count, singular, few, plural) => `${count} ${count % 10 === 1 && count % 100 !== 11 ? singular : count % 10 >= 2 && count % 10 <= 4 && !(count % 100 >= 12 && count % 100 <= 14) ? few : plural}`;
    const normalized = value => String(value || '').trim().toLocaleLowerCase();
    const timestamp = value => {
        if (!value) return 0;
        if (typeof value === 'number') return value < 1e12 ? value * 1000 : value;
        const result = Date.parse(String(value).replace(' ', 'T'));
        return Number.isFinite(result) ? result : 0;
    };
    const duration = seconds => {
        const value = Math.max(0, Math.floor(Number(seconds) || 0));
        if (value < 60) return `${value} сек`;
        return value >= 3600 ? `${Math.floor(value / 3600)} ч ${Math.floor(value % 3600 / 60)} мин` : `${Math.floor(value / 60)} мин`;
    };

    // This module owns only the library below the deck. Playback, queues,
    // editing and network requests remain in the existing player controller.
    class TapeRoomCatalogUI {
        constructor({app, icon, escape, defaultCover, defaultArtist = () => '', imageSources, renderTrack, canPublish = false}) {
            this.app = app;
            this.icon = icon;
            this.escape = escape;
            this.defaultCover = typeof defaultCover === 'function' ? defaultCover : () => defaultCover || '';
            this.defaultArtist = typeof defaultArtist === 'function' ? defaultArtist : () => defaultArtist || '';
            this.imageSources = imageSources || ((source,kind) => ({src:source || (kind === 'artist' ? this.defaultArtist() : this.defaultCover()),srcset:''}));
            this.renderTrack = renderTrack;
            this.canPublish = Boolean(canPublish);
            this.content = app.querySelector('[data-catalog-content]');
            this.latest = null;
        }

        html(value) {
            const template=document.createElement('template');template.innerHTML=value;
            window.TapeRoomUi?.decorate(template.content);
            window.TapeRoomImages.patch(this.content,template.content);
        }

        syncPlayback(current, playing) {
            if (!this.latest || !this.content) return;
            this.latest.current=Number(current);this.latest.playing=Boolean(playing);
            const update=(control,active,title)=>{
                const glyph=active?'pause':'play';
                if(control.dataset.playbackIcon!==glyph){control.innerHTML=this.icon(glyph);control.dataset.playbackIcon=glyph;}
                control.setAttribute('aria-label',`${active?'Приостановить':'Воспроизвести'} ${title}`);
                control.setAttribute('aria-pressed',String(active));
            };
            for(const row of this.content.querySelectorAll('.rp-catalog-popular-list .rp-track')){
                const id=Number(row.dataset.trackId),active=id===Number(current)&&playing;
                row.classList.toggle('is-current',id===Number(current));
                update(row.querySelector('.rp-track-play'),active,this.latest.trackMap.get(id)?.title || 'песню');
            }
            for(const card of this.content.querySelectorAll('.rp-album-card')){
                const album=this.latest.albums.find(item=>Number(item.id)===Number(card.dataset.albumId));
                const active=this.tracksFor(album).some(track=>Number(track.id)===Number(current))&&playing;
                card.classList.toggle('is-playing',active);
                const control=card.querySelector('.rp-catalog-card-play');if(control)update(control,active,`альбом ${album?.title || ''}`);
            }
        }

        render({data, view = {type:'overview'}, current = 0, playing = false, query, canPublish}) {
            if (!this.content) return {listMode:true};
            const catalog = data.catalog || {albums:[],tracks:[]};
            const albums = Array.isArray(catalog.albums) ? catalog.albums : [];
            const ownTracks = data.tracks || data.library?.tracks || [];
            const trackMap = new Map();
            for (const track of [...(catalog.tracks || []), ...ownTracks]) trackMap.set(Number(track.id), {...(trackMap.get(Number(track.id)) || {}),...track});
            const publicAlbums = albums.filter(album => Boolean(album.published));
            const publicIds = new Set(publicAlbums.flatMap(album => (album.tracks || []).map(item => Number(typeof item === 'object' ? item.id : item))));
            const publicTracks = [...trackMap.values()].filter(track => publicIds.has(Number(track.id)));
            const personalAlbums = albums.filter(album => album.saved || album.editable);
            const search = normalized(query ?? this.app.querySelector('[data-search]')?.value);
            this.latest = {data, view, current:Number(current), playing:Boolean(playing), albums, publicAlbums, ownTracks, publicTracks, trackMap, search, canPublish:Boolean(canPublish)};
            const activeSection = view.type === 'album' || view.type === 'artist' ? '' : view.type;
            this.app.querySelectorAll('[data-catalog-nav] [data-view]').forEach(button => {
                const active = button.dataset.view === activeSection;
                button.classList.toggle('is-selected', active);
                button.setAttribute('aria-current', active ? 'page' : 'false');
            });
            const albumCount = this.app.querySelector('[data-catalog-album-count]');
            const savedCount = this.app.querySelector('[data-catalog-saved-count]');
            if (albumCount) albumCount.textContent = publicAlbums.length || '';
            if (savedCount) savedCount.textContent = personalAlbums.length || '';
            const create = this.app.querySelector('[data-catalog-create]');
            if (create) create.hidden = !catalog.canPublish || !['overview','albums','mine'].includes(view.type);

            let result;
            switch (view.type) {
                case 'album': result = this.albumDetail(albums.find(album => Number(album.id) === Number(view.id))); break;
                case 'artist': result = this.artistDetail(view.artist || view.name || ''); break;
                case 'albums':
                    result = this.cardsPage(publicAlbums, 'Альбомы', 'Каталог сайта', 'В каталоге пока нет альбомов', 'Опубликованные альбомы появятся здесь. Собрать свой альбом можно из уже добавленной музыки.');
                    break;
                case 'mine':
                    result = this.cardsPage(personalAlbums, 'Моя музыка', 'Ваша коллекция', 'Ваша коллекция альбомов пока пуста', 'Откройте альбом в каталоге и нажмите «В коллекцию» или создайте альбом из своих песен.', `<button class="rp-catalog-text-button" type="button" data-action="catalog-view" data-view="songs">Все мои песни</button>`);
                    break;
                case 'genres': result = this.genresPage(); break;
                case 'genre': result=this.genrePage(view.id); break;
                case 'artists': result = this.artistsPage(); break;
                case 'new': result = this.newPage(); break;
                case 'popular': {
                    const tracks = this.popularTracks();
                    this.html('<p class="rp-catalog-note">Песни из общего каталога, которые чаще слушают на сайте.</p>');
                    result = {listMode:true,title:'Популярное',tracks,summary:countLabel(tracks.length,'песня','песни','песен'),emptyTitle:'Популярные песни появятся после первых прослушиваний',emptyDescription:'Рейтинг строится по прослушиваниям музыки из общего каталога.'};
                    break;
                }
                case 'songs': case 'favorites': case 'playlist':
                    this.html('');
                    result = {listMode:true};
                    break;
                default: result = this.overview();
            }
            const hasContent = Boolean(this.content.childNodes.length);
            this.content.hidden = !hasContent;
            this.content.dataset.catalogView = view.type;
            this.app.querySelector('[data-tracks]').hidden = !result.listMode;
            if (!result.listMode) this.app.querySelector('[data-empty]').hidden = true;
            const sort = this.app.querySelector('[data-sort]');
            if (sort) sort.hidden = !result.listMode || ['album','popular','artist'].includes(view.type);
            const dropHint = this.app.querySelector('.rp-drop-hint');
            if (dropHint) dropHint.hidden = !['songs','favorites','playlist','mine'].includes(view.type);
            const input = this.app.querySelector('[data-search]');
            if (input) {
                const label = ['overview','albums','mine','new'].includes(view.type) ? 'Найти альбом или артиста' : view.type === 'genres' ? 'Найти жанр' : view.type === 'artists' ? 'Найти артиста' : 'Найти трек или исполнителя';
                input.placeholder = label;
                input.setAttribute('aria-label', label);
            }
            this.syncPlayback(current,playing);
            return result;
        }

        genresPage() {
            const genres=(this.latest.data.catalog?.genres || []).filter(item=>!this.latest.search || normalized(item.name).includes(this.latest.search));
            this.html(`<div class="rp-genre-grid">${genres.map(genre=>{
                const albums=this.latest.albums.filter(album=>(album.published || album.editable || album.browserLocal) && (album.genreIds || [Number(album.genreId)]).map(Number).includes(Number(genre.id)));
                const tracks=window.TapeRoomCollections.collection(this.latest.data,{type:'genre',id:genre.id});
                return `<button type="button" class="rp-genre-card" data-action="catalog-genre" data-id="${Number(genre.id)}"><strong>${this.escape(genre.name)}</strong><span>${this.escape(countLabel(albums.length,'альбом','альбома','альбомов'))} · ${this.escape(countLabel(tracks.length,'песня','песни','песен'))}</span></button>`;
            }).join('')}</div>`);
            return {listMode:false,title:'Жанры',summary:'Библиотека жанров'};
        }

        genrePage(id) {
            const genre=(this.latest.data.catalog?.genres || []).find(item=>Number(item.id)===Number(id));
            const albums=this.latest.albums.filter(item=>(item.published || item.editable || item.browserLocal) && (item.genreIds || [Number(item.genreId)]).map(Number).includes(Number(id)));
            const tracks=window.TapeRoomCollections.collection(this.latest.data,{type:'genre',id:Number(id)});
            this.html(`<section class="rp-catalog-section">${this.sectionHeading('Альбомы жанра','Ваша музыка и одобренные альбомы каталога')}${this.albumGrid(albums.filter(item=>this.matches(item)),'В этом жанре пока нет альбомов','Выберите жанр при создании или редактировании своего альбома.')}</section>${this.sectionHeading('Песни жанра')}`);
            return {listMode:true,title:genre?.name || 'Жанр',tracks,summary:`${countLabel(albums.length,'альбом','альбома','альбомов')} · ${countLabel(tracks.length,'песня','песни','песен')}`,emptyTitle:'В этом жанре пока нет песен',emptyDescription:'Назначьте жанр своей песне в редакторе композиции.'};
        }

        matches(album) {
            return !this.latest.search || normalized(`${album.title} ${album.artist || ''} ${album.description || ''} ${album.genre || ''}`).includes(this.latest.search);
        }

        tracksFor(album) {
            return (album?.tracks || []).map(item => this.latest.trackMap.get(Number(typeof item === 'object' ? item.id : item))).filter(Boolean);
        }

        image(source, className = '', kind = 'cover', eager = false) {
            const compact = className === 'rp-catalog-song-cover', value = this.imageSources(source,kind,compact);
            const placeholder = kind === 'artist' ? `<span class="rp-artist-placeholder" ${value.src ? 'hidden' : ''} aria-hidden="true">${this.icon('microphone')}</span>` : '';
            if (!value.src) return placeholder;
            return `<img class="${className}" ${value.srcset ? `srcset="${this.escape(value.srcset)}"` : ''} src="${this.escape(value.src)}" data-library-image="${kind}" ${compact ? 'data-compact-image' : ''} ${source ? '' : `data-default-${kind}`} alt="" loading="${eager ? 'eager' : 'lazy'}" decoding="async" width="240" height="240">${placeholder}`;
        }

        albumCover(album) {
            return album.cover || this.tracksFor(album).find(track => track.cover)?.cover || '';
        }

        albumCard(album) {
            const esc = this.escape, tracks = this.tracksFor(album);
            const date = timestamp(album.release_date), year = date ? new Date(date).getFullYear() : '';
            const total = tracks.reduce((sum,track) => sum + (Number(track.duration)||0),0);
            const meta = [year || (!album.published ? (album.moderation === 'pending' ? 'На модерации' : album.moderation === 'rejected' ? 'Отклонён' : album.browserLocal ? 'Torrent · в браузере' : 'Личный альбом') : 'Без даты релиза'), countLabel(tracks.length,'трек','трека','треков'),total ? duration(total) : ''].filter(Boolean).join(' · ');
            const active = tracks.some(track => Number(track.id) === this.latest.current) && this.latest.playing;
            return `<article data-album-id="${Number(album.id)}" class="rp-album-card${active ? ' is-playing' : ''}">
                <div class="rp-album-artwork">
                    <button type="button" class="rp-album-open" data-action="catalog-album" data-id="${Number(album.id)}" aria-label="Открыть альбом ${esc(album.title)}">${this.image(this.albumCover(album))}</button>
                    ${tracks.length ? `<button type="button" class="rp-catalog-card-play" data-action="catalog-play-album" data-id="${Number(album.id)}" aria-label="Воспроизвести альбом ${esc(album.title)}">${this.icon('play')}</button>` : ''}
                    ${album.saved ? `<span class="rp-album-saved" title="В вашей коллекции" aria-label="В вашей коллекции">${this.icon('bookmark')}</span>` : ''}
                </div>
                <button type="button" class="rp-album-title" data-action="catalog-album" data-id="${Number(album.id)}">${esc(album.title)}</button>
                ${album.artist ? `<button type="button" class="rp-album-artist" data-action="catalog-artist" data-artist="${esc(album.artist)}">${esc(album.artist)}</button>` : '<span class="rp-album-artist">Исполнитель не указан</span>'}
                ${album.genres?.length ? album.genres.map(genre=>`<button class="rp-catalog-text-button" type="button" data-action="catalog-genre" data-id="${Number(genre.id)}">${esc(genre.name)}</button>`).join('') : album.genre && !album.genreId ? `<span class="rp-album-meta">${esc(album.genre)}</span>` : album.genre ? `<button class="rp-catalog-text-button" type="button" data-action="catalog-genre" data-id="${Number(album.genreId)}">${esc(album.genre)}</button>` : ''}<span class="rp-album-meta">${esc(meta)}</span>
            </article>`;
        }

        albumGrid(albums, emptyTitle = 'Ничего не найдено', emptyDescription = 'Попробуйте другое название альбома или имя артиста.') {
            return albums.length ? `<div class="rp-album-grid">${albums.map(album => this.albumCard(album)).join('')}</div>` : this.empty(emptyTitle, emptyDescription);
        }

        empty(title, description) {
            return `<div class="rp-catalog-empty"><span class="rp-catalog-empty-icon">${this.icon('music')}</span><h3>${this.escape(title)}</h3><p>${this.escape(description)}</p></div>`;
        }

        sectionHeading(title, subtitle = '', action = '') {
            return `<div class="rp-catalog-section-heading"><div><h3>${this.escape(title)}</h3>${subtitle ? `<p>${this.escape(subtitle)}</p>` : ''}</div>${action}</div>`;
        }

        viewLink(view, title) {
            return `<button class="rp-catalog-text-button" type="button" data-action="catalog-view" data-view="${this.escape(view)}">${this.escape(title)}</button>`;
        }

        cardsPage(albums, title, summary, emptyTitle, emptyDescription, extra = '') {
            const visible = albums.filter(album => this.matches(album)).sort((a,b) => timestamp(b.created_at) - timestamp(a.created_at) || Number(b.id) - Number(a.id));
            this.html(`${extra ? `<div class="rp-catalog-collection-link">${extra}</div>` : ''}${this.albumGrid(visible, this.latest.search ? 'Ничего не найдено' : emptyTitle, this.latest.search ? 'Попробуйте другое название альбома или имя артиста.' : emptyDescription)}`);
            return {listMode:false,title,summary:`${summary} · ${countLabel(albums.length,'альбом','альбома','альбомов')}`};
        }

        popularTracks() {
            return this.latest.publicTracks.filter(track => Number(track.listen_count ?? track.plays ?? 0) > 0)
                .sort((a,b) => Number(b.listen_count ?? b.plays ?? 0) - Number(a.listen_count ?? a.plays ?? 0) || Number(b.id) - Number(a.id));
        }

        popularPreview(tracks) {
            return `<div class="rp-track-list rp-catalog-popular-list">${tracks.map((track,index)=>this.renderTrack(track,{index,source:'popular'})).join('')}</div>`;
        }

        overview() {
            const albums = this.latest.publicAlbums.filter(album => this.matches(album)).sort((a,b) => timestamp(b.release_date || b.created_at) - timestamp(a.release_date || a.created_at) || Number(b.id)-Number(a.id));
            const popular = this.popularTracks().filter(track => !this.latest.search || normalized(`${track.title} ${track.artist || ''}`).includes(this.latest.search));
            this.html(`<section class="rp-catalog-section">${this.sectionHeading('Альбомы на вашей ленте','Новые релизы и недавние добавления в каталог',this.viewLink('albums','Все альбомы'))}${this.albumGrid(albums.slice(0,6),this.latest.search ? 'Ничего не найдено' : 'Здесь появятся альбомы сайта',this.latest.search ? 'Попробуйте другое название альбома или имя артиста.' : 'Пока каталог пуст. Соберите альбом из своей музыки; создатель сайта может опубликовать его для всех.')}</section>
                <section class="rp-catalog-section">${this.sectionHeading('Чаще слушают','На основе прослушиваний в общем каталоге',this.viewLink('popular','Все песни'))}${popular.length ? this.popularPreview(popular.slice(0,5)) : `<p class="rp-catalog-note">${this.latest.search ? 'По вашему запросу песен не найдено.' : 'Популярные песни появятся после первых прослушиваний.'}</p>`}</section>`);
            return {listMode:false,title:'Обзор',summary:'Общий каталог сайта'};
        }

        newPage() {
            const dated = this.latest.publicAlbums.filter(album => timestamp(album.release_date) && this.matches(album)).sort((a,b) => timestamp(b.release_date)-timestamp(a.release_date));
            const added = this.latest.publicAlbums.filter(album => !timestamp(album.release_date) && this.matches(album)).sort((a,b) => timestamp(b.created_at)-timestamp(a.created_at) || Number(b.id)-Number(a.id));
            this.html((dated.length ? `<section class="rp-catalog-section">${this.sectionHeading('Новые релизы','Альбомы по дате выпуска')}${this.albumGrid(dated)}</section>` : '') +
                (added.length ? `<section class="rp-catalog-section">${this.sectionHeading('Недавно добавлены','Дата релиза не указана — порядок по добавлению на сайт')}${this.albumGrid(added)}</section>` : '') ||
                this.empty(this.latest.search ? 'Ничего не найдено' : 'Новых альбомов пока нет',this.latest.search ? 'Попробуйте другое название альбома или имя артиста.' : 'Новые релизы и недавно добавленные альбомы появятся здесь после публикации.'));
            return {listMode:false,title:'Новинки',summary:'Релизы и добавления'};
        }

        artists() {
            const groups = new Map();
            const profiles = new Map((this.latest.data.catalog?.artistProfiles || []).map(profile => [normalized(profile.key || profile.name),profile]));
            const ensure = value => {
                const name = String(value || '').trim();
                if (!name) return null;
                const key = normalized(name);
                if (!groups.has(key)) groups.set(key,{name,albums:[],tracks:[],cover:profiles.get(key)?.image || '',profile:profiles.get(key)});
                return groups.get(key);
            };
            for (const album of this.latest.publicAlbums) {
                const artist = ensure(album.artist);
                if (!artist) continue;
                artist.albums.push(album);
                for (const track of this.tracksFor(album)) if (!artist.tracks.some(item=>Number(item.id)===Number(track.id))) artist.tracks.push(track);
            }
            for (const track of this.latest.publicTracks) {
                const artist = ensure(track.artist);
                if (!artist) continue;
                if (!artist.tracks.some(item=>Number(item.id)===Number(track.id))) artist.tracks.push(track);
            }
            return [...groups.values()].sort((a,b) => a.name.localeCompare(b.name,'ru'));
        }

        artistsPage() {
            const artists = this.artists(), visible = artists.filter(artist => !this.latest.search || normalized(artist.name).includes(this.latest.search));
            this.html(visible.length ? `<div class="rp-artist-grid">${visible.map(artist => `<button type="button" data-artist-key="${this.escape(artist.name)}" class="rp-artist-card" data-action="catalog-artist" data-artist="${this.escape(artist.name)}"><span class="rp-artist-artwork">${this.image(artist.cover,'','artist')}</span><strong>${this.escape(artist.name)}</strong><span>${this.escape([countLabel(artist.albums.length,'альбом','альбома','альбомов'),countLabel(artist.tracks.length,'песня','песни','песен')].join(' · '))}</span></button>`).join('')}</div>` : this.empty(this.latest.search ? 'Артист не найден' : 'Артисты появятся вместе с музыкой',this.latest.search ? 'Попробуйте другое имя.' : 'Укажите исполнителей в песнях и альбомах общего каталога.'));
            return {listMode:false,title:'Артисты',summary:countLabel(artists.length,'артист','артиста','артистов')};
        }

        albumDetail(album) {
            if (!album) {
                this.html(this.empty('Альбом больше недоступен','Он мог быть удалён или снят с публикации.') + this.viewLink('albums','Вернуться к альбомам'));
                return {listMode:false,title:'Альбом',summary:''};
            }
            const esc = this.escape, tracks = this.tracksFor(album), total = tracks.reduce((sum,track) => sum + (Number(track.duration)||0),0);
            const year = timestamp(album.release_date) ? new Date(timestamp(album.release_date)).getFullYear() : '';
            const meta = [year,album.genre,countLabel(tracks.length,'трек','трека','треков'),total ? duration(total) : ''].filter(Boolean).join(' · ');
            const canPublish = this.canPublish || this.latest.canPublish || Boolean(this.latest.data.canPublish);
            this.html(`<div class="rp-catalog-detail-back">${this.viewLink(album.published ? 'albums' : 'mine','К альбомам')}</div>
                <section class="rp-album-detail"><div class="rp-album-detail-cover">${this.image(this.albumCover(album),'','cover',true)}</div><div class="rp-album-detail-info">
                <span class="rp-catalog-eyebrow">${album.published ? 'Альбом · каталог сайта' : 'Личный альбом'}</span><h3>${esc(album.title)}</h3>
                ${album.browserLocal && album.artist ? `<span class="rp-catalog-artist-link">${esc(album.artist)}</span>` : album.artist ? `<button class="rp-catalog-artist-link" type="button" data-action="catalog-artist" data-artist="${esc(album.artist)}">${esc(album.artist)}</button>` : ''}<p class="rp-album-detail-meta">${esc(meta)}</p>${window.TapeRoomQuality.albumBadge(tracks)}
                ${album.editable && album.moderation !== 'draft' ? `<p class="rp-catalog-note">${esc(album.moderation === 'pending' ? 'Альбом ожидает проверки. В общем каталоге он появится после одобрения.' : album.moderation === 'rejected' ? 'Публикация отклонена. Исправьте альбом и отправьте его повторно.' : 'Публикация одобрена.')} ${esc(album.moderationNote || '')}</p>` : ''}${album.description ? `<p class="rp-album-description">${esc(album.description)}</p>` : ''}
                <div class="rp-album-detail-actions"><button type="button" class="rp-button rp-button--primary rp-catalog-play" data-action="catalog-play-album" data-id="${Number(album.id)}" ${tracks.length ? '' : 'disabled'}>${this.icon('play')}<span>Слушать альбом</span></button>${album.browserLocal ? `<button class="rp-button" type="button" data-torrent-edit="${esc(album.torrentHash)}">${this.icon('edit')}Изменить torrent</button>` : `<button type="button" class="rp-button rp-catalog-save${album.saved ? ' is-saved' : ''}" data-action="catalog-save" data-id="${Number(album.id)}" aria-pressed="${Boolean(album.saved)}">${this.icon('bookmark')}<span>${album.saved ? 'В коллекции' : 'В коллекцию'}</span></button>`}<button class="rp-button" type="button" data-action="share-album" data-id="${Number(album.id)}">${this.icon('share')}Поделиться</button>${album.editable ? `<button class="rp-icon-button rp-album-edit" type="button" data-action="catalog-edit" data-id="${Number(album.id)}" title="Изменить альбом" aria-label="Изменить альбом">${this.icon('edit')}<span class="rp-album-edit-label">Изменить</span></button>` : ''}</div>
                ${album.editable ? `<div class="rp-album-detail-management">${canPublish ? `<button type="button" class="rp-catalog-publish" data-action="catalog-publish" data-id="${Number(album.id)}" data-published="${album.submitted ? 0 : 1}">${album.submitted ? 'Снять с публикации' : album.requiresModeration===false ? 'Опубликовать альбом' : 'Отправить на модерацию'}</button>` : ''}<button type="button" class="rp-catalog-delete" data-action="catalog-delete" data-id="${Number(album.id)}">${this.icon('trash')}Удалить альбом</button></div>` : ''}
                </div></section><div class="rp-catalog-track-heading"><h3>Песни альбома</h3><span>Порядок альбома</span></div>`);
            return {listMode:true,title:'Альбом',summary:'',tracks,emptyTitle:'В альбоме пока нет песен',emptyDescription:album.editable ? 'Откройте редактор альбома и выберите песни из своей библиотеки.' : 'Песни появятся после добавления владельцем альбома.'};
        }

        artistDetail(name) {
            const artist = this.artists().find(item => normalized(item.name) === normalized(name));
            if (!artist) {
                this.html(this.empty('В каталоге нет музыки этого артиста','Личные альбомы и песни видны только в вашей коллекции.') + this.viewLink('artists','Все артисты'));
                return {listMode:false,title:name || 'Артист',summary:''};
            }
            const albums = artist.albums.filter(album => this.matches(album));
            const canEdit = this.canPublish || this.latest.canPublish || Boolean(this.latest.data.catalog?.canPublish);
            this.html(`<div class="rp-catalog-detail-back">${this.viewLink('artists','Все артисты')}</div><div class="rp-artist-detail"><span class="rp-artist-detail-cover">${this.image(artist.cover,'','artist',true)}</span><div><span class="rp-catalog-eyebrow">Артист · каталог сайта</span><h3>${this.escape(artist.name)}</h3><p>${this.escape(countLabel(artist.albums.length,'альбом','альбома','альбомов'))} · ${this.escape(countLabel(artist.tracks.length,'песня','песни','песен'))}</p>${canEdit ? `<button type="button" class="rp-button" data-action="artist-edit" data-artist="${this.escape(artist.name)}">${this.icon('edit')}Изображение артиста</button>` : ''}</div></div>
                ${albums.length ? `<section class="rp-catalog-section">${this.sectionHeading('Альбомы')}${this.albumGrid(albums)}</section>` : ''}${artist.tracks.length ? `<div class="rp-catalog-track-heading"><h3>Песни артиста</h3></div>` : ''}`);
            return {listMode:artist.tracks.length > 0,title:artist.name,summary:'Дискография',tracks:artist.tracks,emptyTitle:'Песни не найдены',emptyDescription:'Попробуйте другое название.'};
        }
    }

    window.TapeRoomCatalogUI = TapeRoomCatalogUI;
})();
