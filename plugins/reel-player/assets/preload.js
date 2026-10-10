(() => {
    'use strict';
    // Only a tiny JSON acknowledgement travels to the browser. Native Audio
    // consumes the prepared server bytes through its normal media URL.
    class TapeRoomPreloader {
        constructor({url,csrf,fetcher = (...args) => fetch(...args),delay = 1200}) {
            this.url = url; this.csrf = csrf; this.fetcher = fetcher; this.delay = delay;
            this.targets = []; this.done = new Map(); this.attempts = new Map(); this.allowed = false;
            this.controller = null; this.active = ''; this.timer = null; this.epoch = 0;
        }
        key(track) { return `${track.id}:${track.url}`; }
        schedule(tracks, allowed) {
            const next = tracks.slice(0,2); const keys = new Set(next.map(track => this.key(track)));
            const changed = next.map(track => this.key(track)).join('|') !== this.targets.map(track => this.key(track)).join('|');
            this.targets = next; this.allowed = Boolean(allowed);
            if (!this.allowed || (this.active && !keys.has(this.active))) this.cancel();
            if (changed) {
                for (const key of this.attempts.keys()) if (!keys.has(key)) this.attempts.delete(key);
                for (const key of this.done.keys()) if (!keys.has(key)) this.done.delete(key);
            }
            const pending = this.targets.some(track => !this.done.has(this.key(track)) && (this.attempts.get(this.key(track)) || 0) < 2);
            if (this.allowed && pending && !this.controller && !this.timer) this.timer = setTimeout(() => { this.timer = null; this.run(); },this.delay);
        }
        cancel() {
            this.epoch++; clearTimeout(this.timer); this.timer = null;
            this.controller?.abort(); this.controller = null; this.active = '';
        }
        reset() { this.cancel(); this.targets = []; this.done.clear(); this.attempts.clear(); }
        async run() {
            if (!this.allowed || this.controller) return;
            const track = this.targets.find(track => !this.done.has(this.key(track)) && (this.attempts.get(this.key(track)) || 0) < 2);
            if (!track) return;
            const key = this.key(track);
            // Local media is already fully present on disk; do not download a
            // duplicate into JS memory or compete with the native player.
            if (track.source !== 'drive') { this.done.set(key,true); this.run(); return; }
            const controller = new AbortController(), epoch = this.epoch;
            this.controller = controller; this.active = key;
            this.attempts.set(key,(this.attempts.get(key) || 0)+1);
            const timeout = setTimeout(() => controller.abort(),17000);
            let retry = false;
            try {
                const form = new FormData(); form.set('id',track.id); form.set('needCSRFToken',this.csrf);
                const response = await this.fetcher(this.url,{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':this.csrf,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},body:form,signal:controller.signal});
                const result = await response.json();
                if (epoch !== this.epoch) return;
                if (response.ok && result.status && result.ready) this.done.set(key,true);
                else if (result.reason === 'busy') retry = true;
                else this.attempts.set(key,2); // No endless retries for OAuth, deleted files or network errors.
            } catch (error) {
                if (epoch === this.epoch) this.attempts.set(key,2);
            } finally {
                clearTimeout(timeout);
                if (epoch === this.epoch) {
                    this.controller = null; this.active = '';
                    if (this.allowed) this.timer = setTimeout(() => { this.timer = null; this.run(); },retry ? 5000 : this.delay);
                }
            }
        }
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = TapeRoomPreloader;
    else window.TapeRoomPreloader = TapeRoomPreloader;
})();
