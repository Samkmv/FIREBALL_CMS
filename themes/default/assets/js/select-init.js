(function () {
    function getSelectConfig(select) {
        const defaultConfig = {
            allowHTML: select.getAttribute('data-select-allow-html') === 'true',
            searchPlaceholderValue: 'Search...',
            removeItemButton: false,
            editItems: true,
            searchEnabled: false,
            position: 'auto',
            shouldSort: false,
            itemSelectText: '',
            classNames: {
                containerInner: 'form-select'
            }
        };

        const rawConfig = select.getAttribute('data-select');
        if (rawConfig === null || rawConfig === '') {
            return defaultConfig;
        }

        try {
            const parsedConfig = JSON.parse(rawConfig);
            return Object.assign({}, defaultConfig, parsedConfig, {
                classNames: Object.assign({}, defaultConfig.classNames, parsedConfig.classNames || {})
            });
        } catch (error) {
            return defaultConfig;
        }
    }

    function getViewportMetrics() {
        const visualViewport = window.visualViewport || null;

        return {
            width: visualViewport ? visualViewport.width : (window.innerWidth || document.documentElement.clientWidth),
            height: visualViewport ? visualViewport.height : (window.innerHeight || document.documentElement.clientHeight),
            left: visualViewport ? visualViewport.offsetLeft : 0,
            top: visualViewport ? visualViewport.offsetTop : 0
        };
    }

    function getChoicesContainer(select) {
        if (!select) {
            return null;
        }

        return select.closest('.choices') || (select.parentElement ? select.parentElement.querySelector('.choices') : null);
    }

    function getChoicesDropdown(container) {
        return container
            ? container.querySelector('.choices__list--dropdown, .choices__list[aria-expanded]')
            : null;
    }

    function clearChoicesDropdownPosition(dropdown) {
        if (!dropdown) {
            return;
        }

        dropdown.style.position = '';
        dropdown.style.top = '';
        dropdown.style.left = '';
        dropdown.style.right = '';
        dropdown.style.width = '';
        dropdown.style.maxHeight = '';
        dropdown.style.overflowY = '';
        dropdown.style.zIndex = '';
    }

    function positionChoicesDropdown(select) {
        const container = getChoicesContainer(select);
        const dropdown = getChoicesDropdown(container);
        const control = container ? (container.querySelector('.form-select') || container) : null;

        const isOpen = container && (
            container.classList.contains('is-open')
            || (dropdown && dropdown.classList.contains('is-active'))
            || (dropdown && dropdown.getAttribute('aria-expanded') === 'true')
        );

        if (!container || !dropdown || !control || !isOpen) {
            return;
        }

        const viewport = getViewportMetrics();
        const margin = 12;
        const controlRect = control.getBoundingClientRect();

        dropdown.style.position = 'fixed';
        dropdown.style.right = 'auto';
        dropdown.style.width = Math.max(180, controlRect.width) + 'px';
        dropdown.style.zIndex = '12050';
        dropdown.style.maxHeight = '';
        dropdown.style.overflowY = '';

        const dropdownRect = dropdown.getBoundingClientRect();
        const availableBelow = viewport.top + viewport.height - controlRect.bottom - margin;
        const availableAbove = controlRect.top - viewport.top - margin;
        const openAbove = availableBelow < dropdownRect.height && availableAbove > availableBelow;
        const availableHeight = Math.max(64, Math.min(openAbove ? availableAbove : availableBelow, viewport.height - (margin * 2)));
        const renderedHeight = Math.min(dropdownRect.height, availableHeight);
        const top = openAbove
            ? Math.max(viewport.top + margin, controlRect.top - renderedHeight - 6)
            : Math.min(controlRect.bottom + 6, viewport.top + viewport.height - renderedHeight - margin);
        const left = Math.max(
            viewport.left + margin,
            Math.min(controlRect.left, viewport.left + viewport.width - Math.max(180, controlRect.width) - margin)
        );

        dropdown.style.top = top + 'px';
        dropdown.style.left = left + 'px';
        dropdown.style.maxHeight = availableHeight + 'px';
        dropdown.style.overflowY = dropdownRect.height > availableHeight ? 'auto' : 'visible';
        container.classList.toggle('is-flipped', openAbove);
    }

    function syncChoicesChrome(select) {
        const container = getChoicesContainer(select);
        if (!container) {
            return;
        }

        const shouldHideClear = select.getAttribute('data-select-clear') === 'false';
        if (shouldHideClear) {
            container.querySelectorAll('.choices__button').forEach(function (button) {
                button.remove();
            });
        }

        if (!select.hasAttribute('data-select-floating')) {
            return;
        }

        const schedulePosition = function () {
            window.requestAnimationFrame(function () {
                positionChoicesDropdown(select);
            });
        };

        if (select.dataset.selectFloatingReady === '1') {
            schedulePosition();
            return;
        }

        select.dataset.selectFloatingReady = '1';
        select.addEventListener('showDropdown', schedulePosition);
        select.addEventListener('choice', function () {
            clearChoicesDropdownPosition(getChoicesDropdown(container));
        });
        select.addEventListener('hideDropdown', function () {
            clearChoicesDropdownPosition(getChoicesDropdown(container));
            container.classList.remove('is-flipped');
        });
        ['click', 'focus'].forEach(function (eventName) {
            container.addEventListener(eventName, schedulePosition, true);
        });
        ['resize', 'scroll'].forEach(function (eventName) {
            window.addEventListener(eventName, schedulePosition, { passive: true });
        });
        if (window.visualViewport) {
            window.visualViewport.addEventListener('resize', schedulePosition, { passive: true });
            window.visualViewport.addEventListener('scroll', schedulePosition, { passive: true });
        }

        schedulePosition();
    }

    function initDataSelects(scope) {
        if (typeof window.Choices === 'undefined') {
            return;
        }

        const root = scope && typeof scope.querySelectorAll === 'function' ? scope : document;
        root.querySelectorAll('select[data-select]').forEach(function (select) {
            if (select.dataset.selectEnhanced === '1') {
                return;
            }

            if (select.closest('.choices')) {
                select.dataset.selectEnhanced = '1';
                syncChoicesChrome(select);
                return;
            }

            try {
                new window.Choices(select, getSelectConfig(select));
                select.dataset.selectEnhanced = '1';
            } catch (error) {
            }

            syncChoicesChrome(select);
        });
    }

    window.initDataSelects = initDataSelects;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initDataSelects(document);
        }, { once: true });
        return;
    }

    initDataSelects(document);
})();
