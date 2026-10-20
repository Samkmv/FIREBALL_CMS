/* Honest, compact audio labels. Header metadata is optional and source independent. */
(function (root, factory) {
    const helpers = factory();
    if (typeof module === 'object' && module.exports) module.exports = helpers;
    else root.TapeRoomQuality = helpers;
})(typeof window === 'object' ? window : globalThis, function () {
    'use strict';
    const names = {mp3:'MP3',flac:'FLAC',wav:'WAV',m4a:'M4A',ogg:'OGG',opus:'OPUS',aac:'AAC',webm:'WEBM'};
    const mimes = {'audio/mpeg':'MP3','audio/mp3':'MP3','audio/flac':'FLAC','audio/x-flac':'FLAC','application/x-flac':'FLAC','audio/wav':'WAV','audio/x-wav':'WAV','audio/vnd.wave':'WAV','audio/mp4':'M4A','audio/x-m4a':'M4A','audio/ogg':'OGG','application/ogg':'OGG','audio/aac':'AAC','audio/webm':'WEBM','video/webm':'WEBM'};
    const positive = value => Number.isFinite(Number(value)) && Number(value) > 0 ? Number(value) : 0;
    const seconds = value => positive(value) <= 604800 ? positive(value) : 0;
    // FLAC STREAMINFO counts decoded samples. A browser's initial duration may
    // be an estimate and must not replace this value or poison the saved tags.
    const exactDuration = info => info?.format === 'FLAC' && info.lossless === true ? seconds(info.duration) : 0;
    function duration(track = {}, technical = null, nativeDuration = 0) {
        return exactDuration(technical || track.quality) || seconds(nativeDuration) || seconds(track.duration);
    }
    function durationToSave(track = {}, technical = null, nativeDuration = 0) {
        const info=technical || track.quality;
        return format(track) === 'FLAC' || info?.format === 'FLAC' ? exactDuration(info) : seconds(nativeDuration);
    }
    function format(track = {}) {
        const extension = String(track.filename || '').split('.').at(-1).toLowerCase();
        return names[extension] || mimes[String(track.mime || '').split(';')[0].trim().toLowerCase()] || '';
    }
    function summary(track = {}, technical = null, duration = 0) {
        const info = technical || {}, supplied = String(info.format || '').toUpperCase();
        const parts = [Object.values(names).includes(supplied) ? supplied : (format(track) || 'АУДИО')];
        const bits = positive(info.bitDepth), rate = positive(info.sampleRate), channels = positive(info.channels);
        if (bits && bits <= 64) parts.push(`${bits} БИТ`);
        if (rate && rate <= 1048575) parts.push(`${Number((rate / 1000).toFixed(3)).toLocaleString('ru-RU')} КГЦ`);
        let bitrate = positive(info.bitrate), kind = info.bitrateKind;
        if (!bitrate || !['exact','average'].includes(kind)) {
            const size = positive(track.file_size), seconds = positive(info.duration) || positive(duration) || positive(track.duration);
            bitrate = size && seconds ? size * 8 / seconds : 0; kind = 'average';
        }
        if (bitrate && bitrate <= 1e9) parts.push(`${kind === 'average' ? '≈ ' : ''}${Math.round(bitrate / 1000)} КБИТ/С`);
        if (channels === 1) parts.push('МОНО');
        else if (channels === 2) parts.push('СТЕРЕО');
        else if (channels && channels <= 64) parts.push(`${channels} КАН.`);
        return parts.join(' · ');
    }
    const isLossless=track=>track?.quality?.lossless===true || track?.lossless===true || format(track)==='FLAC';
    const badge=()=>'<span class="tr-lossless-badge"><span class="tr-lossless-mark" aria-hidden="true"></span><span>Высокое качество <b>lossless</b></span></span>';
    const albumBadge=tracks=>tracks.length && tracks.every(isLossless)?badge():'';
    return {format,summary,isLossless,badge,albumBadge,duration,durationToSave};
});
