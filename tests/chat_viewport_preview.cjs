'use strict';
// Local fixture server for visual QA with cua_repl. No CMS/auth/database access.
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const root = path.resolve(__dirname, '..');
const port = Number(process.env.CHAT_VIEWPORT_PORT || 8897);
const server = http.createServer((request, response) => {
    const url = new URL(request.url, 'http://localhost');
    const pathname = url.pathname;
    if (pathname === '/delayed-image.png') {
        // A fixture pixel that deliberately arrives after the message layout.
        setTimeout(() => {
            response.writeHead(200, {'Content-Type':'image/png', 'Cache-Control':'no-store'});
            response.end(Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aFYQAAAAASUVORK5CYII=', 'base64'));
        }, 350);
        return;
    }
    if (pathname === '/modals') {
        const php = process.env.PHP_BIN || '/Applications/MAMP/bin/php/php8.2.0/bin/php';
        try {
            const html = execFileSync(php, [path.join(__dirname, 'fixtures/chat_modals.php'), url.searchParams.get('theme') || 'dark', url.searchParams.get('assets') || '', url.searchParams.get('source') || ''], {encoding: 'utf8'});
            response.writeHead(200, {'Content-Type':'text/html;charset=utf-8', 'Cache-Control':'no-store'});response.end(html);
        } catch {response.writeHead(500);response.end('Fixture rendering failed');}
        return;
    }
    const asset = /^(?:\/assets\/default|\/themes\/default\/assets)\/(?:css|js|icons|bootstrap\/js)\/[\w./-]+\.(?:css|js|woff2?|ttf)$/;
    let file;
    if (pathname === '/') file = path.join(__dirname, 'fixtures/chat_viewport.html');
    else if (asset.test(pathname) && !pathname.split('/').includes('..')) file = path.join(root, pathname.startsWith('/assets') ? 'public' : '', pathname);
    if (!file || !fs.existsSync(file)) {response.writeHead(404);response.end();return;}
    response.setHeader('Cache-Control','no-store');
    response.setHeader('Content-Type', file.endsWith('.html') ? 'text/html;charset=utf-8' : file.endsWith('.css') ? 'text/css' : file.endsWith('.js') ? 'application/javascript' : 'font/woff2');
    fs.createReadStream(file).pipe(response);
});
server.listen(port, '127.0.0.1', () => console.log(`Chat viewport fixture: http://127.0.0.1:${port}/`));
