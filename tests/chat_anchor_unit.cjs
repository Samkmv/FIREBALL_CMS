'use strict';
// Execute the production resize/anchor functions. No network or DOM writes.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
for (const assetRoot of ['public/assets/default', 'themes/default/assets']) {
    for (const group of [false, true]) {
        const source = fs.readFileSync(path.join(__dirname, '..', assetRoot, 'js', group ? 'chat-group.js' : 'chat.js'), 'utf8');
        const shell = group
            ? source.slice(source.indexOf('    let viewportFrame ='), source.indexOf('    let source ='))
            : source.slice(source.indexOf('    const mobileFullscreenQuery ='), source.indexOf('    const fetchUrl ='));
        const resize = group
            ? source.slice(source.indexOf('    const resizeInput ='), source.indexOf("    form.on('submit'"))
            : source.slice(source.indexOf('    const resizeMessageInput ='), source.indexOf('    const formatBytes ='));
        let scrollTop = 1400;
        const box = {scrollHeight: 2000, clientHeight: 600};
        Object.defineProperty(box, 'scrollTop', {get: () => scrollTop, set: value => {
            scrollTop = Math.max(0, Math.min(value, box.scrollHeight - box.clientHeight));
        }});
        const field = {style: {}, dataset: {}, value: '', scrollHeight: 44};
        const app = {0: {contains: () => false, style: {setProperty() {}}}, find: () => [box]};
        const frames = new Map();
        let sequence = 0;
        let height = 600;
        const requestAnimationFrame = callback => {frames.set(++sequence, callback); return sequence;};
        const cancelAnimationFrame = id => frames.delete(id);
        const window = {
            matchMedia: () => ({matches: true, addEventListener() {}}),
            addEventListener() {}, visualViewport: {addEventListener() {}},
            requestAnimationFrame, cancelAnimationFrame, setTimeout: () => 1,
            getComputedStyle: () => ({minHeight: '44px', maxHeight: '132px'}),
            FireballChatViewport: {sync() {box.clientHeight = height;}}
        };
        const context = vm.createContext({window, document: {activeElement: null, addEventListener() {}},
            requestAnimationFrame, cancelAnimationFrame, clearTimeout() {},
            chatApp: app, app, box: [box], messageInput: [field], input: [field]});
        vm.runInContext(shell + resize + '\n globalThis.api = {schedule: ' + (group ? 'scheduleViewport' : 'scheduleMobileFullscreenSync') + ', resize: ' + (group ? 'resizeInput' : 'resizeMessageInput') + '};', context);
        const flush = () => {const callbacks = [...frames.values()]; frames.clear(); callbacks.forEach(callback => callback());};
        // Open keyboard while at the latest message.
        height = 300;
        context.api.schedule(); flush(); flush();
        assert.equal(box.scrollTop, 1700, 'Latest message stays at the bottom');
        // Finish the session by recreating it with a history-reading anchor.
        frames.clear();
        box.clientHeight = height = 600; box.scrollTop = 900;
        vm.runInContext('viewportAnchor = null;', context);
        context.api.schedule(); flush(); flush();
        // Shrink, then grow while textarea resize competes for the same frame.
        height = 300; context.api.schedule(); flush(); context.api.resize(); flush();
        assert.equal(box.scrollTop, 1200, 'Reading history preserves distance from the latest message');
        height = 600; context.api.schedule(); flush(); context.api.resize(); flush();
        assert.equal(box.scrollTop, 900, 'Textarea resize cannot override the keyboard-close anchor');
        console.log('PASS ' + assetRoot + ' ' + (group ? 'group' : 'direct') + ': bottom/history anchors and competing resize');
    }
}
