(function (root) {
    'use strict';
    if (root.FireballMediaSession) return;
    const actions = ['play','pause','previoustrack','nexttrack','seekbackward','seekforward','seekto','stop'];
    let owner = null;
    const session = () => root.navigator?.mediaSession;
    function handlers(value) {
        if (!session()) return;
        for (const name of actions) {
            try { session().setActionHandler(name,value[name] ? details => { if (owner === value) value[name](details || {}); } : null); } catch (_) {}
        }
    }
    root.FireballMediaSession = {
        claim(value) {
            if (!value) return;
            const previous = owner; owner = value;
            if (previous && previous !== value) previous.deactivate?.();
            handlers(value);
            value.activate?.();
        },
        owns(value) { return owner === value; },
        update(value,{metadata,playbackState,position} = {}) {
            if (owner !== value || !session()) return;
            try { if (metadata !== undefined) session().metadata = metadata; } catch (_) {}
            try { if (playbackState !== undefined) session().playbackState = playbackState; } catch (_) {}
            try { if (position && session().setPositionState) session().setPositionState(position); } catch (_) {}
        },
        release(value) {
            if (owner !== value) return;
            owner = null;
            if (!session()) return;
            for (const name of actions) { try { session().setActionHandler(name,null); } catch (_) {} }
            try { session().metadata = null; session().playbackState = 'none'; } catch (_) {}
        }
    };
    // A theme may load FirePlayer before the optional Tape Room host assets.
    // Existing audio blocks can register their current owner when the arbiter arrives.
    if (root.document?.dispatchEvent && typeof root.CustomEvent === 'function') root.document.dispatchEvent(new root.CustomEvent('fireball:media-session-ready'));
    if (typeof module !== 'undefined' && module.exports) module.exports = root.FireballMediaSession;
})(typeof window !== 'undefined' ? window : globalThis);
