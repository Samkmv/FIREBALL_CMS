(function () {
    document.querySelectorAll('[data-bs-toggle="modal"], [data-bs-toggle="offcanvas"]').forEach(function (trigger) {
        const selector = trigger.getAttribute('data-bs-target') || trigger.getAttribute('href') || '';
        if (!selector || selector.charAt(0) !== '#') {
            return;
        }

        try {
            if (document.querySelector(selector)) {
                return;
            }
        } catch (error) {
            // Invalid selectors are disabled below.
        }

        trigger.removeAttribute('data-bs-toggle');
        trigger.removeAttribute('data-bs-target');
        trigger.removeAttribute('aria-controls');
    });
})();
