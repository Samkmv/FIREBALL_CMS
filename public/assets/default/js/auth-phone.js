(function () {
    'use strict';
    function format(input) {
        const raw = input.value;
        if (!raw.trim()) return;
        let digits = raw.replace(/\D/g, '');
        if (!digits) return;
        const before = raw.slice(0, input.selectionStart ?? raw.length).replace(/\D/g, '').length;
        let added = 0;
        if (digits[0] === '8') digits = '7' + digits.slice(1);
        else if (digits[0] !== '7') { digits = '7' + digits; added = 1; }
        // Do not silently truncate invalid pasted input; let the server explain it.
        if (digits.length > 11) return;
        const number = digits.slice(1);
        let value = '+7';
        if (number.length) value += ' (' + number.slice(0, 3);
        if (number.length >= 3) value += ')';
        if (number.length > 3) value += ' ' + number.slice(3, 6);
        if (number.length > 6) value += '-' + number.slice(6, 8);
        if (number.length > 8) value += '-' + number.slice(8, 10);
        input.value = value;
        let count = 0, cursor = value.length;
        for (let index = 0; index < value.length; index++) {
            if (/\d/.test(value[index]) && ++count >= before + added) { cursor = index + 1; break; }
        }
        if (document.activeElement === input) input.setSelectionRange(cursor, cursor);
    }
    document.addEventListener('input', function (event) {
        if (event.target.matches('[data-auth-phone]')) format(event.target);
    });
    function start() { document.querySelectorAll('[data-auth-phone]').forEach(format); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
})();
