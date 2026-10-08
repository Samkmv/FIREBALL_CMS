(function () {
    'use strict';
    const settings = window.toyRentalSettings || {};
    const labels = settings.labels || {};
    const grid = document.querySelector('[data-toy-rental-grid]');
    if (!grid) return;
    const csrf = document.querySelector('meta[name="needCSRFToken"]')?.content
        || grid.querySelector('[name="needCSRFToken"]')?.value || '';
    const notified = new Set();
    const syncing = new Set();
    const pending = new Set();
    const modal = document.querySelector('[data-toy-rental-complete-modal]');
    let refreshing = false;
    let epoch = 0;
    let audioContext;

    const format = (seconds) => {
        const value = Math.max(0, Math.floor(seconds));
        return `${String(Math.floor(value / 60)).padStart(2, '0')}:${String(value % 60).padStart(2, '0')}`;
    };
    const updateFixedPrices = () => {
        grid.querySelectorAll('[data-toy-rental-card]').forEach(card => {
            const duration = card.querySelector('[name="duration_minutes"]');
            const price = card.querySelector('[data-toy-rental-fixed-price]');
            if (duration && price) price.textContent = (Number(duration.value) * Number(price.dataset.pricePerMinute)).toFixed(2);
        });
    };
    grid.addEventListener('change', event => {
        if (event.target.matches('[name="duration_minutes"]')) updateFixedPrices();
    });
    const serverNow = (element) => {
        const timestamp = Number(element?.dataset.serverNowMs);
        if (!timestamp) return Date.now();
        if (!element.dataset.clientMountedMs) element.dataset.clientMountedMs = String(Date.now());
        return timestamp + Date.now() - Number(element.dataset.clientMountedMs);
    };
    const notice = (message, tone = 'success', persistent = false) => {
        const area = document.querySelector('[data-toy-rental-notices]');
        if (!area || !message) return;
        const item = document.createElement('div');
        item.className = `alert alert-${tone} rounded-4`;
        const text = document.createElement('span');
        text.textContent = message;
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close';
        close.setAttribute('aria-label', labels.close || 'Close');
        close.addEventListener('click', () => item.remove());
        item.append(text, close);
        area.append(item);
        if (!persistent) window.setTimeout(() => item.remove(), 6000);
    };
    const unlockAudio = () => {
        if (settings.soundEnabled === false) return;
        try {
            const Audio = window.AudioContext || window.webkitAudioContext;
            if (Audio && !audioContext) audioContext = new Audio();
            audioContext?.resume().catch(() => {});
        } catch { /* Audio may be unavailable. */ }
    };
    document.addEventListener('pointerdown', unlockAudio, { once: true });
    document.addEventListener('keydown', unlockAudio, { once: true });
    const playSound = () => {
        if (settings.soundEnabled === false || !audioContext) return;
        try {
            const oscillator = audioContext.createOscillator();
            const gain = audioContext.createGain();
            oscillator.frequency.value = 880;
            gain.gain.value = 0.055;
            oscillator.connect(gain);
            gain.connect(audioContext.destination);
            oscillator.start();
            oscillator.stop(audioContext.currentTime + .18);
            oscillator.onended = () => { oscillator.disconnect(); gain.disconnect(); };
        } catch { /* Timer and notification still work without sound. */ }
    };
    const request = async (url, body) => {
        const response = await fetch(url, {
            method: body ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-store',
            headers: {
                'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                ...(body ? { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', 'X-CSRF-Token': csrf } : {}),
            },
            ...(body ? { body } : {}),
        });
        let data;
        try { data = await response.json(); } catch { throw new Error(labels.error || 'Request failed'); }
        if (!response.ok || data.status !== true) throw new Error(data.message || labels.error || 'Request failed');
        return data;
    };
    const applyState = (data, onlyCarId = '') => {
        if (Number(data.max_ride_minutes) > 0) settings.maxRideMinutes = Number(data.max_ride_minutes);
        const activeOnly = grid.dataset.activeOnly === 'true';
        const present = new Set();
        for (const item of data.cards || []) {
            const id = String(item.car_id);
            present.add(id);
            if (onlyCarId && onlyCarId !== id) continue;
            if (pending.has(id) && onlyCarId !== id) continue;
            const card = grid.querySelector(`[data-car-id="${item.car_id}"]`);
            if (activeOnly && !item.ride_id) { card?.remove(); continue; }
            if (!onlyCarId && card && card.dataset.rideId === String(item.ride_id) && card.dataset.status === item.status) continue;
            const template = document.createElement('template');
            template.innerHTML = item.html;
            const replacement = template.content.firstElementChild;
            if (replacement) {
                if (card) card.replaceWith(replacement);
                else grid.append(replacement);
            }
        }
        if (!onlyCarId) {
            grid.querySelectorAll('[data-toy-rental-card]').forEach(card => {
                if (!present.has(card.dataset.carId) && !pending.has(card.dataset.carId)) card.remove();
            });
        }
        document.querySelector('[data-toy-rental-empty]')?.classList.toggle('d-none', Boolean(grid.children.length));
        document.querySelectorAll('[data-toy-rental-stat]').forEach(target => {
            const key = target.dataset.toyRentalStat;
            if (!data.stats) return;
            const value = key === 'active' ? Number(data.stats.active) + Number(data.stats.overdue) : data.stats[key];
            target.textContent = key === 'revenue_total' ? Number(value).toFixed(2) : String(value);
        });
        updateFixedPrices();
        tickTimers();
    };
    const refreshState = async () => {
        if (refreshing || pending.size || !settings.stateUrl) return;
        refreshing = true;
        const startedEpoch = epoch;
        try {
            const data = await request(settings.stateUrl);
            if (epoch === startedEpoch && !pending.size) applyState(data);
        } catch { /* Retry on the next interval; running timers stay visible. */ }
        finally { refreshing = false; }
    };
    const syncExpiry = async (rideId) => {
        if (!settings.syncOverdueUrl || syncing.has(rideId)) return;
        syncing.add(rideId);
        const body = new URLSearchParams({ needCSRFToken: csrf, ride_id: rideId });
        try {
            await request(settings.syncOverdueUrl, body);
            await refreshState();
        } catch { /* Retry while this ride remains expired. */ }
        finally { syncing.delete(rideId); }
    };
    const updatePayment = () => {
        if (!modal?.dataset.startMs || modal.dataset.submitting === 'true') return;
        const elapsed = Math.min(Math.max(0, serverNow(modal) - Number(modal.dataset.startMs)), Number(settings.maxRideMinutes || 120) * 60000);
        const minutes = Math.max(1, Math.ceil(elapsed / 60000));
        const amount = (minutes * Number(modal.dataset.pricePerMinute || 0)).toFixed(2);
        modal.querySelector('[data-toy-rental-modal-duration]').value = `${minutes} ${labels.minutes || 'min'}`;
        modal.querySelector('[data-toy-rental-modal-calculated]').value = `${amount} ${settings.currency || ''}`;
        const final = modal.querySelector('[data-toy-rental-final-amount]');
        if (final.dataset.userEdited !== 'true') final.value = amount;
    };
    const tickTimers = () => {
        grid.querySelectorAll('[data-toy-rental-timer]').forEach(timer => {
            const now = serverNow(timer);
            const start = Number(timer.dataset.startMs);
            const end = Number(timer.dataset.endMs);
            const card = timer.closest('[data-toy-rental-card]');
            const rideId = card.dataset.rideId;
            const metered = timer.dataset.billingType === 'metered';
            const elapsed = Math.max(0, now - start);
            const limit = Number(settings.maxRideMinutes || 120) * 60000;
            timer.textContent = format(metered ? Math.min(elapsed, limit) / 1000 : Math.ceil(Math.max(0, end - now) / 1000));
            if (metered) {
                const cost = card.querySelector('[data-toy-rental-live-cost]');
                if (cost) cost.textContent = (Math.max(1, Math.ceil(Math.min(elapsed, limit) / 60000)) * Number(timer.dataset.pricePerMinute || 0)).toFixed(2);
            }
            const expired = metered ? elapsed >= limit : now >= end;
            if (!expired) return;
            card.classList.add('is-overdue');
            timer.classList.add('text-danger');
            const badge = card.querySelector('[data-toy-rental-status]');
            if (badge) { badge.textContent = labels.overdue || 'Overdue'; badge.className = 'fb-badge toy-rental-status-badge toy-rental-status-badge--danger'; }
            card.querySelector('[data-toy-rental-time-up]')?.classList.remove('d-none');
            if (!notified.has(rideId)) {
                notified.add(rideId);
                notice((metered ? labels.limitReached : labels.timeUp)?.replace(':car', card.dataset.carLabel) || card.dataset.carLabel, 'warning', true);
                playSound();
                syncExpiry(rideId);
            } else if (now - Number(timer.dataset.lastSyncMs || 0) >= 15000) {
                syncExpiry(rideId);
            }
            timer.dataset.lastSyncMs = timer.dataset.lastSyncMs && now - Number(timer.dataset.lastSyncMs) < 15000 ? timer.dataset.lastSyncMs : String(now);
        });
        updatePayment();
    };
    const openPayment = (card) => {
        if (modal?.dataset.submitting === 'true') return true;
        if (!modal || !window.bootstrap?.Modal) return false;
        const timer = card.querySelector('[data-toy-rental-timer]');
        modal.querySelector('form').reset();
        modal.querySelector('[name="id"]').value = card.dataset.rideId;
        modal.dataset.carId = card.dataset.carId;
        modal.dataset.startMs = timer.dataset.startMs;
        modal.dataset.serverNowMs = String(serverNow(timer));
        modal.dataset.clientMountedMs = String(Date.now());
        modal.dataset.pricePerMinute = timer.dataset.pricePerMinute;
        modal.querySelector('[data-toy-rental-modal-car]').textContent = card.dataset.carLabel;
        modal.querySelector('[data-toy-rental-final-amount]').dataset.userEdited = 'false';
        modal.querySelector('[data-toy-rental-modal-error]').classList.add('d-none');
        updatePayment();
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
        return true;
    };
    modal?.querySelector('[data-toy-rental-final-amount]')?.addEventListener('input', event => { event.target.dataset.userEdited = 'true'; });
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!form.matches('[data-toy-rental-start-form], [data-toy-rental-complete-form], [data-toy-rental-payment-form]')) return;
        event.preventDefault();
        const card = form.closest('[data-toy-rental-card]');
        const id = card?.dataset.carId || modal?.dataset.carId;
        if (!id || pending.has(id)) return;
        if (form.matches('[data-toy-rental-complete-form]') && card.querySelector('[data-toy-rental-timer]')?.dataset.billingType === 'metered' && openPayment(card)) return;
        const body = new URLSearchParams(new FormData(form));
        if (!body.has('needCSRFToken')) body.set('needCSRFToken', csrf);
        const paymentForm = form.matches('[data-toy-rental-payment-form]');
        // The server calculates the final charge at completion unless the operator enters a correction.
        if (paymentForm && form.querySelector('[data-toy-rental-final-amount]').dataset.userEdited !== 'true') body.delete('payment_amount');
        pending.add(id);
        epoch++;
        const controls = [...(card || form).querySelectorAll('button, select, input')].filter(field => !field.disabled);
        controls.forEach(field => { field.disabled = true; });
        form.setAttribute('aria-busy', 'true');
        if (paymentForm) modal.dataset.submitting = 'true';
        try {
            const data = await request(form.action, body);
            if (paymentForm) window.bootstrap?.Modal.getInstance(modal)?.hide();
            applyState(data, id);
            notice(data.message);
        } catch (error) {
            if (paymentForm) {
                const target = modal.querySelector('[data-toy-rental-modal-error]');
                target.textContent = error.message;
                target.classList.remove('d-none');
            } else notice(error.message, 'danger', true);
        } finally {
            controls.forEach(field => { field.disabled = false; });
            form.removeAttribute('aria-busy');
            if (paymentForm) modal.dataset.submitting = 'false';
            pending.delete(id);
            epoch++;
            refreshState();
        }
    });
    updateFixedPrices();
    tickTimers();
    window.setInterval(tickTimers, 1000);
    window.setInterval(refreshState, Math.max(5, Number(settings.autoRefreshSeconds) || 5) * 1000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) { tickTimers(); refreshState(); } });
})();
