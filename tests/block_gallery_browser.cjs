// Actual GLightbox + Cartzilla initializer, renderer and layout includes; all requests intercepted.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..'), php = process.env.PHP_BIN || 'php';
const fixture = (...args) => execFileSync(php, [path.join(__dirname, 'fixtures/block_gallery.php'), ...args], { encoding: 'utf8' });
let checks = 0;
const check = (ok, label) => { checks++; assert.ok(ok, label); };
const routePage = async (page, html) => page.route('**/*', route => {
    const url = new URL(route.request().url());
    if (url.origin !== 'https://gallery.test') return route.abort();
    if (url.pathname.startsWith('/assets/')) return route.fulfill({ path: path.join(root, 'public', url.pathname) });
    if (url.pathname.startsWith('/theme-assets/')) return route.fulfill({ path: path.join(root, 'themes/default/assets', url.pathname.slice('/theme-assets/'.length)) });
    if (url.pathname.startsWith('/photos/')) return route.fulfill({
        body: '<svg xmlns="http://www.w3.org/2000/svg" width="306" height="342"><rect width="100%" height="100%" fill="#168d68"/><text x="20" y="100" fill="white">' + url.pathname + '</text></svg>',
        contentType: 'image/svg+xml',
    });
    return route.fulfill({ body: html, contentType: 'text/html' });
});
const current = async (page, filename) => {
    await page.waitForFunction(filename => {
        const slide = document.querySelector('.gslide.current');
        const image = slide?.querySelector('img'), loader = document.querySelector('.gloader');
        return image?.src.includes(filename) && image.complete && image.naturalWidth > 0
            && (!loader || getComputedStyle(loader).display === 'none');
    }, filename);
    // Wait for the real slide transition to finish before the next gesture.
    await page.waitForFunction(() => !document.querySelector('.gslide.current.gslideInLeft, .gslide.current.gslideInRight'));
};
const close = async page => {
    await page.keyboard.press('Escape');
    await page.locator('.glightbox-container').waitFor({ state: 'detached' });
};
(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        for (const layout of ['theme', 'fallback']) for (const format of ['snapshot', 'json', 'legacy']) {
            const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
            const errors = []; page.on('pageerror', error => errors.push(error.message));
            await routePage(page, fixture(layout, format)); await page.goto('https://gallery.test/');
            await page.evaluate(() => document.fonts.ready);
            check(await page.evaluate(() => typeof window.GLightbox === 'function'), 'Bundled viewer loaded: ' + layout + '/' + format);
            check(errors.length === 0, 'No initialization errors: ' + errors.join('; '));
            const photos = page.locator('main [data-glightbox]');
            check(await photos.count() === 6, 'All gallery items rendered');
            for (const width of [320, 390, 576, 768, 1440]) for (const theme of ['light', 'dark']) {
                await page.setViewportSize({ width, height: 900 });
                await page.evaluate(theme => document.documentElement.dataset.bsTheme = theme, theme);
                const geometry = await photos.evaluateAll(links => ({
                    overflow: document.documentElement.scrollWidth > innerWidth,
                    rows: links.slice(0, 3).map(link => Math.round(link.getBoundingClientRect().top)),
                    ratios: links.map(link => { const r = link.getBoundingClientRect(); return r.height / r.width; }),
                }));
                check(!geometry.overflow, 'No mobile/desktop overflow');
                check(geometry.rows[0] === geometry.rows[1] && (width >= 576 ? geometry.rows[1] === geometry.rows[2] : geometry.rows[1] < geometry.rows[2]), 'Two mobile / three sm+ columns');
                check(geometry.ratios.every(ratio => Math.abs(ratio - 1) < .02), 'Square previews at every viewport');
            }
            await page.setViewportSize({ width: 1280, height: 900 });
            await photos.first().hover();
            await page.waitForFunction(() => Number(getComputedStyle(document.querySelector('[data-glightbox] .ci-zoom-in')).opacity) > .9);
            check(await photos.first().locator('.ratio').evaluate(el => getComputedStyle(el).transform !== 'none'), 'Cartzilla hover zoom');
            await photos.first().click(); await current(page, 'one.jpg');
            check(await page.locator('.gslide').count() === 3, 'Only the selected block in viewer');
            await page.locator('.gprev').waitFor({ state: 'hidden' });
            check(await page.locator('.gprev').isHidden() && await page.locator('.gnext').isVisible(), 'First-photo edge arrows');
            await page.locator('.gnext').click(); await current(page, 'two.jpg');
            check(await page.locator('.gprev').isVisible() && await page.locator('.gnext').isVisible(), 'Middle-photo arrows');
            check(!(await page.evaluate(() => window.galleryInjected)) && await page.locator('.gslide.current .gslide-description').innerText().then(t => t.includes('<img')), 'Captions are text, not executable markup');
            await page.keyboard.press('ArrowRight'); await current(page, 'view?id=3');
            await page.locator('.gnext').waitFor({ state: 'hidden' });
            check(await page.locator('.gnext').isHidden(), 'Extensionless image opens and last arrow hidden');
            await page.keyboard.press('ArrowLeft'); await current(page, 'two.jpg');
            await close(page);
            check(page.url() === 'https://gallery.test/', 'Viewer never navigates to raw image');
            await photos.nth(4).click(); await current(page, 'five.jpg');
            check(await page.locator('.gslide').count() === 2, 'Second gallery isolated, clicked index preserved');
            await page.locator('.gprev').click(); await current(page, 'four.jpg'); await close(page);
            await photos.nth(5).click(); await current(page, 'six.jpg');
            await page.locator('.gprev').waitFor({ state: 'hidden' });
            await page.locator('.gnext').waitFor({ state: 'hidden' });
            check(await page.locator('.gslide').count() === 1 && await page.locator('.gprev').isHidden() && await page.locator('.gnext').isHidden(), 'Single image: no misleading navigation');
            await page.locator('.gclose').click(); await page.locator('.glightbox-container').waitFor({ state: 'detached' });
            check(await page.locator('.glightbox-container').count() === 0 && !(await page.locator('body').getAttribute('class') || '').includes('glightbox-open'), 'Close restores page, no leftover overlay');
            check(errors.length === 0, 'No errors after navigation');
            await page.close();
        }
        const mobile = await browser.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
        await routePage(mobile, fixture('theme', 'snapshot')); await mobile.goto('https://gallery.test/');
        await mobile.locator('main [data-glightbox]').first().tap(); await current(mobile, 'one.jpg');
        const session = await mobile.context().newCDPSession(mobile);
        const swipe = async (from, to) => {
            await session.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: from, y: 390 }] });
            for (let n = 1; n <= 6; n++) await session.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: from + (to - from) * n / 6, y: 390 }] });
            await session.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        };
        await swipe(300, 70); await current(mobile, 'two.jpg');
        await swipe(70, 300); await current(mobile, 'one.jpg');
        check(await mobile.locator('.gslide').count() === 3, 'Trusted touch swipes navigate both directions');
        await mobile.locator('.gclose').tap(); await mobile.locator('.glightbox-container').waitFor({ state: 'detached' });
        await mobile.close();
        console.log(`${checks} public gallery browser checks passed`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
