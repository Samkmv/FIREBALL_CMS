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
                    assert.equal(await page.locator('[data-plugin-update-all-refresh]').count(), 0);
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
            const runtimePage = await browser.newPage();
            runtimePage.on('pageerror', error => pageErrors.push(error.message));
            const calls = [];
            let documents = 0, active = 0, maxActive = 0;
            const expectedCalls = ['csrf', 'network', 'html', 'redirect'].includes(failure) ? 2 : 3;
            await runtimePage.route('https://plugins.test/**', async route => {
                const request = route.request();
                const url = new URL(request.url());
                if (url.pathname === '/plugin-updates.js') {
                    return route.fulfill({path: path.join(root, 'public/assets/default/js/plugin-updates.js'), contentType: 'application/javascript'});
                }
                if (request.method() === 'POST') {
                    const body = request.postData() || '';
                    const field = name => body.match(new RegExp('name="' + name + '"\\r\\n\\r\\n([^\\r\\n]+)'))?.[1];
                    calls.push({slug: field('slug'), csrf: field('csrf'), ajax: request.headers()['x-requested-with']});
                    maxActive = Math.max(maxActive, ++active);
                    await new Promise(resolve => setTimeout(resolve, 20));
                    active--;
                    const second = calls.length === 2;
                    if (second && failure === 'network') return route.abort('failed');
                    if (second && failure === 'redirect') return route.fulfill({status: 302, headers: {location: '/fixture-login'}});
                    if (second && failure === 'html') return route.fulfill({contentType: 'text/html', body: 'Session unavailable'});
                    const bad = second && ['package', 'csrf'].includes(failure);
                    return route.fulfill({
                        status: bad ? (failure === 'csrf' ? 419 : 500) : 200,
                        contentType: 'application/json',
                        body: JSON.stringify(bad ? {status: false, message: 'Fixture failure'}
                            : {status: true, result: {status: calls.length === 3 ? 'current' : 'success'}})
                    });
                }
                if (url.pathname === '/fixture-login') return route.fulfill({contentType: 'text/html', body: 'Login fixture'});
                documents++;
                if (documents > 1) {
                    assert.equal(active, 0, 'No refresh while an update request is pending');
                    assert.equal(calls.length, expectedCalls, 'Queue finishes or stops before refreshing');
                }
                // The new list has no update-all form: restoring errors must
                // still work and must never submit updates a second time.
                const html = fixture('en', documents === 1 ? 'available' : 'none')
                    .replace('<body>', documents === 1 ? '<body>' : '<body data-update-list-refreshed>')
                    .replace('</body>', '<script src="/plugin-updates.js"></script></body>');
                return route.fulfill({contentType: 'text/html', body: html});
            });
            await runtimePage.goto('https://plugins.test/admin/plugins');
            await runtimePage.evaluate(() => {
                const form = document.querySelector('[data-plugin-update-all]');
                form.addEventListener('submit', event => event.preventDefault(), {once: true});
                form.requestSubmit();
            });
            assert.equal(calls.length, 0, 'Unconfirmed submission never starts an update');
            await runtimePage.evaluate(() => {
                const form = document.querySelector('[data-plugin-update-all]');
                form.dataset.deleteConfirmed = '1';
                form.requestSubmit();
                form.requestSubmit();
            });
            await runtimePage.waitForFunction(() => document.body.hasAttribute('data-update-list-refreshed'));
            await runtimePage.waitForLoadState('load');
            assert.equal(documents, 2, 'One automatic reload');
            assert.equal(maxActive, 1);
            assert.equal(calls.length, expectedCalls);
            assert.ok(calls.every(call => call.csrf === 'test-only' && call.ajax === 'XMLHttpRequest'));
            const state = await runtimePage.evaluate(() => ({
                text: document.querySelector('[data-plugin-update-all-summary]').textContent,
                errors: document.querySelector('[data-plugin-update-all-errors]').children.length,
                hidden: document.querySelector('[data-plugin-update-all-status]').hidden,
                stored: sessionStorage.getItem('fireball.plugin-updates.result:/admin/plugins')
            }));
            assert.equal(state.errors, failure === 'none' ? 0 : 1, 'Failures survive automatic reload');
            assert.equal(state.hidden, failure === 'none', 'Successful updates leave a clean list');
            assert.equal(state.stored, null, 'Result consumed once');
            assert.ok(!/:updated|:failed|:skipped/.test(state.text));
            assert.equal(await runtimePage.locator('[data-plugin-update-all-refresh]').count(), 0);
            // A later manual reload cannot resurrect errors or restart updates.
            await runtimePage.reload();
            assert.equal(calls.length, expectedCalls);
            assert.equal(await runtimePage.locator('[data-plugin-update-all-errors] li').count(), 0);
            await runtimePage.close();
        }
        assert.deepEqual(pageErrors, []);
        console.log('Plugin update-all browser tests passed: 24 locale/layout cases and 6 sequential-queue and automatic-refresh scenarios.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
