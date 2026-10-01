'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const rootPath = path.join(__dirname, '..');
const source = fs.readFileSync(path.join(rootPath, 'public/assets/default/js/chat-viewport.js'), 'utf8');
let checks = 0;
const check = (condition, message) => {assert.ok(condition, message); checks++;};
function fixture({standalone = true, width = 390, height = 844, visible = 784, header = 100} = {}) {
    const properties = new Map();
    const style = values => ({
        setProperty: (key, value) => values.set(key, value),
        getPropertyValue: key => values.get(key) || '',
        getPropertyPriority: () => '',
        removeProperty: key => values.delete(key)
    });
    const classes = () => {
        const values = new Set(standalone ? ['pwa-standalone'] : []);
        return {add: value => values.add(value), contains: value => values.has(value), toggle: (value, on) => on ? values.add(value) : values.delete(value)};
    };
    const root = {clientHeight: height, clientWidth: width, classList: classes(), style: style(properties)};
    const body = {classList: classes(), style: style(new Map())};
    const context = {
        document: {documentElement: root, body, addEventListener() {}, removeEventListener() {}, querySelector: () => ({getBoundingClientRect: () => ({height: header})})},
        window: {
            innerHeight: height, innerWidth: width, navigator: {standalone}, visualViewport: {height: visible, offsetTop: 0},
            matchMedia: query => ({matches: query.includes('max-width') ? context.window.innerWidth < 768 : standalone}),
            scrollTo: () => {throw new Error('Document scroll is not allowed');}
        }
    };
    vm.runInNewContext(fs.readFileSync(path.join(rootPath, 'public/assets/default/js/app-viewport.js'), 'utf8'), context);
    vm.runInNewContext(source, context);
    const sync = focused => {
        context.window.FireballChatViewport.sync(focused);
        const top = parseFloat(properties.get('--chat-mobile-viewport-top'));
        const height = parseFloat(properties.get('--chat-mobile-viewport-height'));
        const mobile = context.window.innerWidth < 768;
        const visibleBottom = mobile ? context.window.visualViewport.offsetTop + context.window.visualViewport.height : context.window.innerHeight;
        check(top + height === visibleBottom, 'Composer bottom follows visible viewport, not oversized layout height');
        check(height >= 0, 'Non-negative usable chat height');
    };
    return {context, properties, root, sync};
}
for (const standalone of [true, false]) {
    const test = fixture({standalone});
    const viewport = test.context.window.visualViewport;
    test.sync(false);
    check(parseFloat(test.properties.get('--chat-mobile-viewport-height')) === 684, 'Initial short visual viewport is respected');
    check(!test.root.classList.contains('chat-keyboard-visible'), 'Browser chrome alone is not a keyboard');
    viewport.height = 400;
    test.sync(true);
    check(test.root.classList.contains('chat-keyboard-visible'), 'Keyboard shrink detected');
    viewport.offsetTop = 384;
    test.sync(true);
    check(test.root.classList.contains('chat-keyboard-visible'), 'Safari pan cannot disguise the keyboard shrink');
    viewport.offsetTop = 0;
    // Safari can keep focus after dismissing the keyboard, or blur before the
    // viewport recovers. Geometry cannot switch back to innerHeight on blur.
    test.sync(false);
    check(test.root.classList.contains('chat-keyboard-visible'), 'Safe area stays suppressed during keyboard closing');
    viewport.height = 784;
    test.sync(true);
    check(!test.root.classList.contains('chat-keyboard-visible'), 'Keyboard state clears even when focus is retained');
    viewport.offsetTop = 20;
    viewport.height = 764;
    test.sync(false);
    check(test.properties.get('--chat-visual-viewport-top') === '20px', 'Header follows Safari viewport offset');
    check(test.properties.get('--chat-mobile-viewport-top') === '120px', 'Header and chat use the same coordinate system');
    viewport.offsetTop = 0;
    viewport.height = 844;
    test.sync(false);
    check(test.properties.get('--chat-mobile-viewport-height') === '744px', 'Full size recovers after delayed resize');
    // Rotation with a much smaller window must reset the keyboard baseline.
    test.context.window.innerWidth = test.root.clientWidth = 700;
    test.context.window.innerHeight = test.root.clientHeight = viewport.height = 390;
    test.sync(true);
    check(!test.root.classList.contains('chat-keyboard-visible'), 'Rotation does not leave a stale keyboard baseline');
}
for (const standalone of [true, false]) {
    const test = fixture({standalone, width: 1440, height: 1000, visible: 700});
    test.sync(true);
    check(!test.root.classList.contains('chat-keyboard-visible'), 'Desktop keeps layout viewport sizing');
    check(test.properties.get('--chat-mobile-viewport-top') === (standalone ? '0px' : '100px'), 'Desktop header behaviour retained');
}
const noViewport = fixture({visible: 844});
noViewport.context.window.visualViewport = null;
noViewport.context.window.FireballChatViewport.sync(false);
check(noViewport.properties.get('--chat-mobile-viewport-height') === '744px', 'Browsers without visualViewport retain layout fallback');

for (const file of ['public/assets/default/css/style.css', 'themes/default/assets/css/style.css']) {
    const css = fs.readFileSync(path.join(rootPath, file), 'utf8');
    check(!/\.chat-page\s*\{[^}]*100lvh/.test(css), 'No CSS rule can override measured chat height with 100lvh');
    check(css.includes('top: var(--chat-visual-viewport-top, 0px)'), 'Mobile header uses the shared offset');
}
for (const file of ['public/assets/default/js/chat.js', 'public/assets/default/js/chat-group.js', 'themes/default/assets/js/chat.js', 'themes/default/assets/js/chat-group.js']) {
    check(fs.readFileSync(path.join(rootPath, file), 'utf8').includes('window.FireballChatViewport.sync(focused)'), 'Direct and group runtime share initial paint sizing');
}
console.log(`Chat viewport tests passed: ${checks} checks (PWA/browser, focus/blur, delayed recovery, offset, rotation, desktop).`);
