// Real CMS templates/CSS/JS in Chromium. Browser Push APIs and backend are isolated doubles.
// PHP_BIN=/path/to/php node tests/profile_push_browser.cjs (requires Playwright).
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BIN || 'php';
const endpoint = 'https://fcm.googleapis.com/fcm/send/current';
const second = 'https://web.push.apple.com/other-device';
const hash = value => crypto.createHash('sha256').update(value).digest('hex');
let checks = 0;
const check = (condition, message) => { checks++; assert.ok(condition, message); };

async function scenario(browser, options = {}) {
    const server = new Map(options.active ? [[endpoint, 7], [second, 7]] : [[second, 7]]);
    const calls = [];
    const errors = [];
    const context = await browser.newContext({
        viewport: options.desktop ? { width: 1440, height: 900 } : { width: 390, height: 844 },
        isMobile: !options.desktop, hasTouch: !options.desktop,
        ...(options.userAgent ? { userAgent: options.userAgent } : {}),
    });
    const page = await context.newPage();
    page.on('pageerror', e => errors.push(e.message));
    await page.addInitScript(({ active, permission, supported, standalone, deny, ipad, registerFail, registerHang, getSubscriptionHang, existingRegistration, digestHang }) => {
        if (!localStorage.getItem('fixture.initialized')) {
            localStorage.setItem('fixture.initialized', '1');
            localStorage.setItem('fixture.permission', permission || (active ? 'granted' : 'default'));
            if (active) localStorage.setItem('fixture.endpoint', 'https://fcm.googleapis.com/fcm/send/current');
        }
        window.fixturePushCalls = [];
        const endpoint = 'https://fcm.googleapis.com/fcm/send/current';
        const subscription = () => ({
            endpoint, options: {},
            toJSON: () => ({ endpoint, keys: { p256dh: 'fixture', auth: 'fixture' } }),
            unsubscribe: async () => { window.fixturePushCalls.push('unsubscribe'); localStorage.removeItem('fixture.endpoint'); return true; }
        });
        const registration = {
            active: { scriptURL: 'https://profile.test/service-worker.js', postMessage() {} },
            pushManager: {
                getSubscription: async () => {
                    if (getSubscriptionHang && !window.fixtureRecovered) return new Promise(resolve => { window.fixtureResolveSubscription = resolve; });
                    return localStorage.getItem('fixture.endpoint') ? subscription() : null;
                },
                subscribe: async () => {
                    window.fixturePushCalls.push('subscribe');
                    if (!navigator.userActivation.isActive) throw new Error('Lost user activation');
                    localStorage.setItem('fixture.permission', deny ? 'denied' : 'granted');
                    if (deny) throw new Error('Permission denied');
                    localStorage.setItem('fixture.endpoint', endpoint);
                    return subscription();
                }
            }
        };
        Object.defineProperty(navigator, 'standalone', { value: !!standalone, configurable: true });
        if (ipad) {
            Object.defineProperty(navigator, 'platform', { value: 'MacIntel', configurable: true });
            Object.defineProperty(navigator, 'maxTouchPoints', { value: 5, configurable: true });
        }
        if (supported === false) {
            delete window.PushManager;
            delete window.Notification;
        } else {
            Object.defineProperty(window, 'Notification', { configurable: true, value: {
                get permission() { return localStorage.getItem('fixture.permission'); },
                requestPermission: () => { throw new Error('Permission must not be prompted on page load'); }
            }});
            Object.defineProperty(window, 'PushManager', { configurable: true, value: function () {} });
        }
        Object.defineProperty(navigator, 'serviceWorker', { configurable: true, value: {
            register: async () => {
                window.fixturePushCalls.push('register');
                if (registerHang && !window.fixtureRecovered) return new Promise(() => {});
                if (registerFail) throw new Error('Worker unavailable');
                return registration;
            },
            ...(existingRegistration ? { getRegistration: async () => registration } : {}),
            ready: Promise.resolve(registration), controller: registration.active, addEventListener() {}
        }});
        if (digestHang) {
            const digest = crypto.subtle.digest.bind(crypto.subtle);
            crypto.subtle.digest = (...args) => window.fixtureRecovered ? digest(...args) : new Promise(() => {});
        }
    }, options);
    const renders = new Map();
    await page.route('https://profile.test/**', async route => {
        const request = route.request();
        const url = new URL(request.url());
        if (url.pathname.startsWith('/assets/')) {
            return route.fulfill({ path: path.join(root, 'public', url.pathname), contentType: url.pathname.endsWith('.css') ? 'text/css' : url.pathname.endsWith('.woff2') ? 'font/woff2' : 'application/javascript' });
        }
        if (url.pathname.startsWith('/api/')) {
            const payload = request.postDataJSON();
            calls.push({ path: url.pathname, method: request.method(), payload, hash: url.searchParams.get('endpoint_hash') });
            if (options.statusHang && url.pathname === '/api/pwa/status' && !options.recovered) return;
            if (options.failStatus && url.pathname === '/api/pwa/status') return route.fulfill({ status: 503, json: { status: false } });
            if (url.pathname === '/api/pwa/subscriptions') {
                if (options.failSave) return route.fulfill({ json: { status: false } });
                server.set(payload.endpoint, 7);
                return route.fulfill({ json: { status: true } });
            }
            if (url.pathname === '/api/pwa/subscriptions/delete') {
                if (options.failDelete) return route.fulfill({ json: { status: false } });
                server.delete(payload.endpoint);
                return route.fulfill({ json: { status: true } });
            }
            return route.fulfill({ json: {
                status: true, user_id: 7, badge_count: 0,
                push: { pwa_enabled: true, global_enabled: !options.globalDisabled, vapid_ready: true, secure_context: true,
                    user_enabled: [...server.values()].includes(7), active_subscriptions: server.size,
                    current_device_active: [...server.entries()].some(([endpoint, user]) => user === 7 && hash(endpoint) === url.searchParams.get('endpoint_hash')) }
            }});
        }
        const locale = options.locale || 'ru';
        const section = /\/settings$/.test(url.pathname) ? url.searchParams.get('section') || 'information' : 'overview';
        const key = `${locale}:${section}:${options.fallback ? 'fallback' : 'theme'}:${url.pathname}`;
        const routePath = options.locale && options.locale !== 'ru' ? url.pathname.slice(options.locale.length + 1) : url.pathname;
        if (!renders.has(key)) renders.set(key, execFileSync(php, [path.join(__dirname, 'fixtures/profile.php'), locale, section, options.fallback ? 'fallback' : 'theme', routePath], { encoding: 'utf8' }));
        const html = `<!doctype html><html lang="${locale}" data-bs-theme="${options.light ? 'light' : 'dark'}"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="needCSRFToken" content="fixture"><link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css"><link rel="stylesheet" href="/assets/default/css/profile.css"><link rel="stylesheet" href="/assets/default/css/style.css"></head>
            <body data-pwa-auth-user-id="7" data-pwa-enabled="1" data-pwa-push-enabled="1" data-pwa-vapid-public-key="AQID" data-pwa-subscribe-url="/api/pwa/subscriptions" data-pwa-unsubscribe-url="/api/pwa/subscriptions/delete" data-pwa-status-url="/api/pwa/status">${renders.get(key)}
            <script>const baseUrl = 'https://profile.test';</script><script src="/assets/default/js/jquery-3.7.1.min.js"></script><script src="/assets/default/js/pwa.js"></script><script src="/assets/default/js/main.js"></script></body></html>`;
        return route.fulfill({ contentType: 'text/html', body: html });
    });
    const prefix = options.locale && options.locale !== 'ru' ? '/' + options.locale : '';
    const load = async (section, waitForStatus = true) => {
        await page.goto('https://profile.test' + prefix + (section === 'overview' ? '/profile' : '/profile/settings?section=' + section));
        await page.waitForFunction(() => window.FireballPwa);
        if (waitForStatus && (section === 'overview' || section === 'notifications')) await page.waitForFunction(() => !document.querySelector('[data-pwa-push-status]').textContent.includes('Проверяем'));
        // refresh exposes the same promise/state used by both rendered widgets.
        if (waitForStatus && (section === 'overview' || section === 'notifications')) await page.evaluate(() => window.FireballPwa.refreshPushStatus());
    };
    const state = () => page.evaluate(() => window.FireballPwa.refreshPushStatus());
    return { page, context, server, calls, errors, load, state };
}

(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const s = await scenario(browser, { desktop: true });
        await s.load('overview');
        check(await s.state() === 'disabled', 'A second active device must not enable this browser');
        check(!s.calls.some(c => c.method === 'POST'), 'No subscription mutations on page load');
        check((await s.page.evaluate(() => fixturePushCalls.filter(call => call !== 'register'))).length === 0, 'No permission request on load');
        const overview = await s.page.locator('[data-pwa-push-status]').textContent();
        await s.load('notifications');
        check((await s.page.locator('[data-pwa-push-status]').textContent()).trim() === overview.trim(), 'Overview/settings same state');
        check(await s.page.locator('[data-pwa-enable-push]').isVisible(), 'Disabled shows Enable');
        check(!await s.page.locator('[data-pwa-disable-push]').isVisible(), 'Disabled hides Disable');
        await s.page.locator('[data-pwa-enable-push]').click();
        await s.page.waitForFunction(() => document.querySelector('[data-pwa-disable-push]').disabled === false);
        check(await s.state() === 'enabled', 'Permission allow + persisted current endpoint enables');
        await s.page.reload();
        check(await s.page.evaluate(async () => { while (!window.FireballPwa) await new Promise(r => setTimeout(r, 10)); return FireballPwa.refreshPushStatus(); }) === 'enabled', 'Reload keeps actual state');
        await s.load('overview');
        check(await s.state() === 'enabled', 'Overview follows newly enabled device');
        await s.load('notifications');
        await s.page.locator('[data-pwa-disable-push]').click();
        await s.page.waitForFunction(() => document.querySelector('[data-pwa-enable-push]').disabled === false);
        check(await s.state() === 'disabled', 'Disable returns to disabled');
        check(s.server.has(second) && !s.server.has(endpoint), 'Disable preserves the other endpoint');
        check(s.calls.filter(c => c.path.endsWith('/delete')).every(c => c.payload.endpoint === endpoint), 'Only current endpoint deleted');
        check(await s.page.evaluate(() => localStorage.getItem('fixture.endpoint')) === null, 'Browser subscription removed');
        check(s.errors.length === 0, 'No desktop JS errors: ' + JSON.stringify(s.errors));
        await s.context.close();

        for (const options of [
            { permission: 'denied', expected: 'permission' },
            { deny: true, expected: 'permission' },
            { active: true, failStatus: true, expected: 'error' },
            { registerFail: true, expected: 'error' },
            { supported: false, expected: 'unsupported', desktop: true },
            { globalDisabled: true, expected: 'unavailable' },
            { supported: false, expected: 'install', userAgent: 'Mozilla/5.0 (iPhone) AppleWebKit/605.1.15 Version/18.0 Mobile Safari/604.1' },
            { supported: false, expected: 'install', ipad: true, userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X) AppleWebKit/605.1.15 Version/18.0 Safari/605.1.15' },
        ]) {
            const s = await scenario(browser, options);
            await s.load('notifications');
            if (options.deny) {
                await s.page.locator('[data-pwa-enable-push]').click();
                await s.page.waitForFunction(() => document.querySelector('[data-pwa-push-status]').textContent.trim() === 'Заблокированы');
            }
            check(await s.state() === options.expected, `Environment ${JSON.stringify(options)}`);
            if (['permission', 'install', 'unsupported', 'unavailable'].includes(options.expected)) {
                check(!await s.page.locator('[data-pwa-enable-push]').isVisible(), 'No futile Enable button');
                check((await s.page.locator('[data-pwa-push-status-hint]').textContent()).trim().length > 15, 'State explanation present');
            }
            check(s.errors.length === 0, 'No JS errors in special state');
            await s.context.close();
        }

        const failed = await scenario(browser, { failSave: true });
        await failed.load('notifications');
        await failed.page.locator('[data-pwa-enable-push]').click();
        await failed.page.waitForFunction(() => !document.querySelector('[data-pwa-push-feedback]').classList.contains('d-none'));
        check(await failed.state() === 'disabled', 'HTTP 200 with status:false is not enabled');
        check(await failed.page.locator('[data-pwa-enable-push]').isEnabled(), 'Can retry failed save');
        await failed.context.close();

        const revoked = await scenario(browser, { permission: 'granted' });
        await revoked.load('notifications');
        await revoked.page.evaluate(() => { localStorage.setItem('fixture.endpoint', 'https://fcm.googleapis.com/fcm/send/current'); });
        check(await revoked.state() === 'disabled', 'Local endpoint without server activation stays disabled');
        check(!revoked.calls.some(c => c.method === 'POST'), 'Read-only status does not reactivate an endpoint');
        await revoked.page.locator('[data-pwa-enable-push]').click();
        await revoked.page.waitForFunction(() => !document.querySelector('[data-pwa-disable-push]').disabled);
        check(await revoked.state() === 'enabled', 'Explicit Enable can restore server registration');
        await revoked.page.evaluate(() => { localStorage.setItem('fixture.permission', 'denied'); document.dispatchEvent(new Event('visibilitychange')); });
        check(await revoked.state() === 'permission', 'Changed OS/browser permission is rechecked on return');
        await revoked.context.close();

        const failedDelete = await scenario(browser, { active: true, failDelete: true });
        await failedDelete.load('notifications');
        await failedDelete.page.locator('[data-pwa-disable-push]').click();
        await failedDelete.page.waitForFunction(() => !document.querySelector('[data-pwa-push-feedback]').classList.contains('d-none'));
        check(await failedDelete.state() === 'enabled', 'Failed server deactivation does not falsely show disabled');
        check(failedDelete.server.has(endpoint) && failedDelete.server.has(second), 'Failed deactivation preserves both endpoints');
        check(await failedDelete.page.evaluate(() => localStorage.getItem('fixture.endpoint')) === endpoint, 'Failed server deactivation does not remove local subscription');
        await failedDelete.context.close();

        for (const options of [
            { standalone: true, active: true, userAgent: 'Mozilla/5.0 (iPhone) AppleWebKit/605.1.15 Mobile Safari/604.1' },
            { standalone: true, active: true, userAgent: 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/130 Mobile Safari/537.36' },
            { standalone: false, userAgent: 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/130 Mobile Safari/537.36' }
        ]) {
            const s = await scenario(browser, options);
            await s.load('notifications');
            check(await s.state() === (options.active ? 'enabled' : 'disabled'), 'PWA / supported ordinary Android browser');
            if (!options.active) {
                await s.page.locator('[data-pwa-enable-push]').click();
                await s.page.waitForFunction(() => !document.querySelector('[data-pwa-disable-push]').disabled);
            }
            check(await s.page.locator('[data-pwa-disable-push]').isVisible(), 'Current device can disable in PWA/browser');
            await s.page.locator('[data-pwa-disable-push]').click();
            await s.page.waitForFunction(() => !document.querySelector('[data-pwa-enable-push]').disabled);
            check(await s.state() === 'disabled', 'PWA device disable');
            await s.context.close();
        }

        for (const locale of ['ru', 'en', 'de', 'zh-cn']) {
            for (const fallback of [false, true]) {
                const s = await scenario(browser, { locale, fallback, light: fallback });
                for (const section of ['overview', 'information', 'security', 'notifications']) {
                    await s.load(section);
                    const nav = s.page.locator('[data-profile-route-nav]');
                    check(await nav.locator('a.active[aria-current="page"]').count() === 1, 'Active route survives direct navigation');
                    const geometry = await nav.evaluate(nav => {
                        const links = [...nav.querySelectorAll('a')].map(a => a.getBoundingClientRect());
                        const active = nav.querySelector('.active').getBoundingClientRect();
                        const bounds = nav.getBoundingClientRect();
                        const selected = getComputedStyle(nav.querySelector('.active'));
                        const normal = getComputedStyle(nav.querySelector('a:not(.active)'));
                        return { rows: new Set(links.map(r => Math.round(r.top))).size, count: links.length,
                            visible: active.left >= bounds.left - 1 && active.right <= bounds.right + 1,
                            overflow: document.documentElement.scrollWidth > innerWidth, touch: links.every(r => r.height >= 44),
                            scrollable: nav.scrollWidth > nav.clientWidth,
                            sidebar: !!nav.closest('.profile-sidebar'),
                            buttonStyled: nav.classList.contains('nav-tabs') && nav.classList.contains('flex-column')
                                && parseFloat(selected.borderRadius) > 0 && selected.backgroundColor !== normal.backgroundColor };
                    });
                    check(geometry.rows === geometry.count && !geometry.overflow && geometry.touch && geometry.visible, `Mobile vertical menu geometry ${locale} ${section}`);
                    check(geometry.sidebar && geometry.buttonStyled, 'Sidebar uses native theme button-like active state');
                    if (section === 'notifications') {
                        check(!geometry.scrollable, 'Vertical menu needs no horizontal scroll');
                        check(await s.page.locator('.profile-content > nav').count() === 0, 'Old header tabs removed');
                    }
                    await s.page.reload();
                    await s.page.waitForFunction(() => window.FireballPwa);
                    check(await nav.locator('a.active[aria-current="page"]').count() === 1, 'Active route survives reload');
                }
                const services = s.page.locator('.profile-nav-services .nav');
                check(await services.evaluate(el => el.classList.contains('nav-tabs') && el.classList.contains('flex-column')), 'Vertical button-like theme tabs');
                check(await services.locator('[data-bs-toggle]').count() === 0, 'Services stay real page links');
                const service = services.locator('a[href$="/account/subscription"]');
                const base = await service.evaluate(el => getComputedStyle(el).backgroundColor);
                await service.hover();
                await s.page.waitForFunction(base => getComputedStyle(document.querySelector('.profile-nav-services a[href$="/account/subscription"]')).backgroundColor !== base, base);
                check(await service.evaluate(el => getComputedStyle(el).transitionDuration.split(',').some(s => parseFloat(s) > 0)), 'Theme hover animation retained');
                check(await s.page.locator('.profile-nav-footer .nav').evaluate(el => el.classList.contains('nav-tabs')), 'Footer uses matching theme tabs');
                check(await s.page.locator('.profile-logout').evaluate(el => {
                    const probe = document.createElement('span');
                    probe.style.color = 'var(--cz-danger)';
                    el.append(probe);
                    const matches = getComputedStyle(el).color === getComputedStyle(probe).color;
                    probe.remove();
                    return matches;
                }), 'Logout is danger red');
                const href = await service.getAttribute('href');
                await service.click();
                await s.page.waitForURL('**' + href);
                check(await services.locator('a.active[aria-current="page"]').count() === 1, 'Services active route');
                const selected = await services.locator('a.active').evaluate(el => {
                    const a = getComputedStyle(el);
                    const b = getComputedStyle(el.closest('ul').querySelector('a:not(.active)'));
                    return a.backgroundColor !== b.backgroundColor && parseFloat(a.borderRadius) > 0;
                });
                check(selected, 'Selected service gets native theme button background');
                await s.load('notifications');
                check(s.errors.length === 0, 'No JS errors for locale/fallback');
                if (process.env.PROFILE_PUSH_SCREENSHOT && locale === 'ru' && !fallback) await s.page.screenshot({ path: process.env.PROFILE_PUSH_SCREENSHOT, fullPage: true });
                await s.context.close();
            }
        }
        for (const failure of ['registerHang', 'getSubscriptionHang', 'statusHang', 'digestHang']) {
            for (const fallback of [false, true]) {
                const options = { [failure]: true, active: true, standalone: true, fallback,
                    userAgent: 'Mozilla/5.0 (iPhone) AppleWebKit/605.1.15 Mobile Safari/604.1' };
                const s = await scenario(browser, options);
                s.server.delete(endpoint); // Admin detached this endpoint; another device remains active.
                await s.page.addInitScript(() => {
                    const schedule = window.setTimeout.bind(window);
                    window.fixtureDeadlines = [];
                    window.setTimeout = (callback, delay, ...args) => {
                        if (delay === 10000) window.fixtureDeadlines.push(delay);
                        // Keep deadlines finite but allow intercepted fetches to finish on a busy runner.
                        return schedule(callback, delay === 10000 ? 1000 : delay, ...args);
                    };
                });
                await s.load('notifications', false);
                check(await s.page.evaluate(() => {
                    const first = FireballPwa.refreshPushStatus();
                    const second = FireballPwa.refreshPushStatus();
                    return first === second;
                }), failure + ': concurrent checks reuse the same deadline/promise');
                await s.page.waitForFunction(() => document.querySelector('[data-pwa-push-status]').textContent.trim() === 'Не удалось проверить');
                check(await s.page.locator('[data-pwa-enable-push]').isEnabled(), failure + ': failed check can be retried');
                check(await s.page.locator('[data-pwa-enable-push]').isVisible(), failure + ': action does not stay hidden');
                check(await s.page.evaluate(() => fixtureDeadlines.length > 0 && fixtureDeadlines.every(ms => ms === 10000)), failure + ': real code uses a finite 10-second deadline');
                check(!s.calls.some(call => call.method === 'POST'), failure + ': no automatic endpoint reactivation');
                if (failure === 'getSubscriptionHang') {
                    await s.page.evaluate(() => window.fixtureResolveSubscription?.({ endpoint: 'https://web.push.apple.com/late', options: {} }));
                    await s.page.waitForTimeout(25);
                    check((await s.page.locator('[data-pwa-push-status]').textContent()).trim() === 'Не удалось проверить', 'Late non-abortable browser result cannot overwrite timeout state');
                }
                options.recovered = true;
                await s.page.evaluate(() => { window.fixtureRecovered = true; });
                check(await s.state() === 'disabled', failure + ': detached endpoint becomes disabled after recovery');
                check(!s.calls.some(call => call.method === 'POST'), failure + ': recovery still performs read-only verification');
                check(s.errors.length === 0, failure + ': no unhandled JS errors');
                await s.context.close();
            }
        }

        const existing = await scenario(browser, { active: true, standalone: true, registerHang: true, existingRegistration: true });
        existing.server.delete(endpoint);
        await existing.page.addInitScript(() => {
            const schedule = window.setTimeout.bind(window);
            window.setTimeout = (callback, delay, ...args) => schedule(callback, delay === 10000 ? 100 : delay, ...args);
        });
        await existing.load('notifications');
        check(await existing.state() === 'disabled', 'Existing active worker can check a detached endpoint while register() hangs');
        await existing.page.waitForTimeout(150);
        check((await existing.page.locator('[data-pwa-push-status]').textContent()).trim() === 'Выключены', 'Failed background register must not erase a completed disabled status');
        check(!existing.calls.some(call => call.method === 'POST'), 'Existing-worker lookup never restores an endpoint silently');
        check(existing.errors.length === 0, 'No errors with pending background registration');
        await existing.context.close();

        console.log(`Profile / push browser checks passed: ${checks}`);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
