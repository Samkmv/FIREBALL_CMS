// Exercise the real editor handlers with a small synthetic DOM; never changes the CMS database.
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(path.join(__dirname, '../public/assets/default/js/admin-widgets.js'), 'utf8');
const checks = [];
const verify = (passed, description) => { assert.ok(passed, description); checks.push(description); };

function scenario({ allowSecondArea = false, saveFails = false } = {}) {
    class Element {
        constructor(kind, dataset = {}) {
            this.kind = kind; this.dataset = dataset; this.children = []; this.parentElement = null;
            this.style = {}; this.attributes = {}; this.listeners = {}; this.hidden = false;
            const classes = new Set();
            this.classList = { add: value => classes.add(value), remove: value => classes.delete(value), contains: value => classes.has(value) };
        }
        matches(selector) {
            return selector === '[data-widget-area]' ? this.kind === 'zone'
                : selector === '[data-widget-handle]' ? this.kind === 'handle'
                : selector === '[data-widget-id]' ? this.kind === 'card' : false;
        }
        closest(selector) { return this.matches(selector) ? this : this.parentElement?.closest(selector) || null; }
        contains(element) { return Boolean(element && (element === this || this.children.some(child => child.contains(element)))); }
        append(element) { this.insertBefore(element, null); }
        insertBefore(element, next) {
            if (element.parentElement) element.parentElement.children.splice(element.parentElement.children.indexOf(element), 1);
            this.children.splice(next ? this.children.indexOf(next) : this.children.length, 0, element);
            element.parentElement = this;
        }
        get previousElementSibling() { return this.parentElement?.children[this.parentElement.children.indexOf(this) - 1] || null; }
        get nextElementSibling() { return this.parentElement?.children[this.parentElement.children.indexOf(this) + 1] || null; }
        querySelector() { return null; }
        querySelectorAll(selector) {
            if (selector === '[data-widget-area]') return this.children.flatMap(child => child.kind === 'zone' ? [child] : child.querySelectorAll(selector));
            if (selector === '.widget-drop-active') return [];
            return [];
        }
        setAttribute(name, value) { this.attributes[name] = value; }
        removeAttribute(name) { delete this.attributes[name]; }
        addEventListener(name, callback) { this.listeners[name] = callback; }
        setPointerCapture(id) { this.capturedPointer = id; }
        getBoundingClientRect() {
            if (this.kind === 'root') return { top: 50, left: 100, width: 900 };
            if (this.kind === 'zone') return { top: 100, left: this.dataset.widgetArea === 'posts.sidebar' ? 120 : 520, width: 360 };
            const zone = this.parentElement;
            const top = zone.getBoundingClientRect().top + zone.children.indexOf(this) * 96;
            return { top, bottom: top + 80, height: 80 };
        }
    }
    const root = new Element('root', { revision: '1', saveUrl: '/admin/widgets/save', error: 'Save failed' });
    const content = new Element('content'); root.append(content);
    const status = new Element('status');
    const firstArea = new Element('zone', { widgetArea: 'posts.sidebar' });
    const secondArea = new Element('zone', { widgetArea: 'test.footer' });
    content.append(firstArea); content.append(secondArea);
    const cards = [1, 2, 3].map(id => {
        const card = new Element('card', { widgetId: String(id), allowedAreas: JSON.stringify(allowSecondArea ? ['posts.sidebar', 'test.footer'] : ['posts.sidebar']) });
        const handle = new Element('handle'); card.append(handle); card.handle = handle;
        firstArea.append(card); return card;
    });
    root.querySelector = selector => selector === '[data-widget-content]' ? content : selector === '[data-widget-status]' ? status : { value: 'TEST-CSRF' };
    const requests = [];
    const document = { readyState: 'complete', listeners: {}, hit: null,
        getElementById: () => root, createElement: () => new Element('marker'),
        addEventListener(name, callback) { this.listeners[name] = callback; },
        elementFromPoint() { return this.hit; },
    };
    vm.runInNewContext(source, {
        document, window: { getComputedStyle: () => ({ rowGap: '16px' }) },
        fetch: async (url, options) => {
            requests.push({ url, options, payload: JSON.parse(options.body) });
            return { ok: !saveFails, json: async () => ({ status: saveFails ? 'error' : 'ok', revision: 2, message: 'Saved' }) };
        },
    });
    const marker = root.children.find(element => element.kind === 'marker');
    const outside = new Element('outside');
    const event = target => ({ target, preventDefault() { this.prevented = true; }, dataTransfer: { setData() {} } });
    return { root, content, firstArea, secondArea, cards, marker, outside, requests, document,
        order: () => firstArea.children.map(card => Number(card.dataset.widgetId)),
        start: card => root.listeners.dragstart(event(card.handle)),
        over: (zone, y) => document.listeners.dragover({ ...event(zone), clientY: y }),
        drop: () => root.listeners.drop(event(firstArea)),
        cancel: () => root.listeners.dragend(),
        touchStart: card => root.listeners.pointerdown({ ...event(card.handle), pointerType: 'touch', pointerId: 7 }),
        touchMove: (zone, y) => { document.hit = zone; root.listeners.pointermove({ pointerId: 7, clientX: 0, clientY: y }); },
        touchEnd: () => root.listeners.pointerup({ pointerId: 7 }),
        touchCancel: () => root.listeners.pointercancel({ pointerId: 7 }),
        flush: () => new Promise(setImmediate),
    };
}

(async () => {
    const start = scenario();
    verify(start.marker.hidden && start.marker.parentElement === start.root && start.marker.attributes['aria-hidden'] === 'true', 'Insertion marker is hidden initially and stays outside the saved widget lists');
    start.start(start.cards[2]); start.over(start.firstArea, 90);
    verify(!start.marker.hidden && start.marker.style.top === '50px' && start.marker.style.left === '20px' && start.marker.style.width === '360px' && String(start.order()) === '1,2,3', 'Dragging to the start shows a full-width line while keeping the list stable until drop');
    await start.drop(); await start.flush();
    verify(start.marker.hidden && start.requests.length === 1 && start.requests[0].payload.layout.length === 3 && start.requests[0].payload.layout[0].id === 3, 'Drop hides the line and saves only widget IDs in their new order');

    const middle = scenario(); middle.start(middle.cards[0]); middle.over(middle.firstArea, 270);
    verify(!middle.marker.hidden && middle.marker.style.top === '234px' && String(middle.order()) === '1,2,3', 'Middle insertion line is centred in the chosen gap without moving cards yet');
    await middle.cancel();
    verify(middle.marker.hidden && String(middle.order()) === '1,2,3' && middle.requests.length === 0, 'Cancelled native drag hides the line and restores the previous order');

    const end = scenario(); end.start(end.cards[0]); end.over(end.firstArea, 1000);
    verify(!end.marker.hidden && end.marker.style.top === '330px' && String(end.order()) === '1,2,3', 'Dragging to the end marks the final insertion gap while preserving list geometry');
    await end.cancel();

    const unchanged = scenario(); unchanged.start(unchanged.cards[2]); unchanged.over(unchanged.firstArea, 1000);
    await unchanged.drop(); await unchanged.flush();
    verify(unchanged.marker.hidden && String(unchanged.order()) === '1,2,3' && unchanged.requests.length === 0, 'Dropping a widget at its existing position clears the line without saving an unchanged layout');

    const invalid = scenario(); invalid.start(invalid.cards[0]); invalid.over(invalid.firstArea, 270); invalid.over(invalid.secondArea, 100);
    await invalid.drop();
    verify(invalid.marker.hidden && String(invalid.order()) === '1,2,3' && invalid.requests.length === 0, 'Forbidden target area removes the line and cannot commit the tentative move');
    const outside = scenario(); outside.start(outside.cards[0]); outside.over(outside.firstArea, 270); outside.over(outside.outside, 100);
    await outside.drop();
    verify(outside.marker.hidden && String(outside.order()) === '1,2,3' && outside.requests.length === 0, 'Leaving the editor clears the insertion marker and cancels an invalid drop');

    const failed = scenario({ saveFails: true }); failed.start(failed.cards[0]); failed.over(failed.firstArea, 270);
    await failed.drop(); await failed.flush();
    verify(failed.marker.hidden && String(failed.order()) === '1,2,3', 'Failed AJAX save clears the marker and rolls the moved widget back');

    const touch = scenario({ allowSecondArea: true }); touch.touchStart(touch.cards[0]); touch.touchMove(touch.secondArea, 110);
    verify(!touch.marker.hidden && touch.marker.style.left === '420px' && touch.marker.style.top === '50px', 'Touch drag shows the insertion line in an empty allowed area');
    await touch.touchEnd(); await touch.flush();
    verify(touch.marker.hidden && touch.requests.length === 1 && touch.requests[0].payload.layout.some(item => item.id === 1 && item.area === 'test.footer') && touch.requests[0].options.headers['X-CSRF-Token'] === 'TEST-CSRF', 'Touch release saves the move with CSRF and removes the line');
    const cancelledTouch = scenario(); cancelledTouch.touchStart(cancelledTouch.cards[0]); cancelledTouch.touchMove(cancelledTouch.firstArea, 270);
    await cancelledTouch.touchCancel();
    verify(cancelledTouch.marker.hidden && String(cancelledTouch.order()) === '1,2,3' && cancelledTouch.requests.length === 0, 'Cancelled touch gesture restores order and hides the marker');
    const lost = scenario(); lost.touchStart(lost.cards[0]); lost.touchMove(lost.firstArea, 270);
    await lost.root.listeners.lostpointercapture({ pointerId: 7 });
    verify(lost.marker.hidden && String(lost.order()) === '1,2,3', 'Lost pointer capture also clears the marker and rolls back the tentative move');

    fs.mkdirSync(path.join(__dirname, 'artifacts/widgets'), { recursive: true });
    fs.writeFileSync(path.join(__dirname, 'artifacts/widgets/drag-client-tests.json'), JSON.stringify({ passed: checks.length, checks }, null, 2));
    console.log(`${checks.length} widget drag checks passed.`);
})().catch(error => { console.error(error); process.exitCode = 1; });
