'use strict';
const {chromium} = require(process.env.FIREBALL_PLAYWRIGHT_MODULE || 'playwright');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
const fixture = mode => execFileSync(process.env.FIREBALL_PHP || 'php', [path.join(__dirname,'subscription_form_fixture.php'),mode],{encoding:'utf8'});
(async()=>{
 const browser=await chromium.launch({headless:true});
 try {
  const page=await browser.newPage({timezoneId:'Europe/Moscow',locale:'ru-RU'});
  const errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('https://vpn.test/**',route=>{
   const url=new URL(route.request().url());
   if(url.pathname.startsWith('/assets/'))return route.fulfill({path:path.join(root,'public',url.pathname)});
   assert.equal(route.request().method(),'GET','Fixture never submits a live request');
   return route.fulfill({contentType:'text/html',body:fixture(url.searchParams.get('fixture')||'create')});
  });
  const payload=()=>page.locator('form[action*="subscriptions/"]').evaluate(form=>Object.fromEntries(new FormData(form)));
  for(const width of [320,390,1440]) for(const theme of ['dark','light']) for(const variant of ['create','edit','lifetime','inactive-plan']) {
   await page.setViewportSize({width,height:950});
   await page.goto(`https://vpn.test/admin/plugins/vpn-manager-v2/subscriptions?fixture=${variant}`);
   await page.evaluate(theme=>document.documentElement.dataset.bsTheme=theme,theme);
   const plan=page.locator('#vpnV2SubscriptionPlan');
   const mode=page.locator('#vpnV2ExpiryMode');
   const date=page.locator('#vpnV2SubscriptionExpiresAt');
   if(variant==='create') {
    assert.equal(await mode.inputValue(),'plan');
    await plan.selectOption('1');
    assert.equal((await payload()).plan_id,'1');
    assert.equal((await payload()).expires_at,undefined);
    await mode.selectOption('manual');
    assert(await date.isVisible() && await date.isEnabled());
    await date.fill('2027-01-05T12:30');
    if(width===390 && theme==='dark')await page.screenshot({path:'/private/tmp/vpn-subscription-create-manual.png',fullPage:true});
    await plan.selectOption('2');
    assert.equal(await date.inputValue(),'2027-01-05T12:30','Changing tariffs preserves an explicit manual date');
    assert.equal((await payload()).expires_at,'2027-01-05T12:30');
    await mode.selectOption('lifetime');
    assert.equal((await payload()).expires_at,undefined);
    assert.equal((await payload()).expiry_mode,'lifetime');
    await mode.selectOption('plan');
    await page.locator('#vpnV2SubscriptionStartsAt').fill('2026-10-03T09:00');
    const preview=await page.locator('#vpnV2CalculatedExpiresAt').inputValue();
    assert(preview.includes('01.01.2027'),`90-day term preview: ${preview}`);
   } else {
    assert.equal(await mode.inputValue(),'preserve');
    assert.equal(await plan.inputValue(),'1');
    const original=await page.locator('#vpnV2CalculatedExpiresAt').inputValue();
    if(variant==='lifetime')assert.equal(original,'Бессрочно');
    await plan.selectOption('2');
    assert.equal(await mode.inputValue(),'plan');
    assert.equal(await page.locator('#vpnV2SubscriptionTrafficLimit').inputValue(),'0');
    assert(await page.locator('#vpnV2SubscriptionTrafficLimit').evaluate(el=>el.readOnly));
    if(width===390 && theme==='dark' && variant==='edit')await page.screenshot({path:'/private/tmp/vpn-subscription-edit-plan.png',fullPage:true});
    await mode.selectOption('preserve');
    assert.equal(await page.locator('#vpnV2CalculatedExpiresAt').inputValue(),original);
    await mode.selectOption('manual');
    await date.fill('2027-04-01T10:00');
    assert.equal((await payload()).expires_at,'2027-04-01T10:00');
    await plan.selectOption('1');
    assert.equal(await date.inputValue(),'2027-04-01T10:00');
    assert(!await page.locator('#vpnV2SubscriptionTrafficLimit').evaluate(el=>el.readOnly));
    await mode.selectOption('lifetime');
    assert.equal((await payload()).expires_at,undefined);
   }
   const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1);
   if(overflow){
    console.log(await page.evaluate(()=>Array.from(document.querySelectorAll('body *')).filter(el=>el.getBoundingClientRect().right>innerWidth+1).map(el=>({tag:el.tagName,id:el.id,classes:el.className,right:el.getBoundingClientRect().right})).slice(0,12)));
    await page.screenshot({path:'/private/tmp/vpn-subscription-overflow.png',fullPage:true});
   }
   assert.equal(overflow,false,`${variant}/${width}/${theme}: no mobile horizontal scroll`);
   if(width===390 && theme==='dark')await page.screenshot({path:`/private/tmp/vpn-subscription-${variant}.png`,fullPage:true});
  }
  assert.deepEqual(errors,[]);
  console.log('PASS subscription forms: 24 mobile/desktop/theme cases, tariff selection, plan/manual/lifetime terms and preservation, no real submissions.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1});
