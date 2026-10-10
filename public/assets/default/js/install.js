(function () {
    'use strict';
    const themeButton = document.querySelector('[data-install-theme]');
    const themeColor = document.querySelector('meta[name="theme-color"]');
    const script = document.querySelector('[data-install-script]');
    function syncTheme() {
        const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
        if (themeButton) themeButton.setAttribute('aria-pressed', dark ? 'true' : 'false');
        if (themeColor) themeColor.content = dark ? '#101720' : '#f5f6f8';
    }
    syncTheme();
    if (themeButton) themeButton.addEventListener('click', function () {
        const theme = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', theme);
        try { localStorage.setItem('theme', theme); } catch (error) {}
        syncTheme();
    });
    function restoreForm(form) {
        form.removeAttribute('aria-busy');
        form.querySelectorAll('[data-submit-label]').forEach(function (label) {
            if (label.dataset.originalLabel) label.textContent = label.dataset.originalLabel;
        });
        form.querySelectorAll('[data-submit-icon]').forEach(function (icon) {
            if (icon.dataset.originalClass) icon.className = icon.dataset.originalClass;
        });
        form.querySelectorAll('[data-install-submitted]').forEach(function (button) {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            delete button.dataset.installSubmitted;
        });
        const status = form.querySelector('[data-install-status]');
        if (status) status.textContent = '';
    }
    document.querySelectorAll('[data-install-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) return;
            if (form.getAttribute('aria-busy') === 'true') {
                event.preventDefault();
                return;
            }
            form.setAttribute('aria-busy', 'true');
            const button = event.submitter || form.querySelector('button[type="submit"]');
            if (button) {
                const label = button.querySelector('[data-submit-label]');
                const icon = button.querySelector('[data-submit-icon]');
                if (label) {
                    label.dataset.originalLabel = label.textContent;
                    label.textContent = form.dataset.busyLabel || label.textContent;
                }
                if (icon) {
                    icon.dataset.originalClass = icon.className;
                    icon.className = 'install-spinner';
                }
                button.dataset.installSubmitted = 'true';
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
            }
            const status = form.querySelector('[data-install-status]');
            if (status && script) status.textContent = script.dataset.installingHint || '';
            // Keep configuration fields enabled so every value stays in the POST.
            // Never copy credentials or installation state into browser storage.
        });
    });
    // Restoring a cached page must not leave a frozen form.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('[data-install-form]').forEach(restoreForm);
    });
})();
