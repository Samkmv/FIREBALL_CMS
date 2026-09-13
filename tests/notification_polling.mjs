import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

// Run the actual polling code with deterministic time, network and browser events.
const flush = async () => { for (let i = 0; i < 20; i++) await Promise.resolve(); };
function harness(source, shared = {channels: [], locks: new Set()}, options = {}) {
    let now = 100000;
    let nextTimer = 0;
    const timers = new Map(), events = {}, requests = [], applied = [];
    const document = {hidden: !!options.hidden, documentElement: {lang: 'ru'}, addEventListener: (name, cb) => events[name] = cb};
    const center = {length: options.unread ? 0 : 1, data: () => '/notifications/feed', on: () => {}};
    const list = {attr: () => {}, on: () => {}};
    const $ = () => ({on: () => {}});
    $.ajax = () => {
        let resolve, reject;
        const promise = new Promise((yes, no) => { resolve = yes; reject = no; });
        promise.abort = () => reject({statusText:'abort'});
        requests.push({resolve, reject});
        return promise;
    };
    class Channel {
        constructor(name) { this.name = name; shared.channels.push(this); }
        postMessage(data) { for (const peer of shared.channels) if (peer !== this && peer.name === this.name) peer.onmessage?.({data}); }
    }
    const context = vm.createContext({
        document, window: {addEventListener: (name, cb) => events[name] = cb}, $, notificationCenter: center, notificationList:list,
        unreadBadges: {first: () => ({data: () => '/chat/unread-count'})}, baseUrl:'/', bodyDataset:{pwaAuthUserId: options.user || '1'},
        BroadcastChannel: options.noChannel ? undefined : Channel,
        navigator: {locks: {request: async (name, opts, callback) => {
            if (options.denyLocks) throw new Error('Denied');
            if (shared.locks.has(name)) return callback(null);
            shared.locks.add(name);
            try { await callback({name}); } finally { shared.locks.delete(name); }
        }}},
        Date: {now: () => now}, setTimeout: (cb, delay) => {const id = ++nextTimer; timers.set(id, {cb, at: now+delay}); return id;},
        clearTimeout: id => timers.delete(id), sameOriginUrl: url => url,
        applyNotificationFeed: response => applied.push(response), updateUnreadBadges: count => applied.push(count), showNotificationFeedError: () => {},
        notificationFeedGeneration:0, notificationFeedRequest:null, notificationMutations:0,
    });
    vm.runInContext(source, context);
    return {
        requests, applied, context, events, document,
        run: code => vm.runInContext(code, context),
        async advance(ms) {
            now += ms;
            for (const [id, timer] of [...timers]) if (timer.at <= now) {timers.delete(id); timer.cb();}
            await flush();
        },
        async respond(data = {status:true, total_unread_count:2}) { requests.at(-1).resolve(data); await flush(); },
    };
}
let checks = 0;
function check(value, label) { assert.ok(value,label); checks++; }
for (const path of ['themes/default/assets/js/main.js', 'public/assets/default/js/main.js']) {
    const file = readFileSync(new URL('../'+path, import.meta.url),'utf8');
    const source = file.slice(file.indexOf('    // Fallback polling is shared'), file.indexOf("    $('[data-slug-source]')"));
    assert.ok(source.includes('requestNotificationPoll'));
    const h = harness(source);
    check(h.requests.length === 1,'Initial visible request');
    await h.respond();
    await h.advance(8000);
    check(h.requests.length === 1,'No legacy eight-second polling');
    await h.advance(37000);
    check(h.requests.length === 2,'45-second fallback');
    h.requests.at(-1).reject({status:500}); await flush();
    await h.advance(45000); check(h.requests.length === 2,'Error doubles the interval');
    await h.advance(45000); check(h.requests.length === 3,'Backoff eventually retries');
    await h.respond();
    h.document.hidden = true; h.events.visibilitychange();
    await h.advance(90000); check(h.requests.length === 3,'Hidden tabs stop polling');
    h.document.hidden = false; h.events.visibilitychange(); await flush();
    check(h.requests.length === 4,'Visibility restores polling immediately');
    h.events.pagehide(); await h.respond(); await h.advance(90000);
    check(h.requests.length === 4,'In-flight completion cannot restart a suspended page');
    h.events.pageshow(); await flush(); check(h.requests.length === 5,'BFCache restore resumes polling');

    const hidden = harness(source, undefined, {hidden:true});
    check(hidden.requests.length === 0,'No initial request in hidden tab');
    const unread = harness(source, undefined, {unread:true, noChannel:true});
    await unread.respond({status:true,unread_count:7});
    check(unread.applied[0] === 7,'Unread-only fallback works without BroadcastChannel');
    const denied = harness(source, undefined, {denyLocks:true}); await flush();
    check(denied.requests.length === 1,'Denied Web Locks fall back to local polling');
    denied.requests[0].reject({status:401}); await flush(); await denied.advance(600000);
    check(denied.requests.length === 1,'Auth failure stops repeated requests');

    const shared = {channels:[],locks:new Set()};
    const leader = harness(source,shared), follower = harness(source,shared);
    await flush(); check(leader.requests.length+follower.requests.length === 1,'Concurrent tabs share a request');
    await leader.respond();
    check(follower.applied.length === 1,'Followers receive counters');
    await follower.advance(8000); follower.events.focus(); await flush();
    check(follower.requests.length === 1,'Focused tab can explicitly refresh');
    const otherUser = harness(source,shared,{user:'2'}); await otherUser.respond();
    check(follower.applied.length === 1,'Different users never share responses');

    const mutation = harness(source);
    mutation.run('notificationMutations++; invalidateNotificationPoll();'); await flush();
    await mutation.respond();
    check(mutation.applied.length === 0,'Pre-mutation responses cannot restore read items');
    mutation.run('notificationMutations--; refreshNotificationPoll();');
    await mutation.advance(1); await mutation.respond();
    check(mutation.applied.length === 1,'Mutation refresh bypasses recent-response throttle');
}
console.log(`Notification polling: ${checks} checks passed.`);
