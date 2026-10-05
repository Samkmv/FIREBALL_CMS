// One actual standalone template through both entry points, no application DB/bootstrap.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BIN || 'php';
let checks = 0;
const check = (condition, message) => { checks++; assert.ok(condition, message); };

(async () => {
    const fixtureRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'fireball-maintenance-'));
    const browser = await chromium.launch({ headless: true });
    try {
        fs.mkdirSync(path.join(fixtureRoot, 'public'));
        fs.mkdirSync(path.join(fixtureRoot, 'storage'));
        fs.mkdirSync(path.join(fixtureRoot, 'app/Views/system'), { recursive: true });
        fs.copyFileSync(path.join(root, 'public/runtime-gate.php'), path.join(fixtureRoot, 'public/runtime-gate.php'));
        fs.copyFileSync(path.join(root, 'app/Views/system/update.php'), path.join(fixtureRoot, 'app/Views/system/update.php'));
        fs.writeFileSync(path.join(fixtureRoot, 'storage/update.maintenance'), JSON.stringify({ site_title: 'Fixture CMS' }));
        const fixture = path.join(__dirname, 'fixtures/update_maintenance.php');
        for (const locale of ['ru', 'en', 'de', 'zh-cn']) {
            for (const mode of ['gate', 'app']) {
                const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
                const page = await context.newPage();
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                const html = execFileSync(php, [fixture, locale, mode, fixtureRoot], { encoding: 'utf8' });
                let assetRequests = 0;
                let releaseCheck;
                let finished = false;
                const readyHtml = '<!doctype html><html><body data-ready-page>Ready</body></html>';
                await page.route('https://maintenance.test/**', async route => {
                    if (route.request().resourceType() === 'fetch') {
                        return new Promise(resolve => {
                            releaseCheck = async (status, body) => {
                                await route.fulfill({ status, contentType: 'text/html', body });
                                resolve();
                            };
                        });
                    }
                    if (route.request().resourceType() !== 'document') { assetRequests++; return route.abort(); }
                    return route.fulfill({ status: finished ? 200 : 503, contentType: 'text/html', body: finished ? readyHtml : html });
                });
                const url = 'https://maintenance.test/cms' + (locale === 'ru' ? '' : '/' + locale) + '/profile/settings';
                await page.goto(url);
                for (const theme of ['dark', 'light']) {
                    await page.evaluate(theme => document.documentElement.dataset.bsTheme = theme, theme);
                    for (const width of [320, 390, 768, 1440]) {
                        await page.setViewportSize({ width, height: 900 });
                        for (const motion of ['no-preference', 'reduce']) {
                            await page.emulateMedia({ reducedMotion: motion });
                            const metrics = await page.evaluate(() => {
                                const loader = document.querySelector('.update-loader');
                                const symbol = document.querySelector('.update-loader__symbol');
                                const icon = symbol.querySelector('.ci-refresh-cw');
                                const button = document.querySelector('[data-update-refresh]');
                                const bounds = symbol.getBoundingClientRect();
                                const loaderBounds = loader.getBoundingClientRect();
                                const card = document.querySelector('.update-card').getBoundingClientRect();
                                const buttonBounds = button.getBoundingClientRect();
                                return {
                                    brandPlain: document.querySelectorAll('.update-brand i, .update-brand svg, .update-brand img').length === 0,
                                    icons: document.querySelectorAll('.ci-refresh-cw').length,
                                    centered: Math.abs((bounds.left + bounds.width / 2) - (loaderBounds.left + loaderBounds.width / 2)) < 1
                                        && Math.abs((bounds.top + bounds.height / 2) - (loaderBounds.top + loaderBounds.height / 2)) < 1,
                                    spinner: getComputedStyle(icon).animationName,
                                    buttonAnimation: getComputedStyle(button.querySelector('.ci-refresh-cw')).animationName,
                                    ringAnimation: getComputedStyle(loader, '::before').animationName,
                                    noOverflow: document.documentElement.scrollWidth <= innerWidth,
                                    buttonFits: buttonBounds.bottom <= card.bottom - 20 && buttonBounds.height >= 44,
                                    language: document.documentElement.lang,
                                };
                            });
                            const at = `${mode}/${locale}/${theme}/${width}/${motion}`;
                            check(metrics.brandPlain, 'No icon above the card: ' + at);
                            check(metrics.icons === 2, 'Both refresh symbols use ci-refresh-cw: ' + at);
                            check(metrics.centered, 'Spinner background stays centered: ' + at);
                            check(metrics.spinner === (motion === 'reduce' ? 'none' : 'update-spin') && metrics.buttonAnimation === 'none' && metrics.ringAnimation === 'none', 'Only center icon rotates / reduced motion respected: ' + at);
                            check(metrics.noOverflow && metrics.buttonFits, 'Mobile/desktop geometry and touch area: ' + at);
                            check(metrics.language === locale, 'Language survives both render paths: ' + at);
                        }
                    }
                }
                if (process.env.MAINTENANCE_SCREENSHOT && locale === 'ru' && mode === 'gate') {
                    await page.setViewportSize({ width: 390, height: 844 });
                    await page.evaluate(() => document.documentElement.dataset.bsTheme = 'dark');
                    await page.screenshot({ path: process.env.MAINTENANCE_SCREENSHOT });
                }
                await page.locator('[data-update-refresh]').click();
                await page.waitForFunction(() => document.querySelector('[data-update-refresh]').getAttribute('aria-busy') === 'true');
                check(await page.locator('[data-update-refresh] .ci-refresh-cw').evaluate(icon => getComputedStyle(icon).animationName === 'none'), 'Button remains static during a real check');
                await releaseCheck(500, '<html><body>Temporary restart</body></html>');
                await page.waitForFunction(() => !document.querySelector('[data-update-refresh]').hasAttribute('aria-busy'));
                check(await page.locator('.update-card').count() === 1, 'Temporary 5xx cannot falsely complete the update');
                check(await page.locator('[data-update-refresh]').getAttribute('aria-disabled') === null, 'Manual check can be retried');
                finished = true;
                await page.locator('[data-update-refresh]').click();
                await page.waitForFunction(() => document.querySelector('[data-update-refresh]').getAttribute('aria-busy') === 'true');
                await releaseCheck(200, readyHtml);
                await page.waitForSelector('[data-ready-page]');
                check(true, 'Successful completed page triggers reload');
                check(assetRequests === 0, 'Protective template renders without external fonts/scripts/assets');
                check(errors.length === 0, 'No maintenance JS errors: ' + JSON.stringify(errors));
                await context.close();
            }
        }
        fs.unlinkSync(path.join(fixtureRoot, 'app/Views/system/update.php'));
        const fallback = execFileSync(php, [fixture, 'ru', 'gate', fixtureRoot], { encoding: 'utf8' });
        check(fallback.includes('data-update-maintenance-page="1"') && fallback.includes('http-equiv="refresh"'), 'Missing shared file still fails closed and retries');
        console.log(`Update maintenance browser checks passed: ${checks}`);
    } finally {
        await browser.close();
        fs.rmSync(fixtureRoot, { recursive: true, force: true });
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
