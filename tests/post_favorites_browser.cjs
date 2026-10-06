// Actual theme/legacy templates, isolated responses; no application DB or remote writes.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..'), php = process.env.PHP_BIN || 'php';
const fixture = (...args) => execFileSync(php, [path.join(__dirname, 'fixtures/post_favorites.php'), ...args], { encoding: 'utf8' });
const component = (...args) => execFileSync(php, [path.join(__dirname, 'fixtures/favorite_button.php'), ...args], { encoding: 'utf8' });
let checks = 0;
const check = (ok, label) => { checks++; assert.ok(ok, label); };
const shell = (html, fallback) => `<!doctype html><html data-bs-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/${fallback ? 'assets/default' : 'theme-assets'}/css/theme.min.css"><link rel="stylesheet" href="/${fallback ? 'assets/default' : 'theme-assets'}/css/style.css"><link rel="stylesheet" href="/theme-assets/css/home.css"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css"><link rel="stylesheet" href="/assets/default/css/favorites.css"></head><body>${html}</body></html>`;
const routePage = async (page, html, fallback, post) => page.route('**/*', route => {
    const req = route.request(), url = new URL(req.url());
    if (req.method() === 'POST' && post) return post(route);
    if (url.pathname.startsWith('/assets/')) return route.fulfill({ path: path.join(root, 'public', url.pathname) });
    if (url.pathname.startsWith('/theme-assets/')) return route.fulfill({ path: path.join(root, 'themes/default/assets', url.pathname.slice('/theme-assets/'.length)) });
    if (req.resourceType() === 'image') return route.fulfill({ body: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2AAAAABJRU5ErkJggg==', 'base64'), contentType: 'image/png' });
    return route.fulfill({ body: shell(html, fallback), contentType: 'text/html' });
});
(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        for (const locale of ['ru', 'en', 'de', 'zh-cn']) for (const fallback of [false, true]) {
            for (const kind of ['posts', 'post', 'category', 'archive', 'home']) {
                if (fallback && ['category', 'archive'].includes(kind)) continue;
                const page = await browser.newPage();
                await routePage(page, fixture(locale, kind, fallback ? 'fallback' : 'theme'), fallback);
                await page.goto('https://favorites.test/'); await page.evaluate(() => document.fonts.ready);
                for (const width of [320, 390, 1440]) for (const theme of ['light', 'dark']) {
                    await page.setViewportSize({ width, height: 900 });
                    await page.evaluate(theme => document.documentElement.dataset.bsTheme = theme, theme);
                    const result = await page.evaluate(() => ({
                        controls: [...document.querySelectorAll('.post-favorite__button')].map(e => { const r = e.getBoundingClientRect(); return [r.width, r.height, !!e.getAttribute('aria-label'), !!e.closest('a')]; }),
                        overflow: document.documentElement.scrollWidth > innerWidth,
                        nested: !!document.querySelector('a button, a form'),
                        untranslated: /\baccount_favorite_[a-z_]+\b/.test(document.body.innerText),
                        icons: [...document.querySelectorAll('[data-favorite-form]')].every(f => !!f.querySelector(f.querySelector('button').getAttribute('aria-pressed') === 'true' ? '.ci-heart-filled' : '.ci-heart'))
                    }));
                    const at = `${locale}/${kind}/${fallback}/${width}/${theme}`;
                    check(result.controls.length === (kind === 'post' ? 1 : 3), 'One heart per post: ' + at);
                    check(result.controls.every(([w, h, label]) => Math.abs(w - 44) < .02 && Math.abs(h - 44) < .02 && label), 'Stable accessible 44px controls: ' + at);
                    check(!result.nested && !result.untranslated && result.icons, 'Links, translations and saved outline/filled states: ' + at);
                    // Legacy homepage has a pre-existing carousel requiring Swiper; validate the affected headings instead.
                    if (!(kind === 'home' && fallback)) check(!result.overflow, 'No document overflow with long titles: ' + at);
                    if (kind === 'home') {
                        const overlay = await page.locator('.post-favorite-media').evaluateAll(items => items.map(media => {
                            const control = media.querySelector('.post-favorite__button'), m = media.getBoundingClientRect(), b = control.getBoundingClientRect();
                            const overlaps = selector => [...media.querySelectorAll(selector)].some(e => {
                                const r = e.getBoundingClientRect();
                                return b.left < r.right && b.right > r.left && b.top < r.bottom && b.bottom > r.top;
                            });
                            return {
                                placed: !!control.closest('.post-favorite--overlay') && Math.abs(b.top - m.top - 12) < .02 && Math.abs(m.right - b.right - 12) < .02 && b.bottom <= m.bottom,
                                independent: !control.closest('a:not(.post-favorite__button)') && !media.parentElement.querySelector('.post-favorite-heading, .home-camera-card__body .post-favorite'),
                                unobstructed: !overlaps('.home-online-badge, .home-camera-card__open')
                            };
                        }));
                        check(overlay.length === 3 && overlay.every(o => o.placed), 'Hearts contained in photo, 12px top/right offsets: ' + at);
                        check(overlay.every(o => o.independent && o.unobstructed), 'Separate media links, titles and photo badges: ' + at);
                    }
                }
                await page.close();
            }
        }
        for (const fallback of [false, true]) for (const width of [390, 1440]) {
            const page = await browser.newPage({ viewport: { width, height: 900 } });
            let saved = true, fail = false, sent = 0;
            await routePage(page, fixture('ru', 'home', fallback ? 'fallback' : 'theme'), fallback, route => {
                sent++; check(route.request().postData().includes('fixture'), 'Photo heart submits CSRF');
                saved = !saved;
                return route.fulfill({ status: fail ? 422 : 200, contentType: 'application/json', body: JSON.stringify(fail ? { status: 'error', message: 'Не удалось сохранить. Попробуйте снова.' } : { status: 'success', saved, label: saved ? 'В избранном' : 'В избранное' }) });
            });
            await page.goto('https://favorites.test/');
            await page.addScriptTag({ content: fs.readFileSync(path.join(root, 'public/assets/default/js/favorites.js'), 'utf8') });
            const media = page.locator('.post-favorite-media').first(), button = media.locator('button'), link = media.locator(':scope > a');
            await button.click();
            await page.waitForFunction(() => document.querySelector('.post-favorite-media button').getAttribute('aria-pressed') === 'false' && !document.querySelector('.post-favorite-media button').disabled);
            check(page.url() === 'https://favorites.test/' && sent === 1, 'Overlay click toggles favorite without opening post');
            check(await button.evaluate(e => {
                const r = e.getBoundingClientRect();
                return document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2)?.closest('.post-favorite__button') === e;
            }), 'Photo heart is visible and hit-testable');
            fail = true; await button.click();
            const error = media.locator('[data-favorite-error]'); await error.waitFor({ state: 'visible' });
            check(await error.evaluate(e => {
                const r = e.getBoundingClientRect(), m = e.closest('.post-favorite-media').getBoundingClientRect();
                return r.left >= m.left && r.right <= m.right && r.top >= m.top && r.bottom <= m.bottom;
            }), 'Overlay error fits within photo on mobile/desktop');
            fail = false; saved = false; await button.click();
            await page.waitForFunction(() => document.querySelector('.post-favorite-media button').getAttribute('aria-pressed') === 'true' && !document.querySelector('.post-favorite-media button').disabled);
            check(await media.locator('.ci-heart-filled').count() === 1 && await error.isHidden(), 'Photo favorite retry restores red filled heart');
            const target = await link.getAttribute('href');
            await link.click({ position: { x: 70, y: 80 } });
            check(new URL(page.url()).pathname === target && sent === 3, 'Rest of photo still opens post, not favorite');
            await page.close();
        }
        const html = component('ru') + component('ru');
        const page = await browser.newPage(); let fail = false, saved = true, sent = 0;
        await routePage(page, html, false, route => {
            sent++; check(route.request().postData().includes('fixture'), 'CSRF submitted');
            return route.fulfill({ status: fail ? 422 : 200, contentType: 'application/json', body: JSON.stringify(fail ? { status: 'error', message: '<img src=x onerror=window.bad=true>' } : { status: 'success', saved, label: saved ? 'В избранном' : 'В избранное' }) });
        });
        await page.goto('https://favorites.test/posts/test');
        await page.addScriptTag({ content: fs.readFileSync(path.join(root, 'public/assets/default/js/favorites.js'), 'utf8') });
        const controls = page.locator('[data-favorite-form] button');
        await controls.first().click(); await page.waitForFunction(() => [...document.querySelectorAll('[data-favorite-form] button')].every(b => b.getAttribute('aria-pressed') === 'true' && !b.disabled));
        check(await page.locator('.ci-heart-filled').count() === 2 && await controls.first().getAttribute('aria-label') === 'Удалить из избранного', 'All copies switch icons and accessible actions');
        fail = true; await controls.last().click(); await page.locator('[data-favorite-error]').last().waitFor({ state: 'visible' });
        check(await page.locator('[data-favorite-error] img').count() === 0 && await controls.first().isEnabled() && await controls.last().isEnabled(), 'Errors escaped and all controls recover');
        fail = false; saved = false; await controls.last().click(); await page.waitForFunction(() => [...document.querySelectorAll('[data-favorite-form] button')].every(b => b.getAttribute('aria-pressed') === 'false' && !b.disabled));
        check(await page.locator('.ci-heart-filled').count() === 0 && sent === 3, 'Remove/retry restores both outline hearts');
        await page.close();
        for (const mode of ['guest', 'unavailable']) for (const kind of ['posts', 'home']) {
            const page = await browser.newPage();
            await routePage(page, fixture('ru', kind, 'theme', mode), false);
            await page.goto('https://favorites.test/posts');
            check(mode === 'guest' ? await page.locator('.post-favorite a[href$="/login"]').count() === 3 : await page.locator('.post-favorite button:disabled').count() === 3, 'Guest CTA or disabled pending-migration state');
            await page.close();
        }
        console.log(`Public post heart checks passed: ${checks}`);
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
