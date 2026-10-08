// Actual analytics template, shared CMS components and production AJAX controller.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BIN || 'php';
let checks = 0;
const check = (value, message) => { checks++; assert.ok(value, message); };

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        for (const locale of ['ru', 'en', 'de', 'zh-cn']) {
            // Exercise both bundled controllers and the theme fallback as well.
            const script = locale === 'en' || locale === 'de' ? 'main.js' : 'datatable.js';
            const context = await browser.newContext({ viewport: { width: 1440, height: 1100 } });
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('https://analytics.test/**', async route => {
                const url = new URL(route.request().url());
                if (url.pathname.startsWith('/assets/')) return route.fulfill({
                    path: locale === 'de' && url.pathname.endsWith('/main.js')
                        ? path.join(root, 'themes/default/assets/js/main.js') : path.join(root, 'public', url.pathname),
                    contentType: url.pathname.endsWith('.css') ? 'text/css' : url.pathname.endsWith('.js') ? 'application/javascript' : 'font/woff2',
                });
                const html = execFileSync(php, [path.join(__dirname, 'fixtures/full_analytics.php'), locale, url.search.slice(1), url.searchParams.has('empty') ? 'empty' : ''], { encoding: 'utf8' });
                return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html lang="${locale}" data-bs-theme="dark"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
                    <link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/css/style.css"><link rel="stylesheet" href="/assets/default/css/admin-ui.css"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css"></head>
                    <body class="fb-admin-body">${html}<script>const baseUrl = location.origin;</script><script src="/assets/default/js/jquery-3.7.1.min.js"></script><script src="/assets/default/bootstrap/js/bootstrap.bundle.min.js"></script><script src="/assets/default/js/${script}"></script></body></html>` });
            });
            const base = 'https://analytics.test' + (locale === 'ru' ? '' : '/' + locale) + '/admin/analytics';
            await page.goto(base);
            await page.evaluate(() => document.fonts.ready);
            const cards = page.locator('.fb-analytics-table-card');
            for (const width of [1996, 1440, 1280, 1200, 1024, 768, 390, 320]) {
                await page.setViewportSize({ width, height: 1100 });
                for (const theme of ['light', 'dark']) {
                    await page.evaluate(theme => document.documentElement.dataset.bsTheme = theme, theme);
                    const dimensions = await cards.evaluateAll(cards => {
                        const boxes = cards.map(card => card.getBoundingClientRect());
                        return {
                            equal: Math.abs(boxes[0].width - boxes[1].width) < 1,
                            row: Math.abs(boxes[0].top - boxes[1].top) < 1,
                            stack: boxes[0].bottom <= boxes[1].top,
                            equalHeight: Math.abs(boxes[0].height - boxes[1].height) < 1,
                            overflow: document.documentElement.scrollWidth > innerWidth,
                            tableOverflow: cards.some(card => { const scroll = card.querySelector('.admin-table-component__scroll'); return scroll.offsetWidth && scroll.scrollWidth > scroll.clientWidth + 1; }),
                        };
                    });
                    const at = `${locale}/${width}/${theme}`;
                    check(dimensions.equal && (width >= 1200 ? dimensions.row && dimensions.equalHeight : dimensions.stack), 'Equal desktop panels / responsive stack: ' + at);
                    check(!dimensions.overflow && !dimensions.tableOverflow, 'No horizontal page/table scrolling: ' + at);
                    check(width >= 768 ? await page.locator('.admin-analytics-table--visits-full').isVisible() : await page.locator('[data-ajax-table="analytics-visits"] .admin-mobile-table-cards').isVisible(), 'Readable desktop table / mobile cards: ' + at);
                }
            }
            await page.setViewportSize({ width: 1440, height: 1100 });
            const visits = page.locator('[data-ajax-table="analytics-visits"]');
            check(await visits.locator('thead th').count() === 3, 'Three grouped visitor columns');
            check(await visits.locator('thead a').count() === 6, 'All six sorting fields retained');
            check((await visits.locator('tbody tr').first().textContent()).includes('macOS') && (await visits.locator('tbody tr').first().textContent()).includes('Firefox'), 'Device, OS and browser retained');
            check(await page.locator('.admin-analytics-table img').count() === 0, 'Paths safely escaped');
            await page.evaluate(() => window.qaDocumentToken = 'unchanged');
            await page.locator('[data-ajax-table="analytics-pages"] .pagination a[href*="pages_page=2"]').first().click();
            await page.waitForFunction(() => new URL(location.href).searchParams.get('pages_page') === '2');
            check(await page.evaluate(() => window.qaDocumentToken) === 'unchanged', 'Pagination updates tables without page reload');
            await visits.locator('.pagination a[href*="visits_page=2"]').first().click();
            await page.waitForFunction(() => new URL(location.href).searchParams.get('visits_page') === '2');
            check(new URL(page.url()).searchParams.get('pages_page') === '2', 'Independent table pagination preserved');
            for (const field of ['created_at', 'country', 'device', 'browser', 'source', 'page']) {
                await visits.locator(`thead a[href*="visits_sort=${field}"]`).click();
                await page.waitForFunction(field => new URL(location.href).searchParams.get('visits_sort') === field, field);
                check(new URL(page.url()).searchParams.get('pages_page') === '2' && new URL(page.url()).searchParams.get('visits_page') === '1', 'Sorting resets only visitor page: ' + field);
            }
            await page.locator('#analytics-country').selectOption('Netherlands');
            await page.locator('[data-admin-table-form] button[type="submit"]').click();
            await page.waitForFunction(() => new URL(location.href).searchParams.get('country') === 'Netherlands');
            check((await visits.locator('.fb-analytics-table-total').textContent()).includes('63'), 'Filtered visit count refreshed with AJAX');
            check(await page.locator('[name="pages_page"]').inputValue() === '1' && await page.locator('[name="visits_page"]').inputValue() === '1', 'Filter resets both pagination controls');
            check(await page.evaluate(() => window.qaDocumentToken) === 'unchanged', 'Sorting and filtering keep document alive');
            if (process.env.FULL_ANALYTICS_SCREENSHOT && locale === 'ru') {
                await page.goto(base);
                await page.screenshot({ path: process.env.FULL_ANALYTICS_SCREENSHOT, fullPage: true });
            }
            await page.goto(base + '?empty=1');
            for (const width of [1440, 390]) {
                await page.setViewportSize({ width, height: 1100 });
                check(await cards.count() === 2 && await cards.locator('.fb-analytics-table-total').count() === 2, 'Empty state keeps both panels');
                check(await page.locator('.admin-table-footer').count() === 0, 'Empty state has no stale pagination');
            }
            check(errors.length === 0, 'No JavaScript errors: ' + JSON.stringify(errors));
            await context.close();
        }
        console.log(`Full analytics browser checks passed: ${checks}`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
