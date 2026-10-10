// Deterministic template/CMS CSS tests. Does not contact the CMS or mobile VPN clients.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
const php = process.env.VPN_TEST_PHP || '/Applications/MAMP/bin/php/php8.2.0/bin/php';
(async () => {
    const browser = await chromium.launch({headless: true, ...(process.env.VPN_TEST_CHROMIUM ? {executablePath: process.env.VPN_TEST_CHROMIUM} : {})});
    let cases = 0;
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        await page.route('**/*', async route => {
            const url = new URL(route.request().url());
            assert.equal(url.origin, 'https://vpn.test', 'No third-party request during page render');
            if (url.pathname.startsWith('/assets/')) return route.fulfill({path: root + '/public' + url.pathname});
            if (url.pathname.endsWith('vpn-manager-v2.js')) return route.fulfill({path: root + url.pathname});
            const html = execFileSync(php, [path.join(__dirname, 'smart_connect_render.php'), url.searchParams.get('mode') || 'settings', url.searchParams.get('lang') || 'ru'], {encoding: 'utf8'});
            assert(!/Warning:|Fatal error:/.test(html) && !html.includes('vpn_manager_v2_'), 'Templates render cleanly with all translations');
            return route.fulfill({contentType: 'text/html; charset=utf-8', body: html});
        });
        for (const lang of ['ru', 'en', 'de', 'zh-cn']) for (const width of [320, 390, 768, 1440]) {
            await page.setViewportSize({width, height: 1000});
            await page.goto('https://vpn.test/?lang=' + lang);
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Settings overflow at ' + width + ' ' + lang);
            assert.equal(await page.locator('#smart-connect .form-select').count(), 2);
            assert.equal(await page.locator('[name="smart_connect_server_priorities[1]"]').count(), 1);
            assert.equal(await page.locator('[data-vpn-v2-happ-protection]').count(), 1);
            assert.equal(await page.locator('[data-vpn-v2-happ-protection] a[href="#vpnSmartProvider"]').count(), 1);
            assert.equal(await page.inputValue('#vpnV2HappServerSettingsPolicy'), 'default');
            await page.selectOption('#vpnV2HappServerSettingsPolicy', 'hide');
            await page.selectOption('#vpnSmartMode', 'manual');
            assert.equal(await page.inputValue('#vpnSmartMode'), 'manual');
            const form = await page.locator('form').evaluate(form => Object.fromEntries(new FormData(form)));
            assert.equal(form.smart_connect_enabled, '1');
            assert.equal(form.smart_connect_happ_ping_on_open, '1');
            assert.equal(form.smart_connect_interval_seconds, '180');
            assert.equal(form.happ_server_settings_policy, 'hide');
            assert.equal(form.smart_connect_happ_provider_id, 'fixture-provider');
            assert(!await page.evaluate(() => window.injected), 'Escaped server name');
            await page.goto('https://vpn.test/?mode=access&lang=' + lang);
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Access card overflow');
            assert.equal(await page.locator('[data-vpn-v2-copy-value]').count(), 1);
            assert((await page.locator('[data-vpn-v2-copy-value]').getAttribute('data-vpn-v2-copy-value')).endsWith('?format=singbox'));
            if (lang === 'ru' && width === 390) {
                await page.screenshot({path: '/private/tmp/vpn-smart-access-390.png', fullPage: true});
                await page.goto('https://vpn.test/?lang=ru#smart-connect');
                await page.evaluate(() => document.fonts.ready);
                await page.locator('#smart-connect').evaluate(section => section.scrollIntoView({block: 'start', behavior: 'instant'}));
                await page.screenshot({path: '/private/tmp/vpn-smart-settings-390-overview.png'});
                await page.screenshot({path: '/private/tmp/vpn-smart-settings-390.png', fullPage: true});
            }
            cases++;
        }
        await page.goto('https://vpn.test/?mode=disabled');
        assert.equal(await page.locator('[data-vpn-v2-copy-value]').count(), 0);
        assert.deepEqual(errors, []);
        console.log(JSON.stringify({status: 'ok', viewport_language_cases: cases, disabled_links_hidden: true}));
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exit(1); });
