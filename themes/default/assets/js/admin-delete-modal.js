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
    const titleElement = modalElement.find('[data-admin-delete-modal-title]');
    const iconWrap = modalElement.find('[data-admin-delete-modal-icon-wrap]');
    const iconElement = modalElement.find('[data-admin-delete-modal-icon]');
    const messageElement = modalElement.find('[data-admin-delete-modal-message]');
    const itemWrap = modalElement.find('[data-admin-delete-modal-item-wrap]');
    const itemLabel = modalElement.find('[data-admin-delete-modal-item-label]');
    const itemElement = modalElement.find('[data-admin-delete-modal-item]');
    const hintElement = modalElement.find('[data-admin-delete-modal-hint]');
    const confirmButton = modalElement.find('[data-admin-delete-modal-confirm]');
    const confirmIcon = modalElement.find('[data-admin-delete-modal-confirm-icon]');
    const confirmLabel = modalElement.find('[data-admin-delete-modal-confirm-label]');
    const defaultTitle = String(titleElement.text() || '').trim();
    const defaultMessage = String(messageElement.text() || '').trim();
    const defaultItemLabel = String(itemLabel.text() || '').trim();
    const defaultHint = String(hintElement.text() || '').trim();
    const defaultConfirmLabel = String(confirmLabel.text() || '').trim();
    const defaultIcon = String(iconElement.attr('class') || 'ci-trash fs-2')
        .split(/\s+/)
        .find((className) => className.startsWith('ci-')) || 'ci-trash';
    const variants = {
        danger: {
            button: 'btn-danger',
            iconBackground: 'bg-danger-subtle',
            iconColor: 'text-danger',
        },
        warning: {
            button: 'btn-warning',
            iconBackground: 'bg-warning-subtle',
            iconColor: 'text-warning-emphasis',
        },
        primary: {
            button: 'btn-primary',
            iconBackground: 'bg-primary-subtle',
            iconColor: 'text-primary',
        },
    };
    let activeForm = null;

    const formValue = (form, name, fallback = '') => {
        const value = String(form.getAttribute(name) || '').trim();
        return value || fallback;
    };

    const applyVariant = (variantName, requestedIcon) => {
        const variant = variants[variantName] || variants.danger;
        const icon = /^ci-[a-z0-9-]+$/i.test(requestedIcon) ? requestedIcon : defaultIcon;

        confirmButton.removeClass('btn-danger btn-warning btn-primary').addClass(variant.button);
        iconWrap
            .removeClass('bg-danger-subtle bg-warning-subtle bg-primary-subtle text-danger text-warning-emphasis text-primary')
            .addClass(`${variant.iconBackground} ${variant.iconColor}`);
        iconElement.attr('class', `${icon} fs-2`);
        confirmIcon.attr('class', icon);
    };

    const resetModal = () => {
        titleElement.text(defaultTitle);
        messageElement.text(defaultMessage);
        itemLabel.text(defaultItemLabel);
        hintElement.text(defaultHint);
        confirmLabel.text(defaultConfirmLabel);
        itemElement.text('');
        itemWrap.addClass('d-none');
        applyVariant('danger', defaultIcon);
    };

    $(document).on('submit', '[data-admin-delete-form]', function (event) {
        const form = this;

        if (form.dataset.deleteConfirmed === '1') {
            form.dataset.deleteConfirmed = '0';
            return;
        }

        event.preventDefault();
        activeForm = form;

        const title = formValue(form, 'data-confirm-title', defaultTitle);
        const message = formValue(form, 'data-delete-message', defaultMessage);
        const item = formValue(form, 'data-delete-item');
        const itemLabelText = formValue(form, 'data-confirm-item-label', defaultItemLabel);
        const hint = formValue(form, 'data-confirm-hint', defaultHint);
        const confirmText = formValue(form, 'data-delete-confirm-label', defaultConfirmLabel);
        const variant = formValue(form, 'data-confirm-variant', 'danger');
        const icon = formValue(form, 'data-confirm-icon', defaultIcon);

        titleElement.text(title);
        messageElement.text(message);
        itemLabel.text(itemLabelText);
        itemElement.text(item);
        hintElement.text(hint);
        confirmLabel.text(confirmText);
        itemWrap.toggleClass('d-none', item === '');
        applyVariant(variant, icon);
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
        resetModal();
    });
});
