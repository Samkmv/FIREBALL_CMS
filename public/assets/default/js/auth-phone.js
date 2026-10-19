(function () {
    'use strict';
    const identifierMasks = new WeakMap();
    const boundFields = new WeakSet();
    function format(input, countryPrefix = false) {
        const raw = input.value;
        if (!raw.trim() || !/^\+?[0-9() .-]+$/.test(raw)) return;
        if (raw.startsWith('+') && !/^\+[78]/.test(raw)) return;
        let digits = raw.replace(/\D/g, '');
        if (!digits) return;
        const before = raw.slice(0, input.selectionStart ?? raw.length).replace(/\D/g, '').length;
        let added = 0;
        if (!countryPrefix && digits.length === 10 && !raw.startsWith('+') && !raw.includes('(')) { digits = '7' + digits; added = 1; }
        else if (digits[0] === '8') digits = '7' + digits.slice(1);
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
    function digitOffset(value, count) {
        if (count <= 0) return 0;
        for (let index = 0, seen = 0; index < value.length; index++) {
            if (/\d/.test(value[index]) && ++seen === count) return index + 1;
        }
        return value.length;
    }
    function originalIdentifier(raw, previous) {
        if (!previous || !raw.startsWith('+7')) return {value: raw, cursor: null};
        const old = previous.formatted;
        let start = 0, end = old.length, nextEnd = raw.length;
        while (start < old.length && start < raw.length && old[start] === raw[start]) start++;
        while (end > start && nextEnd > start && old[end - 1] === raw[nextEnd - 1]) { end--; nextEnd--; }
        const before = old.slice(0, start).replace(/\D/g, '').length - Number(previous.countryAdded);
        const after = old.slice(0, end).replace(/\D/g, '').length - Number(previous.countryAdded);
        const from = digitOffset(previous.original, before);
        const to = end === old.length ? previous.original.length : digitOffset(previous.original, after);
        const inserted = raw.slice(start, nextEnd);
        return {value: previous.original.slice(0, from) + inserted + previous.original.slice(to), cursor: from + inserted.length};
    }
    function formatIdentifier(input) {
        const raw = input.value;
        const previous = identifierMasks.get(input);
        const original = originalIdentifier(raw, previous);
        // Keep the unformatted identifier alongside the visual mask, so typing
        // a letter can immediately switch back to a username or email.
        if (input.value !== original.value) input.value = original.value;
        if (original.cursor !== null && document.activeElement === input) input.setSelectionRange(original.cursor, original.cursor);
        const digits = original.value.replace(/\D/g, '');
        if (!/^\+?[0-9() .-]+$/.test(original.value) || /^\+[^7]/.test(original.value)
            || (!/^[789]/.test(digits) && !/^[3489]\d{9}$/.test(digits))) {
            identifierMasks.delete(input);
            return;
        }
        const countryAdded = previous && raw.startsWith('+7') ? previous.countryAdded
            : !original.value.startsWith('+') && (digits.length === 10 || !/^[78]/.test(digits));
        format(input, !countryAdded);
        if (input.value === original.value) { identifierMasks.delete(input); return; }
        identifierMasks.set(input, {original: original.value, formatted: input.value, countryAdded});
        if (!boundFields.has(input) && input.form) {
            boundFields.add(input);
            input.form.addEventListener('formdata', function (event) {
                const mask = identifierMasks.get(input);
                // Preserve numeric usernames and the server's ambiguity checks.
                // Formatting is visual; authentication still sees what was typed.
                if (mask && input.value === mask.formatted) event.formData.set(input.name, mask.original);
            });
        }
    }
    document.addEventListener('input', function (event) {
        if (event.isComposing) return;
        if (event.target.matches('[data-auth-phone]')) format(event.target);
        else if (event.target.matches('[data-auth-identifier-phone]')) formatIdentifier(event.target);
    });
    function start() {
        document.querySelectorAll('[data-auth-phone]').forEach(input => format(input));
        document.querySelectorAll('[data-auth-identifier-phone]').forEach(formatIdentifier);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
})();
