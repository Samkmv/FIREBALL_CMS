'use strict';
// Run the real player functions in small host environments. No audio engine or
// browser timing is simulated: these regressions concern deferred positions,
// DOM replacement and the order in which asynchronous requests settle.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const chapters = require('../plugins/reel-player/assets/chapters.js');
const source = fs.readFileSync(path.join(__dirname, '../plugins/reel-player/assets/player.js'), 'utf8');
let checks = 0;
function equal(actual, expected, message) { assert.deepEqual(actual, expected, message); checks++; }
function section(from, to) {
    const start = source.indexOf(from), end = source.indexOf(to, start + from.length);
    assert.ok(start >= 0 && end > start, `Player snippet exists: ${from}`);
    return source.slice(start, end);
}
const position = source.match(/^    const playbackPosition = .*;$/m)?.[0];
assert.ok(position, 'Use the player position helper');
const sync = section('    function syncChapter(', '    function seekTo(');
const chapterNavigation = section('    function seekTo(', '    function chapterTrack(');
const navigation = section('    function next(automatic = false,', '    function applyState(result)');
const nativePlay = section('    async function play(', '    function shuffled(');
const openDialog = section('    function openDialog(', '    function syncDialogViewport(');
const submit = section("    $('[data-dialog-form]').addEventListener('submit'", "    dialog.addEventListener('cancel'");

function node() {
    return {
        hidden: false, disabled: false, children: [], dataset: {}, attrs: {},
        classList: {
            values: new Set(),
            toggle(name, active) { active ? this.values.add(name) : this.values.delete(name); },
            remove(...names) { for (const name of names) this.values.delete(name); }
        },
        setAttribute(name, value) { this.attrs[name] = value; },
        removeAttribute(name) { delete this.attrs[name]; },
        addEventListener(name, callback) { this[name] = callback; }
    };
}
function markersNode() {
    const markers = node();
    // Replacing innerHTML destroys old marker attributes just as the browser
    // does. Only parse the ids needed by the actual synchronization function.
    Object.defineProperty(markers, 'innerHTML', {set(html) {
        this.children = [...html.matchAll(/data-id="(\d+)"/g)].map(match => {
            const child = node(); child.dataset.id = match[1]; return child;
        });
    }});
    return markers;
}
function chapterHost(items) {
    const markers = markersNode(), status = node(), prev = node(), next = node();
    const context = vm.createContext({
        chapters, items, currentChapters: () => context.items,
        chapterDuration: -1, chapterIndex: -2, chapterRows: [], chapterMarkers: [],
        restoreTime: 0, audio: {readyState: 0, currentTime: 0}, esc: String, updateNow() {},
        $: selector => ({
            '[data-chapter-markers]': markers, '[data-chapter-current]': status,
            '[data-action="prev"]': prev, '[data-action="next"]': next
        })[selector]
    });
    vm.runInContext(position + '\n' + sync, context);
    return {context, markers, status, prev, next};
}
const mix = [
    {id: 1, start: 0, title: 'First'},
    {id: 2, start: 30, title: 'Second'},
    {id: 3, start: 60, title: 'Third'}
];

async function main() {
    const host = chapterHost(mix);
    host.context.syncChapter(0);
    equal(host.markers.hidden, true, 'Unknown duration hides markers');
    host.context.syncChapter(120);
    equal(host.markers.children[0].classList.values.has('is-current'), true, 'Metadata-created marker highlights the unchanged first chapter');
    equal(host.markers.children[0].attrs['aria-current'], 'true', 'Metadata-created marker exposes the current chapter');
    const originalMarker = host.markers.children[0];
    host.context.syncChapter(121);
    equal(host.markers.children[0] === originalMarker, false, 'Duration correction replaces the marker DOM');
    equal(host.markers.children[0].classList.values.has('is-current'), true, 'Replacement retains the current marker');
    host.context.restoreTime = 60;
    host.context.syncChapter(121);
    equal(host.context.chapterIndex, 2, 'Current chapter follows a pending seek before metadata');
    equal(host.markers.children[2].attrs['aria-current'], 'true', 'Pending chapter has the active marker');
    host.context.items = [];
    host.context.chapterDuration = -1;
    host.context.chapterIndex = -2;
    host.context.syncChapter(120);
    equal(host.markers.hidden, true, 'An ordinary file has no marker band');
    equal(host.status.hidden, true, 'An ordinary file has no chapter announcement');
    equal(host.next.attrs['aria-label'], 'Следующий трек', 'Ordinary-file navigation keeps its track label');

    let sourceLoads = 0, plays = 0;
    const pending = vm.createContext({
        chapters, currentChapters: () => mix, activeTrack: () => ({duration: 120}), current: 1, restoreTime: 0,
        audio: {readyState: 0, currentTime: 0, duration: NaN},
        queue: [1, 2], repeat: 'off', shuffle: false, history: [], bag: [], futureBag: [],
        drawProgress() {}, save() {}, play() { plays++; },
        load() { sourceLoads++; }
    });
    vm.runInContext(position + '\n' + chapterNavigation + '\n' + navigation, pending);
    pending.next(false, 'user');
    equal(pending.restoreTime, 30, 'First next queues the second chapter without metadata');
    pending.next(false, 'user');
    equal(pending.restoreTime, 60, 'Second next advances from the pending chapter');
    pending.previous('user');
    equal(pending.restoreTime, 30, 'Previous also starts from the pending position');
    equal(sourceLoads, 0, 'Pending chapter navigation never reloads the audio source');
    equal(plays, 3, 'Each chapter action uses the existing playback command');

    const loads = [];
    const recovery = vm.createContext({
        current: 1, generation: 1, restoreTime: 600,
        audio: {readyState: 0, currentTime: 0, error: {code: 2}, ended: false, play() { plays++; return Promise.resolve(); }},
        configureAudioSession() {}, resumeMeters() {},
        load(id, time) { loads.push([id, time]); recovery.restoreTime = time; recovery.audio.error = null; recovery.generation++; },
        setBuffering() {}, stopMeters() {}, toast() { throw new Error('Playback should not fail'); }
    });
    vm.runInContext(position + '\n' + nativePlay, recovery);
    await recovery.play('user');
    equal(loads, [[1, 600]], 'Native error recovery preserves the requested chapter');
    equal(recovery.restoreTime, 600, 'The metadata restore target survives recovery');

    for (const rejected of [false, true]) {
        await testOldSubmission(rejected);
    }
    console.log(`Tape Room chapter races: ${checks} checks passed (metadata markers, pending navigation, native recovery, stale submit success/error).`);
}

function deferred() {
    let resolve, reject;
    const promise = new Promise((accept, fail) => { resolve = accept; reject = fail; });
    return {promise, resolve, reject};
}
async function testOldSubmission(rejected) {
    const oldSave = deferred(), newSave = deferred(), elements = {};
    for (const name of ['submit', 'error', 'title', 'fields', 'description', 'form']) elements[`[data-dialog-${name}]`] = node();
    const dialog = {
        open: true, closes: 0, classList: node().classList,
        showModal() { this.open = true; },
        close() { this.open = false; this.closes++; }
    };
    const context = vm.createContext({
        dialog, dialogRequest: 7, chapterJob: null, coverPreviewUrl: null,
        dialogSubmitting: false, dialogHandler: () => oldSave.promise,
        syncDialogViewport() {}, $: selector => elements[selector],
        FormData: class {}, URL: {revokeObjectURL() {}}
    });
    vm.runInContext(openDialog + '\n' + submit, context);
    const event = () => ({preventDefault() {}, currentTarget: {}});
    const oldPending = elements['[data-dialog-form]'].submit(event());
    equal(context.dialogSubmitting, true, 'Old save is in flight');
    context.openDialog('Главы записи', 'New fields', () => newSave.promise, 'New dialog');
    const newPending = elements['[data-dialog-form]'].submit(event());
    if (rejected) oldSave.reject(new Error('Stale save failure')); else oldSave.resolve();
    await oldPending;
    equal(dialog.open, true, `Stale ${rejected ? 'error' : 'success'} does not close the new dialog`);
    equal(elements['[data-dialog-title]'].textContent, 'Главы записи', 'The new title remains intact');
    equal(elements['[data-dialog-error]'].hidden, true, 'An old failure never leaks into the new dialog');
    equal(context.dialogSubmitting, true, 'An old finalizer never unlocks the new save');
    equal(elements['[data-dialog-submit]'].disabled, true, 'An old finalizer never enables the new submit');
    newSave.resolve();
    await newPending;
    equal(dialog.closes, 1, 'Only the current save closes the dialog');
    equal(context.dialogSubmitting, false, 'The current save clears its submission state');
}

main().catch(error => { console.error(error); process.exitCode = 1; });
