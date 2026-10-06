// Real PHP multipart parsing and shared validation, only a temporary localhost fixture.
const { spawn } = require('node:child_process'), http = require('node:http'), net = require('node:net');
const path = require('node:path'), assert = require('node:assert/strict');
const php = process.env.PHP_BIN || 'php';
const request = (port, body = null) => new Promise((resolve, reject) => {
    const req = http.request({ hostname: '127.0.0.1', port, path: '/', method: body ? 'POST' : 'GET', headers: body ? { 'Content-Type': 'multipart/form-data; boundary=fireball-fixture', 'Content-Length': body.length } : {} }, res => {
        let text = ''; res.on('data', data => text += data); res.on('end', () => { try { resolve({ status: res.statusCode, data: JSON.parse(text) }); } catch (e) { reject(e); } });
    }); req.on('error', reject); req.end(body);
});
const multipart = bytes => Buffer.concat([Buffer.from('--fireball-fixture\r\nContent-Disposition: form-data; name="needCSRFToken"\r\n\r\nfixture\r\n--fireball-fixture\r\nContent-Disposition: form-data; name="file"; filename="sound.mp3"\r\nContent-Type: audio/mpeg\r\n\r\n'), bytes, Buffer.from('\r\n--fireball-fixture--\r\n')]);
(async () => {
    const listener = net.createServer(); await new Promise(resolve => listener.listen(0, '127.0.0.1', resolve));
    const port = listener.address().port; await new Promise(resolve => listener.close(resolve));
    const child = spawn(php, ['-d', 'upload_max_filesize=64K', '-d', 'post_max_size=128K', '-d', 'display_errors=0', '-S', '127.0.0.1:' + port, path.join(__dirname, 'fixtures/upload_http.php')], { env: { ...process.env, FIREBALL_UPLOAD_FIXTURE: '1' }, stdio: 'ignore' });
    try {
        let ready = false;
        for (let i = 0; i < 50; i++) { try { await request(port); ready = true; break; } catch { await new Promise(resolve => setTimeout(resolve, 100)); } }
        assert.ok(ready, 'Fixture starts');
        const frame = Buffer.concat([Buffer.from([0xff, 0xfb, 0x90, 0x64]), Buffer.alloc(413)]);
        const result = await request(port, multipart(Buffer.concat([frame, frame, frame, frame])));
        assert.equal(result.status, 200); assert.equal(result.data.mime, 'audio/mpeg'); assert.equal(result.data.native_upload, true);
        const fileLimit = await request(port, multipart(Buffer.alloc(96 * 1024)));
        assert.equal(fileLimit.status, 422); assert.equal(fileLimit.data.php_error, 1); assert.equal(fileLimit.data.key, 'upload_error_size');
        assert.ok(fileLimit.data.message.includes('0.06 MiB') && !fileLimit.data.message.includes('/tmp/'));
        const postLimit = await request(port, multipart(Buffer.alloc(150 * 1024)));
        assert.equal(postLimit.status, 413); assert.equal(postLimit.data.post_empty, true); assert.equal(postLimit.data.files_empty, true);
        console.log('11 native multipart upload checks passed');
    } finally { child.kill('SIGTERM'); }
})().catch(e => { console.error(e); process.exitCode = 1; });
