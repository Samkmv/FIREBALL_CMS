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
                                            <span class="small text-body-secondary d-block text-truncate mt-1">
                                                <?= htmlSC(get_user_role_label((string)($contact['role'] ?? 'user'))) ?>
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
        <?php
    };
    ?>

    <section class="container-fluid px-2 px-md-3 px-lg-4 px-xxl-5 py-3 py-lg-5 chat-page">
        <div class="chat-page-header d-flex align-items-start justify-content-between flex-wrap gap-3 mb-3 mb-xl-4">
            <div>
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
                data-delete-url="<?= htmlSC($chat_delete_url) ?>"
                data-clear-url="<?= htmlSC($chat_clear_url) ?>"
                data-audit-url="<?= htmlSC($chat_audit_url) ?>"
                data-current-user-avatar="<?= htmlSC(get_user_avatar(get_user()['avatar'] ?? null, 'sm')) ?>"
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
                <div class="row g-3 g-xl-4 align-items-stretch">
                    <div class="col-lg-4 col-xl-4 col-xxl-3 d-none d-lg-block">
                        <?php $renderChatSidebar(); ?>
                    </div>

                    <div class="col-lg-8 col-xl-8 col-xxl-9">
                        <div class="chat-thread h-100">
                            <div class="chat-thread__head border-bottom px-3 px-md-4 py-3 py-md-4">
                                <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
                                    <div class="d-flex align-items-center gap-3 min-w-0">
                                        <img
                                            src="<?= get_user_avatar($active_contact['avatar'] ?? null, 'sm') ?>"
                                            alt="<?= htmlSC($active_contact['name']) ?>"
                                            class="rounded-circle border object-fit-cover flex-shrink-0"
                                            style="width: 56px; height: 56px;"
                                            data-chat-current-avatar
                                        >
                                        <div class="min-w-0">
                                            <strong class="d-block text-truncate fs-5" data-chat-current-name><?= htmlSC($active_contact['name']) ?></strong>
                                            <div class="small text-body-secondary text-truncate"><?= htmlSC(get_user_role_label((string)($active_contact['role'] ?? 'user'))) ?></div>
                                            <span class="small d-inline-flex align-items-center gap-1 <?= !empty($active_contact['is_online']) ? 'text-success' : 'text-body-secondary' ?>" data-chat-current-status>
                                                <span class="rounded-circle d-inline-block flex-shrink-0 <?= !empty($active_contact['is_online']) ? 'bg-success' : 'bg-secondary' ?>" style="width: 8px; height: 8px;"></span>
                                                <span><?= !empty($active_contact['is_online']) ? print_translation('chat_status_online') : print_translation('chat_status_offline') ?></span>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end chat-thread__toolbar">
                                        <?php if (!empty($chatPermissions['can_bulk_delete'])): ?>
                                            <button type="button" class="btn btn-outline-secondary rounded-pill" data-chat-selection-toggle>
                                                <?= print_translation('chat_selection_mode') ?>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (!empty($chatPermissions['can_clear_chat'])): ?>
                                            <button type="button" class="btn btn-outline-danger rounded-pill" data-chat-clear-conversation>
                                                <?= print_translation('chat_clear_chat_btn') ?>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (!empty($chatPermissions['can_view_audit'])): ?>
                                            <button type="button" class="btn btn-outline-secondary rounded-pill" data-chat-open-audit>
                                                <?= print_translation('chat_audit_btn') ?>
                                            </button>
                                        <?php endif; ?>
                                        <span class="badge rounded-pill text-body-emphasis bg-body-tertiary px-3 py-2 d-none d-md-inline-flex"><?= print_translation('chat_encryption_badge') ?></span>
                                    </div>
                                </div>

                                <div class="chat-thread__searchbar mt-3 d-flex align-items-center gap-2 flex-wrap">
                                    <div class="position-relative flex-grow-1">
                                        <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3 text-body-secondary"></i>
                                        <input
                                            type="search"
                                            class="form-control form-icon-start rounded-pill"
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
                                    <p class="text-body-secondary mb-0"><?= print_translation('chat_loading') ?></p>
                                </div>
                            </div>

                            <div class="chat-thread__composer border-top px-2 px-md-3 py-2 py-md-3">
                                <form data-chat-form enctype="multipart/form-data">
                                    <?= get_csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int)$active_contact['id'] ?>" data-chat-user-id>
                                    <input class="visually-hidden position-absolute" type="text" id="chatSiteFileSelection" data-chat-site-file-input>
                                    <input class="d-none" type="file" data-chat-camera-input accept="image/*,video/*" capture="environment">
                                    <input class="d-none" type="file" data-chat-gallery-input accept="image/*,video/*" multiple>

                                    <div class="chat-composer rounded-5 p-2 p-md-3" data-chat-dropzone>
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
                                            <button class="chat-composer__submit btn btn-dark rounded-pill px-4" type="submit">
                                                <span class="d-inline-flex align-items-center justify-content-center gap-2">
                                                    <i class="ci-send"></i>
                                                    <span><?= print_translation('chat_send_btn') ?></span>
                                                </span>
                                            </button>
                                        </div>

                                        <div class="chat-composer__meta d-flex align-items-center justify-content-between gap-3 flex-wrap mt-2 px-2">
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
            <div class="offcanvas offcanvas-start chat-sidebar-offcanvas d-lg-none" tabindex="-1" id="accountSidebar" aria-labelledby="accountSidebarLabel">
                <div class="offcanvas-header py-3">
                    <h5 class="offcanvas-title" id="accountSidebarLabel"><?= print_translation('chat_contacts_title') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body pt-0 pb-4">
                    <?php $renderChatSidebar(); ?>
                </div>
            </div>

            <button
                type="button"
                class="fixed-bottom z-sticky w-100 btn btn-lg btn-dark border-0 border-top border-light border-opacity-10 rounded-0 pb-4 d-lg-none"
                data-bs-toggle="offcanvas"
                data-bs-target="#accountSidebar"
                aria-controls="accountSidebar"
                data-bs-theme="light"
            >
                <i class="ci-sidebar fs-base me-2"></i>
                <?= print_translation('chat_mobile_chats_btn') ?>
            </button>

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
