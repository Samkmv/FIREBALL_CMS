(function () {
    'use strict';

    // FIREBALL_SUBSCRIPTIONS_BASIC_DROPDOWN_JS_V1

    // FIREBALL_SUBSCRIPTIONS_ADDRESS_TEXT_AUTOCOMPLETE_JS_V1
    // FIREBALL_SUBSCRIPTIONS_ADDRESS_BOOTSTRAP_DROPDOWN_JS_V1

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
            clearTimeout(timer);
            timer = setTimeout(function () {
                callback.apply(null, args);
            }, delay);
        };
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

            function syncCountry() {
                const key = normalizeCountry(country ? country.value : root.dataset.countryValue);
                const useDirectory = key === '' || aliases.includes(key);

                if (russian) russian.hidden = !useDirectory;
                if (foreign) foreign.hidden = useDirectory;
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
        document.querySelectorAll('form[data-subscriptions-address-form]').forEach(function (form) {
            if (form.dataset.addressTextAutocompleteInitialized === '1') return;

            const endpoint = String(form.dataset.addressSuggestUrl || '').trim();
            if (!endpoint) return;

            form.dataset.addressTextAutocompleteInitialized = '1';

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
                if (regionSelect && !regionSelect.disabled) return String(regionSelect.value || '').trim();
                if (regionInput && !regionInput.disabled) return String(regionInput.value || '').trim();
                return '';
            }

            function valueOf(type) {
                return states[type] ? String(states[type].input.value || '').trim() : '';
            }

            function close(state) {
                state.activeIndex = -1;
                state.items = [];
                state.list.classList.remove('show');
                state.list.hidden = true;
                state.list.replaceChildren();
                state.input.setAttribute('aria-expanded', 'false');
            }

            function setStatus(state, message) {
                state.activeIndex = -1;
                state.items = [];
                state.list.replaceChildren();

                const row = document.createElement('li');
                const item = document.createElement('a');

                item.href = '#';
                item.className = 'dropdown-item disabled';
                item.setAttribute('aria-disabled', 'true');
                item.textContent = message;

                item.addEventListener('click', function (event) {
                    event.preventDefault();
                });

                row.appendChild(item);
                state.list.appendChild(row);
                state.list.hidden = false;
                state.list.classList.add('show');
                state.input.setAttribute('aria-expanded', 'true');
            }

            function updateActive(state) {
                const buttons = Array.from(
                    state.list.querySelectorAll('[data-address-suggestion-index]')
                );

                buttons.forEach(function (button, index) {
                    const active = index === state.activeIndex;
                    button.classList.toggle('is-active', active);
                    button.classList.toggle('active', active);
                    button.setAttribute('aria-selected', active ? 'true' : 'false');

                    if (active) {
                        button.scrollIntoView({ block: 'nearest' });
                    }
                });
            }

            function clearField(type) {
                const state = states[type];
                if (!state) return;

                if (state.controller) {
                    state.controller.abort();
                    state.controller = null;
                }

                state.input.value = '';
                close(state);
            }

            function clearAfter(type) {
                if (type === 'city') {
                    clearField('street');
                    clearField('house');
                } else if (type === 'street') {
                    clearField('house');
                }

                if (postalCode) postalCode.value = '';
            }

            function choose(state, item) {
                clearAfter(state.type);
                state.input.value = String(item.value || '');
                close(state);

                if (state.type === 'house' && postalCode && item.postal_code) {
                    postalCode.value = String(item.postal_code);
                }

                state.input.dispatchEvent(new Event('change', { bubbles: true }));
            }

            function render(state, suggestions) {
                state.list.replaceChildren();
                state.items = suggestions;
                state.activeIndex = -1;

                if (!suggestions.length) {
                    setStatus(state, texts.noResults);
                    return;
                }

                suggestions.forEach(function (suggestion, index) {
                    const row = document.createElement('li');
                    const link = document.createElement('a');

                    link.href = '#';
                    link.className = 'dropdown-item';
                    link.dataset.addressSuggestionIndex = String(index);
                    link.setAttribute('role', 'option');
                    link.setAttribute('aria-selected', 'false');
                    link.textContent = String(suggestion.label || suggestion.value || '');

                    link.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                    });

                    link.addEventListener('click', function (event) {
                        event.preventDefault();
                        choose(state, suggestion);
                    });

                    row.appendChild(link);
                    state.list.appendChild(row);
                });

                state.list.hidden = false;
                state.list.classList.add('show');
                state.input.setAttribute('aria-expanded', 'true');
            }

            async function load(state) {
                const query = String(state.input.value || '').trim();
                const minimum = state.type === 'house' ? 1 : 2;

                if (!isRussianAddress() || query.length < minimum) {
                    close(state);
                    return;
                }

                if (state.controller) state.controller.abort();

                state.controller = new AbortController();
                const requestId = ++state.requestId;

                setStatus(state, texts.searching);

                try {
                    const url = new URL(endpoint, window.location.origin);
                    url.searchParams.set('type', state.type);
                    url.searchParams.set('q', query);
                    url.searchParams.set('country', country ? String(country.value || '') : '');
                    url.searchParams.set('region', regionValue());
                    url.searchParams.set('city', state.type === 'city' ? '' : valueOf('city'));
                    url.searchParams.set('street', state.type === 'house' ? valueOf('street') : '');

                    const response = await fetch(url.toString(), {
                        method: 'GET',
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
                    if (requestId !== state.requestId) return;
                    setStatus(state, texts.noResults);
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

                const schedule = debounce(function () {
                    load(state);
                }, 180);

                input.addEventListener('input', function () {
                    clearAfter(type);
                    schedule();
                });

                input.addEventListener('focus', function () {
                    const minimum = type === 'house' ? 1 : 2;
                    if (String(input.value || '').trim().length >= minimum) {
                        schedule();
                    }
                });

                input.addEventListener('blur', function () {
                    setTimeout(function () {
                        close(state);
                    }, 140);
                });

                input.addEventListener('keydown', function (event) {
                    if (state.list.hidden || !state.items.length) {
                        if (event.key === 'Escape') close(state);
                        return;
                    }

                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        state.activeIndex = Math.min(
                            state.items.length - 1,
                            state.activeIndex + 1
                        );
                        updateActive(state);
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        state.activeIndex = Math.max(0, state.activeIndex - 1);
                        updateActive(state);
                    } else if (event.key === 'Enter' && state.activeIndex >= 0) {
                        event.preventDefault();
                        choose(state, state.items[state.activeIndex]);
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        close(state);
                    }
                });
            });

            let lastCountry = country ? String(country.value || '') : '';
            let lastRegion = regionValue();

            function resetHierarchy() {
                const nextCountry = country ? String(country.value || '') : '';
                const nextRegion = regionValue();

                if (nextCountry !== lastCountry || nextRegion !== lastRegion) {
                    clearField('city');
                    clearField('street');
                    clearField('house');

                    if (postalCode) postalCode.value = '';

                    lastCountry = nextCountry;
                    lastRegion = nextRegion;
                }
            }

            if (country) country.addEventListener('change', resetHierarchy);
            if (regionSelect) regionSelect.addEventListener('change', resetHierarchy);
            if (regionInput) regionInput.addEventListener('change', resetHierarchy);

            form.addEventListener('subscriptions:address-mode', function () {
                Object.keys(states).forEach(function (key) {
                    close(states[key]);
                });
            });
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
