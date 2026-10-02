'use strict';
// Execute the real queue code with an isolated DOM/HTTP adapter, no browser or CMS writes.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../public/assets/default/js/plugin-updates.js'), 'utf8');
function element() {
    return {hidden: true, disabled: false, textContent: '', children: [], classList: {add() {}, remove() {}}, append(item) {this.children.push(item);}};
}
async function scenario(failure) {
    const form = element();
    const button = element();
    const label = element();
    const summary = element();
    const errors = element();
    const status = element();
    const handlers = {};
    form.dataset = {
        plugins: JSON.stringify(['one', 'two', 'three'].map(slug => ({slug, name: slug}))),
        updateUrl: '/admin/plugins/update', progressLabel: ':current / :total :name',
        resultLabel: 'Updated :updated; skipped :skipped; failed :failed', interruptedLabel: 'Interrupted'
    };
    form.addEventListener = (type, handler) => {handlers[type] = handler;};
    form.querySelector = selector => selector.includes('label') ? label : button;
    status.querySelector = selector => selector.includes('summary') ? summary : errors;
    const controls = [button, element()];
    controls[1].disabled = true;
    const calls = [];
    const savedResults = new Map();
    const windowHandlers = {};
    let reloads = 0;
    let hasForm = true;
    let active = 0, maxActive = 0;
    const context = {
        document: {
            readyState: 'complete',
            querySelector: selector => selector === '[data-plugin-update-all]' ? (hasForm ? form : null) : selector === '[data-plugin-update-all-status]' ? status : null,
            querySelectorAll: selector => selector.startsWith('form[action=') ? [button] : controls,
            createElement: () => element()
        },
        FormData: class {constructor() {this.data = {csrf: 'fixture-csrf'};} set(key, value) {this.data[key] = value;}},
        fetch: async (url, options) => {
            calls.push({url, ...options.body.data, headers: options.headers});
            maxActive = Math.max(maxActive, ++active);
            await new Promise(resolve => setTimeout(resolve, 5)); active--;
            const second = calls.length === 2;
            if (second && failure === 'network') throw new Error('Network');
            const bad = second && ['package', 'csrf'].includes(failure);
            return {
                ok: !bad, redirected: second && failure === 'redirect', status: bad ? (failure === 'csrf' ? 419 : 500) : 200,
                headers: {get: () => second && failure === 'html' ? 'text/html' : 'application/json'},
                json: async () => bad ? {status: false, message: 'Package error'} : {status: true, result: {status: calls.length === 3 ? 'current' : 'success'}}
            };
        },
        window: {
            fetch: true, FormData: true,
            addEventListener(type, handler) {windowHandlers[type] = handler;},
            sessionStorage: {
                getItem: key => savedResults.get(key) || null,
                setItem: (key, value) => savedResults.set(key, value),
                removeItem: key => savedResults.delete(key),
            },
            location: {
                pathname: '/admin/plugins',
                reload() {
                    assert.equal(active, 0, 'Refresh waits for pending update responses');
                    let blocked = false;
                    windowHandlers.beforeunload({preventDefault() {blocked = true;}});
                    assert.equal(blocked, false, 'Automatic refresh is not blocked by the running-queue guard');
                    reloads++;
                },
            },
        },
    };
    vm.runInNewContext(source, context);
    const event = {preventDefault() {}, stopPropagation() {}};
    await handlers.submit(event);
    assert.equal(calls.length, 0, 'First submission waits for shared confirmation');
    form.dataset.deleteConfirmed = '1';
    const running = handlers.submit(event);
    await handlers.submit(event);
    await running;
    assert.equal(maxActive, 1, 'Sequential requests and double-click guard');
    assert.equal(calls.length, ['csrf', 'network', 'html', 'redirect'].includes(failure) ? 2 : 3);
    assert.ok(calls.every(call => call.csrf === 'fixture-csrf' && call.headers['X-Requested-With'] === 'XMLHttpRequest'));
    assert.equal(errors.children.length, failure === 'none' ? 0 : 1);
    assert.ok(!/:updated|:skipped|:failed/.test(summary.textContent));
    assert.equal(reloads, 1, 'Exactly one automatic refresh after the queue finishes or stops');
    assert.equal(button.disabled, true);
    assert.equal(controls[1].disabled, true, 'Pre-disabled buttons remain disabled');
    assert.equal(savedResults.size, failure === 'none' ? 0 : 1, 'Only failures persist across refresh');
    // On the refreshed page, the update form may disappear. Show saved errors
    // once without restarting the queue or refreshing again.
    hasForm = false;
    errors.children = [];
    status.hidden = true;
    vm.runInNewContext(source, context);
    assert.equal(errors.children.length, failure === 'none' ? 0 : 1);
    assert.equal(status.hidden, failure === 'none');
    assert.equal(savedResults.size, 0, 'Saved result is consumed after refresh');
    assert.equal(reloads, 1, 'Rendering the saved result never restarts a refresh loop');
}
(async () => {
    for (const failure of ['none', 'package', 'csrf', 'network', 'html', 'redirect']) await scenario(failure);
    console.log('Plugin update queue tests passed: 6 success/failure/interruption scenarios.');
})().catch(error => {console.error(error); process.exitCode = 1;});
