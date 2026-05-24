(function () {
    function getSelectConfig(select) {
        const defaultConfig = {
            allowHTML: true,
            searchPlaceholderValue: 'Search...',
            removeItemButton: true,
            editItems: true,
            searchEnabled: false,
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
                return;
            }

            try {
                new window.Choices(select, getSelectConfig(select));
                select.dataset.selectEnhanced = '1';
            } catch (error) {
            }
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
