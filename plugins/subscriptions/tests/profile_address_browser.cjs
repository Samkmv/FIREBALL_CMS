// Uses CLI-rendered templates, the real local Choices/CMS assets, and a mocked local GET endpoint.
// No live CMS, database, remote address provider, payment or browser installation is used.
const { chromium, webkit } = require('playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '../../..');
const origin = 'http://127.0.0.1:8894';
const fixture = path.join(__dirname, 'fixtures/profile-address.php');
const endpoint = '/profile/subscription-address/suggest';
const mimeTypes = { '.js': 'application/javascript', '.css': 'text/css', '.woff2': 'font/woff2', '.woff': 'font/woff', '.svg': 'image/svg+xml' };
let checks = 0;

function check(actual, expected, message) { assert.deepEqual(actual, expected, message); checks++; }
async function waitFor(condition, message) {
    const deadline = Date.now() + 5000;
    while (Date.now() < deadline) {
        if (await condition()) return;
        await new Promise(resolve => setTimeout(resolve, 30));
    }
    throw new Error(message);
}

async function run(browserType) {
    const executablePath = browserType === chromium ? process.env.SUBSCRIPTIONS_BROWSER_EXECUTABLE : undefined;
    const browser = await browserType.launch({ headless: true, ...(executablePath ? { executablePath } : {}) });
    try {
        for (const width of [390, 1440]) {
            for (const theme of ['light', 'dark']) {
                const context = await browser.newContext({ viewport: { width, height: 1000 }, hasTouch: width === 390 });
                const page = await context.newPage();
                const errors = [];
                const requests = [];
                const unexpectedRequests = [];
                let scenario = 'saved';
                let mode = 'normal';
                let choicesEnabled = true;
                let finishLateRequest;
                page.on('pageerror', error => errors.push(error.message));
                await page.route('**/*', async route => {
                    const request = route.request();
                    const url = new URL(request.url());
                    if (url.origin !== origin) {
                        unexpectedRequests.push(request.url());
                        return route.abort();
                    }
                    if (url.pathname === '/profile/subscription-details') {
                        return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: execFileSync(process.env.PHP_BINARY || 'php', [fixture, scenario, 'ru', theme], { encoding: 'utf8' }) });
                    }
                    if (url.pathname === endpoint) {
                        check(request.method(), 'GET', 'Suggestions only use the existing read-only GET endpoint');
                        const parameters = Object.fromEntries(url.searchParams);
                        requests.push(parameters);
                        if (mode === 'error') return route.fulfill({ status: 503, contentType: 'application/json', body: '{"ok":false}' });
                        if (mode === 'race' && parameters.q === 'Ста') {
                            await new Promise(resolve => { finishLateRequest = resolve; });
                            return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, suggestions: [{ value: 'Старый ответ', label: 'Старый ответ' }] }) });
                        }
                        let suggestions = [];
                        if (mode === 'markup') {
                            suggestions = [{ value: '<img src=x onerror=alert(1)>', label: '<img src=x onerror=alert(1)>', postal_code: '' }];
                        } else if (mode !== 'empty') {
                            if (parameters.type === 'city' && parameters.q.toLowerCase().startsWith('жел')) suggestions = [{ value: 'Железноводск', label: 'Железноводск', postal_code: '' }];
                            if (parameters.type === 'street' && parameters.q.toLowerCase().startsWith('ок')) suggestions = [{ value: 'Октябрьская', label: 'Октябрьская', postal_code: '' }];
                            if (parameters.type === 'house' && parameters.q.startsWith('4')) suggestions = [{ value: '41', label: '41', postal_code: '357400' }];
                        }
                        return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true, suggestions }) });
                    }
                    if (url.pathname.startsWith('/assets/default/') || url.pathname.startsWith('/plugins/subscriptions/assets/')) {
                        const asset = path.resolve(url.pathname.startsWith('/assets/') ? path.join(root, 'public') : root, '.' + decodeURIComponent(url.pathname));
                        if (!asset.startsWith(root + path.sep)) return route.abort();
                        if (!choicesEnabled && asset.endsWith('/choices.min.js')) return route.fulfill({ contentType: 'application/javascript', body: '' });
                        if (fs.existsSync(asset) && fs.statSync(asset).isFile()) return route.fulfill({ status: 200, contentType: mimeTypes[path.extname(asset)] || 'application/octet-stream', body: fs.readFileSync(asset) });
                    }
                    unexpectedRequests.push(request.url());
                    return route.abort();
                });

                const field = type => page.locator(`[data-address-field="${type}"]`);
                const select = type => field(type).locator(`[data-address-select="${type}"]`);
                const search = type => field(type).locator('.choices__input--cloned');
                async function load(nextScenario, nextMode = 'normal') {
                    scenario = nextScenario;
                    mode = nextMode;
                    requests.length = 0;
                    await page.goto(origin + '/profile/subscription-details', { waitUntil: 'networkidle' });
                    if (choicesEnabled) await field('city').locator('.choices').waitFor({ state: 'attached' });
                }
                async function typeSearch(type, value) {
                    const dropdown = field(type).locator('.choices');
                    if (!(await search(type).isVisible())) await dropdown.locator('.choices__inner, :scope > .form-select').first().click();
                    await search(type).fill(value);
                }
                async function choose(type, query, value) {
                    await typeSearch(type, query);
                    const option = field(type).locator('.choices__item--choice').filter({ hasText: value }).first();
                    await option.waitFor({ state: 'visible' });
                    await option.click();
                    check(await select(type).inputValue(), value, `${type}: selected value`);
                }
                async function values(type) {
                    return page.locator('form[data-subscriptions-address-form]').evaluate((form, key) => new FormData(form).getAll(key), type);
                }

                await load('saved');
                check(await page.locator('.choices').count(), 4, 'One Choices per region/city/street/house; no duplicate initialization');
                for (const [type, expected] of Object.entries({ city: 'Железноводск', street: 'Октябрьская', house: '41' })) {
                    check(await values(type), [expected], `${type}: saved value submitted exactly once`);
                    check(await field(type).locator(`[data-address-input="${type}"]`).isDisabled(), true, `${type}: fallback input disabled in Choices mode`);
                    check(await field(type).locator(`[data-address-input="${type}"]`).isVisible(), false, `${type}: fallback input hidden in Choices mode`);
                }
                check(await values('postal_code'), ['357400'], 'Saved postal code preserved');
                check(await page.locator('body').evaluate(body => body.scrollWidth <= window.innerWidth), true, 'No horizontal overflow');
                check(/class="|data-address-suggestions|FIREBALL_SUBSCRIPTIONS_BASIC_DROPDOWN/.test(await page.locator('body').innerText()), false, 'No raw broken HTML in profile text');

                await load('empty');
                await choose('city', 'Жел', 'Железноводск');
                check(requests.find(item => item.type === 'city')?.region, 'Ставропольский край', 'Cities filtered by selected region');
                await choose('street', 'Ок', 'Октябрьская');
                check(requests.find(item => item.type === 'street')?.city, 'Железноводск', 'Streets filtered by selected city');
                await choose('house', '4', '41');
                check(requests.find(item => item.type === 'house')?.street, 'Октябрьская', 'Houses filtered by selected street');
                check(await values('postal_code'), ['357400'], 'House selection fills postal code');
                if (process.env.SUBSCRIPTIONS_SCREENSHOT_DIR) {
                    await page.screenshot({ path: path.join(process.env.SUBSCRIPTIONS_SCREENSHOT_DIR, `profile-address-${browserType.name()}-${width}-${theme}.png`), fullPage: true });
                    await typeSearch('city', 'Жел');
                    await field('city').locator('.choices__item--choice[data-value="Железноводск"]').waitFor({ state: 'visible' });
                    await page.screenshot({ path: path.join(process.env.SUBSCRIPTIONS_SCREENSHOT_DIR, `profile-address-open-${browserType.name()}-${width}-${theme}.png`), fullPage: true });
                    await search('city').press('Escape');
                }

                await choose('street', 'Новая улица', 'Новая улица');
                check(await values('house'), [''], 'Street change clears old house');
                check(await values('postal_code'), [''], 'Street change clears old postal code');
                await choose('city', 'Другой город', 'Другой город');
                check(await values('street'), [''], 'City change clears old street');
                check(await values('house'), [''], 'City change clears old house');
                const regionChoices = page.locator('[data-subscriptions-region] .choices');
                await regionChoices.locator(':scope > .form-select').click();
                await regionChoices.locator('.choices__input--cloned').fill('Московская');
                await regionChoices.locator('.choices__item--choice[data-value="Московская область"]').click();
                check(await values('city'), [''], 'Region change clears old city');
                check(await values('street'), [''], 'Region change clears old street');

                await load('old');
                check(await values('city'), ['Ранее сохранённый город'], 'Saved value not present in directory is retained');
                check(await values('house'), ['17А/2'], 'Nonstandard saved house is retained');
                await load('validation');
                check(await values('city'), ['Введённый город'], 'Validation retry preserves submitted city');
                check(await values('street'), ['Введённая улица'], 'Validation retry preserves submitted street');

                await load('empty', 'empty');
                await choose('city', 'Неизвестный посёлок', 'Неизвестный посёлок');
                check(await values('city'), ['Неизвестный посёлок'], 'No directory result still allows explicitly selected manual address');
                await load('saved', 'empty');
                await typeSearch('city', 'Введённый без выбора город');
                await page.locator('form[data-subscriptions-address-form]').evaluate(form => {
                    form.addEventListener('submit', event => event.preventDefault(), { once: true });
                });
                await page.locator('button[type="submit"]').click();
                check(await values('city'), ['Введённый без выбора город'], 'Save commits a typed manual value without a dropdown click');
                check(await values('street'), [''], 'Committing a different city clears the previous street');
                await load('empty', 'error');
                await typeSearch('city', 'Новый город');
                const unavailable = await page.locator('form[data-subscriptions-address-form]').getAttribute('data-address-service-unavailable');
                await waitFor(async () => (await field('city').locator('[data-address-status]').innerText()).trim() === unavailable, 'Service error should have a status hint');
                await field('city').locator('.choices__item--choice').filter({ hasText: 'Новый город' }).first().click();
                check(await values('city'), ['Новый город'], 'Directory failure does not discard manual choice');

                await load('foreign');
                check(await values('city'), ['Munich'], 'Foreign saved address retained');
                check(await field('city').locator('[data-address-input="city"]').isVisible(), true, 'Foreign address uses unrestricted text fallback');
                await field('city').locator('[data-address-input="city"]').fill('Berlin');
                check(await values('city'), ['Berlin'], 'Foreign text submitted once');
                check(requests.length, 0, 'Foreign address does not query Russian directory');

                await load('empty');
                await typeSearch('city', 'Железноводск');
                await field('city').locator('.choices__item--choice[data-value="Железноводск"]').waitFor({ state: 'visible' });
                await search('city').press('ArrowDown');
                await search('city').press('Enter');
                check(await select('city').inputValue(), 'Железноводск', 'Keyboard selection supports arrow and Enter');

                await load('empty', 'race');
                await typeSearch('city', 'Ста');
                await waitFor(() => Boolean(finishLateRequest), 'First request should be pending before replacing search');
                await typeSearch('city', 'Жел');
                await field('city').locator('.choices__item--choice[data-value="Железноводск"]').waitFor({ state: 'visible' });
                finishLateRequest();
                finishLateRequest = null;
                await page.waitForLoadState('networkidle');
                check(await field('city').locator('.choices__item--choice[data-value="Старый ответ"]').count(), 0, 'Late response cannot overwrite newer query');

                await load('empty', 'race');
                await typeSearch('city', 'Ста');
                await waitFor(() => Boolean(finishLateRequest), 'Request should be pending before clearing search');
                await search('city').fill('');
                finishLateRequest();
                finishLateRequest = null;
                await page.waitForLoadState('networkidle');
                check(await field('city').locator('.choices__item--choice[data-value="Старый ответ"]').count(), 0, 'Clearing search also rejects pending replies');
                check(await search('city').inputValue(), '', 'Late response does not restore a cleared query');

                await load('unsafe');
                check(await page.locator('img[onerror], script:not([src])').count(), 0, 'Stored markup is escaped');
                await load('empty', 'markup');
                await typeSearch('city', 'Безопасно');
                await field('city').locator('.choices__item--choice').filter({ hasText: '<img src=x onerror=alert(1)>' }).first().waitFor({ state: 'visible' });
                check(await field('city').locator('img[onerror]').count(), 0, 'Remote suggestion labels rendered as text');

                choicesEnabled = false;
                await load('saved');
                check(await field('city').locator('[data-address-input="city"]').isVisible(), true, 'No Choices script: native text fallback remains usable');
                check(await values('city'), ['Железноводск'], 'No Choices script: initial data submitted once');
                check(errors, [], 'No JavaScript errors');
                check(unexpectedRequests, [], 'No live or external network requests');
                await context.close();
            }
        }
    } finally {
        await browser.close();
    }
}

(async () => {
    await run(chromium);
    if (process.env.SUBSCRIPTIONS_BROWSER === 'all') await run(webkit);
    console.log(`Profile address browser tests passed: ${checks} checks.`);
})().catch(error => { console.error(error); process.exitCode = 1; });
