<main class="content-wrapper">
    <?php
    $contactsByGroup = ['admins' => [], 'clients' => []];
    foreach ($contacts as $contact) {
        $group = ($contact['chat_group'] ?? 'clients') === 'admins' ? 'admins' : 'clients';
        $contactsByGroup[$group][] = $contact;
    }
    $contactGroupTitles = [
        'admins' => return_translation('chat_contacts_admins'),
        'clients' => return_translation('chat_contacts_clients'),
    ];
    ?>
    <section class="container py-5">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h1 class="h3 mb-1"><?= print_translation('chat_index_heading') ?></h1>
                <p class="text-body-secondary mb-0"><?= print_translation('chat_index_subtitle') ?></p>
            </div>
        </div>

        <?php if (empty($contacts)): ?>
            <div class="alert alert-info mb-0"><?= print_translation('chat_no_contacts') ?></div>
        <?php else: ?>
            <div
                class="row g-4 align-items-stretch"
                data-chat-app
                data-fetch-url="<?= htmlSC($chat_fetch_url) ?>"
                data-send-url="<?= htmlSC($chat_send_url) ?>"
                data-current-user-avatar="<?= htmlSC(get_user_avatar(get_user()['avatar'] ?? null, 'sm')) ?>"
                data-empty-text="<?= htmlSC(return_translation('chat_empty_dialog')) ?>"
                data-new-message-text="<?= htmlSC(return_translation('chat_new_message')) ?>"
                data-attachment-label="<?= htmlSC(return_translation('chat_attachment_label')) ?>"
                data-file-too-large-text="<?= htmlSC(return_translation('chat_file_size_error')) ?>"
                data-download-image-text="<?= htmlSC(return_translation('chat_download_image')) ?>"
                data-image-modal-title="<?= htmlSC(return_translation('chat_image_modal_title')) ?>"
                data-online-text="<?= htmlSC(return_translation('chat_status_online')) ?>"
                data-offline-text="<?= htmlSC(return_translation('chat_status_offline')) ?>"
            >
                <div class="col-lg-4">
                    <div class="card border shadow-sm rounded-5 overflow-hidden h-100">
                        <div class="card-header border-bottom bg-body px-4 py-3">
                            <strong class="d-block"><?= print_translation('chat_contacts_title') ?></strong>
                        </div>
                        <div class="card-body p-0 bg-body-tertiary">
                            <div style="max-height: 640px; overflow-y: auto;">
                                <?php foreach ($contactsByGroup as $groupKey => $groupContacts): ?>
                                    <?php if (empty($groupContacts)): ?>
                                        <?php continue; ?>
                                    <?php endif; ?>
                                    <div class="border-bottom" data-chat-contact-group="<?= htmlSC($groupKey) ?>">
                                        <div class="px-4 py-2 text-uppercase small fw-semibold text-body-secondary bg-body" style="letter-spacing: .08em;">
                                            <?= htmlSC($contactGroupTitles[$groupKey] ?? $groupKey) ?>
                                        </div>
                                        <div class="list-group list-group-flush" data-chat-contact-group-list="<?= htmlSC($groupKey) ?>">
                                            <?php foreach ($groupContacts as $contact): ?>
                                                <?php $isActive = (int)$active_contact['id'] === (int)$contact['id']; ?>
                                                <button
                                                    type="button"
                                                    class="list-group-item list-group-item-action px-4 py-3 <?= $isActive ? 'active' : '' ?>"
                                                    data-chat-contact
                                                    data-contact-group="<?= htmlSC((string)($contact['chat_group'] ?? 'clients')) ?>"
                                                    data-user-id="<?= (int)$contact['id'] ?>"
                                                    data-user-name="<?= htmlSC($contact['name']) ?>"
                                                    data-user-avatar="<?= htmlSC(get_user_avatar($contact['avatar'] ?? null, 'sm')) ?>"
                                                    data-user-online="<?= (int)($contact['is_online'] ?? 0) ?>"
                                                    data-user-last-seen="<?= htmlSC((string)($contact['last_seen_at'] ?? '')) ?>"
                                                    data-last-message-preview="<?= htmlSC((string)($contact['last_message_preview'] ?? '')) ?>"
                                                >
                                                    <span class="d-flex align-items-center gap-3 min-w-0">
                                                        <span class="flex-shrink-0">
                                                            <img
                                                                src="<?= get_user_avatar($contact['avatar'] ?? null, 'sm') ?>"
                                                                alt="<?= htmlSC($contact['name']) ?>"
                                                                class="rounded-circle border object-fit-cover"
                                                                style="width: 48px; height: 48px;"
                                                            >
                                                        </span>
                                                        <span class="min-w-0 flex-grow-1">
                                                            <span class="d-flex align-items-start justify-content-between gap-2">
                                                                <span class="d-block text-truncate fw-semibold"><?= htmlSC($contact['name']) ?></span>
                                                                <span
                                                                    class="badge text-bg-danger rounded-pill flex-shrink-0 chat-contact-unread-badge <?= (int)($contact['unread_count'] ?? 0) > 0 ? '' : 'd-none' ?>"
                                                                    data-chat-contact-unread="<?= (int)$contact['id'] ?>"
                                                                ><?= (int)($contact['unread_count'] ?? 0) ?></span>
                                                            </span>
                                                            <span
                                                                class="small d-inline-flex align-items-center gap-1 mt-1 <?= !empty($contact['is_online']) ? 'text-success' : 'text-body-secondary' ?>"
                                                                data-chat-contact-status="<?= (int)$contact['id'] ?>"
                                                            >
                                                                <span class="rounded-circle d-inline-block flex-shrink-0 <?= !empty($contact['is_online']) ? 'bg-success' : 'bg-secondary' ?>" style="width: 8px; height: 8px;"></span>
                                                                <span><?= !empty($contact['is_online']) ? print_translation('chat_status_online') : print_translation('chat_status_offline') ?></span>
                                                            </span>
                                                            <span class="small text-body-secondary d-block text-truncate mt-1" data-chat-contact-preview="<?= (int)$contact['id'] ?>">
                                                                <?= htmlSC((string)($contact['last_message_preview'] ?? return_translation('chat_empty_dialog'))) ?>
                                                            </span>
                                                        </span>
                                                    </span>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card border shadow-sm rounded-5 overflow-hidden h-100">
                        <div class="card-header border-bottom bg-body px-4 py-3 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <img
                                    src="<?= get_user_avatar($active_contact['avatar'] ?? null, 'sm') ?>"
                                    alt="<?= htmlSC($active_contact['name']) ?>"
                                    class="rounded-circle border object-fit-cover flex-shrink-0"
                                    style="width: 44px; height: 44px;"
                                    data-chat-current-avatar
                                >
                                <div class="min-w-0">
                                    <strong class="d-block text-truncate" data-chat-current-name><?= htmlSC($active_contact['name']) ?></strong>
                                    <span class="small d-inline-flex align-items-center gap-1 <?= !empty($active_contact['is_online']) ? 'text-success' : 'text-body-secondary' ?>" data-chat-current-status>
                                        <span class="rounded-circle d-inline-block flex-shrink-0 <?= !empty($active_contact['is_online']) ? 'bg-success' : 'bg-secondary' ?>" style="width: 8px; height: 8px;"></span>
                                        <span><?= !empty($active_contact['is_online']) ? print_translation('chat_status_online') : print_translation('chat_status_offline') ?></span>
                                    </span>
                                </div>
                            </div>
                            <span class="badge text-bg-secondary"><?= print_translation('chat_encryption_badge') ?></span>
                        </div>

                        <div class="card-body bg-body p-3 p-md-4">
                            <div class="chat-messages-surface rounded-4 border p-3 p-md-4" style="height: 420px; overflow-y: auto;" data-chat-messages>
                                <p class="text-body-secondary mb-0"><?= print_translation('chat_loading') ?></p>
                            </div>
                        </div>

                        <div class="card-footer border-0 bg-body px-3 px-md-4 pb-4 pt-0">
                            <form data-chat-form enctype="multipart/form-data">
                                <?= get_csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int)$active_contact['id'] ?>" data-chat-user-id>
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" name="message" maxlength="2000" placeholder="<?= print_translation('chat_message_placeholder') ?>">
                                    <label class="btn btn-outline-secondary mb-0" for="chat_attachment">
                                        <i class="ci-paperclip"></i>
                                    </label>
                                    <input class="d-none" id="chat_attachment" type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.zip,.rar" data-chat-attachment>
                                    <button class="btn btn-dark" type="submit"><?= print_translation('chat_send_btn') ?></button>
                                </div>
                                <div class="small text-body-secondary d-none" data-chat-attachment-name></div>
                                <div class="small text-body-secondary"><?= print_translation('chat_file_hint') ?></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="chatImageModal" tabindex="-1" role="dialog" aria-hidden="true" data-chat-image-modal>
                <div class="modal-dialog modal-fullscreen" role="document">
                    <div class="modal-content border-0 rounded-5 overflow-hidden">
                        <div class="modal-header border-0 pb-0">
                            <h2 class="modal-title fs-5" data-chat-image-modal-title><?= print_translation('chat_image_modal_title') ?></h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body pt-3">
                            <div class="rounded-4 bg-body-tertiary d-flex align-items-center justify-content-center p-2 p-md-3" style="min-height: 320px;">
                                <img
                                    src=""
                                    alt=""
                                    class="img-fluid rounded-4"
                                    style="max-height: 75vh; width: auto;"
                                    data-chat-image-modal-image
                                >
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <a class="btn btn-outline-secondary rounded-pill" href="" download data-chat-image-modal-download>
                                <i class="ci-download me-2"></i>
                                <?= print_translation('chat_download_image') ?>
                            </a>
                            <button type="button" class="btn btn-dark rounded-pill" data-bs-dismiss="modal"><?= print_translation('admin_btn_cancel') ?></button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>
