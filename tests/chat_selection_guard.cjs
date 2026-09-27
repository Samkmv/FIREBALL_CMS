'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

// Execute the production guards against a minimal Selection/DOM double.
for (const assetRoot of ['public/assets/default', 'themes/default/assets']) {
    const read = name => fs.readFileSync(path.join(__dirname, '..', assetRoot, 'js', name), 'utf8');
    let selected = false;
    let spanning = false;
    const inside = {nodeType: 1};
    const outside = {nodeType: 1};
    const container = {contains: node => node === inside};
    const window = {getSelection: () => ({
        isCollapsed: !selected, rangeCount: selected ? 1 : 0,
        anchorNode: spanning ? outside : inside, focusNode: spanning ? outside : inside,
        toString: () => selected ? 'Selected message' : '',
        getRangeAt: () => ({intersectsNode: () => spanning === true}),
    })};
    const base = {window, Node: {TEXT_NODE: 3}, document: {hidden: false}};
    const direct = read('chat.js');
    const guard = direct.slice(direct.indexOf('    // FIREBALL_CHAT_SELECTION_GUARD'), direct.indexOf('        const box = messagesBox[0];\n        const force'));
    const context = vm.createContext({...base, messagesBox: [container]});
    vm.runInContext(`let state = {messages: []}; let rendered = []; let refreshes = 0;
        let chatRealtimeRefreshPending = false;
        const scheduleRealtimeRefresh = () => {refreshes++; chatRealtimeRefreshPending = false;};
        ${guard}
        rendered.push(messages);
    };
    globalThis.api = {renderMessages, flushDeferredMessageRender, hasMessageTextSelection,
        setMessages: value => state.messages = value,
        pending: () => chatRealtimeRefreshPending = true,
        stats: () => ({rendered, refreshes, messageRenderDeferred})};`, context);
    const a = context.api;
    selected = true;
    a.pending(); a.flushDeferredMessageRender();
    assert.equal(a.stats().refreshes, 0);
    selected = false; a.flushDeferredMessageRender();
    assert.equal(a.stats().refreshes, 1, 'SSE-only pending refresh must resume');
    selected = true;
    a.setMessages(['old']); a.renderMessages(['old'], 7);
    a.setMessages(['latest']); a.renderMessages(['latest'], 7);
    assert.equal(a.stats().rendered.length, 0);
    selected = false; a.flushDeferredMessageRender();
    assert.deepEqual(a.stats().rendered[0], ['latest']);
    a.flushDeferredMessageRender();
    assert.equal(a.stats().rendered.length, 1, 'Flush only once');
    selected = true; spanning = true;
    assert.equal(a.hasMessageTextSelection(), true, 'Spanning selections are protected');
    spanning = 'outside';
    assert.equal(a.hasMessageTextSelection(), false, 'Selections outside the thread do not block');

    selected = true; spanning = false;
    const group = read('chat-group.js');
    const groupGuard = group.slice(group.indexOf('    // FIREBALL_CHAT_SELECTION_GUARD'), group.indexOf('        const nextSignature = signature(messages);'));
    const groupContext = vm.createContext({...base, box: [container]});
    vm.runInContext(`let requestPending = false; let realtimeRefreshPending = false;
        let rendered = []; let loads = 0; const loadMessages = () => loads++;
        ${groupGuard}
        rendered.push(messages);
    };
    globalThis.api = {render, flushDeferredGroupRender, refreshRealtimeMessages,
        busy: value => requestPending = value,
        stats: () => ({rendered, loads})};`, groupContext);
    const g = groupContext.api;
    g.render(['old']); g.refreshRealtimeMessages();
    assert.equal(g.stats().loads, 0);
    selected = false;
    // A newer response can render before the queued selectionchange callback.
    g.render(['latest']); g.flushDeferredGroupRender();
    assert.equal(g.stats().rendered.length, 1, 'Do not replay an obsolete group payload');
    assert.deepEqual(g.stats().rendered[0], ['latest']);
    assert.equal(g.stats().loads, 1);
    g.busy(true); g.refreshRealtimeMessages();
    assert.equal(g.stats().loads, 1);
    g.busy(false); g.flushDeferredGroupRender();
    assert.equal(g.stats().loads, 2, 'Coalesced realtime refresh resumes after an in-flight request');
    console.log(`PASS ${assetRoot}: SSE resume, latest payload, single flush, selection boundaries, group race`);
}
