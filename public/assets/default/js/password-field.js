(function () {
    'use strict';

    const hiddenIcon = [
        '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">',
        '<path d="M23.8 11.6c-.1-.1-.1-.3-.2-.4-.8-1.4-1.7-2.7-2.8-3.8-1.9-2.1-4.9-4.3-8.8-4.3S5.2 5.3 3.2 7.4c-1.1 1.2-2 2.4-2.8 3.8-.1.1-.1.2-.2.4s-.1.5 0 .8v.1c.2.4.5.8.7 1.2.7 1 1.4 2 2.2 2.9 1.9 2.1 4.9 4.3 8.8 4.3 3.9 0 6.8-2.2 8.8-4.3 1.1-1.2 2-2.4 2.8-3.8.1-.1.1-.2.2-.4.2-.3.2-.6.1-.8zm-4.3 3.8c-1.8 1.9-4.3 3.7-7.5 3.7s-5.7-1.8-7.5-3.7c-1-1-1.8-2.2-2.5-3.4.7-1.2 1.5-2.4 2.5-3.4C6.3 6.7 8.8 4.9 12 4.9s5.7 1.8 7.5 3.7c1 1 1.8 2.2 2.5 3.4-.7 1.2-1.5 2.4-2.5 3.4z"></path>',
        '<path d="M12 8.1c-2.2 0-3.9 1.8-3.9 3.9s1.7 3.9 3.9 3.9 3.9-1.7 3.9-3.9-1.7-3.9-3.9-3.9zm0 6c-1.1 0-2.1-1-2.1-2.1s.9-2.1 2.1-2.1 2.1 1 2.1 2.1-1 2.1-2.1 2.1z"></path>',
        '</svg>'
    ].join('');

    const visibleIcon = [
        '<svg width="1em" height="1em" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">',
        '<path d="M23.8 11.6c-.1-.1-.1-.3-.2-.4-.8-1.4-1.7-2.7-2.8-3.8-1.9-2.1-4.9-4.3-8.8-4.3-.8 0-1.5.1-2.3.3-.5.1-.8.6-.7 1.1s.6.8 1.1.6c.6-.1 1.3-.2 1.9-.2 3.2 0 5.7 1.8 7.5 3.7 1 1 1.8 2.2 2.5 3.4-.5.9-1.1 1.8-1.8 2.6-.3.4-.3.9.1 1.2.4.3.9.3 1.2-.1.9-1 1.6-2.2 2.3-3.3.1-.2.1-.5 0-.8z"></path>',
        '<path d="M23.6 22.4 1.6.4C1.3 0 .7 0 .4.4c-.4.3-.4.9 0 1.2L4.8 6C3 7.6 1.4 9.5.3 11.6c-.1.2-.1.5 0 .8.1.1.1.2.2.4.8 1.4 1.7 2.7 2.8 3.8 1.9 2.1 4.9 4.3 8.8 4.3 2.2 0 4.2-.7 5.9-1.7l4.4 4.4c.3.3.9.3 1.2 0s.3-.9 0-1.2zM12 19.1c-3.2 0-5.7-1.8-7.5-3.7-1-1-1.8-2.2-2.5-3.4 1-1.8 2.4-3.4 4-4.8l2.7 2.7c-.4.6-.6 1.3-.6 2.1 0 2.2 1.7 3.9 3.9 3.9.8 0 1.5-.2 2.1-.6l2.5 2.5c-1.4.8-3 1.3-4.6 1.3z"></path>',
        '</svg>'
    ].join('');

    function initializePasswordToggle(control) {
        if (!(control instanceof HTMLElement) || control.dataset.passwordToggleReady === 'true') {
            return;
        }

        const input = control.querySelector('.password-field__input');
        const checkbox = control.querySelector('.password-toggle-button .btn-check');
        const button = checkbox ? checkbox.closest('.password-toggle-button') : null;
        if (!(input instanceof HTMLInputElement) || !(checkbox instanceof HTMLInputElement) || !button) {
            return;
        }

        let icon = button.querySelector('.password-toggle-button-icon');
        const initializedByCartzilla = Boolean(icon);
        if (!icon) {
            icon = document.createElement('span');
            icon.className = 'password-toggle-button-icon d-flex';
            button.appendChild(icon);
        }

        const syncAccessibility = function () {
            button.setAttribute('aria-pressed', checkbox.checked ? 'true' : 'false');
        };

        if (initializedByCartzilla) {
            checkbox.addEventListener('change', syncAccessibility);
            syncAccessibility();
            control.dataset.passwordToggleReady = 'true';
            return;
        }

        const sync = function () {
            const isVisible = checkbox.checked;
            input.type = isVisible ? 'text' : 'password';
            icon.innerHTML = isVisible ? visibleIcon : hiddenIcon;
            syncAccessibility();
        };

        checkbox.addEventListener('change', sync);
        sync();
        control.dataset.passwordToggleReady = 'true';
    }

    function initializeAll(root) {
        const scope = root instanceof Element || root instanceof Document ? root : document;
        scope.querySelectorAll('[data-password-toggle]').forEach(initializePasswordToggle);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initializeAll(document);
        }, {once: true});
    } else {
        initializeAll(document);
    }

    window.FireballPasswordToggle = {
        initialize: initializeAll
    };
})();
