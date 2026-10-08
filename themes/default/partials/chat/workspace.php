<?php $chat_members = $chat_members ?? []; ?>
<script type="application/json" data-chat-workspace-data><?= json_encode([
    'members' => $chat_members,
    'candidates' => $chat_group_candidates ?? [],
    'groups' => $chat_groups ?? [],
    'group' => $chat_active_group ?? null,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>

<div class="modal fade fb-cms-modal" id="chatGroupSettingsModal" tabindex="-1" aria-labelledby="chatGroupSettingsTitle" aria-hidden="true" data-chat-group-modal>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="chatGroupSettingsTitle">Группа и участники</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>
            <div class="modal-body">
                <form data-chat-group-profile enctype="multipart/form-data">
                    <label class="form-label" for="chatGroupTitle">Название группы</label><input id="chatGroupTitle" name="title" class="form-control mb-3" minlength="2" maxlength="100" required value="<?= htmlSC($chat_active_group['title'] ?? '') ?>">
                    <label class="form-label" for="chatGroupAvatar">Аватар группы</label><input id="chatGroupAvatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="form-control mb-2">
                    <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="remove_avatar" value="1"><span class="form-check-label">Убрать аватар</span></label>
                    <button class="btn btn-primary w-100 mb-4" type="submit">Сохранить группу</button>
                </form>
                <h3 class="h6">Участники</h3><div data-chat-group-member-list></div>
                <form class="d-flex gap-2 mt-3" data-chat-group-add>
                    <select class="form-select" name="member_id" aria-label="Добавить участника" required></select><button type="submit" class="btn btn-outline-primary">Добавить</button>
                </form>
                <p class="small text-body-secondary mt-3 mb-0" data-chat-group-owner-help>Владелец может передать группу другому участнику перед выходом.</p>
                <div class="alert alert-danger d-none mt-3 mb-0" role="alert" data-chat-group-error></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-danger" data-chat-group-leave>Выйти</button><button type="button" class="btn btn-danger" data-chat-group-delete>Удалить группу</button></div>
        </div>
    </div>
</div>

<div class="modal fade fb-cms-modal" id="chatGalleryModal" tabindex="-1" aria-labelledby="chatGalleryTitle" aria-hidden="true" data-chat-gallery-modal>
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="chatGalleryTitle">Медиа и файлы</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>
            <div class="modal-body"><div class="chat-media-gallery" data-chat-gallery-list></div><button type="button" class="btn btn-outline-secondary w-100 mt-3 d-none" data-chat-gallery-more>Загрузить ещё</button></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button></div>
        </div>
    </div>
</div>

<div class="modal fade fb-cms-modal" id="chatForwardModal" tabindex="-1" aria-labelledby="chatForwardTitle" aria-hidden="true" data-chat-forward-modal>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" data-chat-forward-form>
            <div class="modal-header"><h2 class="modal-title fs-5" id="chatForwardTitle">Переслать сообщение</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>
            <div class="modal-body"><p class="text-body-secondary text-break" data-chat-forward-preview></p><label class="form-label" for="chatForwardTarget">Получатель</label><select id="chatForwardTarget" class="form-select" name="target" required aria-label="Получатель"></select><div class="alert alert-danger d-none mt-3" role="alert" data-chat-forward-error></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button><button type="submit" class="btn btn-primary">Переслать</button></div>
        </form>
    </div>
</div>
