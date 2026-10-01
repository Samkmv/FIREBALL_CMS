'use strict';
// Real multipart HTTP uploads into a disposable directory, not public/uploads.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const net = require('node:net');
const { spawn } = require('node:child_process');
(async () => {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'fireball-download-upload-'));
    const socket = net.createServer();
    await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
    const port = socket.address().port;
    await new Promise(resolve => socket.close(resolve));
    const server = spawn(process.env.FIREBALL_PHP || 'php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'downloads_upload_fixture.php')], { env: { ...process.env, FIREBALL_UPLOAD_TEST_DIR: directory }, stdio: 'ignore' });
    const base = 'http://127.0.0.1:' + port;
    try {
        let ready = false;
        for (let attempt = 0; attempt < 100; attempt++) {
            try { ready = (await fetch(base + '/health')).ok; } catch {}
            if (ready) break;
            await new Promise(resolve => setTimeout(resolve, 50));
        }
        assert.ok(ready, 'PHP fixture starts');
        const upload = async (name, contents, entity = 'post') => {
            const body = new FormData();
            body.append('entity_type', entity);
            body.append('file', new Blob([contents]), name);
            const response = await fetch(base + '/upload', { method: 'POST', body });
            return { status: response.status, result: await response.json() };
        };
        const success = await upload('Материалы.txt', 'Useful material');
        assert.equal(success.status, 200);
        assert.equal(success.result.file.name, 'Материалы.txt');
        assert.equal(success.result.file.size, 15);
        assert.ok(success.result.file.url.startsWith('/uploads/posts/downloads/'));
        const stored = path.join(directory, success.result.file.url.replace('/uploads/', ''));
        assert.equal(fs.readFileSync(stored, 'utf8'), 'Useful material');
        const page = await upload('Materials.txt', 'Page material', 'page');
        assert.ok(page.result.file.url.startsWith('/uploads/pages/downloads/'));
        const renamed = await upload('Материалы.txt', 'Another file');
        assert.notEqual(renamed.result.file.url, success.result.file.url, 'Existing file is not overwritten');
        for (const [name, contents] of [['script.php', '<?php echo 1;'], ['fake.pdf', 'This is not a PDF'], ['empty.txt', ''], ['large.txt', 'x'.repeat(1025)]]) {
            assert.equal((await upload(name, contents)).status, 422, 'Reject ' + name);
        }
        assert.equal(fs.readdirSync(path.join(directory, 'posts/downloads')).length, 2, 'Rejected files are never stored');
        console.log('PASS real file uploads: metadata, posts/pages, collisions, extension/MIME/empty/size validation');
    } finally {
        server.kill('SIGTERM');
        // Remove only the fixture directory allocated by this test.
        fs.rmSync(directory, { recursive: true, force: true });
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
