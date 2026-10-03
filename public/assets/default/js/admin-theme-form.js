(function () {
    'use strict';
    document.querySelectorAll('[data-theme-preview]').forEach(function (section) {
        var methods = section.querySelectorAll('input[name="preview_method"]');
        function selectMethod() {
            var selected = section.querySelector('input[name="preview_method"]:checked');
            section.querySelectorAll('[data-preview-panel]').forEach(function (panel) {
                var active = panel.dataset.previewPanel === selected.value;
                panel.hidden = !active;
                panel.querySelectorAll('input, button').forEach(function (input) { input.disabled = !active; });
            });
        }
        methods.forEach(function (method) { method.addEventListener('change', selectMethod); });
        selectMethod();
    });
}());
