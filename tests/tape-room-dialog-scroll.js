'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../plugins/reel-player/assets/player.js'),'utf8');
const scrollCode=source.slice(source.indexOf('    function lockDialogScroll()'),source.indexOf('    function syncDialogViewport('));
assert.ok(scrollCode.includes('function unlockDialogScroll()'),'The actual dialog scroll helpers are loaded');
let checks=0;
for(const fixture of [
    {x:35,y:720,width:1280,client:1265,padding:'16px',behavior:'smooth',expectedPadding:'31px'},
    {x:0,y:1854,width:390,client:390,padding:'0px',behavior:'',expectedPadding:'0px'},
    {x:0,y:0,width:390,client:400,padding:'12px',behavior:'smooth',expectedPadding:'12px'},
    {x:0,y:400,width:640,client:640,padding:'normal',behavior:'auto',expectedPadding:'0px'},
]){
    const classes=new Set(['existing-body-class']),styles=new Map([['--unrelated','keep']]),scrolls=[];
    const body={style:{setProperty:(name,value)=>styles.set(name,value),removeProperty:name=>styles.delete(name)},classList:{add:name=>classes.add(name),remove:name=>classes.delete(name)}};
    const root={clientWidth:fixture.client,style:{scrollBehavior:fixture.behavior}};
    const context=vm.createContext({document:{body,documentElement:root},window:{scrollX:fixture.x,scrollY:fixture.y,innerWidth:fixture.width,scrollTo(x,y){scrolls.push({x,y,behavior:root.style.scrollBehavior});}},getComputedStyle:()=>({paddingRight:fixture.padding})});
    vm.runInContext('let dialogScroll=null;\n'+scrollCode,context);
    context.unlockDialogScroll();assert.equal(scrolls.length,0,'Closing with no open dialog does not jump the page');checks++;
    context.lockDialogScroll();assert.equal(classes.has('is-dialog-open'),true);checks++;
    assert.equal(styles.get('--rp-dialog-page-offset'),`${-fixture.y}px`,'A modal opened below the deck preserves the visible page position');checks++;
    assert.equal(styles.get('--rp-dialog-page-padding'),fixture.expectedPadding,'Scrollbar compensation does not shift the player width or go negative');checks++;
    // Opening another editor within the dialog may happen after the browser
    // moved its body scroll position to zero. The original offset must survive.
    context.window.scrollX=0;context.window.scrollY=0;context.lockDialogScroll();
    assert.equal(styles.get('--rp-dialog-page-offset'),`${-fixture.y}px`,'Nested or repeated opens do not replace the saved page offset');checks++;
    context.unlockDialogScroll();assert.deepEqual(scrolls,[{x:fixture.x,y:fixture.y,behavior:'auto'}],'Closing restores both offsets without a smooth scroll animation');checks++;
    assert.equal(root.style.scrollBehavior,fixture.behavior,'The original document scrolling preference is restored');checks++;
    assert.deepEqual([...classes],['existing-body-class'],'Closing removes only the owned scroll-lock class');checks++;
    assert.deepEqual([...styles],[['--unrelated','keep']],'Closing removes only the owned scroll-lock properties');checks++;
    context.unlockDialogScroll();assert.equal(scrolls.length,1,'Duplicate close events cannot scroll the page twice');checks++;
    context.window.scrollY=2500;context.lockDialogScroll();context.unlockDialogScroll();
    assert.equal(scrolls.at(-1).y,2500,'A later editor uses the new page position');checks++;
}
console.log(`Passed ${checks} Tape Room dialog scroll checks: desktop scrollbar, iPhone-sized page, nested opens, offset restoration and harmless repeated close.`);
