'use strict';
const { chromium, webkit } = require(process.env.FIREBALL_PLAYWRIGHT_MODULE || 'playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.join(__dirname, '..');
const fixture = execFileSync(process.env.FIREBALL_PHP || 'php', [path.join(__dirname, 'editor2_fixture.php')], { encoding: 'utf8' });

(async () => {
    const engine = process.env.FIREBALL_BROWSER === 'webkit' ? webkit : chromium;
    const browser = await engine.launch({
        headless: true,
        ...(process.env.FIREBALL_BROWSER_EXECUTABLE ? { executablePath: process.env.FIREBALL_BROWSER_EXECUTABLE } : {})
    });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('https://editor.test/**', route => {
            const pathname = new URL(route.request().url()).pathname;
            if (pathname.startsWith('/assets/default/')) {
                const file = path.join(root, 'public' + pathname);
                if (fs.existsSync(file)) return route.fulfill({path: file});
            }
            return route.fulfill({contentType: 'text/html; charset=utf-8', body: fixture});
        });
        for (const width of [1920, 1440, 1280, 1024, 768, 430, 393, 390, 360, 320]) {
            await page.setViewportSize({ width, height: width === 1440 ? 900 : 844 });
            await page.goto('https://editor.test/');
            await page.evaluate(() => localStorage.clear());
            await page.addStyleTag({url: 'https://editor.test/assets/default/icons/cartzilla-icons.min.css'});
            for (const file of ['theme.min.css', 'style.css', 'admin-ui.css', 'block-editor.css']) {
                await page.addStyleTag({url: 'https://editor.test/assets/default/css/' + file});
            }
            await page.addScriptTag({ path: path.join(root, 'public/assets/default/js/app-viewport.js') });
            for (const file of ['registry', 'sanitizer', 'importer', 'history', 'editor']) {
                await page.addScriptTag({ path: path.join(root, 'public/assets/default/js/editor2', file + '.js') });
            }
            assert.equal(await page.locator('[data-editor-block]').count(), 3);
            if (process.env.FIREBALL_SCREENSHOT_DIR && [1440, 390].includes(width)) {
                fs.mkdirSync(process.env.FIREBALL_SCREENSHOT_DIR, {recursive: true});
                await page.screenshot({path: path.join(process.env.FIREBALL_SCREENSHOT_DIR, 'editor-' + width + '.png')});
            }
            assert.ok(await page.locator('.fb-editor-workspace__statusbar').evaluate(element => !!element.closest('form[data-post-autosave]')), 'Dialog markup does not prematurely close the document form');
            assert.ok(await page.locator('[data-editor-document-title]').evaluate(element => element.form === document.querySelector('form[data-post-autosave]')), 'Document settings belong to the save form');
            const first = page.locator('[data-block-id="first"]');
            for (const button of await page.locator('.fb-editor-workspace__topbar-actions > button:visible').all()) {
                assert.ok(await button.evaluate(element => element.scrollWidth <= element.clientWidth + 1), 'Header button labels never overflow their click area');
            }
            const blockAction = async (block, action) => {
                if (width < 768 && ['duplicate', 'hide', 'copy'].includes(action)) {
                    await block.locator('[data-block-action="more"]').click();
                    await page.locator('[data-context-action="' + action + '"]').click();
                } else {
                    await block.locator('[data-block-action="' + action + '"]').click();
                }
            };
            assert.equal(await first.locator('[data-block-action="moveUp"]').isDisabled(), true);
            assert.equal(await page.locator('[data-editor-block]').last().locator('[data-block-action="moveDown"]').isDisabled(), true);
            for (const header of await page.locator('[data-editor-block] .fb-editor2-block__header').all()) {
                const inside = await header.evaluate(element => {
                    const surface = element.closest('.fb-editor2-block__surface').getBoundingClientRect();
                    const rect = element.getBoundingClientRect();
                    return rect.top >= surface.top && rect.right <= surface.right && rect.bottom <= surface.bottom;
                });
                assert.ok(inside, 'All block actions live inside the card boundary');
            }
            if (width < 768) {
                assert.ok(await page.locator('.fb-editor-workspace__identity > strong').evaluate(element => element.scrollWidth <= element.clientWidth && getComputedStyle(element).whiteSpace === 'normal'), 'Full editor title is readable');
                assert.ok(await page.locator('.fb-editor2__toolbar-scroll').evaluate(element => element.scrollWidth > element.clientWidth && getComputedStyle(element).overflowX === 'auto'), 'Formatting scrolls instead of shrinking');
                assert.equal(await page.locator('[data-editor-block-style]').evaluate(element => element.getBoundingClientRect().width), 190);
            }
            await first.locator('[data-editor-rich]').fill('Changed paragraph');
            await blockAction(first, 'duplicate');
            assert.equal(await page.locator('[data-editor-block]').count(), 4);
            await page.locator('[data-editor-undo]').click();
            assert.equal(await page.locator('[data-editor-block]').count(), 3);
            await page.locator('[data-editor-redo]').click();
            assert.equal(await page.locator('[data-editor-block]').count(), 4);

            const roundTrip = await page.evaluate(() => {
                const editor = FireballEditor2.getEditors()[0];
                const state = FireballEditor2.importer.extractSnapshot(editor.serializeState(), FireballEditor2);
                return { count: state.blocks.length, text: state.blocks[0].data.html };
            });
            assert.equal(roundTrip.count, 4);
            assert.equal(roundTrip.text, 'Changed paragraph');
            await first.locator('[data-editor-rich]').evaluate(element => {
                element.focus();
                const range = document.createRange(); range.selectNodeContents(element);
                const selection = getSelection(); selection.removeAllRanges(); selection.addRange(range);
                FireballEditor2.getEditors()[0].savedRange = range.cloneRange();
            });
            await page.locator('[data-editor-toolbar] [data-editor-inline="strong"]').click();
            assert.ok(await first.locator('[data-editor-rich] strong, [data-editor-rich] b').count(), 'Formatting still uses the active text selection');
            await page.locator('[data-editor-undo]').click();
            const nativeUndo = await page.evaluate(() => {
                const title = document.querySelector('[data-editor-document-title]');
                const event = new KeyboardEvent('keydown', { key: 'z', ctrlKey: true, bubbles: true, cancelable: true });
                title.dispatchEvent(event);
                return !event.defaultPrevented;
            });
            assert.ok(nativeUndo, 'Title field keeps its native undo shortcut');
            await first.locator('[data-editor-rich]').click();
            await first.locator('[data-block-action="moveDown"]').click();
            assert.equal(await page.locator('[data-editor-block]').nth(1).getAttribute('data-block-id'), 'first');
            await blockAction(first, 'hide');
            assert.ok(await first.evaluate(element => element.classList.contains('is-hidden')));
            await blockAction(first, 'hide');
            await first.locator('[data-block-action="remove"]').click();
            const deleteCard = await page.locator('[data-editor-delete-dialog] .fb-editor2__recovery-card').boundingBox();
            assert.ok(deleteCard.height < 422, 'Short delete confirmation hugs its content');
            await page.locator('[data-editor-delete-confirm]').click();
            assert.equal(await page.locator('[data-editor-block]').count(), 3);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
            assert.equal(await page.evaluate(() => window.scrollY), 0, 'Block actions never scroll the outer document');
            if (width >= 992) {
                const order = await page.evaluate(() => FireballEditor2.getEditors()[0].state.blocks.map(block => block.id));
                await page.evaluate(() => {
                    const blocks = document.querySelectorAll('[data-editor-block]');
                    const transfer = new DataTransfer();
                    blocks[0].querySelector('[data-editor-drag-handle]').dispatchEvent(new DragEvent('dragstart', {bubbles: true, dataTransfer: transfer}));
                    const target = blocks[blocks.length - 1];
                    const clientY = target.getBoundingClientRect().bottom - 1;
                    target.dispatchEvent(new DragEvent('dragover', {bubbles: true, cancelable: true, dataTransfer: transfer, clientY}));
                    target.dispatchEvent(new DragEvent('drop', {bubbles: true, cancelable: true, dataTransfer: transfer, clientY}));
                });
                assert.notDeepEqual(await page.evaluate(() => FireballEditor2.getEditors()[0].state.blocks.map(block => block.id)), order, 'Header drag handle preserves reordering');
                await page.locator('[data-editor-undo]').click();
                assert.deepEqual(await page.evaluate(() => FireballEditor2.getEditors()[0].state.blocks.map(block => block.id)), order);
                await page.evaluate(() => FireballEditor2.getEditors()[0].setMode('structure'));
                const outline = page.locator('[data-editor-outline-item]').last();
                const outlineId = await outline.getAttribute('data-editor-outline-item');
                const count = await page.locator('[data-editor-block]').count();
                await outline.locator('[data-block-action="more"]').click();
                await page.locator('[data-context-action="duplicate"]').click();
                assert.equal(await page.locator('[data-editor-block]').count(), count + 1, 'Structure uses the same duplicate handler');
                await page.locator('[data-editor-undo]').click();
                assert.equal(await page.locator('[data-editor-block]').count(), count);
                assert.ok(await page.locator('[data-block-id="' + outlineId + '"]').count());
                await page.evaluate(() => FireballEditor2.getEditors()[0].setMode('document'));
                const multi = await page.evaluate(() => {
                    const editor = FireballEditor2.getEditors()[0];
                    const firstId = editor.state.blocks[0].id;
                    const lastId = editor.state.blocks.at(-1).id;
                    editor.selectedIds = new Set([firstId, lastId]);
                    editor.activeId = lastId;
                    editor.openContextMenu(firstId, document.querySelector('[data-block-id="' + firstId + '"] [data-block-action="more"]'));
                    const result = {count: editor.selectedIds.size, active: editor.activeId === firstId,
                        view: document.querySelector('[data-block-id="' + firstId + '"]').classList.contains('is-active')};
                    editor.closeContextMenu(); editor.selectBlock(firstId, null, true);
                    return result;
                });
                assert.deepEqual(multi, {count: 2, active: true, view: true}, 'More menu preserves multi-selection and synchronizes the active block');
            }
            if (width < 992) {
                await page.evaluate(() => FireballEditor2.getEditors()[0].openMobilePanels());
                await page.waitForTimeout(250);
                const panel = await page.locator('[data-editor-inspector-panel]').boundingBox();
                assert.ok(panel.y >= 0 && panel.y + panel.height <= 845, 'Sheet fits the mobile viewport');
                const scrollable = await page.locator('[data-editor-inspector]').evaluate(element => {
                    element.scrollTop = 10000;
                    return element.scrollTop > 0 && element.clientHeight < element.scrollHeight;
                });
                assert.ok(scrollable, 'Long settings scroll inside the sheet');
                await page.evaluate(() => FireballEditor2.getEditors()[0].closeMobilePanels());
                await page.waitForTimeout(250);
            }
            assert.deepEqual(errors, []);
            await page.evaluate(() => FireballEditor2.getEditors()[0].addBlock('video', ''));
            assert.equal(await page.locator('[data-block-type="video"] .fb-editor2-block__header').count(), 1, 'Video shares the same header component');
            console.log('PASS typing, duplicate, undo/redo, reorder, hide, delete, snapshot and layout at ' + width + 'px');
        }

        // PWA shell: the editor and sheet must share the same bottom edge.
        // Check real theme styles as well, including loading the theme copy
        // after the served editor stylesheet (it used to override geometry).
        for (const size of [{ width: 390, height: 844 }, { width: 844, height: 390 }]) {
            await page.setViewportSize(size);
            await page.goto('https://editor.test/');
            await page.evaluate(() => {
                localStorage.clear(); // Geometry tests must not open recovery from earlier edit tests.
                document.documentElement.classList.add('pwa-standalone');
                document.body.classList.add('fb-admin-body');
                Object.defineProperty(window, 'visualViewport', {configurable: true, value: Object.assign(new EventTarget(), {
                    height: innerHeight, width: innerWidth, offsetTop: 0, offsetLeft: 0, scale: 1
                })});
            });
            for (const file of ['theme.min.css', 'style.css', 'admin-ui.css', 'block-editor.css']) {
                await page.addStyleTag({ path: path.join(root, 'public/assets/default/css', file) });
            }
            await page.addStyleTag({ path: path.join(root, 'themes/default/assets/css/block-editor.css') });
            await page.addScriptTag({ path: path.join(root, 'public/assets/default/js/app-viewport.js') });
            for (const file of ['registry', 'sanitizer', 'importer', 'history', 'editor']) {
                await page.addScriptTag({ path: path.join(root, 'public/assets/default/js/editor2', file + '.js') });
            }
            const bottom = async selector => {
                const rect = await page.locator(selector).boundingBox();
                return rect.y + rect.height;
            };
            assert.ok(Math.abs(await bottom('[data-editor-workspace]') - size.height) < 1, 'Workspace reaches full PWA viewport');
            assert.ok(Math.abs(await bottom('.fb-editor-workspace__form') - size.height) < 1, 'Form fills workspace');
            assert.ok(Math.abs(await bottom('.fb-editor-workspace__statusbar') - size.height) < 1, 'No unused area below editor footer');
            await page.locator('.fb-editor-workspace__document').evaluate(element => { element.scrollTop = 120; });
            const scrollBefore = await page.locator('.fb-editor-workspace__document').evaluate(element => element.scrollTop);
            await page.evaluate(() => FireballEditor2.getEditors()[0].openMobilePanels());
            await page.waitForTimeout(250);
            assert.ok(Math.abs(await bottom('[data-editor-inspector-panel]') - size.height) < 1, 'Sheet reaches same bottom edge');
            const sheet = await page.locator('[data-editor-inspector-panel]').boundingBox();
            assert.ok(sheet.y >= 0, 'Sheet stays inside viewport in landscape too');
            const keyboardHeight = Math.round(size.height * .55);
            await page.locator('[data-editor-setting="settings.marginTop"]').focus();
            await page.evaluate(height => {
                visualViewport.height = height;
                visualViewport.offsetTop = 80;
                visualViewport.dispatchEvent(new Event('resize'));
                visualViewport.dispatchEvent(new Event('scroll'));
            }, keyboardHeight);
            await page.waitForTimeout(50);
            assert.ok(Math.abs(await bottom('[data-editor-workspace]') - (80 + keyboardHeight)) < 1, 'Workspace follows keyboard viewport even with Safari pan');
            assert.ok(Math.abs(await bottom('[data-editor-inspector-panel]') - (80 + keyboardHeight)) < 1, 'Sheet stays above keyboard');
            assert.equal(await page.locator('[data-editor-workspace]').evaluate(element => element.style.getPropertyValue('--fb-editor-safe-bottom')), '0px', 'Keyboard does not reserve home-indicator inset twice');
            await page.evaluate(() => FireballEditor2.getEditors()[0].closeMobilePanels());
            await page.evaluate(() => {
                visualViewport.height = innerHeight;
                visualViewport.offsetTop = 0;
                visualViewport.dispatchEvent(new Event('resize'));
            });
            await page.waitForTimeout(250);
            assert.ok(Math.abs(await bottom('[data-editor-workspace]') - size.height) < 1, 'Workspace recovers full height after keyboard closes');
            assert.equal(await page.locator('.fb-editor-workspace__document').evaluate(element => element.scrollTop), scrollBefore, 'Closing sheet preserves document scroll');
            assert.deepEqual(errors, []);
            console.log('PASS PWA workspace/sheet bottom edges, orientation and scroll restoration at ' + size.width + 'x' + size.height);
        }
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
