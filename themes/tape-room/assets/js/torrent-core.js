/* Browser-only torrent primitives; neither audio nor metainfo is uploaded. */
((root)=>{
    'use strict';
    class MemoryStore {
        constructor(chunkLength,options={}){
            if(!Number.isSafeInteger(chunkLength) || chunkLength<1 || chunkLength>16*1048576)throw new Error('Размер блока torrent превышает 16 МБ.');
            this.chunkLength=chunkLength;this.chunks=new Map();this.bytes=0;this.closed=false;
            this.limit=Math.max(chunkLength,Math.min(Number(options.maxBytes)||192*1048576,192*1048576));
            this.torrent=options.torrent;
            if(this.torrent)this.torrent.tapeRoomBuffer=this;
        }
        put(index,bytes,callback){
            let error=null;
            if(this.closed)error=new Error('Torrent остановлен.');
            else if(bytes.length>this.chunkLength)error=new Error('Некорректный размер блока torrent.');
            else{
                const replacing=this.chunks.get(index)?.length||0;
                if(this.bytes+bytes.length-replacing>this.limit && typeof this.torrent?._markUnverified!=='function')error=new Error('Не удалось освободить потоковый буфер torrent.');
                else{
                    this.bytes-=replacing;this.chunks.delete(index);
                    while(this.bytes+bytes.length>this.limit){
                        const oldest=this.chunks.keys().next().value,previous=this.chunks.get(oldest);
                        this.chunks.delete(oldest);this.bytes-=previous.length;
                        // WebTorrent 3.0.21 must request evicted pieces again on seek.
                        this.torrent._markUnverified(oldest);this.torrent.done=false;
                    }
                    this.chunks.set(index,bytes.slice());this.bytes+=bytes.length;
                }
            }
            queueMicrotask(()=>callback?.(error));
        }
        get(index,options,callback){
            if(typeof options==='function'){callback=options;options={};}
            const bytes=this.chunks.get(index),offset=options?.offset||0;
            if(bytes){this.chunks.delete(index);this.chunks.set(index,bytes);}
            queueMicrotask(()=>bytes && !this.closed ? callback(null,bytes.subarray(offset,options?.length===undefined?undefined:offset+options.length)) : callback(new Error('Блок ещё не получен.')));
        }
        close(callback){this.closed=true;this.chunks.clear();this.bytes=0;queueMicrotask(()=>callback?.());}
        destroy(callback){this.close(callback);}
    }
    const audioFile=file=>/\.(mp3|flac|wav|m4a|ogg|opus|aac|webm)$/i.test(file.name) && Number.isSafeInteger(file.length) && file.length>0;
    const collator=new Intl.Collator('ru',{numeric:true,sensitivity:'base'});
    const sortAudioFiles=files=>[...files].sort((a,b)=>collator.compare(a.filePath||a.path||a.filename||a.name||'',b.filePath||b.path||b.filename||b.name||''));
    const defaultTrackers=['wss://tracker.openwebtorrent.com'];
    const sourceProfile=(torrent,secure=false,defaults=false)=>{
        const announce=[...(torrent.announce||[]),...(!torrent.private&&defaults?defaultTrackers:[])];
        const trackers=new Set(announce.filter(url=>/^(wss:|ws:)/i.test(url)&&(!secure||/^wss:/i.test(url))));
        const seeds=new Set((torrent.urlList||[]).filter(url=>/^https?:/i.test(url)&&(!secure||/^https:/i.test(url))));
        return {private:Boolean(torrent.private),trackers:trackers.size,webSeeds:seeds.size,available:Boolean(trackers.size||seeds.size)};
    };
    const connectionStatus=(torrent,{elapsed=0,online=true,audioError=0,paused=false,ready=0,secure=false}={})=>{
        const sources=sourceProfile(torrent,secure),received=Number(torrent.received||torrent.downloaded)||0;
        if(!online)return {code:'OFFLINE',message:'Нет подключения к интернету.'};
        if(torrent.ready===false)return {code:'INITIALIZING',message:'Читаем параметры torrent…'};
        if(!sources.available)return {code:'NO_BROWSER_SOURCE',message:'В этом torrent нет доступного WebRTC-трекера или web seed. Браузер не может подключиться к обычным TCP/UDP-раздающим. Нужна раздача с WebTorrent / WebRTC или HTTPS web seed.'};
        if(!received && torrent.numPeers && (elapsed>=20000 || audioError))return {code:'NO_DATA',message:'Раздающие подключились, но не передают нужные аудиоблоки. Возможно, у них нет выбранной песни. Попробуйте позже или выберите другую раздачу.'};
        if(!received && (elapsed>=20000 || audioError))return {code:'NO_DATA',message:sources.webSeeds?'Аудиоданные не поступают. Web seed должен разрешать CORS и Range; WebRTC-раздающие должны быть онлайн.':'Совместимые WebRTC-раздающие не найдены. Сиды обычного torrent-клиента браузеру недоступны. Оставьте эту раздачу открытой в WebTorrent Desktop с общим WebSocket-трекером.'};
        if(received && audioError)return {code:'AUDIO_ERROR',message:[3,4].includes(audioError)?'Данные torrent получены, но браузер не смог прочитать аудио. Проверьте содержимое файла и поддержку его кодека.':'Передача аудио из torrent прервалась. Проверьте соединение и попробуйте снова.'};
        if(!received)return {code:'SEARCHING',message:torrent.numPeers?'Есть соединения, ждём первые аудиоблоки…':'Ищем раздающих WebTorrent…'};
        return {code:ready<3?'BUFFERING':paused?'PAUSED':'PLAYING',message:ready<3?'Получаем аудио и готовим воспроизведение…':paused?'Пауза · буфер остаётся в памяти браузера':'Потоковое прослушивание'};
    };
    const api={MemoryStore,audioFile,sortAudioFiles,sourceProfile,connectionStatus,defaultTrackers};
    if(typeof module==='object' && module.exports)module.exports=api;else root.TapeRoomTorrentCore=api;
})(typeof window==='object'?window:globalThis);
