// Run with Node.js and Playwright installed. Uses only CLI fixtures and local CMS assets.
const { chromium, webkit } = require('playwright');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');

async function run(browserType) {
    const browser = await browserType.launch({ headless: true, ...(browserType === chromium && process.env.SUBSCRIPTIONS_BROWSER_EXECUTABLE ? { executablePath: process.env.SUBSCRIPTIONS_BROWSER_EXECUTABLE } : {}) });
    try {
        const page = await browser.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        async function load(country, region = '') {
            const html = execFileSync('php', [path.join(__dirname, 'fixtures/profile-region.php'), country, region], { encoding: 'utf8' });
            await page.setContent(html);
            await page.addStyleTag({ path: path.join(root, 'public/assets/default/vendor/choices.js/choices.min.css') });
            await page.addStyleTag({ path: path.join(root, 'public/assets/default/css/theme.min.css') });
            await page.addScriptTag({ path: path.join(root, 'public/assets/default/vendor/choices.js/choices.min.js') });
            await page.addScriptTag({ path: path.join(root, 'plugins/subscriptions/assets/profile-region.js') });
            await page.addScriptTag({ path: path.join(root, 'public/assets/default/js/select-init.js') });
            assert.equal(await page.locator('.choices').count(), 1, 'CMS must not initialize the selector twice');
        }
        async function choose(search, name) {
            await page.locator('.choices__inner, .choices > .form-select').first().click();
            await page.locator('.choices__input--cloned').fill(search);
            await page.locator(`.choices__item--choice[data-value="${name}"]`).click();
            assert.equal(await page.locator('[data-region-select]').inputValue(), name);
            assert.deepEqual(await page.locator('form').evaluate(form => new FormData(form).getAll('region')), [name]);
        }
        await load('Russia');
        await choose('Stavropol', 'Ставропольский край');
        await choose('Moscow Region', 'Московская область');
        await choose('краснояр', 'Красноярский край');
        await page.locator('[name="country"]').fill('Germany');
        assert.equal(await page.locator('[data-region-input]').isVisible(), true);
        await page.locator('[data-region-input]').fill('Bavaria');
        assert.deepEqual(await page.locator('form').evaluate(form => new FormData(form).getAll('region')), ['Bavaria']);
        await page.locator('[name="country"]').fill('Российская Федерация');
        await choose('Stavropol', 'Ставропольский край');
        await load('Germany', 'Bavaria');
        await page.locator('[name="country"]').fill('RU');
        await choose('Leningrad', 'Ленинградская область');
        await load('Russia', 'Stavropol Krai');
        assert.equal(await page.locator('[data-region-select]').inputValue(), 'Ставропольский край');
        assert.equal(await page.locator('body').evaluate(body => body.scrollWidth <= window.innerWidth), true, 'No mobile horizontal overflow');
        assert.deepEqual(errors, [], 'No browser JS errors');
        console.log(`Profile region browser test passed: ${browserType.name()}`);
    } finally {
        await browser.close();
    }
}
(async () => { await run(chromium); if (process.env.SUBSCRIPTIONS_BROWSER !== 'chromium') await run(webkit); })().catch(error => { console.error(error); process.exitCode = 1; });
