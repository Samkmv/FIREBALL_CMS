'use strict';

// Node built-ins only. Execute actual page and generated worker scripts against
// mocked platform APIs; no browser installation, real push or live database.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {execFileSync} = require('node:child_process');
const root = path.resolve(__dirname, '..');
const workerScript = execFileSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, 'fixtures/pwa_badge_worker.php')], {encoding: 'utf8'});
const pageScript = fs.readFileSync(path.join(root, 'public/assets/default/js/pwa.js'), 'utf8');
let checks = 0;
function check(value, message) { checks++; assert.ok(value, message); }
const flush = () => new Promise(resolve => setImmediate(resolve));

function worker({supported = true, rejectBadge = false, userId = 2, count = 7, httpStatus = 200} = {}) {
  const handlers = {};
  const state = {userId, count, httpStatus, offline: false, badges: [], shown: [], closed: 0, fetches: [], messages: [], navigations: []};
  const api = supported ? {
    setAppBadge: async (...args) => { if (rejectBadge) throw new Error('Permission denied'); state.badges.push(args.length ? args[0] : 'flag'); },
    clearAppBadge: async () => { if (rejectBadge) throw new Error('Permission denied'); state.badges.push(0); }
  } : {};
  const client = {postMessage: data => state.messages.push(data), focus: async () => {}, navigate: async url => state.navigations.push(url)};
  const context = vm.createContext({URL, AbortController, setTimeout, clearTimeout, console,
    location: {origin: 'https://example.test'},
    fetch: async (url, options) => {
      state.fetches.push({url, options});
      if (state.fetchWait) await state.fetchWait;
      if (state.offline) throw new Error('Offline');
      return {status: state.httpStatus, ok: state.httpStatus === 200, json: async () => ({status: true, user_id: state.userId, badge_count: state.count})};
    },
    self: {
      navigator: api,
      addEventListener: (type, callback) => { handlers[type] = callback; },
      skipWaiting: async () => {},
      registration: {
        setAppBadge: () => { throw new Error('Wrong API object'); },
        clearAppBadge: () => { throw new Error('Wrong API object'); },
        showNotification: async (title, options) => state.shown.push({title, options}),
        getNotifications: async () => [{close: () => state.closed++}, {close: () => state.closed++}]
      },
      clients: {matchAll: async () => [client], openWindow: async url => state.navigations.push(url)}
    }
  });
  vm.runInContext(workerScript, context);
  const emit = async (type, values) => {
    const waits = [];
    handlers[type]({...values, waitUntil: task => waits.push(task)});
    await Promise.all(waits);
  };
  return {state,
    push: (target = 2) => emit('push', {data: {json: () => ({title: 'New notification', data: {user_id: target}, url: 'https://example.test/chat'})}}),
    sync: (target = 2, suppliedCount = 0, origin = 'https://example.test') => emit('message', {source: {url: origin + '/profile'}, data: {type: 'SYNC_BADGE', user_id: target, count: suppliedCount}}),
    click: () => emit('notificationclick', {notification: {close: () => {}, data: {url: 'https://example.test/chat'}}})
  };
}

function page({supported = true, rejected = false, userId = 2, onlySet = false, pwaEnabled = true} = {}) {
  const state = {badges: [], messages: [], events: [], permissions: 0};
  const workerHandlers = {};
  const worker = {postMessage: data => state.messages.push(data)};
  const classList = {toggle() {}, add() {}, remove() {}};
  const body = {dataset: {pwaAuthUserId: String(userId), pwaEnabled: pwaEnabled ? '1' : '0', pwaPushEnabled: '0'}, classList};
  const navigator = {userAgent: 'Test', standalone: true, serviceWorker: {
    controller: worker,
    addEventListener: (name, callback) => { workerHandlers[name] = callback; },
    register: async () => ({active: worker})
  }};
  if (supported) {
    navigator.setAppBadge = async value => { if (rejected) throw new Error('Denied'); state.badges.push(value); };
    if (!onlySet) navigator.clearAppBadge = async () => { if (rejected) throw new Error('Denied'); state.badges.push(0); };
  }
  const window = {navigator, location: {protocol: 'https:', hostname: 'example.test', origin: 'https://example.test', href: 'https://example.test/'},
    addEventListener() {}, matchMedia: () => ({matches: true, addEventListener() {}}),
    Notification: {permission: 'default', requestPermission: () => { state.permissions++; return Promise.resolve('granted'); }}
  };
  const context = vm.createContext({window, navigator, console, URL, Uint8Array, CustomEvent: class {constructor(type) {this.type = type;}},
    localStorage: {getItem() {return null;}, setItem() {}},
    document: {body, documentElement: {classList}, querySelector: () => null, querySelectorAll: () => [], addEventListener() {}, dispatchEvent: event => state.events.push(event.type)},
    fetch: () => {throw new Error('Unexpected page fetch');}
  });
  vm.runInContext(pageScript, context);
  return {state, set: count => window.FireballPwa.setBadge(count), notify: id => workerHandlers.message({data: {type: 'PWA_NOTIFICATIONS_CHANGED', user_id: id}})};
}

(async () => {
  let w = worker();
  await w.push();
  check(w.state.badges.at(-1) === 7, 'Push must use backend count, not hardcoded 1');
  check(w.state.shown.length === 1, 'Push must remain visible');
  check(w.state.fetches[0].options.cache === 'no-store' && w.state.fetches[0].options.credentials === 'include', 'Status must be authenticated and fresh');
  check(w.state.fetches[0].options.signal instanceof AbortSignal, 'Status request must be bounded');
  check(w.state.messages[0].type === 'PWA_NOTIFICATIONS_CHANGED', 'Foreground feed is not notified');
  await w.click();
  check(w.state.badges.at(-1) === 7 && !w.state.badges.includes(0), 'Opening one notification cleared all unread badges');
  check(w.state.navigations.length === 1, 'Push no longer opens its destination');
  let releaseStatus;
  w.state.fetchWait = new Promise(resolve => { releaseStatus = resolve; });
  const slowClick = w.click();
  await flush();
  check(w.state.navigations.length === 2, 'Slow badge lookup delayed opening the app');
  releaseStatus();
  await slowClick;
  w.state.fetchWait = null;
  await w.sync(2, 999);
  check(w.state.badges.at(-1) === 7, 'Forged page count overrode backend truth');
  w.state.count = 0;
  await w.sync();
  check(w.state.badges.at(-1) === 0, 'Zero unread must clear native badge');
  check(w.state.closed === 2, 'Android notifications were not closed after all items read');

  for (const options of [{supported: false}, {rejectBadge: true}]) {
    w = worker(options); await w.push();
    check(w.state.shown.length === 1, 'Unsupported/denied Badging API blocked push');
    check(w.state.badges.length === 0, 'Unsupported API was called');
    w.state.count = 0; await w.sync();
    check(w.state.closed === 2, 'Android fallback cleanup failed');
  }
  w = worker({count: null}); await w.push();
  check(w.state.badges.at(-1) === 'flag', 'Unavailable count must not invent a numeric count');
  for (const options of [{userId: 3}, {userId: 0, httpStatus: 401}, {httpStatus: 503}]) {
    w = worker(options); await w.push();
    check(w.state.shown.length === 0 && w.state.badges.length === 0, 'Another/logged-out user or failed validation received push');
  }
  w = worker(); w.state.offline = true; await w.push(); await w.sync();
  check(w.state.shown.length === 0 && w.state.badges.length === 0, 'Network failure changed badge/user state');
  w = worker(); await w.sync(3); await w.sync(2, 0, 'https://evil.test');
  check(w.state.badges.length === 0, 'Stale account or foreign-origin message changed badge');
  w = worker({userId: 0, httpStatus: 401}); await w.sync(0);
  check(w.state.badges.at(-1) === 0 && w.state.closed === 2, 'Logout left a previous account badge');
  w = worker(); await Promise.all([w.push(), w.push()]);
  check(w.state.badges.join(',') === '7,7', 'Concurrent pushes incremented or corrupted authoritative count');

  let p = page(); await flush();
  await p.set(12); await p.set(0);
  check(p.state.badges.join(',') === '12,0', 'Page count is not synchronized with installed icon');
  check(p.state.messages.at(-1).type === 'SYNC_BADGE' && p.state.messages.at(-1).user_id === 2, 'Worker misses current account');
  check(p.state.permissions === 0, 'Badge update requested notification permission automatically');
  await p.set(1.9); await p.set(-10); await p.set(null); await p.set(NaN);
  check(p.state.badges.slice(-2).join(',') === '1,0', 'Invalid/fractional values corrupted badge');
  const before = p.state.messages.length;
  await p.set(0); check(p.state.messages.length === before, 'Unchanged polling causes redundant worker fetches');
  p.notify(99); check(p.state.events.length === 0, 'Another account push refreshes the foreground');
  p.notify(2); await p.set(0);
  check(p.state.events[0] === 'fireball:notifications-changed', 'Own push does not refresh the foreground');
  check(p.state.messages.length === before + 1, 'Same-count push leaves an Android dot stuck');
  p = page({onlySet: true}); await p.set(0);
  check(p.state.badges.at(-1) === 0, 'setAppBadge(0) fallback missing');
  for (const options of [{supported: false}, {rejected: true}]) {
    p = page(options); await p.set(8); await p.set(0);
    check(p.state.badges.length === 0, 'Unsupported/denied page badging failed');
    check(p.state.messages.some(data => data.count === 0), 'Worker fallback not notified on unsupported platform');
  }
  p = page({userId: 0}); await flush(); await p.set(10);
  check(p.state.badges.every(count => count === 0), 'Guest can retain another account unread count');
  p = page({pwaEnabled: false}); await p.set(10);
  check(p.state.badges.length === 0, 'Disabled PWA updates its badge');
  p = page(); await Promise.all([p.set(9), p.set(3), p.set(0)]);
  check(p.state.badges.at(-1) === 0, 'Rapid read/clear operations end with an old badge');
  const main = fs.readFileSync(path.join(root, 'public/assets/default/js/main.js'), 'utf8');
  check(main.includes('window.FireballPwa?.setBadge(total);'), 'Main notification counter is not connected');
  console.log(`PWA badge: ${checks} checks passed.`);
})().catch(error => {console.error(error); process.exitCode = 1;});
