// Real settings template + asset route + importer, with a mocked API; never uses the working CMS/DB.
const { chromium } = require('playwright');
const { execFileSync, spawnSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
const origin = 'http://127.0.0.1:8894';
let checks = 0;
function check(actual, expected, label) { assert.deepEqual(actual, expected, label); checks++; }

(async () => {
    const assetFixture = path.join(__dirname, 'fixtures/public-asset.php');
    for (const locale of ['', '/en', '/de', '/zh-cn']) {
        const served = execFileSync('php', [assetFixture, locale + '/plugins/subscriptions/assets/address-catalog-import.js']);
        check(served.equals(fs.readFileSync(path.join(root, 'plugins/subscriptions/assets/address-catalog-import.js'))), true, 'Public route serves complete importer: ' + (locale || 'default'));
    }
    for (const forbidden of ['plugin.php', 'private.js', '../plugin.php']) {
        check(spawnSync('php', [assetFixture, '/plugins/subscriptions/assets/' + forbidden]).status, 44, 'Asset allowlist rejects ' + forbidden);
    }
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
        const errors = [], unexpected = [], calls = [];
        let mode = 'normal', job, lost = false;
        page.on('pageerror', error => errors.push(error.message));
        await page.route('**/*', async route => {
            const request = route.request();
            const url = new URL(request.url());
            if (url.origin !== origin) { unexpected.push(url.href); return route.abort(); }
            if (url.pathname === '/admin/subscriptions/settings') {
                let html = execFileSync('php', [path.join(__dirname, 'fixtures/payment-admin.php'), 'settings', 'ru', 'dark'], { encoding: 'utf8' });
                html = html.replaceAll('name="csrf"', 'name="needCSRFToken"').replace('data-request-limit="262144"', 'data-request-limit="16384"');
                if (mode === 'wrong-origin') html = html.replace('data-batch-url="/admin/', 'data-batch-url="https://other.example.test/admin/');
                if (mode === 'missing-token') html = html.replaceAll('value="test-only"', 'value=""');
                html = html.replace('<body>', '<head><link rel="stylesheet" href="/assets/default/css/theme.min.css"></head><body>');
                return route.fulfill({ contentType: 'text/html', body: html });
            }
            if (url.pathname === '/admin/subscriptions/address-catalog/batch') {
                check(request.method(), 'POST', 'Only authenticated POST route used');
                check(request.headers()['x-csrf-token'], 'test-only', 'Every portion has CSRF header');
                check(Buffer.byteLength(request.postData()) <= 16384, true, 'Entire body fits small POST limit');
                const data = request.postDataJSON(); calls.push(data);
                if (mode === 'expired') return route.fulfill({ status: 419, json: { ok: false } });
                if (data.action === 'start') {
                    job = { id: 'f'.repeat(32), next: 0, processed: 0, skipped: 0, completed: false, replace: data.replace };
                } else if (data.action === 'batch') {
                    if (data.sequence === job.next) { job.next++; job.processed += data.rows.length; }
                    if (mode === 'lost' && !lost) { lost = true; return route.abort(); }
                    if (mode === 'slow') await new Promise(resolve => setTimeout(resolve, 100));
                } else if (data.action === 'finish') job.completed = true;
                else if (data.action === 'cancel') return route.fulfill({ json: { ok: true, cancelled: true } });
                return route.fulfill({ json: { ...job, ok: true } });
            }
            if (url.pathname.startsWith('/plugins/subscriptions/assets/')) {
                if (mode === 'missing-script') return route.fulfill({ status: 404, contentType: 'text/html', body: 'Not found' });
                const served = spawnSync('php', [assetFixture, url.pathname]);
                check(served.status, 0, 'Asset fetched through real plugin route, not direct filesystem');
                return route.fulfill({ body: served.stdout, contentType: url.pathname.endsWith('.js') ? 'application/javascript' : 'text/css' });
            }
            if (url.pathname.startsWith('/assets/default/')) {
                const filename = path.join(root, 'public', url.pathname);
                if (fs.existsSync(filename)) return route.fulfill({ body: fs.readFileSync(filename), contentType: filename.endsWith('.js') ? 'application/javascript' : 'text/css' });
            }
            unexpected.push(url.href); return route.abort();
        });
        const form = page.locator('[data-address-import]');
        async function load(nextMode = 'normal') {
            mode = nextMode; calls.length = 0; job = null; lost = false;
            await page.goto(origin + '/admin/subscriptions/settings');
            await form.locator('[type="file"]').waitFor();
        }
        async function upload(text, replace = false) {
            await form.locator('[type="file"]').setInputFiles({ name: 'addresses.csv', mimeType: 'text/csv', buffer: Buffer.from(text) });
            await form.locator('[name="replace_catalog"]').setChecked(replace);
            await form.locator('[type="submit"]').click();
        }
        const header = 'region,city,street,house,postal_code\n';
        const csv = header + Array.from({ length: 3400 }, (_, i) => `Ставропольский край,Железноводск,ул. Октябрьская,${i},357400\n`).join('');
        for (const failure of ['missing-script', 'wrong-origin', 'missing-token']) {
            await load(failure);
            check(await form.locator('[type="submit"]').isDisabled(), true, failure + ': upload stays disabled');
            check(await form.locator('[type="file"]').isDisabled(), true, failure + ': file selection stays disabled');
            check(await form.locator('[data-import-unavailable]').isVisible(), true, failure + ': initialization warning visible');
            check(await form.getAttribute('data-import-ready'), null, failure + ': failed initialization is not marked ready');
            check(calls.length, 0, failure + ': no upload requests');
            // Even forced native form submission cannot attach the file if the script is unavailable.
            await form.locator('[type="file"]').setInputFiles({ name: 'addresses.csv', mimeType: 'text/csv', buffer: Buffer.from(csv) });
            const fields = await form.evaluate(element => {
                for (const input of element.querySelectorAll('input')) input.disabled = false;
                return [...new FormData(element).entries()].map(([name, value]) => ({ name, file: value instanceof File }));
            });
            check(fields.some(field => field.file || field.name === 'address_catalog'), false, failure + ': native POST cannot include CSV');
        }
        const noJs = await browser.newContext({ javaScriptEnabled: false });
        try {
            const noJsPage = await noJs.newPage();
            await noJsPage.setContent(execFileSync('php', [path.join(__dirname, 'fixtures/payment-admin.php'), 'settings', 'ru', 'dark'], { encoding: 'utf8' }).replace(/<script\b[^>]*>[\s\S]*?<\/script>/g, ''));
            check(await noJsPage.locator('[data-address-import] [type="submit"]').isDisabled(), true, 'No JavaScript: upload disabled in server markup');
            check(await noJsPage.locator('[data-import-unavailable]').isVisible(), true, 'No JavaScript: helpful warning visible');
        } finally { await noJs.close(); }
        await load(); await upload(csv);
        check(await form.getAttribute('data-import-ready'), '1', 'Ready only after initialization succeeds');
        check(await form.locator('[data-import-unavailable]').isHidden(), true, 'Ready loader hides initialization warning');
        await page.waitForFunction(() => document.querySelector('[data-import-status]').textContent.includes('Импорт завершён'));
        check(job.processed, 3400, 'All source rows imported despite small POST limit');
        check(job.completed, true, 'Final publication requested');
        check(await form.locator('[type="submit"]').isEnabled(), true, 'Controls restored after completion');
        check(calls.filter(call => call.action === 'batch').length > 1, true, 'Large file split into portions');

        await load('lost'); await upload(csv, true);
        await form.locator('[data-import-resume]').waitFor({ state: 'visible' });
        await form.locator('[data-import-resume]').click();
        await page.waitForFunction(() => document.querySelector('[data-import-status]').textContent.includes('Импорт завершён'));
        check(job.processed, 3400, 'Retry after lost response does not duplicate source rows');
        check(calls.filter(call => call.action === 'batch' && call.sequence === 0).length, 2, 'Exactly the unacknowledged portion is retried');

        await load('slow'); await upload(csv, true);
        await form.locator('[data-import-pause]').click();
        await form.locator('[data-import-resume]').waitFor({ state: 'visible' });
        check(calls.some(call => call.action === 'finish'), false, 'Pause never publishes replacement');
        await form.locator('[data-import-resume]').click();
        check(await form.locator('[data-import-pause]').isEnabled(), true, 'Pause re-enabled on resume');
        await form.locator('[data-import-pause]').click();
        await form.locator('[data-import-cancel]').waitFor({ state: 'visible' });
        await form.locator('[data-import-cancel]').click();
        await page.waitForFunction(() => document.querySelector('[data-import-status]').textContent.includes('Импорт отменён'));
        check(calls.some(call => call.action === 'finish'), false, 'Cancel never publishes replacement');

        await load(); await upload(csv + 'Край,"Незакрытые кавычки', true);
        await form.locator('[data-import-error]').waitFor({ state: 'visible' });
        check(await form.locator('[data-import-resume]').isHidden(), true, 'Corrupt file cannot resume into premature publication');
        check(calls.some(call => call.action === 'finish'), false, 'Corrupt file does not publish partial replacement');
        await form.locator('[data-import-cancel]').click();
        await page.waitForFunction(() => !document.querySelector('[data-address-import] [type="file"]').disabled);

        await load('expired'); await upload(header + 'Край,Город,Улица,1,123456\n');
        await form.locator('[data-import-error]').waitFor({ state: 'visible' });
        check((await form.locator('[data-import-error]').innerText()).includes('Сессия истекла'), true, 'Actual CSRF expiration has useful error');
        check(errors, [], 'No unhandled browser errors');
        check(unexpected, [], 'No live or third-party requests');
    } finally { await browser.close(); }
    console.log(`Address import browser tests passed: ${checks} checks.`);
})().catch(error => { console.error(error); process.exitCode = 1; });
