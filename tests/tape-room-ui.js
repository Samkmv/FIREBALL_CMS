'use strict';
const fs = require('node:fs'), vm = require('node:vm'), assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../plugins/reel-player/assets/player.js'), 'utf8');
const restore = source.slice(source.indexOf('    let data = config.state'), source.indexOf('    let selected ='));
for (const saved of ['null', '[]', 'true', '123', '"oops"', '{bad', '{"volume":0.4,"audibleVolume":0.6}']) {
    const context = vm.createContext({config:{state:{},storageKey:'fixture'},localStorage:{getItem:()=>saved}});
    vm.runInContext(restore,context);
    assert.equal(vm.runInContext('audibleVolume',context),saved.includes('audibleVolume') ? .6 : .8,'Invalid saved state must not prevent player startup');
}
const driveDialogs = source.slice(source.indexOf('    async function driveDialogStatus('),source.indexOf('    async function driveFiles('));
const viewportSync = source.slice(source.indexOf('    function syncDialogViewport('),source.indexOf("    window.visualViewport?.addEventListener('resize'"));
for (const [layoutHeight,visibleHeight,top] of [[852,852,0],[852,352,120],[740,290,180],[740,240,180]]) {
    const values=new Map(),compact=[];
    const context=vm.createContext({window:{innerHeight:layoutHeight,visualViewport:{height:visibleHeight,offsetTop:top}},dialog:{style:{setProperty:(name,value)=>values.set(name,value)},classList:{toggle:(name,value)=>compact.push([name,value])}}});
    vm.runInContext(viewportSync,context);context.syncDialogViewport();
    assert.equal(values.get('--rp-dialog-viewport-height'),`${visibleHeight}px`);
    assert.equal(values.get('--rp-dialog-viewport-top'),`${top}px`);
    assert.deepEqual(compact,[['is-compact',visibleHeight<520],['is-short',visibleHeight<340]],'Keyboard-sized viewport keeps essential controls reachable');
}
function setup() {
    const requests = [], opened = [], control = {hidden:false};
    const context = vm.createContext({config:{drive:{}},api:()=>new Promise((resolve,reject)=>requests.push({resolve,reject})),
        $:()=>control,field:()=>'',driveSetupHelp:()=>'',toast(){},dialog:{classList:{add(){}},querySelector:()=>({addEventListener(){}})},navigateDrive:async()=>{},
        openDialog:title=>{opened.push(title);vm.runInContext('dialogRequest++',context);}});
    vm.runInContext('let dialogRequest=0, driveSelection, drivePath;',context);
    vm.runInContext(driveDialogs,context);
    return {context,requests,opened};
}
(async()=>{
    const status={drive:{canManage:true,connected:false,configured:true,callback:'/callback'}};
    const race=setup(), first=race.context.openDrive(),second=race.context.driveSettings();
    race.requests[1].resolve(status);await second;race.requests[0].resolve(status);await first;
    assert.deepEqual(race.opened,['Настройки плеера'],'Old Drive response cannot replace a newer settings dialog');
    const staleError=setup(),old=staleError.context.openDrive();
    vm.runInContext('dialogRequest++',staleError.context);
    staleError.requests[0].reject(new Error('old connection error'));await old;
    assert.equal(staleError.opened.length,0,'Dismissed or superseded dialog ignores an old request error');
    const currentError=setup(),active=currentError.context.openDrive();
    currentError.requests[0].reject(new Error('current error'));
    await assert.rejects(active,/current error/,'Active request still reports its actual error');
    console.log('Tape Room UI checks passed: invalid saved state, keyboard viewport, stale Drive replies, stale errors and current request errors.');
})().catch(error=>{console.error(error);process.exitCode=1;});
