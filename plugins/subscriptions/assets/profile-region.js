(function () {
    'use strict';

    const normalizeCountry = (value) => String(value)
        .toLowerCase()
        .replace(/ё/g, 'е')
        .replace(/[^\p{L}\p{N}]+/gu, ' ')
        .trim()
        .split(/\s+/)
        .sort()
        .join(' ');

    function parseJson(value, fallback) {
        try {
            return JSON.parse(value);
        } catch (_) {
            return fallback;
        }
    }

    function initRegionMode() {
        document.querySelectorAll('[data-subscriptions-region]').forEach(function (root) {
            const form = root.closest('form');
            const country = form ? form.querySelector('[name="country"]') : null;

            if (root.dataset.regionModeInitialized === '1') return;
            root.dataset.regionModeInitialized = '1';

            const aliases = parseJson(root.dataset.countryAliases || '[]', []).map(normalizeCountry);
            const select = root.querySelector('[data-region-select]');
            const input = root.querySelector('[data-region-input]');
            const russian = root.querySelector('[data-region-russian]');
            const foreign = root.querySelector('[data-region-foreign]');
            let choices = null;
            if (select && typeof window.Choices === 'function' && !select.closest('.choices')) {
                try {
                    // Keep the instance so switching back from a foreign country enables
                    // the visible selector as well as the underlying native select.
                    choices = new window.Choices(select, Object.assign({
                        allowHTML: false, removeItemButton: false, shouldSort: false,
                        itemSelectText: '', classNames: { containerInner: 'form-select' }
                    }, parseJson(select.dataset.select || '{}', {})));
                } catch (_) { /* The native region selector remains usable. */ }
            }

            function syncCountry() {
                const key = normalizeCountry(country ? country.value : root.dataset.countryValue);
                const useDirectory = key === '' || aliases.includes(key);

                if (russian) russian.hidden = !useDirectory;
                if (foreign) foreign.hidden = useDirectory;
                if (choices) {
                    if (useDirectory) choices.enable();
                    else choices.disable();
                }
                if (select) select.disabled = !useDirectory;
                if (input) input.disabled = useDirectory;

                if (form) {
                    form.dispatchEvent(new CustomEvent('subscriptions:address-mode', {
                        detail: { russian: useDirectory }
                    }));
                }
            }

            if (country) {
                country.addEventListener('input', syncCountry);
                country.addEventListener('change', syncCountry);
            }

            syncCountry();
        });
    }

    function initAddressAutocomplete() {
        if (typeof window.Choices !== 'function') return;
        document.querySelectorAll('form[data-subscriptions-address-form]').forEach(function (form) {
            if (form.dataset.addressChoicesInitialized === '1') return;
            const endpoint = String(form.dataset.addressSuggestUrl || '').trim();
            if (!endpoint) return;
            let urlBase;
            try {
                urlBase = new URL(endpoint, window.location.href);
                if (urlBase.origin !== window.location.origin) return;
            } catch (_) { return; }
            form.dataset.addressChoicesInitialized = '1';

            const country = form.querySelector('[name="country"]');
            const regionRoot = form.querySelector('[data-subscriptions-region]');
            const regionSelect = regionRoot && regionRoot.querySelector('[data-region-select]');
            const regionInput = regionRoot && regionRoot.querySelector('[data-region-input]');
            const aliases = regionRoot
                ? parseJson(regionRoot.dataset.countryAliases || '[]', []).map(normalizeCountry) : [];
            const postal = form.querySelector('[data-address-postal-code]');
            const states = {};
            const texts = {
                choose: form.dataset.addressChoose || '',
                search: form.dataset.addressSearch || '',
                minimum: form.dataset.addressMinimum || '',
                manual: form.dataset.addressManual || ':value',
                searching: form.dataset.addressSearching || '',
                empty: form.dataset.addressNoResults || '',
                unavailable: form.dataset.addressServiceUnavailable || ''
            };
            function useDirectory() {
                const key = normalizeCountry(country ? country.value : (regionRoot ? regionRoot.dataset.countryValue : ''));
                return key === '' || aliases.includes(key);
            }
            function regionValue() {
                const control = regionSelect && !regionSelect.disabled ? regionSelect : regionInput;
                return control && !control.disabled ? control.value.trim() : '';
            }
            function valueOf(type) {
                const state = states[type];
                if (state) return (state.select.disabled ? state.input.value : state.select.value).trim();
                const control = form.querySelector('[name="' + type + '"]');
                return control ? control.value.trim() : '';
            }
            function invalidate(state) {
                state.requestId++;
                clearTimeout(state.timer);
                if (state.controller) state.controller.abort();
                state.controller = null;
            }
            function render(state, items, query, message) {
                const selected = state.input.value;
                const rows = [{ value: '', label: texts.choose, placeholder: true, selected: !selected }];
                const seen = new Set(['']);
                items.forEach(function (item) {
                    const value = typeof item.value === 'string' ? item.value.trim() : '';
                    if (!value || seen.has(value)) return;
                    seen.add(value);
                    rows.push({
                        value: value, label: String(item.label || value), selected: value === selected,
                        customProperties: { postalCode: String(item.postal_code || '') }
                    });
                });
                if (selected && !seen.has(selected)) {
                    rows.push({ value: selected, label: selected, selected: true });
                    seen.add(selected);
                }
                const manual = query.trim();
                if (manual && !seen.has(manual)) {
                    rows.push({ value: manual, label: texts.manual.replace(':value', manual) });
                }
                state.choices.clearStore();
                state.choices.setChoices(rows, 'value', 'label', true);
                // Keep the user's query and caret while asynchronous suggestions arrive.
                state.choices.input.element.value = query;
                state.status.textContent = message || state.hint;
            }
            function clearField(type) {
                const state = states[type];
                if (!state) return;
                invalidate(state);
                state.query = '';
                state.input.value = '';
                state.choices.hideDropdown(true);
                render(state, [], '', '');
            }
            function clearAfter(type) {
                if (type === 'city') {
                    clearField('street');
                    clearField('house');
                } else if (type === 'street') {
                    clearField('house');
                }
                if (postal) postal.value = '';
            }
            function commit(state, value, postalCode) {
                value = String(value || '').trim();
                if (value !== state.input.value) clearAfter(state.type);
                invalidate(state);
                state.input.value = value;
                state.query = '';
                state.status.textContent = state.hint;
                if (state.type === 'house' && postalCode && postal) postal.value = postalCode;
            }
            async function load(state) {
                const query = state.query.trim();
                if (!useDirectory()) return;
                const minimum = state.type === 'house' ? 1 : 2;
                if (query.length < minimum) {
                    render(state, [], state.query, texts.minimum.replace(':count', String(minimum)));
                    return;
                }
                const requestId = state.requestId;
                const controller = new AbortController();
                state.controller = controller;
                const timeout = setTimeout(function () { controller.abort(); }, 8000);
                render(state, [], state.query, texts.searching);
                try {
                    const url = new URL(urlBase);
                    url.searchParams.set('type', state.type);
                    url.searchParams.set('q', query);
                    url.searchParams.set('country', country ? country.value : '');
                    url.searchParams.set('region', regionValue());
                    url.searchParams.set('city', state.type === 'city' ? '' : valueOf('city'));
                    url.searchParams.set('street', state.type === 'house' ? valueOf('street') : '');
                    const response = await fetch(url.toString(), {
                        credentials: 'same-origin', cache: 'no-store',
                        headers: { Accept: 'application/json' }, signal: controller.signal
                    });
                    if (!response.ok) throw new Error('Address lookup failed');
                    const payload = await response.json();
                    if (requestId !== state.requestId || !useDirectory()) return;
                    if (!payload || payload.ok === false || !Array.isArray(payload.suggestions)) {
                        throw new Error('Address lookup unavailable');
                    }
                    const items = payload.suggestions.filter(function (item) {
                        return item && typeof item === 'object' && typeof item.value === 'string';
                    }).slice(0, 12);
                    render(state, items, state.query, items.length ? '' : texts.empty);
                } catch (_) {
                    if (requestId === state.requestId && useDirectory()) {
                        // A missing directory or a network error must not lock the form.
                        render(state, [], state.query, texts.unavailable);
                    }
                } finally {
                    clearTimeout(timeout);
                    if (state.controller === controller) state.controller = null;
                }
            }
            function search(state, query) {
                invalidate(state);
                state.query = String(query || '');
                // Invalidate dependent requests immediately, before the debounce expires.
                const dependent = state.type === 'city' ? ['street', 'house'] : state.type === 'street' ? ['house'] : [];
                dependent.forEach(function (type) {
                    if (states[type]) {
                        invalidate(states[type]);
                        states[type].choices.hideDropdown(true);
                    }
                });
                state.timer = setTimeout(function () { load(state); }, 180);
            }

            ['city', 'street', 'house'].forEach(function (type) {
                const root = form.querySelector('[data-address-field="' + type + '"]');
                if (!root) return;
                const select = root.querySelector('[data-address-select]');
                const input = root.querySelector('[data-address-input]');
                const enhanced = root.querySelector('[data-address-enhanced]');
                const status = root.querySelector('[data-address-status]');
                if (!select || !input || !enhanced || !status) return;
                let choices;
                try {
                    select.disabled = false;
                    enhanced.hidden = false;
                    // Reuse the CMS's bundled Choices component and its region-selector chrome.
                    choices = new window.Choices(select, {
                        allowHTML: false, searchEnabled: true, searchChoices: false,
                        shouldSort: false, itemSelectText: '', removeItemButton: false,
                        searchPlaceholderValue: texts.search, noResultsText: texts.empty,
                        noChoicesText: texts.choose, searchResultLimit: 15,
                        classNames: { containerInner: 'form-select' }
                    });
                } catch (_) {
                    select.disabled = true;
                    enhanced.hidden = true;
                    return;
                }
                const state = {
                    type: type, select: select, input: input, enhanced: enhanced,
                    choices: choices, status: status, hint: status.textContent.trim(),
                    query: '', requestId: 0, controller: null, timer: null
                };
                states[type] = state;
                input.hidden = true;
                input.disabled = true;
                choices.input.element.setAttribute('aria-label', select.getAttribute('aria-label'));
                choices.input.element.setAttribute('aria-describedby', select.getAttribute('aria-describedby'));
                // Observe clearing the input too: Choices does not emit "search" below
                // its searchFloor, but an old in-flight response must still be rejected.
                choices.input.element.addEventListener('input', function () {
                    search(state, choices.input.element.value);
                });
                select.addEventListener('showDropdown', function () {
                    search(state, state.choices.input.element.value);
                });
                select.addEventListener('hideDropdown', function () {
                    invalidate(state);
                });
                select.addEventListener('change', function (event) {
                    const value = event.detail ? event.detail.value : select.value;
                    const item = choices.getValue();
                    const postalCode = item && item.customProperties ? item.customProperties.postalCode : '';
                    commit(state, value, postalCode);
                    render(state, [{ value: String(value || ''), postal_code: postalCode }], '', '');
                });
                input.addEventListener('input', function () {
                    if (!input.disabled) {
                        clearAfter(type);
                        invalidate(state);
                    }
                });
            });

            let lastCountry = normalizeCountry(country ? country.value : '');
            let lastRegion = regionValue();
            function syncMode() {
                const russian = useDirectory();
                Object.values(states).forEach(function (state) {
                    invalidate(state);
                    state.choices.hideDropdown(true);
                    state.enhanced.hidden = !russian;
                    state.input.hidden = russian;
                    state.input.disabled = russian;
                    if (russian) {
                        state.choices.enable();
                        render(state, [], '', '');
                    } else {
                        state.choices.disable();
                    }
                    state.select.disabled = !russian;
                });
            }
            function resetHierarchy() {
                const nextCountry = normalizeCountry(country ? country.value : '');
                const nextRegion = regionValue();
                if (nextCountry !== lastCountry || nextRegion !== lastRegion) {
                    ['city', 'street', 'house'].forEach(clearField);
                    if (postal) postal.value = '';
                    lastCountry = nextCountry;
                    lastRegion = nextRegion;
                }
                syncMode();
            }
            if (country) country.addEventListener('change', resetHierarchy);
            if (regionSelect) regionSelect.addEventListener('change', resetHierarchy);
            if (regionInput) regionInput.addEventListener('change', resetHierarchy);
            form.addEventListener('subscriptions:address-mode', syncMode);
            form.addEventListener('submit', function () {
                // A typed manual value is still usable if the user presses Save directly.
                ['city', 'street', 'house'].forEach(function (type) {
                    const state = states[type];
                    if (!state || state.select.disabled || !state.query.trim()) return;
                    const value = state.query.trim();
                    commit(state, value, '');
                    render(state, [{ value: value }], '', '');
                });
            });
            syncMode();
        });
    }

    function start() {
        initRegionMode();
        initAddressAutocomplete();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
