(function (root) {
    'use strict';
    const idOf = value => Number.isSafeInteger(Number(value)) && Number(value) > 0 ? Number(value) : 0;
    const fields = ['id','title','artist','duration','mime','url','cover','chapters','favorite','listen_count','created_at','access','download_url','purchase_url','source','genres','tags','album'];
    function playableTrack(value) {
        if (!value || !idOf(value.id) || !value.url || value.access?.stream === false) return null;
        let url;
        try { url = new URL(value.url, root.location?.href || 'http://localhost/'); } catch (_) { return null; }
        if (!['http:','https:'].includes(url.protocol) || root.location && url.origin !== root.location.origin) return null;
        const track = {}; for (const key of fields) if (Object.hasOwn(value,key)) track[key] = value[key];
        track.id = idOf(value.id); track.url = url.href; return track;
    }
    function appendTracks(previous, additions) {
        const items = new Map();
        for (const value of [...previous,...additions]) { const track = playableTrack(value); if (track) items.set(track.id,track); }
        return [...items.values()];
    }
    async function jsonRequest(url, options = {}) {
        const response = await root.fetch(url,{credentials:'same-origin',cache:'no-store',...options,headers:{Accept:'application/json',...options.headers}});
        let result; try { result = await response.json(); } catch (_) { throw new Error('Сервер не смог получить музыкальные данные.'); }
        if (!response.ok || result.status !== true) throw new Error(result.message || 'Не удалось получить музыку. Попробуйте ещё раз.');
        return result;
    }
    const icon = (name, document = root.document) => {
        const svg=document.createElementNS('http://www.w3.org/2000/svg','svg'),use=document.createElementNS('http://www.w3.org/2000/svg','use');
        svg.setAttribute('class','rp-icon');svg.setAttribute('aria-hidden','true');use.setAttribute('href',`#rp-i-${name}`);svg.append(use);return svg;
    };

    /** The first song starts within its click; later pages only extend the same queue. */
    class TapeRoomAlbumQueue {
        constructor({core,play,fetchPage,onError=()=>{}}) { this.core=core;this.play=play;this.fetchPage=fetchPage;this.onError=onError;this.ticket=0;this.state=null; }
        cancel() { this.ticket++;this.state?.controller.abort();this.state=null; }
        start(album,event) {
            const first=playableTrack(album?.first_track);if (!first) { this.onError('В альбоме нет доступных для прослушивания записей.');return false; }
            if (this.state?.id===idOf(album.id) && this.state.queue.some(track=>track.id===this.core.snapshot().current)) { this.core.toggle('user',event);return true; }
            this.cancel();
            const state=this.state={id:idOf(album.id),ticket:this.ticket,queue:[first],page:1,busy:false,starting:true,done:false,controller:new AbortController()};
            // Do not await metadata, preferences or a network call before native play().
            try { this.play([first],first.id,event); } finally { state.starting=false; }
            this.pull(state);return true;
        }
        maybe(snapshot) {
            const state=this.state;if (!state || state.starting || state.busy || state.done) return;
            const index=state.queue.findIndex(track=>track.id===snapshot.current);
            if (index<0) { this.cancel();return; }
            if (index>=state.queue.length-3) this.pull(state);
        }
        async pull(state=this.state) {
            if (!state || state.busy || state.done || state!==this.state) return;
            state.busy=true;
            try {
                const result=await this.fetchPage(state.id,state.page,state.controller.signal);
                if (state!==this.state || state.ticket!==this.ticket) return;
                const album=result.album || result,raw=Array.isArray(album.tracks)?album.tracks.slice(0,48):[];
                const additions=raw.map(playableTrack).filter(Boolean),pagination=album.tracks_pagination || album.pagination || {};
                const previous=state.queue;
                state.queue=appendTracks(previous,additions);
                const current=this.core.snapshot().current;
                if (!previous.some(track=>track.id===current)) { this.cancel();return; }
                const pages=Math.max(0,Number(pagination.total_pages) || 0),page=Math.max(state.page,Number(pagination.page) || state.page);
                state.done=!additions.length || !pages || page>=pages;state.page=page+1;
                // A zero current argument keeps audio.src, position and generation intact.
                this.core.setQueue(state.queue);
            } catch (error) {
                if (state===this.state && error.name!=='AbortError') { state.done=true;this.onError(error.message); }
            } finally {
                state.busy=false;
                if (state===this.state && !state.done) this.maybe(this.core.snapshot());
            }
        }
    }

    class TapeRoomPublicInterface {
        constructor(document=root.document) {
            this.document=document;this.tracks=new Map();this.albums=new Map();this.collectionJobs=new Map();this.recommendations=[];this.config={};
            this.onClick=event=>this.click(event);this.onNavigation=()=>this.mount(this.document.querySelector('[data-music-page]'));this.onTheme=()=>this.images();
            document.addEventListener('click',this.onClick);document.addEventListener('tape-room:navigation',this.onNavigation);document.addEventListener('tape-room:theme',this.onTheme);
        }
        connect() {
            const core=root.TapeRoomPublic?.core || root.TapeRoomPlaybackCore?.instance;
            if (!core || core===this.core) return;
            this.unsubscribe?.();this.albumQueue?.cancel();this.core=core;
            this.albumQueue=new TapeRoomAlbumQueue({core,play:(tracks,id,event)=>{if(root.TapeRoomPublic)root.TapeRoomPublic.play(tracks,id,event);else{core.setQueue(tracks);core.select(id,'user',event);}},fetchPage:(id,page,signal)=>{const url=this.endpoint(`albums/${id}`);url.searchParams.set('streamable','1');url.searchParams.set('page',String(page));url.searchParams.set('per_page','48');return jsonRequest(url,{signal});},onError:message=>this.message(message)});
            this.unsubscribe=core.subscribe(state=>{this.updatePlaying(state);this.albumQueue.maybe(state);});
        }
        mount(page) {
            for (const controller of this.recommendations) controller.abort();this.recommendations=[];
            this.page=page;if (!page) return;
            const node=page.querySelector('script[type="application/json"][data-music-data]');
            let data;try { data=JSON.parse(node?.textContent || '{}'); } catch (_) { this.message('Не удалось прочитать музыкальную страницу.');return; }
            this.config=data.config || {};this.tracks.clear();this.albums.clear();
            for (const item of (Array.isArray(data.tracks)?data.tracks:[]).slice(0,48)) { const track=playableTrack(item);if(track)this.tracks.set(track.id,track); }
            for (const item of (Array.isArray(data.albums)?data.albums:[]).slice(0,48)) if(idOf(item.id))this.albums.set(idOf(item.id),item);
            this.connect();this.images();if(this.core)this.updatePlaying(this.core.snapshot());this.loadRecommendations();
        }
        endpoint(path) {
            const url=new URL(`${String(this.config.api || '/music/api').replace(/\/$/,'')}/${path}`,root.location.href);
            if(url.origin!==root.location.origin || !['http:','https:'].includes(url.protocol))throw new Error('Недопустимый адрес музыкального API.');return url;
        }
        message(text) {
            if(!this.page?.isConnected)return;
            let node=this.page.querySelector('[data-music-error],[data-music-message]');
            if(!node){node=this.document.createElement('p');node.className='tr-message';node.dataset.musicMessage='';node.setAttribute('role','status');this.page.append(node);}
            node.textContent=text || '';node.hidden=!text;
        }
        click(event) {
            const control=event.target?.closest?.('[data-public-play],[data-public-album],[data-public-collection],[data-public-preferences]');
            if(!control || !this.page?.contains(control) || control.disabled || event.defaultPrevented)return;
            this.connect();
            if(control.hasAttribute('data-public-preferences')){(root.TapeRoomPublic?.preferences || root.TapeRoomPreferences?.instance)?.open();return;}
            if(control.hasAttribute('data-public-collection')) { this.collection(control);return; }
            if(!this.core){this.message('Плеер ещё загружается. Попробуйте ещё раз.');return;}
            if(control.hasAttribute('data-public-album')) { this.albumQueue.start(this.albums.get(idOf(control.dataset.publicAlbum)),event);return; }
            const id=idOf(control.dataset.publicPlay),track=this.tracks.get(id);if(!track){this.message('Запись недоступна для прослушивания.');return;}
            if(this.core.snapshot().current===id){this.core.toggle('user',event);return;}
            this.albumQueue.cancel();this.message('');
            if(root.TapeRoomPublic)root.TapeRoomPublic.play([...this.tracks.values()],id,event);
            else{this.core.setQueue([...this.tracks.values()]);this.core.select(id,'user',event);}
        }
        updatePlaying(state) {
            if(!this.page)return;
            for(const control of this.page.querySelectorAll('[data-public-play],[data-public-album]')) {
                const album=this.albums.get(idOf(control.dataset.publicAlbum));
                const selected=control.hasAttribute('data-public-play')?idOf(control.dataset.publicPlay)===state.current:this.albumQueue?.state?.id===idOf(album?.id) && this.albumQueue.state.queue.some(track=>track.id===state.current);
                const playing=selected && state.playing;control.classList.toggle('is-playing',playing);control.setAttribute('aria-pressed',String(Boolean(playing)));
                control.querySelector('use')?.setAttribute('href',playing?'#rp-i-pause':'#rp-i-play');
                if(control.classList.contains('tr-button')){const text=[...control.childNodes].find(node=>node.nodeType===3 && node.textContent.trim());if(text){if(!control.dataset.originalPlayLabel)control.dataset.originalPlayLabel=text.textContent;text.textContent=playing?' Пауза':control.dataset.originalPlayLabel;}}
                const label=playing?'Пауза':control.hasAttribute('data-public-album')?'Воспроизвести альбом':'Воспроизвести композицию';
                const title=album?.title || this.tracks.get(idOf(control.dataset.publicPlay))?.title || '';
                control.setAttribute('aria-label',label+(title?' — '+title:''));
            }
            for(const row of this.page.querySelectorAll('[data-public-track],[data-track-row]'))row.classList.toggle('is-current',idOf(row.dataset.publicTrack || row.dataset.trackRow)===state.current);
        }
        async collection(control) {
            const action=control.dataset.publicCollection,id=idOf(control.dataset.id);if(!id || !['album.save','track.favorite'].includes(action))return;
            if(this.config.viewer!==true || !this.config.csrf){this.message('Войдите в аккаунт, чтобы сохранить музыку.');return;}
            const key=`${action}:${id}`;if(this.collectionJobs.has(key))return;
            const value=control.getAttribute('aria-pressed')!=='true',disabled=new Map();
            for(const button of this.page.querySelectorAll('[data-public-collection]'))if(button.dataset.publicCollection===action && idOf(button.dataset.id)===id){disabled.set(button,button.disabled);button.disabled=true;}
            const form=new FormData();form.set('action',action);form.set('id',String(id));form.set('value',value?'1':'0');form.set('needCSRFToken',this.config.csrf);
            const job={};this.collectionJobs.set(key,job);
            try {
                const result=await jsonRequest(this.endpoint('collection'),{method:'POST',headers:{'X-CSRF-Token':this.config.csrf},body:form});
                const saved=result.value===true;const item=action==='album.save'?this.albums.get(id):this.tracks.get(id);if(item)item[action==='album.save'?'saved':'favorite']=saved;
                for(const button of this.page?.querySelectorAll('[data-public-collection]') || [])if(button.dataset.publicCollection===action && idOf(button.dataset.id)===id){
                    button.setAttribute('aria-pressed',String(saved));button.classList.toggle('is-saved',saved);
                    const title=action==='album.save'?(saved?'Убрать альбом из коллекции':'Сохранить альбом в коллекцию'):(saved?'Убрать из избранного':'Добавить в избранное');button.setAttribute('aria-label',title);button.title=title;
                    if(button.classList.contains('tr-button')){const label=button.querySelector('span');if(label)label.textContent=action==='album.save'?(saved?'В коллекции':'В коллекцию'):(saved?'В избранном':'В избранное');}
                }
            } catch(error) { this.message(error.message); }
            finally { if(this.collectionJobs.get(key)===job)this.collectionJobs.delete(key);for(const [button,old]of disabled)if(button.isConnected)button.disabled=old; }
        }
        images() {
            if(!this.page)return;
            const theme=this.document.documentElement.dataset.theme || this.document.documentElement.dataset.bsTheme || 'light';
            for(const image of this.page.querySelectorAll('[data-music-image]'))this.image(image,theme);
        }
        image(image,theme) {
            const kind=image.dataset.musicImage==='artist'?'artist':'cover',source=image.dataset.imageSource || '',compact=image.classList.contains('tr-track-cover');
            const fallback=(kind==='artist'?this.config.defaultArtists:this.config.defaultCovers)?.[theme] || (kind==='cover'?this.config.defaultCover:'') || '';
            image.hidden=false;
            image.onerror=()=>{image.onerror=null;image.removeAttribute('srcset');if(fallback && image.getAttribute('src')!==fallback)image.src=fallback;else image.hidden=true;};
            if(root.TapeRoomImages)root.TapeRoomImages.apply(image,source,this.config,theme,kind,compact);
            else if(source || fallback)image.src=source || fallback;else image.hidden=true;
        }
        async loadRecommendations() {
            if(!this.page || !this.config.flags?.discovery)return;
            const page=this.page;
            for(const container of page.querySelectorAll('[data-music-recommendations]')) {
                const controller=new AbortController();this.recommendations.push(controller);const id=idOf(container.dataset.trackId);
                if(!container.querySelector('h2')){const heading=this.document.createElement('header'),title=this.document.createElement('h2');heading.className='tr-heading';heading.style.marginTop='28px';title.textContent=id?'Похожие композиции':'Рекомендации';heading.append(title);container.append(heading);}
                const url=this.endpoint('recommendations');url.searchParams.set('limit','8');if(id)url.searchParams.set('track_id',String(id));
                try {
                    const result=await jsonRequest(url,{signal:controller.signal});if(page!==this.page || !container.isConnected)return;
                    const outside=new Set();for(const node of page.querySelectorAll('[data-public-track],[data-public-play]'))if(!container.contains(node)){const id=idOf(node.dataset.publicTrack || node.dataset.publicPlay);if(id)outside.add(id);}
                    let list=container.querySelector('[data-recommendation-items]') || container.querySelector('.tr-track-list,.tr-grid');
                    if(list){for(const node of list.querySelectorAll('[data-public-track],[data-public-play]')){const id=idOf(node.dataset.publicTrack || node.dataset.publicPlay);if(id && !outside.has(id))this.tracks.delete(id);}}
                    else{list=this.document.createElement('div');container.append(list);}
                    list.className='tr-grid';list.dataset.recommendationItems='';list.replaceChildren();const shown=new Set(outside);
                    for(const value of (Array.isArray(result.items)?result.items:[]).slice(0,24)){const track=playableTrack(value);if(!track || shown.has(track.id))continue;shown.add(track.id);this.tracks.set(track.id,track);list.append(this.recommendationCard(track));}
                    container.hidden=!list.children.length;this.images();if(this.core)this.updatePlaying(this.core.snapshot());
                } catch(error){if(error.name!=='AbortError' && page===this.page){const message=this.document.createElement('p');message.className='tr-message';message.textContent='Рекомендации сейчас недоступны.';container.append(message);}}
            }
        }
        recommendationCard(track) {
            const article=this.document.createElement('article');article.className='tr-card';const art=this.document.createElement('div');art.className='tr-card-image';
            const link=this.document.createElement('a');link.className='tr-card-art';link.href=`${String(this.config.base || '/music').replace(/\/$/,'')}/track/${track.id}`;link.dataset.musicLink='';
            const image=this.document.createElement('img');image.width=320;image.height=320;image.loading='lazy';image.decoding='async';image.alt=track.title || 'Обложка композиции';image.dataset.musicImage='cover';image.dataset.imageSource=track.cover || '';link.append(image);art.append(link);
            const play=this.document.createElement('button');play.type='button';play.className='tr-play-button';play.dataset.publicPlay=String(track.id);play.setAttribute('aria-label','Воспроизвести композицию');play.append(icon('play',this.document));art.append(play);
            if(this.config.viewer===true){const favorite=this.document.createElement('button');favorite.type='button';favorite.className='tr-save-button'+(track.favorite?' is-saved':'');favorite.dataset.publicCollection='track.favorite';favorite.dataset.id=String(track.id);favorite.setAttribute('aria-pressed',String(Boolean(track.favorite)));favorite.setAttribute('aria-label',track.favorite?'Убрать из избранного':'Добавить в избранное');favorite.append(icon('heart',this.document));art.append(favorite);}
            const title=this.document.createElement('a');title.className='tr-card-title';title.href=link.href;title.dataset.musicLink='';title.textContent=track.title || 'Композиция';
            const artist=this.document.createElement('span');artist.className='tr-card-meta';artist.textContent=track.artist || '';article.append(art,title,artist);return article;
        }
        destroy() {this.unsubscribe?.();this.albumQueue?.cancel();for(const job of this.recommendations)job.abort();this.document.removeEventListener('click',this.onClick);this.document.removeEventListener('tape-room:navigation',this.onNavigation);this.document.removeEventListener('tape-room:theme',this.onTheme);}
    }
    const api={TapeRoomAlbumQueue,TapeRoomPublicInterface,playableTrack,appendTracks};
    if(typeof module!=='undefined' && module.exports)module.exports=api;
    root.TapeRoomPublicInterface=TapeRoomPublicInterface;
    if(root.document && !root.TapeRoomPublicUI){const start=()=>{const ui=root.TapeRoomPublicUI=new TapeRoomPublicInterface();ui.mount(root.document.querySelector('[data-music-page]'));};if(root.document.readyState==='loading')root.document.addEventListener('DOMContentLoaded',start,{once:true});else start();}
})(typeof window!=='undefined'?window:globalThis);
