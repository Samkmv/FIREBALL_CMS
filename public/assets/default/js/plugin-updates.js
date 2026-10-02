(() => {
    'use strict';

    const init = () => {
        const status = document.querySelector('[data-plugin-update-all-status]');
        if (!status) return;
        const summary = status.querySelector('[data-plugin-update-all-summary]');
        const errors = status.querySelector('[data-plugin-update-all-errors]');
        const resultKey = 'fireball.plugin-updates.result:' + window.location.pathname;
        // Keep failures visible after refreshing versions, even if every other
        // plugin is now current and the bulk update form is no longer rendered.
        try {
            const saved = window.sessionStorage.getItem(resultKey);
            window.sessionStorage.removeItem(resultKey);
            const result = saved ? JSON.parse(saved) : null;
            if (result && typeof result.summary === 'string'
                && Array.isArray(result.errors) && result.errors.length
                && result.errors.every(message => typeof message === 'string')) {
                summary.textContent = result.summary;
                for (const message of result.errors) {
                    const item = document.createElement('li');
                    item.textContent = message;
                    errors.append(item);
                }
                errors.hidden = false;
                status.hidden = false;
                status.classList.remove('alert-info');
                status.classList.add('alert-warning');
            }
        } catch (_) { /* Storage may be unavailable in private browsing. */ }
        const form = document.querySelector('[data-plugin-update-all]');
        if (!form || !window.fetch || !window.FormData) return;
        const plugins = JSON.parse(form.dataset.plugins || '[]');
        if (!plugins.length) return;
        const label = form.querySelector('[data-plugin-update-all-label]');
        let running = false;
        const format = (template, values) => Object.entries(values).reduce(
            (text, [key, value]) => text.replaceAll(':' + key, String(value)), template
        );

        window.addEventListener('beforeunload', event => {
            if (!running) return;
            event.preventDefault();
            event.returnValue = form.dataset.leaveLabel;
        });

        // The shared confirmation modal resubmits with this flag. Intercept only
        // that confirmed submit; the first submit must still open the CMS modal.
        form.addEventListener('submit', async event => {
            if (!running && form.dataset.deleteConfirmed !== '1') return;
            event.preventDefault();
            event.stopPropagation();
            if (running) return;
            form.dataset.deleteConfirmed = '0';
            running = true;
            const controls = Array.from(document.querySelectorAll('form button[type="submit"]'));
            const disabledStates = controls.map(button => button.disabled);
            controls.forEach(button => { button.disabled = true; });
            const modal = document.querySelector('[data-admin-delete-modal]');
            const bootstrapApi = typeof bootstrap !== 'undefined' ? bootstrap : window.bootstrap;
            if (modal && bootstrapApi) bootstrapApi.Modal.getInstance(modal)?.hide();
            status.hidden = false;
            errors.textContent = '';
            errors.hidden = true;
            const counts = {updated: 0, skipped: 0, failed: 0};
            const failureMessages = [];
            let interrupted = false;
            try {
                for (const [index, plugin] of plugins.entries()) {
                    const progress = format(form.dataset.progressLabel, {
                        current: index + 1, total: plugins.length, name: plugin.name
                    });
                    label.textContent = (index + 1) + ' / ' + plugins.length;
                    summary.textContent = progress;
                    const body = new FormData(form);
                    body.set('slug', plugin.slug);
                    let response;
                    let payload;
                    try {
                        response = await fetch(form.dataset.updateUrl, {
                            method: 'POST', body, credentials: 'same-origin',
                            headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}
                        });
                        if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) {
                            throw new Error(form.dataset.interruptedLabel);
                        }
                        payload = await response.json();
                    } catch (_) {
                        // Unknown outcome: do not retry a potentially committed
                        // replacement or keep sending mutations with a lost session.
                        interrupted = true;
                    }
                    if (response && [401, 403, 419, 503].includes(response.status)) interrupted = true;
                    if (interrupted || !response.ok || payload?.status !== true) {
                        counts.failed++;
                        const item = document.createElement('li');
                        item.textContent = plugin.name + ': ' + (interrupted
                            ? form.dataset.interruptedLabel : (payload?.message || form.dataset.interruptedLabel));
                        failureMessages.push(item.textContent);
                        errors.append(item);
                        errors.hidden = false;
                        if (interrupted) break;
                        continue;
                    }
                    counts[payload.result?.status === 'success' ? 'updated' : 'skipped']++;
                }
            } finally {
                running = false;
                controls.forEach((button, index) => { button.disabled = disabledStates[index]; });
                // Refresh to reread versions; do not expose stale individual update buttons.
                document.querySelectorAll('form[action="' + form.dataset.updateUrl + '"] button[type="submit"]').forEach(button => { button.disabled = true; });
                form.querySelector('button[type="submit"]').disabled = true;
                form.classList.remove('d-inline-flex');
                form.hidden = true;
                summary.textContent = format(form.dataset.resultLabel, counts) + (interrupted ? ' ' + form.dataset.interruptedLabel : '');
                status.classList.remove('alert-info');
                status.classList.add(counts.failed ? 'alert-warning' : 'alert-success');
                if (failureMessages.length) {
                    try {
                        window.sessionStorage.setItem(resultKey, JSON.stringify({
                            summary: summary.textContent, errors: failureMessages
                        }));
                    } catch (_) { /* Refresh still works without browser storage. */ }
                }
                window.location.reload();
            }
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
