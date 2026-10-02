// Real recovery template and script; all panel/queue responses are isolated fixtures.
const assert = require('node:assert/strict');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.FIREBALL_PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '../../..');
const php = process.env.FIREBALL_PHP || 'php';
const fixture = unchecked => execFileSync(php, [path.join(__dirname, 'server_recovery_fixture.php'), ...(unchecked ? ['--unchecked'] : [])], {encoding: 'utf8'});
(async () => {
    const browser = await chromium.launch({headless: true});
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        let unchecked = true;
        let rejectApply = false;
        let rejectRetry = false;
        let requests = [];
        await page.route('https://vpn.test/**', async route => {
            const url = new URL(route.request().url());
            if (url.pathname.startsWith('/assets/')) return route.fulfill({path: path.join(root, 'public', url.pathname)});
            if (url.pathname.endsWith('/apply')) return route.fulfill({status: rejectApply ? 422 : 200, contentType: 'application/json', body: JSON.stringify(rejectApply ? {error: 'Проверьте панель'} : {operation_ids: ['one', 'two']})});
            if (url.pathname.endsWith('/process')) {
                const body = route.request().postData();
                const id = /name="operation_id"\r\n\r\n([^\r]+)/.exec(body)[1];
                const retry = body.includes('name="retry"\r\n\r\n1');
                assert(body.includes('name="needCSRFToken"\r\n\r\nfixture'), 'CSRF must accompany every mutation');
                requests.push({id, retry});
                if (rejectRetry) return route.fulfill({status: 502, contentType: 'application/json', body: JSON.stringify({error: 'Связь прервана'})});
                const failed = id === 'two' && !retry;
                return route.fulfill({contentType: 'application/json', body: JSON.stringify({subscription_id: id === 'one' ? 11 : 12, status: failed ? 'retry' : 'completed', status_label: failed ? 'Требует повтора' : 'Выполнено', last_error: failed ? 'Панель недоступна' : ''})});
            }
            return route.fulfill({contentType: 'text/html', body: fixture(unchecked)});
        });
        for (const width of [1440, 390]) {
            await page.setViewportSize({width, height: 1000});
            unchecked = true;
            await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2/servers/1/recovery');
            await page.addScriptTag({path: path.join(root, 'plugins/vpn-manager-v2/assets/vpn-manager-v2.js')});
            const apply = page.locator('[data-vpn-recovery-apply] button');
            assert(await apply.isDisabled(), 'Restoration cannot start before panel inspection');
            assert(await page.locator('select').isDisabled());
            unchecked = false;
            await page.reload();
            await page.addScriptTag({path: path.join(root, 'plugins/vpn-manager-v2/assets/vpn-manager-v2.js')});
            assert.equal(await page.locator('select').inputValue(), '20', 'Unique matching replacement is suggested');
            rejectApply = true;
            await apply.click();
            await page.waitForFunction(() => document.querySelector('[data-vpn-recovery-progress]').textContent === 'Проверьте панель');
            assert(await apply.isEnabled(), 'An unsuccessful request remains recoverable');
            rejectApply = false; requests = [];
            await apply.click();
            await page.waitForFunction(() => document.querySelector('[data-vpn-recovery-progress]').textContent.includes('Готово: 1.'));
            assert.deepEqual(requests, [{id: 'one', retry: false}, {id: 'two', retry: false}], 'Each subscription is processed sequentially');
            for (const text of await page.locator('[data-vpn-recovery-status="12"]').allTextContents()) assert.equal(text, 'Требует повтора', 'Desktop and mobile results both update');
            const retry = page.locator('[data-vpn-recovery-resume][data-retry="1"] button');
            assert(await retry.isEnabled());
            rejectRetry = true;
            await retry.click();
            await page.waitForFunction(() => document.querySelector('[data-vpn-recovery-progress]').textContent.includes('Готово: 0.'));
            assert(await retry.isEnabled(), 'Network failure must not disable retry permanently');
            rejectRetry = false; requests = [];
            await retry.click();
            await page.waitForFunction(() => document.querySelector('[data-vpn-recovery-progress]').textContent.includes('Готово: 1. Требуют внимания: 0.'));
            assert.deepEqual(requests, [{id: 'two', retry: true}], 'Retry only failed subscriptions');
            for (const text of await page.locator('[data-vpn-recovery-status="12"]').allTextContents()) assert.equal(text, 'Выполнено');
            assert(await retry.isDisabled());
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Workflow fits the viewport');
            await page.screenshot({path: '/private/tmp/vpn-recovery-' + width + '-qa.png', fullPage: true});
        }
        assert.deepEqual(errors, []);
        console.log('PASS recovery UI at 1440/390px: ordered steps, inspection guard, sequential processing, partial result, scoped retries, network recovery and mobile status updates');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
