(function () {
    'use strict';
    document.addEventListener('submit', async function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-favorite-form]') || !window.fetch) return;
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        const error = form.parentElement.querySelector('[data-favorite-error]');
        if (!button || button.disabled) return;
        const id = form.elements.entity_id.value;
        const peers = Array.from(document.querySelectorAll('[data-favorite-form]')).filter(function (other) {
            return other.elements.entity_id.value === id && other.elements.entity_type.value === form.elements.entity_type.value;
        });
        peers.forEach(function (other) {
            const control = other.querySelector('button[type="submit"]');
            control.disabled = true;
            control.setAttribute('aria-busy', 'true');
            other.parentElement.querySelector('[data-favorite-error]').hidden = true;
        });
        error.hidden = true;
        const controller = window.AbortController ? new AbortController() : null;
        const timer = controller ? setTimeout(function () { controller.abort(); }, 15000) : null;
        let message = form.dataset.error;
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin',
                signal: controller ? controller.signal : undefined, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            if (response.redirected) { window.location.assign(response.url); return; }
            const data = await response.json();
            if (!response.ok || data.status !== 'success') {
                message = data.message || message;
                throw new Error('Favorite request rejected');
            }
            peers.forEach(function (other) {
                const control = other.querySelector('button[type="submit"]');
                const saved = Boolean(data.saved);
                const label = saved ? other.dataset.removeLabel : other.dataset.addLabel;
                control.setAttribute('aria-pressed', String(saved));
                control.setAttribute('aria-label', label);
                control.title = label;
                control.classList.toggle('text-danger', saved);
                const icon = control.querySelector('[data-favorite-icon]');
                icon.classList.toggle('ci-heart-filled', saved);
                icon.classList.toggle('ci-heart', !saved);
                other.querySelector('[data-favorite-label]').textContent = data.label;
                other.action = saved ? other.dataset.removeUrl : other.dataset.addUrl;
            });
        } catch (e) {
            error.textContent = message;
            error.hidden = false;
        } finally {
            clearTimeout(timer);
            peers.forEach(function (other) {
                const control = other.querySelector('button[type="submit"]');
                control.disabled = false;
                control.removeAttribute('aria-busy');
            });
        }
    });
}());
