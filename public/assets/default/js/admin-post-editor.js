$(function () {
    const editorFontFamily = 'Roboto, "Helvetica Neue", Arial, sans-serif';
    const defaultFontControls = (
        typeof Jodit !== 'undefined'
        && Jodit.defaultOptions
        && Jodit.defaultOptions.controls
        && Jodit.defaultOptions.controls.font
        && Jodit.defaultOptions.controls.font.list
    ) ? Jodit.defaultOptions.controls.font.list : {};
    const fontList = Object.assign(
        {
            [editorFontFamily]: 'Roboto'
        },
        defaultFontControls
    );
    const defaultButtons = (
        typeof Jodit !== 'undefined'
        && Jodit.defaultOptions
        && Array.isArray(Jodit.defaultOptions.buttons)
    ) ? Jodit.defaultOptions.buttons.slice() : [];

    if (defaultButtons.length) {
        if (defaultButtons.indexOf('font') === -1) {
            defaultButtons.unshift('font');
        }

        if (defaultButtons.indexOf('fontsize') === -1) {
            defaultButtons.splice(Math.min(defaultButtons.length, 1), 0, 'fontsize');
        }
    }

    const prefersNativeSourceEditor = (function () {
        if (typeof window === 'undefined') {
            return false;
        }

        const hasCoarsePointer = typeof window.matchMedia === 'function'
            && window.matchMedia('(pointer: coarse)').matches;
        const hasTouchPoints = Number(
            (window.navigator && (window.navigator.maxTouchPoints || window.navigator.msMaxTouchPoints)) || 0
        ) > 0;

        return hasCoarsePointer || hasTouchPoints || 'ontouchstart' in window;
    }());

    const publishedAtField = document.querySelector('[data-post-datepicker]');
    if (publishedAtField && typeof flatpickr !== 'undefined') {
        flatpickr(publishedAtField, {
            enableTime: true,
            time_24hr: true,
            allowInput: true,
            minuteIncrement: 1,
            dateFormat: 'Y-m-d H:i:S',
            altInput: true,
            altFormat: 'd.m.Y H:i',
            disableMobile: true
        });
    }

    const editorField = document.querySelector('[data-post-editor]');
    if (!editorField || typeof Jodit === 'undefined') {
        return;
    }

    const editor = Jodit.make(editorField, {
        height: 500,
        toolbarAdaptive: false,
        buttonsSticky: false,
        buttons: defaultButtons.length ? defaultButtons : undefined,
        showCharsCounter: false,
        showWordsCounter: false,
        askBeforePasteHTML: false,
        askBeforePasteFromWord: false,
        defaultMode: Jodit.MODE_WYSIWYG,
        sourceEditor: prefersNativeSourceEditor ? 'area' : 'ace',
        beautifyHTML: !prefersNativeSourceEditor,
        controls: {
            font: {
                list: fontList
            }
        },
        style: {
            font: `400 16px ${editorFontFamily}`
        }
    });

    const applyEditorFont = function () {
        if (editor.container) {
            editor.container.style.fontFamily = editorFontFamily;
        }

        if (editor.workplace) {
            editor.workplace.style.fontFamily = editorFontFamily;
        }

        if (editor.editor) {
            editor.editor.style.fontFamily = editorFontFamily;
            editor.editor.style.fontSize = '16px';
        }
    };

    applyEditorFont();
    editor.e.on('afterSetMode', applyEditorFont);
    editor.e.on('afterInit', applyEditorFont);

    const form = editorField.form;
    if (!form) {
        return;
    }

    form.addEventListener('submit', function () {
        editor.synchronizeValues();
    });
});
