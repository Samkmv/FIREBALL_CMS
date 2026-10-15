/* Browser-only torrent primitives; neither audio nor metainfo is uploaded. */
((root)=>{
    'use strict';
    class MemoryStore {
        constructor(chunkLength){
            if(!Number.isSafeInteger(chunkLength) || chunkLength<1 || chunkLength>4*1048576)throw new Error('Размер блока torrent превышает 4 МБ.');
            this.chunkLength=chunkLength;this.chunks=new Map();this.bytes=0;this.closed=false;
        }
        put(index,bytes,callback){
            let error=null;
            if(this.closed)error=new Error('Torrent остановлен.');
            else if(this.bytes+bytes.length-(this.chunks.get(index)?.length||0)>192*1048576)error=new Error('Буфер torrent заполнен. Остановите torrent и включите песню снова.');
            else{this.bytes-=this.chunks.get(index)?.length||0;this.chunks.set(index,bytes.slice());this.bytes+=bytes.length;}
            queueMicrotask(()=>callback?.(error));
        }
        get(index,options,callback){
            if(typeof options==='function'){callback=options;options={};}
            const bytes=this.chunks.get(index),offset=options?.offset||0;
            queueMicrotask(()=>bytes && !this.closed ? callback(null,bytes.subarray(offset,options?.length===undefined?undefined:offset+options.length)) : callback(new Error('Блок ещё не получен.')));
        }
        close(callback){this.closed=true;this.chunks.clear();this.bytes=0;queueMicrotask(()=>callback?.());}
        destroy(callback){this.close(callback);}
    }
    const audioFile=file=>/\.(mp3|flac|wav|m4a|ogg|opus|aac|webm)$/i.test(file.name) && file.length>0 && file.length<=128*1048576;
    const api={MemoryStore,audioFile};
    if(typeof module==='object' && module.exports)module.exports=api;else root.TapeRoomTorrentCore=api;
})(typeof window==='object'?window:globalThis);
