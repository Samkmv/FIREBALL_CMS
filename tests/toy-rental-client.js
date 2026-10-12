'use strict';
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(path.join(__dirname, '../plugins/toy-car-rental/assets/toy-rental.js'), 'utf8');
let checks = 0;

// Run the actual panel script against one metered card and a deterministic server clock.
function renderedCharge(elapsedMs) {
    const startMs = 1700000000000;
    const clock = startMs + elapsedMs;
    const classes = { add() {}, remove() {}, toggle() {} };
    const cost = { textContent: '' };
    const badge = { textContent: '', className: '' };
    const hint = { classList: classes };
    const card = {
        dataset: { rideId: '1', carId: '1', carLabel: 'Test car' },
        classList: classes,
        querySelector(selector) {
            if (selector === '[data-toy-rental-live-cost]') return cost;
            if (selector === '[data-toy-rental-status]') return badge;
            if (selector === '[data-toy-rental-time-up]') return hint;
            return null;
        },
    };
    const timer = {
        dataset: { billingType: 'metered', startMs: String(startMs), endMs: String(clock + 60000), serverNowMs: String(clock), pricePerMinute: '20' },
        textContent: '', classList: classes, closest: () => card,
    };
    const grid = {
        addEventListener() {},
        querySelector: () => null,
        querySelectorAll(selector) {
            if (selector === '[data-toy-rental-timer]') return [timer];
            if (selector === '[data-toy-rental-card]') return [card];
            return [];
        },
    };
    const document = {
        readyState: 'complete', hidden: false, addEventListener() {},
        querySelector(selector) { return selector === '[data-toy-rental-grid]' ? grid : null; },
    };
    class PanelDate extends Date { static now() { return clock; } }
    const window = { toyRentalSettings: { maxRideMinutes: 120, soundEnabled: false }, setInterval() {} };
    vm.runInNewContext(source, { window, document, Date: PanelDate, URLSearchParams }, { timeout: 1000 });
    return { timer: timer.textContent, charge: cost.textContent };
}

for (const [elapsed, timer, charge] of [
    [0, '00:00', '20.00'], [59000, '00:59', '20.00'], [60000, '01:00', '20.00'],
    [60999, '01:00', '20.00'], [61000, '01:01', '40.00'],
    [540000, '09:00', '180.00'], [540999, '09:00', '180.00'],
    [541000, '09:01', '200.00'], [552000, '09:12', '200.00'],
    [599000, '09:59', '200.00'], [600000, '10:00', '200.00'],
    [600999, '10:00', '200.00'], [601000, '10:01', '220.00'],
    [7200000, '120:00', '2400.00'], [9000000, '120:00', '2400.00'],
]) {
    assert.deepEqual(renderedCharge(elapsed), { timer, charge }, 'Displayed seconds and started-minute charge must agree: ' + elapsed);
    checks++;
}
console.log(checks + ' actual panel timer/billing checks passed; no browser, requests or audio.');
