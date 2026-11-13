import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import {createRequire} from 'node:module';

const directory = path.resolve(process.argv[2]);
const require = createRequire(path.join(directory, 'package.json'));
const {JSDOM} = require('jsdom');
const page = fs.readFileSync(path.join(directory, 'page.html'), 'utf8');
const snapshot = fs.readFileSync(path.join(directory, 'snapshot.html'), 'utf8');
const asset = fs.readFileSync('plugins/vpn-manager-v2/assets/vpn-manager-v2.js', 'utf8');
const dom = new JSDOM(page, {url: 'https://cms.example/admin/plugins/vpn-manager-v2/online',
    runScripts: 'outside-only', pretendToBeVisual: true});
const {window} = dom;
let interval;
let requests = 0;
let mode = 'success';
let finishSlow;
const response = () => ({ok: true, status: 200, redirected: false, json: async () => ({html: snapshot})});
window.setInterval = (callback, delay) => {
    assert.equal(delay, 10000);
    interval = callback;
    return 1;
};
window.clearInterval = () => { interval = null; };
window.fetch = async () => {
    requests++;
    if (mode === 'failure') throw new Error('Simulated network failure');
    if (mode === 'slow') return new Promise(resolve => { finishSlow = resolve; });
    return response();
};
const settle = async () => {
    await new Promise(resolve => setTimeout(resolve, 5));
    await new Promise(resolve => setTimeout(resolve, 5));
};
const document = window.document;
const visible = selector => [...document.querySelectorAll(selector)].filter(row => !row.hidden && !row.classList.contains('d-none'));
const ids = () => visible('tbody [data-vpn-online-row]').map(row => Number(row.dataset.vpnOnlineRow));
const change = (element, event = 'change') => element.dispatchEvent(new window.Event(event, {bubbles: true}));

try {
    window.eval(asset);
    document.dispatchEvent(new window.Event('DOMContentLoaded'));
    await settle();
    assert.equal(requests, 1);
    assert.deepEqual(ids(), [101, 102, 103]);
    assert.equal(visible('article[data-vpn-online-row]').length, 3);
    assert.equal(document.querySelectorAll('.fb-vpn-stat-card').length, 4);
    assert.equal(document.querySelector('[data-vpn-online-error]').hidden, true);

    const search = document.querySelector('[data-vpn-online-search]');
    const scope = document.querySelector('[data-vpn-online-scope]');
    const sort = document.querySelector('[data-vpn-online-sort-select]');
    search.value = '78—76';
    change(search, 'input');
    assert.deepEqual(ids(), [101, 102]);
    assert.equal(requests, 1, 'Instant search uses current rows without extra server requests.');
    search.value = '';
    scope.value = 'all';
    change(scope);
    assert.equal(ids().length, 5);
    assert.equal(visible('article[data-vpn-online-row]').length, 5);

    sort.value = 'subscription';
    change(sort);
    assert.deepEqual(ids(), [101, 102, 103, 105, 104]);
    document.querySelector('[data-vpn-online-direction]').click();
    assert.deepEqual(ids(), [104, 103, 105, 102, 101]);
    document.querySelector('[data-vpn-online-sort="server"]').click();
    assert.equal(sort.value, 'server');
    assert.equal(document.querySelector('[data-vpn-online-sort="server"]').closest('th').getAttribute('aria-sort'), 'ascending');

    search.value = 'Netherlands';
    change(search, 'input');
    assert.deepEqual(ids(), [102]);
    interval();
    await settle();
    assert.equal(search.value, 'Netherlands');
    assert.equal(scope.value, 'all');
    assert.equal(sort.value, 'server');
    assert.deepEqual(ids(), [102]);

    mode = 'failure';
    interval();
    await settle();
    assert.equal(document.querySelector('[data-vpn-online-error]').hidden, false);
    assert.deepEqual(ids(), [102], 'A failed refresh retains the previous rows and filters.');
    mode = 'slow';
    const before = requests;
    interval();
    interval();
    assert.equal(requests, before + 1, 'Polling never overlaps a pending request.');
    finishSlow(response());
    await settle();
    mode = 'success';
    Object.defineProperty(document, 'hidden', {configurable: true, get: () => true});
    const hiddenRequests = requests;
    interval();
    assert.equal(requests, hiddenRequests);
    Object.defineProperty(document, 'hidden', {configurable: true, get: () => false});
    document.dispatchEvent(new window.Event('visibilitychange'));
    await settle();
    assert.equal(requests, hiddenRequests + 1);

    search.value = 'nonexistent name';
    change(search, 'input');
    assert.equal(ids().length, 0);
    assert.equal(document.querySelector('[data-vpn-online-empty]').hidden, false);
    assert.equal(document.querySelector('[data-vpn-online-table]').hidden, true);
    assert.equal(document.querySelectorAll('[data-vpn-online-results] script').length, 0);
    window.dispatchEvent(new window.Event('pagehide'));
    assert.equal(interval, null);
    const restored = new window.Event('pageshow');
    Object.defineProperty(restored, 'persisted', {value: true});
    window.dispatchEvent(restored);
    await settle();
    assert.equal(typeof interval, 'function');
    assert.equal(search.value, 'nonexistent name');
    console.log('PASS online search, sorting, mobile cards, 10-second polling, partial failures, visibility and back navigation.');
} finally {
    window.dispatchEvent(new window.Event('pagehide'));
    window.close();
}
