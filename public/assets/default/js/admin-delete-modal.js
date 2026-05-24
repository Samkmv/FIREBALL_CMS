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

    const deleteForms = $('[data-admin-delete-form]');
    if (!deleteForms.length) {
        return;
    }

    const modal = new bootstrapApi.Modal(modalElement[0]);
    const messageElement = modalElement.find('[data-admin-delete-modal-message]');
    const itemWrap = modalElement.find('[data-admin-delete-modal-item-wrap]');
    const itemElement = modalElement.find('[data-admin-delete-modal-item]');
    const confirmButton = modalElement.find('[data-admin-delete-modal-confirm]');
    const defaultMessage = String(messageElement.text() || '').trim();
    let activeForm = null;

    deleteForms.on('submit', function (event) {
        const form = this;

        if (form.dataset.deleteConfirmed === '1') {
            form.dataset.deleteConfirmed = '0';
            return;
        }

        event.preventDefault();
        activeForm = form;

        const message = String(form.dataset.deleteMessage || '').trim() || defaultMessage;
        const item = String(form.dataset.deleteItem || '').trim();

        messageElement.text(message);
        itemElement.text(item);
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
        itemElement.text('');
        itemWrap.addClass('d-none');
    });
});
