// All pages and API responses are intercepted; no live CMS or 3x-ui is contacted.
const { chromium } = require('playwright');
const { execFileSync, spawnSync } = require('node:child_process');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
const php = process.env.VPN_TEST_PHP || '/Applications/MAMP/bin/php/php8.2.0/bin/php';
(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage({ locale: 'ru-RU' });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        let mode = 'success', reads = 0;
        const result = { subscription_id: 14, connection_id: 7, checked_at: '2026-10-07 12:34:56', fields: [
            { label: 'Трафик в панели', value: '8 ГБ' },
            { label: 'Клиент', value: '<img src=x onerror="window.leaked=true">' },
            { label: 'Длинное значение', value: 'fixture'.repeat(60) },
        ] };
        await page.route('**/*', async route => {
            const url = new URL(route.request().url());
            if (url.origin !== 'https://vpn.test') throw new Error('Unexpected external request');
            if (url.pathname.startsWith('/assets/')) return route.fulfill({ path: root + '/public' + url.pathname });
            if (url.pathname === '/plugins/vpn-manager-v2/assets/vpn-manager-v2.js') return route.fulfill({ path: root + url.pathname });
            if (url.pathname.endsWith('/client-info')) {
                reads++;
                assert.equal(route.request().method(), 'GET');
                if (mode === 'offline') return route.fulfill({ status: 502, json: { error: 'secret-panel-password' } });
                if (mode === 'login') return route.fulfill({ status: 403, contentType: 'text/html', body: '<h1>Login</h1>' });
                if (mode === 'malformed') return route.fulfill({ json: { checked_at: 'now', fields: [{ label: 'Partial', value: 'one' }, { label: 12, value: 'invalid' }] } });
                return route.fulfill({ json: result });
            }
            const args = [path.join(__dirname, 'client_information_render.php')];
            if (url.searchParams.has('unknown')) args.push('--unknown');
            const html = execFileSync(php, args, { encoding: 'utf8' });
            assert(!html.includes('Warning:') && !html.includes('Fatal error:') && !html.includes('vpn_manager_v2_'), 'Actual PHP view must render cleanly and translate every key');
            return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
        });
        for (const width of [320, 390, 768, 1280, 1440]) for (const theme of ['dark', 'light']) {
            await page.setViewportSize({ width, height: 1100 });
            const before = reads;
            await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2/subscriptions/14');
            await page.evaluate(theme => document.documentElement.dataset.bsTheme = theme, theme);
            assert.equal(reads, before, 'Opening subscription must not contact panels automatically');
            const card = page.locator('[data-vpn-v2-client-card]');
            await card.locator('summary').click();
            const live = card.locator('[data-vpn-v2-client-live]');
            assert.equal(await live.evaluate(element => getComputedStyle(element).display), 'none', 'Live fields start hidden');
            const button = card.locator('[data-vpn-v2-client-inspect]');
            const original = await button.innerText();
            await button.click();
            await page.waitForFunction(() => document.querySelector('[data-vpn-v2-client-result]').textContent === 'Данные панели (3x-ui) · 2026-10-07 12:34:56');
            assert.equal(await live.locator('dd').count(), 3);
            assert.equal(await live.locator('img').count(), 0, 'Panel text must never become HTML');
            assert.equal(await page.evaluate(() => window.leaked), undefined, 'No panel-text XSS');
            assert.equal(await button.innerText(), original, 'Button restored after success');
            assert.equal(await button.isDisabled(), false);
            assert(!await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), 'No page-level horizontal overflow');
            assert(await button.evaluate(element => element.getBoundingClientRect().right <= element.closest('[data-vpn-v2-client-card]').getBoundingClientRect().right), 'Button fits mobile card');
            if (width === 1440) {
                const boxes = await page.locator('[data-vpn-v2-client-information] > .row > div').evaluateAll(elements => elements.map(element => element.getBoundingClientRect().toJSON()));
                assert.equal(boxes.length, 4);
                assert(boxes.every(box => Math.abs(box.y - boxes[0].y) < 1 && Math.abs(box.width - boxes[0].width) < 1), 'Desktop summary cards equal width in one row');
            }
            if (width === 390 || width === 1440) {
                await page.locator('[data-vpn-v2-client-information]').screenshot({ path: `/private/tmp/vpn-client-information-${theme}-${width}.png` });
            }
            for (mode of ['offline', 'login', 'malformed']) {
                await button.click();
                await page.waitForFunction(() => document.querySelector('[data-vpn-v2-client-result]').classList.contains('text-danger'));
                assert.equal(await live.locator('dd').count(), 0, 'Old or partially parsed live data cleared after failure');
                assert.equal(await live.evaluate(element => getComputedStyle(element).display), 'none');
                assert(!await card.innerText().then(text => text.includes('secret-panel-password')), 'Safe error without panel credentials');
                assert.equal(await button.isDisabled(), false, 'Retry remains possible');
            }
            mode = 'success';
        }
        await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2/subscriptions/14?unknown=1');
        const section = page.locator('[data-vpn-v2-client-information]');
        assert.equal(await section.locator('[role=progressbar]').count(), 0, 'Unverified zero has no factual progress bar');
        assert((await section.innerText()).includes('Трафик ещё не проверялся'));
        await section.locator('summary').click();
        assert(!(await section.innerText()).includes('failed'), 'Traffic error localized');
        const foreign = spawnSync(php, [path.join(__dirname, 'client_information_unit.php'), '--foreign-endpoint'], { encoding: 'utf8' });
        assert.equal(foreign.status, 0);
        assert(foreign.stderr.includes('HTTP_STATUS=404'));
        assert.equal(JSON.parse(foreign.stdout).error, 'Подключение VPN V2 не найдено.');
        assert.deepEqual(errors, []);
        console.log('PASS client info browser: 320–1440px, dark/light, equal cards, live inspection, offline/login/malformed recovery, XSS and actual 404 controller');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
