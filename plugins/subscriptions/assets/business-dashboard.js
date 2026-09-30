(function () {
    'use strict';
    function initializeForms() {
        const name = document.getElementById('business-name');
        const slug = document.getElementById('business-slug');
        const address = document.querySelector('[data-business-public-address]');
        if (name && slug) {
            const letters = {'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'e','ж':'zh','з':'z','и':'i','й':'y','к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'c','ч':'ch','ш':'sh','щ':'shch','ъ':'','ы':'y','ь':'','э':'e','ю':'yu','я':'ya'};
            name.addEventListener('input', function () {
                let value = name.value.trim().toLowerCase().replace(/[а-яё]/g, c => letters[c]).replace(/[^a-z0-9\s-]/g, '').replace(/[\s-]+/g, '-').replace(/^-|-$/g, '') || 'business';
                value = value.slice(0, 180).replace(/-$/g, '');
                if (/^\d+$/.test(value)) value = 'business-' + value;
                slug.value = name.value.trim() === slug.dataset.originalName && slug.dataset.originalSlug ? slug.dataset.originalSlug : value;
                if (address) address.textContent = address.dataset.base + slug.value;
            });
        }
        document.querySelectorAll('[data-business-camera-form]').forEach(form => {
            const stream = form.elements.camera_url;
            const poster = form.elements.camera_poster;
            function validate() { stream.setCustomValidity(poster.value.trim() && !stream.value.trim() ? form.dataset.missingStream : ''); }
            stream.addEventListener('input', validate);
            poster.addEventListener('input', validate);
            form.addEventListener('submit', event => { validate(); if (!form.reportValidity()) event.preventDefault(); });
        });
    }
    function openTarget() {
        const target = document.getElementById(window.location.hash.slice(1));
        if (target && target.tagName === 'DETAILS') { target.open = true; }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { openTarget(); initializeForms(); });
    else { openTarget(); initializeForms(); }
    window.addEventListener('hashchange', openTarget);
    document.addEventListener('click', async function (event) {
        const button = event.target.closest('[data-business-camera-refresh]');
        if (!button || button.disabled) return;
        const panel = button.closest('.business-camera-panel') || button.closest('.business-owner-main');
        const element = panel && panel.querySelector('[data-fire-player]');
        const player = element && element.firePlayer;
        const status = panel && panel.querySelector('[data-business-camera-status]');
        if (!player || typeof player.retry !== 'function') {
            if (status) status.textContent = button.dataset.unavailable || '';
            return;
        }
        button.disabled = true;
        if (status) status.textContent = '';
        try { await player.retry(); }
        catch (error) { if (status) status.textContent = button.dataset.unavailable || ''; }
        finally { button.disabled = false; }
    });
}());
