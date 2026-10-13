(() => {
    'use strict';
    // Original URLs remain the full-resolution source. Only known, authenticated
    // Tape Room image endpoints receive a thumbnail parameter.
    const thumbnail = (source, size) => {
        if (!source || ![96,320,640].includes(size)) return source || '';
        const parts = String(source).split('#'), separator=parts[0].indexOf('?');
        const path=separator<0 ? parts[0] : parts[0].slice(0,separator), query=separator<0 ? '' : parts[0].slice(separator+1);
        if (!path.startsWith('/') || path.startsWith('//')) {
            if (typeof location==='undefined') return source;
            try { const url=new URL(path,location.href);if(url.origin!==location.origin || !/^https?:$/.test(url.protocol))return source; }
            catch (_) {return source;}
        }
        if (!/\/(?:admin\/reel-player\/(?:catalog\/)?|music\/)(?:cover|album-cover|artist-image)\/\d+$/.test(path)) return source;
        const params = new URLSearchParams(query); params.set('size',String(size));
        return `${path}?${params}${parts.length > 1 ? '#' + parts.slice(1).join('#') : ''}`;
    };
    const sources = (source, config = {}, theme = 'light', kind = 'cover', compact = false) => {
        const defaults = kind === 'artist' ? config.defaultArtists : config.defaultCovers;
        const variants = kind === 'artist' ? config.defaultArtistVariants : config.defaultCoverVariants;
        const fallback = defaults?.[theme] || (kind === 'cover' ? config.defaultCover : '') || '';
        const original = source || fallback;
        if (!original) return {src:'',srcset:''};
        const first = compact ? 96 : 320, second = compact ? 320 : 640;
        const small = source ? thumbnail(source,first) : variants?.[theme]?.[first] || original;
        const large = source ? thumbnail(source,second) : variants?.[theme]?.[second] || original;
        return {src:small,srcset:small !== large ? `${small} 1x, ${large} 2x` : ''};
    };
    const apply = (image, source, config, theme, kind = 'cover', compact = false) => {
        const value = sources(source,config,theme,kind,compact);
        // Set candidates before src to avoid requesting the original in between.
        if (image.getAttribute('srcset') !== value.srcset) {
            if (value.srcset) image.setAttribute('srcset',value.srcset);
            else image.removeAttribute('srcset');
        }
        if (image.getAttribute('src') !== value.src) image.setAttribute('src',value.src);
    };
    const api = {thumbnail,sources,apply};
    if (typeof window !== 'undefined') window.TapeRoomImages = api;
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
})();
