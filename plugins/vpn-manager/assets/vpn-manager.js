(function () {
    'use strict';

    var active = null;
    var portalClass = 'admin-dropdown-portal';

    function closeDropdown() {
        if (!active) {
            return;
        }

        active.source.appendChild(active.menu);
        active.menu.classList.remove(portalClass, 'show');
        active.menu.style.left = '';
        active.menu.style.top = '';
        active.menu.style.right = '';
        active.menu.style.bottom = '';
        active.toggle.setAttribute('aria-expanded', 'false');
        active.root.classList.remove('is-open');
        active = null;
    }

    function positionMenu() {
        if (!active) {
            return;
        }

        var rect = active.toggle.getBoundingClientRect();
        var menu = active.menu;
        var gap = 8;
        var viewportWidth = window.innerWidth || document.documentElement.clientWidth;
        var viewportHeight = window.innerHeight || document.documentElement.clientHeight;

        menu.style.left = '0px';
        menu.style.top = '0px';
        menu.style.right = 'auto';
        menu.style.bottom = 'auto';

        var width = menu.offsetWidth;
        var height = menu.offsetHeight;
        var left = rect.right - width;
        if (left < gap) {
            left = Math.min(rect.left, viewportWidth - width - gap);
        }
        if (left + width > viewportWidth - gap) {
            left = viewportWidth - width - gap;
        }
        left = Math.max(gap, left);

        var top = rect.bottom + gap;
        if (top + height > viewportHeight - gap) {
            top = rect.top - height - gap;
        }
        if (top < gap) {
            top = Math.max(gap, viewportHeight - height - gap);
        }

        menu.style.left = Math.round(left) + 'px';
        menu.style.top = Math.round(top) + 'px';
    }

    function openDropdown(root, toggle, menu) {
        closeDropdown();

        active = {
            root: root,
            toggle: toggle,
            menu: menu,
            source: root
        };

        document.body.appendChild(menu);
        menu.classList.add(portalClass, 'show');
        toggle.setAttribute('aria-expanded', 'true');
        root.classList.add('is-open');
        positionMenu();
    }

    document.addEventListener('click', function (event) {
        var copyButton = event.target.closest('[data-vpn-copy-value]');
        if (copyButton) {
            event.preventDefault();
            var value = copyButton.getAttribute('data-vpn-copy-value') || '';
            if (!value || !navigator.clipboard) {
                return;
            }

            navigator.clipboard.writeText(value).then(function () {
                var label = copyButton.querySelector('[data-vpn-copy-label]') || copyButton;
                var original = copyButton.getAttribute('data-vpn-copy-original') || label.textContent;
                copyButton.setAttribute('data-vpn-copy-original', original);
                label.textContent = copyButton.getAttribute('data-vpn-copy-done') || original;
                window.setTimeout(function () {
                    label.textContent = original;
                }, 1400);
            }).catch(function () {});
            return;
        }

        var toggle = event.target.closest('[data-vpn-dropdown-toggle]');
        if (toggle) {
            event.preventDefault();
            event.stopPropagation();

            var root = toggle.closest('[data-vpn-dropdown]');
            var menu = root ? root.querySelector('[data-vpn-dropdown-menu]') : null;
            if (!root || !menu) {
                return;
            }

            if (active && active.toggle === toggle) {
                closeDropdown();
                return;
            }

            openDropdown(root, toggle, menu);
            return;
        }

        if (active && !active.menu.contains(event.target)) {
            closeDropdown();
        }
    }, true);

    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-vpn-dropdown-action]')) {
            window.setTimeout(closeDropdown, 0);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeDropdown();
        }
    });

    window.addEventListener('resize', closeDropdown);
    window.addEventListener('scroll', closeDropdown, true);
})();
