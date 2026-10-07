// Render actual PHP templates; every browser request is intercepted. No site or panel writes.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
const php = process.env.VPN_TEST_PHP || '/Applications/MAMP/bin/php/php8.2.0/bin/php';
(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage({ locale: 'ru-RU' });
        const errors = [], writes = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('**/*', async route => {
            const url = new URL(route.request().url());
            assert.equal(url.origin, 'https://vpn.test', 'No real external network');
            if (route.request().method() === 'POST') {
                writes.push({ path: url.pathname, data: route.request().postData() });
                return route.fulfill({ contentType: 'text/html', body: '<p>Isolated submission</p>' });
            }
            if (url.pathname.startsWith('/assets/')) return route.fulfill({ path: root + '/public' + url.pathname });
            if (url.pathname === '/plugins/vpn-manager-v2/assets/vpn-manager-v2.js') return route.fulfill({ path: root + url.pathname });
            const args = [path.join(__dirname, url.pathname.includes('/subscriptions/') ? 'client_information_render.php' : 'plan_external_sources_render.php')];
            if (url.pathname.includes('/subscriptions/')) args.push('--plan-external');
            if (url.pathname.endsWith('/create')) args.push('--create');
            const html = execFileSync(php, args, { encoding: 'utf8' });
            assert(!/Warning:|Fatal error:|vpn_manager_v2_/.test(html), 'Real view renders without errors or missing translations');
            assert(!html.includes('private-token'), 'Source tokens never rendered');
            return route.fulfill({ contentType: 'text/html; charset=utf-8', body: html });
        });
        for (const width of [320, 390, 768, 1280, 1440]) for (const theme of ['dark', 'light']) {
            await page.setViewportSize({ width, height: 1000 });
            await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2/plans/edit/1');
            await page.evaluate(theme => document.documentElement.dataset.bsTheme = theme, theme);
            const section = page.locator('[data-vpn-v2-plan-external]');
            assert.equal(await section.count(), 1);
            assert.equal(await section.locator('form[action$="/external/subscription"]').count(), 1);
            assert.equal(await section.locator('form[action$="/external/connection"]').count(), 1);
            assert.equal(await section.locator('form[action$="/external/order"]').count(), 1);
            assert.equal(await section.locator('input[name=source_url]').getAttribute('type'), 'url');
            assert.equal(await section.locator('textarea[name=connection_uri]').getAttribute('maxlength'), '16384');
            assert.equal(await page.locator('form form').count(), 0, 'Independent forms, not nested in tariff form');
            assert.equal(await page.locator('form:not(:has(input[name=needCSRFToken]))').count(), 0, 'Every action has CSRF protection');
            assert.equal(await section.locator('img').count(), 0, 'Source names escaped');
            assert.equal(await page.evaluate(() => window.leaked), undefined);
            const overflow = await page.evaluate(() => Array.from(document.querySelectorAll('main *')).filter(element => {
                const rect = element.getBoundingClientRect(); return rect.width && rect.right > innerWidth + 1 && !element.closest('.dropdown-menu');
            }).slice(0, 12).map(element => ({ tag: element.tagName, class: element.className, text: element.textContent.slice(0, 60), right: element.getBoundingClientRect().right })));
            if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1)) {
                await section.screenshot({ path: '/private/tmp/vpn-plan-external-overflow.png' });
                assert.fail(`No horizontal overflow ${width}/${theme}: ${JSON.stringify(overflow)}`);
            }
            const cards = section.locator('[data-admin-mobile-table-cards]');
            assert.equal(await cards.locator('article').count(), 2);
            assert.equal(await cards.isVisible(), width < 768, 'Native responsive CMS table/cards');
            assert((await cards.innerText()).includes('Конфигурации') && (await cards.innerText()).includes('Источник'), 'Mobile cards keep source information');
            const ordering = section.locator('[data-vpn-v2-connection-order]');
            const order = () => ordering.locator('input[name="external_source_order[]"]').evaluateAll(inputs => inputs.map(input => input.value));
            assert.deepEqual(await order(), ['1', '2']);
            await ordering.locator('[data-vpn-v2-order-move=down]').first().click();
            assert.deepEqual(await order(), ['2', '1'], 'Actual plugin JS reorders plan sources');
            await ordering.locator('[data-vpn-v2-order-move=up]').last().click();
            assert.deepEqual(await order(), ['1', '2']);
            const dropdown = width < 768 ? cards.locator('article').first() : section.locator('tbody tr').first();
            await dropdown.locator('[data-bs-toggle=dropdown]').click();
            assert.equal(await dropdown.locator('.dropdown-menu').isVisible(), true);
            for (const action of ['sync', 'toggle', 'detach']) assert.equal(await dropdown.locator(`form[action$="/external/1/${action}"]`).count(), 1, 'Correct tariff owner in actions');
            await dropdown.locator('[data-bs-toggle=dropdown]').click();
            if (width === 390 || width === 1440) await section.screenshot({ path: `/private/tmp/vpn-plan-external-${theme}-${width}.png` });
            await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2/subscriptions/14');
            const inherited = page.locator('[data-vpn-v2-plan-external]');
            const naming = page.locator('[data-vpn-v2-client-name]');
            assert.equal(await naming.locator('input[name=client_display_name]').getAttribute('maxlength'), '160');
            assert.equal(await naming.locator('input[name=client_display_name]').inputValue(), 'Новое имя клиента');
            assert.equal(await naming.getAttribute('action'), '/admin/plugins/vpn-manager-v2/subscriptions/14/rename');
            assert((await inherited.innerText()).includes('Внешние источники из тарифа'));
            assert.equal(await inherited.locator('form').count(), 0, 'Inherited section read-only, personal sources preserved');
            assert.equal(await inherited.locator('a[href$="/plans/edit/1#external-sources"]').count(), 1, 'Go to owning plan');
            assert(!await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), 'Inherited section fits viewport');
        }
        await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2/plans/create');
        assert.equal(await page.locator('button[name=manage_external]').count(), 1);
        assert.equal(await page.locator('[data-vpn-v2-plan-external]').count(), 0, 'Create tariff first, no ownerless source writes');
        await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2/plans/edit/1');
        const section = page.locator('[data-vpn-v2-plan-external]');
        await section.locator('input[name=source_url]').fill('https://provider.example.test/subscription');
        await section.locator('form[action$="/external/subscription"] button[type=submit]').click();
        await page.waitForURL('**/external/subscription');
        assert.equal(writes.length, 1);
        assert.equal(writes[0].path, '/admin/plugins/vpn-manager-v2/plans/1/external/subscription');
        assert(new URLSearchParams(writes[0].data).get('needCSRFToken') === 'fixture');
        await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2/subscriptions/14');
        const naming = page.locator('[data-vpn-v2-client-name]');
        await naming.locator('input[name=client_display_name]').fill('Переименованный клиент');
        await naming.screenshot({ path: '/private/tmp/vpn-subscription-client-name.png' });
        await naming.locator('button[type=submit]').click();
        await page.waitForURL('**/subscriptions/14/rename');
        assert.equal(writes.length, 2);
        assert.equal(new URLSearchParams(writes[1].data).get('client_display_name'), 'Переименованный клиент');
        assert.equal(new URLSearchParams(writes[1].data).get('needCSRFToken'), 'fixture');
        assert.deepEqual(errors, []);
        console.log('PASS plan sources browser: real plan/subscription views at 320–1440px, light/dark, CMS tables/mobile cards, dropdown CRUD, CSRF POST, source ordering, masking/XSS and read-only inheritance');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
