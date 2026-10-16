(() => {
    'use strict';
    const player=document.querySelector('[data-player]'), modal=document.getElementById('tr-torrent-modal');
    if(!player || !modal)return;
    const config=JSON.parse(document.querySelector('[data-player-config]').textContent), $=selector=>modal.querySelector(selector);
    if(config.authenticated===false)return;
    const {MemoryStore,audioFile,sortAudioFiles,sourceProfile,connectionStatus,defaultTrackers}=window.TapeRoomTorrentCore, audio=player.querySelector('[data-audio]');
    const dialog=bootstrap.Modal.getOrCreateInstance(modal), error=$('[data-torrent-error]'), selection=$('[data-torrent-selection]');
    const escape=value=>String(value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const size=bytes=>`${(bytes/1048576).toFixed(1)} МБ`;
    const genres=config.state.catalog?.genres||[], images=new Map();
    let records=[], db, client, server, workerReady, clientId, current=null, active=null, epoch=0, metadataJob, statusTimer;
    const failure=message=>{error.textContent=message;error.hidden=false;};
    const id=()=>4e15+Math.floor(Math.random()*1e14);
    const image=blob=>{if(!blob)return '';if(!images.has(blob))images.set(blob,URL.createObjectURL(blob));return images.get(blob);};
    const supported=()=>{
        if(!window.isSecureContext || !('serviceWorker' in navigator))throw new Error('Для torrent нужен HTTPS или localhost и поддержка Service Worker.');
        if(!window.WebTorrent?.WEBRTC_SUPPORT)throw new Error('В этом браузере WebRTC недоступен. Откройте сайт в актуальном Safari, Chrome или Firefox.');
    };
    const initialized=new Promise((resolve,reject)=>{
        const request=indexedDB.open('tape-room-browser-torrents',1);
        request.onupgradeneeded=()=>request.result.createObjectStore('libraries');
        request.onerror=()=>reject(new Error('Браузер запретил локальное хранилище. Разрешите хранение данных для этого сайта.'));
        request.onsuccess=()=>{
            db=request.result;db.onversionchange=()=>db.close();
            const read=db.transaction('libraries').objectStore('libraries').get(config.storageKey);
            read.onsuccess=()=>{records=Array.isArray(read.result)?read.result:[];records.forEach(record=>record.tracks=sortAudioFiles(record.tracks||[]));publish();resolve();};read.onerror=()=>reject(read.error);
        };
    });
    initialized.catch(exception=>failure(exception.message));
    async function persist(){
        await initialized;
        await new Promise((resolve,reject)=>{
            const tx=db.transaction('libraries','readwrite');tx.objectStore('libraries').put(records,config.storageKey);
            tx.oncomplete=resolve;tx.onerror=()=>reject(new Error('Не удалось сохранить torrent в браузере. Возможно, закончилось место.'));tx.onabort=tx.onerror;
        });
        publish();recent();
    }
    const genreData=item=>{const ids=(item.genreIds || (item.genreId?[item.genreId]:[])).map(Number),selected=ids.map(id=>genres.find(genre=>Number(genre.id)===id)).filter(Boolean);return {genreIds:ids,genres:selected,genreId:ids[0]||0,genre:selected.map(item=>item.name).join(', ')||item.genre||''};};
    function library(){
        return {albums:records.map(record=>({id:record.id,title:record.title,artist:record.artist,...genreData(record),
            tracks:record.tracks.map(track=>track.id),saved:true,editable:false,published:false,browserLocal:true,torrentHash:record.hash,cover:image(record.cover),
            description:'Torrent · хранится в этом браузере. Музыка поступает напрямую от участников раздачи.'})),
        tracks:records.flatMap(record=>record.tracks.map(track=>({...track,source:'torrent',browserLocal:true,torrentHash:record.hash,shared:false,cover:image(track.cover||record.cover),
            artist:track.artist||record.artist,...genreData(track.genreIds?.length||track.genreId?track:record),url:'',chapters:[]})))};
    }
    function publish(select=0){player.dispatchEvent(new CustomEvent('tape-room:browser-library',{detail:{library:library(),select}}));}
    function recent(){
        $('[data-torrent-recent]').hidden=!records.length;
        $('[data-torrent-jobs]').innerHTML=records.map(record=>`<div class="tr-torrent-job"><button class="btn btn-outline-secondary text-start flex-grow-1" type="button" data-torrent-edit="${record.hash}"><span>${escape(record.title)}</span><small>${record.tracks.length} песен · в этом браузере</small></button><button class="btn btn-outline-secondary" type="button" data-torrent-remove="${record.hash}" aria-label="Убрать torrent ${escape(record.title)}"><i class="ci-trash tr-icon" aria-hidden="true"></i></button></div>`).join('');
    }
    async function ensureWorker(connect=true){
        if(connect)supported();
        if(!workerReady)workerReady=(async()=>{
            const registration=await navigator.serviceWorker.register(config.torrent.worker,{scope:config.torrent.scope,updateViaCache:'none'});
            await registration.update();
            const wait=()=>new Promise((resolve,reject)=>{
                const timeout=setTimeout(()=>{cleanup();reject(new Error('Не удалось включить потоковое воспроизведение. Перезагрузите страницу.'));},15000);
                const check=()=>{if(registration.active?.scriptURL===new URL(config.torrent.worker,location.href).href && navigator.serviceWorker.controller?.scriptURL===registration.active.scriptURL){cleanup();resolve(registration);}};
                const cleanup=()=>{clearTimeout(timeout);navigator.serviceWorker.removeEventListener('controllerchange',check);registration.removeEventListener('updatefound',watch);};
                const watch=()=>{registration.installing?.addEventListener('statechange',check);};
                navigator.serviceWorker.addEventListener('controllerchange',check);registration.addEventListener('updatefound',watch);watch();check();
            });
            return wait();
        })().catch(exception=>{workerReady=null;throw exception;});
        const registration=await workerReady;
        if(!connect)return registration;
        if(!clientId)clientId=await new Promise((resolve,reject)=>{const channel=new MessageChannel(),timer=setTimeout(()=>reject(new Error('Не удалось связать плеер с браузерным потоком.')),5000);channel.port1.onmessage=event=>{clearTimeout(timer);channel.port1.close();resolve(event.data);};navigator.serviceWorker.controller.postMessage({type:'tape-room-client-id'},[channel.port2]);});
        if(!client){
            client=new WebTorrent({dht:false,lsd:false,utPex:false,natUpnp:false,natPmp:false,maxConns:12,tracker:{announce:defaultTrackers}});
            client.on('error',()=>failure('Ошибка torrent-соединения. Остановите torrent и попробуйте снова.'));
            server=client.createServer({controller:registration,origin:location.origin});
        }
        return client;
    }
    function release(){
        epoch++;metadataJob?.abort();metadataJob=null;
        if(active){const old=active;active=null;if(!old.torrent.destroyed)old.torrent.destroy({destroyStore:true},()=>{});}
        clearInterval(statusTimer);statusTimer=null;status();
    }
    function status(){
        const section=$('[data-torrent-progress]');section.hidden=!active;
        if(!active)return;
        const torrent=active.torrent, elapsed=performance.now()-active.started;
        const diagnosis=connectionStatus(torrent,{elapsed,online:navigator.onLine,audioError:audio.error?.code||0,paused:audio.paused,ready:audio.readyState,secure:location.protocol==='https:'});
        active.diagnosis=diagnosis;
        $('[data-torrent-progress-name]').textContent=active.title;
        $('[data-torrent-status]').textContent=active.failed||diagnosis.message;
        $('[data-torrent-speed]').textContent=`${torrent.numPeers} соединений · ${size(torrent.downloadSpeed||0)}/с · получено ${size(torrent.received||torrent.downloaded||0)} · в буфере ${size(torrent.tapeRoomBuffer?.bytes||0)}`;
    }
    async function resolve(track){
        await initialized;const record=records.find(item=>item.hash===track.torrentHash);
        if(!record)throw new Error('Этот torrent удалён из браузера.');
        release();const version=epoch, instance=await ensureWorker();if(version!==epoch)throw new DOMException('Песня переключена','AbortError');
        const torrent=instance.add(record.metainfo,{store:MemoryStore,storeOpts:{maxBytes:config.torrent.bufferBytes},storeCacheSlots:0,destroyStoreOnDestroy:true,deselect:true,strategy:'sequential'});
        active={torrent,title:track.title,started:performance.now(),failed:''};status();statusTimer=setInterval(status,1000);
        torrent.on('error',()=>{if(active?.torrent===torrent){active.failed='Раздача недоступна. Остановите torrent и попробуйте снова.';status();}});
        torrent.on('warning',exception=>{if(active?.torrent===torrent)active.warning=exception.message;});
        await new Promise((done,reject)=>{
            const timer=setTimeout(()=>reject(new Error('Не удалось прочитать torrent.')),15000);
            torrent.once('ready',()=>{clearTimeout(timer);done();});torrent.once('error',exception=>{clearTimeout(timer);reject(exception);});torrent.once('close',()=>{clearTimeout(timer);reject(new DOMException('Torrent остановлен','AbortError'));});
        });
        if(version!==epoch)throw new DOMException('Песня переключена','AbortError');
        const sources=sourceProfile(torrent,location.protocol==='https:');
        if(!sources.available){active.failed=connectionStatus(torrent,{secure:location.protocol==='https:'}).message;status();throw new Error(active.failed);}
        const file=torrent.files[track.fileIndex];if(!file || !audioFile(file))throw new Error('Формат или размер песни не поддерживается.');
        readMetadata(record,track.id,file,version);
        return `${file.streamURL}?trClient=${encodeURIComponent(clientId)}`;
    }
    async function readMetadata(record,trackId,file,version){
        const target=record.tracks.find(track=>track.id===trackId);if(!target || target.tagsRead)return;
        const controller=new AbortController();metadataJob=controller;const timer=setTimeout(()=>controller.abort(),25000);
        try{
            const tags=await window.TapeRoomTorrentMetadata.read(file,controller.signal);
            if(controller.signal.aborted || version!==epoch || !records.includes(record))return;
            if(!target.edited){target.title=tags.title||target.title;target.artist=tags.artist||target.artist;target.genre=tags.genre||target.genre;}
            target.duration=Number(tags.duration)||target.duration;
            if(tags.genre && !target.genreId && !target.edited){target.genreId=Number(genres.find(genre=>genre.name.toLocaleLowerCase()===tags.genre.toLocaleLowerCase())?.id)||0;target.genreIds=target.genreId?[target.genreId]:[];}target.cover=target.cover||tags.cover;target.tagsRead=true;
            if(!record.edited){record.title=tags.album||record.title;record.artist=tags.albumartist||tags.artist||record.artist;record.genre=tags.genre||record.genre;if(!record.genreId){record.genreId=target.genreId||0;record.genreIds=target.genreIds||[];}record.cover=record.cover||tags.cover;}
            await persist();
        }catch(exception){if(version===epoch && active)active.metadataError=exception.message;}
        finally{clearTimeout(timer);if(metadataJob===controller)metadataJob=null;}
    }
    async function inspect(metainfo){
        supported();
        const parser=new WebTorrent({tracker:false,dht:false,lsd:false,natUpnp:false,natPmp:false});
        try{return await new Promise((done,reject)=>{
            const timer=setTimeout(()=>reject(new Error('Не удалось прочитать файл torrent.')),8000);
            parser.on('error',()=>{clearTimeout(timer);reject(new Error('Повреждённый или неподдерживаемый .torrent.'));});
            const torrent=parser.add(metainfo,{store:MemoryStore,storeCacheSlots:0,deselect:true});
            torrent.on('error',()=>{clearTimeout(timer);reject(new Error('Повреждённый или неподдерживаемый .torrent.'));});
            torrent.once('ready',()=>{
                clearTimeout(timer);
                if(torrent.files.length>1000){reject(new Error('В раздаче больше 1000 файлов. Выберите меньшую раздачу.'));return;}
                done({hash:torrent.infoHash,name:torrent.name,metainfo,sources:sourceProfile(torrent,location.protocol==='https:',true),unsupported:[...new Set(torrent.files.filter(file=>!audioFile(file)).map(file=>file.name.match(/\.[^.]+$/)?.[0]?.toLowerCase()||'без расширения'))],files:sortAudioFiles(torrent.files.map((file,index)=>({name:file.name,path:file.path,length:file.length,index})).filter(audioFile))});
            });
        });}finally{parser.destroy(()=>{});}
    }
    let torrentGenrePicker;
    function choose(item){
        current=item;selection.hidden=false;error.hidden=true;
        const existing=records.find(record=>record.hash===item.hash);
        $('[data-torrent-name]').textContent=item.name;$('[data-torrent-summary]').textContent=`${item.files.length} песен · теги читаются при прослушивании`;
        const sources=$('[data-torrent-sources]');sources.hidden=false;sources.classList.toggle('alert-warning',!item.sources.available);sources.classList.toggle('alert-info',item.sources.available);
        sources.textContent=item.sources.available?`WebRTC-трекеров: ${item.sources.trackers} · web seeds: ${item.sources.webSeeds}. Наличие трекера не гарантирует, что совместимый раздающий сейчас онлайн.`:'В этом torrent нет источников, доступных браузеру. Для прослушивания нужна раздача с WebRTC-трекером или web seed.';
        $('[data-torrent-files]').innerHTML=item.files.map(file=>`<label class="tr-torrent-file"><input class="form-check-input" type="checkbox" name="files[]" value="${file.index}" ${!existing || existing.tracks.some(track=>track.fileIndex===file.index)?'checked':''}><span>${escape(file.name)}<small>${size(file.length)}</small></span></label>`).join('');
        selection.elements.title.value=existing?.title||item.name;selection.elements.artist.value=existing?.artist||'';torrentGenrePicker=new window.TapeRoomGenrePicker(genres);const holder=$('[data-torrent-genres]');holder.innerHTML=torrentGenrePicker.markup(existing?.genreIds||existing?.genreId||[]);torrentGenrePicker.mount(holder);
    }
    async function open(hash){
        await initialized;error.hidden=true;recent();status();
        if(hash){const record=records.find(item=>item.hash===hash);if(record)choose(await inspect(record.metainfo));}
        const drawer=document.getElementById('tr-site-menu');
        if(drawer.classList.contains('show')){drawer.addEventListener('hidden.bs.offcanvas',()=>dialog.show(),{once:true});bootstrap.Offcanvas.getOrCreateInstance(drawer).hide();}else dialog.show();
    }
    document.addEventListener('click',event=>{
        const button=event.target.closest('[data-tr-torrent-open],[data-torrent-edit]');if(button)open(button.dataset.torrentEdit).catch(exception=>{dialog.show();failure(exception.message);});
    });
    $('[data-torrent-file]').addEventListener('change',async event=>{
        const file=event.target.files[0];if(!file)return;error.hidden=true;event.target.disabled=true;
        try{
            await initialized;
            if(!/\.torrent$/i.test(file.name) || file.size>2*1048576 || !file.size)throw new Error('Выберите файл .torrent до 2 МБ.');
            const item=await inspect(new Uint8Array(await file.arrayBuffer()));
            if(!item.files.length)throw new Error(`В раздаче нет аудио поддерживаемого формата. Найдено: ${item.unsupported.slice(0,8).join(', ')}. Поддерживаются MP3, FLAC, WAV, M4A, OGG, Opus, AAC и WebM. Ограничение 128 МБ на файл снято.`);
            choose(item);
        }catch(exception){failure(exception.message);selection.hidden=true;current=null;}
        finally{event.target.disabled=false;event.target.value='';}
    });
    selection.addEventListener('submit',async event=>{
        event.preventDefault();const button=$('[data-torrent-start]');button.disabled=true;error.hidden=true;
        try{
            if(!current)return;const form=new FormData(selection), selected=new Set(form.getAll('files[]').map(Number)), existing=records.find(record=>record.hash===current.hash);
            const files=current.files.filter(file=>selected.has(file.index));
            if(!files.length)throw new Error('Выберите хотя бы одну песню.');
            if(!existing && records.length>=50)throw new Error('В браузере уже 50 раздач. Уберите ненужную.');
            const selectedGenres=torrentGenrePicker.values();
            const record={...(existing||{}),id:existing?.id||id(),hash:current.hash,metainfo:current.metainfo,title:String(form.get('title')).trim(),artist:String(form.get('artist')).trim(),genreIds:selectedGenres,genreId:selectedGenres[0]||0,genre:'',
                tracks:files.map(file=>{const saved=existing?.tracks.find(track=>track.fileIndex===file.index);return {...saved,id:saved?.id||id(),fileIndex:file.index,filePath:file.path,title:saved?.title||file.name.replace(/\.[^.]+$/,''),filename:file.name,file_size:file.length,duration:saved?.duration||0,favorite:saved?.favorite||false};})};
            if(!record.title)throw new Error('Укажите название альбома.');
            record.edited=Boolean(existing?.edited || record.title!==(existing?.title||current.name) || record.artist!==(existing?.artist||''));
            const previous=records;records=[...records.filter(item=>item.hash!==record.hash),record];
            try{await persist();}catch(exception){records=previous;throw exception;}
            modal.addEventListener('hidden.bs.modal',()=>publish(record.id),{once:true});dialog.hide();selection.hidden=true;current=null;
        }catch(exception){failure(exception.message);}finally{button.disabled=false;}
    });
    $('[data-torrent-select-all]').addEventListener('change',event=>$('[data-torrent-files]').querySelectorAll('input').forEach(input=>input.checked=event.target.checked));
    $('[data-torrent-stop]').addEventListener('click',()=>{audio.pause();if(audio.src.includes('/webtorrent/')){audio.removeAttribute('src');audio.load();}release();});
    $('[data-torrent-jobs]').addEventListener('click',async event=>{
        const button=event.target.closest('[data-torrent-remove]');if(!button)return;
        const hash=button.dataset.torrentRemove, previous=records;records=records.filter(record=>record.hash!==hash);
        if(active?.torrent.infoHash===hash){audio.pause();release();}
        try{await persist();selection.hidden=true;current=null;}catch(exception){records=previous;failure(exception.message);}
    });
    async function action(name,values,cover){
        await initialized;
        const record=records.find(item=>item.tracks.some(track=>track.id===Number(values.id))), track=record?.tracks.find(track=>track.id===Number(values.id));
        if(!track)throw new Error('Песня больше недоступна.');
        switch(name){
            case 'track.favorite':track.favorite=Boolean(Number(values.favorite));break;
            case 'track.duration':track.duration=Number(values.duration)||0;break;
            case 'track.update':
                track.title=String(values.title||'').trim().slice(0,240);track.artist=String(values.artist||'').trim().slice(0,240);track.genreIds=JSON.parse(values.genre_ids||JSON.stringify(Number(values.genre_id)?[Number(values.genre_id)]:[]));track.genreId=track.genreIds[0]||0;track.genre='';track.edited=true;
                if(cover?.size){if(cover.size>2*1048576 || !/^image\/(jpeg|png|webp)$/.test(cover.type))throw new Error('Выберите JPG, PNG или WebP до 2 МБ.');track.cover=cover;}
                break;
            case 'track.delete':record.tracks=record.tracks.filter(item=>item!==track);if(!record.tracks.length)records=records.filter(item=>item!==record);break;
            default:throw new Error('Это действие недоступно для музыки, которая хранится только в браузере.');
        }
        await persist();return {status:true};
    }
    const playbackError=code=>active?connectionStatus(active.torrent,{elapsed:performance.now()-active.started,online:navigator.onLine,audioError:code,secure:location.protocol==='https:'}).message:'Torrent остановлен. Откройте раздачу и запустите песню снова.';
    window.TapeRoomTorrents={resolve,release,action,playbackError,state:()=>active?{hash:active.torrent.infoHash,ready:active.torrent.ready,peers:active.torrent.numPeers,wires:active.torrent.wires.length,downloaded:active.torrent.downloaded,received:active.torrent.received,bufferBytes:active.torrent.tapeRoomBuffer?.bytes||0,bufferLimit:active.torrent.tapeRoomBuffer?.limit||0,length:active.torrent.length,sources:sourceProfile(active.torrent,location.protocol==='https:'),stage:active.diagnosis?.code,webSeeds:active.torrent.urlList.length,selections:active.torrent._selections.length,metadataError:active.metadataError||'',warning:active.warning||'',failed:active.failed}:null};
    audio.addEventListener('pause',status);audio.addEventListener('playing',status);
    window.addEventListener('pagehide',release);
    // Keep the CMS offline/notification worker active, with local torrent ranges.
    if(window.isSecureContext && 'serviceWorker' in navigator)ensureWorker(false).catch(()=>{});
})();
