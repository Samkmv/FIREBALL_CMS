$(function () {
    const managerButtons = $('[data-file-manager-open]');
    const fileSelectButtons = $('[data-file-select]');
    const renameButtons = $('[data-file-rename-open]');
    const renameModalElement = $('[data-file-rename-modal]');
    const renamePathInput = renameModalElement.find('[data-file-rename-path-input]');
    const renameInput = renameModalElement.find('[data-file-rename-input]');
    const renameCurrentName = renameModalElement.find('[data-file-rename-current-name]');
    const renameExtension = renameModalElement.find('[data-file-rename-extension]');
    const renameExtensionWrap = renameModalElement.find('[data-file-rename-extension-wrap]');
    const previewButtons = $('[data-file-preview]');
    const previewModalElement = $('[data-file-preview-modal]');
    const previewTitle = previewModalElement.find('[data-file-preview-title]');
    const previewImage = previewModalElement.find('[data-file-preview-image]');
    const previewOpen = previewModalElement.find('[data-file-preview-open]');
    const popupName = 'fireball_file_manager';
    const bootstrapApi = typeof bootstrap !== 'undefined'
        ? bootstrap
        : (window.bootstrap || null);
    const previewModal = previewModalElement.length && bootstrapApi
        ? new bootstrapApi.Modal(previewModalElement[0])
        : null;
    const renameModal = renameModalElement.length && bootstrapApi
        ? new bootstrapApi.Modal(renameModalElement[0])
        : null;

    managerButtons.on('click', function () {
        const button = $(this);
        const inputId = String(button.data('file-manager-input') || '');
        const baseUrl = String(button.data('file-manager-url') || '');
        const directory = String(button.data('file-manager-dir') || '');

        if (!inputId || !baseUrl) {
            return;
        }

        const url = new URL(baseUrl, window.location.origin);
        url.searchParams.set('picker', '1');
        url.searchParams.set('field', inputId);

        if (directory) {
            url.searchParams.set('dir', directory);
        }

        window.open(url.toString(), popupName, 'width=1280,height=860,resizable=yes,scrollbars=yes');
    });

    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin || !event.data || event.data.type !== 'fireball:file:selected') {
            return;
        }

        const input = document.getElementById(String(event.data.field || ''));
        if (!input) {
            return;
        }

        input.value = String(event.data.value || '');
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));

        const previewImageSelector = input.getAttribute('data-file-preview-image');
        const previewTextSelector = input.getAttribute('data-file-preview-text');
        const previewWrap = document.getElementById(input.id + '_preview_wrap') || document.getElementById(input.id.replace(/_image$/, '_image_preview_wrap'));

        if (previewImageSelector) {
            const image = document.querySelector(previewImageSelector);
            if (image) {
                image.setAttribute('src', String(event.data.value || ''));
            }
        }

        if (previewTextSelector) {
            const text = document.querySelector(previewTextSelector);
            if (text) {
                text.textContent = String(event.data.value || '');
            }
        }

        if (previewWrap) {
            previewWrap.classList.toggle('d-none', !event.data.value);
        }
    });

    fileSelectButtons.on('click', function () {
        const button = $(this);
        const field = String(button.data('file-select-field') || '');
        const value = String(button.data('file-select-value') || '');

        if (!field || !window.opener) {
            return;
        }

        window.opener.postMessage({
            type: 'fireball:file:selected',
            field: field,
            value: value,
        }, window.location.origin);

        window.close();
    });

    renameButtons.on('click', function () {
        if (!renameModal) {
            return;
        }

        const button = $(this);
        const path = String(button.data('file-rename-path') || '');
        const name = String(button.data('file-rename-name') || '');
        const fullName = String(button.data('file-rename-full-name') || '');
        const extension = String(button.data('file-rename-extension') || '');

        renamePathInput.val(path);
        renameInput.val(name);
        renameCurrentName.text(fullName);
        renameExtension.text(extension);
        renameExtensionWrap.toggleClass('d-none', !extension);
        renameModal.show();

        window.setTimeout(function () {
            renameInput.trigger('focus').trigger('select');
        }, 150);
    });

    previewButtons.on('click', function () {
        if (!previewModal) {
            return;
        }

        const button = $(this);
        const imageUrl = String(button.data('file-preview-url') || '');
        const imageName = String(button.data('file-preview-name') || '');

        previewTitle.text(imageName || 'Preview');
        previewImage.attr('src', imageUrl).attr('alt', imageName);
        previewOpen.attr('href', imageUrl);
        previewModal.show();
    });

    previewModalElement.on('hidden.bs.modal', function () {
        previewTitle.text('');
        previewImage.attr('src', '').attr('alt', '');
        previewOpen.attr('href', '');
    });

    renameModalElement.on('hidden.bs.modal', function () {
        renamePathInput.val('');
        renameInput.val('');
        renameCurrentName.text('');
        renameExtension.text('');
        renameExtensionWrap.removeClass('d-none');
    });
});
