(() => {
    'use strict';
    const cards = [...document.querySelectorAll('[data-weather-geolocation]')];
    if (!cards.length) return;
    let positionPromise = null;
    const position = () => {
        if (!positionPromise) {
            positionPromise = new Promise((resolve, reject) => {
                navigator.geolocation.getCurrentPosition(({ coords }) => resolve({
                    latitude: Math.round(coords.latitude * 100) / 100,
                    longitude: Math.round(coords.longitude * 100) / 100,
                }), reject, { enableHighAccuracy: false, maximumAge: 1800000, timeout: 8000 });
            }).catch(error => { positionPromise = null; throw error; });
        }
        return positionPromise;
    };
    cards.forEach(card => {
        const button = card.querySelector('[data-weather-locate]');
        const status = card.querySelector('[data-weather-status]');
        let loading = false;
        const load = async () => {
            if (loading) return;
            if (!navigator.geolocation || !window.isSecureContext) { status.textContent = card.dataset.weatherFallback; return; }
            loading = true;
            button.disabled = true;
            status.textContent = card.dataset.weatherLoading;
            let timer;
            try {
                const coordinates = await position().catch(() => { throw new Error(card.dataset.weatherFallback); });
                const abort = new AbortController();
                timer = window.setTimeout(() => abort.abort(), 10000);
                const response = await fetch(card.dataset.weatherUrl, {
                    method: 'POST', credentials: 'same-origin', signal: abort.signal,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': card.dataset.weatherToken },
                    body: JSON.stringify({ ...coordinates, page: card.dataset.weatherPage }),
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'ok') throw new Error(card.dataset.weatherError);
                const template = document.createElement('template');
                template.innerHTML = result.html; // Escaped, trusted CMS partial from the same origin.
                const body = template.content.querySelector('[data-weather-body]');
                if (!body) throw new Error(card.dataset.weatherError);
                card.querySelector('[data-weather-body]').replaceChildren(...body.childNodes);
                status.textContent = '';
            } catch (error) {
                status.textContent = error.message === card.dataset.weatherFallback ? card.dataset.weatherFallback : card.dataset.weatherError;
            } finally {
                window.clearTimeout(timer);
                loading = false;
                button.disabled = false;
            }
        };
        button.addEventListener('click', load);
        // Reuse a previously granted permission automatically; first-time visitors opt in with the button.
        if (!navigator.geolocation || !window.isSecureContext) { status.textContent = card.dataset.weatherFallback; return; }
        navigator.permissions?.query({ name: 'geolocation' }).then(permission => {
            if (permission.state === 'granted') load();
            else if (permission.state === 'denied') status.textContent = card.dataset.weatherFallback;
        }).catch(() => {});
    });
})();
