// Isolated rendering: local CLI fixtures and CMS styles; no account, database or payment provider.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');

(async () => {
    const browser = await chromium.launch({ headless: true, ...(process.env.SUBSCRIPTIONS_BROWSER_EXECUTABLE ? { executablePath: process.env.SUBSCRIPTIONS_BROWSER_EXECUTABLE } : {}) });
    let checks = 0;
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        for (const width of [360, 768, 1280]) {
            await page.setViewportSize({ width, height: 900 });
            for (const theme of ['light', 'dark']) {
                for (const scenario of ['account', 'account-recurring', 'account-utility', 'account-grace', 'account-long']) {
                    const html = execFileSync('php', [path.join(__dirname, 'fixtures/payment-admin.php'), scenario, 'ru', theme], { encoding: 'utf8' });
                    await page.setContent(html);
                    for (const asset of ['public/assets/default/css/theme.min.css', 'public/assets/default/css/style.css', 'public/assets/default/css/admin-ui.css', 'plugins/subscriptions/assets/subscriptions.css']) {
                        await page.addStyleTag({ path: path.join(root, asset) });
                    }
                    const context = `${width}/${theme}/${scenario}`;
                    assert(await page.locator('body').evaluate(body => body.scrollWidth <= innerWidth), `${context}: no horizontal overflow`);
                    const layout = await page.locator('.subscriptions-account-card__layout').evaluate(el => ({ columns: getComputedStyle(el).gridTemplateColumns.split(' ').length, width: el.clientWidth }));
                    assert.equal(layout.columns, width < 768 ? 1 : 2, `${context}: responsive card columns`);
                    assert(await page.getByRole('link', { name: 'Данные для подписки', exact: true }).isVisible(), `${context}: profile action is visible`);
                    const badge = await page.locator('.subscriptions-account-card__status').boundingBox();
                    assert(badge.height >= 30, `${context}: status remains legible`);
                    for (const rect of await page.locator('.subscriptions-account-card__actions .btn').evaluateAll(els => els.map(el => { const r = el.getBoundingClientRect(); return { left: r.left, right: r.right, height: r.height }; }))) {
                        assert(rect.left >= 0 && rect.right <= width + 1 && rect.height >= 44, `${context}: action stays on screen and is touch-sized`);
                    }
                    checks++;
                }
            }
        }
        assert.deepEqual(errors, [], 'No page errors');
        console.log(`Account browser tests passed: ${checks} layout scenarios.`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
