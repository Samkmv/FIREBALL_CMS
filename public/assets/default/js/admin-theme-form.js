(function () {
    'use strict';
    document.querySelectorAll('[data-theme-preview]').forEach(function (section) {
        var source = section.querySelector('[name="preview_source"]');
        var upload = section.querySelector('[name="preview_upload"]');
        // The last choice wins, including selections made by the file manager.
        source.addEventListener('input', function () { if (source.value.trim()) upload.value = ''; });
        upload.addEventListener('change', function () { if (upload.files.length) source.value = ''; });
    });
}());
