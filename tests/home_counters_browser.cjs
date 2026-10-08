// Real homepage markup and both existing CSS layers, without app/database/network access.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const path = require('node:path'), assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..'), php = process.env.PHP_BIN || 'php';
let checks = 0;
const check = (ok, label) => { checks++; assert.ok(ok, label); };
(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        for (const locale of ['ru', 'en', 'de', 'zh-cn']) {
            const home = execFileSync(php, [path.join(__dirname, 'fixtures/post_favorites.php'), locale, 'home', 'theme'], { encoding: 'utf8' });
            const stats = home.match(/<section class="home-stats"[\s\S]*?<\/section>/)?.[0];
            check(!!stats, 'Real homepage stats section');
            const html = `<!doctype html><html lang="${locale}" data-bs-theme="dark"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/theme-assets/css/theme.min.css"><link rel="stylesheet" href="/theme-assets/css/style.css"><link rel="stylesheet" href="/theme-assets/css/home.css"></head><body><main class="home-page--apple">${stats}</main></body></html>`;
            const page = await browser.newPage({ reducedMotion: 'reduce' });
            await page.route('**/*', route => {
                const url = new URL(route.request().url());
                if (url.origin !== 'https://counters.test') return route.abort();
                if (url.pathname.startsWith('/theme-assets/')) return route.fulfill({ path: path.join(root, 'themes/default/assets', url.pathname.slice('/theme-assets/'.length)) });
                return route.fulfill({ body: html, contentType: 'text/html' });
            });
            await page.goto('https://counters.test/'); await page.evaluate(() => document.fonts.ready);
            await page.evaluate(() => {
                document.querySelectorAll('[data-home-counter]').forEach((element, i) => element.textContent = i ? '7' : '42');
            });
            for (const width of [320, 375, 390, 430, 576, 768, 1440]) for (const theme of ['light', 'dark']) {
                await page.setViewportSize({ width, height: 844 });
                await page.evaluate(theme => document.documentElement.dataset.bsTheme = theme, theme);
                const result = await page.evaluate(() => {
                    const stats = [...document.querySelectorAll('.home-stat')];
                    const tops = selector => stats.map(stat => stat.querySelector(selector).getBoundingClientRect().top);
                    return { numbers: tops('strong'), labels: tops(':scope > span'), values: stats.map(stat => stat.querySelector('strong').textContent), overflow: document.documentElement.scrollWidth > innerWidth };
                });
                const at = `${locale}/${width}/${theme}`;
                check(Math.max(...result.numbers) - Math.min(...result.numbers) < .1, 'Numbers aligned despite wrapped labels: ' + at);
                check(Math.max(...result.labels) - Math.min(...result.labels) < .1, 'Label first lines aligned: ' + at);
                check(!result.overflow && result.values.join(',') === '42,24/7,7', 'No overflow or value changes: ' + at);
            }
            if (locale === 'ru' && process.env.COUNTERS_SCREENSHOT) {
                await page.setViewportSize({ width: 390, height: 844 });
                await page.screenshot({ path: process.env.COUNTERS_SCREENSHOT });
            }
            await page.close();
        }
        console.log(`${checks} homepage counter browser checks passed`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
