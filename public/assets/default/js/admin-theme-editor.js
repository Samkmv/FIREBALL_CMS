(function () {
    'use strict';

    var editor = document.querySelector('[data-theme-editor]');
    if (!editor) {
        return;
    }

    var textarea = editor.querySelector('[data-theme-editor-code]');
    var initialValue = textarea ? textarea.value : '';
    var dirty = false;

    function updateDirtyState() {
        dirty = Boolean(textarea && textarea.value !== initialValue);
    }

    if (textarea) {
        textarea.addEventListener('input', updateDirtyState);
    }

    var saveForm = document.getElementById('themeEditorSaveForm');
    if (saveForm) {
        saveForm.addEventListener('submit', function () {
            dirty = false;
        });
    }

    var resetButton = editor.querySelector('[data-theme-editor-reset]');
    if (resetButton && textarea) {
        resetButton.addEventListener('click', function () {
            textarea.value = initialValue;
            updateDirtyState();
            textarea.focus();
        });
    }

    var themeSelect = editor.querySelector('[data-theme-editor-theme-select]');
    if (themeSelect) {
        var initialTheme = themeSelect.value;
        themeSelect.addEventListener('change', function () {
            if (!dirty || window.confirm(editor.dataset.unsavedMessage || 'Unsaved changes will be lost.')) {
                dirty = false;
                window.location.href = themeSelect.value;
                return;
            }
            themeSelect.value = initialTheme;
        });
    }

    editor.querySelectorAll('[data-theme-editor-file-link]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            if (dirty && !window.confirm(editor.dataset.unsavedMessage || 'Unsaved changes will be lost.')) {
                event.preventDefault();
            }
        });
    });

    var deleteForm = document.querySelector('[data-theme-editor-delete-form]');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function (event) {
            if (!window.confirm(deleteForm.dataset.confirm || 'Delete this item?')) {
                event.preventDefault();
            }
        });
    }

    window.addEventListener('beforeunload', function (event) {
        if (!dirty) {
            return;
        }
        event.preventDefault();
        event.returnValue = editor.dataset.unsavedMessage || '';
    });
}());
