(function () {
    'use strict';
    if (window.fireballSubscriptionPhoneMask) return;
    window.fireballSubscriptionPhoneMask = true;
    function format(input) {
        const raw = input.value;
        if (!raw.trim() || !/^\+?[0-9() .-]+$/.test(raw) || (raw.startsWith('+') && !/^\+[78]/.test(raw))) return;
        let digits = raw.replace(/\D/g, '');
        const before = raw.slice(0, input.selectionStart ?? raw.length).replace(/\D/g, '').length;
        let added = 0;
        if (digits.length === 10 && !raw.startsWith('+') && !raw.includes('(')) { digits = '7' + digits; added = 1; }
        else if (digits[0] === '8') digits = '7' + digits.slice(1);
        else if (digits[0] !== '7') { digits = '7' + digits; added = 1; }
        if (digits.length > 11) return;
        const national = digits.slice(1);
        let value = '+7';
        if (national.length) value += ' (' + national.slice(0, 3);
        if (national.length >= 3) value += ')';
        if (national.length > 3) value += ' ' + national.slice(3, 6);
        if (national.length > 6) value += '-' + national.slice(6, 8);
        if (national.length > 8) value += '-' + national.slice(8, 10);
        input.value = value;
        let seen = 0, cursor = value.length;
        for (let index = 0; index < value.length; index++) {
            if (/\d/.test(value[index]) && ++seen >= before + added) { cursor = index + 1; break; }
        }
        if (document.activeElement === input) input.setSelectionRange(cursor, cursor);
    }
    document.addEventListener('input', function (event) {
        if (!event.isComposing && event.target.matches('[data-subscriptions-phone]')) format(event.target);
    });
    function start() { document.querySelectorAll('[data-subscriptions-phone]').forEach(input => format(input)); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once: true});
    else start();
})();
