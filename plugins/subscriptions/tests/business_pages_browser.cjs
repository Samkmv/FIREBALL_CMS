// Isolated templates and local styles; no production database, uploads or live camera.
const { chromium } = require('playwright');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const assert = require('node:assert/strict');
(async () => {
    const browser = await chromium.launch({headless:true, ...(process.env.SUBSCRIPTIONS_BROWSER_EXECUTABLE ? {executablePath:process.env.SUBSCRIPTIONS_BROWSER_EXECUTABLE} : {})});
    let checks=0;
    try {
        const page=await browser.newPage();
        const errors=[]; page.on('pageerror',error=>errors.push(error.message));
        await page.route('https://example.com/**',route=>route.abort());
        for (const width of [360,768,1280]) {
            await page.setViewportSize({width,height:900});
            for (const theme of ['light','dark']) {
                for (const mode of ['public','guest','manage','locked','admin','owner-overview','owner-posts','owner-camera','owner-statistics','owner-gallery']) {
                    const html=execFileSync('php',[path.join(__dirname,'fixtures/business-page.php'),mode,'ru',theme],{encoding:'utf8'});
                    await page.setContent(html);
                    const context=`${mode}/${theme}/${width}`;
                    assert(await page.locator('body').evaluate(body=>body.scrollWidth<=innerWidth),context+': no horizontal overflow');
                    assert.equal(await page.locator('script').count(),0,context+': stored markup stays escaped');
                    if (mode==='manage') {
                        assert(await page.getByLabel('Название бизнеса',{exact:true}).isVisible(),context+': owner fields');
                        assert.equal(await page.locator('input[type=file]').count(),2,context+': logo and cover uploads');

                    }
                    if (mode==='owner-posts') {
                        const details=page.locator('details'); await details.first().locator('summary').click();
                        assert(await details.first().getByRole('button',{name:'Сохранить',exact:true}).isVisible(),context+': publication editing');
                    }
                    if (mode==='owner-overview') {
                        assert.equal(await page.locator('.business-dashboard-middle').count(),1,context+': camera and publications');
                        assert.equal(await page.locator('.business-dashboard-bottom').count(),1,context+': statistics, offers and actions');
                        assert.equal(await page.locator('input[type=file]').count(),0,context+': overview has focused editor links');
                    }
                    if (mode==='guest') {
                        assert.equal(await page.locator('#review-rating').count(),0,context+': login required for rating');
                        assert(await page.getByRole('link',{name:'Войдите, чтобы оставить отзыв'}).isVisible(),context+': login action');
                    }
                    if (mode==='locked') { assert.equal(await page.locator('form').count(),0,context+': inactive plan has no editing forms'); }
                    if (mode==='public') { assert.equal(await page.locator('[data-fire-player]').count(),1,context+': assigned camera player'); }
                    for (const form of await page.locator('form').all()) { assert.equal(await form.locator('input[name=csrf_token]').count(),1,context+': CSRF field on every form'); }
                    const ids=await page.locator('[id]').evaluateAll(elements=>elements.map(el=>el.id));
                    assert.equal(new Set(ids).size,ids.length,context+': unique form field ids');
                    checks++;
                }
            }
        }
        assert.deepEqual(errors,[],'No JavaScript errors');
        if (process.env.SUBSCRIPTIONS_SCREENSHOT_DIR) {
            await page.setViewportSize({width:1280,height:900});
            await page.setContent(execFileSync('php',[path.join(__dirname,'fixtures/business-page.php'),'public','ru','light'],{encoding:'utf8'}));
            await page.screenshot({path:path.join(process.env.SUBSCRIPTIONS_SCREENSHOT_DIR,'business-public.png'),fullPage:true});
        }
        console.log(`Business browser tests passed: ${checks} layout scenarios.`);
    } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
