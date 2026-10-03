(function () {
    'use strict';

    var editor = document.querySelector('[data-theme-editor]');
    if (!editor) {
        return;
    }

    var textarea = editor.querySelector('[data-theme-editor-code]');
    var toolbar = editor.querySelector('.theme-editor-toolbar[data-simplebar]');

    function refreshToolbarScroll() {
        if (!toolbar || typeof window.SimpleBar === 'undefined') {
            return;
        }

        try {
            var instance = window.SimpleBar.instances && window.SimpleBar.instances.get(toolbar);
            if (!instance) {
                instance = new window.SimpleBar(toolbar, { autoHide: false });
            }
            instance.recalculate();
        } catch (error) {
            // Native overflow remains available if SimpleBar cannot refresh.
        }
    }

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

    var dirtyIndicator = editor.querySelector('[data-theme-editor-dirty]');
    function updateDirtyState() {
        dirty = Boolean(codeAdapter && codeAdapter.getValue() !== initialValue);
        if (dirtyIndicator) { dirtyIndicator.hidden = !dirty; }
    }

    if (codeAdapter) {
        codeAdapter.onChange(updateDirtyState);
    }

    var saveForm = document.getElementById('themeEditorSaveForm');
    if (saveForm) {
        saveForm.addEventListener('submit', function () {
            if (textarea && codeAdapter) { textarea.value = codeAdapter.getValue(); }
            dirty = false;
        });
    }

    var monacoEditor = null;
    var monacoModel = null;
    var themeObserver = null;
    var previewObserver = null;
    var mount = editor.querySelector('[data-theme-editor-monaco]');
    var status = editor.querySelector('[data-theme-editor-status]');

    if (textarea && mount && editor.dataset.monacoUrl) {
        import(editor.dataset.monacoUrl).then(function (module) {
            var monaco = module.monaco;
            monaco.editor.defineTheme('fireball-dark', {
                base: 'vs-dark', inherit: true, rules: [],
                colors: {
                    'editor.background': '#101722',
                    'editorLineNumber.foreground': '#64748b',
                    'editorLineNumber.activeForeground': '#dce4ef',
                    'editor.lineHighlightBackground': '#192436',
                    'editor.selectionBackground': '#33445f',
                    'editorCursor.foreground': '#ff5a3c'
                }
            });
            mount.hidden = false;
            monacoEditor = monaco.editor.create(mount, {
                value: textarea.value,
                language: textarea.dataset.editorLanguage === 'text' ? 'plaintext' : textarea.dataset.editorLanguage,
                theme: document.documentElement.dataset.bsTheme === 'dark' ? 'fireball-dark' : 'vs',
                automaticLayout: true,
                tabSize: 4,
                insertSpaces: true,
                detectIndentation: true,
                autoIndent: 'full',
                fontSize: 14,
                minimap: {enabled: false},
                scrollBeyondLastLine: false,
                bracketPairColorization: {enabled: true},
                padding: {top: 16, bottom: 16}
            });
            monacoModel = monacoEditor.getModel();
            textarea.hidden = true;
            textarea.dataset.editorAdapter = 'monaco';
            codeAdapter = {
                getValue: function () { return monacoEditor.getValue(); },
                setValue: function (value) {
                    monacoEditor.pushUndoStop();
                    monacoEditor.executeEdits('discard', [{range: monacoModel.getFullModelRange(), text: value}]);
                    monacoEditor.pushUndoStop();
                },
                focus: function () { monacoEditor.focus(); }
            };
            monacoEditor.onDidChangeModelContent(function () {
                textarea.value = codeAdapter.getValue();
                updateDirtyState();
            });
            monacoEditor.addCommand(monaco.KeyMod.CtrlCmd | monaco.KeyCode.KeyS, function () {
                if (saveForm) { saveForm.requestSubmit(); }
            });
            monacoEditor.onDidChangeCursorPosition(function (event) {
                if (status) { status.textContent = 'Monaco · ' + event.position.lineNumber + ':' + event.position.column + ' · Ctrl/Cmd+S · Tab · Ctrl/Cmd+F'; }
            });
            themeObserver = new MutationObserver(function () {
                monaco.editor.setTheme(document.documentElement.dataset.bsTheme === 'dark' ? 'fireball-dark' : 'vs');
            });
            themeObserver.observe(document.documentElement, {attributes: true, attributeFilter: ['data-bs-theme']});
            updateDirtyState();
        }).catch(function () {
            if (monacoEditor) { monacoEditor.dispose(); monacoEditor = null; }
            if (monacoModel) { monacoModel.dispose(); monacoModel = null; }
            mount.hidden = true;
            textarea.hidden = false;
            codeAdapter = createTextareaAdapter(textarea);
            editor.dataset.editorAdapter = 'textarea';
            if (status) { status.textContent = editor.dataset.editorFallbackMessage; }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (!monacoEditor && textarea === document.activeElement && (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
            event.preventDefault();
            if (saveForm) { saveForm.requestSubmit(); }
        }
    });
    window.addEventListener('pagehide', function (event) {
        if (event.persisted) { return; }
        if (themeObserver) { themeObserver.disconnect(); }
        if (previewObserver) { previewObserver.disconnect(); }
        if (monacoEditor) { monacoEditor.dispose(); }
        if (monacoModel) { monacoModel.dispose(); }
    });

    var previewPane = editor.querySelector('[data-theme-preview-pane]');
    var previewToggle = editor.querySelector('[data-theme-preview-toggle]');
    var previewLayout = editor.querySelector('.theme-editor-layout');
    var previewStage = editor.querySelector('[data-theme-preview-stage]');
    var previewFrame = editor.querySelector('[data-theme-preview-frame]');
    var previewReload = editor.querySelector('[data-theme-preview-reload]');
    function fitPreview() {
        if (!previewStage || !previewFrame || !previewStage.clientWidth) { return; }
        var width = {desktop: 1280, tablet: 768, mobile: 375}[previewStage.dataset.device] || 1280;
        var available = Math.max(1, previewStage.clientWidth - 16);
        var scale = Math.min(1, available / width);
        previewFrame.style.width = width + 'px';
        previewFrame.style.height = Math.max(1, (previewStage.clientHeight - 16) / scale) + 'px';
        previewFrame.style.transform = 'scale(' + scale + ')';
        previewFrame.style.left = (8 + (available - width * scale) / 2) + 'px';
    }
    if (previewStage && previewFrame) {
        previewObserver = new ResizeObserver(fitPreview);
        previewObserver.observe(previewStage);
        fitPreview();
    }
    if (previewPane && previewToggle && previewLayout) {
        previewToggle.addEventListener('click', function () {
            previewPane.hidden = !previewPane.hidden;
            previewToggle.setAttribute('aria-expanded', String(!previewPane.hidden));
            previewLayout.classList.toggle('preview-hidden', previewPane.hidden);
            fitPreview();
        });
    }
    editor.querySelectorAll('[data-theme-preview-device]').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!previewStage) { return; }
            previewStage.dataset.device = button.dataset.themePreviewDevice;
            fitPreview();
            editor.querySelectorAll('[data-theme-preview-device]').forEach(function (item) {
                item.setAttribute('aria-pressed', String(item === button));
            });
        });
    });
    if (previewFrame && previewReload) {
        previewReload.addEventListener('click', function () {
            // Reload the page currently visited inside preview, preserving its URL.
            try { previewFrame.contentWindow.location.reload(); }
            catch (error) { previewFrame.src = previewFrame.getAttribute('src'); }
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

    window.requestAnimationFrame(refreshToolbarScroll);
    window.setTimeout(refreshToolbarScroll, 150);
    window.addEventListener('resize', refreshToolbarScroll, { passive: true });
}());
