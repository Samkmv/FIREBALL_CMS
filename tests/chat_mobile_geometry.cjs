'use strict';
// Offline geometry regression: real chat styles, no login or network traffic.
const { chromium, webkit } = require(process.env.FIREBALL_PLAYWRIGHT_MODULE || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.join(__dirname, '..');
const inline = Array.from(fs.readFileSync(path.join(root, 'app/Views/themes/default/chat/index.php'), 'utf8')
    .matchAll(/<style[^>]*>([\s\S]*?)<\/style>/g), match => match[1]).join('\n');
const actions = ['reply', 'reaction', 'edit', 'delete'].map(name =>
    `<button type="button" class="chat-message-${name}-btn">${name[0]}</button>`).join('');
const row = direction => `<div class="chat-message-row chat-message-row--${direction}">
    <div class="chat-message-stack has-actions"><div class="chat-message-bubble">Message</div>
    <div class="chat-reaction-picker d-none"><div class="chat-reaction-picker__choices">👍 ❤️ 😂 😮 😢</div></div>
    <div class="chat-message-actions">${actions}</div></div></div>`;
const fixture = `<!doctype html><html class="pwa-standalone chat-mobile-fullscreen chat-viewport-fullscreen">
    <head><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"></head>
    <body style="margin:0"><header class="navbar navbar-expand navbar-sticky sticky-top d-block bg-body z-fixed" data-sticky-element style="height:100px"><div class="container"><button type="button">Menu</button><a class="navbar-brand">MAXIPAPA</a><button type="button">Search</button></div></header>
    <main class="chat-page"><div class="chat-app-shell" data-chat-app><div class="chat-app-layout">
    <div class="chat-layout-main"><div class="chat-thread"><div class="chat-thread__head">Contact</div>
    <div class="chat-thread__body"><div class="chat-messages-surface" data-chat-messages>
    ${row('mine')}${row('theirs')}</div></div>
    <div class="chat-thread__composer"><div class="chat-composer"><input placeholder="Message"></div></div>
    </div></div></div></div></main></body></html>`;
(async () => {
    const browser = await (process.env.FIREBALL_BROWSER === 'webkit' ? webkit : chromium).launch({ headless: true });
    try {
        const page = await browser.newPage();
        for (const theme of ['light', 'dark']) for (const size of [{width:390,height:844}, {width:320,height:568}]) {
            await page.setViewportSize(size);
            await page.goto('about:blank'); // setContent alone preserves previous shell globals.
            await page.setContent(fixture);
            await page.evaluate(theme => {
                document.documentElement.setAttribute('data-bs-theme', theme);
                // Model visualViewport being 60px shorter than the layout viewport.
                Object.defineProperty(window, 'visualViewport', {configurable: true, value: Object.assign(new EventTarget(), {
                    height: innerHeight - 60, offsetTop: 0, scale: 1
                })});
            }, theme);
            for (const file of ['theme.min.css', 'style.css']) {
                await page.addStyleTag({ path: path.join(root, 'public/assets/default/css', file) });
            }
            await page.addStyleTag({ path: path.join(root, 'themes/default/assets/css/style.css') });
            await page.addStyleTag({ content: inline });
            for (const file of ['app-viewport.js', 'chat-viewport.js']) {
                await page.addScriptTag({path: path.join(root, 'public/assets/default/js', file)});
            }
            await page.evaluate(() => FireballChatViewport.sync(false));
            assert.equal(await page.getByText('Viewport', {exact: true}).count(), 0, 'Diagnostics does not add controls to normal CMS UI');
            const composerBottom = async () => {
                const box = await page.locator('.chat-thread__composer').boundingBox();
                return box.y + box.height;
            };
            assert.ok(Math.abs(await composerBottom() - size.height) < 1, 'Closed keyboard: PWA fills native extent instead of reserving another 60px strip');
            for (const direction of ['mine', 'theirs']) {
                const actionRow = page.locator('.chat-message-row--' + direction + ' .chat-message-actions');
                await page.mouse.move(0, 0);
                const before = await actionRow.boundingBox();
                await actionRow.locator('button').first().hover();
                await page.waitForTimeout(180);
                const hover = await actionRow.boundingBox();
                assert.ok(Math.abs(before.y - hover.y) < .1 && Math.abs(before.x - hover.x) < .1, 'Hover cannot translate mobile actions');
                await actionRow.locator('button').first().focus();
                assert.ok(Math.abs((await actionRow.boundingBox()).y - before.y) < .1, 'Focus cannot translate mobile actions');
                assert.ok(await actionRow.locator('button').evaluateAll(buttons => buttons.every(button => {
                    const box = button.getBoundingClientRect();
                    return box.width >= 44 && box.height >= 44;
                })), 'Mobile actions have 44px touch areas');
                await page.locator('.chat-message-row--' + direction + ' .chat-reaction-picker').evaluate(element => element.classList.remove('d-none'));
                assert.ok(Math.abs((await actionRow.boundingBox()).y - before.y) < .1, 'Reaction picker cannot push action row');
            }
            await page.evaluate(() => {
                visualViewport.height = 350;
                FireballChatViewport.sync(true);
            });
            assert.ok(Math.abs(await composerBottom() - 350) < 1, 'Open keyboard: JS visualViewport height remains in control');
            await page.evaluate(() => {
                visualViewport.offsetTop = 200;
                FireballChatViewport.sync(true);
            });
            assert.ok(Math.abs(await composerBottom() - 550) < 1, 'Composer and navigation follow the same panned viewport');
            const header = await page.locator('body > header').boundingBox();
            const brand = await page.locator('.navbar-brand').boundingBox();
            assert.ok(brand.y >= header.y && brand.y + brand.height <= header.y + header.height, 'Header content stays inside its viewport while keyboard is open');
            assert.ok(await page.evaluate(() => document.documentElement.classList.contains('chat-keyboard-visible')), 'Pan does not cancel keyboard detection');
            await page.evaluate(() => {
                visualViewport.height = innerHeight - 60;
                visualViewport.offsetTop = 20; // iOS can retain stale pan after keyboard dismissal.
                FireballChatViewport.sync(false);
            });
            assert.ok(Math.abs(await composerBottom() - size.height) < 1, 'Closing keyboard restores native full PWA extent');
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'No horizontal overflow');
            assert.notEqual(await page.evaluate(() => getComputedStyle(document.body).position), 'fixed', 'Header is not nested inside a second fixed viewport');
            console.log(`PASS ${theme} ${size.width}: PWA bottom, keyboard restore, hover/focus, reactions and touch areas`);
        }
        await page.setViewportSize({width:1440,height:1000});
        await page.evaluate(() => {
            FireballChatViewport.sync(false);
            document.documentElement.classList.remove('chat-mobile-fullscreen', 'pwa-standalone');
            document.documentElement.style.setProperty('--chat-mobile-viewport-height', '800px');
        });
        assert.equal(await page.locator('.chat-page').evaluate(element => getComputedStyle(element).height), '800px', 'Desktop keeps JS viewport sizing');
        assert.equal(await page.locator('.chat-message-actions').first().evaluate(element => getComputedStyle(element).position), 'absolute', 'Desktop action positioning is unchanged');
        assert.notEqual(await page.evaluate(() => getComputedStyle(document.body).position), 'fixed', 'Desktop document lock is released');
        console.log('PASS desktop geometry preserved');
        await page.setViewportSize({width: 390, height: 844});
        await page.route('https://chat.test/**', route => route.fulfill({contentType: 'text/html', body: fixture}));
        await page.goto('https://chat.test/?viewport_debug=1');
        for (const file of ['theme.min.css', 'style.css']) {
            await page.addStyleTag({path: path.join(root, 'public/assets/default/css', file)});
        }
        for (const file of ['app-viewport.js', 'chat-viewport.js']) {
            await page.addScriptTag({path: path.join(root, 'public/assets/default/js', file)});
        }
        await page.evaluate(() => FireballChatViewport.sync(false));
        const report = new Promise(resolve => page.once('dialog', async dialog => {
            resolve(JSON.parse(dialog.defaultValue()));
            await dialog.dismiss();
        }));
        await page.getByText('Viewport', {exact: true}).click();
        const diagnostics = await report;
        assert.equal(diagnostics.applications[0].standalone, true);
        assert.ok(diagnostics.headerContent && diagnostics.composer, 'Device diagnostics captures header and composer geometry');
        assert.ok(!JSON.stringify(diagnostics).includes('Message'), 'Diagnostics contains no message text or field values');
        console.log('PASS opt-in geometry diagnostics without message content');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
