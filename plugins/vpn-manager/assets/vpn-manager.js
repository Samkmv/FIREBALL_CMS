(function () {
    'use strict';

    var active = null;
    var portalClass = 'admin-dropdown-portal';
    var mobileClass = 'admin-dropdown-portal-mobile';
    var lastTouchToggleAt = 0;

    function viewport() {
        var visual = window.visualViewport;

        return {
            width: visual ? visual.width : (window.innerWidth || document.documentElement.clientWidth),
            height: visual ? visual.height : (window.innerHeight || document.documentElement.clientHeight)
        };
    }

    function closeDropdown() {
        document.querySelectorAll('[data-vpn-dropdown-backdrop]').forEach(function (backdrop) {
            backdrop.remove();
        });

        if (!active) {
            return;
        }

        active.source.appendChild(active.menu);
        active.menu.classList.remove(portalClass, mobileClass, 'show', 'dropup');
        active.menu.style.left = '';
        active.menu.style.top = '';
        active.menu.style.right = '';
        active.menu.style.bottom = '';
        active.menu.style.maxHeight = '';
        active.menu.style.overflowY = '';
        active.menu.style.width = '';
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
        var view = viewport();
        var viewportWidth = view.width;
        var viewportHeight = view.height;
        var isTouch = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
        var isMobile = viewportWidth < 992 || isTouch;

        menu.style.left = '0px';
        menu.style.top = '0px';
        menu.style.right = 'auto';
        menu.style.bottom = 'auto';
        menu.style.maxHeight = '';
        menu.style.overflowY = '';
        menu.style.width = '';
        menu.classList.toggle(mobileClass, isMobile);
        menu.classList.remove('dropup');

        var width = menu.offsetWidth;
        var height = menu.offsetHeight;

        if (isMobile) {
            var mobileGap = 12;
            var maxWidth = Math.min(320, viewportWidth - mobileGap * 2);
            width = Math.min(Math.max(width, 220), maxWidth);
            menu.style.width = Math.round(width) + 'px';
            height = menu.offsetHeight;

            var center = rect.left + (rect.width / 2);
            var leftMobile = center - (width / 2);
            leftMobile = Math.max(mobileGap, Math.min(leftMobile, viewportWidth - width - mobileGap));

            var belowSpace = viewportHeight - rect.bottom - mobileGap;
            var aboveSpace = rect.top - mobileGap;
            var openUp = belowSpace < Math.min(height, 220) && aboveSpace > belowSpace;
            var availableHeight = openUp ? aboveSpace - gap : belowSpace - gap;
            var maxHeight = Math.max(160, Math.min(height, availableHeight, viewportHeight - mobileGap * 2));
            menu.style.maxHeight = Math.round(maxHeight) + 'px';
            menu.style.overflowY = height > maxHeight ? 'auto' : '';

            var topMobile = openUp ? rect.top - Math.min(height, maxHeight) - gap : rect.bottom + gap;
            topMobile = Math.max(mobileGap, Math.min(topMobile, viewportHeight - Math.min(height, maxHeight) - mobileGap));

            menu.style.left = Math.round(leftMobile) + 'px';
            menu.style.top = Math.round(topMobile) + 'px';
            menu.classList.toggle('dropup', openUp);
            return;
        }

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

    function handleDropdownToggle(event) {
        var toggle = event.target.closest('[data-vpn-dropdown-toggle]');
        if (!toggle) {
            return false;
        }

        event.preventDefault();
        event.stopPropagation();

        var root = toggle.closest('[data-vpn-dropdown]');
        if (!root) {
            return true;
        }

        if (active && active.toggle === toggle) {
            closeDropdown();
            return true;
        }

        var menu = root.querySelector('[data-vpn-dropdown-menu]');
        if (!menu) {
            return true;
        }

        openDropdown(root, toggle, menu);
        return true;
    }

    document.addEventListener('touchend', function (event) {
        if (!event.target.closest('[data-vpn-dropdown-toggle]')) {
            return;
        }

        lastTouchToggleAt = Date.now();
        handleDropdownToggle(event);
    }, true);

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
            if (Date.now() - lastTouchToggleAt < 650) {
                event.preventDefault();
                return;
            }

            handleDropdownToggle(event);
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

    window.addEventListener('resize', positionMenu);
    window.addEventListener('orientationchange', closeDropdown);
    window.addEventListener('scroll', positionMenu, true);
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', positionMenu);
        window.visualViewport.addEventListener('scroll', positionMenu);
    }
})();
