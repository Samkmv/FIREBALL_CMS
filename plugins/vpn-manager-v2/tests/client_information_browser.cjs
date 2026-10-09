// All pages and API responses are intercepted; no live CMS or 3x-ui is contacted.
const { chromium } = require('playwright');
const { execFileSync, spawnSync } = require('node:child_process');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
const php = process.env.VPN_TEST_PHP || '/Applications/MAMP/bin/php/php8.2.0/bin/php';
(async () => {
    const browser = await chromium.launch({ headless: true, ...(process.env.VPN_TEST_CHROMIUM
        ? {executablePath: process.env.VPN_TEST_CHROMIUM} : {}) });
    try {
        const page = await browser.newPage({ locale: 'ru-RU' });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        let mode = 'success', reads = 0, concurrent = 0, maxConcurrent = 0;
        const result = { subscription_id: 14, connection_id: 7, server_id: 1,
            traffic: {upload: 1024 ** 3, download: 3 * 1024 ** 3, total: 4 * 1024 ** 3},
            checked_at: '2026-10-07 12:34:56', fields: [
            { label: 'Трафик в панели', value: '8 ГБ' },
            { label: 'Клиент', value: '<img src=x onerror="window.leaked=true">' },
            { label: 'Длинное значение', value: 'fixture'.repeat(60) },
        ] };
        await page.route('**/*', async route => {
            const url = new URL(route.request().url());
            if (url.origin !== 'https://vpn.test') throw new Error('Unexpected external request');
            assert.equal(route.request().method(), 'GET', 'Traffic inspection never performs writes');
            if (url.pathname.startsWith('/assets/')) return route.fulfill({ path: root + '/public' + url.pathname });
            if (url.pathname === '/plugins/vpn-manager-v2/assets/vpn-manager-v2.js') return route.fulfill({ path: root + url.pathname });
            if (url.pathname.endsWith('/client-info')) {
                reads++;
                const id = Number(url.pathname.split('/').at(-2));
                concurrent++; maxConcurrent = Math.max(maxConcurrent, concurrent);
                await new Promise(resolve => setTimeout(resolve, 75));
                concurrent--;
                if (mode === 'partial' && id === 8) return route.fulfill({status: 502, json: {error: 'offline'}});
                if (mode === 'offline') return route.fulfill({ status: 502, json: { error: 'secret-panel-password' } });
                if (mode === 'login') return route.fulfill({ status: 403, contentType: 'text/html', body: '<h1>Login</h1>' });
                if (mode === 'malformed') return route.fulfill({ json: { checked_at: 'now', fields: [{ label: 'Partial', value: 'one' }, { label: 12, value: 'invalid' }] } });
                return route.fulfill({ json: {...result, connection_id: mode === 'wrong-owner' ? 999 : id,
                    server_id: mode === 'three-servers' ? id - 6 : id === 9 ? 2 : 1,
                    traffic: mode === 'zero' ? {upload: 0, download: 0, total: 0}
                        : mode === 'over-limit' ? {upload: 1024 ** 3, download: 19 * 1024 ** 3, total: 20 * 1024 ** 3}
                        : {...result.traffic, download: (id === 8 ? 1 : id === 9 ? 4 : 3) * 1024 ** 3,
                        total: (id === 8 ? 2 : id === 9 ? 5 : 4) * 1024 ** 3}} });
            }
            const args = [path.join(__dirname, 'client_information_render.php')];
            if (url.searchParams.has('unknown')) args.push('--unknown');
            if (url.searchParams.has('multi')) args.push('--multi');
            if (url.searchParams.has('unlimited')) args.push('--unlimited');
            if (url.searchParams.has('stale-total')) args.push('--stale-total');
            if (url.searchParams.has('large-limit')) args.push('--large-limit');
            if (url.searchParams.has('three-servers')) args.push('--three-servers');
            if (url.searchParams.has('all-unknown')) args.push('--all-unknown');
            if (url.searchParams.has('pending-delete')) args.push('--pending-delete');
            if (url.searchParams.has('deleted')) args.push('--deleted');
            if (url.searchParams.has('connection')) args.push('--connection');
            const html = execFileSync(php, args, { encoding: 'utf8' });
            assert(!html.includes('Warning:') && !html.includes('Fatal error:') && !html.includes('vpn_manager_v2_'), 'Actual PHP view must render cleanly and translate every key');
            return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
        });
        for (const width of [320, 390, 1440]) for (const state of ['pending-delete', 'deleted']) {
            await page.setViewportSize({width, height: 1100});
            await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2/subscriptions/14?' + state);
            assert.equal(await page.locator('a[href*="/subscriptions/edit/"], a[href$="/edit"], a[href$="/devices"]').count(), 0,
                'Removed subscriptions still expose editing or device changes');
            assert.equal(await page.locator('form[action*="/sync/subscription/"], form[action$="/renew"], form[action$="/suspend"]').count(), 0,
                'Removed subscriptions still expose access-changing actions');
            assert.equal(await page.getByText('Создать отсутствующее подключение', {exact: true}).count(), 0,
                'Removed clients are suggested for re-creation');
            assert.equal(await page.getByText('Доступ к подписке', {exact: true}).count(), 0,
                'Revoked subscription still promises a connection URL after provisioning');
            const retry = page.locator('form[action$="/delete"]');
            assert.equal(await retry.count(), state === 'pending-delete' ? 1 : 0,
                'Deletion retry visibility is wrong');
            if (state === 'pending-delete') assert.match(await retry.innerText(), /Повторить удаление/);
            else assert.match(await page.locator('.alert-success').innerText(), /Подписка удалена/);
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1),
                'Deletion details overflow on mobile');
            if (width === 390) await page.screenshot({path: `/private/tmp/vpn-subscription-${state}-390.png`, fullPage: true});
            await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2/subscriptions/14?connection&' + state);
            assert.equal(await page.locator('a[href$="/edit"], form').count(), 0,
                'Deleted connection details still expose mutations');
            assert.match(await page.locator('dl').innerText(), /Лимит устройств: 2 · Лимит IP: 1/,
                'Connection details confuse physical device and IP limits');
        }
        if (process.argv.includes('--deletion-only')) {
            assert.deepEqual(errors, []);
            console.log('PASS deletion details: 320/390/1440px, subscription and connection guards, retry visibility, revoked URL hidden, separate device/IP limits');
            return;
        }
        for (const width of [320, 390, 768, 1280, 1440]) for (const theme of ['dark', 'light']) {
            await page.setViewportSize({ width, height: 1100 });
            const before = reads;
            await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2/subscriptions/14');
            await page.evaluate(theme => document.documentElement.dataset.bsTheme = theme, theme);
            assert.equal(reads, before, 'Opening subscription must not contact panels automatically');
            const card = page.locator('[data-vpn-v2-client-card]');
            const summary = page.locator('[data-vpn-v2-client-information]');
            const total = summary.locator('[data-vpn-v2-usage-value=used]');
            const remaining = summary.locator('[data-vpn-v2-usage-value=remaining]');
            const warning = summary.locator('[data-vpn-v2-usage-warning]');
            assert.equal(await total.innerText(), '8 ГБ', 'Stored server total is visible immediately');
            assert.equal(await remaining.innerText(), '2 ГБ');
            assert.match(await summary.locator('[data-vpn-v2-usage-value=term]').innerText(), /^\d+ дн\.$/);
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
            const traffic = page.locator('[data-vpn-v2-server-traffic]');
            const used = traffic.locator('[data-vpn-v2-traffic-value=used][data-server-id="1"]').first();
            assert.equal(await used.innerText(), '4 ГБ', 'Individual inspection also updates server traffic');
            assert.equal(await total.innerText(), '4 ГБ', 'Individual inspection updates total immediately');
            assert.equal(await remaining.innerText(), '6 ГБ');
            assert.equal(await warning.isVisible(), false, 'Confirmed counters have no stale warning');
            const refresh = traffic.locator('[data-vpn-v2-client-traffic-refresh]');
            const readCount = reads;
            await refresh.click();
            await page.waitForFunction(() => !document.querySelector('[data-vpn-v2-client-traffic-refresh]').disabled);
            assert.equal(reads, readCount + 1, 'Single explicit read-only inspection');
            assert.equal(await used.innerText(), '4 ГБ', 'Fresh per-server traffic displayed');
            assert.equal(await total.innerText(), '4 ГБ');
            assert.equal(await remaining.innerText(), '6 ГБ');
            assert.equal(await summary.locator('[role=progressbar]').getAttribute('aria-valuenow'), '40');
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
                await refresh.click();
                await page.waitForFunction(() => !document.querySelector('[data-vpn-v2-client-traffic-refresh]').disabled);
                assert.equal(await used.innerText(), '4 ГБ', 'Unavailable/malformed panel retains previous traffic');
                assert.equal(await total.innerText(), '4 ГБ', 'Total retains the same available counters as the table');
                assert.equal(await warning.isVisible(), true);
                assert((await warning.innerText()).includes('Не удалось обновить'));
                assert.equal(await traffic.locator('[data-vpn-v2-server-traffic-result].text-warning').count(), 1);
            }
            mode = 'success';
        }
        await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2/subscriptions/14?unknown=1');
        const section = page.locator('[data-vpn-v2-client-information]');
        assert.equal(await section.locator('[role=progressbar]:visible').count(), 0, 'Unverified zero has no factual progress bar');
        assert.equal(await section.locator('[data-vpn-v2-usage-value=used]').innerText(), '—');
        assert.equal(await section.locator('[data-vpn-v2-usage-value=remaining]').innerText(), '—');
        assert((await section.innerText()).includes('Трафик ещё не проверялся'));
        await section.locator('summary').click();
        assert(!(await section.innerText()).includes('failed'), 'Traffic error localized');
        mode = 'zero'; await section.locator('[data-vpn-v2-client-traffic-refresh]').click();
        await page.waitForFunction(() => !document.querySelector('[data-vpn-v2-client-traffic-refresh]').disabled);
        assert.equal(await section.locator('[data-vpn-v2-usage-value=used]').innerText(), '0 Б', 'Confirmed zero is not unknown');
        assert.equal(await section.locator('[data-vpn-v2-usage-value=remaining]').innerText(), '10 ГБ');
        assert.equal(await section.locator('[data-vpn-v2-usage-warning]').isVisible(), false, 'Fresh counters clear the persisted failure warning');
        assert(!(await section.locator('[data-vpn-v2-usage-checked]').innerText()).includes('не проверялся'));
        mode = 'partial'; await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2/subscriptions/14?multi=1&stale-total=1&large-limit=1');
        const traffic = page.locator('[data-vpn-v2-server-traffic]');
        const refresh = traffic.locator('[data-vpn-v2-client-traffic-refresh]');
        const values = id => traffic.locator(`[data-vpn-v2-traffic-value=used][data-server-id="${id}"]`).allTextContents();
        assert((await values(1)).every(value => value === '10 ГБ'), 'Two connections grouped under one server');
        assert((await values(2)).every(value => value === '3 ГБ'));
        const total = section.locator('[data-vpn-v2-usage-value=used]');
        const remaining = section.locator('[data-vpn-v2-usage-value=remaining]');
        assert.equal(await total.innerText(), '13 ГБ', 'Server sum ignores stale zero in subscription accounting');
        assert.equal(await remaining.innerText(), '17 ГБ');
        await refresh.click();
        await page.waitForFunction(() => !document.querySelector('[data-vpn-v2-client-traffic-refresh]').disabled);
        assert((await values(1)).every(value => value === '10 ГБ'), 'Partial server response must not replace total with a subtotal');
        assert((await values(2)).every(value => value === '5 ГБ'), 'Other server independently refreshed');
        assert.equal(await total.innerText(), '15 ГБ', 'Saved full server plus fresh reachable server; not a misleading subtotal');
        assert.equal(await remaining.innerText(), '15 ГБ');
        assert.equal(await section.locator('[data-vpn-v2-usage-warning]').isVisible(), true);
        assert.equal(maxConcurrent, 2, 'At most two panel inspections in parallel');
        mode = 'success'; await refresh.click();
        await page.waitForFunction(() => !document.querySelector('[data-vpn-v2-client-traffic-refresh]').disabled);
        assert((await values(1)).every(value => value === '6 ГБ'), 'All reachable connections summed for server');
        assert.equal(await traffic.locator('[data-vpn-v2-server-traffic-result].text-warning').count(), 0);
        assert.equal(await total.innerText(), '11 ГБ', 'All servers contribute once to total');
        assert.equal(await remaining.innerText(), '19 ГБ');
        assert.equal(await section.locator('[data-vpn-v2-usage-warning]').isVisible(), false);
        await refresh.click();
        await page.waitForFunction(() => !document.querySelector('[data-vpn-v2-client-traffic-refresh]').disabled);
        assert.equal(await total.innerText(), '11 ГБ', 'Repeated refresh does not double-count');
        mode = 'wrong-owner'; await refresh.click();
        await page.waitForFunction(() => !document.querySelector('[data-vpn-v2-client-traffic-refresh]').disabled);
        assert((await values(1)).every(value => value === '6 ГБ'), 'Mismatched connection ID cannot overwrite counters');
        assert.equal(await total.innerText(), '11 ГБ', 'Foreign response cannot overwrite total');
        mode = 'over-limit'; await refresh.click();
        await page.waitForFunction(() => !document.querySelector('[data-vpn-v2-client-traffic-refresh]').disabled);
        assert.equal(await total.innerText(), '60 ГБ');
        assert.equal(await remaining.innerText(), '0 Б', 'Remaining allowance never becomes negative');
        assert.equal(await section.locator('[role=progressbar]').getAttribute('aria-valuenow'), '100');
        mode = 'success'; await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2/subscriptions/14?multi=1&unlimited=1&stale-total=1');
        assert.equal(await total.innerText(), '13 ГБ');
        assert.equal(await remaining.innerText(), 'Безлимит');
        assert.equal(await section.locator('[data-vpn-v2-usage-value=term]').innerText(), 'Бессрочно');
        await section.locator('[data-vpn-v2-client-traffic-refresh]').click();
        await page.waitForFunction(() => !document.querySelector('[data-vpn-v2-client-traffic-refresh]').disabled);
        assert.equal(await total.innerText(), '11 ГБ');
        assert.equal(await remaining.innerText(), 'Безлимит');
        assert.equal(await section.locator('[role=progressbar]:visible').count(), 0);
        mode = 'three-servers';
        await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2/subscriptions/14?multi=1&three-servers=1&all-unknown=1&unlimited=1');
        assert.equal(await total.innerText(), '—');
        assert.equal(await remaining.innerText(), 'Безлимит');
        assert.equal(await section.locator('[data-vpn-v2-usage-warning]').isVisible(), true);
        await section.locator('[data-vpn-v2-client-traffic-refresh]').click();
        await page.waitForFunction(() => !document.querySelector('[data-vpn-v2-client-traffic-refresh]').disabled);
        assert.equal(await total.innerText(), '11 ГБ', 'Three previously unknown servers produce a complete total');
        assert.equal(await remaining.innerText(), 'Безлимит');
        assert.equal(await section.locator('[data-vpn-v2-usage-warning]').isVisible(), false);
        assert((await values(1)).every(value => value === '4 ГБ'));
        assert((await values(2)).every(value => value === '2 ГБ'));
        assert((await values(3)).every(value => value === '5 ГБ'));
        const foreign = spawnSync(php, [path.join(__dirname, 'client_information_unit.php'), '--foreign-endpoint'], { encoding: 'utf8' });
        assert.equal(foreign.status, 0);
        assert(foreign.stderr.includes('HTTP_STATUS=404'));
        assert.equal(JSON.parse(foreign.stdout).error, 'Подключение VPN V2 не найдено.');
        assert.deepEqual(errors, []);
        console.log('PASS client info browser: 320–1440px, dark/light, total/server agreement, remaining allowance, unlimited/lifetime, confirmed zero, stale-warning recovery, max 2 concurrent reads, partial/offline retention, ownership, equal cards, XSS and actual 404 controller');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
