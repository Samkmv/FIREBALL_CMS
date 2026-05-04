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
    <section class="container-fluid px-3 px-lg-4 px-xxl-5 py-4 py-lg-5">
        <div class="chat-page-header d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4 mb-xl-5">
            <div>
                <div class="small text-uppercase fw-semibold text-body-secondary mb-2" style="letter-spacing: .08em;">
                    <?= print_translation('tpl_auth_chat') ?>
                </div>
                <h1 class="h3 mb-1"><?= print_translation('chat_index_heading') ?></h1>
                <p class="text-body-secondary mb-0"><?= print_translation('chat_index_subtitle') ?></p>
            </div>
        </div>

        <?php if (empty($contacts)): ?>
            <div class="chat-empty-state border rounded-5 p-4 p-md-5 text-center">
                <div class="rounded-circle bg-body-tertiary border d-inline-flex align-items-center justify-content-center mb-3" style="width: 72px; height: 72px;">
                    <i class="ci-chat fs-2 text-body-secondary"></i>
                </div>
                <h2 class="h5 mb-2"><?= print_translation('chat_contacts_title') ?></h2>
                <p class="text-body-secondary mb-0"><?= print_translation('chat_no_contacts') ?></p>
            </div>
        <?php else: ?>
            <div
                class="chat-app-shell"
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
                data-preview-text="<?= htmlSC(return_translation('chat_preview_file')) ?>"
                data-open-file-text="<?= htmlSC(return_translation('chat_open_file')) ?>"
                data-download-file-text="<?= htmlSC(return_translation('chat_download_file')) ?>"
                data-preview-unavailable-text="<?= htmlSC(return_translation('chat_preview_unavailable')) ?>"
                data-preview-loading-text="<?= htmlSC(return_translation('chat_preview_loading')) ?>"
                data-search-empty-text="<?= htmlSC(return_translation('chat_search_empty')) ?>"
                data-online-text="<?= htmlSC(return_translation('chat_status_online')) ?>"
                data-offline-text="<?= htmlSC(return_translation('chat_status_offline')) ?>"
            >
                <div class="row g-4 align-items-stretch">
                    <div class="col-xl-4 col-xxl-3">
                        <div class="chat-sidebar h-100">
                            <div class="chat-sidebar__head border-bottom px-4 py-4">
                                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                                    <div class="min-w-0">
                                        <div class="small text-uppercase fw-semibold text-body-secondary mb-1" style="letter-spacing: .08em;">
                                            <?= print_translation('chat_contacts_title') ?>
                                        </div>
                                        <strong class="d-block text-truncate"><?= htmlSC(get_user()['name'] ?? '') ?></strong>
                                    </div>
                                    <img
                                        src="<?= get_user_avatar(get_user()['avatar'] ?? null, 'sm') ?>"
                                        alt="<?= htmlSC((string)(get_user()['name'] ?? '')) ?>"
                                        class="rounded-circle border object-fit-cover flex-shrink-0"
                                        style="width: 48px; height: 48px;"
                                    >
                                </div>
                                <div class="position-relative">
                                    <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3 text-body-secondary"></i>
                                    <input
                                        type="search"
                                        class="form-control form-icon-start rounded-pill"
                                        placeholder="<?= print_translation('chat_search_placeholder') ?>"
                                        data-chat-contact-search
                                    >
                                </div>
                            </div>
                            <div class="chat-sidebar__body">
                                <?php foreach ($contactsByGroup as $groupKey => $groupContacts): ?>
                                    <?php if (empty($groupContacts)): ?>
                                        <?php continue; ?>
                                    <?php endif; ?>
                                    <div class="chat-contact-group border-bottom" data-chat-contact-group="<?= htmlSC($groupKey) ?>">
                                        <div class="px-4 pt-4 pb-2 d-flex align-items-center justify-content-between gap-2">
                                            <div class="small text-uppercase fw-semibold text-body-secondary" style="letter-spacing: .08em;">
                                                <?= htmlSC($contactGroupTitles[$groupKey] ?? $groupKey) ?>
                                            </div>
                                            <span class="badge rounded-pill text-body-emphasis bg-body-tertiary px-2"><?= count($groupContacts) ?></span>
                                        </div>
                                        <div class="list-group list-group-flush px-3 pb-3 gap-2" data-chat-contact-group-list="<?= htmlSC($groupKey) ?>">
                                            <?php foreach ($groupContacts as $contact): ?>
                                                <?php $isActive = (int)$active_contact['id'] === (int)$contact['id']; ?>
                                                <button
                                                    type="button"
                                                    class="list-group-item list-group-item-action border rounded-4 px-3 py-3 <?= $isActive ? 'active' : '' ?>"
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
                                                        <span class="position-relative flex-shrink-0">
                                                            <img
                                                                src="<?= get_user_avatar($contact['avatar'] ?? null, 'sm') ?>"
                                                                alt="<?= htmlSC($contact['name']) ?>"
                                                                class="rounded-circle border object-fit-cover"
                                                                style="width: 52px; height: 52px;"
                                                            >
                                                            <span class="chat-contact-presence <?= !empty($contact['is_online']) ? 'is-online' : 'is-offline' ?>"></span>
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
                                <div class="px-4 py-4 text-center text-body-secondary d-none" data-chat-search-empty>
                                    <?= print_translation('chat_search_empty') ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-8 col-xxl-9">
                        <div class="chat-thread h-100">
                            <div class="chat-thread__head border-bottom px-4 py-4 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                <div class="d-flex align-items-center gap-3 min-w-0">
                                    <img
                                        src="<?= get_user_avatar($active_contact['avatar'] ?? null, 'sm') ?>"
                                        alt="<?= htmlSC($active_contact['name']) ?>"
                                        class="rounded-circle border object-fit-cover flex-shrink-0"
                                        style="width: 56px; height: 56px;"
                                        data-chat-current-avatar
                                    >
                                    <div class="min-w-0">
                                        <div class="small text-uppercase fw-semibold text-body-secondary mb-1" style="letter-spacing: .08em;">
                                            <?= print_translation('chat_new_message') ?>
                                        </div>
                                        <strong class="d-block text-truncate fs-5" data-chat-current-name><?= htmlSC($active_contact['name']) ?></strong>
                                        <span class="small d-inline-flex align-items-center gap-1 <?= !empty($active_contact['is_online']) ? 'text-success' : 'text-body-secondary' ?>" data-chat-current-status>
                                            <span class="rounded-circle d-inline-block flex-shrink-0 <?= !empty($active_contact['is_online']) ? 'bg-success' : 'bg-secondary' ?>" style="width: 8px; height: 8px;"></span>
                                            <span><?= !empty($active_contact['is_online']) ? print_translation('chat_status_online') : print_translation('chat_status_offline') ?></span>
                                        </span>
                                    </div>
                                </div>
                                <span class="badge rounded-pill text-body-emphasis bg-body-tertiary px-3 py-2"><?= print_translation('chat_encryption_badge') ?></span>
                            </div>

                            <div class="chat-thread__body">
                                <div class="chat-messages-surface h-100" data-chat-messages>
                                    <p class="text-body-secondary mb-0"><?= print_translation('chat_loading') ?></p>
                                </div>
                            </div>

                            <div class="chat-thread__composer border-top px-3 px-md-4 py-3 py-md-4">
                                <form data-chat-form enctype="multipart/form-data">
                                    <?= get_csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int)$active_contact['id'] ?>" data-chat-user-id>
                                    <div class="chat-composer rounded-5 p-2 p-md-3">
                                        <div class="chat-composer__row">
                                            <label class="chat-composer__attach btn btn-outline-secondary rounded-circle mb-0" for="chat_attachment" title="<?= print_translation('chat_attachment_label') ?>">
                                                <i class="ci-paperclip"></i>
                                            </label>
                                            <input class="d-none" id="chat_attachment" type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.gif,.bmp,.mp3,.wav,.ogg,.m4a,.flac,.aac,.mp4,.webm,.mov,.avi,.mkv,.mpeg,.mpg,.pdf,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.rtf,.odt,.ods,.odp,.md,.json,.xml,.zip,.rar,.7z" data-chat-attachment>
                                            <input type="text" class="form-control border-0 shadow-none bg-transparent" name="message" maxlength="2000" placeholder="<?= print_translation('chat_message_placeholder') ?>">
                                            <button class="chat-composer__submit btn btn-dark rounded-pill px-4" type="submit">
                                                <i class="ci-send me-2"></i><?= print_translation('chat_send_btn') ?>
                                            </button>
                                        </div>
                                        <div class="chat-composer__meta d-flex align-items-center justify-content-between gap-3 flex-wrap mt-2 px-2">
                                            <div class="small text-body-secondary d-none" data-chat-attachment-name></div>
                                            <div class="small text-body-secondary"><?= print_translation('chat_file_hint') ?></div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="chatAttachmentModal" tabindex="-1" role="dialog" aria-hidden="true" data-chat-preview-modal>
                <div class="modal-dialog modal-fullscreen" role="document">
                    <div class="modal-content border-0 rounded-5 overflow-hidden">
                        <div class="modal-header border-0 pb-0">
                            <h2 class="modal-title fs-5 text-truncate" data-chat-preview-modal-title><?= print_translation('chat_image_modal_title') ?></h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body pt-3">
                            <div class="rounded-4 bg-body-tertiary d-flex align-items-center justify-content-center p-2 p-md-3" style="min-height: 320px;" data-chat-preview-modal-body>
                                <p class="text-body-secondary mb-0"><?= print_translation('chat_preview_loading') ?></p>
                            </div>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <a class="btn btn-outline-secondary rounded-pill" href="" target="_blank" rel="noopener noreferrer" data-chat-preview-modal-open>
                                <i class="ci-external-link me-2"></i>
                                <?= print_translation('chat_open_file') ?>
                            </a>
                            <a class="btn btn-outline-secondary rounded-pill" href="" download data-chat-preview-modal-download>
                                <i class="ci-download me-2"></i>
                                <?= print_translation('chat_download_file') ?>
                            </a>
                            <button type="button" class="btn btn-dark rounded-pill" data-bs-dismiss="modal"><?= print_translation('admin_btn_cancel') ?></button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>
</main>
