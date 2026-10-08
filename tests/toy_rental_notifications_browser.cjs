'use strict';
// Real CMS notification feed and toast code; intercepted routes only, no DB or Push.
const {chromium} = require('playwright');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
let checks = 0;
const check = (value, message) => { checks++; assert.ok(value, message); };

(async () => {
    const browser = await chromium.launch({headless:true});
    try {
        for (const assetRoot of ['public', 'themes/default']) for (const theme of ['light','dark']) for (const width of [390,1440]) {
            const page = await browser.newPage({viewport:{width,height:844}});
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            let items = [];
            await page.route('https://toy-notify.test/**', route => {
                const url = new URL(route.request().url());
                if (url.pathname === '/notifications/feed') return route.fulfill({json:{status:true,items,notification_count:items.length}});
                if (url.pathname.startsWith('/assets/')) {
                    const file = url.pathname.endsWith('/main.js') ? path.join(root,assetRoot,'assets/default/js/main.js') : path.join(root,'public',url.pathname);
                    const mainFile = assetRoot === 'themes/default' && url.pathname.endsWith('/main.js') ? path.join(root,assetRoot,'assets/js/main.js') : file;
                    return route.fulfill({path:mainFile});
                }
                if (url.pathname === '/admin/toy-rental') return route.fulfill({contentType:'text/html',body:'Rental panel'});
                return route.fulfill({contentType:'text/html',body:`<!doctype html><html lang="ru" data-bs-theme="${theme}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
                    <link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/css/style.css"><link rel="stylesheet" href="/assets/default/css/admin-ui.css"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css"></head>
                    <body class="fb-admin-body"><main class="container py-4"><div data-notifications-center data-feed-url="/notifications/feed" data-empty-text="Empty"><div data-notifications-list></div></div></main>
                    <script>const baseUrl=location.origin;</script><script src="/assets/default/js/jquery-3.7.1.min.js"></script><script src="/assets/default/bootstrap/js/bootstrap.bundle.min.js"></script><script src="/assets/default/js/main.js"></script></body></html>`});
            });
            await page.goto('https://toy-notify.test/admin');
            await page.waitForFunction(()=>document.querySelector('[data-notifications-list]').textContent==='Empty');
            items = [{type:'toy_rental',notification_id:1,source_label:'Прокат машинок',title:'Время поездки закончилось',text:'Ferrari: верните машинку. <img src=x onerror=window.leaked=true>',url:'/admin/toy-rental?notice=1',created_at:'2026-10-08 12:00:00',sort_id:1}];
            await page.evaluate(()=>document.dispatchEvent(new Event('fireball:notifications-changed')));
            const toast = page.locator('[data-app-toast-container] .app-toast--warning').filter({hasText:'Время поездки закончилось'}).first();
            await toast.waitFor({state:'visible'});
            check(await page.locator('[data-notifications-list] .badge.text-bg-warning').count()===1, 'Overdue feed item uses the CMS warning badge');
            check(await toast.locator('.toast-header, .bg-white, img').count()===0 && await toast.getAttribute('data-bs-theme')===null, 'Overdue toast uses the common layout and inherits the active theme');
            check(!(await page.evaluate(()=>window.leaked)), 'Notification text is escaped');
            const actual = await toast.evaluate(element=>{const style=getComputedStyle(element);return [style.backgroundColor,style.color,style.borderRadius,style.borderLeftColor];});
            await page.evaluate(()=>window.toastr.warning('Reference message','Reference title'));
            const reference = await page.locator('[data-app-toast-container] .app-toast--warning').last().evaluate(element=>{const style=getComputedStyle(element);return [style.backgroundColor,style.color,style.borderRadius,style.borderLeftColor];});
            check(JSON.stringify(actual)===JSON.stringify(reference), `Matches the CMS warning style ${assetRoot}/${theme}/${width}`);
            check(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth), 'Notifications do not overflow on mobile');
            if(assetRoot==='public' && theme==='dark' && width===390) await page.screenshot({path:'/tmp/toy-rental-overdue-dark.png'});
            await toast.locator('.btn-close').click();
            await toast.waitFor({state:'detached'});
            check(new URL(page.url()).pathname==='/admin', 'Closing an overdue notification does not navigate');
            await page.evaluate(()=>window.toastr.remove());
            await page.evaluate(()=>document.dispatchEvent(new Event('fireball:notifications-changed')));
            await page.waitForTimeout(300);
            check(await page.locator('[data-app-toast-container] .toast').count()===0, 'Repeated feed refresh does not repeat the overdue toast');
            items = [{...items[0], notification_id:2,sort_id:2,url:'/admin/toy-rental?notice=2'}];
            await page.evaluate(()=>document.dispatchEvent(new Event('fireball:notifications-changed')));
            await toast.waitFor({state:'visible'});
            await toast.locator('.toast-body').click();
            await page.waitForURL('**/admin/toy-rental?notice=2');
            check(new URL(page.url()).pathname==='/admin/toy-rental', 'Clicking an overdue toast opens the rental panel');
            check(errors.length===0, 'No browser errors: '+errors.join('; '));
            await page.close();
        }
        console.log(`Toy rental notification checks passed: ${checks}`);
    } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
