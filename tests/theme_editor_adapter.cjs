'use strict';
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/assets/default/js/admin-theme-editor.js', 'utf8');
function element(value = '') {
    return {value, dataset: {}, handlers: {}, hidden: false,
        addEventListener(name, fn) { this.handlers[name] = fn; }, focus() {this.focused = true;}};
}
async function scenario(fail) {
    const textarea = element('original'); textarea.dataset.editorLanguage = 'php';
    const mount = element(); const status = element(); const reset = element(); const select = element('theme-a');
    const form = element(); form.requestSubmit = () => {form.handlers.submit(); form.submitted = true;};
    const root = element(); root.dataset = {monacoUrl: 'local-editor.js', editorFallbackMessage: 'fallback', unsavedMessage: 'dirty'};
    root.querySelector = key => ({'[data-theme-editor-code]': textarea, '[data-theme-editor-monaco]': mount,
        '[data-theme-editor-status]': status, '[data-theme-editor-reset]': reset, '[data-theme-editor-theme-select]': select})[key] || null;
    root.querySelectorAll = () => [];
    const doc = element(); doc.querySelector = key => key === '[data-theme-editor]' ? root : null;
    doc.getElementById = () => form; doc.documentElement = {dataset: {bsTheme: 'dark'}};
    const win = element(); win.requestAnimationFrame = () => {}; win.setTimeout = () => {}; win.confirm = () => false;
    win.location = {};
    let value, change, theme, command, options, observer;
    const instance = {getModel: () => ({getFullModelRange: () => ({}), dispose() {}}), getValue: () => value,
        executeEdits: (_, edits) => {value = edits[0].text; change();}, pushUndoStop() {},
        onDidChangeModelContent: fn => {change = fn;}, addCommand: (_, fn) => {command = fn;},
        onDidChangeCursorPosition() {}, focus() {}, dispose() {}};
    const monaco = {editor: {defineTheme() {}, create: (_, opts) => {options = opts; value = opts.value; return instance;}, setTheme: name => {theme = name;}},
        KeyMod: {CtrlCmd: 1}, KeyCode: {KeyS: 2}};
    const promise = fail ? 'Promise.reject(new Error("offline"))' : 'Promise.resolve({monaco})';
    vm.runInNewContext(source.replace('import(editor.dataset.monacoUrl)', promise), {
        document: doc, window: win, monaco, MutationObserver: class {constructor(fn) {observer = fn;} observe() {} disconnect() {}}
    });
    // Input while the bundle is loading must become the model's initial value.
    textarea.value = 'typed while loading'; textarea.handlers.input();
    await new Promise(resolve => setImmediate(resolve));
    if (fail) {
        assert.equal(textarea.hidden, false); assert.equal(status.textContent, 'fallback');
        assert.equal(textarea.value, 'typed while loading');
        reset.handlers.click(); assert.equal(textarea.value, 'original');
        return;
    }
    assert.equal(options.language, 'php'); assert.equal(options.theme, 'fireball-dark');
    assert.equal(value, 'typed while loading'); assert.equal(textarea.hidden, true);
    value = '<?php edited'; change(); assert.equal(textarea.value, value);
    let prevented = false; win.handlers.beforeunload({preventDefault() {prevented = true;}}); assert.ok(prevented);
    select.value = 'theme-b'; select.handlers.change(); assert.equal(select.value, 'theme-a');
    reset.handlers.click(); assert.equal(value, 'original');
    prevented = false; win.handlers.beforeunload({preventDefault() {prevented = true;}}); assert.equal(prevented, false);
    value = 'saved'; change(); command(); assert.equal(form.submitted, true); assert.equal(textarea.value, 'saved');
    doc.documentElement.dataset.bsTheme = 'light'; observer(); assert.equal(theme, 'vs');
}
(async () => {await scenario(false); await scenario(true); console.log('Theme editor adapter: async input, save, discard, dirty guard, theme switch and load failure passed.');})().catch(error => {console.error(error); process.exit(1);});
// The last chosen source wins without disabling the native file selector.
{
    const managerInput = element('/uploads/old.png'); const uploadInput = element('new.png');
    uploadInput.files = [{}];
    const section = {querySelector: selector => selector === '[name="preview_source"]' ? managerInput : uploadInput};
    vm.runInNewContext(fs.readFileSync('public/assets/default/js/admin-theme-form.js', 'utf8'), {document: {querySelectorAll: () => [section]}});
    uploadInput.handlers.change(); assert.equal(managerInput.value, '');
    managerInput.value = '/uploads/selected.png'; managerInput.handlers.input(); assert.equal(uploadInput.value, '');
    uploadInput.value = 'keep.png'; managerInput.value = ''; managerInput.handlers.input(); assert.equal(uploadInput.value, 'keep.png');
}
