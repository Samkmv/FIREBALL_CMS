'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const rootPath = path.resolve(__dirname, '..');
let checks = 0;
function equal(actual, expected, message) { checks++; assert.deepEqual(actual, expected, message); }
function environment(base, initial = {}) {
    const state = {width: 390, layoutHeight: 844, nativeHeight: 844, visualHeight: 763, top: 0, scale: 1, standalone: true, touch: true, headerHeight: 65, ...initial};
    function element() {
        const classes = new Set();
        const styles = new Map();
        return {
            classList: {contains: name => classes.has(name), add: name => classes.add(name), toggle(name, on) { on ? classes.add(name) : classes.delete(name); }},
            style: {setProperty: (name, value) => styles.set(name, value), getPropertyValue: name => styles.get(name) || '', getPropertyPriority: () => '', removeProperty: name => styles.delete(name)},
            setAttribute() {}, appendChild() {}, addEventListener() {}, removeEventListener() {}, remove() {},
            getBoundingClientRect: () => ({height: state.nativeHeight}),
        };
    }
    const html = element();
    const body = element();
    const document = {documentElement: html, body, activeElement: null, createElement: element, addEventListener() {}, removeEventListener() {},
        querySelector: () => ({getBoundingClientRect: () => ({height: state.headerHeight})})};
    const window = {
        navigator: {standalone: state.standalone}, location: {search: ''},
        matchMedia: query => ({matches: query.includes('standalone') ? state.standalone :
            (state.width < 768 || (state.touch && /hover|pointer/.test(query))), addEventListener() {}, removeEventListener() {}}),
        visualViewport: {get height() {return state.visualHeight;}, get offsetTop() {return state.top;}, get scale() {return state.scale;}},
        get innerWidth() {return state.width;}, get innerHeight() {return state.layoutHeight;},
        requestAnimationFrame() {}, cancelAnimationFrame() {},
    };
    const context = vm.createContext({window, document, URLSearchParams});
    for (const file of ['app-viewport.js', 'chat-viewport.js']) vm.runInContext(fs.readFileSync(path.join(base, 'js', file), 'utf8'), context);
    const sync = focused => {
        window.FireballChatViewport.sync(focused);
        return {
            top: html.style.getPropertyValue('--chat-mobile-viewport-top'),
            height: html.style.getPropertyValue('--chat-mobile-viewport-height'),
            keyboard: html.classList.contains('chat-keyboard-visible'),
            compact: html.classList.contains('chat-compact-viewport'),
        };
    };
    return {state, window, document, html, sync};
}
for (const assetRoot of ['public/assets/default', 'themes/default/assets']) {
    const base = path.join(rootPath, assetRoot);
    const ios = environment(base);
    equal(ios.sync(false), {top: '65px', height: '779px', keyboard: false, compact: false}, 'Closed PWA includes native safe area once');
    equal(ios.html.style.getPropertyValue('--fb-modal-viewport-height'), '844px', 'Modal uses full viewport including site header area');
    Object.assign(ios.state, {visualHeight: 430, top: 120});
    equal(ios.sync(true), {top: '185px', height: '365px', keyboard: true, compact: true}, 'Panned keyboard viewport fits between its visible top and bottom');
    equal(ios.html.style.getPropertyValue('--fb-modal-viewport-height'), '430px', 'Modal matches visual keyboard height');
    equal(ios.html.style.getPropertyValue('--fb-modal-viewport-top'), '120px', 'Modal matches visual keyboard offset');
    Object.assign(ios.state, {visualHeight: 763, top: 120});
    equal(ios.sync(false), {top: '65px', height: '779px', keyboard: false, compact: false}, 'Dismiss restores full native height and discards stale offset');
    const tablet = environment(base, {width: 1024, nativeHeight: 768, layoutHeight: 768, visualHeight: 768, headerHeight: 0});
    tablet.sync(false);
    Object.assign(tablet.state, {visualHeight: 320, top: 40});
    equal(tablet.sync(true), {top: '40px', height: '320px', keyboard: true, compact: true}, 'Touch tablet/landscape phone tracks keyboard above desktop breakpoint');
    Object.assign(tablet.state, {width: 390, nativeHeight: 844, layoutHeight: 844, visualHeight: 430, top: 0});
    equal(tablet.sync(true).keyboard, true, 'Rotation with keyboard remeasures native extent');
    const android = environment(base, {standalone: false, visualHeight: 844});
    android.sync(false);
    Object.assign(android.state, {visualHeight: 430, layoutHeight: 430});
    equal(android.sync(true).height, '365px', 'Resizing Android viewport subtracts header once');
    equal(android.sync(true).keyboard, true, 'Android retains pre-keyboard extent');
    const desktop = environment(base, {width: 1440, touch: false, standalone: false, layoutHeight: 900, visualHeight: 300});
    equal(desktop.sync(true), {top: '65px', height: '835px', keyboard: false, compact: false}, 'Desktop layout remains full-height');
    const beforeZoom = ios.sync(false);
    Object.assign(ios.state, {scale: 2, visualHeight: 200});
    equal(ios.sync(true), beforeZoom, 'Pinch zoom does not resize layout');
    // Actual controller API, with browser-like clamping of scrollTop.
    const box = {scrollHeight: 2000, clientHeight: 600, _top: 300,
        get scrollTop() {return this._top;}, set scrollTop(top) {this._top = Math.min(top, this.scrollHeight - this.clientHeight);}};
    const api = ios.window.FireballChatViewport;
    const reading = api.captureMessageAnchor(box);
    box.clientHeight = 250;
    api.restoreMessageAnchor(box, reading);
    equal(box.scrollTop, 300, 'Keyboard does not move the line being read');
    box.scrollTop = box.scrollHeight - box.clientHeight;
    const latest = api.captureMessageAnchor(box);
    box.clientHeight = 600;
    api.restoreMessageAnchor(box, latest);
    equal(box.scrollTop, 1400, 'Latest message remains at bottom after dismiss');
    equal(api.captureMessageAnchor(null), null, 'Early markup without messages is safe');
    const composer = {scrollTop: 0, scrollHeight: 240, clientHeight: 100, getBoundingClientRect: () => ({top: 0, bottom: 100})};
    const row = {getBoundingClientRect: () => ({top: 118 - composer.scrollTop, bottom: 162 - composer.scrollTop})};
    const input = {closest: selector => selector.includes('__composer') ? composer : row};
    ios.window.getComputedStyle = () => ({paddingTop: '8px', paddingBottom: '8px'});
    ios.document.activeElement = input;
    api.keepComposerInputVisible(input);
    equal(composer.scrollTop, 70, 'Reply panel scrolls locally to keep full input/send row visible');
    api.keepComposerInputVisible(input);
    equal(composer.scrollTop, 70, 'No repeated scroll when the row already fits');
    const modalBody = {scrollTop: 0, scrollHeight: 600, clientHeight: 120, getBoundingClientRect: () => ({top: 0, bottom: 120})};
    const modalInput = {matches: () => true, closest: () => modalBody, getBoundingClientRect: () => ({top: 180 - modalBody.scrollTop, bottom: 232 - modalBody.scrollTop})};
    ios.document.activeElement = modalInput;
    api.keepModalInputVisible();
    equal(modalBody.scrollTop, 120, 'Modal scrolls its body to keep focused field above keyboard');
    api.keepModalInputVisible();
    equal(modalBody.scrollTop, 120, 'Visible modal field does not cause repeated scrolling');


}
console.log(`Chat viewport checks passed: ${checks}`);
