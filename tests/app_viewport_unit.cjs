'use strict';
// Model tests, not a claim of native iPhone keyboard coverage.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../public/assets/default/js/app-viewport.js'), 'utf8');
const target = () => {
    const listeners = new Map();
    return {
        listeners,
        addEventListener(type, callback) {if (!listeners.has(type)) listeners.set(type, new Set()); listeners.get(type).add(callback);},
        removeEventListener(type, callback) {listeners.get(type)?.delete(callback);},
        emit(type, event = {}) {listeners.get(type)?.forEach(callback => callback(event));}
    };
};
const style = () => {
    const properties = new Map();
    return {
        getPropertyValue: key => properties.get(key)?.[0] || '',
        getPropertyPriority: key => properties.get(key)?.[1] || '',
        setProperty: (key, value, priority = '') => properties.set(key, [value, priority]),
        removeProperty: key => properties.delete(key)
    };
};
function fixture() {
    const root = {clientHeight: 844, clientWidth: 390, style: style()};
    const body = {style: style()};
    body.style.setProperty('position', 'relative', 'important');
    const document = Object.assign(target(), {documentElement: root, body, activeElement: null});
    const viewport = Object.assign(target(), {height: 784, width: 390, offsetTop: 0, scale: 1});
    const frames = new Map();
    let sequence = 0;
    const window = Object.assign(target(), {
        innerHeight: 844, innerWidth: 390, visualViewport: viewport,
        matchMedia(media) {return Object.defineProperty(target(), 'matches', {get() {return !media.includes('display-mode') && window.innerWidth < (media.includes('991') ? 992 : 768);}});},
        requestAnimationFrame(callback) {frames.set(++sequence, callback); return sequence;},
        cancelAnimationFrame(id) {frames.delete(id);},
        getComputedStyle: element => ({overflowY: element.overflowY || 'visible'}),
        getSelection: () => ({isCollapsed: true}),
        scrollTo() {throw new Error('No programmatic document scrolling during keyboard transitions');}
    });
    const context = vm.createContext({window, document});
    vm.runInContext(source, context);
    const changes = [];
    const controller = window.FireballAppViewport.create({onChange: value => changes.push(value)});
    const flush = () => {const pending = [...frames.values()]; frames.clear(); pending.forEach(callback => callback());};
    return {window, document, root, body, viewport, frames, changes, controller, flush};
}
const test = fixture();
test.controller.sync();
assert.equal(test.body.style.getPropertyValue('position'), 'relative', 'Document is not a second fixed viewport');
assert.equal(test.body.style.getPropertyValue('min-height'), '0', 'PWA 100svh minimum cannot expand the root');
test.document.activeElement = {matches: () => true};
test.viewport.height = 400;
test.viewport.offsetTop = 384;
test.viewport.emit('resize');
test.viewport.emit('scroll');
test.document.emit('focusin');
assert.equal(test.frames.size, 1, 'Viewport updates coalesce into one layout per frame');
test.flush();
assert.equal(test.changes.at(-1).keyboard, true, 'A large pan does not cancel the keyboard height');
assert.equal(test.changes.at(-1).height, 400);
assert.equal(test.changes.at(-1).top, 384);
test.document.activeElement = null;
test.document.emit('focusout');
test.flush();
assert.equal(test.changes.at(-1).keyboard, true, 'Blur is not proof of keyboard dismissal');
test.viewport.height = 784;
test.viewport.offsetTop = 0;
test.viewport.emit('resize');
test.flush();
assert.equal(test.changes.at(-1).keyboard, false);
test.viewport.scale = 2;
const beforeZoom = test.changes.length;
test.viewport.emit('resize');
test.flush();
assert.equal(test.changes.length, beforeZoom, 'Pinch zoom does not shrink the app layout');
test.viewport.scale = 1;

const scroller = {nodeType: 1, parentElement: test.body, overflowY: 'auto', scrollHeight: 1000, clientHeight: 400, scrollTop: 100};
const gesture = (targetElement, deltaX, deltaY, count = 1) => {
    let blocked = false;
    const points = (x, y) => Array.from({length: count}, () => ({clientX: x, clientY: y}));
    test.document.emit('touchstart', {target: targetElement, touches: points(50, 100)});
    test.document.emit('touchmove', {cancelable: true, touches: points(50 + deltaX, 100 + deltaY), preventDefault() {blocked = true;}});
    return blocked;
};
assert.equal(gesture(scroller, 0, -20), false, 'Messages/settings can scroll down');
assert.equal(gesture(scroller, 0, 20), false, 'Messages/settings can scroll up');
scroller.scrollTop = 0;
assert.equal(gesture(scroller, 0, 20), true, 'Top-edge gestures cannot pan the root');
scroller.scrollTop = 600;
assert.equal(gesture(scroller, 0, -20), true, 'Bottom-edge gestures cannot expose a blank native root strip');
const nested = {nodeType: 1, parentElement: scroller, overflowY: 'auto', scrollHeight: 200, clientHeight: 100, scrollTop: 100};
scroller.scrollTop = 100;
assert.equal(gesture(nested, 0, -20), false, 'A parent inner scroller may consume a nested edge gesture');
assert.equal(gesture(test.body, 20, 0), false, 'Horizontal gestures are preserved');
assert.equal(gesture(test.body, 0, -20, 2), false, 'Multitouch is preserved');
test.window.getSelection = () => ({isCollapsed: false});
assert.equal(gesture(test.body, 0, -20), false, 'Text selection is preserved');
test.window.getSelection = () => ({isCollapsed: true});
test.viewport.scale = 2;
assert.equal(gesture(test.body, 0, -20), false, 'A zoomed visual viewport may be panned');
test.viewport.scale = 1;

test.window.innerWidth = 1440;
test.controller.sync();
assert.equal(test.changes.at(-1).mobile, false);
assert.equal(test.body.style.getPropertyValue('position'), 'relative', 'Desktop restores preexisting body styles');
assert.equal(test.body.style.getPropertyPriority('position'), 'important');
assert.equal(test.document.listeners.get('touchmove').size, 0);
test.window.innerWidth = 390;
test.controller.sync();
test.viewport.emit('scroll');
test.controller.destroy();
test.flush();
assert.equal(test.body.style.getPropertyValue('position'), 'relative');
assert.equal(test.root.style.getPropertyValue('overflow'), '');
assert.equal(test.viewport.listeners.get('resize').size, 0, 'Destroy removes viewport listeners');
assert.equal(test.frames.size, 0, 'Destroy cancels pending layout work');

const multiple = fixture();
const second = multiple.window.FireballAppViewport.create({observe: false});
multiple.controller.sync();
second.sync();
multiple.controller.destroy();
assert.equal(multiple.body.style.getPropertyValue('overflow'), 'hidden', 'One owner cannot release another shell');
second.destroy();
assert.equal(multiple.body.style.getPropertyValue('position'), 'relative');
const pwa = fixture();
pwa.window.navigator = {standalone: true};
pwa.viewport.offsetTop = 20;
pwa.controller.sync();
assert.equal(pwa.changes.at(-1).height, 844, 'Closed PWA cannot reserve a second 60px safe-area gap');
assert.equal(pwa.changes.at(-1).top, 0, 'Closed PWA cannot keep a stale keyboard offset');
pwa.document.activeElement = {matches: () => true};
pwa.viewport.height = 400;
pwa.viewport.offsetTop = 384;
pwa.controller.sync();
assert.equal(pwa.changes.at(-1).height, 400, 'PWA keeps visual height above keyboard, not 100vh');
assert.equal(pwa.changes.at(-1).top, 384);
pwa.viewport.height = 784;
pwa.viewport.offsetTop = 20;
pwa.controller.sync();
assert.equal(pwa.changes.at(-1).height, 844, 'Full extent recovers even if input stays focused');
assert.equal(pwa.changes.at(-1).top, 0);
console.log('PASS shared app viewport: keyboard/pan/blur recovery, touch boundaries, zoom, event coalescing, desktop and cleanup');
