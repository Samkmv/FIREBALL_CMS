// Real templates/script with intercepted HTTP: no CMS DB, VPN panels, or actual queue execution.
const assert = require('node:assert/strict');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');
const root = path.resolve(__dirname, '../../..');
const php = process.env.VPN_TEST_PHP || '/Applications/MAMP/bin/php/php8.2.0/bin/php';
(async () => {
    const browser = await chromium.launch({headless: true});
    try {
        const page = await browser.newPage();
        const errors = [], writes = [], reads = [];
        page.on('pageerror', error => errors.push(error.message));
        let mode = 'success', block = null, release, refreshes = 0, progress = 0, brokenRefresh = false, empty = false;
        await page.route('**/*', async route => {
            const request = route.request(), url = new URL(request.url());
            assert.equal(url.origin, 'https://vpn.test', 'No external HTTP');
            if (url.pathname.startsWith('/assets/')) return route.fulfill({path: root + '/public' + url.pathname});
            if (url.pathname.endsWith('/vpn-manager-v2.js')) return route.fulfill({path: root + url.pathname});
            if (request.method() === 'POST') {
                writes.push({path: url.pathname, body: request.postData()});
                if (block) await block;
                if (mode === 'network') return route.abort('failed');
                if (mode === 'login') return route.fulfill({status: 403, contentType: 'text/html', body: '<h1>Login</h1>'});
                if (mode === 'denied') return route.fulfill({status: 403, json: {error: 'Недостаточно прав.'}});
                const status = mode === 'pending' ? 'running' : mode === 'retry' ? 'retry'
                    : mode === 'partial' ? 'completed_partial' : mode === 'cancelled' ? 'cancelled' : 'completed';
                return route.fulfill({json: {status, status_label: status, message: 'Результат ' + mode,
                    total_count: 3, processed_count: 3,
                    last_error: mode === 'retry' ? '<img src=x onerror="window.leaked=true">' : '',
                    progress_url: '/admin/plugins/vpn-manager-v2/operations/00000000-0000-4000-8000-000000000001'}});
            }
            if (url.pathname.endsWith('00000000-0000-4000-8000-000000000001')) {
                progress++;
                return route.fulfill({json: {status: progress === 1 ? 'running' : 'completed', status_label: 'Выполнена',
                    total_count: 3, processed_count: progress === 1 ? 1 : 3}});
            }
            assert.equal(url.pathname, '/admin/plugins/vpn-manager-v2/operations');
            reads.push(url.search);
            if (request.headers().accept === 'text/html') {
                refreshes++;
                if (brokenRefresh) return route.fulfill({contentType: 'text/html', body: '<h1>Login</h1>'});
            }
            const args = ['--render', '--page=' + (url.searchParams.get('page') || 1), '--pending'];
            if (empty) args.push('--empty');
            let html = execFileSync(php, [path.join(__dirname, 'metrics_operations_unit.php'), ...args], {encoding: 'utf8'});
            assert(!/Warning:|Fatal error:|vpn_manager_v2_/.test(html));
            html = html.replace('data-vpn-v2-operations-table>', `data-vpn-v2-operations-table data-refresh="${refreshes}">`)
                .replace('</body>', '<script>window.pageIdentity = Math.random()</script><script src="/assets/default/bootstrap/js/bootstrap.bundle.min.js"></script><script src="/plugins/vpn-manager-v2/assets/vpn-manager-v2.js"></script></body>');
            return route.fulfill({contentType: 'text/html', body: html});
        });
        const form = suffix => page.locator(`form[action$="${suffix}"]`).first();
        const status = () => page.locator('[data-vpn-v2-operation-alert]');
        const done = () => page.waitForFunction(() => !document.querySelector('[data-vpn-v2-operation-alert]').hasAttribute('aria-busy'));
        for (const width of [320, 390, 1440]) for (const theme of ['light', 'dark']) {
            mode = 'success'; brokenRefresh = false; empty = false;
            await page.setViewportSize({width, height: 1000});
            await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2/operations?page=2');
            await page.evaluate(theme => { document.documentElement.dataset.bsTheme = theme; document.querySelector('details').open = true; }, theme);
            const identity = await page.evaluate(() => window.pageIdentity);
            const before = writes.length;
            block = new Promise(resolve => {release = resolve;});
            await form('/sync/full').locator('button').click();
            await status().locator('.spinner-border').waitFor();
            assert.equal(await form('/sync/full').locator('button').isDisabled(), true);
            assert.equal(await form('/operations/retry').locator('button').isDisabled(), true);
            assert.equal(await form('/operations/clear').locator('button').isDisabled(), true);
            await form('/sync/full').evaluate(element => element.dispatchEvent(new Event('submit', {bubbles: true, cancelable: true})));
            assert.equal(writes.length, before + 1, 'Duplicate submit blocked');
            await page.locator('details').screenshot({path: `/private/tmp/vpn-operation-loading-${theme}-${width}.png`});
            release(); block = null;
            await done();
            assert((await status().innerText()).includes('Результат success'));
            assert.equal(await status().locator('.alert-success').count(), 1);
            assert.equal(await page.evaluate(() => window.pageIdentity), identity, 'Whole page was not reloaded');
            assert.equal(await page.locator('details').getAttribute('open'), '');
            assert.equal(new URL(page.url()).searchParams.get('page'), '2');
            assert.equal(await page.locator('.admin-pagination-nav [aria-current=page]').innerText(), '2');
            assert.equal(await form('/sync/full').locator('button').isDisabled(), false);
            assert((await form('/sync/full').innerText()).includes('Проверить все панели'));
            assert(!await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1));
            assert.match(writes.at(-1).body, /name="needCSRFToken"\r\n\r\nfixture\r\n/, 'Multipart CSRF retained before disabling controls');
        }
        for (mode of ['partial', 'cancelled', 'retry', 'denied', 'login', 'network']) {
            const before = writes.length, pollsBefore = progress;
            await form('/operations/process').locator('button').click();
            await done();
            assert.equal(writes.length, before + 1, 'Never replay POST automatically');
            assert.equal(progress, pollsBefore, 'Completed/partial/retry/error does not poll forever');
            assert.equal(await status().locator(['partial', 'cancelled', 'retry'].includes(mode) ? '.alert-warning' : '.alert-danger').count(), 1);
            assert.equal(await status().locator('img').count(), 0);
            assert.equal(await page.evaluate(() => window.leaked), undefined);
            assert.equal(await form('/operations/process').locator('button').isDisabled(), false);
        }
        mode = 'pending'; progress = 0;
        await form('/sync/full').locator('button').click();
        await page.waitForFunction(() => document.querySelector('[data-vpn-v2-operation-alert]').textContent.includes('1 / 3'));
        assert.equal(await form('/sync/full').locator('button').isDisabled(), true, 'Locked during status polling');
        await done();
        assert.equal(progress, 2);
        assert((await status().innerText()).includes('3 / 3'));
        mode = 'success'; brokenRefresh = true;
        const old = await page.locator('[data-vpn-v2-operations-table]').getAttribute('data-refresh');
        await form('/operations/retry').locator('button').click();
        await done();
        assert.equal(await status().locator('.alert-success').count(), 1, 'Actual outcome remains visible');
        assert.equal(await status().locator('.alert-warning').count(), 1, 'Refresh failure has separate notice');
        assert.equal(await page.locator('[data-vpn-v2-operations-table]').getAttribute('data-refresh'), old);
        brokenRefresh = false;
        // Refreshed cancellation forms work through the delegated listener.
        await page.locator('[data-vpn-v2-operations-table] [data-bs-toggle=dropdown]').first().click();
        await page.locator('[data-vpn-v2-operations-table] form[data-vpn-v2-async-operation]').first().locator('button').click();
        await done();
        assert(writes.at(-1).path.endsWith('/cancel'));
        empty = true; await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2/operations');
        await page.locator('details').evaluate(element => element.open = true);
        assert.equal(await form('/operations/clear').locator('button').isDisabled(), true);
        await form('/operations/process').locator('button').click(); await done();
        assert.equal(await form('/operations/clear').locator('button').isDisabled(), true, 'Initially disabled state preserved');
        assert(refreshes >= 16);
        assert.deepEqual(errors, []);
        console.log('PASS operation feedback: busy/spinner, duplicate guard, actual outcomes, pending progress, CSRF, error recovery, XSS, native table refresh/pagination and no page reload at 320/390/1440 light/dark');
    } finally { await browser.close(); }
})().catch(error => {console.error(error); process.exitCode = 1;});
