// Real dashboard markup, Chart.js / bundled Apex compatibility adapter and CMS styles.
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
            for (const scriptVariant of ['public', 'theme']) {
                for (const engine of ['chart', 'apex']) {
                    const context = await browser.newContext({ viewport: { width: 1996, height: 1248 } });
                    const page = await context.newPage();
                    const errors = [];
                    page.on('pageerror', error => errors.push(error.message));
                    let html = execFileSync(php, [path.join(__dirname, 'fixtures/dashboard_analytics.php'), locale], { encoding: 'utf8' });
                    await page.route('https://dashboard.test/**', async route => {
                        const url = new URL(route.request().url());
                        if (url.pathname.startsWith('/assets/')) {
                            const file = url.pathname.endsWith('/admin-analytics.js') && scriptVariant === 'theme'
                                ? path.join(root, 'themes/default/assets/js/admin-analytics.js') : path.join(root, 'public', url.pathname);
                            return route.fulfill({ path: file, contentType: url.pathname.endsWith('.css') ? 'text/css' : url.pathname.endsWith('.js') ? 'application/javascript' : 'font/woff2' });
                        }
                        return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html lang="${locale}" data-bs-theme="dark"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
                            <link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css"><link rel="stylesheet" href="/assets/default/css/admin-ui.css"></head>
                            <body class="fb-admin-body">${html}<script src="/assets/default/bootstrap/js/bootstrap.bundle.min.js"></script>
                            <script src="/assets/default/vendor/chart.js/chart.umd.js"></script>
                            ${engine === 'apex' ? '<script src="/assets/default/vendor/apexcharts/apexcharts.min.js"></script>' : ''}
                            ${engine === 'apex' ? '<script>window.qaCharts=[]; window.ApexCharts=class extends ApexCharts { constructor(element, options) { super(element, options); window.qaCharts.push(this); } };</script>' : ''}
                            <script src="/assets/default/js/admin-analytics.js"></script></body></html>` });
                    });
                    await page.goto('https://dashboard.test/admin');
                    await page.waitForFunction(() => document.querySelectorAll('.admin-analytics-legend button').length === 24);
                    await page.evaluate(() => document.fonts.ready);
                    for (const width of [1996, 1440, 1024, 768, 390, 320]) {
                        await page.setViewportSize({ width, height: 1248 });
                        for (const theme of ['dark', 'light']) {
                            await page.evaluate(theme => document.documentElement.dataset.bsTheme = theme, theme);
                            await page.waitForFunction(() => document.querySelectorAll('.admin-analytics-legend button').length === 24);
                            await page.waitForTimeout(150); // Let the chart provider resize its real canvas/SVG.
                            const metrics = await page.locator('[data-admin-analytics]').evaluate(root => {
                                const summary = root.querySelector('.fb-dashboard-chart-summary').getBoundingClientRect();
                                const plot = root.querySelector('[data-analytics-chart="traffic"]').getBoundingClientRect();
                                const popular = root.querySelector('.fb-dashboard-popular-pages').getBoundingClientRect();
                                const latest = root.querySelector('.fb-dashboard-latest-visits').getBoundingClientRect();
                                const legends = [...root.querySelectorAll('.admin-analytics-legend')];
                                const traffic = root.querySelector('.fb-dashboard-traffic-card').getBoundingClientRect();
                                const quickActions = root.querySelector('.fb-dashboard-quick-actions-card').getBoundingClientRect();
                                const circles = [...root.querySelectorAll('.fb-dashboard-chart-card')].map(card => card.getBoundingClientRect());
                                return {
                                    summaryBeforePlot: summary.bottom + 10 <= plot.top,
                                    equalTables: Math.abs(popular.width - latest.width) < 1,
                                    sameRow: Math.abs(popular.top - latest.top) < 1,
                                    stacked: popular.bottom <= latest.top,
                                    legendsInside: legends.every(legend => {
                                        const body = legend.closest('.fb-card-body').getBoundingClientRect();
                                        return [...legend.querySelectorAll('button')].every(button => button.getBoundingClientRect().bottom <= body.bottom - 10);
                                    }),
                                    circlePlotsInside: [...root.querySelectorAll('.admin-analytics-plot')].every(plot => {
                                        const draw = plot.querySelector('canvas, .apexcharts-canvas');
                                        return !draw || draw.getBoundingClientRect().bottom <= plot.getBoundingClientRect().bottom + 1;
                                    }),
                                    noOverflow: document.documentElement.scrollWidth <= innerWidth,
                                    legendCount: legends.length,
                                    legendDirection: legends.every(legend => getComputedStyle(legend).flexDirection === 'row'),
                                    equalCircleCards: circles.every(card => Math.abs(card.width - circles[0].width) < 1 && Math.abs(card.height - circles[0].height) < 1),
                                    circlesSameRow: circles.every(card => Math.abs(card.top - circles[0].top) < 1),
                                    circlesStacked: circles.slice(1).every((card, index) => card.top >= circles[index].bottom),
                                    desktopCardOrder: Math.abs(traffic.top - quickActions.top) < 1
                                        && circles.every(card => card.top >= traffic.bottom)
                                        && circles.every(card => Math.abs(card.top - circles[0].top) < 1)
                                        && popular.top >= Math.max(...circles.map(card => card.bottom)),
                                };
                            });
                            const at = `${locale}/${scriptVariant}/${engine}/${width}/${theme}`;
                            check(metrics.summaryBeforePlot, 'Visit total is outside the plot: ' + at);
                            check(metrics.equalTables && (width >= 768 ? metrics.sameRow : metrics.stacked), 'Tables use equal halves / mobile stack: ' + at);
                            check(metrics.legendsInside && metrics.legendCount === 3, 'All circular legends fit without clipping: ' + at);
                            check(metrics.circlePlotsInside, 'Actual chart surface fits its container: ' + at);
                            check(metrics.noOverflow, 'No document horizontal overflow: ' + at);
                            check(metrics.legendDirection, 'Legend items wrap horizontally in the CMS theme: ' + at);
                            check(width >= 768 ? metrics.equalCircleCards && metrics.circlesSameRow : metrics.circlesStacked, 'Three equal chart cards in one row / mobile stack: ' + at);
                            if (width >= 1440) check(metrics.desktopCardOrder, 'Original desktop card arrangement is preserved: ' + at);
                        }
                    }
                    const devices = page.locator('[data-analytics-chart="devices"]');
                    const legendButton = devices.locator('.admin-analytics-legend button').first();
                    await legendButton.click();
                    check(await legendButton.getAttribute('aria-pressed') === 'false', 'Legend can hide a series');
                    check(await devices.locator('canvas').evaluate(canvas => !Chart.getChart(canvas).getDataVisibility(0)), 'Actual native chart series is hidden');
                    await legendButton.focus();
                    await page.keyboard.press('Enter');
                    check(await legendButton.getAttribute('aria-pressed') === 'true', 'Keyboard restores a series');
                    await page.locator('[data-analytics-range-toggle]').click();
                    await page.locator('[data-analytics-range="30"]').click();
                    check(await page.locator('[data-admin-analytics]').getAttribute('data-analytics-active-range') === '30', 'Period selection still works');
                    check((await page.locator('[data-analytics-range-total]').textContent()).trim() !== '0', 'Period total is still calculated');
                    check(errors.length === 0, 'No chart errors: ' + JSON.stringify(errors));
                    if (process.env.DASHBOARD_ANALYTICS_SCREENSHOT && locale === 'ru' && scriptVariant === 'public' && engine === 'chart') {
                        await page.setViewportSize({ width: 1996, height: 1248 });
                        await page.evaluate(() => document.documentElement.dataset.bsTheme = 'dark');
                        await page.waitForTimeout(1500); // Capture the completed chart animation, not a partial pie.
                        await page.locator('[data-admin-analytics]').screenshot({ path: process.env.DASHBOARD_ANALYTICS_SCREENSHOT });
                    }
                    if (scriptVariant === 'public' && engine === 'chart') {
                        html = execFileSync(php, [path.join(__dirname, 'fixtures/dashboard_analytics.php'), locale, 'empty'], { encoding: 'utf8' });
                        await page.goto('https://dashboard.test/admin?empty=1');
                        for (const width of [1996, 1440, 768, 320]) {
                            await page.setViewportSize({ width, height: 1248 });
                            for (const theme of ['dark', 'light']) {
                                await page.evaluate(theme => document.documentElement.dataset.bsTheme = theme, theme);
                                const metrics = await page.locator('.fb-dashboard-analytics-charts-row').evaluate(row => {
                                    const cards = [...row.querySelectorAll('.fb-dashboard-chart-card')].map(card => card.getBoundingClientRect());
                                    return {
                                        equal: cards.every(card => Math.abs(card.width - cards[0].width) < 1 && Math.abs(card.height - cards[0].height) < 1 && Math.abs(card.top - cards[0].top) < 1),
                                        stacked: cards.slice(1).every((card, index) => card.top >= cards[index].bottom),
                                        placeholders: [...row.querySelectorAll('[data-analytics-chart]')].every(target => target.textContent.trim() && !target.querySelector('canvas, .admin-analytics-legend')),
                                        noOverflow: document.documentElement.scrollWidth <= innerWidth,
                                    };
                                });
                                const at = `${locale}/empty/${width}/${theme}`;
                                check(width >= 768 ? metrics.equal : metrics.stacked, 'Empty chart cards retain equal sizes / mobile stack: ' + at);
                                check(metrics.placeholders, 'Empty state does not contain an orphan chart / legend: ' + at);
                                check(metrics.noOverflow, 'Empty state has no document overflow: ' + at);
                            }
                        }
                    }
                    await context.close();
                }
            }
        }
        console.log(`Dashboard analytics browser checks passed: ${checks}`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
