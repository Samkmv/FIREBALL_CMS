document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-cookie-consent-settings]');
    if (!form) {
        return;
    }

    const enabled = form.querySelector('[data-cookie-preview-enabled]');
    const message = form.querySelector('[data-cookie-preview-message]');
    const buttonText = form.querySelector('[data-cookie-preview-button]');
    const policy = form.querySelector('[data-cookie-preview-policy]');
    const registrationPolicy = form.querySelector('[data-cookie-policy-registration]');
    const stage = form.querySelector('[data-cookie-preview-stage]');
    const banner = form.querySelector('[data-cookie-preview-banner]');
    const status = form.querySelector('[data-cookie-preview-status]');
    const messageOutput = form.querySelector('[data-cookie-preview-message-output]');
    const buttonOutput = form.querySelector('[data-cookie-preview-button-output]');
    const policyOutput = form.querySelector('[data-cookie-preview-policy-output]');

    const checkedValue = function (selector, fallback) {
        const input = form.querySelector(selector + ':checked');
        return input ? input.value : fallback;
    };

    const update = function () {
        const style = checkedValue('[data-cookie-preview-style]', 'card');
        const position = checkedValue('[data-cookie-preview-position]', 'bottom_right');
        const isEnabled = Boolean(enabled && enabled.checked);
        const selectedPolicy = policy && policy.options[policy.selectedIndex];
        const policyUrl = selectedPolicy ? selectedPolicy.getAttribute('data-url') || '' : '';

        if (registrationPolicy) {
            registrationPolicy.disabled = policyUrl === '';
            if (policyUrl === '') {
                registrationPolicy.checked = false;
            }
        }

        if (messageOutput) {
            messageOutput.textContent = message && message.value.trim()
                ? message.value.trim()
                : message.getAttribute('placeholder') || '';
        }
        if (buttonOutput) {
            buttonOutput.textContent = buttonText && buttonText.value.trim()
                ? buttonText.value.trim()
                : '...';
        }
        if (policyOutput) {
            policyOutput.href = policyUrl || '#';
            policyOutput.classList.toggle('d-none', policyUrl === '');
        }
        if (banner) {
            banner.className = 'cookie-preview-banner cookie-preview-banner--' + style
                + ' cookie-preview-banner--' + position.replace(/_/g, '-');
        }
        if (stage) {
            stage.classList.toggle('is-disabled', !isEnabled);
        }
        if (status && stage) {
            status.textContent = isEnabled
                ? stage.getAttribute('data-enabled-label')
                : stage.getAttribute('data-disabled-label');
            status.classList.toggle('text-bg-success', isEnabled);
            status.classList.toggle('text-bg-secondary', !isEnabled);
        }
    };

    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();
});
