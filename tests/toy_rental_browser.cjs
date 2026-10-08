'use strict';
// Real plugin templates/assets, local intercepted requests only; no CMS DB or phone push.
const {chromium} = require('playwright');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const fixture = (locale, mode, clock, id = 1, duration = 10) => execFileSync(process.env.PHP_BIN || 'php', [path.join(__dirname, 'fixtures/toy_rental.php'), locale, mode, String(clock), String(id), String(duration)], {encoding: 'utf8'});
const shell = html => `<!doctype html><html data-bs-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="needCSRFToken" content="toy-fixture"><link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/css/style.css"><link rel="stylesheet" href="/assets/default/css/admin-ui.css"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css"><link rel="stylesheet" href="/admin/toy-rental/assets/toy-rental.css"></head><body class="fb-admin-body"><div class="fb-admin"><div class="fb-admin-main"><main class="fb-content"><div class="fb-page-content">${html}</div></main></div></div><script src="/assets/default/bootstrap/js/bootstrap.bundle.min.js"></script><script src="/admin/toy-rental/assets/toy-rental.js"></script></body></html>`;
let checks = 0;
function check(value, message) { checks++; assert.ok(value, message); }
(async () => {
    const browser = await chromium.launch({headless: true, ...(process.env.CHROME_BIN ? {executablePath:process.env.CHROME_BIN} : {})});
    try {
        for (const locale of ['ru','en','de','zh-cn']) {
            const page = await browser.newPage();
            const now = Math.floor(Date.now() / 1000);
            for (const mode of ['settings', 'car-form']) {
                const formHtml = fixture(locale, mode, now);
                check(!/name="(?:default_price|price_per_ride)"/.test(formHtml), `No separate fixed price field ${locale}/${mode}`);
                check(/name="(?:default_minute_price|price_per_minute)"/.test(formHtml), `Minute rate remains editable ${locale}/${mode}`);
                check(!/toy_rental_[a-z_]+/.test(formHtml), `Pricing form is translated ${locale}/${mode}`);
            }
            const states = Array.from({length: 6}, (_, i) => ({id: i + 1, mode: i === 4 ? 'metered' : (i === 5 ? 'maintenance' : 'available'), start: i === 4 ? now - 30 : now}));
            let starts = [], completes = [], syncs = 0, navigations = 0, failNext = false, delayNext = false;
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            const state = () => ({status:true, cards:states.map(s => ({car_id:s.id, ride_id:['available','maintenance'].includes(s.mode) ? 0 : 100 + s.id, status:s.mode === 'overdue' ? 'overdue' : (s.mode === 'available' ? 'available' : (s.mode === 'maintenance' ? 'maintenance' : 'active')), html:fixture(locale, s.mode, s.start, s.id, s.duration)})), stats:{rides_total:starts.length+1,active:states.filter(s => ['fixed','metered','cap'].includes(s.mode)).length,overdue:states.filter(s => s.mode === 'overdue').length,revenue_total:starts.filter(s=>s.get('billing_type')==='fixed').reduce((sum,s)=>sum+Number(s.get('duration_minutes'))*25,0)}});
            await page.route('**/*', async route => {
                const req = route.request(), url = new URL(req.url());
                if (url.pathname.startsWith('/assets/')) return route.fulfill({path:path.join(root, 'public', url.pathname)});
                if (url.pathname.endsWith('toy-rental.css') || url.pathname.endsWith('toy-rental.js')) return route.fulfill({path:path.join(root,'plugins/toy-car-rental/assets',path.basename(url.pathname))});
                if (url.pathname.endsWith('/state')) return route.fulfill({json:state()});
                if (req.method() === 'POST') {
                    const data = new URLSearchParams(req.postData());
                    check(data.get('needCSRFToken') === 'toy-fixture' && req.headers()['x-requested-with'] === 'XMLHttpRequest', 'AJAX carries CSRF and request contract');
                    if (url.pathname.endsWith('/sync-overdue')) {
                        syncs++;
                        for (const s of states) if (s.mode === 'fixed') s.mode = 'overdue'; else if (s.mode === 'cap') s.mode = 'available';
                        return route.fulfill({json:{status:true, updated:1}});
                    }
                    if (delayNext) { delayNext = false; await new Promise(resolve => setTimeout(resolve, 200)); }
                    if (failNext) { failNext=false; return route.fulfill({status:422,json:{status:false,message:'<img src=x onerror=window.bad=true> Error'}}); }
                    const s = states.find(s => s.id === Number(data.get('car_id') || Number(data.get('id')) - 100));
                    if (url.pathname.endsWith('/start')) { starts.push(data); s.mode=data.get('billing_type'); s.start=now; s.duration=Number(data.get('duration_minutes')) || 10; }
                    else { completes.push(data); s.mode='available'; }
                    return route.fulfill({json:{...state(),message:'OK'}});
                }
                navigations++;
                return route.fulfill({contentType:'text/html',body:shell(fixture(locale,'dashboard',now))});
            });
            await page.goto(`https://toy.test/${locale}/admin/toy-rental`);
            await page.evaluate(() => document.fonts.ready);
            await page.addStyleTag({content:"* { transition: none !important; animation: none !important; }"});
            for (const width of [320,390,768,1440]) for (const theme of ['light','dark']) {
                await page.setViewportSize({width,height:900});
                await page.evaluate(theme => document.documentElement.dataset.bsTheme=theme,theme);
                const metrics = await page.evaluate(() => ({overflow:document.documentElement.scrollWidth>innerWidth, cards:[...document.querySelectorAll('[data-toy-rental-card]')].map(c => c.getBoundingClientRect().height), circles:[...document.querySelectorAll('.toy-rental-round-button')].map(b => { const r=b.getBoundingClientRect();return [r.width,r.height,getComputedStyle(b).borderRadius]; }), untranslated:/toy_rental_[a-z_]+/.test(document.body.innerText)}));
                if (metrics.overflow) { await page.screenshot({path:'/tmp/toy-rental-overflow.png',fullPage:true}); console.log(await page.evaluate(()=>({widths:[innerWidth,document.documentElement.scrollWidth,document.body.scrollWidth],wide:[...document.querySelectorAll('*')].filter(e=>e.scrollWidth>e.clientWidth+1).map(e=>[e.tagName,e.className,e.scrollWidth,e.clientWidth])}))); }
                check(!metrics.overflow, `No horizontal overflow ${locale}/${width}/${theme}`);
                check(metrics.cards.every(h=>h<280), `Compact cards ${locale}/${width}/${theme}: ${metrics.cards}`);
                check(metrics.circles.every(([w,h,r])=>w>=44 && Math.abs(w-h)<1 && r==='50%'), `Round touch targets ${locale}/${width}/${theme}`);
                check(!metrics.untranslated, `All operator text translated ${locale}/${width}/${theme}`);
            }
            check(await page.locator('article[data-car-id="1"] button').count() === 2, 'Two start buttons for available car');
            check(await page.locator('article[data-car-id="6"] button').count() === 0, 'Unavailable cars cannot start');
            check(await page.locator('[data-toy-rental-card] .toy-rental-color-dot').count() === 0, 'No car colors in operator cards');
            if (locale === 'ru') { await page.setViewportSize({width:1440,height:900}); await page.evaluate(()=>document.documentElement.dataset.bsTheme='light'); await page.screenshot({path:'/tmp/toy-rental-desktop.png',fullPage:true}); await page.setViewportSize({width:390,height:844}); await page.screenshot({path:'/tmp/toy-rental-mobile.png',fullPage:true}); }
            check(await page.locator('article[data-car-id="1"] [data-toy-rental-fixed-price]').innerText()==='250.00', 'Initial fixed preview uses minute rate and default duration');
            for (const [minutes, amount] of [['5','125.00'],['30','750.00'],['15','375.00']]) {
                await page.locator('article[data-car-id="1"] select').selectOption(minutes);
                check(await page.locator('article[data-car-id="1"] [data-toy-rental-fixed-price]').innerText()===amount, `Fixed preview updates immediately for ${minutes} minutes`);
            }
            check(starts.length===0 && navigations===1, 'Changing duration does not start or navigate');
            delayNext=true;
            await page.locator('article[data-car-id="1"] button').first().click();
            check(await page.locator('article[data-car-id="1"] button').last().isDisabled(), 'Both modes blocked while starting');
            await page.waitForFunction(()=>document.querySelector('article[data-car-id="1"] [data-toy-rental-timer]'));
            check(starts.length===1 && starts[0].get('duration_minutes')==='15', 'Single start with selected minutes');
            check(await page.locator('article[data-car-id="1"] [data-toy-rental-fixed-price]').innerText()==='375.00', 'Active fixed card displays the saved amount');
            check(!['','--:--'].includes(await page.locator('article[data-car-id="1"] [data-toy-rental-timer]').innerText()), 'Timer appears immediately after asynchronous start');
            check(navigations===1, 'Start does not navigate');
            await page.locator('article[data-car-id="1"] button').click();
            await page.waitForFunction(()=>document.querySelector('article[data-car-id="1"] select'));
            check(completes.length===1 && navigations===1, 'Fixed completion immediately restores the buttons');
            check(await page.locator('article[data-car-id="1"] [data-toy-rental-fixed-price]').innerText()==='250.00', 'Restored card previews the default duration price');
            failNext=true;
            await page.locator('article[data-car-id="2"] button').last().click();
            await page.waitForFunction(()=>document.querySelector('[data-toy-rental-notices]').textContent.includes('Error'));
            check(await page.locator('[data-toy-rental-notices] img').count()===0 && await page.locator('article[data-car-id="2"] button').first().isEnabled(), 'Errors are escaped and controls recover');
            await page.locator('article[data-car-id="2"] button').last().click();
            await page.waitForFunction(()=>document.querySelector('article[data-car-id="2"] [data-toy-rental-timer]'));
            check(starts.at(-1).get('billing_type')==='metered', 'Per-minute button chooses the right mode');
            await page.locator('article[data-car-id="2"] button').click();
            await page.locator('#toyCompleteRide').waitFor({state:'visible'});
            check((await page.locator('#toyCompleteAmount').inputValue())==='25.00', 'Payment modal uses car minute rate');
            await page.locator('#toyCompleteRide button[type="submit"]').click();
            await page.waitForFunction(()=>document.querySelector('article[data-car-id="2"] select'));
            check(!completes.at(-1).has('payment_amount') && completes.at(-1).get('payment_status')==='paid', 'Server computes unedited final amount at actual completion');
            await page.locator('article[data-car-id="2"] button').last().click();
            await page.waitForFunction(()=>document.querySelector('article[data-car-id="2"] [data-toy-rental-timer]'));
            await page.locator('article[data-car-id="2"] button').click();
            await page.locator('#toyCompleteRide').waitFor({state:'visible'});
            await page.locator('#toyCompleteAmount').fill('20');
            await page.locator('#toyCompleteStatus').selectOption('unpaid');
            await page.locator('#toyCompleteRide button[type="submit"]').click();
            await page.waitForFunction(()=>document.querySelector('article[data-car-id="2"] select'));
            check(completes.at(-1).get('payment_amount')==='20' && completes.at(-1).get('payment_status')==='unpaid', 'Manual correction and unpaid status survive completion');
            await page.locator('article[data-car-id="1"] button').first().click();
            await page.waitForFunction(()=>document.querySelector('article[data-car-id="1"] [data-toy-rental-timer]'));
            await page.evaluate(() => {const t=document.querySelector('article[data-car-id="1"] [data-toy-rental-timer]');t.dataset.endMs=String(Number(t.dataset.serverNowMs)-1000);});
            await page.waitForFunction(()=>document.querySelector('article[data-car-id="1"] [data-toy-rental-timer]').textContent==='00:00');
            check(syncs>0, 'Expiry reaches the server notification system');
            const noticeCount = await page.locator('[data-toy-rental-notices] .alert-warning').count();
            await page.waitForTimeout(1100);
            check(await page.locator('[data-toy-rental-notices] .alert-warning').count()===noticeCount, 'Expiry message is not repeated every second');
            check(errors.length===0, 'No browser runtime errors: '+errors.join('; '));
            await page.close();
        }
        const page = await browser.newPage({viewport:{width:390,height:844}});
        const now=Math.floor(Date.now()/1000); let completed=false;
        await page.route('**/*', route => {
            const req=route.request(),url=new URL(req.url());
            if(url.pathname.startsWith('/assets/')) return route.fulfill({path:path.join(root,'public',url.pathname)});
            if(url.pathname.endsWith('toy-rental.css')||url.pathname.endsWith('toy-rental.js'))return route.fulfill({path:path.join(root,'plugins/toy-car-rental/assets',path.basename(url.pathname))});
            if(req.method()==='POST')completed=true;
            if(url.pathname.endsWith('/state')||req.method()==='POST')return route.fulfill({json:{status:true,message:'OK',cards:completed?[]:[{car_id:5,ride_id:105,status:'active',html:fixture('ru','metered',now-30,5)}]}});
            return route.fulfill({contentType:'text/html',body:shell(fixture('ru','active',now))});
        });
        await page.goto('https://toy.test/admin/toy-rental/active');
        check(await page.locator('[data-toy-rental-card]').count()===1,'Active page only displays active rides');
        await page.locator('[data-toy-rental-card] button').click();
        await page.locator('#toyCompleteRide').waitFor({state:'visible'});
        await page.locator('#toyCompleteRide button[type="submit"]').click();
        await page.waitForFunction(()=>!document.querySelector('[data-toy-rental-card]'));
        check(await page.locator('[data-toy-rental-empty]').isVisible(),'Last completion reveals active empty state');
        await page.close();
        console.log(`Toy rental browser checks passed: ${checks}`);
    } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
