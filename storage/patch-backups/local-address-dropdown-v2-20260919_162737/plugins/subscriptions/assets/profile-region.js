(function () {
    'use strict';

    // FIREBALL_SUBSCRIPTIONS_LOCAL_ADDRESS_V1

    const normalizeCountry = (value) => String(value)
        .toLowerCase()
        .replace(/ё/g, 'е')
        .replace(/[^\p{L}\p{N}]+/gu, ' ')
        .trim()
        .split(/\s+/)
        .sort()
        .join(' ');

    function parseJson(value, fallback) {
        try { return JSON.parse(value); } catch (_) { return fallback; }
    }

    function debounce(callback, delay) {
        let timer = null;
        return function () {
            const args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { callback.apply(null, args); }, delay);
        };
    }

    function initRegions() {
        document.querySelectorAll('[data-subscriptions-region]').forEach(function (root) {
            const form = root.closest('form');
            const country = form ? form.querySelector('[name="country"]') : null;

            if (root.dataset.regionInitialized === '1') return;
            root.dataset.regionInitialized = '1';

            const aliases = parseJson(root.dataset.countryAliases || '[]', []).map(normalizeCountry);
            const select = root.querySelector('[data-region-select]');
            const input = root.querySelector('[data-region-input]');
            const russian = root.querySelector('[data-region-russian]');
            const foreign = root.querySelector('[data-region-foreign]');

            const choices = window.Choices && select ? new window.Choices(select, Object.assign({
                allowHTML: false,
                shouldSort: false,
                removeItemButton: false,
                itemSelectText: '',
                classNames: { containerInner: 'form-select' }
            }, parseJson(select.dataset.select || '{}', {}))) : null;

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

    function initAddressAutocomplete() {
        document.querySelectorAll('form[data-subscriptions-address-form]').forEach(function (form) {
            if (form.dataset.addressInitialized === '1') return;
            form.dataset.addressInitialized = '1';

            const enabled = form.dataset.addressSuggestionsEnabled === '1';
            const endpoint = String(form.dataset.addressSuggestUrl || '');
            const regionRoot = form.querySelector('[data-subscriptions-region]');
            const country = form.querySelector('[name="country"]');
            const regionSelect = regionRoot ? regionRoot.querySelector('[data-region-select]') : null;
            const regionInput = regionRoot ? regionRoot.querySelector('[data-region-input]') : null;
            const aliases = regionRoot
                ? parseJson(regionRoot.dataset.countryAliases || '[]', []).map(normalizeCountry)
                : [];
            const postalCode = form.querySelector('[data-address-postal-code]');
            const states = {};
            const searching = form.dataset.addressSearching || 'Ищем…';
            const noResults = form.dataset.addressNoResults || 'Ничего не найдено';

            const isRussian = () => {
                const key = normalizeCountry(country ? country.value : '');
                return key === '' || aliases.includes(key);
            };

            const regionValue = () => {
                if (regionSelect && !regionSelect.disabled) return String(regionSelect.value || '').trim();
                if (regionInput && !regionInput.disabled) return String(regionInput.value || '').trim();
                return '';
            };

            const fieldValue = (type) => states[type]
                ? String(states[type].input.value || '').trim()
                : '';

            function close(state) {
                state.items = [];
                state.activeIndex = -1;
                state.list.hidden = true;
                state.list.replaceChildren();
                state.input.setAttribute('aria-expanded', 'false');
            }

            function status(state, message) {
                state.list.replaceChildren();
                state.items = [];
                state.activeIndex = -1;

                const div = document.createElement('div');
                div.className = 'subscriptions-address-suggestion is-status';
                div.textContent = message;
                state.list.appendChild(div);
                state.list.hidden = false;
                state.input.setAttribute('aria-expanded', 'true');
            }

            function clearAfter(type) {
                const targets = type === 'city'
                    ? ['street', 'house']
                    : (type === 'street' ? ['house'] : []);

                targets.forEach(function (name) {
                    if (!states[name]) return;
                    states[name].input.value = '';
                    close(states[name]);
                });

                if (postalCode) postalCode.value = '';
            }

            function choose(state, item) {
                clearAfter(state.type);
                state.input.value = String(item.value || '');

                if (state.type === 'house' && postalCode && item.postal_code) {
                    postalCode.value = String(item.postal_code);
                }

                close(state);
            }

            function updateActive(state) {
                Array.from(state.list.querySelectorAll('button[data-address-suggestion-index]'))
                    .forEach(function (button, index) {
                        button.classList.toggle('is-active', index === state.activeIndex);
                    });
            }

            function render(state, items) {
                state.list.replaceChildren();
                state.items = items;
                state.activeIndex = -1;

                if (!items.length) {
                    status(state, noResults);
                    return;
                }

                items.forEach(function (item, index) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'subscriptions-address-suggestion';
                    button.dataset.addressSuggestionIndex = String(index);
                    button.textContent = String(item.label || item.value || '');

                    button.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                    });

                    button.addEventListener('click', function () {
                        choose(state, item);
                    });

                    state.list.appendChild(button);
                });

                state.list.hidden = false;
                state.input.setAttribute('aria-expanded', 'true');
            }

            async function load(state) {
                const query = String(state.input.value || '').trim();
                const minimum = state.type === 'house' ? 1 : 2;

                if (!enabled || !endpoint || !isRussian() || query.length < minimum) {
                    close(state);
                    return;
                }

                if (state.controller) state.controller.abort();
                state.controller = new AbortController();

                const requestId = ++state.requestId;
                status(state, searching);

                try {
                    const url = new URL(endpoint, window.location.origin);
                    url.searchParams.set('type', state.type);
                    url.searchParams.set('q', query);
                    url.searchParams.set('country', country ? String(country.value || '') : '');
                    url.searchParams.set('region', regionValue());
                    url.searchParams.set('city', fieldValue('city'));
                    url.searchParams.set('street', fieldValue('street'));

                    const response = await fetch(url.toString(), {
                        credentials: 'same-origin',
                        cache: 'no-store',
                        headers: { Accept: 'application/json' },
                        signal: state.controller.signal
                    });

                    if (!response.ok) throw new Error('HTTP ' + response.status);

                    const payload = await response.json();
                    if (requestId !== state.requestId) return;

                    render(
                        state,
                        payload && Array.isArray(payload.suggestions)
                            ? payload.suggestions
                            : []
                    );
                } catch (error) {
                    if (error && error.name === 'AbortError') return;
                    close(state);
                }
            }

            ['city', 'street', 'house'].forEach(function (type) {
                const root = form.querySelector('[data-address-field="' + type + '"]');
                if (!root) return;

                const input = root.querySelector('[data-address-input="' + type + '"]');
                const list = root.querySelector('[data-address-suggestions]');
                if (!input || !list) return;

                const state = {
                    type: type,
                    input: input,
                    list: list,
                    items: [],
                    activeIndex: -1,
                    requestId: 0,
                    controller: null
                };

                states[type] = state;
                const scheduled = debounce(function () { load(state); }, 180);

                input.addEventListener('input', function () {
                    clearAfter(type);
                    scheduled();
                });

                input.addEventListener('focus', function () {
                    if (String(input.value || '').trim().length >= (type === 'house' ? 1 : 2)) {
                        scheduled();
                    }
                });

                input.addEventListener('blur', function () {
                    setTimeout(function () { close(state); }, 120);
                });

                input.addEventListener('keydown', function (event) {
                    if (state.list.hidden || !state.items.length) return;

                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        state.activeIndex = Math.min(state.items.length - 1, state.activeIndex + 1);
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        state.activeIndex = Math.max(0, state.activeIndex - 1);
                    } else if (event.key === 'Enter' && state.activeIndex >= 0) {
                        event.preventDefault();
                        choose(state, state.items[state.activeIndex]);
                    } else if (event.key === 'Escape') {
                        close(state);
                    }

                    updateActive(state);
                });
            });
        });
    }

    function init() {
        initRegions();
        initAddressAutocomplete();
    }

    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', init, { once: true })
        : init();
})();
