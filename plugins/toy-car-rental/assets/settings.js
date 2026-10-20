(function () {
    'use strict';
    const fileInput = document.getElementById('toyCustomSound');
    const resetInput = document.querySelector('[data-toy-rental-sound-reset]');
    const status = document.querySelector('[data-toy-rental-sound-preview-status]');
    let preview;
    const clearStatus = () => { if (status) status.textContent = ''; };
    const stopPreview = () => {
        if (!preview) return;
        const current = preview;
        preview = null;
        window.clearTimeout(current.loadTimeout);
        window.clearTimeout(current.playTimeout);
        current.audio.onended = null;
        current.audio.onerror = null;
        current.audio.pause();
        current.audio.removeAttribute('src');
        current.audio.load();
        current.button.removeAttribute('aria-busy');
        current.button.setAttribute('aria-pressed', 'false');
        if (current.objectUrl) URL.revokeObjectURL(current.objectUrl);
    };
    const updatePreviewButtons = () => {
        document.querySelectorAll('[data-toy-rental-sound-preview]').forEach(button => {
            button.disabled = !fileInput?.files?.length && (!button.dataset.soundUrl || Boolean(resetInput?.checked));
        });
    };
    document.addEventListener('change', event => {
        if (event.target !== fileInput && event.target !== resetInput) return;
        stopPreview();
        clearStatus();
        updatePreviewButtons();
    });
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-toy-rental-sound-preview]');
        if (!button || button.disabled) return;
        event.preventDefault();
        if (preview?.button === button) { stopPreview(); return; }
        stopPreview();
        clearStatus();
        const file = fileInput?.files?.[0];
        let objectUrl;
        let audio;
        const fail = () => {
            stopPreview();
            if (status) status.textContent = button.dataset.errorMessage || '';
        };
        try {
            objectUrl = file ? URL.createObjectURL(file) : null;
            const source = objectUrl || (!resetInput?.checked ? button.dataset.soundUrl : '');
            if (!source) return;
            document.querySelectorAll('[data-toy-rental-saved-sound]').forEach(saved => saved.pause());
            audio = new window.Audio(source);
            audio.preload = 'auto';
            audio.loop = false;
            const current = { audio, button, objectUrl };
            preview = current;
            button.setAttribute('aria-busy', 'true');
            button.setAttribute('aria-pressed', 'true');
            audio.onended = () => { if (preview === current) stopPreview(); };
            audio.onerror = () => { if (preview === current) fail(); };
            current.loadTimeout = window.setTimeout(() => { if (preview === current) fail(); }, 8000);
            Promise.resolve(audio.play()).then(() => {
                if (preview !== current) { audio.pause(); return; }
                window.clearTimeout(current.loadTimeout);
                button.removeAttribute('aria-busy');
                current.playTimeout = window.setTimeout(() => { if (preview === current) stopPreview(); }, 30000);
            }).catch(() => { if (preview === current) fail(); });
        } catch {
            if (!preview && objectUrl) URL.revokeObjectURL(objectUrl);
            fail();
        }
    });
    window.addEventListener('pagehide', stopPreview);
    document.addEventListener('visibilitychange', () => { if (document.hidden) stopPreview(); });
    updatePreviewButtons();

    const normalizeSearch = value => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
    document.addEventListener('input', event => {
        const input = event.target.closest('[data-toy-rental-recipient-search]');
        if (!input) return;
        const container = input.closest('form') || document;
        const list = container.querySelector('[data-toy-rental-recipient-list]');
        if (!list) return;
        const query = normalizeSearch(input.value);
        let matches = 0;
        list.querySelectorAll('[data-toy-rental-recipient]').forEach(item => {
            const visible = normalizeSearch(item.dataset.searchText || item.textContent).includes(query);
            item.hidden = !visible;
            if (visible) matches++;
        });
        const empty = container.querySelector('[data-toy-rental-recipient-no-results]');
        if (empty) empty.hidden = matches > 0;
    });
})();
