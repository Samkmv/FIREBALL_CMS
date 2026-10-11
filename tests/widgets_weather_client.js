// Unit-test the location flow with synthetic coordinates; never reads a real device location.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(path.join(__dirname, '../public/assets/default/js/widgets-weather.js'), 'utf8');
const checks = [];
const verify = (passed, description) => { assert.ok(passed, description); checks.push(description); };
async function scenario({ permission = 'prompt', cardCount = 1, geoError = false, fetchError = false, secure = true, supported = true, permissionsSupported = true } = {}) {
    const requests = [];
    let geoCalls = 0;
    const cards = Array.from({ length: cardCount }, (_, index) => {
        const button = { disabled: false, addEventListener(event, callback) { this.click = callback; } };
        const status = { textContent: '' };
        const body = { updated: false, replaceChildren() { this.updated = true; } };
        return { button, status, body, dataset: { weatherUrl: '/widgets/weather/' + (index + 1), weatherPage: 'posts.index', weatherToken: 'TEST-CSRF', weatherLoading: 'Loading', weatherFallback: 'City fallback', weatherError: 'Provider fallback' }, querySelector(selector) { return selector === '[data-weather-locate]' ? button : selector === '[data-weather-status]' ? status : body; } };
    });
    const navigator = {};
    if (supported) navigator.geolocation = { getCurrentPosition(success, failure) { geoCalls++; queueMicrotask(() => geoError ? failure({ code: 1 }) : success({ coords: { latitude: 56.12345, longitude: 40.56789 } })); } };
    if (permissionsSupported) navigator.permissions = { query: async () => ({ state: permission }) };
    const context = { navigator, AbortController, Promise, Error, Math, console,
        window: { isSecureContext: secure, setTimeout, clearTimeout },
        document: { querySelectorAll: () => cards, createElement: () => ({ content: { querySelector: () => ({ childNodes: [{}] }) } }) },
        fetch: async (url, options) => { requests.push({ url, options, payload: JSON.parse(options.body) }); return { ok: !fetchError, json: async () => ({ status: fetchError ? 'error' : 'ok', html: '<section>Trusted weather</section>' }) }; },
    };
    vm.runInNewContext(source, context);
    await new Promise(setImmediate);
    return { cards, requests, get geoCalls() { return geoCalls; }, flush: () => new Promise(setImmediate) };
}
(async () => {
    const auto = await scenario({ permission: 'granted', cardCount: 2 });
    verify(auto.geoCalls === 1 && auto.requests.length === 2 && auto.cards.every(card => card.body.updated), 'Granted permission loads weather automatically with one shared location lookup');
    verify(auto.requests.every(({ payload, options }) => payload.latitude === 56.12 && payload.longitude === 40.57 && options.method === 'POST' && options.headers['X-CSRF-Token'] === 'TEST-CSRF'), 'Only rounded coordinates are sent in CSRF-protected POST bodies');
    const prompt = await scenario();
    verify(prompt.geoCalls === 0 && prompt.requests.length === 0, 'First-time visitor is not prompted until choosing the location button');
    await prompt.cards[0].button.click();
    verify(prompt.geoCalls === 1 && prompt.cards[0].body.updated && !prompt.cards[0].button.disabled, 'Opt-in button loads local weather and restores its enabled state');
    const denied = await scenario({ permission: 'denied' });
    verify(denied.geoCalls === 0 && !denied.cards[0].body.updated && denied.cards[0].status.textContent === 'City fallback', 'Denied location permission keeps the configured-city forecast');
    const failed = await scenario({ geoError: true }); await failed.cards[0].button.click();
    verify(failed.requests.length === 0 && failed.cards[0].status.textContent === 'City fallback' && !failed.cards[0].button.disabled, 'Location timeout or rejection keeps city fallback without sending coordinates');
    const provider = await scenario({ permission: 'granted', fetchError: true });
    verify(!provider.cards[0].body.updated && provider.cards[0].status.textContent === 'Provider fallback', 'Provider failure leaves the initial weather card intact');
    const insecure = await scenario({ permission: 'granted', secure: false });
    const unsupported = await scenario({ permission: 'granted', supported: false });
    verify(insecure.geoCalls === 0 && unsupported.geoCalls === 0 && insecure.cards[0].status.textContent === 'City fallback' && unsupported.cards[0].status.textContent === 'City fallback', 'Unsupported or insecure contexts keep city fallback');
    const withoutPermissions = await scenario({ permissionsSupported: false }); await withoutPermissions.cards[0].button.click();
    verify(withoutPermissions.cards[0].body.updated, 'Browsers without Permissions API still support explicit location opt-in');
    fs.mkdirSync(path.join(__dirname, 'artifacts/widgets'), { recursive: true });
    fs.writeFileSync(path.join(__dirname, 'artifacts/widgets/client-tests.json'), JSON.stringify({ passed: checks.length, checks }, null, 2));
    console.log(`${checks.length} weather client checks passed.`);
})().catch(error => { console.error(error); process.exitCode = 1; });
