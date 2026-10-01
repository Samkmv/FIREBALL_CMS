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
    const refresh = element();
    const status = element();
    const handlers = {};
    form.dataset = {
        plugins: JSON.stringify(['one', 'two', 'three'].map(slug => ({slug, name: slug}))),
        updateUrl: '/admin/plugins/update', progressLabel: ':current / :total :name',
        resultLabel: 'Updated :updated; skipped :skipped; failed :failed', interruptedLabel: 'Interrupted'
    };
    form.addEventListener = (type, handler) => {handlers[type] = handler;};
    form.querySelector = selector => selector.includes('label') ? label : button;
    status.querySelector = selector => selector.includes('summary') ? summary : selector.includes('errors') ? errors : refresh;
    const controls = [button, element()];
    controls[1].disabled = true;
    const calls = [];
    let active = 0, maxActive = 0;
    const context = {
        document: {
            readyState: 'complete',
            querySelector: selector => selector === '[data-plugin-update-all]' ? form : selector === '[data-plugin-update-all-status]' ? status : null,
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
        window: {fetch: true, FormData: true, addEventListener() {}},
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
    assert.equal(refresh.hidden, false);
    assert.equal(button.disabled, true);
    assert.equal(controls[1].disabled, true, 'Pre-disabled buttons remain disabled');
}
(async () => {
    for (const failure of ['none', 'package', 'csrf', 'network', 'html', 'redirect']) await scenario(failure);
    console.log('Plugin update queue tests passed: 6 success/failure/interruption scenarios.');
})().catch(error => {console.error(error); process.exitCode = 1;});
