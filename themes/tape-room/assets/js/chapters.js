/* Small, source-independent chapter helpers. Native audio owns playback. */
(function (root, factory) {
    const helpers = factory();
    if (typeof module === 'object' && module.exports) module.exports = helpers;
    else root.TapeRoomChapters = helpers;
})(typeof window === 'object' ? window : globalThis, function () {
    'use strict';
    function parseTime(value) {
        const text = String(value).trim();
        if (!/^\d{1,6}:\d{2}(?:\.\d{1,6})?$/.test(text) && !/^\d{1,3}:\d{2}:\d{2}(?:\.\d{1,6})?$/.test(text)) throw new Error('Введите время в формате ММ:СС или ЧЧ:ММ:СС.');
        const parts = text.split(':').map(Number);
        if (parts.at(-1) >= 60 || (parts.length === 3 && parts[1] >= 60)) throw new Error('Минуты и секунды должны быть меньше 60.');
        const seconds = parts.length === 3 ? parts[0] * 3600 + parts[1] * 60 + parts[2] : parts[0] * 60 + parts[1];
        if (!Number.isFinite(seconds) || seconds < 0 || seconds > 604800) throw new Error('Таймкод должен быть в пределах семи суток.');
        return Math.round(seconds * 1e6) / 1e6;
    }
    function formatTime(value) {
        const micros = Math.round(Math.max(0, Number(value) || 0) * 1e6), whole = Math.floor(micros / 1e6);
        const fraction = micros % 1e6 ? '.' + String(micros % 1e6).padStart(6,'0').replace(/0+$/, '') : '';
        const tail = String(whole % 60).padStart(2, '0') + fraction;
        return whole >= 3600 ? `${Math.floor(whole / 3600)}:${String(Math.floor(whole / 60) % 60).padStart(2, '0')}:${tail}` : `${String(Math.floor(whole / 60)).padStart(2, '0')}:${tail}`;
    }
    function indexAt(chapters, time) {
        // Last start <= position, also handles a gap before the first chapter.
        let low = 0, high = chapters.length;
        while (low < high) { const mid = (low + high) >>> 1; if (Number(chapters[mid].start) <= time + 1e-7) low = mid + 1; else high = mid; }
        return low - 1;
    }
    function destination(chapters, time, direction) {
        if (!chapters.length) return null;
        const index = indexAt(chapters, time);
        if (direction > 0) return chapters[index + 1]?.start ?? null;
        if (index < 0) return 0;
        if (time - chapters[index].start > 3) return chapters[index].start;
        return chapters[Math.max(0, index - 1)].start;
    }
    function nearest(chapters, time) {
        if (!chapters.length) return null;
        const before = indexAt(chapters,time);
        if (before < 0) return chapters[0];
        const after = chapters[before+1];
        return after && after.start-time < time-chapters[before].start ? after : chapters[before];
    }
    return {parseTime, formatTime, indexAt, destination, nearest};
});
