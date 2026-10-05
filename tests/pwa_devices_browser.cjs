// Actual CMS table/modal assets, isolated routes; never touches the application database.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BIN || 'php';
let checks = 0;
const check = (condition, message) => { checks++; assert.ok(condition, message); };

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        for (const locale of ['ru', 'en', 'de', 'zh-cn']) {
            for (const mobile of [false, true]) {
                const context = await browser.newContext({ viewport: mobile ? { width: 390, height: 844 } : { width: 1440, height: 900 }, isMobile: mobile, hasTouch: mobile });
                const page = await context.newPage();
                const prefix = locale === 'ru' ? '' : '/' + locale;
                let removed = [];
                let posts = [];
                const errors = [];
                page.on('pageerror', e => errors.push(e.message));
                await page.route('https://devices.test/**', async route => {
                    const request = route.request();
                    const url = new URL(request.url());
                    if (url.pathname.startsWith('/assets/')) {
                        return route.fulfill({ path: path.join(root, 'public', url.pathname), contentType: url.pathname.endsWith('.css') ? 'text/css' : url.pathname.endsWith('.woff2') ? 'font/woff2' : 'application/javascript' });
                    }
                    if (request.method() === 'POST') {
                        const post = Object.fromEntries(new URLSearchParams(request.postData()));
                        posts.push(post);
                        removed = post.scope === 'all' ? Array.from({ length: 126 }, (_, i) => i + 1) : [...removed, Number(post.id)];
                        // A new document navigation keeps this fake host inside the route fixture.
                        // The controller's redirect URL is verified separately in the service tests.
                        return route.fulfill({ contentType: 'text/html', body: `<script>location.replace('https://devices.test${prefix}/admin/settings/pwa#pwa-devices')</script>` });
                    }
                    const html = execFileSync(php, [path.join(__dirname, 'fixtures/pwa_devices.php'), locale, url.searchParams.get('devices_page') || '1', JSON.stringify(removed)], { encoding: 'utf8' });
                    return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html lang="${locale}" data-bs-theme="${mobile ? 'dark' : 'light'}"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
                        <link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css">
                        <link rel="stylesheet" href="/assets/default/css/style.css"><link rel="stylesheet" href="/assets/default/css/admin-ui.css"></head>
                        <body class="fb-admin-body"><main class="container py-4" data-admin-shell>${html}</main>
                        <script src="/assets/default/js/jquery-3.7.1.min.js"></script><script src="/assets/default/bootstrap/js/bootstrap.bundle.min.js"></script><script src="/assets/default/js/admin-delete-modal.js"></script></body></html>` });
                });
                await page.goto('https://devices.test' + prefix + '/admin/settings/pwa');
                await page.waitForFunction(() => window.bootstrap && window.jQuery);
                const rows = page.locator(mobile ? '.admin-mobile-table-card' : '.admin-table-component__table tbody tr');
                check(await rows.count() === 20, '20 devices per page');
                check(await page.locator('.admin-pagination-nav .page-item.active').textContent().then(t => t.trim()) === '1', 'CMS pagination active');
                check(!await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), 'No document overflow');
                check(!await page.locator('#pwa-devices').textContent().then(t => /admin_pwa_|admin_posts_|admin_table_/.test(t)), 'All table labels translated');
                check(await page.locator('[data-admin-mobile-table-cards]').isVisible() === mobile, 'CMS responsive table/cards');
                const one = rows.first().locator('button[type=submit]');
                check((await one.boundingBox()).height >= 44, 'Detach touch area');
                await one.click();
                await page.locator('[data-admin-delete-modal].show').waitFor();
                check(posts.length === 0, 'Detach requires confirmation');
                check(await page.locator('[data-admin-delete-modal-confirm-label]').textContent().then(t => t.trim()) === await one.textContent().then(t => t.trim()), 'Localized confirm label');
                await page.locator('[data-bs-dismiss=modal]').click();
                await page.locator('[data-admin-delete-modal].show').waitFor({ state: 'hidden' });
                check(posts.length === 0, 'Cancel does not detach');
                await one.click();
                await page.locator('[data-admin-delete-modal].show').waitFor();
                await page.locator('[data-admin-delete-modal-confirm]').click();
                await page.waitForURL('**#pwa-devices');
                check(posts.length === 1 && posts[0].scope === 'one' && posts[0].id === '1' && posts[0].needCSRFToken === 'fixture', 'One-device POST carries exact ID and CSRF');
                check(!await rows.first().textContent().then(t => /ID:\s*#1\b|^\s*#1\s/.test(t)), 'Detached row disappears');
                await page.locator('.admin-pagination-nav a.page-link').filter({ hasText: /^\s*7\s*$/ }).click();
                await page.waitForURL('**devices_page=7');
                check(await rows.count() === 5, 'Last page updates after detach');
                await page.locator('#pwa-devices form:has(input[name="scope"][value="all"]) button').click();
                await page.locator('[data-admin-delete-modal].show').waitFor();
                check(await page.locator('[data-admin-delete-modal-item]').textContent() === '125', 'Unlink all scope is full table, not just page');
                await page.locator('[data-admin-delete-modal-confirm]').click();
                await page.waitForURL('**#pwa-devices');
                check(posts.length === 2 && posts[1].scope === 'all' && posts[1].needCSRFToken === 'fixture', 'Confirmed all-device action');
                check(await page.locator('#pwa-devices form').count() === 0 && await page.locator('.admin-pagination-nav').count() === 0, 'Empty state has no detach buttons or pagination');
                check(errors.length === 0, 'No UI script errors: ' + JSON.stringify(errors));
                if (process.env.PWA_DEVICES_SCREENSHOT && locale === 'ru' && mobile) {
                    await page.goto('https://devices.test/admin/settings/pwa');
                    removed = [];
                    await page.reload();
                    await page.screenshot({ path: process.env.PWA_DEVICES_SCREENSHOT, fullPage: false });
                }
                await context.close();
            }
        }
        console.log(`PWA devices browser checks passed: ${checks}`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
