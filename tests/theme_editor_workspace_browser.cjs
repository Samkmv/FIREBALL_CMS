'use strict';
const {chromium} = require(process.env.FIREBALL_PLAYWRIGHT_MODULE || 'playwright');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.join(__dirname, '..');
const fixture = execFileSync(process.env.FIREBALL_PHP || 'php', [path.join(__dirname, 'fixtures/theme_editor_workspace.php')], {encoding: 'utf8'});
(async () => {
 const browser = await chromium.launch({headless:true});
 try {
  const page = await browser.newPage();
  const errors = [];
  let submitted = null;
  page.on('pageerror', e => errors.push(e.message));
  await page.route('https://theme-editor.test/**', route => {
   const url = new URL(route.request().url());
   if (url.pathname === '/admin/theme-editor/save') submitted = new URLSearchParams(route.request().postData());
   if (url.pathname.startsWith('/assets/')) return route.fulfill({path:path.join(root,'public',url.pathname)});
   if (url.searchParams.has('preview_theme')) return route.fulfill({contentType:'text/html',body:'<h1>Theme preview</h1><p>Saved changes</p>'});
   return route.fulfill({contentType:'text/html; charset=utf-8',body:fixture});
  });
  for (const width of [1920,1440,1280,1024,768,390,320]) {
   await page.setViewportSize({width,height:900});
   await page.goto('https://theme-editor.test/editor');
   await page.waitForSelector('[data-theme-editor-code][hidden]', {state:'attached'});
   assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'No horizontal page overflow at '+width);
   assert.ok(await page.locator('[data-theme-editor-monaco]').evaluate(el => el.clientHeight > 250), 'Monaco has usable height');
   const frame = page.locator('[data-theme-preview-frame]');
   await page.locator('[data-theme-preview-device="mobile"]').click();
   assert.equal(await frame.evaluate(el => el.clientWidth),375);
   await page.locator('[data-theme-preview-device="tablet"]').click();
   assert.equal(await frame.evaluate(el => el.clientWidth),768);
   await page.locator('[data-theme-preview-device="desktop"]').click();
   assert.equal(await frame.evaluate(el => el.clientWidth),1280);
   await page.locator('[data-theme-preview-toggle]').click();
   assert.equal(await page.locator('[data-theme-preview-pane]').isVisible(),false);
   await page.locator('[data-theme-preview-toggle]').click();
   assert.equal(await page.locator('[data-theme-preview-pane]').isVisible(),true);
   await page.locator('.theme-editor-monaco .view-lines').click();
   await page.keyboard.press('End'); await page.keyboard.type(' edited');
   assert.equal(await page.locator('[data-theme-editor-dirty]').isVisible(),true);
   assert.ok((await page.locator('[data-theme-editor-code]').inputValue()).includes('edited'));
   await page.locator('[data-theme-editor-reset]').click();
   assert.equal(await page.locator('[data-theme-editor-dirty]').isVisible(),false);
   await page.locator('.theme-editor-toolbar .dropdown-toggle').click();
   await page.locator('[data-bs-target="#themeCreateFileModal"]').click();
   await page.locator('#themeCreateFileModal').waitFor({state:'visible'});
   await page.locator('#themeCreateFileModal [data-bs-dismiss="modal"]').click();
   await page.locator('#themeCreateFileModal').waitFor({state:'hidden'});
   if (width === 1920) {
    await page.locator('.theme-editor-monaco .view-lines').click();
    await page.keyboard.press('End'); await page.keyboard.type(' saved-by-test');
    await page.locator('[form="themeEditorSaveForm"]').click();
    await page.waitForSelector('[data-theme-editor-code][hidden]', {state:'attached'});
    assert.equal(submitted.get('csrf'), 'fixture');
    assert.equal(submitted.get('path'), 'templates/layout.php');
    assert.ok(submitted.get('content').includes('saved-by-test'));
   }
   if (process.env.FIREBALL_SCREENSHOT_DIR && [1440,390].includes(width)) {
    await page.locator('.theme-editor-monaco .view-lines').click();
    await page.keyboard.press('Control+Home');
    await page.locator('.theme-editor-heading h1').click();
    await page.evaluate(() => scrollTo(0,0));
    await page.screenshot({path:path.join(process.env.FIREBALL_SCREENSHOT_DIR,'theme-editor-'+width+'.png'),fullPage:true});
   }
  }
  assert.deepEqual(errors,[]);
  console.log('Theme editor browser: 7 viewport widths, real Monaco, preview sizing/toggle, dirty/reset and file modal passed.');
 } finally {await browser.close();}
})().catch(e => {console.error(e);process.exit(1);});
