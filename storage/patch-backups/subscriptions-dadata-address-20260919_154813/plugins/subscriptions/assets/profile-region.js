(function () {
    'use strict';

    const normalizeCountry = (value) => String(value).toLowerCase().replace(/ё/g, 'е').replace(/[^\p{L}\p{N}]+/gu, ' ').trim().split(/\s+/).sort().join(' ');

    function initRegions() {
        document.querySelectorAll('[data-subscriptions-region]').forEach(function (root) {
            const country = root.closest('form').querySelector('[name="country"]');
            if (root.dataset.regionInitialized === '1') return;
            root.dataset.regionInitialized = '1';
            const aliases = JSON.parse(root.dataset.countryAliases).map(normalizeCountry);
            const select = root.querySelector('[data-region-select]');
            const input = root.querySelector('[data-region-input]');
            const russian = root.querySelector('[data-region-russian]');
            const foreign = root.querySelector('[data-region-foreign]');
            // Use the CMS's bundled Choices component; core initialization skips its existing wrapper.
            const choices = window.Choices ? new window.Choices(select, Object.assign({
                allowHTML: false,
                shouldSort: false,
                removeItemButton: false,
                itemSelectText: '',
                classNames: { containerInner: 'form-select' }
            }, JSON.parse(select.dataset.select))) : null;

            function syncCountry() {
                const key = normalizeCountry(country ? country.value : root.dataset.countryValue);
                const useDirectory = key === '' || aliases.includes(key);
                russian.hidden = !useDirectory;
                foreign.hidden = useDirectory;
                select.disabled = !useDirectory;
                input.disabled = useDirectory;
                if (choices) useDirectory ? choices.enable() : choices.disable();
            }

            if (country) {
                country.addEventListener('input', syncCountry);
                country.addEventListener('change', syncCountry);
            }
            syncCountry();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initRegions, { once: true });
    } else {
        initRegions();
    }
})();
