(function () {
    'use strict';

    // FIREBALL_SUBSCRIPTIONS_DADATA_ADDRESS_V1

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

            const texts = {
                searching: form.dataset.addressSearching || 'Searching…',
                noResults: form.dataset.addressNoResults || 'Nothing found',
                selectRequired: form.dataset.addressSelectRequired || 'Choose an item from the suggestions.',
                unavailable: form.dataset.addressServiceUnavailable || 'Address suggestions are temporarily unavailable.'
            };

            const states = {};

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
                return states[type] ? String(states[type].input.value || '').trim() : '';
            }

            function close(state) {
                state.activeIndex = -1;
                state.items = [];
                state.list.hidden = true;
                state.list.replaceChildren();
                state.input.setAttribute('aria-expanded', 'false');
            }

            function setStatus(state, message) {
                state.activeIndex = -1;
                state.items = [];
                state.list.replaceChildren();

                const item = document.createElement('div');
                item.className = 'subscriptions-address-suggestion is-status';
                item.textContent = message;

                state.list.appendChild(item);
                state.list.hidden = false;
                state.input.setAttribute('aria-expanded', 'true');
            }

            function updateActive(state) {
                const buttons = Array.from(state.list.querySelectorAll('button[data-address-suggestion-index]'));
                buttons.forEach(function (button, index) {
                    const active = index === state.activeIndex;
                    button.classList.toggle('is-active', active);
                    button.setAttribute('aria-selected', active ? 'true' : 'false');
                    if (active) button.scrollIntoView({ block: 'nearest' });
                });
            }

            function clearField(type) {
                const state = states[type];
                if (!state) return;

                state.input.value = '';
                state.input.dataset.addressSelected = '';
                state.selected = false;
                state.dirty = false;
                state.lastFetchOk = null;
                state.input.setCustomValidity('');
                close(state);
            }

            function clearAfter(type) {
                if (type === 'city') {
                    clearField('street');
                    clearField('house');
                    if (postalCode) postalCode.value = '';
                } else if (type === 'street') {
                    clearField('house');
                    if (postalCode) postalCode.value = '';
                } else if (type === 'house') {
                    if (postalCode) postalCode.value = '';
                }
            }

            function selectSuggestion(state, suggestion) {
                clearAfter(state.type);

                state.input.value = String(suggestion.value || '');
                state.input.dataset.addressSelected = '1';
                state.selected = true;
                state.dirty = true;
                state.lastFetchOk = true;
                state.input.setCustomValidity('');

                if (state.type === 'house' && postalCode && suggestion.postal_code) {
                    postalCode.value = String(suggestion.postal_code);
                }

                close(state);
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
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'subscriptions-address-suggestion';
                    button.dataset.addressSuggestionIndex = String(index);
                    button.setAttribute('role', 'option');
                    button.setAttribute('aria-selected', 'false');

                    const label = document.createElement('span');
                    label.className = 'subscriptions-address-suggestion__label';
                    label.textContent = String(suggestion.label || suggestion.value || '');

                    button.appendChild(label);

                    button.addEventListener('mousedown', function (event) {
                        event.preventDefault();
                    });

                    button.addEventListener('click', function () {
                        selectSuggestion(state, suggestion);
                    });

                    state.list.appendChild(button);
                });

                state.list.hidden = false;
                state.input.setAttribute('aria-expanded', 'true');
            }

            async function loadSuggestions(state) {
                const query = String(state.input.value || '').trim();
                const minimum = state.type === 'house' ? 1 : 2;

                if (!enabled || !endpoint || !isRussianAddress() || query.length < minimum) {
                    close(state);
                    return;
                }

                if (state.controller) {
                    state.controller.abort();
                }

                state.controller = new AbortController();
                state.requestId += 1;
                const requestId = state.requestId;

                setStatus(state, texts.searching);

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
                    if (requestId !== state.requestId) return;

                    state.lastFetchOk = payload && payload.ok === true;

                    if (!payload || payload.ok !== true) {
                        setStatus(state, texts.unavailable);
                        return;
                    }

                    render(state, Array.isArray(payload.suggestions) ? payload.suggestions : []);
                } catch (error) {
                    if (error && error.name === 'AbortError') return;
                    if (requestId !== state.requestId) return;

                    state.lastFetchOk = false;
                    setStatus(state, texts.unavailable);
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
                    root: root,
                    input: input,
                    list: list,
                    initialValue: String(input.value || '').trim(),
                    selected: String(input.value || '').trim() !== '',
                    dirty: false,
                    lastFetchOk: null,
                    items: [],
                    activeIndex: -1,
                    requestId: 0,
                    controller: null
                };

                states[type] = state;

                const schedule = debounce(function () {
                    loadSuggestions(state);
                }, 300);

                input.addEventListener('input', function () {
                    state.dirty = true;
                    state.selected = false;
                    state.input.dataset.addressSelected = '';
                    state.input.setCustomValidity('');
                    clearAfter(type);
                    schedule();
                });

                input.addEventListener('focus', function () {
                    if (String(input.value || '').trim().length >= (type === 'house' ? 1 : 2)) {
                        schedule();
                    }
                });

                input.addEventListener('blur', function () {
                    window.setTimeout(function () {
                        close(state);
                    }, 150);
                });

                input.addEventListener('keydown', function (event) {
                    if (state.list.hidden || !state.items.length) {
                        if (event.key === 'Escape') close(state);
                        return;
                    }

                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        state.activeIndex = Math.min(state.items.length - 1, state.activeIndex + 1);
                        updateActive(state);
                    } else if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        state.activeIndex = Math.max(0, state.activeIndex - 1);
                        updateActive(state);
                    } else if (event.key === 'Enter' && state.activeIndex >= 0) {
                        event.preventDefault();
                        selectSuggestion(state, state.items[state.activeIndex]);
                    } else if (event.key === 'Escape') {
                        event.preventDefault();
                        close(state);
                    }
                });
            });

            let lastRegion = regionValue();
            let lastCountry = country ? String(country.value || '') : '';

            function resetHierarchyIfParentChanged() {
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

            if (country) country.addEventListener('change', resetHierarchyIfParentChanged);
            if (regionSelect) regionSelect.addEventListener('change', resetHierarchyIfParentChanged);
            if (regionInput) regionInput.addEventListener('change', resetHierarchyIfParentChanged);

            form.addEventListener('subscriptions:address-mode', function () {
                Object.keys(states).forEach(function (key) {
                    close(states[key]);
                    states[key].input.setCustomValidity('');
                });
            });

            form.addEventListener('submit', function (event) {
                if (!enabled || !isRussianAddress()) return;

                for (const type of ['city', 'street']) {
                    const state = states[type];
                    if (!state) continue;

                    const value = String(state.input.value || '').trim();
                    const changed = state.dirty && value !== state.initialValue;

                    // If DaData itself is unavailable, preserve manual-entry fallback.
                    if (
                        value !== ''
                        && changed
                        && !state.selected
                        && state.lastFetchOk !== false
                    ) {
                        state.input.setCustomValidity(texts.selectRequired);
                        state.input.reportValidity();
                        event.preventDefault();
                        state.input.focus();
                        return;
                    }
                }
            });
        });
    }

    function init() {
        initRegions();
        initAddressAutocomplete();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
