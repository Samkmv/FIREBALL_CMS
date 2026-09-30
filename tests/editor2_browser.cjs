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
        await page.route('https://editor.test/**', route => route.fulfill({ contentType: 'text/html', body: fixture }));
        for (const width of [1440, 390, 320]) {
            await page.setViewportSize({ width, height: width === 1440 ? 900 : 844 });
            await page.goto('https://editor.test/');
            await page.evaluate(() => localStorage.clear());
            await page.addStyleTag({ path: path.join(root, 'public/assets/default/css/block-editor.css') });
            for (const file of ['registry', 'sanitizer', 'importer', 'history', 'editor']) {
                await page.addScriptTag({ path: path.join(root, 'public/assets/default/js/editor2', file + '.js') });
            }
            assert.equal(await page.locator('[data-editor-block]').count(), 3);
            const first = page.locator('[data-block-id="first"]');
            await first.locator('[data-editor-rich]').fill('Changed paragraph');
            await first.locator('[data-block-action="duplicate"]').click();
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
            await first.locator('[data-block-action="hide"]').click();
            assert.ok(await first.evaluate(element => element.classList.contains('is-hidden')));
            await first.locator('[data-block-action="hide"]').click();
            await first.locator('[data-block-action="remove"]').click();
            const deleteCard = await page.locator('[data-editor-delete-dialog] .fb-editor2__recovery-card').boundingBox();
            assert.ok(deleteCard.height < 422, 'Short delete confirmation hugs its content');
            await page.locator('[data-editor-delete-confirm]').click();
            assert.equal(await page.locator('[data-editor-block]').count(), 3);
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
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
            console.log('PASS typing, duplicate, undo/redo, reorder, hide, delete, snapshot and layout at ' + width + 'px');
        }
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
