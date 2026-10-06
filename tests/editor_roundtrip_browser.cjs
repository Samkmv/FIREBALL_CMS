// Real editor serializers/importer; PHP persistence is separately tested by page_content_unit.php.
const { chromium } = require('playwright');
const fs = require('node:fs'), path = require('node:path'), assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
(async () => {
    const browser = await chromium.launch({ headless: true }); let checks = 0;
    try {
        const page = await browser.newPage();
        await page.route('**/*', route => route.fulfill({ body: '<!doctype html><html><body></body></html>', contentType: 'text/html' }));
        await page.goto('https://editor.test/');
        for (const file of ['registry', 'sanitizer', 'importer', 'history', 'editor']) await page.addScriptTag({ content: fs.readFileSync(path.join(root, 'public/assets/default/js/editor2', file + '.js'), 'utf8') });
        const result = await page.evaluate(() => {
            const api = window.FireballEditor2;
            const types = ['downloads', 'gallery', 'slider', 'faq', 'newsletter', 'alert', 'audio', 'video'];
            types.forEach(type => api.registerBlockType(type, { default_content: {} }));
            const editor = Object.create(api.Editor.prototype);
            editor.labels = { download: 'Скачать' }; editor.config = {};
            editor.state = api.importer.normalizeState({ version: 2, blocks: types.map(type => ({ id: 'block_' + type, type,
                data: { title: 'Заголовок', description: 'Описание', caption: 'Подпись', src: '/uploads/sound.mp3', text: 'Text', variant: 'warning',
                    items: type === 'downloads' ? [{ url: '/uploads/file.pdf', name: 'Файл.pdf', size: 2048 }] : [{ src: '/uploads/p.jpg', alt: 'Alt', caption: 'Caption', question: 'Question', answer: '<strong>Answer</strong>' }] },
                settings: { width: 'wide', marginTop: 8, marginBottom: 16, hiddenOn: ['mobile'], anchor: 'a_' + type }, meta: { custom: 'kept' }
            })) }, api);
            const failures = []; let checks = 0;
            for (let cycle = 0; cycle < 3; cycle++) {
                const expected = JSON.stringify(editor.historyState());
                const html = editor.serializeState();
                const reopened = api.importer.extractSnapshot(html, api);
                checks++;
                if (JSON.stringify(reopened) !== expected) failures.push('snapshot cycle ' + cycle);
                for (const type of types) {
                    checks++;
                    if (!html.includes('data-fb-block="' + type + '"')) failures.push('public fallback ' + type);
                }
                editor.state = reopened;
                editor.state.blocks.forEach(b => { b.data.title += ' edited'; b.settings.marginBottom += 1; });
            }
            return { failures, checks };
        });
        assert.deepEqual(result.failures, []); checks += result.checks;
        console.log(`Editor serializer/importer browser checks passed: ${checks}`);
    } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
