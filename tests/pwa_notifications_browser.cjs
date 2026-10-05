// Actual CMS table/cards/pagination, isolated routes; no working DB or real Push.
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
                let empty = false;
                const posts = [];
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                await page.route('https://notifications.test/**', async route => {
                    const request = route.request();
                    const url = new URL(request.url());
                    if (url.pathname.startsWith('/assets/')) {
                        return route.fulfill({ path: path.join(root, 'public', url.pathname), contentType: url.pathname.endsWith('.css') ? 'text/css' : url.pathname.endsWith('.js') ? 'application/javascript' : 'font/woff2' });
                    }
                    if (request.method() === 'POST') {
                        check(url.pathname === prefix + '/admin/settings/pwa/notifications/clear', 'Clear posts only to the log endpoint');
                        const post = Object.fromEntries(new URLSearchParams(request.postData()));
                        posts.push(post);
                        empty = true;
                        return route.fulfill({ contentType: 'text/html', body: `<script>location.replace('https://notifications.test${prefix}/admin/settings/pwa?devices_page=${Number(post.devices_page)}#pwa-notifications')</script>` });
                    }
                    const html = execFileSync(php, [path.join(__dirname, 'fixtures/pwa_notifications.php'), locale,
                        url.searchParams.get('notifications_page') || '1', url.searchParams.get('devices_page') || '2', empty ? 'empty' : ''], { encoding: 'utf8' });
                    return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html lang="${locale}" data-bs-theme="${mobile ? 'dark' : 'light'}"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
                        <link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css">
                        <link rel="stylesheet" href="/assets/default/css/style.css"><link rel="stylesheet" href="/assets/default/css/admin-ui.css"></head>
                        <body class="fb-admin-body"><main class="container py-4" data-admin-shell data-fb-admin>${html}</main>
                        <script>const baseUrl = 'https://notifications.test'; const themeAssetsUrl = '/assets/default/';</script>
                        <script src="/assets/default/js/jquery-3.7.1.min.js"></script><script src="/assets/default/bootstrap/js/bootstrap.bundle.min.js"></script><script src="/assets/default/js/admin-delete-modal.js"></script>
                        <script src="/assets/default/js/main.js"></script><script src="/assets/default/js/admin-ui.js"></script></body></html>` });
                });
                await page.goto('https://notifications.test' + prefix + '/admin/settings/pwa?devices_page=2');
                await page.waitForFunction(() => window.bootstrap && window.jQuery);
                const log = page.locator('#pwa-notifications');
                const devices = page.locator('#pwa-devices');
                const rows = log.locator(mobile ? '.admin-mobile-table-card' : '.admin-table-component__table tbody tr');
                const activePage = async section => (await section.locator('.admin-pagination-nav .page-item.active').textContent()).trim();
                check(await rows.count() === 20, '20 notifications per page');
                check(await activePage(log) === '1' && await activePage(devices) === '2', 'Independent CMS pagination states');
                check(!/PRIVATE_|private-attachment/.test(await page.content()), 'No legacy private text even in hidden table/cards markup');
                check(!await page.evaluate(() => window.leaked), 'Ordinary text remains escaped');
                check((await log.textContent()).includes('Public update'), 'Public notifications remain readable');
                check(!/admin_pwa_|admin_table_/.test(await log.textContent()), 'All privacy/status labels translated');
                check(await log.locator('[data-admin-mobile-table-cards]').isVisible() === mobile, 'Native responsive table/cards');
                check(!await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), 'No horizontal page overflow');
                if (mobile) {
                    const touch = await log.locator('.admin-pagination-nav a.page-link').first().boundingBox();
                    const baseTouch = await devices.locator('.admin-pagination-nav a.page-link').first().boundingBox();
                    check(touch.width === baseTouch.width && touch.height === baseTouch.height, 'Pager retains the shared CMS button dimensions');
                }
                await log.locator('.admin-pagination-nav a.page-link').filter({ hasText: /^\s*3\s*$/ }).click();
                check(await rows.count() === 5 && await activePage(log) === '3', 'Final log page has five records');
                check(new URL(page.url()).searchParams.get('devices_page') === '2', 'Log paging retains devices page');
                await devices.locator('.admin-pagination-nav a.page-link').filter({ hasText: /^\s*3\s*$/ }).click();
                check(await activePage(devices) === '3' && await activePage(log) === '3', 'Device paging retains log page');
                await log.locator('.admin-pagination-nav a.page-link').filter({ hasText: /^\s*2\s*$/ }).click();
                check(await rows.count() === 20 && await activePage(log) === '2' && await activePage(devices) === '3', 'Middle page and both query parameters work');
                await page.reload();
                check(await activePage(log) === '2' && !/PRIVATE_|private-attachment/.test(await page.content()), 'Direct reload remains private and paginated');
                check(new URL(page.url()).pathname === prefix + '/admin/settings/pwa', 'Locale retained in links');
                if (process.env.PWA_NOTIFICATIONS_SCREENSHOT && locale === 'ru' && mobile) {
                    await page.goto('https://notifications.test/admin/settings/pwa?devices_page=2');
                    await page.screenshot({ path: process.env.PWA_NOTIFICATIONS_SCREENSHOT, fullPage: false });
                }
                const clear = log.locator('form[data-admin-delete-form] button');
                const devicePageBeforeClear = await activePage(devices);
                check((await clear.boundingBox()).height >= 44, 'Clear button touch area');
                await clear.click();
                await page.locator('[data-admin-delete-modal].show').waitFor();
                check(posts.length === 0 && await page.locator('[data-admin-delete-modal-item]').textContent() === '45', 'Clear requires confirmation of all 45 entries');
                await page.locator('[data-bs-dismiss=modal]').click();
                await page.locator('[data-admin-delete-modal].show').waitFor({ state: 'hidden' });
                check(await rows.count() === 20 && posts.length === 0, 'Cancel retains the journal');
                await clear.click();
                await page.locator('[data-admin-delete-modal].show').waitFor();
                await page.locator('[data-admin-delete-modal-confirm]').click();
                await page.waitForURL('**#pwa-notifications');
                check(posts.length === 1 && posts[0].scope === 'all' && posts[0].needCSRFToken === 'fixture' && posts[0].devices_page === devicePageBeforeClear, 'Confirmed clear carries explicit scope, CSRF and device page');
                check(await log.locator('.admin-pagination-nav').count() === 0 && await rows.count() <= 1, 'Empty log has no pager');
                check(await activePage(devices) === devicePageBeforeClear, 'Empty log does not affect devices');
                check(await log.locator('form[data-admin-delete-form]').count() === 0, 'Clear button disappears from empty log');
                check(errors.length === 0, 'No browser errors: ' + JSON.stringify(errors));
                await context.close();
            }
        }
        console.log(`PWA notifications browser checks passed: ${checks}`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
