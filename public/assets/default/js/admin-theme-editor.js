(function () {
    'use strict';

    var editor = document.querySelector('[data-theme-editor]');
    if (!editor) {
        return;
    }

    var textarea = editor.querySelector('[data-theme-editor-code]');

    function createTextareaAdapter(element) {
        if (!element) {
            return null;
        }

        return {
            getValue: function () {
                return element.value;
            },
            setValue: function (value) {
                element.value = value;
            },
            focus: function () {
                element.focus();
            },
            onChange: function (callback) {
                element.addEventListener('input', callback);
            }
        };
    }

    var codeAdapter = createTextareaAdapter(textarea);
    var initialValue = codeAdapter ? codeAdapter.getValue() : '';
    var dirty = false;

    function updateDirtyState() {
        dirty = Boolean(codeAdapter && codeAdapter.getValue() !== initialValue);
    }

    if (codeAdapter) {
        codeAdapter.onChange(updateDirtyState);
    }

    var saveForm = document.getElementById('themeEditorSaveForm');
    if (saveForm) {
        saveForm.addEventListener('submit', function () {
            dirty = false;
        });
    }

    var resetButton = editor.querySelector('[data-theme-editor-reset]');
    if (resetButton && codeAdapter) {
        resetButton.addEventListener('click', function () {
            codeAdapter.setValue(initialValue);
            updateDirtyState();
            codeAdapter.focus();
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
