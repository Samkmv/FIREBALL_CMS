// Render real CMS update templates with isolated PHP fixtures, never the live site.
const assert = require('node:assert/strict');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.FIREBALL_PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const html = admin => execFileSync(process.env.FIREBALL_PHP || 'php', [path.join(__dirname, 'update_channels_unit.php'), '--render', ...(admin ? ['--admin'] : [])], {encoding: 'utf8'});
(async () => {
    const browser = await chromium.launch({headless: true});
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('https://updates.test/**', async route => {
            const url = new URL(route.request().url());
            if (url.pathname.startsWith('/assets/')) return route.fulfill({path: path.join(root, 'public', url.pathname)});
            return route.fulfill({contentType: 'text/html', body: html(url.searchParams.has('admin'))});
        });
        for (const width of [1440, 390]) {
            await page.setViewportSize({width, height: 1000});
            for (const admin of [false, true]) {
                await page.goto('https://updates.test/admin/updates' + (admin ? '?admin=1' : ''));
                const content = await page.locator('#update-center').innerText();
                assert(!content.includes('admin_update_'), 'Labels are translated');
                if (admin) {
                    assert(!content.includes('v2.0.0-beta.1'), 'Admin does not see a beta package');
                    assert.equal(await page.locator('#update-channel').count(), 0, 'Admin cannot edit creator source settings');
                } else {
                    assert(content.includes('v2.0.0-beta.1'), 'Creator sees newer published beta');
                    assert(content.includes('Она может быть нестабильной'), 'Beta warning shown');
                    await page.locator('details').last().evaluate(node => { node.open = true; });
                    assert.equal(await page.locator('#update-channel').inputValue(), 'Релизы и бета-версии');
                    assert(await page.locator('#update-channel').getAttribute('readonly') !== null);
                    const bounds = await page.locator('#update-channel-help').boundingBox();
                    assert(bounds.x >= 0 && bounds.x + bounds.width <= width + 1, 'Role policy text fits mobile/desktop');
                }
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'No page overflow');
                await page.locator('#update-center').screenshot({path: '/private/tmp/update-channels-' + (admin ? 'admin-' : 'creator-') + width + '.png'});
            }
        }
        assert.deepEqual(errors, []);
        console.log('PASS update UI: real CMS template at 1440px/390px; creator beta warning, role label, admin stable-only and no overflow.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
