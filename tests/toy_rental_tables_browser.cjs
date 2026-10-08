'use strict';
// Native CMS table/card components and confirmation modal; all writes are intercepted.
const {chromium} = require('playwright');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const fixture = (locale, mode) => execFileSync(process.env.PHP_BIN || 'php', [path.join(__dirname,'fixtures/toy_rental.php'),locale,mode,String(Math.floor(Date.now()/1000))], {encoding:'utf8'});
let checks = 0;
const check = (value, message) => { checks++; assert.ok(value, message); };
(async () => {
    const browser = await chromium.launch({headless:true, ...(process.env.CHROME_BIN ? {executablePath:process.env.CHROME_BIN} : {})});
    try {
        for (const locale of ['ru','en','de','zh-cn']) {
            const modalHtml = fixture(locale,'delete-modal');
            for (const mode of ['cars','history','cars-empty','history-empty']) {
                const page = await browser.newPage();
                const errors = [], posts = [];
                page.on('pageerror', error => errors.push(error.message));
                await page.route('https://toy-table.test/**', route => {
                    const request = route.request(), url = new URL(request.url());
                    if (url.pathname.startsWith('/assets/')) return route.fulfill({path:path.join(root,'public',url.pathname)});
                    if (request.method()==='POST') {
                        posts.push({path:url.pathname,data:new URLSearchParams(request.postData())});
                        return route.fulfill({contentType:'text/html',body:'<p>Saved</p>'});
                    }
                    return route.fulfill({contentType:'text/html',body:`<!doctype html><html lang="${locale}" data-bs-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
                        <link rel="stylesheet" href="/assets/default/css/theme.min.css"><link rel="stylesheet" href="/assets/default/css/style.css"><link rel="stylesheet" href="/assets/default/css/admin-ui.css"><link rel="stylesheet" href="/assets/default/icons/cartzilla-icons.min.css"></head>
                        <body class="fb-admin-body"><div class="fb-admin"><main class="fb-admin-main"><div class="fb-content"><div class="fb-page-content" data-admin-shell>${fixture(locale,mode)}</div></div></main></div>${modalHtml}
                        <script>const baseUrl=location.origin;</script><script src="/assets/default/js/jquery-3.7.1.min.js"></script><script src="/assets/default/bootstrap/js/bootstrap.bundle.min.js"></script><script src="/assets/default/js/main.js"></script><script src="/assets/default/js/admin-delete-modal.js"></script></body></html>`});
                });
                await page.goto(`https://toy-table.test/${locale}/admin/toy-rental/${mode}`);
                await page.evaluate(()=>document.fonts.ready);
                await page.addStyleTag({content:'* { transition: none !important; animation: none !important; }'});
                const widths = mode.endsWith('empty') ? [320] : [320,390,768,1440];
                for (const width of widths) for (const theme of ['light','dark']) {
                    await page.setViewportSize({width,height:844});
                    await page.evaluate(theme=>document.documentElement.dataset.bsTheme=theme,theme);
                    const mobile = width < 768;
                    const cards = page.locator('[data-admin-mobile-table-cards]');
                    const table = page.locator('.admin-table-component__scroll');
                    check(await cards.isVisible()===mobile && await table.isVisible()!==mobile, `CMS responsive breakpoint ${locale}/${mode}/${width}/${theme}`);
                    check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth), `No page overflow ${locale}/${mode}/${width}/${theme}`);
                    check(!/toy_rental_[a-z_]+|admin_[a-z_]+/.test(await page.locator('body').innerText()), `All table text is translated ${locale}/${mode}/${width}/${theme}`);
                    if (mode.endsWith('empty')) {
                        check(await cards.locator('article').count()===0 && (await cards.innerText()).trim().length>0, 'Native empty state');
                        continue;
                    }
                    const region = mobile ? cards : table;
                    check(await cards.locator('article').count()===(mode==='cars' ? 6 : 1), 'One mobile card per table row');
                    if (mode==='cars') {
                        const deletes = region.locator('form[action$="/cars/delete"] button');
                        check(await deletes.count()===6 && await deletes.nth(4).isDisabled() && await deletes.nth(2).isEnabled(), 'Active deletion is disabled; hidden car deletion remains available');
                        await region.locator('[data-bs-toggle="dropdown"]').first().click();
                        await page.locator('.dropdown-menu.show form[action$="/cars/delete"] button').click();
                        await page.locator('#adminDeleteModal').waitFor({state:'visible'});
                        check(posts.length===0, 'Deletion requires CMS confirmation');
                        check((await page.locator('[data-admin-delete-modal-item]').innerText()).includes('Очень длинное название'), 'Confirmation identifies the selected car');
                        check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth), 'Confirmation fits on mobile');
                        await page.locator('#adminDeleteModal [data-bs-dismiss="modal"]').click();
                        await page.locator('#adminDeleteModal').waitFor({state:'hidden'});
                        check(posts.length===0, 'Cancelling confirmation sends no deletion');
                    } else {
                        if (mobile) {
                            check(await cards.locator('.list-group-item').count()===11, 'Every history field is present in the mobile card');
                            await region.locator('[data-bs-toggle="dropdown"]').click();
                            const methods = await page.locator('.dropdown-menu.show form[action$="/rides/pay"] [name="payment_method"]').evaluateAll(inputs=>inputs.map(input=>input.value));
                            check(JSON.stringify(methods)===JSON.stringify(['cash','transfer']), 'Mobile debt actions retain cash and transfer');
                            await region.locator('[data-bs-toggle="dropdown"]').click();
                        }
                        check(await page.locator('select[name="car_id"] option').last().count()===1 && (await page.locator('select[name="car_id"]').innerText()).includes('Mercedes'), 'History filter includes deleted cars');
                    }
                    if(locale==='ru' && width===390 && theme==='dark') await page.screenshot({path:`/tmp/toy-rental-${mode}-mobile.png`,fullPage:true});
                }
                if(mode==='cars') {
                    await page.setViewportSize({width:390,height:844});
                    await page.locator('[data-admin-mobile-table-cards] [data-bs-toggle="dropdown"]').first().click();
                    await page.locator('.dropdown-menu.show form[action$="/cars/delete"] button').click();
                    await page.locator('#adminDeleteModal').waitFor({state:'visible'});
                    await page.locator('[data-admin-delete-modal-confirm]').click();
                    await page.waitForURL('**/cars/delete');
                    check(posts.length===1 && posts[0].data.get('id')==='1' && posts[0].data.get('needCSRFToken')==='toy-fixture', 'Confirmed deletion posts the selected ID and CSRF exactly once');
                } else if(mode==='history') {
                    await page.setViewportSize({width:390,height:844});
                    await page.locator('[data-admin-mobile-table-cards] [data-bs-toggle="dropdown"]').click();
                    await page.locator('.dropdown-menu.show form').filter({has:page.locator('[name="payment_method"][value="transfer"]')}).locator('button').click();
                    await page.waitForURL('**/rides/pay');
                    check(posts.length===1 && posts[0].data.get('payment_method')==='transfer' && posts[0].data.get('id')==='101' && posts[0].data.get('needCSRFToken')==='toy-fixture' && posts[0].data.get('return_to').endsWith('/admin/toy-rental/rides'), 'Mobile debt action submits transfer, ride ID, CSRF and return path');
                }
                check(errors.length===0, 'No browser errors: '+errors.join('; '));
                await page.close();
            }
        }
        console.log(`Toy rental table checks passed: ${checks}`);
    } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
