(function () {
    'use strict';

    function parseRecord(record, delimiter) {
        const fields = [];
        let value = '', quoted = false, closed = false;
        for (let i = 0; i < record.length; i++) {
            const char = record[i];
            if (quoted) {
                if (char === '"' && record[i + 1] === '"') { value += '"'; i++; }
                else if (char === '"') { quoted = false; closed = true; }
                else value += char;
            } else if (char === delimiter) {
                fields.push(value); value = ''; closed = false;
            } else if (char === '"' && value === '' && !closed) {
                quoted = true;
            } else {
                if (closed || char === '"') throw new Error('Некорректные кавычки в CSV.');
                value += char;
            }
        }
        if (quoted) throw new Error('В CSV не закрыты кавычки.');
        fields.push(value);
        if (fields.length > 100) throw new Error('В CSV слишком много колонок.');
        return fields;
    }

    async function* records(file) {
        const decoder = new TextDecoder('utf-8', { fatal: true });
        let pending = '', scan = 0, quoted = false;
        for (let offset = 0; offset < file.size; offset += 65536) {
            pending += decoder.decode(await file.slice(offset, offset + 65536).arrayBuffer(), { stream: true });
            let start = 0;
            for (; scan < pending.length; scan++) {
                if (pending[scan] === '"') quoted = !quoted;
                if (pending[scan] === '\n' && !quoted) {
                    yield pending.slice(start, scan).replace(/\r$/, '');
                    start = scan + 1;
                }
            }
            pending = pending.slice(start);
            scan -= start;
            if (pending.length > 2 * 1024 * 1024) throw new Error('Слишком длинная строка CSV. Проверьте разделители и кавычки.');
        }
        pending += decoder.decode();
        if (quoted) throw new Error('В CSV не закрыты кавычки.');
        if (pending !== '') yield pending.replace(/\r$/, '');
    }

    async function* batches(file, limit) {
        const encoder = new TextEncoder();
        let header, delimiter, rows = [], bytes = 0, position = 0;
        for await (const record of records(file)) {
            position += encoder.encode(record).length + 1;
            if (!record.trim()) continue;
            if (!header) {
                delimiter = [',', ';', '\t'].sort((a, b) => record.split(b).length - record.split(a).length)[0];
                header = parseRecord(record.replace(/^\uFEFF/, ''), delimiter);
                continue;
            }
            const row = parseRecord(record, delimiter);
            const rowBytes = encoder.encode(JSON.stringify(row)).length + 1;
            if (rowBytes > limit - 512) throw new Error('Одна строка CSV превышает лимит порции.');
            if (rows.length && (bytes + rowBytes > limit - 512 || rows.length >= 1500)) {
                yield { header, rows, position: position - encoder.encode(record).length - 1 };
                rows = []; bytes = 0;
            }
            rows.push(row); bytes += rowBytes;
        }
        if (!header) throw new Error('CSV-файл пуст.');
        if (rows.length) yield { header, rows, position: file.size };
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { parseRecord, records, batches };
    if (typeof document === 'undefined') return;

    function init() {
        document.querySelectorAll('form[data-address-import]').forEach(function (form) {
            if (form.dataset.importReady) return;
            // Fail closed even if initialization fails after this point.
            form.addEventListener('submit', function (event) { event.preventDefault(); });
            const input = form.querySelector('[type="file"]');
            const replace = form.querySelector('[name="replace_catalog"]');
            const submit = form.querySelector('[type="submit"]');
            const progress = form.querySelector('[data-import-progress]');
            const bar = progress.querySelector('.progress-bar');
            const status = form.querySelector('[data-import-status]');
            const error = form.querySelector('[data-import-error]');
            const resume = form.querySelector('[data-import-resume]');
            const pause = form.querySelector('[data-import-pause]');
            const cancel = form.querySelector('[data-import-cancel]');
            const unavailable = form.querySelector('[data-import-unavailable]');
            let file, iterator, pending, job, running = false, paused = false, sequence = 0, exhausted = false, invalidFile = false;
            const limit = Number(form.dataset.requestLimit);
            let endpoint;
            try {
                endpoint = new URL(form.dataset.batchUrl, window.location.href);
                if (endpoint.origin !== window.location.origin) throw new Error('Адрес импорта отличается от адреса сайта. Проверьте основной URL сайта в настройках CMS.');
                if (!Number.isFinite(limit) || limit < 1024 || limit > 262144) throw new Error('Не удалось определить размер порции. Обновите страницу настроек.');
                if (!form.querySelector('[name="needCSRFToken"]')?.value) throw new Error('Сессия истекла. Обновите страницу и войдите заново.');
                if (!window.fetch || !window.TextDecoder || !window.TextEncoder || !window.Blob?.prototype.arrayBuffer) throw new Error('Для импорта CSV обновите браузер до актуальной версии.');
            } catch (exception) {
                unavailable.querySelector('.alert').textContent = exception.message;
                return;
            }

            async function send(data) {
                const token = form.querySelector('[name="needCSRFToken"]').value;
                const response = await fetch(endpoint, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token, 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                    body: JSON.stringify(data)
                });
                if (response.status === 419 || response.redirected) throw new Error('Сессия истекла. Обновите страницу и войдите заново.');
                if (response.status === 413) throw new Error('Сервер отклонил размер порции. Обновите страницу настроек.');
                const result = await response.json().catch(() => { throw new Error('Сервер не вернул результат импорта. Проверьте подключение и повторите попытку.'); });
                if (!response.ok || !result.ok) throw new Error(result.message || 'Не удалось обработать порцию CSV.');
                return result;
            }
            function lock(value) {
                input.disabled = replace.disabled = submit.disabled = value;
            }
            async function run() {
                if (running || invalidFile) return;
                running = true; paused = false; lock(true);
                error.hidden = resume.hidden = cancel.hidden = true;
                pause.hidden = false; pause.disabled = false; progress.hidden = false;
                try {
                    while (!paused) {
                        if (!pending && !exhausted) {
                            let next;
                            try { next = await iterator.next(); }
                            catch (exception) { invalidFile = true; throw exception; }
                            exhausted = next.done;
                            pending = next.value;
                        }
                        if (!job && pending) job = await send({ action: 'start', header: pending.header, replace: replace.checked });
                        if (!pending) {
                            if (!job) throw new Error('В файле нет строк адресов.');
                            job = await send({ action: 'finish', id: job.id, sequence });
                            bar.style.width = '100%'; bar.parentElement.setAttribute('aria-valuenow', '100');
                            status.textContent = 'Импорт завершён. Обработано строк: ' + job.processed.toLocaleString() + ', пропущено: ' + job.skipped.toLocaleString() + '. Обновите страницу, чтобы увидеть статистику справочника.';
                            lock(false); pause.hidden = true; job = null;
                            return;
                        }
                        status.textContent = 'Импорт адресов… Обработано строк: ' + job.processed.toLocaleString();
                        job = await send({ action: 'batch', id: job.id, sequence, rows: pending.rows });
                        sequence = job.next;
                        const percent = Math.min(99, Math.floor(pending.position / file.size * 100));
                        bar.style.width = percent + '%'; bar.parentElement.setAttribute('aria-valuenow', String(percent));
                        status.textContent = 'Импорт: ' + percent + '%. Обработано строк: ' + job.processed.toLocaleString() + ', пропущено: ' + job.skipped.toLocaleString();
                        pending = null;
                    }
                    status.textContent += '. Приостановлено.';
                } catch (exception) {
                    error.textContent = exception.message; error.hidden = false;
                } finally {
                    running = false; pause.hidden = true;
                    if (input.disabled) { resume.hidden = invalidFile; cancel.hidden = false; }
                }
            }
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                file = input.files[0];
                if (!file || running) return;
                iterator = batches(file, limit); pending = job = null; sequence = 0; exhausted = invalidFile = false;
                bar.style.width = '0%'; bar.parentElement.setAttribute('aria-valuenow', '0');
                status.textContent = 'Читаем заголовок CSV…';
                run();
            });
            resume.addEventListener('click', run);
            pause.addEventListener('click', function () { paused = true; pause.disabled = true; });
            cancel.addEventListener('click', async function () {
                try {
                    if (job) await send({ action: 'cancel', id: job.id });
                    status.textContent = replace.checked ? 'Импорт отменён. Старый справочник сохранён.' : 'Импорт остановлен. Уже добавленные порции сохранены; повторная загрузка не создаст дубликатов.';
                    job = pending = null; iterator = null; lock(false); resume.hidden = cancel.hidden = true; error.hidden = true;
                } catch (exception) { error.textContent = exception.message; error.hidden = false; }
            });
            window.addEventListener('beforeunload', function (event) { if (iterator && input.disabled) { event.preventDefault(); event.returnValue = ''; } });
            form.dataset.importReady = '1';
            unavailable.hidden = true;
            lock(false);
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, { once: true });
    else init();
})();
