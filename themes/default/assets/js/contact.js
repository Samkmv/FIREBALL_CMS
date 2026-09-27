document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('[data-contact-form]');

    forms.forEach(function (form) {
        // CONTACT_DUPLICATE_FIX_V1
        let contactSubmitting = false;

        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                form.classList.add('was-validated');
                return;
            }

            if (contactSubmitting || form.dataset.contactSubmitting === '1') {
                event.preventDefault();
                event.stopPropagation();
                return;
            }

            contactSubmitting = true;
            form.dataset.contactSubmitting = '1';

            const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.setAttribute('aria-disabled', 'true');
            }

            form.classList.add('was-validated');
        });
    });
});
