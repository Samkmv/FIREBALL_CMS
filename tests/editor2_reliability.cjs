'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assetRoot = path.join(__dirname, '../public/assets/default/js/editor2');
const storage = new Map();
const timers = new Map();
let timerId = 0;
const window = {
    location: { origin: 'https://editor.test' },
    localStorage: {
        getItem: key => storage.get(key) ?? null,
        setItem: (key, value) => storage.set(key, value),
        removeItem: key => storage.delete(key)
    },
    setTimeout: callback => { timers.set(++timerId, callback); return timerId; },
    clearTimeout: id => timers.delete(id),
    alert: () => {}
};
const document = { readyState: 'loading', addEventListener: () => {}, querySelector: () => null };
const context = vm.createContext({ window, document, structuredClone, AbortController, URL, console, FormData: class {
    constructor(form) { this.content = form?.content; }
    append() {}
} });
for (const file of ['registry.js', 'history.js', 'sanitizer.js', 'importer.js', 'editor.js']) {
    vm.runInContext(fs.readFileSync(path.join(assetRoot, file), 'utf8'), context, { filename: file });
}
const API = window.FireballEditor2;

function editor() {
    const instance = Object.create(API.Editor.prototype);
    const attributes = new Map([['data-entity-id', '0'], ['data-entity-type', 'post']]);
    const title = { value: 'Title' };
    const formAttributes = new Map([['data-autosave-url', '/save']]);
    Object.assign(instance, {
        root: {
            getAttribute: name => attributes.get(name),
            setAttribute: (name, value) => attributes.set(name, value)
        },
        workspace: { querySelector: () => title, classList: { add() {}, remove() {} } },
        form: {
            content: 'old',
            hasAttribute: () => true,
            getAttribute: name => formAttributes.get(name),
            setAttribute: (name, value) => formAttributes.set(name, value),
            querySelector: () => null
        },
        config: { userId: 1, galleryUploadUrl: '/upload' },
        labels: {}, ui: {}, state: { blocks: [] }, dirty: true, changeRevision: 1,
        destroyed: false, abortController: null, autosaveQueued: false,
        lastSerialized: 'old', savedSerialized: 'initial',
        syncTextarea() { this.lastSerialized = this.form.content; },
        refreshStatus() {},
        setSaveState(value) { this.saveStatus = value; }
    });
    return instance;
}

function deferred() {
    let resolve;
    const promise = new Promise(r => { resolve = r; });
    return { promise, resolve };
}

const success = id => ({ ok: true, json: async () => ({ status: 'success', id }) });

(async () => {
    const history = new API.EditorHistory({ text: '' }, { coalesceMs: 100000 });
    history.push({ text: 'a' }, 'typing');
    history.push({ text: 'ab' }, 'typing', true);
    assert.equal(history.undo().text, 'a');
    history.push({ text: 'ax' }, 'typing');
    assert.equal(history.canRedo(), false, 'Typing after undo discards the old redo branch');
    assert.equal(history.undo().text, 'a', 'Typing after undo preserves the preceding state');
    const initial = new API.EditorHistory({ text: '' });
    initial.push({ text: 'x' }, 'initial');
    assert.equal(initial.undo().text, '', 'The initial state cannot be coalesced away');
    assert.equal(new API.EditorHistory({}, { coalesceMs: 0 }).coalesceMs, 0);
    console.log('PASS undo/redo branching and coalescing');

    const normalized = API.importer.normalizeState({ blocks: [
        { id: 'same', type: 'text', data: { html: 'one' } },
        { id: 'same', type: 'text', data: { html: 'two' } }
    ] }, API);
    assert.notEqual(normalized.blocks[0].id, normalized.blocks[1].id);
    assert.equal(normalized.blocks[1].data.html, 'two');
    console.log('PASS imported duplicate IDs are separated');

    const instance = editor();
    instance.saveLocalDraft();
    const first = deferred();
    const requests = [];
    window.fetch = (url, options) => {
        requests.push(options);
        return first.promise;
    };
    const saving = instance.autosave(false);
    instance.form.content = 'new';
    instance.lastSerialized = 'new';
    instance.markDirty();
    await instance.autosave(true);
    assert.equal(requests.length, 1, 'Only one request can create a new draft');
    first.resolve(success(42));
    await saving;
    assert.equal(instance.root.getAttribute('data-entity-id'), '42');
    assert.equal(instance.savedSerialized, 'old', 'Acknowledgement describes the sent version');
    assert.equal(instance.dirty, true, 'New edits remain unsaved');
    assert.equal(instance.saveStatus, 'saving');
    assert.equal(JSON.parse(storage.get(instance.localStorageKey())).serialized, 'new');
    window.fetch = async (url, options) => {
        requests.push(options);
        assert.equal(instance.ui.entityIdInput?.value ?? instance.root.getAttribute('data-entity-id'), '42');
        return success(42);
    };
    await instance.autosave(false);
    assert.equal(instance.dirty, false);
    assert.equal(instance.savedSerialized, 'new');
    assert.equal(storage.has(instance.localStorageKey()), false);
    assert.equal(timers.has(instance.localSaveTimer), false, 'A delayed local save cannot recreate a stale draft');
    console.log('PASS edits during autosave, serialized requests, draft cleanup');

    const submit = editor();
    submit.handleSubmit();
    assert.ok(storage.has(submit.localStorageKey()), 'Submitting keeps recovery until save is confirmed');
    console.log('PASS recovery survives form submission');

    const pendingSubmit = editor();
    const pendingResponse = deferred();
    window.fetch = () => pendingResponse.promise;
    let submittedId, prevented = false;
    pendingSubmit.form.requestSubmit = () => {
        submittedId = pendingSubmit.root.getAttribute('data-entity-id');
        pendingSubmit.handleSubmit();
    };
    const pendingSave = pendingSubmit.autosave(false);
    pendingSubmit.handleSubmit({ preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(submittedId, undefined, 'Final submit waits for draft creation');
    pendingResponse.resolve(success(99));
    await pendingSave;
    await Promise.resolve();
    assert.equal(submittedId, '99', 'Final submit uses the newly created record ID');
    console.log('PASS final submit waits for in-flight draft creation');

    storage.clear();
    const recovery = editor();
    recovery.form.content = recovery.savedSerialized = recovery.lastSerialized = 'same';
    const title = { value: 'Server title' };
    recovery.workspace.querySelector = () => title;
    let shown = false, restore;
    recovery.ui.recoveryDialog = {
        showModal() { shown = true; }, close() {},
        querySelector(selector) { return { addEventListener(name, callback) {
            if (selector.includes('restore')) restore = callback;
        } }; }
    };
    storage.set(recovery.localStorageKey(), JSON.stringify({ state: { blocks: [] }, title: '', serialized: 'same' }));
    recovery.restoreLocalDraft();
    assert.equal(shown, true, 'Title-only changes trigger recovery');
    recovery.renderAll = () => {};
    restore();
    assert.equal(title.value, '', 'Recovery preserves an intentionally empty title');
    console.log('PASS title-only recovery');

    const upload = editor();
    let uploads = 0, attached;
    upload.addGalleryItems = (id, urls) => { attached = urls.slice(); };
    window.fetch = async () => ++uploads === 1
        ? { ok: true, json: async () => ({ status: 'success', url: '/one.png' }) }
        : { ok: false, json: async () => ({ status: 'error', message: 'failed' }) };
    await upload.uploadGalleryLocalImages('gallery', [
        { type: 'image/png', name: 'one.png' }, { type: 'image/png', name: 'two.png' }
    ], null);
    assert.deepEqual(Array.from(attached), ['/one.png'], 'Successful uploads survive a later failure');
    console.log('PASS partial gallery upload recovery');

    const downloads = editor();
    downloads.config.fileUploadUrl = '/upload-file';
    downloads.state.blocks = [{ id: 'files', type: 'downloads', data: { items: [] } }];
    downloads.activeId = 'another-block';
    downloads.refreshBlock = () => {};
    downloads.renderInspector = () => {};
    let commits = 0;
    downloads.commit = () => { commits++; };
    let downloadRequests = 0;
    window.fetch = async () => ++downloadRequests === 2
        ? { ok: false, json: async () => ({ status: 'error', message: 'failed' }) }
        : { ok: true, json: async () => ({ status: 'success', file: { url: '/uploads/files/' + downloadRequests + '.txt', name: downloadRequests + '.txt', size: 8 } }) };
    await downloads.uploadDownloadFiles('files', ['one.txt', 'two.txt', 'three.txt'].map(name => ({ name, size: 8 })));
    assert.equal(downloads.state.blocks[0].data.items.length, 2, 'Partial downloads upload retains successes and continues after a failure');
    assert.equal(commits, 1);
    assert.equal(downloads.activeId, 'another-block', 'An asynchronous upload never steals the active selection');
    assert.equal(downloads.downloadUploads.size, 0);
    const pendingFile = deferred();
    window.fetch = () => pendingFile.promise;
    const pendingUpload = downloads.uploadDownloadFiles('files', [{ name: 'later.txt', size: 8 }]);
    let submitPrevented = false;
    downloads.handleSubmit({ preventDefault() { submitPrevented = true; } });
    assert.equal(submitPrevented, true, 'Publishing cannot race pending file uploads');
    const unload = { preventDefault() {} };
    downloads.dirty = false;
    downloads.handleBeforeUnload(unload);
    assert.equal(unload.returnValue, '', 'Navigation warns about unfinished uploads');
    downloads.state.blocks = [];
    pendingFile.resolve({ ok: true, json: async () => ({ status: 'success', file: { url: '/uploads/files/later.txt', name: 'later.txt', size: 8 } }) });
    await pendingUpload;
    assert.equal(downloads.state.blocks.length, 0, 'Completing an upload cannot resurrect a deleted block');
    assert.equal(commits, 1);
    assert.equal(downloads.downloadUploads.size, 0);
    console.log('PASS partial downloads upload, selection, submit/navigation guard and deleted-block race');
})().catch(error => { console.error(error); process.exitCode = 1; });
