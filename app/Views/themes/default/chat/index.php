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

    $chatPermissions = is_array($chat_permissions ?? null) ? $chat_permissions : [];

    $renderChatSidebar = static function () use ($contactsByGroup, $contactGroupTitles, $active_contact) {
        ?>
        <div class="chat-sidebar">
            <div class="chat-sidebar__head">
                <div class="chat-sidebar__title-row">
                    <span class="chat-sidebar__title-icon" aria-hidden="true">
                        <i class="ci-chat"></i>
                    </span>
                    <div class="min-w-0 flex-grow-1">
                        <div class="fw-semibold text-truncate">
                            <?= print_translation('chat_contacts_title') ?>
                        </div>
                        <div class="small text-body-secondary text-truncate">
                            <?= htmlSC(get_user()['name'] ?? '') ?><?= render_public_verified_badge(get_user()['role'] ?? null) ?>
                        </div>
                    </div>
                    <button type="button" class="btn-close d-lg-none flex-shrink-0" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="position-relative mt-3">
                    <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3 text-body-secondary"></i>
                    <input
                        type="search"
                        class="form-control form-icon-start chat-sidebar__search"
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
                        <div class="chat-contact-group__head d-flex align-items-center justify-content-between gap-2">
                            <div class="small text-uppercase fw-semibold text-body-secondary">
                                <?= htmlSC($contactGroupTitles[$groupKey] ?? $groupKey) ?>
                            </div>
                            <span class="badge rounded-pill text-body-emphasis bg-body-tertiary px-2"><?= count($groupContacts) ?></span>
                        </div>
                        <div class="list-group list-group-flush chat-contact-list" data-chat-contact-group-list="<?= htmlSC($groupKey) ?>">
                            <?php foreach ($groupContacts as $contact): ?>
                                <?php $isActive = (int)$active_contact['id'] === (int)$contact['id']; ?>
                                <button
                                    type="button"
                                    class="list-group-item list-group-item-action chat-contact-item <?= $isActive ? 'active' : '' ?>"
                                    data-chat-contact
                                    data-chat-id="<?= (int)$contact['id'] ?>"
                                    data-contact-group="<?= htmlSC((string)($contact['chat_group'] ?? 'clients')) ?>"
                                    data-contact-group-label="<?= htmlSC($contactGroupTitles[$groupKey] ?? $groupKey) ?>"
                                    data-user-id="<?= (int)$contact['id'] ?>"
                                    data-user-name="<?= htmlSC($contact['name']) ?>"
                                    data-user-avatar="<?= htmlSC(get_user_avatar($contact['avatar'] ?? null, 'sm')) ?>"
                                    data-user-online="<?= (int)($contact['is_online'] ?? 0) ?>"
                                    data-user-last-seen="<?= htmlSC((string)($contact['last_seen_at'] ?? '')) ?>"
                                    data-user-role="<?= htmlSC((string)($contact['role'] ?? 'user')) ?>"
                                    data-user-role-label="<?= htmlSC(get_user_role_label((string)($contact['role'] ?? 'user'))) ?>"
                                    data-last-message-preview="<?= htmlSC((string)($contact['last_message_preview'] ?? '')) ?>"
                                >
                                    <span class="d-flex align-items-center gap-3 min-w-0">
                                        <span class="position-relative flex-shrink-0">
                                            <img
                                                src="<?= get_user_avatar($contact['avatar'] ?? null, 'sm') ?>"
                                                alt="<?= htmlSC($contact['name']) ?>"
                                                class="chat-contact-avatar rounded-circle border object-fit-cover"
                                            >
                                            <span class="chat-contact-presence <?= !empty($contact['is_online']) ? 'is-online' : 'is-offline' ?>"></span>
                                        </span>
                                        <span class="min-w-0 flex-grow-1">
                                            <span class="d-flex align-items-start justify-content-between gap-2">
                                                <span class="d-block text-truncate fw-semibold"><?= htmlSC($contact['name']) ?><?= render_public_verified_badge($contact['role'] ?? null) ?></span>
                                                <span
                                                    class="badge text-bg-danger rounded-pill flex-shrink-0 chat-contact-unread-badge <?= (int)($contact['unread_count'] ?? 0) > 0 ? '' : 'd-none' ?>"
                                                    data-chat-contact-unread="<?= (int)$contact['id'] ?>"
                                                ><?= (int)($contact['unread_count'] ?? 0) ?></span>
                                            </span>
                                            <span class="chat-contact-meta small text-body-secondary d-flex align-items-center gap-2 mt-1">
                                                <span class="text-truncate"><?= htmlSC(get_user_role_label((string)($contact['role'] ?? 'user'))) ?></span>
                                                <span aria-hidden="true">•</span>
                                                <span
                                                    class="d-inline-flex align-items-center gap-1 flex-shrink-0 <?= !empty($contact['is_online']) ? 'text-success' : 'text-body-secondary' ?>"
                                                    data-chat-contact-status="<?= (int)$contact['id'] ?>"
                                                >
                                                    <span class="rounded-circle d-inline-block flex-shrink-0 <?= !empty($contact['is_online']) ? 'bg-success' : 'bg-secondary' ?>" style="width: 8px; height: 8px;"></span>
                                                    <span><?= !empty($contact['is_online']) ? print_translation('chat_status_online') : print_translation('chat_status_offline') ?></span>
                                                </span>
                                            </span>
                                            <span class="chat-contact-preview small text-body-secondary d-block text-truncate mt-1" data-chat-contact-preview="<?= (int)$contact['id'] ?>">
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
        <?php
    };
    ?>

    <section class="container-fluid chat-page">
        <div class="chat-page-header d-flex align-items-start justify-content-between flex-wrap gap-3 mb-3 mb-xl-4">
            <div class="d-flex align-items-center gap-3">
                <span class="chat-page-header__icon" aria-hidden="true">
                    <i class="ci-chat"></i>
                </span>
                <div>
                    <h1 class="h4 mb-1"><?= print_translation('chat_index_heading') ?></h1>
                    <p class="small text-body-secondary mb-0"><?= print_translation('chat_index_subtitle') ?></p>
                </div>
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
                data-delete-url="<?= htmlSC($chat_delete_url) ?>"
                data-clear-url="<?= htmlSC($chat_clear_url) ?>"
                data-audit-url="<?= htmlSC($chat_audit_url) ?>"
                data-current-user-avatar="<?= htmlSC(get_user_avatar(get_user()['avatar'] ?? null, 'sm')) ?>"
                data-verified-title="<?= htmlSC(return_translation('tpl_verified_customer')) ?>"
                data-empty-text="<?= htmlSC(return_translation('chat_empty_dialog')) ?>"
                data-new-message-text="<?= htmlSC(return_translation('chat_new_message')) ?>"
                data-attachment-label="<?= htmlSC(return_translation('chat_attachment_label')) ?>"
                data-file-too-large-text="<?= htmlSC(return_translation('chat_file_size_error')) ?>"
                data-file-type-error-text="<?= htmlSC(return_translation('chat_file_type_error')) ?>"
                data-message-required-text="<?= htmlSC(return_translation('chat_message_required')) ?>"
                data-download-image-text="<?= htmlSC(return_translation('chat_download_image')) ?>"
                data-image-modal-title="<?= htmlSC(return_translation('chat_image_modal_title')) ?>"
                data-preview-text="<?= htmlSC(return_translation('chat_preview_file')) ?>"
                data-open-file-text="<?= htmlSC(return_translation('chat_open_file')) ?>"
                data-download-file-text="<?= htmlSC(return_translation('chat_download_file')) ?>"
                data-preview-unavailable-text="<?= htmlSC(return_translation('chat_preview_unavailable')) ?>"
                data-preview-loading-text="<?= htmlSC(return_translation('chat_preview_loading')) ?>"
                data-search-empty-text="<?= htmlSC(return_translation('chat_search_empty')) ?>"
                data-message-search-empty-text="<?= htmlSC(return_translation('chat_message_search_empty')) ?>"
                data-message-search-results-text="<?= htmlSC(return_translation('chat_message_search_results')) ?>"
                data-online-text="<?= htmlSC(return_translation('chat_status_online')) ?>"
                data-offline-text="<?= htmlSC(return_translation('chat_status_offline')) ?>"
                data-attachment-remove-text="<?= htmlSC(return_translation('chat_attachment_remove')) ?>"
                data-attachment-ready-text="<?= htmlSC(return_translation('chat_attachment_ready')) ?>"
                data-drop-title-text="<?= htmlSC(return_translation('chat_attachment_drop_title')) ?>"
                data-drop-subtitle-text="<?= htmlSC(return_translation('chat_attachment_drop_subtitle')) ?>"
                data-delete-message-text="<?= htmlSC(return_translation('chat_message_deleted')) ?>"
                data-delete-messages-text="<?= htmlSC(return_translation('chat_messages_deleted')) ?>"
                data-clear-chat-text="<?= htmlSC(return_translation('chat_conversation_cleared')) ?>"
                data-action-delete-text="<?= htmlSC(return_translation('chat_action_delete')) ?>"
                data-confirm-delete-message-text="<?= htmlSC(return_translation('chat_confirm_delete_message')) ?>"
                data-confirm-delete-messages-text="<?= htmlSC(return_translation('chat_confirm_delete_messages')) ?>"
                data-confirm-clear-chat-text="<?= htmlSC(return_translation('chat_confirm_clear_chat')) ?>"
                data-audit-empty-text="<?= htmlSC(return_translation('chat_audit_empty')) ?>"
                data-audit-delete-text="<?= htmlSC(return_translation('chat_audit_action_delete_message')) ?>"
                data-audit-bulk-delete-text="<?= htmlSC(return_translation('chat_audit_action_bulk_delete')) ?>"
                data-audit-clear-text="<?= htmlSC(return_translation('chat_audit_action_clear_conversation')) ?>"
                data-audit-ip-text="<?= htmlSC(return_translation('chat_audit_label_ip')) ?>"
                data-audit-device-text="<?= htmlSC(return_translation('chat_audit_label_device')) ?>"
                data-audit-reason-text="<?= htmlSC(return_translation('chat_audit_label_reason')) ?>"
                data-audit-attachment-text="<?= htmlSC(return_translation('chat_audit_label_attachment')) ?>"
                data-selection-count-text="<?= htmlSC(return_translation('chat_selection_count')) ?>"
                data-can-moderate="<?= !empty($chatPermissions['can_moderate']) ? '1' : '0' ?>"
                data-can-bulk-delete="<?= !empty($chatPermissions['can_bulk_delete']) ? '1' : '0' ?>"
                data-can-clear-chat="<?= !empty($chatPermissions['can_clear_chat']) ? '1' : '0' ?>"
                data-can-view-audit="<?= !empty($chatPermissions['can_view_audit']) ? '1' : '0' ?>"
            >
                <div class="chat-app-layout">
                    <aside class="chat-layout-sidebar d-none d-lg-flex">
                        <?php $renderChatSidebar(); ?>
                    </aside>

                    <div class="chat-layout-main">
                        <div class="chat-thread">
                            <div class="chat-thread__head">
                                <div class="chat-thread__topline">
                                    <div class="d-flex align-items-center gap-3 min-w-0">
                                        <button
                                            type="button"
                                            class="chat-thread__mobile-chats btn btn-outline-secondary d-lg-none flex-shrink-0"
                                            data-bs-toggle="offcanvas"
                                            data-bs-target="#accountSidebar"
                                            aria-controls="accountSidebar"
                                            aria-label="<?= htmlSC(return_translation('chat_mobile_chats_btn')) ?>"
                                        >
                                            <i class="ci-sidebar"></i>
                                        </button>
                                        <img
                                            src="<?= get_user_avatar($active_contact['avatar'] ?? null, 'sm') ?>"
                                            alt="<?= htmlSC($active_contact['name']) ?>"
                                            class="chat-current-avatar rounded-circle border object-fit-cover flex-shrink-0"
                                            data-chat-current-avatar
                                        >
                                        <div class="min-w-0">
                                            <strong class="d-block text-truncate" data-chat-current-name><?= htmlSC($active_contact['name']) ?><?= render_public_verified_badge($active_contact['role'] ?? null) ?></strong>
                                            <div class="chat-current-meta small d-flex align-items-center gap-2 text-body-secondary">
                                                <span class="text-truncate" data-chat-current-role><?= htmlSC(get_user_role_label((string)($active_contact['role'] ?? 'user'))) ?></span>
                                                <span aria-hidden="true">•</span>
                                                <span class="d-inline-flex align-items-center gap-1 flex-shrink-0 <?= !empty($active_contact['is_online']) ? 'text-success' : 'text-body-secondary' ?>" data-chat-current-status>
                                                    <span class="rounded-circle d-inline-block flex-shrink-0 <?= !empty($active_contact['is_online']) ? 'bg-success' : 'bg-secondary' ?>" style="width: 8px; height: 8px;"></span>
                                                    <span><?= !empty($active_contact['is_online']) ? print_translation('chat_status_online') : print_translation('chat_status_offline') ?></span>
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="chat-thread__toolbar">
                                        <?php if (!empty($chatPermissions['can_bulk_delete'])): ?>
                                            <button type="button" class="btn btn-outline-secondary chat-thread__tool-btn" title="<?= htmlSC(return_translation('chat_selection_mode')) ?>" aria-label="<?= htmlSC(return_translation('chat_selection_mode')) ?>" data-chat-selection-toggle>
                                                <i class="ci-check-circle" aria-hidden="true"></i>
                                                <span class="chat-thread__tool-label"><?= print_translation('chat_selection_mode') ?></span>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (!empty($chatPermissions['can_clear_chat'])): ?>
                                            <button type="button" class="btn btn-outline-danger chat-thread__tool-btn" title="<?= htmlSC(return_translation('chat_clear_chat_btn')) ?>" aria-label="<?= htmlSC(return_translation('chat_clear_chat_btn')) ?>" data-chat-clear-conversation>
                                                <i class="ci-trash" aria-hidden="true"></i>
                                                <span class="chat-thread__tool-label"><?= print_translation('chat_clear_chat_btn') ?></span>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (!empty($chatPermissions['can_view_audit'])): ?>
                                            <button type="button" class="btn btn-outline-secondary chat-thread__tool-btn" title="<?= htmlSC(return_translation('chat_audit_btn')) ?>" aria-label="<?= htmlSC(return_translation('chat_audit_btn')) ?>" data-chat-open-audit>
                                                <i class="ci-file-text" aria-hidden="true"></i>
                                                <span class="chat-thread__tool-label"><?= print_translation('chat_audit_btn') ?></span>
                                            </button>
                                        <?php endif; ?>
                                        <span class="chat-thread__encryption badge text-body-emphasis bg-body-tertiary d-none d-xl-inline-flex align-items-center gap-2">
                                            <i class="ci-lock" aria-hidden="true"></i>
                                            <?= print_translation('chat_encryption_badge') ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="chat-thread__searchbar d-flex align-items-center gap-2 flex-wrap">
                                    <div class="position-relative flex-grow-1">
                                        <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3 text-body-secondary"></i>
                                        <input
                                            type="search"
                                            class="form-control form-icon-start"
                                            placeholder="<?= print_translation('chat_message_search_placeholder') ?>"
                                            data-chat-message-search
                                        >
                                    </div>
                                    <span class="badge rounded-pill text-body-emphasis bg-body-tertiary px-3 py-2 d-none" data-chat-message-search-results></span>
                                    <button type="button" class="btn btn-outline-secondary rounded-pill d-none" data-chat-selection-cancel>
                                        <?= print_translation('chat_selection_cancel') ?>
                                    </button>
                                    <button type="button" class="btn btn-danger rounded-pill d-none" data-chat-delete-selected>
                                        <?= print_translation('chat_delete_selected_btn') ?>
                                    </button>
                                </div>
                            </div>

                            <div class="chat-thread__body">
                                <div class="chat-messages-surface h-100" data-chat-messages>
                                    <div class="chat-dialog-empty">
                                        <span class="chat-dialog-empty__icon" aria-hidden="true"><i class="ci-chat"></i></span>
                                        <p class="text-body-secondary mb-0"><?= print_translation('chat_loading') ?></p>
                                    </div>
                                </div>
                            </div>

                            <div class="chat-thread__composer border-top">
                                <form data-chat-form enctype="multipart/form-data">
                                    <?= get_csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int)$active_contact['id'] ?>" data-chat-user-id>
                                    <input class="visually-hidden position-absolute" type="text" id="chatSiteFileSelection" data-chat-site-file-input>
                                    <input class="d-none" type="file" data-chat-camera-input accept="image/*,video/*" capture="environment">
                                    <input class="d-none" type="file" data-chat-gallery-input accept="image/*,video/*" multiple>

                                    <div class="chat-composer" data-chat-dropzone>
                                        <div class="chat-composer__dropzone d-none" data-chat-dropzone-overlay>
                                            <div class="text-center">
                                                <div class="fw-semibold mb-1"><?= print_translation('chat_attachment_drop_title') ?></div>
                                                <div class="small text-body-secondary"><?= print_translation('chat_attachment_drop_subtitle') ?></div>
                                            </div>
                                        </div>

                                        <div class="chat-composer__row">
                                            <div class="chat-composer__actions d-flex align-items-center gap-2">
                                                <div id="chatAttachBtn" class="chat-composer__attach chat-composer__attach--picker btn btn-outline-secondary rounded-circle" title="<?= htmlSC(return_translation('chat_attachment_files')) ?>" aria-label="<?= htmlSC(return_translation('chat_attachment_files')) ?>">
                                                    <i class="ci-paperclip"></i>
                                                    <input class="chat-file-input" type="file" id="chatFileInput" name="attachment[]" multiple data-chat-attachment accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.rtf,.odt,.ods,.odp,.ppt,.pptx,.zip,.rar,.7z,.json,.xml,.md">
                                                </div>
                                                <?php if (!empty($chat_file_manager_enabled) && !empty($chat_file_manager_url)): ?>
                                                    <button
                                                        type="button"
                                                        class="chat-composer__attach btn btn-outline-secondary rounded-circle"
                                                        title="<?= htmlSC(return_translation('admin_btn_choose_file')) ?>"
                                                        data-file-manager-open
                                                        data-file-manager-input="chatSiteFileSelection"
                                                        data-file-manager-dir="chat"
                                                        data-file-manager-url="<?= htmlSC($chat_file_manager_url) ?>"
                                                    >
                                                        <i class="ci-folder"></i>
                                                    </button>
                                                <?php endif; ?>

                                            </div>
                                            <input type="text" class="form-control border-0 shadow-none bg-transparent" name="message" maxlength="2000" placeholder="<?= print_translation('chat_message_placeholder') ?>">
                                            <button
                                                class="chat-composer__submit btn btn-primary rounded-circle"
                                                type="submit"
                                                title="<?= htmlSC(return_translation('chat_send_btn')) ?>"
                                                aria-label="<?= htmlSC(return_translation('chat_send_btn')) ?>"
                                            >
                                                <i class="ci-send" aria-hidden="true"></i>
                                                <span class="visually-hidden"><?= print_translation('chat_send_btn') ?></span>
                                            </button>
                                        </div>

                                        <div class="chat-composer__meta chat-composer__hint d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                            <div class="small text-body-secondary"><?= print_translation('chat_file_hint') ?></div>
                                        </div>

                                        <div class="chat-pending-attachment d-none mt-3" data-chat-pending-attachment>
                                            <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
                                                <div class="min-w-0 flex-grow-1">
                                                    <div class="small text-body-secondary mb-2" data-chat-pending-meta></div>
                                                    <div class="chat-pending-attachment__list d-flex flex-column gap-2" data-chat-pending-list></div>
                                                    <div class="progress mt-3 d-none" style="height: 6px;" data-chat-upload-progress-wrap>
                                                        <div class="progress-bar" role="progressbar" style="width: 0%" data-chat-upload-progress></div>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-outline-secondary rounded-pill flex-shrink-0" data-chat-remove-attachment>
                                                    <?= print_translation('chat_attachment_remove') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <div class="offcanvas offcanvas-start chat-sidebar-offcanvas d-lg-none" tabindex="-1" id="accountSidebar" aria-label="<?= htmlSC(return_translation('chat_contacts_title')) ?>">
                <div class="offcanvas-body p-0">
                    <?php $renderChatSidebar(); ?>
                </div>
            </div>

            <div class="modal fade" id="chatAttachmentModal" tabindex="-1" role="dialog" aria-hidden="true" data-chat-preview-modal>
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content chat-preview-modal">
                        <div class="modal-header chat-preview-modal__header align-items-center gap-3">
                            <h2 class="modal-title fs-5 text-truncate min-w-0 flex-grow-1" data-chat-preview-modal-title><?= print_translation('chat_image_modal_title') ?></h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body chat-preview-modal__body">
                            <div class="chat-preview-modal__stage rounded-4 bg-body-tertiary d-flex align-items-center justify-content-center p-2 p-md-3" data-chat-preview-modal-body>
                                <p class="text-body-secondary mb-0"><?= print_translation('chat_preview_loading') ?></p>
                            </div>
                        </div>
                        <div class="modal-footer chat-preview-modal__footer">
                            <a class="btn btn-outline-secondary rounded-pill" href="" target="_blank" rel="noopener noreferrer" data-chat-preview-modal-open>
                                <i class="ci-external-link me-2"></i>
                                <?= print_translation('chat_open_file') ?>
                            </a>
                            <a class="btn btn-outline-secondary rounded-pill" href="" download data-chat-preview-modal-download>
                                <i class="ci-download me-2"></i>
                                <?= print_translation('chat_download_file') ?>
                            </a>
                            <button type="button" class="btn btn-dark rounded-pill" data-bs-dismiss="modal"><?= print_translation('admin_btn_close') ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="chatAuditModal" tabindex="-1" aria-hidden="true" data-chat-audit-modal>
                <div class="modal-dialog modal-dialog-scrollable modal-lg">
                    <div class="modal-content border-0 rounded-5 overflow-hidden">
                        <div class="modal-header">
                            <h2 class="modal-title fs-5"><?= print_translation('chat_audit_title') ?></h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" data-chat-audit-list>
                            <p class="text-body-secondary mb-0"><?= print_translation('chat_loading') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="chatConfirmModal" tabindex="-1" aria-hidden="true" data-chat-confirm-modal>
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 rounded-5 overflow-hidden">
                        <div class="modal-body p-4 p-md-5 text-center">
                            <div class="rounded-circle bg-body-tertiary border d-inline-flex align-items-center justify-content-center mb-3" style="width: 72px; height: 72px;">
                                <i class="ci-trash fs-2 text-danger"></i>
                            </div>
                            <h2 class="h4 mb-2"><?= print_translation('admin_delete_modal_title') ?></h2>
                            <p class="text-body-secondary mb-0" data-chat-confirm-message><?= print_translation('chat_confirm_delete_message') ?></p>
                        </div>
                        <div class="modal-footer border-0 justify-content-center gap-2 px-4 pb-4 pt-0">
                            <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">
                                <?= print_translation('admin_btn_cancel') ?>
                            </button>
                            <button type="button" class="btn btn-danger rounded-pill" data-chat-confirm-submit>
                                <?= print_translation('chat_action_delete') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            </div>
        <?php endif; ?>
    </section>
</main>
