// Render real CMS update templates with isolated PHP fixtures, never the live site.
const assert = require('node:assert/strict');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.FIREBALL_PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const html = (admin, dev) => execFileSync(process.env.FIREBALL_PHP || 'php', [path.join(__dirname, 'update_channels_unit.php'), '--render', ...(admin ? ['--admin'] : []), ...(dev ? ['--dev'] : [])], {encoding: 'utf8'});
(async () => {
    const browser = await chromium.launch({headless: true});
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('https://updates.test/**', async route => {
            const url = new URL(route.request().url());
            if (url.pathname.startsWith('/assets/')) return route.fulfill({path: path.join(root, 'public', url.pathname)});
            const submitted = route.request().method() === 'POST' ? new URLSearchParams(route.request().postData()) : null;
            return route.fulfill({contentType: 'text/html', body: html(url.searchParams.has('admin'), submitted ? submitted.get('update_channel') === 'dev' : url.searchParams.has('dev'))});
        });
        for (const width of [1440, 390]) {
            await page.setViewportSize({width, height: 1000});
            for (const channel of ['beta', 'dev', 'admin']) {
                const admin = channel === 'admin';
                await page.goto('https://updates.test/admin/updates' + (admin ? '?admin=1' : channel === 'dev' ? '?dev=1' : ''));
                const content = await page.locator('#update-center').innerText();
                assert(!content.includes('admin_update_'), 'Labels are translated');
                if (admin) {
                    assert(!content.includes('v2.0.0-beta.1'), 'Admin does not see a beta package');
                    assert.equal(await page.locator('#update-channel').count(), 0, 'Admin cannot edit creator source settings');
                } else {
                    if (channel === 'beta') {
                        assert(content.includes('v2.0.0-beta.1'), 'Creator sees newer published beta');
                        assert(content.includes('Она может быть нестабильной'), 'Beta warning shown');
                    } else {
                        assert(content.includes('v1.8.2-beta.1') && content.includes('Main changes'), 'Developer metadata comes from main');
                        assert(content.includes('Версии могут быть нестабильными'), 'Developer warning shown');
                    }
                    await page.locator('details').last().evaluate(node => { node.open = true; });
                    assert.equal(await page.locator('#update-channel').inputValue(), channel);
                    assert.deepEqual(await page.locator('#update-channel option').evaluateAll(nodes => nodes.map(node => node.value)), ['beta', 'dev']);
                    assert.equal(await page.locator('input[name="updater_github_branch"]').inputValue(), 'main');
                    assert(await page.locator('input[name="updater_github_branch"]').getAttribute('readonly') !== null);
                    const bounds = await page.locator('#update-channel-help').boundingBox();
                    assert(bounds.x >= 0 && bounds.x + bounds.width <= width + 1, 'Role policy text fits mobile/desktop');
                    if (channel === 'beta') {
                        await page.locator('#update-channel').selectOption('dev');
                        await page.locator('#update-channel').evaluate(node => {
                            const data = new FormData(node.form);
                            if (data.get('update_channel') !== 'dev') throw new Error('Channel must be submitted with source form');
                        });
                        await Promise.all([page.waitForNavigation(), page.locator('#update-channel').locator('xpath=ancestor::form').locator('button[type="submit"]').click()]);
                        assert((await page.locator('#update-center').innerText()).includes('Main changes'), 'Saving choice reloads developer source');
                        await page.locator('details').last().evaluate(node => { node.open = true; });
                        assert.equal(await page.locator('#update-channel').inputValue(), 'dev');
                    }
                }
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'No page overflow');
                await page.locator('#update-center').screenshot({path: '/private/tmp/update-channels-' + channel + '-' + width + '.png'});
            }
        }
        assert.deepEqual(errors, []);
        console.log('PASS update UI: real CMS template at 1440px/390px; creator release/main choice and submission, developer metadata, admin stable-only and no overflow.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
