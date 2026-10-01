'use strict';
const { chromium } = require(process.env.FIREBALL_PLAYWRIGHT_MODULE || 'playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.join(__dirname, '..');
const php = process.env.FIREBALL_PHP || 'php';
const editorHtml = execFileSync(php, [path.join(__dirname, 'editor2_fixture.php')], { encoding: 'utf8' });
const publicHtml = execFileSync(php, [path.join(__dirname, 'downloads_block.php'), '--render'], { encoding: 'utf8' });
(async () => {
    const browser = await chromium.launch({ headless: true });
    try {
        const page = await browser.newPage();
        const errors = [];
        let uploads = 0;
        page.on('pageerror', error => errors.push(error.message));
        await page.route('https://editor.test/**', async route => {
            const pathname = new URL(route.request().url()).pathname;
            if (pathname.startsWith('/assets/default/')) return route.fulfill({ path: path.join(root, 'public', pathname) });
            if (pathname === '/admin/block-editor/upload-file') {
                uploads++;
                return route.fulfill({ json: { status: 'success', file: { url: '/uploads/posts/downloads/upload.txt', name: 'Файл с устройства.txt', size: 8 } } });
            }
            if (pathname === '/save') return route.fulfill({ json: { status: 'success', id: 7 } });
            return route.fulfill({ contentType: 'text/html; charset=utf-8', body: pathname === '/public' ? publicHtml : editorHtml });
        });
        for (const width of [1440, 768, 430, 390, 320]) {
            await page.setViewportSize({ width, height: 844 });
            await page.goto('https://editor.test/');
            await page.evaluate(() => localStorage.clear());
            for (const file of ['theme.min.css', 'style.css', 'admin-ui.css', 'block-editor.css']) await page.addStyleTag({ url: '/assets/default/css/' + file });
            await page.addStyleTag({ url: '/assets/default/icons/cartzilla-icons.min.css' });
            await page.addScriptTag({ path: path.join(root, 'public/assets/default/js/app-viewport.js') });
            for (const file of ['registry', 'sanitizer', 'importer', 'history', 'editor']) await page.addScriptTag({ path: path.join(root, 'public/assets/default/js/editor2', file + '.js') });
            await page.evaluate(() => {
                const editor = FireballEditor2.getEditors()[0];
                editor.addBlock('downloads', '');
                window.open = url => { window.pickerUrl = url; return {}; };
            });
            const block = page.locator('[data-editor-block][data-block-type="downloads"]');
            // Production file manager callback, including original filename/bytes metadata.
            await block.locator('[data-editor-download-action="manager"]').click();
            await page.evaluate(() => {
                const editor = FireballEditor2.getEditors()[0];
                editor.handleFileSelectionMessage({ origin: location.origin, data: { type: 'fireball:file:selected', field: new URL(window.pickerUrl).searchParams.get('field'), value: '/uploads/files/prices.xlsx', name: 'Прайс-лист.xlsx', size: 364544 } });
            });
            const chooserPromise = page.waitForEvent('filechooser');
            await block.locator('[data-editor-download-action="upload"]').click();
            await (await chooserPromise).setFiles({ name: 'upload.txt', mimeType: 'text/plain', buffer: Buffer.from('contents') });
            await page.waitForFunction(() => FireballEditor2.getEditors()[0].activeBlock().data.items.length === 2);
            // Real native file drag/drop event on the block's drop zone.
            await block.locator('[data-editor-download-drop]').evaluate(element => {
                const data = new DataTransfer();
                data.items.add(new File(['contents'], 'dropped.txt', { type: 'text/plain' }));
                element.dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: data }));
            });
            await page.waitForFunction(() => FireballEditor2.getEditors()[0].activeBlock().data.items.length === 3);
            await block.locator('[data-editor-download-action="up"]').nth(1).click();
            assert.equal(await block.locator('.fb-downloads__info').first().innerText(), 'Файл с устройства.txt\nTXT · 8 B');
            await block.locator('[data-editor-download-action="remove"]').last().click();
            assert.equal(await block.locator('.fb-downloads__row').count(), 2);
            await page.evaluate(() => {
                const editor = FireballEditor2.getEditors()[0];
                if (innerWidth < 992) editor.openMobilePanels();
            });
            const inspector = page.locator('[data-editor-inspector]');
            await inspector.locator('[data-editor-setting="data.title"]').fill('Полезные файлы');
            await inspector.locator('[data-editor-setting="data.description"]').fill('Материалы по проекту');
            await inspector.locator('[data-editor-setting="data.showIcon"]').uncheck();
            await inspector.locator('[data-editor-setting="data.showSize"]').uncheck();
            assert.equal(await block.locator('.fb-downloads__icon').count(), 0);
            const saved = await page.evaluate(() => {
                const editor = FireballEditor2.getEditors()[0];
                const snapshot = editor.serializeState();
                return { html: snapshot, block: FireballEditor2.importer.extractSnapshot(snapshot, FireballEditor2).blocks.find(block => block.type === 'downloads') };
            });
            assert.equal(saved.block.data.title, 'Полезные файлы');
            assert.equal(saved.block.data.showIcon, false);
            assert.equal(saved.block.data.showSize, false);
            assert.equal(saved.block.data.items.length, 2);
            assert.ok(saved.html.includes('download="Файл с устройства.txt"'));
            if (process.env.FIREBALL_SCREENSHOT_DIR && [1440, 390].includes(width)) {
                await inspector.locator('[data-editor-setting="data.showIcon"]').check();
                await inspector.locator('[data-editor-setting="data.showSize"]').check();
                fs.mkdirSync(process.env.FIREBALL_SCREENSHOT_DIR, { recursive: true });
                await page.screenshot({ path: path.join(process.env.FIREBALL_SCREENSHOT_DIR, 'downloads-settings-' + width + '.png') });
                if (width < 992) await page.evaluate(() => FireballEditor2.getEditors()[0].closeMobilePanels());
                await block.scrollIntoViewIfNeeded();
                await page.screenshot({ path: path.join(process.env.FIREBALL_SCREENSHOT_DIR, 'downloads-editor-' + width + '.png') });
                if (width < 992) await page.evaluate(() => FireballEditor2.getEditors()[0].openMobilePanels());
            }
            // Toggles/buttons in the inspector use the same component, not an alternative upload flow.
            await inspector.locator('[data-editor-download-action="manager"]').click();
            assert.ok(await page.evaluate(() => new URL(window.pickerUrl).searchParams.get('picker') === '1'));
            await page.goto('https://editor.test/public');
            for (const theme of ['light', 'dark']) {
                await page.evaluate(theme => document.documentElement.setAttribute('data-bs-theme', theme), theme);
                // Let the template's native color transitions finish before visual QA.
                await page.waitForTimeout(250);
                assert.equal(await page.locator('.fb-downloads__row').count(), 3);
                assert.equal(await page.locator('a[download]').count(), 3);
                if (theme === 'dark') assert.ok(await page.locator('a[download]').first().evaluate(element => {
                    const channels = getComputedStyle(element).color.match(/[\d.]+/g).slice(0, 3).map(Number);
                    return channels.every(value => value >= 200);
                }), 'Native CMS dark button contrast, allowing transition rounding');
                assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'No horizontal overflow at ' + width + '/' + theme);
                for (const button of await page.locator('a[download]').all()) {
                    assert.ok(await button.evaluate(element => element.getBoundingClientRect().height >= 44), 'Download touch target is at least 44px');
                }
                if (process.env.FIREBALL_SCREENSHOT_DIR && [1440, 390].includes(width)) {
                    fs.mkdirSync(process.env.FIREBALL_SCREENSHOT_DIR, { recursive: true });
                    await page.screenshot({ path: path.join(process.env.FIREBALL_SCREENSHOT_DIR, 'downloads-' + width + '-' + theme + '.png') });
                }
            }
            console.log('PASS downloads editor and public UI at ' + width + 'px, light/dark');
        }
        assert.equal(uploads, 10, 'Each local/drag action uploads once, without event bubbling duplicates');
        assert.deepEqual(errors, []);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
