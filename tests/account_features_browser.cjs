// Real templates/CMS styles/JS, intercepted requests only: no app DB or network writes.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..'), php = process.env.PHP_BIN || 'php';
let checks = 0;
const check = (ok, label) => { checks++; assert.ok(ok, label); };
const fixture = (...args) => execFileSync(php, args, { encoding: 'utf8' });
const shell = (html, theme = 'dark') => `<!doctype html><html data-bs-theme="${theme}"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css"><link rel="stylesheet" href="/assets/default/css/profile.css"><link rel="stylesheet" href="/assets/default/css/style.css"><link rel="stylesheet" href="/assets/default/css/favorites.css"></head><body>${html}</body></html>`;
(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        for (const locale of ['ru', 'en', 'de', 'zh-cn']) {
            for (const fallback of [false, true]) {
                for (const section of ['sessions', 'favorites']) {
                    const html = fixture(path.join(__dirname, 'fixtures/profile.php'), locale, section, fallback ? 'fallback' : 'theme', '/profile/' + section);
                    const page = await browser.newPage();
                    await page.route('**/*', route => {
                        const url = new URL(route.request().url());
                        if (url.pathname.startsWith('/assets/')) return route.fulfill({ path: path.join(root, 'public', url.pathname) });
                        return route.fulfill({ body: shell(html), contentType: 'text/html' });
                    });
                    await page.goto('https://account.test/profile/' + section);
                    await page.evaluate(() => document.fonts.ready);
                    for (const width of [320, 390, 768, 1440]) for (const theme of ['light', 'dark']) {
                        await page.setViewportSize({ width, height: 900 });
                        await page.evaluate(theme => document.documentElement.dataset.bsTheme = theme, theme);
                        const metrics = await page.evaluate(() => ({ overflow: document.documentElement.scrollWidth > innerWidth,
                            buttons: [...document.querySelectorAll('button[type="submit"]')].map(b => b.getBoundingClientRect().height),
                            current: document.querySelector('[data-profile-route-nav] a[aria-current="page"]')?.textContent,
                            injected: Boolean(window.bad), untranslated: /\baccount_[a-z_]+\b/.test(document.body.innerText),
                            outside: [...document.querySelectorAll('section, form, button, .profile-content, .table-responsive')].filter(e => e.getBoundingClientRect().right > innerWidth + 1).map(e => e.className),
                            widths: [innerWidth, document.documentElement.scrollWidth], table: [...document.querySelectorAll('.table-responsive')].map(e => [getComputedStyle(e).overflowX, e.clientWidth, e.scrollWidth]),
                            wide: [...document.querySelectorAll('*')].filter(e => e.scrollWidth > e.clientWidth + 1 && !e.closest('table')).map(e => [e.tagName, e.className, e.clientWidth, e.scrollWidth, getComputedStyle(e).overflowX, getComputedStyle(e).position]) }));
                        const at = `${locale}/${section}/${fallback}/${width}/${theme}`;
                        check(!metrics.overflow, 'Page contains responsive table/cards: ' + at + ' ' + JSON.stringify(metrics));
                        check(metrics.buttons.length > 0 && metrics.buttons.every(height => height >= 43), 'Touch targets: ' + at);
                        check(!metrics.injected && !metrics.untranslated, 'Escaping and translations: ' + at);
                        check(Boolean(metrics.current), 'Current account section indicated: ' + at);
                    }
                    check(await page.locator('input[name="needCSRFToken"]').count() > 0, 'Forms have CSRF token');
                    check(await page.locator('.pagination .page-link').count() === 2, 'CMS pagination');
                    await page.close();
                }
            }
            const html = fixture(path.join(__dirname, 'fixtures/favorite_button.php'), locale);
            const page = await browser.newPage(); let mode = 'add', requests = [];
            await page.route('**/*', route => {
                const req = route.request(), url = new URL(req.url());
                if (req.method() === 'POST') {
                    requests.push(req);
                    if (mode === 'error') return route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ status: 'error', message: '<img src=x onerror=window.bad=true>' }) });
                    return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ status: 'success', saved: mode === 'add', label: mode === 'add' ? 'Saved' : 'Add' }) });
                }
                if (url.pathname.startsWith('/assets/')) return route.fulfill({ path: path.join(root, 'public', url.pathname) });
                return route.fulfill({ body: shell(html), contentType: 'text/html' });
            });
            await page.goto('https://account.test/posts/test');
            await page.addScriptTag({ content: fs.readFileSync(path.join(root, 'public/assets/default/js/favorites.js'), 'utf8') });
            await page.locator('button').click(); await page.waitForFunction(() => document.querySelector('button').getAttribute('aria-pressed') === 'true');
            check((await page.locator('form').getAttribute('action')).endsWith('/remove'), 'AJAX state becomes removable');
            check(requests[0].postData().includes('fixture') && requests[0].postData().includes('42'), 'CSRF and entity sent');
            mode = 'error'; await page.locator('button').click(); await page.locator('[data-favorite-error]').waitFor({ state: 'visible' });
            check(await page.locator('[data-favorite-error] img').count() === 0 && await page.locator('button').isEnabled(), 'Failure text is escaped; button recovers');
            mode = 'remove'; await page.locator('button').click(); await page.waitForFunction(() => document.querySelector('button').getAttribute('aria-pressed') === 'false');
            check((await page.locator('form').getAttribute('action')).endsWith('/add'), 'Retry succeeds');
            check(await page.locator('form').getAttribute('method') === 'post', 'Native form fallback preserved');
            await page.close();
            const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 320, height: 800 } });
            const native = await context.newPage(); let posted = null;
            await native.route('**/*', route => {
                const req = route.request(), url = new URL(req.url());
                if (req.method() === 'POST') { posted = req.postData(); return route.fulfill({ contentType: 'text/html', body: 'SAVED' }); }
                if (url.pathname.startsWith('/assets/')) return route.fulfill({ path: path.join(root, 'public', url.pathname) });
                return route.fulfill({ contentType: 'text/html', body: shell(html) });
            });
            await native.goto('https://account.test/posts/test');
            check((await native.locator('button').boundingBox()).height >= 44, 'Public button touch target');
            await native.locator('button').click(); await native.waitForURL('**/profile/favorites/add');
            check(posted.includes('needCSRFToken=fixture') && posted.includes('entity_id=42'), 'No-JS browser submits authenticated form contract');
            check(await native.locator('body').innerText() === 'SAVED', 'Native POST completes');
            const guestHtml = fixture(path.join(__dirname, 'fixtures/favorite_button.php'), locale, 'guest');
            await native.unroute('**/*');
            await native.route('**/*', route => {
                const url = new URL(route.request().url());
                if (url.pathname.startsWith('/assets/')) return route.fulfill({ path: path.join(root, 'public', url.pathname) });
                return route.fulfill({ contentType: 'text/html', body: shell(guestHtml) });
            });
            await native.goto('https://account.test/posts/test');
            check((await native.locator('a').getAttribute('href')).endsWith('/login'), 'Guest login CTA');
            check(await native.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Translated guest CTA fits 320px');
            check((await native.locator('a').boundingBox()).height >= 44, 'Guest CTA touch target');
            await context.close();
        }
        console.log(`Account feature browser checks passed: ${checks}`);
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
