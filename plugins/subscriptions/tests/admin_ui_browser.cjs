// Render real plugin views with CMS assets, without touching the working site or database.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
const origin = 'https://subscriptions-ui.test';
const screens = ['overview', 'plans', 'subscribers', 'exclusions', 'payments', 'content', 'fields', 'settings', 'plan-form', 'field-form', 'exclusion-form'];
const css = ['css/theme.min.css', 'css/style.css', 'css/admin-ui.css', 'icons/cartzilla-icons.min.css'];
const scripts = ['js/jquery-3.7.1.min.js', 'bootstrap/js/bootstrap.bundle.min.js', 'js/main.js', 'js/admin-ui.js'];
const mime = { '.js': 'application/javascript', '.css': 'text/css', '.woff2': 'font/woff2', '.woff': 'font/woff', '.svg': 'image/svg+xml' };
let checks = 0;
function check(value, expected, label) { assert.deepEqual(value, expected, label); checks++; }
(async () => {
    const browser = await chromium.launch({ headless: true, ...(process.env.SUBSCRIPTIONS_BROWSER_EXECUTABLE ? { executablePath: process.env.SUBSCRIPTIONS_BROWSER_EXECUTABLE } : {}) });
    try {
        const page = await browser.newPage();
        const errors = [], unexpected = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('**/*', async route => {
            const request = route.request(), url = new URL(request.url());
            if (url.origin !== origin || request.method() !== 'GET') { unexpected.push(request.url()); return route.abort(); }
            if (url.pathname.startsWith('/preview/')) {
                const screen = url.pathname.slice('/preview/'.length);
                check(screens.includes(screen), true, 'Only known fixture views');
                let html = execFileSync('php', [path.join(__dirname, 'fixtures/payment-admin.php'), screen, 'ru', url.searchParams.get('theme')], { encoding: 'utf8' });
                html = html.replace('<body>', css.map(asset => `<link rel="stylesheet" href="/assets/default/${asset}">`).join('')
                    + '<link rel="stylesheet" href="/plugins/subscriptions/assets/subscriptions.css"><body class="fb-admin-body">')
                    .replaceAll('name="csrf"', 'name="needCSRFToken"')
                    .replace('</body>', `<script>var baseUrl = ${JSON.stringify(origin)};</script>` + scripts.map(asset => `<script src="/assets/default/${asset}"></script>`).join('') + '</body>');
                return route.fulfill({ contentType: 'text/html', body: html });
            }
            if (url.pathname.startsWith('/assets/default/') || url.pathname.startsWith('/plugins/subscriptions/assets/')) {
                const filename = path.resolve(url.pathname.startsWith('/assets/') ? path.join(root, 'public') : root, '.' + url.pathname);
                if (filename.startsWith(root + path.sep) && fs.existsSync(filename)) return route.fulfill({ body: fs.readFileSync(filename), contentType: mime[path.extname(filename)] || 'application/octet-stream' });
            }
            unexpected.push(request.url()); return route.abort();
        });
        for (const width of [390, 1440]) for (const theme of ['light', 'dark']) for (const screen of screens) {
            const context = `${screen}/${width}/${theme}`;
            await page.setViewportSize({ width, height: 1000 });
            await page.goto(`${origin}/preview/${screen}?theme=${theme}`, { waitUntil: 'domcontentloaded' });
            await page.waitForFunction(() => !!window.bootstrap && !!window.jQuery);
            check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, context + ': no horizontal page overflow');
            const more = page.locator('[data-subscriptions-admin-nav] button');
            await more.click();
            check(await more.getAttribute('aria-expanded'), 'true', context + ': More opens');
            check(await page.locator('[data-subscriptions-admin-nav] .dropdown-menu.show a').count(), 3, context + ': secondary sections accessible');
            await more.press('Escape');
            check(await more.getAttribute('aria-expanded'), 'false', context + ': keyboard dismissal');
            if (['plans', 'fields', 'exclusions', 'subscribers'].includes(screen)) {
                const toggle = page.locator('[data-admin-post-actions-dropdown] > button:visible').first();
                await toggle.click();
                const menu = page.locator('.dropdown-menu.show').first();
                await menu.waitFor();
                check(await menu.evaluate(element => { const r = element.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth + 1 && r.top >= 0; }), true, context + ': action menu not clipped');
                await toggle.press('Escape');
            }
            if (screen === 'payments') {
                if (width < 768) await page.locator('[data-admin-post-actions-dropdown] > button:visible').first().click();
                await page.locator('[data-subscriptions-payment-details]:visible').first().click();
                await page.locator('#subscriptionsPaymentDetailsModal.show').waitFor();
                check(await page.locator('[data-subscriptions-payment-details-body]').innerText().then(text => text.includes('Многоквартирные дома')), true, context + ': payment details still open');
                await page.locator('#subscriptionsPaymentDetailsModal .btn-close').click();
                await page.locator('#subscriptionsPaymentDetailsModal').waitFor({ state: 'hidden' });
            }
            if (screen === 'settings') check(await page.locator('[data-address-import]').getAttribute('data-import-ready'), '1', context + ': CSV importer still initializes');
            if (screen === 'subscribers') {
                await page.locator('[data-subscriptions-grant-panel] summary').click();
                check(await page.locator('#subscriptionsGrantUser').isVisible(), true, context + ': grant form expands');
                check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, context + ': expanded grant form fits viewport');
                await page.locator('[data-subscriptions-grant-panel] summary').click();
            }
            if (process.env.SUBSCRIPTIONS_SCREENSHOT_DIR && ['overview', 'plans', 'subscribers', 'settings'].includes(screen)) {
                await page.locator('h1').click();
                await page.screenshot({ path: path.join(process.env.SUBSCRIPTIONS_SCREENSHOT_DIR, `${screen}-${width}-${theme}.png`), fullPage: true });
            }
        }
        check(errors, [], 'No uncaught browser errors');
        check(unexpected, [], 'No missing assets, live requests, or form submissions');
        console.log(`Admin UI browser tests passed: ${checks} checks, 44 desktop/mobile/theme views.`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
