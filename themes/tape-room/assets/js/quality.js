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
    return {format, summary};
});
