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
            height: visual ? visual.height : (window.innerHeight || document.documentElement.clientHeight),
            offsetLeft: visual ? visual.offsetLeft : 0,
            offsetTop: visual ? visual.offsetTop : 0
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
        active.menu.hidden = true;
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
        var viewportLeft = view.offsetLeft || 0;
        var viewportTop = view.offsetTop || 0;
        var viewportPadding = 8;
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
            leftMobile = Math.max(viewportLeft + mobileGap, Math.min(leftMobile, viewportLeft + viewportWidth - width - mobileGap));

            var belowSpace = viewportTop + viewportHeight - rect.bottom - mobileGap;
            var aboveSpace = rect.top - viewportTop - mobileGap;
            var openUp = belowSpace < Math.min(height, 220) && aboveSpace > belowSpace;
            var availableHeight = openUp ? aboveSpace - gap : belowSpace - gap;
            var maxHeight = Math.max(160, Math.min(height, availableHeight, viewportHeight - mobileGap * 2));
            menu.style.maxHeight = Math.round(maxHeight) + 'px';
            menu.style.overflowY = height > maxHeight ? 'auto' : '';

            var topMobile = openUp ? rect.top - Math.min(height, maxHeight) - gap : rect.bottom + gap;
            topMobile = Math.max(viewportTop + mobileGap, Math.min(topMobile, viewportTop + viewportHeight - Math.min(height, maxHeight) - mobileGap));

            menu.style.left = Math.round(leftMobile) + 'px';
            menu.style.top = Math.round(topMobile) + 'px';
            menu.classList.toggle('dropup', openUp);
            return;
        }

        var left = rect.right - width;
        if (left < viewportLeft + viewportPadding) {
            left = Math.min(rect.left, viewportLeft + viewportWidth - width - viewportPadding);
        }
        if (left + width > viewportLeft + viewportWidth - viewportPadding) {
            left = viewportLeft + viewportWidth - width - viewportPadding;
        }
        left = Math.max(viewportLeft + viewportPadding, left);

        var top = rect.bottom + gap;
        if (top + height > viewportTop + viewportHeight - viewportPadding) {
            top = rect.top - height - gap;
        }
        if (top < viewportTop + viewportPadding) {
            top = Math.max(viewportTop + viewportPadding, viewportTop + viewportHeight - height - viewportPadding);
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

        menu.hidden = false;
        document.body.appendChild(menu);
        menu.classList.add(portalClass, 'show');
        toggle.setAttribute('aria-expanded', 'true');
        root.classList.add('is-open');
        positionMenu();
    }

    function copyText(value) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(value);
        }

        return new Promise(function (resolve, reject) {
            var textarea = document.createElement('textarea');
            textarea.value = value;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'fixed';
            textarea.style.top = '-1000px';
            textarea.style.left = '-1000px';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            textarea.setSelectionRange(0, textarea.value.length);
            try {
                if (document.execCommand('copy')) {
                    resolve();
                } else {
                    reject(new Error('copy_failed'));
                }
            } catch (error) {
                reject(error);
            } finally {
                textarea.remove();
            }
        });
    }

    function handlePageScroll(event) {
        if (!active) {
            return;
        }

        if (event && event.target && active.menu.contains(event.target)) {
            return;
        }

        closeDropdown();
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
            if (!value) {
                return;
            }

            copyText(value).then(function () {
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
    window.addEventListener('scroll', handlePageScroll, true);
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', positionMenu);
        window.visualViewport.addEventListener('scroll', closeDropdown);
    }
})();
