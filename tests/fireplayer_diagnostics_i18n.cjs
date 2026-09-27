'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const { Target } = require('./fireplayer_native_startup.cjs');
const source = fs.readFileSync(path.join(__dirname, '../public/assets/default/js/fireplayer-diagnostics.js'), 'utf8');
class Node extends Target {
    appendChild(node) { this.children.push(node); node.parentNode = this; return node; }
    insertBefore(node) { this.appendChild(node); this.children[0].nextSibling = node; }
    remove() { this.removed = true; }
}
function render(lang, allowed = true) {
    const root = new Node(), parent = new Node(); parent.appendChild(root); root.isConnected = true;
    const player = {
        root, options: { src: 'https://camera.test/stream-one/index.m3u8?secret=hidden' },
        info: { media: 'video', protocol: 'hls', mode: 'live' },
        controller: { engine: 'native', liveSyncPosition: 9 },
        media: { currentTime: 7, duration: Infinity, buffered: { length: 0 }, seekable: { length: 1, end: () => 10 } },
        _state: 'buffering', _metrics: { wakeMs: 8072, recoveryStage: 'native-attach', recoveryReason: 'initial' },
        _health: { lastFrameAt: Date.now() - 1000 }, on() {}, off() {}
    };
    const document = new Node();
    Object.assign(document, { documentElement: { lang }, baseURI: 'https://cms.test/', readyState: 'complete',
        createElement: () => new Node(), querySelectorAll: () => [root] });
    let tick;
    const window = { FirePlayer: { version: '1.1.0', get: () => player }, navigator: { onLine: true },
        canViewVideoDiagnostics: allowed, setInterval: callback => { tick = callback; return 1; }, clearInterval() {} };
    vm.runInNewContext(source, { window, document, URL, Date });
    const panel = parent.children[1];
    const rows = panel ? panel.children[1].children : [];
    return { player, window, tick, panel,
        values: Object.fromEntries(rows.map(row => [row.children[1].getAttribute('data-fp-diagnostic'), row.children[1]])),
        headings: rows.map(row => row.children[0].textContent) };
}
const expectations = {
    ru: ['Буферизация', 'Да', 'Встроенный в браузер', 'Первоначальный запуск', '8072 мс'],
    en: ['Buffering', 'Yes', 'Browser native', 'Initial start', '8072 ms'],
    de: ['Puffern', 'Ja', 'Browserintern', 'Erster Start', '8072 ms'],
    'zh-cn': ['缓冲中', '是', '浏览器原生', '首次启动', '8072 毫秒']
};
for (const [lang, expected] of Object.entries(expectations)) {
    const view = render(lang);
    assert.deepEqual(['state', 'managed', 'engine', 'recoveryReason', 'wakeMs'].map(key => view.values[key].textContent), expected);
    assert.equal(view.panel.children[0].children[1].textContent, expected[0]);
    for (const key of Object.keys(view.values)) { assert(!view.headings.includes(key), `${lang}: untranslated heading ${key}`); }
    for (const state of ['idle', 'lazy', 'detecting', 'waking', 'connecting', 'ready', 'playing', 'buffering', 'paused', 'reconnecting', 'offline', 'awaiting-gesture', 'ended', 'error', 'destroyed']) {
        view.player._state = state; view.tick(); assert.notEqual(view.values.state.textContent, '—', `${lang}: missing ${state}`);
    }
    view.window.navigator.onLine = false; view.tick(); assert.notEqual(view.values.online.textContent, 'false');
    view.player._metrics.recoveryReason = '<img src=secret>'; view.tick(); assert.equal(view.values.recoveryReason.textContent, '—');
    assert.equal(view.values.source.textContent, 'camera.test');
    console.log('PASS diagnostics labels, states, values, units and safe fallback: ' + lang);
}
assert.equal(render('de-DE').values.state.textContent, expectations.de[0]);
assert.equal(render('zh_CN').values.state.textContent, expectations['zh-cn'][0]);
assert.equal(render('fr').values.state.textContent, expectations.en[0]);
assert.equal(render('ru', false).panel, undefined);
console.log('PASS regional locales, English fallback and creator-only guard');
