'use strict';
// Settings interactions run against DOM and Audio doubles without network or speakers.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const filename = path.join(__dirname, '../plugins/toy-car-rental/assets/settings.js');
const source = fs.readFileSync(filename, 'utf8');
let checks = 0;
const check = (condition, message) => { checks++; assert.ok(condition, message); };
const settle = async () => { for (let i = 0; i < 6; i++) await Promise.resolve(); };

function fixture(options = {}) {
    const listeners = {};
    const windowListeners = {};
    const timers = new Map();
    const createdUrls = [];
    const revokedUrls = [];
    const audios = [];
    let timerId = 0;
    const fileInput = { files: options.file ? [options.file] : [] };
    const resetInput = { checked: false };
    const status = { textContent: '' };
    const button = {
        dataset: { soundUrl: options.url ?? '', errorMessage: 'Could not play this sound' },
        attributes: {},
        setAttribute(name, value) { this.attributes[name] = value; },
        removeAttribute(name) { delete this.attributes[name]; },
    };
    const target = { closest(selector) { return selector === '[data-toy-rental-sound-preview]' ? button : null; } };
    const recipients = [
        { dataset: { searchText: 'José Álvarez jose Admin' }, checked: true, disabled: false },
        { dataset: { searchText: 'Сергей Сидоров sergey Оператор' }, checked: false, disabled: false },
    ];
    const noResults = { hidden: true };
    const list = { querySelectorAll: () => recipients };
    const form = { querySelector(selector) { return selector === '[data-toy-rental-recipient-list]' ? list : noResults; } };
    const search = { value: '', closest(selector) { return selector === '[data-toy-rental-recipient-search]' ? this : (selector === 'form' ? form : null); } };
    const document = {
        hidden: false,
        getElementById() { return fileInput; },
        querySelector(selector) { return selector === '[data-toy-rental-sound-reset]' ? resetInput : status; },
        querySelectorAll(selector) { return selector === '[data-toy-rental-sound-preview]' ? [button] : []; },
        addEventListener(name, listener) { (listeners[name] ||= []).push(listener); },
    };
    class FakeAudio {
        constructor(url) { this.src = url; this.pauses = 0; this.loads = 0; audios.push(this); }
        play() { this.plays = (this.plays || 0) + 1; return options.play ? options.play(this) : Promise.resolve(); }
        pause() { this.pauses++; }
        removeAttribute(name) { if (name === 'src') this.src = ''; }
        load() { this.loads++; }
    }
    const window = {
        Audio: FakeAudio,
        setTimeout(callback, delay) { const id = ++timerId; timers.set(id, { callback, delay }); return id; },
        clearTimeout(id) { timers.delete(id); },
        addEventListener(name, listener) { windowListeners[name] = listener; },
    };
    vm.runInNewContext(source, {
        document, window,
        URL: {
            createObjectURL(file) { const url = `blob:fixture/${createdUrls.length + 1}/${file.name}`; createdUrls.push(url); return url; },
            revokeObjectURL(url) { revokedUrls.push(url); },
        },
    }, { filename });
    const emit = (name, target) => (listeners[name] || []).forEach(listener => listener({ target, preventDefault() {} }));
    const click = () => emit('click', target);
    const timeout = delay => {
        const entries = [...timers].filter(([, value]) => value.delay === delay);
        check(entries.length === 1, `One ${delay}ms timer is active`);
        const [id, value] = entries[0]; timers.delete(id); value.callback();
    };
    return { button, fileInput, resetInput, status, audios, timers, createdUrls, revokedUrls, document, recipients, noResults, search, click, emit, timeout, windowListeners };
}

async function run() {
    const empty = fixture(); check(empty.button.disabled, 'Preview disabled without a custom sound');
    empty.click(); check(!empty.audios.length, 'Disabled preview stays silent');
    empty.fileInput.files = [{ name: 'custom.mp3' }]; empty.emit('change', empty.fileInput);
    check(!empty.button.disabled && !empty.audios.length, 'Choosing a file enables preview without playing automatically');
    empty.click(); await settle();
    check(empty.audios[0].src === empty.createdUrls[0], 'Newly selected file previews using an ObjectURL');
    check(empty.audios[0].loop === false && empty.button.attributes['aria-pressed'] === 'true', 'Preview does not loop and exposes active state');
    check(!('aria-busy' in empty.button.attributes), 'Playing audio clears loading state');
    empty.click();
    check(empty.audios[0].pauses === 1 && !empty.audios[0].src && empty.audios[0].loads === 1, 'Second preview click stops and releases audio');
    check(empty.revokedUrls.length === 1 && empty.button.attributes['aria-pressed'] === 'false', 'Stopped file preview revokes its URL and state');
    check(!empty.timers.size, 'Stopping clears all playback timers');

    const saved = fixture({ url: '/admin/toy-rental/sound?v=saved.mp3' });
    check(!saved.button.disabled, 'Saved sound enables preview'); saved.click(); await settle();
    check(saved.audios[0].src === '/admin/toy-rental/sound?v=saved.mp3' && !saved.createdUrls.length, 'Saved URL is used without a temporary Blob URL');
    saved.audios[0].onended(); check(saved.audios[0].pauses === 1 && !saved.timers.size, 'Natural completion cleans preview');
    saved.resetInput.checked = true; saved.emit('change', saved.resetInput); check(saved.button.disabled, 'Reset disables saved-sound preview');
    saved.fileInput.files = [{ name: 'replacement.wav' }]; saved.emit('change', saved.fileInput); check(!saved.button.disabled, 'New upload takes precedence over reset for preview');
    saved.click(); await settle(); check(saved.audios[1].src.includes('replacement.wav'), 'Replacement file previews even when reset checked');
    saved.timeout(30000); check(saved.audios[1].pauses === 1 && saved.revokedUrls.length === 1, 'Preview is limited to thirty seconds and releases URL');

    const broken = fixture({ file: { name: 'bad.ogg' } }); broken.click(); await settle(); broken.audios[0].onerror();
    check(broken.status.textContent === 'Could not play this sound', 'Decode errors use translated inline feedback');
    check(broken.revokedUrls.length === 1 && !broken.timers.size, 'Media errors release URL and timers');
    const rejected = fixture({ file: { name: 'blocked.m4a' }, play: () => Promise.reject(new Error('Blocked')) });
    rejected.click(); await settle(); check(rejected.status.textContent === 'Could not play this sound' && rejected.audios[0].pauses === 1, 'Playback rejection cleans audio with inline feedback');
    let resolvePlay;
    const delayed = fixture({ file: { name: 'slow.mp3' }, play: () => new Promise(resolve => { resolvePlay = resolve; }) });
    delayed.click(); delayed.timeout(8000); check(delayed.audios[0].pauses === 1 && !delayed.audios[0].src, 'Slow preview is stopped after eight seconds');
    resolvePlay(); await settle(); check(delayed.audios[0].pauses === 2 && !delayed.timers.size, 'Late playback resolution stays stopped without a queued cue');
    const leaving = fixture({ file: { name: 'leave.wav' } }); leaving.click(); await settle(); leaving.windowListeners.pagehide();
    check(leaving.audios[0].pauses === 1 && leaving.revokedUrls.length === 1, 'Leaving settings cleans preview and ObjectURL');
    const hidden = fixture({ file: { name: 'background.wav' } }); hidden.click(); await settle(); hidden.document.hidden = true; hidden.emit('visibilitychange');
    check(hidden.audios[0].pauses === 1 && !hidden.timers.size, 'Hiding the settings page stops preview');
    const changed = fixture({ file: { name: 'first.wav' } }); changed.click(); await settle(); changed.fileInput.files = [{ name: 'second.wav' }]; changed.emit('change', changed.fileInput);
    check(changed.audios.length === 1 && changed.audios[0].pauses === 1 && changed.revokedUrls.length === 1, 'Changing selected audio stops the old preview without auto-playing a replacement');

    const search = fixture();
    search.search.value = '  JOSE  '; search.emit('input', search.search);
    check(!search.recipients[0].hidden && search.recipients[1].hidden, 'Recipient search ignores case, accents and outer whitespace');
    check(search.recipients[0].checked && !search.recipients[0].disabled && search.noResults.hidden, 'Visible checked recipient remains enabled');
    search.search.value = 'сергеи'; search.emit('input', search.search);
    check(search.recipients[0].hidden && !search.recipients[1].hidden, 'Recipient search supports normalized Cyrillic names');
    check(search.recipients[0].checked && !search.recipients[0].disabled, 'Hidden checked recipients are still submitted');
    search.search.value = 'nobody'; search.emit('input', search.search); check(search.recipients.every(item => item.hidden) && !search.noResults.hidden, 'Unmatched search displays no-results text');
    search.search.value = ''; search.emit('input', search.search); check(search.recipients.every(item => !item.hidden) && search.noResults.hidden, 'Clearing search restores all recipients');
    console.log(`${checks} rental settings checks passed`);
}
run().catch(error => { console.error(error); process.exitCode = 1; });
