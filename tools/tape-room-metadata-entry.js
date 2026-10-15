import {parseBlob, selectCover} from 'music-metadata';

// BlobTokenizer supports random reads. A virtual Blob reads only requested
// torrent ranges, including MP4 tags at the end, without downloading the song.
export async function read(file, signal) {
    let remaining=4*1048576;
    const blob={size:file.length,type:file.type,slice(start,end){return {async arrayBuffer(){
        const length=end-start;
        if(signal.aborted)throw signal.reason;
        if(length>remaining)throw new Error('Metadata read budget exceeded');
        remaining-=length;
        const bytes=new Uint8Array(length);
        if(!length)return bytes.buffer;
        const iterator=file[Symbol.asyncIterator]({start,end:end-1});
        const abort=()=>iterator.return();signal.addEventListener('abort',abort,{once:true});
        try{let offset=0;for await(const chunk of iterator){if(signal.aborted)throw signal.reason;bytes.set(chunk,offset);offset+=chunk.length;}}
        finally{signal.removeEventListener('abort',abort);await iterator.return();}
        return bytes.buffer;
    }}}};
    {
        const result=await parseBlob(blob,{duration:false,skipPostHeaders:true,maxBufferSize:4*1048576});
        const tags=result.common, picture=selectCover(tags.picture);
        return {title:tags.title,artist:tags.artist,album:tags.album,albumartist:tags.albumartist,genre:tags.genre?.[0],duration:result.format.duration,
            cover:picture && /image\/(jpeg|png|webp)/.test(picture.format) && picture.data.length<=2*1048576 ? new Blob([picture.data],{type:picture.format}) : null};
    }
}
