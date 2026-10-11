(() => {
    'use strict';

    function init() {
        const root = document.querySelector('[data-ui-kit]');
        if (!root || root.dataset.uiInitialized) return;
        root.dataset.uiInitialized = 'true';
        const search = root.querySelector('[data-ui-search]');
        const sections = Array.from(root.querySelectorAll('[data-ui-section]'));
        const count = root.querySelector('[data-ui-count]');
        const status = root.querySelector('[data-ui-status]');
        const announce = (message) => { status.textContent = message; };
        const normalize = (text) => text.toLocaleLowerCase().replace(/ё/g, 'е').trim();
        const searchable = new Map(sections.map((section) => [section,
            normalize(section.textContent + ' ' + section.dataset.uiKeywords)]));
        // Keep dialogs reachable when their catalogue section is filtered out.
        root.querySelectorAll('.modal, .offcanvas').forEach((dialog) => root.append(dialog));

        function filter() {
            const terms = normalize(search.value).split(/\s+/).filter(Boolean);
            let visible = 0;
            sections.forEach((section) => {
                const matches = terms.every((term) => searchable.get(section).includes(term));
                section.hidden = !matches;
                root.querySelector(`[data-ui-index="${section.id}"]`).hidden = !matches;
                if (matches) visible++;
            });
            count.textContent = root.dataset.countLabel.replace(':visible', visible).replace(':total', sections.length);
            root.querySelector('[data-ui-empty]').hidden = visible !== 0;
        }
        search.addEventListener('input', filter);
        root.querySelector('[data-ui-reset]').addEventListener('click', () => {
            search.value = '';
            filter();
            search.focus();
        });
        filter();

        async function copy(text) {
            if (navigator.clipboard?.writeText) {
                try { await navigator.clipboard.writeText(text); return; } catch (_) { /* Try local fallback. */ }
            }
            const previousFocus = document.activeElement;
            const textarea = document.createElement('textarea');
            textarea.className = 'fb-ui-copy-fallback';
            textarea.value = text;
            document.body.append(textarea);
            textarea.select();
            let copied = false;
            try { copied = document.execCommand('copy'); }
            finally {
                textarea.remove();
                previousFocus?.focus();
            }
            if (!copied) throw new Error('Clipboard unavailable');
        }

        root.addEventListener('click', async (event) => {
            const button = event.target.closest('[data-ui-copy]');
            if (button && root.contains(button)) {
                try {
                    await copy(document.getElementById(button.dataset.uiCopy).textContent);
                    announce(root.dataset.copiedLabel);
                } catch (_) { announce(root.dataset.copyErrorLabel); }
            }
            const toastButton = event.target.closest('[data-ui-toast]');
            if (toastButton) {
                window.toastr?.[toastButton.dataset.uiToast]?.(root.dataset.demoMessage);
            }
        });

        // Demo forms stay entirely local; no admin actions or requests are submitted.
        root.querySelectorAll('[data-ui-demo-form]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                form.classList.add('was-validated');
                const valid = form.checkValidity();
                announce(valid ? root.dataset.demoMessage : root.dataset.invalidLabel);
                if (!valid) form.querySelector(':invalid')?.focus();
            });
            form.addEventListener('reset', () => {
                form.classList.remove('was-validated');
                announce('');
            });
        });

        const checks = Array.from(root.querySelectorAll('[data-ui-row-check]'));
        const all = root.querySelector('[data-ui-check-all]');
        const selection = root.querySelector('[data-ui-selection]');
        function updateSelection() {
            const selected = checks.filter((checkbox) => checkbox.checked).length;
            all.checked = checks.length > 0 && selected === checks.length;
            all.indeterminate = selected > 0 && selected < checks.length;
            selection.textContent = root.dataset.selectionLabel.replace(':count', selected);
        }
        all.addEventListener('change', () => {
            checks.forEach((checkbox) => { checkbox.checked = all.checked; });
            updateSelection();
        });
        checks.forEach((checkbox) => checkbox.addEventListener('change', updateSelection));
        updateSelection();

        root.querySelector('[data-ui-table-search]').addEventListener('input', (event) => {
            const query = normalize(event.target.value);
            root.querySelectorAll('[data-ui-selectable-row]').forEach((row) => {
                row.hidden = !normalize(row.textContent).includes(query);
            });
        });

        root.querySelectorAll('[data-ui-page]').forEach((button) => {
            button.addEventListener('click', () => {
                root.querySelectorAll('[data-ui-page]').forEach((page) => {
                    const active = page === button;
                    page.parentElement.classList.toggle('active', active);
                    if (active) page.setAttribute('aria-current', 'page');
                    else page.removeAttribute('aria-current');
                });
            });
        });

        const range = root.querySelector('[data-ui-range]');
        const rangeValue = root.querySelector('[data-ui-range-value]');
        range.addEventListener('input', () => { rangeValue.value = range.value; });
        function updateTokens() {
            const style = getComputedStyle(root);
            root.querySelectorAll('[data-ui-token-value]').forEach((value) => {
                value.textContent = style.getPropertyValue(value.dataset.uiTokenValue).trim();
            });
        }
        updateTokens();
        new MutationObserver(updateTokens).observe(document.documentElement, {
            attributes: true, attributeFilter: ['data-bs-theme']
        });
        root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((element) => {
            window.bootstrap?.Tooltip?.getOrCreateInstance(element);
        });
        root.querySelectorAll('[data-bs-toggle="popover"]').forEach((element) => {
            window.bootstrap?.Popover?.getOrCreateInstance(element);
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
