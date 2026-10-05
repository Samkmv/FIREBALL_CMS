// Actual profile templates/styles, no working database or Push endpoints.
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
            for (const fallback of [false, true]) {
                const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
                const page = await context.newPage();
                const prefix = locale === 'ru' ? '' : '/' + locale;
                const html = execFileSync(php, [path.join(__dirname, 'fixtures/profile.php'), locale, 'overview', fallback ? 'fallback' : 'theme'], { encoding: 'utf8' });
                await page.route('https://registration.test/**', async route => {
                    const url = new URL(route.request().url());
                    if (url.pathname.startsWith('/assets/')) {
                        return route.fulfill({ path: path.join(root, 'public', url.pathname), contentType: url.pathname.endsWith('.css') ? 'text/css' : 'font/woff2' });
                    }
                    return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html lang="${locale}" data-bs-theme="dark"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
                        <link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css">
                        <link rel="stylesheet" href="/assets/default/css/profile.css"><link rel="stylesheet" href="/assets/default/css/style.css"></head><body>${html}</body></html>` });
                });
                await page.goto('https://registration.test' + prefix + '/profile');
                await page.evaluate(() => document.fonts.ready);
                for (const width of [320, 360, 390, 575, 576, 767, 768, 820, 991, 992, 1024, 1440]) {
                    await page.setViewportSize({ width, height: 900 });
                    for (const theme of ['light', 'dark']) {
                        await page.evaluate(theme => document.documentElement.dataset.bsTheme = theme, theme);
                        const metrics = await page.locator('.profile-meta-registration').evaluate(row => {
                            const dt = row.querySelector('dt');
                            const dd = row.querySelector('dd');
                            const label = row.querySelector('.profile-meta-label');
                            const a = dt.getBoundingClientRect(), b = dd.getBoundingClientRect(), r = row.getBoundingClientRect();
                            return {
                                centers: Math.abs(a.top + a.height / 2 - b.top - b.height / 2),
                                lineHeight: parseFloat(getComputedStyle(dd).lineHeight), height: b.height,
                                value: dd.textContent.trim(), right: b.right, rowRight: r.right, gap: b.left - a.right,
                                labelFits: label.scrollWidth <= label.clientWidth + 1,
                                labelWidth: label.clientWidth, labelScroll: label.scrollWidth,
                                rowWidth: r.width, valueWidth: b.width, font: getComputedStyle(dd).fontSize,
                                overflow: document.documentElement.scrollWidth > innerWidth,
                                fullLabel: dt.title,
                            };
                        });
                        const at = `${locale}/${fallback ? 'fallback' : 'theme'}/${width}/${theme}`;
                        check(metrics.centers < 1 && metrics.height <= metrics.lineHeight + 1, 'One aligned date row: ' + at);
                        check(metrics.value === '01.01.2026 00:00', 'Full date and time retained: ' + at);
                        check(metrics.right <= metrics.rowRight + 1 && metrics.gap >= 5, 'No clipping/overlap of value: ' + at);
                        check(metrics.labelFits, 'Compact translated label fully fits: ' + at + ' ' + JSON.stringify(metrics));
                        check(!metrics.overflow, 'No page overflow: ' + at);
                        check(metrics.fullLabel && !metrics.fullLabel.startsWith('auth_profile_'), 'Accessible full label retained: ' + at);
                    }
                }
                if (process.env.PROFILE_REGISTRATION_SCREENSHOT && locale === 'ru' && !fallback) {
                    await page.setViewportSize({ width: 390, height: 844 });
                    await page.locator('.profile-sidebar').screenshot({ path: process.env.PROFILE_REGISTRATION_SCREENSHOT });
                }
                await context.close();
            }
        }
        console.log(`Profile registration browser checks passed: ${checks}`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
