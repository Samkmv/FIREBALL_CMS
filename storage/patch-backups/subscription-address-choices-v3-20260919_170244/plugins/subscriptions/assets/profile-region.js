(function () {
    'use strict';

    // FIREBALL_SUBSCRIPTIONS_ADDRESS_CHOICES_FIX_V2

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

    function debounce(callback, delay) {
        let timer = null;

        return function () {
            const args = arguments;
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                callback.apply(null, args);
            }, delay);
        };
    }

    function waitForChoices(callback, attempts) {
        attempts = Number.isFinite(attempts) ? attempts : 80;

        if (typeof window.Choices !== 'undefined') {
            callback();
            return;
        }

        if (attempts <= 0) {
            return;
        }

        window.setTimeout(function () {
            waitForChoices(callback, attempts - 1);
        }, 50);
    }

    function initRegions() {
        document.querySelectorAll('[data-subscriptions-region]').forEach(function (root) {
            const form = root.closest('form');
            const country = form ? form.querySelector('[name="country"]') : null;

            if (root.dataset.regionInitialized === '1') {
                return;
            }

            root.dataset.regionInitialized = '1';

            const aliases = parseJson(root.dataset.countryAliases || '[]', []).map(normalizeCountry);
            const select = root.querySelector('[data-region-select]');
            const input = root.querySelector('[data-region-input]');
            const russian = root.querySelector('[data-region-russian]');
            const foreign = root.querySelector('[data-region-foreign]');

            let choices = null;

            if (select && typeof window.Choices !== 'undefined') {
                if (!select.closest('.choices')) {
                    choices = new window.Choices(select, Object.assign({
                        allowHTML: false,
                        shouldSort: false,
                        removeItemButton: false,
                        itemSelectText: '',
                        classNames: { containerInner: 'form-select' }
                    }, parseJson(select.dataset.select || '{}', {})));
                    select.dataset.selectEnhanced = '1';
                }
            }

            function syncCountry() {
                const key = normalizeCountry(country ? country.value : root.dataset.countryValue);
                const useDirectory = key === '' || aliases.includes(key);

                if (russian) russian.hidden = !useDirectory;
                if (foreign) foreign.hidden = useDirectory;
                if (select) select.disabled = !useDirectory;
                if (input) input.disabled = useDirectory;

                if (choices) {
                    useDirectory ? choices.enable() : choices.disable();
                }

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

    function initAddressChoices() {
        if (typeof window.Choices === 'undefined') {
            return;
        }

        document.querySelectorAll('form[data-subscriptions-address-form]').forEach(function (form) {
            if (form.dataset.addressChoicesInitialized === '1') {
                return;
            }

            const endpoint = String(form.dataset.addressSuggestUrl || '').trim();
            if (!endpoint) {
                return;
            }

            const regionRoot = form.querySelector('[data-subscriptions-region]');
            const country = form.querySelector('[name="country"]');
            const regionSelect = regionRoot ? regionRoot.querySelector('[data-region-select]') : null;
            const regionInput = regionRoot ? regionRoot.querySelector('[data-region-input]') : null;
            const aliases = regionRoot
                ? parseJson(regionRoot.dataset.countryAliases || '[]', []).map(normalizeCountry)
                : [];
            const postalCode = form.querySelector('[data-address-postal-code]');
            const states = {};

            const texts = {
                searching: form.dataset.addressSearching || 'Ищем…',
                noResults: form.dataset.addressNoResults || 'Ничего не найдено'
            };

            function isRussianAddress() {
                const key = normalizeCountry(country ? country.value : '');
                return key === '' || aliases.includes(key);
            }

            function regionValue() {
                if (regionSelect && !regionSelect.disabled) {
                    return String(regionSelect.value || '').trim();
                }

                if (regionInput && !regionInput.disabled) {
                    return String(regionInput.value || '').trim();
                }

                return '';
            }

            function valueOf(type) {
                const state = states[type];

                if (!state) {
                    return '';
                }

                return state.russian
                    ? String(state.select.value || '').trim()
                    : String(state.manual.value || '').trim();
            }

            function openDropdown(state) {
                window.requestAnimationFrame(function () {
                    if (state.instance) {
                        try {
                            state.instance.showDropdown();
                        } catch (_) {}
                    }

                    const container = state.select.closest('.choices');
                    const input = container
                        ? container.querySelector('.choices__input--cloned')
                        : null;

                    if (input && document.activeElement !== input) {
                        input.focus({ preventScroll: true });
                    }
                });
            }

            function replaceChoices(state, items, keepOpen) {
                if (!state.instance) {
                    return;
                }

                state.instance.setChoices(items, 'value', 'label', true);

                if (keepOpen) {
                    openDropdown(state);
                }
            }

            function showStatus(state, label) {
                replaceChoices(state, [{
                    value: '__status__',
                    label: label,
                    disabled: true
                }], true);
            }

            function resetState(type) {
                const state = states[type];
                if (!state) {
                    return;
                }

                if (state.controller) {
                    state.controller.abort();
                    state.controller = null;
                }

                state.select.value = '';
                state.manual.value = '';
                state.lastQuery = '';

                replaceChoices(state, [{
                    value: '',
                    label: state.placeholder,
                    selected: true,
                    placeholder: true
                }], false);
            }

            function clearAfter(type) {
                if (type === 'city') {
                    resetState('street');
                    resetState('house');
                } else if (type === 'street') {
                    resetState('house');
                }

                if (postalCode) {
                    postalCode.value = '';
                }
            }

            async function loadSuggestions(state, query) {
                query = String(query || '').trim();
                const minimum = state.type === 'house' ? 1 : 2;

                if (
                    !state.russian
                    || !isRussianAddress()
                    || query.length < minimum
                ) {
                    return;
                }

                state.lastQuery = query;

                if (state.controller) {
                    state.controller.abort();
                }

                state.controller = new AbortController();
                const requestId = ++state.requestId;

                showStatus(state, texts.searching);

                try {
                    const url = new URL(endpoint, window.location.origin);
                    url.searchParams.set('type', state.type);
                    url.searchParams.set('q', query);
                    url.searchParams.set('country', country ? String(country.value || '') : '');
                    url.searchParams.set('region', regionValue());
                    url.searchParams.set('city', valueOf('city'));
                    url.searchParams.set('street', valueOf('street'));

                    const response = await window.fetch(url.toString(), {
                        method: 'GET',
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: { Accept: 'application/json' },
                        signal: state.controller.signal
                    });

                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }

                    const payload = await response.json();

                    if (requestId !== state.requestId) {
                        return;
                    }

                    const suggestions = payload && Array.isArray(payload.suggestions)
                        ? payload.suggestions
                        : [];

                    if (!suggestions.length) {
                        showStatus(state, texts.noResults);
                        return;
                    }

                    replaceChoices(
                        state,
                        suggestions.map(function (suggestion) {
                            return {
                                value: String(suggestion.value || ''),
                                label: String(suggestion.label || suggestion.value || ''),
                                customProperties: {
                                    postal_code: String(suggestion.postal_code || '')
                                }
                            };
                        }),
                        true
                    );
                } catch (error) {
                    if (error && error.name === 'AbortError') {
                        return;
                    }

                    if (requestId !== state.requestId) {
                        return;
                    }

                    showStatus(state, texts.noResults);
                }
            }

            ['city', 'street', 'house'].forEach(function (type) {
                const root = form.querySelector('[data-address-field="' + type + '"]');
                if (!root) {
                    return;
                }

                const select = root.querySelector('[data-address-select="' + type + '"]');
                const manual = root.querySelector('[data-address-manual="' + type + '"]');
                const russianWrap = root.querySelector('[data-address-russian-field]');
                const foreignWrap = root.querySelector('[data-address-foreign-field]');

                if (!select || !manual || !russianWrap || !foreignWrap) {
                    return;
                }

                const state = {
                    type: type,
                    root: root,
                    select: select,
                    manual: manual,
                    russianWrap: russianWrap,
                    foreignWrap: foreignWrap,
                    placeholder: String(select.dataset.addressPlaceholder || ''),
                    instance: null,
                    controller: null,
                    requestId: 0,
                    russian: true,
                    lastQuery: ''
                };

                states[type] = state;

                if (select.closest('.choices')) {
                    return;
                }

                const config = Object.assign({
                    allowHTML: false,
                    shouldSort: false,
                    searchEnabled: true,
                    searchChoices: false,
                    searchFloor: 1,
                    searchResultLimit: 12,
                    removeItemButton: false,
                    itemSelectText: '',
                    noResultsText: texts.noResults,
                    noChoicesText: texts.noResults,
                    classNames: {
                        containerInner: 'form-select'
                    }
                }, parseJson(select.dataset.select || '{}', {}));

                config.classNames = Object.assign(
                    { containerInner: 'form-select' },
                    config.classNames || {}
                );

                state.instance = new window.Choices(select, config);
                select.dataset.selectEnhanced = '1';

                const schedule = debounce(function (query) {
                    loadSuggestions(state, query);
                }, 180);

                select.addEventListener('search', function (event) {
                    const query = event && event.detail
                        ? String(event.detail.value || '')
                        : '';

                    schedule(query);
                });

                select.addEventListener('showDropdown', function () {
                    const container = select.closest('.choices');
                    const searchInput = container
                        ? container.querySelector('.choices__input--cloned')
                        : null;

                    if (!searchInput || searchInput.dataset.addressBound === '1') {
                        return;
                    }

                    searchInput.dataset.addressBound = '1';

                    searchInput.addEventListener('input', function () {
                        schedule(searchInput.value);
                    });
                });

                select.addEventListener('choice', function (event) {
                    const choice = event && event.detail ? event.detail.choice : null;

                    if (!choice || String(choice.value || '') === '__status__') {
                        return;
                    }

                    clearAfter(type);

                    if (
                        type === 'house'
                        && postalCode
                        && choice.customProperties
                        && choice.customProperties.postal_code
                    ) {
                        postalCode.value = String(choice.customProperties.postal_code);
                    }
                });
            });

            function setRussianMode(russian) {
                Object.keys(states).forEach(function (type) {
                    const state = states[type];
                    state.russian = russian;

                    state.russianWrap.hidden = !russian;
                    state.foreignWrap.hidden = russian;
                    state.select.disabled = !russian;
                    state.manual.disabled = russian;

                    if (state.instance) {
                        russian ? state.instance.enable() : state.instance.disable();
                    }

                    if (!russian && state.manual.value === '' && state.select.value !== '') {
                        state.manual.value = state.select.value;
                    }
                });
            }

            form.addEventListener('subscriptions:address-mode', function (event) {
                const russian = !event.detail || event.detail.russian !== false;
                setRussianMode(russian);
            });

            setRussianMode(isRussianAddress());

            let lastCountry = country ? String(country.value || '') : '';
            let lastRegion = regionValue();

            function resetHierarchy() {
                const nextCountry = country ? String(country.value || '') : '';
                const nextRegion = regionValue();

                if (nextCountry !== lastCountry || nextRegion !== lastRegion) {
                    resetState('city');
                    resetState('street');
                    resetState('house');

                    if (postalCode) {
                        postalCode.value = '';
                    }

                    lastCountry = nextCountry;
                    lastRegion = nextRegion;
                }
            }

            if (country) {
                country.addEventListener('change', resetHierarchy);
            }

            if (regionSelect) {
                regionSelect.addEventListener('change', resetHierarchy);
            }

            if (regionInput) {
                regionInput.addEventListener('change', resetHierarchy);
            }

            form.dataset.addressChoicesInitialized = '1';
        });
    }

    function start() {
        waitForChoices(function () {
            initRegions();
            initAddressChoices();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start, { once: true });
    } else {
        start();
    }
})();
