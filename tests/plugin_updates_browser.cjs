'use strict';
const {chromium} = require(process.env.FIREBALL_PLAYWRIGHT_MODULE || 'playwright');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.join(__dirname, '..');
const fixture = (locale, scenario) => execFileSync(process.env.FIREBALL_PHP || 'php', [path.join(__dirname, 'plugin_updates_fixture.php'), locale, scenario], {encoding: 'utf8'});

(async () => {
    const browser = await chromium.launch({headless: true, ...(process.env.FIREBALL_BROWSER_EXECUTABLE ? {executablePath: process.env.FIREBALL_BROWSER_EXECUTABLE} : {})});
    const page = await browser.newPage();
    const pageErrors = [];
    page.on('pageerror', error => pageErrors.push(error.message));
    try {
        for (const locale of ['ru', 'en', 'de', 'zh-cn']) {
            for (const scenario of ['available', 'none', 'nonadmin']) {
                for (const width of [390, 1440]) {
                    await page.setViewportSize({width, height: 844});
                    await page.setContent(fixture(locale, scenario));
                    await page.addStyleTag({path: path.join(root, 'public/assets/default/css/theme.min.css')});
                    assert.equal(await page.locator('[data-plugin-update-all]').count(), scenario === 'available' ? 1 : 0);
                    if (scenario === 'available') {
                        const form = page.locator('[data-plugin-update-all]');
                        const data = JSON.parse(await form.getAttribute('data-plugins'));
                        assert.deepEqual(data.map(plugin => plugin.slug), ['one', 'two', 'three']);
                        assert.equal(data[0].name, 'Plugin "one" <safe> & тест');
                        assert.equal(await form.locator('input[name="csrf"]').count(), 1);
                        assert.ok((await form.getAttribute('data-delete-confirm-label')).length > 0);
                        assert.ok(!/admin_plugin_updates_/.test(await form.textContent()));
                        const button = await form.locator('button').boundingBox();
                        assert.ok(button.x >= 0 && button.x + button.width <= width && button.height >= 40, 'Button fits mobile/desktop and has touch area');
                    }
                }
            }
        }
        for (const failure of ['none', 'package', 'csrf', 'network', 'html', 'redirect']) {
            await page.setContent(fixture('en', 'available'));
            await page.evaluate(failure => {
                window.calls = [];
                window.active = 0;
                window.maxActive = 0;
                window.fetch = async (_, options) => {
                    window.calls.push({slug: options.body.get('slug'), csrf: options.body.get('csrf'), ajax: options.headers['X-Requested-With']});
                    window.maxActive = Math.max(window.maxActive, ++window.active);
                    await new Promise(resolve => setTimeout(resolve, 20));
                    window.active--;
                    const second = window.calls.length === 2;
                    if (second && failure === 'network') throw new Error('Offline');
                    const bad = second && ['package', 'csrf'].includes(failure);
                    return {
                        redirected: second && failure === 'redirect',
                        ok: !bad,
                        status: bad ? (failure === 'csrf' ? 419 : 500) : 200,
                        headers: new Headers({'content-type': second && failure === 'html' ? 'text/html' : 'application/json'}),
                        json: async () => bad ? {status: false, message: 'Fixture failure'} : {status: true, result: {status: window.calls.length === 3 ? 'current' : 'success'}}
                    };
                };
            }, failure);
            await page.addScriptTag({path: path.join(root, 'public/assets/default/js/plugin-updates.js')});
            // First submit must reach the shared confirmation; no update yet.
            await page.evaluate(() => {
                const form = document.querySelector('[data-plugin-update-all]');
                form.addEventListener('submit', event => event.preventDefault(), {once: true});
                form.requestSubmit();
            });
            assert.equal(await page.evaluate(() => calls.length), 0);
            await page.evaluate(() => {
                const form = document.querySelector('[data-plugin-update-all]');
                form.dataset.deleteConfirmed = '1';
                form.requestSubmit();
                form.requestSubmit(); // Double submission cannot spawn a second queue.
            });
            await page.waitForFunction(() => !document.querySelector('[data-plugin-update-all-refresh]').hidden);
            const state = await page.evaluate(() => ({calls, maxActive, text: document.querySelector('[data-plugin-update-all-summary]').textContent, errors: document.querySelector('[data-plugin-update-all-errors]').children.length}));
            assert.equal(state.maxActive, 1);
            assert.equal(state.calls.length, ['csrf', 'network', 'html', 'redirect'].includes(failure) ? 2 : 3);
            assert.ok(state.calls.every(call => call.csrf === 'test-only' && call.ajax === 'XMLHttpRequest'));
            assert.equal(state.errors, failure === 'none' ? 0 : 1);
            assert.ok(!/:updated|:failed|:skipped/.test(state.text));
        }
        assert.deepEqual(pageErrors, []);
        console.log('Plugin update-all browser tests passed: 24 locale/layout cases and 6 sequential-queue scenarios.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
