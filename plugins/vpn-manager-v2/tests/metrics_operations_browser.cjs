// Isolated real-template/real-script regression checks. No live CMS or panel requests.
const assert = require('node:assert/strict');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.FIREBALL_PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '../../..');
const php = process.env.FIREBALL_PHP || 'php';
const fixture = (...args) => execFileSync(php, [path.join(__dirname, 'metrics_operations_unit.php'), ...args], {encoding: 'utf8'});
const metrics = {
    server: {status: 'online'}, checked_at: '2026-10-01 23:19:45',
    cpu: {percent: 8.2, cores: 1, speed_mhz: 3394},
    memory: {percent: 30.9, current: 638582784, total: 2061584302},
    swap: {percent: null, current: null, total: null},
    disk: {percent: 28.3, current: 5948529704, total: 21045339750},
    uptime_seconds: 475980, load: {one: 0.06, five: 0.09, fifteen: 0.11},
    network: {up: 740352, down: 1384120}, connections: {tcp: 1895, udp: 80},
    xray: {state: 'running', version: '26.9.9'}
};
(async () => {
    const browser = await chromium.launch({headless: true});
    try {
        const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        let responses = ['success', 'offline'];
        let release;
        let pending = new Promise(resolve => { release = resolve; });
        let requests = [];
        await page.route('https://vpn.test/**', async route => {
            const url = new URL(route.request().url());
            const match = url.pathname.match(/\/servers\/(\d+)\/metrics$/);
            if (match) {
                const id = Number(match[1]);
                requests.push(id);
                const mode = responses[id - 1];
                if (pending) await pending;
                if (mode === 'network') return route.abort('failed');
                if (mode === 'invalid') return route.fulfill({status: 200, contentType: 'text/html', body: '<html>Not metrics</html>'});
                return route.fulfill({status: mode === 'success' ? 200 : mode === 'forbidden' ? 403 : 502,
                    contentType: 'application/json', body: JSON.stringify(mode === 'success' ? metrics : {error: 'Не удалось подключиться к 3x-ui.'})});
            }
            if (url.pathname.startsWith('/assets/')) {
                const file = path.join(root, 'public', url.pathname);
                return route.fulfill({path: file});
            }
            const body = url.pathname.endsWith('/operations')
                ? fixture('--render', '--page=' + (url.searchParams.get('page') || 1))
                : fixture('--overview');
            return route.fulfill({contentType: 'text/html', body});
        });
        await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2');
        const cards = page.locator('[data-vpn-v2-server-metric-card]');
        const badge = index => cards.nth(index).locator('[data-vpn-v2-server-status]');
        assert.equal(await badge(0).innerText(), 'Не проверен', 'No stale online badge before request');
        await page.addScriptTag({path: path.join(root, 'plugins/vpn-manager-v2/assets/vpn-manager-v2.js')});
        assert.equal(await badge(0).innerText(), 'Не проверен');
        release(); pending = null;
        const waitForRefresh = () => page.waitForFunction(() => !document.querySelector('[data-vpn-v2-refresh-metrics]').disabled);
        await waitForRefresh();
        assert.equal(await badge(0).innerText(), 'Онлайн');
        assert.equal(await badge(1).innerText(), 'Недоступен');
        assert.equal(await badge(2).innerText(), 'Отключён');
        assert.equal(await cards.nth(0).locator('[data-vpn-v2-metric="swap-value"]').innerText(), '—', 'Null is not zero percent');
        assert.equal(await cards.nth(1).locator('[data-vpn-v2-metric="cpu-value"]').innerText(), '—');
        assert(!requests.includes(3), 'Disabled servers must not be queried');
        await page.locator('[data-vpn-v2-server-metrics]').screenshot({path: '/private/tmp/vpn-metrics-qa.png'});
        for (const modes of [['offline', 'success'], ['success', 'network'], ['success', 'invalid'], ['success', 'forbidden']]) {
            responses = modes;
            await page.locator('[data-vpn-v2-refresh-metrics]').click();
            await waitForRefresh();
            assert.equal(await badge(0).innerText(), modes[0] === 'success' ? 'Онлайн' : 'Недоступен');
            assert.equal(await badge(1).innerText(), modes[1] === 'success' ? 'Онлайн' : ['invalid', 'forbidden'].includes(modes[1]) ? 'Ошибка' : 'Недоступен');
            if (modes[0] === 'offline') assert.equal(await cards.nth(0).locator('[data-vpn-v2-metric="cpu-value"]').innerText(), '—', 'Old successful metrics cleared on failure');
            if (modes[1] === 'network') assert.equal(await cards.nth(1).locator('[data-vpn-v2-metric-state]').innerText(), 'Показатели сейчас недоступны.', 'Network error is localized');
        }
        for (const width of [1440, 390]) {
            await page.setViewportSize({width, height: 900});
            await page.goto('https://vpn.test/admin/plugins/vpn-manager-v2/operations');
            for (const [number, count, first] of [[1, 20, 41], [2, 20, 21], [3, 1, 1]]) {
                if (number > 1) await page.locator('.admin-pagination-nav a[href]').filter({hasText: String(number)}).click();
                assert.equal(await page.locator('tbody tr').count(), count);
                assert.match(await page.locator('tbody tr').first().innerText(), new RegExp('operation-' + first + '\\b'));
                assert.equal(await page.locator('.admin-pagination-nav [aria-current="page"]').innerText(), String(number));
                const bounds = await page.locator('.admin-table-footer').boundingBox();
                assert(bounds.x >= 0 && bounds.x + bounds.width <= width + 1, 'Native footer fits screen');
                assert(await page.locator('.admin-pagination-nav').isVisible());
            }
            await page.locator('.admin-table-footer').screenshot({path: '/private/tmp/vpn-operations-' + width + '-qa.png'});
        }
        assert.deepEqual(errors, []);
        console.log('PASS real templates and JS: loading/live/failure/recovery/disabled/malformed/network; 20/20/1 operations and native pagination at 1440px/390px.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
