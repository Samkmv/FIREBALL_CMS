'use strict';
const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium, firefox } = require('playwright');
const root = path.resolve(__dirname, '../public/assets/default');
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aG1cAAAAASUVORK5CYII=', 'base64');

(async () => {
    for (const [name, type] of Object.entries({ chromium, firefox })) {
        const browser = await type.launch({ headless: true });
        try {
            const page = await browser.newPage({ viewport: { width: 1200, height: 800 } });
            const pending = new Map(), seen = new Set();
            let active = 0, maximum = 0, wake = 0, manifest = 0;
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('**/*', async route => {
                const url = new URL(route.request().url());
                if (url.pathname.endsWith('.jpg')) {
                    if (seen.has(url.pathname)) { return route.fulfill({ contentType: 'image/png', body: png }); }
                    seen.add(url.pathname); active++; maximum = Math.max(maximum, active);
                    return new Promise(resolve => pending.set(url.pathname, async (ok = true) => {
                        pending.delete(url.pathname); active--;
                        await route.fulfill({ status: ok ? 200 : 404, contentType: 'image/png', body: ok ? png : Buffer.alloc(0) });
                        resolve();
                    }));
                }
                if (url.pathname.includes('/wake')) { wake++; }
                if (url.pathname.endsWith('.m3u8')) { manifest++; }
                return route.fulfill({ contentType: 'text/html', body: '<html><body></body></html>' });
            });
            await page.goto('https://poster.test/');
            await page.addStyleTag({ path: path.join(root, 'css/fireplayer.css') });
            await page.addScriptTag({ path: path.join(root, 'js/fireplayer.js') });
            const initial = await page.evaluate(() => {
                window.players = [];
                for (let i = 0; i < 10; i++) {
                    const container = document.createElement('div'); document.body.appendChild(container);
                    Object.assign(container.style, { position: 'absolute', width: '200px', height: '110px', top: (i < 4 ? i * 130 : 3000 + i * 150) + 'px' });
                    players.push(new FirePlayer(container, { src: 'https://poster.test/stream-' + i + '/index.m3u8',
                        ...(i === 0 ? { poster: '/explicit.jpg' } : {}) }));
                }
                return players.map(p => ({ poster: p.media.getAttribute('poster'), state: p._posterState, url: p.options.poster }));
            });
            assert(initial.every(p => p.poster === null && p.state === 'idle'));
            assert.equal(initial[0].url, '/explicit.jpg');
            assert.equal(initial[1].url, 'https://poster.test/tn-1.jpg');
            const wait = async predicate => { const deadline = Date.now() + 5000; while (!predicate()) { if (Date.now() > deadline) { throw new Error('Poster request wait timed out'); } await new Promise(r => setTimeout(r, 20)); } };
            await wait(() => pending.size === 2); assert.equal(maximum, 2);
            await pending.get('/explicit.jpg')();
            await page.waitForFunction(() => players[0]._posterState === 'loaded');
            assert.equal(await page.evaluate(() => players[0].media.getAttribute('poster')), '/explicit.jpg');
            await wait(() => pending.has('/tn-2.jpg'));
            await pending.get('/tn-1.jpg')(false);
            await page.waitForFunction(() => players[1]._posterState === 'failed');
            assert.equal(await page.evaluate(() => players[1].media.getAttribute('poster')), null);
            await wait(() => pending.has('/tn-3.jpg'));
            await pending.get('/tn-2.jpg')(); await pending.get('/tn-3.jpg')();
            await page.waitForFunction(() => players[2]._posterState === 'loaded' && players[3]._posterState === 'loaded');
            assert.equal(seen.size, 4); assert.equal(wake, 0); assert.equal(manifest, 0);
            await page.evaluate(() => window.scrollTo(0, 3600));
            await wait(() => seen.size > 4); assert.equal(maximum, 2);
            await page.evaluate(() => players.forEach(p => p.destroy()));
            assert.deepEqual(errors, []);
            console.log('PASS ' + name + ': real constructor, explicit/inferred posters, two slots, 404, viewport scrolling, zero HLS/wake before Play');
        } finally { await browser.close(); }
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
