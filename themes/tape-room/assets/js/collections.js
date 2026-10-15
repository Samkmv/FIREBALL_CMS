((root) => {
    'use strict';
    // Browsing and the playing queue each keep their own collection descriptor.
    // This model has no audio or DOM side effects.
    class TapeRoomCollections {
        static descriptor(value, fallback = {type:'songs'}) {
            const types = ['songs','favorites','playlist','album','artist','popular','genre'];
            if (!value || !types.includes(value.type)) return {...fallback};
            if (['playlist','album','genre'].includes(value.type)) return Number.isSafeInteger(Number(value.id)) && Number(value.id) > 0 ? {type:value.type,id:Number(value.id)} : {...fallback};
            if (value.type === 'artist') return typeof value.artist === 'string' && value.artist.trim() ? {type:'artist',artist:value.artist.trim().slice(0,240)} : {...fallback};
            return {type:value.type};
        }
        static tracks(data) {
            const pool = new Map();
            for (const track of data.catalog?.tracks || []) pool.set(Number(track.id),track);
            for (const track of data.tracks || []) {
                const catalog = pool.get(Number(track.id));
                pool.set(Number(track.id),{...catalog,...track,shared:false,listen_count:catalog?.listen_count ?? track.listen_count ?? 0});
            }
            return [...pool.values()];
        }
        static album(data,id) { return (data.catalog?.albums || []).find(album => Number(album.id) === Number(id)); }
        static myTracks(data) { return this.tracks(data).filter(track => !track.shared || track.collected || track.favorite); }
        static sort(tracks,mode) { return mode === 'playlist' ? [...tracks] : [...tracks].sort((a,b) => mode === 'old' ? a.id-b.id : b.id-a.id); }
        static collection(data,value,sort = 'new') {
            const descriptor = this.descriptor(value), pool = this.tracks(data), byId = new Map(pool.map(track => [Number(track.id),track]));
            if (descriptor.type === 'album') return (this.album(data,descriptor.id)?.tracks || []).map(id=>byId.get(Number(id))).filter(Boolean);
            if (descriptor.type === 'playlist') return this.sort(((data.playlists || []).find(item=>Number(item.id)===descriptor.id)?.tracks || []).map(id=>byId.get(Number(id))).filter(Boolean),sort);
            if (descriptor.type === 'favorites') return this.sort(pool.filter(track=>track.favorite),sort);
            if (descriptor.type === 'genre') {
                const albums=(data.catalog?.albums || []).filter(album=>album.published || album.editable || album.browserLocal);
                const publicIds=new Set(albums.flatMap(album=>album.tracks).map(Number));
                const inheritedIds=new Set(albums.filter(album=>Number(album.genreId)===descriptor.id).flatMap(album=>album.tracks).map(Number));
                return this.sort(pool.filter(track=>(track.shared===false || publicIds.has(Number(track.id))) && (Number(track.genreId)===descriptor.id || (!Number(track.genreId) && inheritedIds.has(Number(track.id))))),sort);
            }
            if (descriptor.type === 'popular') {
                const publicIds = new Set((data.catalog?.albums || []).filter(album=>album.published).flatMap(album=>album.tracks).map(Number));
                return pool.filter(track=>publicIds.has(Number(track.id)) && Number(track.listen_count)>0).sort((a,b)=>Number(b.listen_count)-Number(a.listen_count) || b.id-a.id);
            }
            if (descriptor.type === 'artist') {
                const key = descriptor.artist.toLocaleLowerCase();
                const artistIds = new Set((data.catalog?.albums || []).filter(album=>album.published && album.artist.toLocaleLowerCase()===key).flatMap(album=>album.tracks).map(Number));
                const publicIds = new Set((data.catalog?.albums || []).filter(album=>album.published).flatMap(album=>album.tracks).map(Number));
                return this.sort(pool.filter(track=>artistIds.has(Number(track.id)) || (publicIds.has(Number(track.id)) && (track.artist || '').toLocaleLowerCase()===key)),sort);
            }
            return this.sort(this.myTracks(data),sort);
        }
        static name(data,value) {
            const descriptor = this.descriptor(value);
            if (descriptor.type === 'album') return this.album(data,descriptor.id)?.title || 'Альбом';
            if (descriptor.type === 'genre') return (data.catalog?.genres || []).find(genre=>Number(genre.id)===descriptor.id)?.name || 'Жанр';
            if (descriptor.type === 'playlist') return (data.playlists || []).find(item=>Number(item.id)===descriptor.id)?.name || 'Лента';
            return descriptor.type === 'favorites' ? 'Избранное' : descriptor.type === 'popular' ? 'Популярные песни' : descriptor.type === 'artist' ? descriptor.artist : 'Моя музыка';
        }
    }
    root.TapeRoomCollections = TapeRoomCollections;
    if (typeof module !== 'undefined' && module.exports) module.exports = TapeRoomCollections;
})(typeof window !== 'undefined' ? window : globalThis);
