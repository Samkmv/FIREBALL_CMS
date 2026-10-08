<?php
// Renders the real chat modal markup with disposable data; no CMS boot or DB.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$theme = ($argv[1] ?? 'dark') === 'light' ? 'light' : 'dark';
$assets = ($argv[2] ?? '') === 'theme' ? '/themes/default/assets' : '/assets/default';
$source = ($argv[3] ?? '') === 'fallback' ? 'app/Views/themes/default/chat/index.php' : 'themes/default/templates/chat/index.php';
function htmlSC($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function return_translation($key) {
    return [
        'chat_image_modal_title'=>'Просмотр вложения', 'chat_preview_loading'=>'Загрузка…', 'chat_open_file'=>'Открыть файл',
        'chat_download_file'=>'Скачать файл', 'admin_btn_close'=>'Закрыть', 'chat_audit_title'=>'Журнал сообщений',
        'chat_loading'=>'Загрузка…', 'chat_audit_clear_btn'=>'Очистить журнал', 'admin_delete_modal_title'=>'Подтвердите удаление',
        'chat_confirm_delete_message'=>'Удалить сообщение?', 'chat_confirm_reason_label'=>'Причина удаления',
        'chat_confirm_reason_placeholder'=>'Укажите причину', 'admin_btn_cancel'=>'Отмена', 'chat_action_delete'=>'Удалить',
        'chat_group_create'=>'Создать группу', 'chat_group_create_hint'=>'Название и участники группы',
        'chat_group_name'=>'Название группы', 'chat_group_members'=>'Участники', 'chat_group_min_members'=>'Выберите участников.',
        'chat_group_create_submit'=>'Создать группу',
    ][$key] ?? $key;
}
function print_translation($key) { echo return_translation($key); }
function get_csrf_field() { return '<input type="hidden" name="csrf" value="fixture-only">'; }
function get_user_avatar($avatar = null, $size = '') { return 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="34" height="34"><circle cx="17" cy="17" r="17" fill="#687083"/></svg>'); }
function get_user_role_label($role) { return 'Участник'; }
$chatPermissions = ['can_delete_audit'=>true];
$chat_group_create_url = '/fixture-no-submit';
$chat_group_candidates = array_map(fn($id)=>['id'=>$id,'name'=>'Участник '.$id,'role'=>'user'], range(1,24));
$template = file_get_contents(dirname(__DIR__,2) . '/' . $source);
$begin = strpos($template, '<div class="modal fade fb-cms-modal" id="chatAttachmentModal"');
$end = strrpos($template, '<?php endif; ?>');
if ($begin === false || $end === false) throw new RuntimeException('Chat modal markup missing');
?>
<!doctype html><html lang="ru" data-bs-theme="<?= $theme ?>" class="pwa-standalone">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>CMS chat modal fixture</title>
<link rel="stylesheet" href="<?= $assets ?>/css/theme.min.css"><link rel="stylesheet" href="<?= $assets ?>/icons/cartzilla-icons.min.css"><link rel="stylesheet" href="<?= $assets ?>/css/style.css">
<style>:root{--pwa-safe-top:47px;--pwa-safe-bottom:34px}#fixtureControls{padding:52px 16px 16px}#results{display:block;margin:16px 0;font:13px monospace}.modal-footer .btn{margin:0}body{background:var(--cz-body-bg)}</style>
<script>
const nativeMedia=window.matchMedia.bind(window);
window.matchMedia=query=>/hover: none|pointer: coarse/.test(query)?{matches:true,addEventListener(){},removeEventListener(){}}:nativeMedia(query);
const vv=new EventTarget();Object.assign(vv,{height:innerHeight,offsetTop:0,scale:1});Object.defineProperty(window,'visualViewport',{value:vv});Object.defineProperty(navigator,'standalone',{value:true});
</script></head><body>
<div id="fixtureControls"><strong>Проверка окон CMS • тестовые данные</strong><output id="results">Проверка…</output><button class="btn btn-outline-primary" id="showAudit">Открыть тестовый журнал</button></div>
<?php eval('?>' . substr($template,$begin,$end-$begin)); ?>
<?php $chat_active_group = ['title' => 'Тестовая группа']; require dirname(__DIR__, 2) . '/themes/default/partials/chat/workspace.php'; ?>
<script src="<?= $assets ?>/bootstrap/js/bootstrap.bundle.min.js"></script><script src="<?= $assets ?>/js/app-viewport.js"></script><script src="<?= $assets ?>/js/chat-viewport.js"></script>
<script>
const result=document.querySelector('#results');
let checks=0;
const check=(ok,text)=>{checks++;if(!ok)throw new Error(text);};
const frames=()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
const modalIds=['chatAuditModal','chatAttachmentModal','chatConfirmModal','chatCreateGroupModal','chatGroupSettingsModal','chatGalleryModal','chatForwardModal'];
const instances=Object.fromEntries(modalIds.map(id=>[id,bootstrap.Modal.getOrCreateInstance(document.getElementById(id))]));
const opened=id=>new Promise(resolve=>{const e=document.getElementById(id);e.addEventListener('shown.bs.modal',resolve,{once:true});instances[id].show();});
const closed=id=>new Promise(resolve=>{const e=document.getElementById(id);e.addEventListener('hidden.bs.modal',resolve,{once:true});instances[id].hide();});
const audit=document.querySelector('[data-chat-audit-list]');
audit.innerHTML='<div class="d-flex flex-column gap-3">'+Array.from({length:15},(_,i)=>`<div class="chat-audit-entry border rounded-4 p-3"><div class="d-flex align-items-start justify-content-between gap-3 mb-2"><div class="d-flex align-items-start gap-2 min-w-0"><span class="chat-audit-entry__icon"><i class="ci-file-text"></i></span><div><strong>Удаление сообщения</strong><div class="small text-body-secondary">Тестовый участник • 2026-10-08 19:${String(i).padStart(2,'0')}</div></div></div><span class="badge bg-body-tertiary text-body-emphasis rounded-pill">admin</span></div><div class="small">Удалено сообщений: 1<br>IP: 203.0.113.1<br>Устройство: Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1</div></div>`).join('')+'</div>';
document.querySelector('[data-chat-preview-modal-body]').innerHTML='<pre class="chat-preview-modal__text">'+('Тестовый документ — длинное содержимое\n'.repeat(80))+'</pre>';
document.querySelector('[data-chat-confirm-message]').textContent='Подтверждение с длинным описанием. '.repeat(45);
document.querySelector('[data-chat-confirm-reason-wrap]').classList.remove('d-none');
document.querySelector('[data-chat-group-member-list]').innerHTML=Array.from({length:24},(_,i)=>`<div class="chat-group-settings-member"><div class="flex-grow-1"><strong>Тестовый участник ${i+1}</strong><div class="small text-body-secondary">Участник</div></div><select class="form-select form-select-sm"><option>Участник</option></select><button class="btn btn-sm btn-outline-danger" type="button" aria-label="Удалить участника"><i class="ci-trash"></i></button></div>`).join('');
document.querySelector('[data-chat-gallery-list]').innerHTML=Array.from({length:24},(_,i)=>`<div class="border rounded-4 p-3 text-break">Тестовый файл ${i+1}<p class="small text-body-secondary mb-0">Изображение или документ</p></div>`).join('');
document.querySelector('[data-chat-forward-preview]').textContent='Тестовое длинное сообщение для пересылки. '.repeat(100);
document.querySelector('[data-chat-forward-form] select').innerHTML='<option>Тестовая группа</option>';
document.addEventListener('submit',e=>e.preventDefault());
const geometry=el=>el.getBoundingClientRect();
function inspect(id, keyboard) {
    const modal=document.getElementById(id), content=modal.querySelector('.modal-content'), body=modal.querySelector('.modal-body');
    const frame=geometry(content), style=getComputedStyle(modal), dialogStyle=getComputedStyle(modal.querySelector('.modal-dialog'));
    // CSS max()/calc() remain serialized: verify physical bounds, not strings.
    const bottom=keyboard?vv.offsetTop+vv.height:innerHeight;
    check(frame.top>=vv.offsetTop+parseFloat(dialogStyle.marginTop)-1 && frame.bottom<=bottom-parseFloat(dialogStyle.marginBottom)+1,id+' stays inside visual viewport and safe margins');
    check(modal.scrollHeight<=modal.clientHeight+1,id+' has no outer scrolling');
    check(getComputedStyle(content).borderTopLeftRadius==='20px',id+' shares CMS corner radius');
    const scroller=body.scrollHeight>body.clientHeight?body:body.querySelector('.chat-preview-modal__text');
    check(getComputedStyle(body).overflowY==='auto' && scroller && scroller.scrollHeight>scroller.clientHeight,id+' has internal content scrolling');
    for(const button of modal.querySelectorAll('.modal-header .btn-close,.modal-footer .btn')) {
        const r=geometry(button);check(r.top>=frame.top && r.bottom<=frame.bottom+1,id+' close/actions fully visible');
    }
    const header=modal.querySelector('.modal-header'),footer=modal.querySelector('.modal-footer');
    const before=[header&&geometry(header).top,footer&&geometry(footer).bottom];
    const previousScroll=scroller.scrollTop;
    scroller.scrollTop=100;
    const after=[header&&geometry(header).top,footer&&geometry(footer).bottom];
    check(JSON.stringify(before)===JSON.stringify(after),id+' header and footer stay still while body scrolls');
    check(scroller.scrollTop>0,id+' content can scroll');
    scroller.scrollTop=previousScroll;
    check(modal.getAttribute('aria-labelledby') && document.getElementById(modal.getAttribute('aria-labelledby')),id+' has a labelled dialog');
}
(async()=>{
    try {
        window.FireballChatViewport.sync(false);
        for(const id of modalIds) {
            await opened(id);await frames();inspect(id,false);
            const editable=document.getElementById(id).querySelector('textarea,input:not([type=hidden]):not([type=checkbox])');
            if(editable)editable.focus();
            Object.assign(vv,{height:430,offsetTop:100});window.FireballChatViewport.sync(true);await frames();inspect(id,true);
            if(editable) {
                const field=geometry(editable), bounds=geometry(editable.closest('.modal-body'));
                check(field.top>=bounds.top-1 && field.bottom<=bounds.bottom+1,id+' focused field is visible above keyboard');
            }
            if(editable)editable.blur();
            await closed(id);
            Object.assign(vv,{height:innerHeight,offsetTop:0});window.FireballChatViewport.sync(false);
        }
        result.textContent=`PASS ${checks} • ${innerWidth}px • <?= $theme ?> • <?= htmlSC($assets) ?>`;
        document.title=result.textContent;
        audit.scrollTop=0;
        await opened('chatAuditModal');
    } catch(e) {result.textContent='FAIL: '+e.message;document.title=result.textContent;console.error(e);}
})();
document.querySelector('#showAudit').addEventListener('click',()=>instances.chatAuditModal.show());
</script></body></html>
