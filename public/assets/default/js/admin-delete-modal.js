$(function () {
    const modalElement = $('[data-admin-delete-modal]');
    if (!modalElement.length) {
        return;
    }

    const bootstrapApi = typeof bootstrap !== 'undefined'
        ? bootstrap
        : (window.bootstrap || null);

    if (!bootstrapApi) {
        return;
    }

    const modal = new bootstrapApi.Modal(modalElement[0]);
    const messageElement = modalElement.find('[data-admin-delete-modal-message]');
    const itemWrap = modalElement.find('[data-admin-delete-modal-item-wrap]');
    const itemElement = modalElement.find('[data-admin-delete-modal-item]');
    const confirmButton = modalElement.find('[data-admin-delete-modal-confirm]');
    const confirmLabel = modalElement.find('[data-admin-delete-modal-confirm-label]');
    const defaultMessage = String(messageElement.text() || '').trim();
    const defaultConfirmLabel = String(confirmLabel.text() || '').trim();
    let activeForm = null;

    $(document).on('submit', '[data-admin-delete-form]', function (event) {
        const form = this;

        if (form.dataset.deleteConfirmed === '1') {
            form.dataset.deleteConfirmed = '0';
            return;
        }

        event.preventDefault();
        activeForm = form;

        const message = String(form.dataset.deleteMessage || '').trim() || defaultMessage;
        const item = String(form.dataset.deleteItem || '').trim();
        const confirmText = String(form.dataset.deleteConfirmLabel || '').trim() || defaultConfirmLabel;

        messageElement.text(message);
        itemElement.text(item);
        confirmLabel.text(confirmText);
        itemWrap.toggleClass('d-none', item === '');
        modal.show();
    });

    confirmButton.on('click', function () {
        if (!activeForm) {
            return;
        }

        activeForm.dataset.deleteConfirmed = '1';
        activeForm.requestSubmit
            ? activeForm.requestSubmit()
            : activeForm.submit();
    });

    modalElement.on('hidden.bs.modal', function () {
        activeForm = null;
        messageElement.text(defaultMessage);
        confirmLabel.text(defaultConfirmLabel);
        itemElement.text('');
        itemWrap.addClass('d-none');
    });
});
