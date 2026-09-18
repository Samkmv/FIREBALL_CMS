
<style id="fireball-chat-reply-ui-v2">
/* FIREBALL_CHAT21_REPLY_UI_FIX_V2 */

[data-chat-app] .chat-composer-reply {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) 30px !important;
    align-items: center !important;
    gap: 8px !important;
    width: 100% !important;
    min-width: 0 !important;
    margin: 0 0 8px !important;
    padding: 8px 9px 8px 12px !important;
    border: 1px solid rgba(148, 163, 184, .16) !important;
    border-left: 3px solid var(--cz-primary, #4d8dff) !important;
    border-radius: 10px !important;
    background: rgba(77, 141, 255, .075) !important;
    box-shadow: none !important;
}

[data-chat-app] .chat-composer-reply.d-none {
    display: none !important;
}

[data-chat-app] .chat-composer-reply__body {
    display: block !important;
    min-width: 0 !important;
    overflow: hidden !important;
}

[data-chat-app] .chat-composer-reply__author {
    display: block !important;
    margin: 0 0 2px !important;
    color: #74a8ff !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    line-height: 1.15 !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}

[data-chat-app] .chat-composer-reply__text {
    display: block !important;
    margin: 0 !important;
    color: var(--cz-body-color) !important;
    font-size: 13px !important;
    font-weight: 400 !important;
    line-height: 1.25 !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    opacity: .72 !important;
}

[data-chat-app] .chat-composer-reply__cancel {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 28px !important;
    min-width: 28px !important;
    height: 28px !important;
    min-height: 28px !important;
    padding: 0 !important;
    margin: 0 !important;
    border: 0 !important;
    border-radius: 50% !important;
    background: transparent !important;
    color: var(--cz-body-color) !important;
    box-shadow: none !important;
    outline: none !important;
    opacity: .62 !important;
}

[data-chat-app] .chat-composer-reply__cancel:hover,
[data-chat-app] .chat-composer-reply__cancel:focus,
[data-chat-app] .chat-composer-reply__cancel:focus-visible {
    background: rgba(148, 163, 184, .12) !important;
    color: var(--cz-body-color) !important;
    box-shadow: none !important;
    outline: none !important;
    opacity: 1 !important;
}

[data-chat-app] .chat-message-reply-quote {
    -webkit-appearance: none !important;
    appearance: none !important;
    display: block !important;
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    margin: 0 0 6px !important;
    padding: 6px 8px 6px 10px !important;
    border: 0 !important;
    border-left: 3px solid rgba(255, 255, 255, .42) !important;
    border-radius: 7px !important;
    background: rgba(255, 255, 255, .09) !important;
    color: inherit !important;
    font: inherit !important;
    text-align: left !important;
    text-decoration: none !important;
    line-height: 1.2 !important;
    box-shadow: none !important;
    outline: none !important;
    cursor: pointer !important;
}

[data-chat-app] .chat-message-row--theirs .chat-message-reply-quote {
    border-left-color: rgba(116, 168, 255, .68) !important;
    background: rgba(116, 168, 255, .075) !important;
}

[data-chat-app] .chat-message-reply-quote:hover,
[data-chat-app] .chat-message-reply-quote:focus,
[data-chat-app] .chat-message-reply-quote:focus-visible {
    background: rgba(255, 255, 255, .14) !important;
    box-shadow: none !important;
    outline: none !important;
}

[data-chat-app] .chat-message-reply-quote__author {
    display: block !important;
    max-width: 100% !important;
    margin: 0 0 2px !important;
    overflow: hidden !important;
    color: inherit !important;
    font-size: 11px !important;
    font-weight: 600 !important;
    line-height: 1.15 !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
    opacity: .82 !important;
}

[data-chat-app] .chat-message-reply-quote__text {
    display: block !important;
    max-width: 100% !important;
    margin: 0 !important;
    overflow: hidden !important;
    color: inherit !important;
    font-size: 12px !important;
    font-weight: 400 !important;
    line-height: 1.2 !important;
    text-overflow: ellipsis !important;
    white-space: nowrap !important;
    opacity: .72 !important;
}

[data-chat-app] .chat-message-actions {
    position: absolute !important;
    top: 50% !important;
    z-index: 5 !important;
    display: flex !important;
    align-items: center !important;
    gap: 3px !important;
    width: auto !important;
    height: auto !important;
    padding: 3px !important;
    margin: 0 !important;
    border: 1px solid rgba(148, 163, 184, .15) !important;
    border-radius: 999px !important;
    background: rgba(20, 27, 38, .94) !important;
    box-shadow: 0 5px 14px rgba(0, 0, 0, .18) !important;
    transform: translateY(-50%) scale(.96) !important;
    opacity: 0 !important;
    pointer-events: none !important;
}

[data-chat-app] .chat-message-row--mine .chat-message-actions {
    right: calc(100% + 7px) !important;
    left: auto !important;
}

[data-chat-app] .chat-message-row--theirs .chat-message-actions {
    left: calc(100% + 7px) !important;
    right: auto !important;
}

[data-chat-app] .chat-message-row:hover .chat-message-actions,
[data-chat-app] .chat-message-row:focus-within .chat-message-actions {
    opacity: 1 !important;
    pointer-events: auto !important;
    transform: translateY(-50%) scale(1) !important;
}

[data-chat-app] .chat-message-reply-btn,
[data-chat-app] .chat-message-delete-btn {
    -webkit-appearance: none !important;
    appearance: none !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 27px !important;
    min-width: 27px !important;
    height: 27px !important;
    min-height: 27px !important;
    padding: 0 !important;
    margin: 0 !important;
    border: 0 !important;
    border-radius: 50% !important;
    background: transparent !important;
    color: #c7d0dc !important;
    font-size: 13px !important;
    line-height: 1 !important;
    box-shadow: none !important;
    outline: none !important;
}

[data-chat-app] .chat-message-reply-btn:hover,
[data-chat-app] .chat-message-reply-btn:focus,
[data-chat-app] .chat-message-reply-btn:focus-visible {
    background: rgba(77, 141, 255, .20) !important;
    color: #fff !important;
    box-shadow: none !important;
    outline: none !important;
}

[data-chat-app] .chat-message-delete-btn:hover,
[data-chat-app] .chat-message-delete-btn:focus,
[data-chat-app] .chat-message-delete-btn:focus-visible {
    background: rgba(255, 77, 87, .18) !important;
    color: #ff6c75 !important;
    box-shadow: none !important;
    outline: none !important;
}

[data-chat-app] .chat-message-reply-btn i,
[data-chat-app] .chat-message-delete-btn i {
    margin: 0 !important;
    font-size: 13px !important;
    line-height: 1 !important;
}

[data-chat-app] .chat-message-row.is-reply-target .chat-message-bubble {
    outline: 2px solid rgba(77, 141, 255, .75) !important;
    outline-offset: 2px !important;
    box-shadow: 0 0 0 4px rgba(77, 141, 255, .10) !important;
}

@media (max-width: 767.98px) {
    [data-chat-app] .chat-message-actions {
        position: static !important;
        justify-content: flex-end !important;
        margin-top: 3px !important;
        padding: 0 !important;
        border: 0 !important;
        background: transparent !important;
        box-shadow: none !important;
        transform: none !important;
        opacity: 1 !important;
        pointer-events: auto !important;
    }

    [data-chat-app] .chat-message-row--theirs .chat-message-actions {
        justify-content: flex-start !important;
    }

    [data-chat-app] .chat-message-reply-btn,
    [data-chat-app] .chat-message-delete-btn {
        width: 26px !important;
        min-width: 26px !important;
        height: 26px !important;
        min-height: 26px !important;
        background: rgba(148, 163, 184, .09) !important;
    }
}
</style>


<style id="fireball-chat-edit-ui">
/* FIREBALL_CHAT21_EDIT */
[data-chat-app] .chat-composer-edit {
    display: grid;
    grid-template-columns: 30px minmax(0, 1fr) 30px;
    align-items: center;
    gap: 8px;
    width: 100%;
    margin: 0 0 8px;
    padding: 8px 9px;
    border: 1px solid rgba(148, 163, 184, .16);
    border-left: 3px solid #f59e0b;
    border-radius: 10px;
    background: rgba(245, 158, 11, .075);
}
[data-chat-app] .chat-composer-edit.d-none { display: none !important; }
[data-chat-app] .chat-composer-edit__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(245, 158, 11, .12);
    color: #f59e0b;
}
[data-chat-app] .chat-composer-edit__body {
    min-width: 0;
    overflow: hidden;
}
[data-chat-app] .chat-composer-edit__title {
    display: block;
    margin: 0 0 2px;
    color: #f59e0b;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.15;
}
[data-chat-app] .chat-composer-edit__text {
    display: block;
    overflow: hidden;
    color: var(--cz-body-color);
    font-size: 13px;
    line-height: 1.25;
    white-space: nowrap;
    text-overflow: ellipsis;
    opacity: .72;
}
[data-chat-app] .chat-composer-edit__cancel {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: var(--cz-body-color);
    box-shadow: none;
    opacity: .62;
}
[data-chat-app] .chat-composer-edit__cancel:hover,
[data-chat-app] .chat-composer-edit__cancel:focus-visible {
    background: rgba(148, 163, 184, .12);
    opacity: 1;
}
[data-chat-app] form.is-editing .chat-composer__actions,
[data-chat-app] form.is-editing .chat-pending-attachment,
[data-chat-app] form.is-editing .chat-voice-recorder,
[data-chat-app] form.is-editing .chat-composer-reply {
    display: none !important;
}

/* FIREBALL_CHAT21_EDIT_UI_FIX */
[data-chat-app] form.is-editing .chat-composer__row {
    display: grid !important;
    grid-template-columns: minmax(0, 1fr) auto !important;
    align-items: center !important;
    gap: 12px !important;
}

[data-chat-app] form.is-editing .chat-composer__message {
    width: 100% !important;
    min-width: 0 !important;
    margin: 0 !important;
}

[data-chat-app] form.is-editing .chat-composer__submit {
    justify-self: end !important;
    align-self: center !important;
    margin: 0 !important;
}

[data-chat-app] form.is-editing .chat-composer__meta {
    margin-top: 10px !important;
}

@media (max-width: 767.98px) {
    [data-chat-app] form.is-editing .chat-composer__row {
        gap: 10px !important;
    }
}

[data-chat-app] .chat-message-edit-btn {
    -webkit-appearance: none;
    appearance: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 27px;
    min-width: 27px;
    height: 27px;
    min-height: 27px;
    padding: 0;
    margin: 0;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: #c7d0dc;
    font-size: 13px;
    line-height: 1;
    box-shadow: none;
    outline: none;
}
[data-chat-app] .chat-message-edit-btn:hover,
[data-chat-app] .chat-message-edit-btn:focus-visible {
    background: rgba(245, 158, 11, .18);
    color: #ffd089;
}
[data-chat-app] .chat-message-edit-btn i {
    margin: 0;
    font-size: 13px;
    line-height: 1;
}
[data-chat-app] .chat-message-edited {
    margin-left: 4px;
    font-size: .68rem;
    opacity: .58;
}
@media (max-width: 767.98px) {
    [data-chat-app] .chat-composer-edit {
        grid-template-columns: 28px minmax(0, 1fr) 28px;
        padding: 7px 8px;
    }
    [data-chat-app] .chat-message-edit-btn {
        width: 26px;
        min-width: 26px;
        height: 26px;
        min-height: 26px;
        background: rgba(148, 163, 184, .09);
        color: inherit;
    }
}
</style>


<style id="fireball-chat-reactions-ui">
/* FIREBALL_CHAT21_REACTIONS */
[data-chat-app] .chat-message-reactions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 4px;
}
[data-chat-app] .chat-message-row--mine .chat-message-reactions { justify-content: flex-end; }
[data-chat-app] .chat-message-row--theirs .chat-message-reactions { justify-content: flex-start; }
[data-chat-app] .chat-reaction-chip {
    -webkit-appearance: none;
    appearance: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-height: 24px;
    padding: 2px 7px;
    border: 1px solid rgba(148, 163, 184, .16);
    border-radius: 999px;
    background: rgba(148, 163, 184, .08);
    color: inherit;
    font: inherit;
    font-size: 12px;
    line-height: 1;
    box-shadow: none;
}
[data-chat-app] .chat-reaction-chip:hover,
[data-chat-app] .chat-reaction-chip:focus-visible {
    border-color: rgba(116, 168, 255, .42);
    background: rgba(116, 168, 255, .12);
}
[data-chat-app] .chat-reaction-chip.is-mine {
    border-color: rgba(77, 141, 255, .48);
    background: rgba(77, 141, 255, .16);
}
[data-chat-app] .chat-reaction-chip__emoji { font-size: 14px; }
[data-chat-app] .chat-reaction-chip__count { font-size: 11px; font-weight: 600; opacity: .72; }
[data-chat-app] .chat-message-reaction-btn {
    -webkit-appearance: none;
    appearance: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 27px;
    min-width: 27px;
    height: 27px;
    min-height: 27px;
    padding: 0;
    margin: 0;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: #c7d0dc;
    font-size: 13px;
    line-height: 1;
    box-shadow: none;
    outline: none;
}
[data-chat-app] .chat-message-reaction-btn:hover,
[data-chat-app] .chat-message-reaction-btn:focus-visible {
    background: rgba(116, 168, 255, .16);
    color: #fff;
}
[data-chat-app] .chat-reaction-picker {
    display: block;
    width: max-content;
    max-width: min(320px, calc(100vw - 40px));
    margin-top: 5px;
    padding: 6px;
    border: 1px solid rgba(148, 163, 184, .16);
    border-radius: 12px;
    background: rgba(20, 27, 38, .96);
    color: #e7edf6;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .22);
}
[data-chat-app] .chat-message-row--mine .chat-reaction-picker { margin-left: auto; }
[data-chat-app] .chat-message-row--theirs .chat-reaction-picker { margin-right: auto; }
[data-chat-app] .chat-reaction-picker.d-none { display: none !important; }
[data-chat-app] .chat-reaction-picker__choices { display: flex; align-items: center; gap: 3px; }
[data-chat-app] .chat-reaction-choice {
    -webkit-appearance: none;
    appearance: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: transparent;
    font-size: 19px;
    line-height: 1;
}
[data-chat-app] .chat-reaction-choice:hover,
[data-chat-app] .chat-reaction-choice:focus-visible,
[data-chat-app] .chat-reaction-choice.is-selected { background: rgba(77, 141, 255, .2); }
[data-chat-app] .chat-reaction-picker__users {
    display: grid;
    gap: 3px;
    margin-top: 6px;
    padding-top: 6px;
    border-top: 1px solid rgba(148, 163, 184, .12);
    font-size: 11px;
    line-height: 1.25;
    color: #aeb9c8;
}
[data-chat-app] .chat-reaction-picker__users:empty { display: none; }
@media (max-width: 767.98px) {
    [data-chat-app] .chat-message-reaction-btn {
        width: 26px;
        min-width: 26px;
        height: 26px;
        min-height: 26px;
        background: rgba(148, 163, 184, .09);
        color: inherit;
    }
    [data-chat-app] .chat-reaction-picker { max-width: min(290px, calc(100vw - 28px)); }
}
</style>


<style id="fireball-chat-receipts-ui">
/* FIREBALL_CHAT22_RECEIPTS */
[data-chat-app] .chat-message-checks {
    display: inline-flex;
    align-items: center;
    position: relative;
    min-width: 14px;
    margin-left: 2px;
    line-height: 1;
    vertical-align: middle;
    color: currentColor;
    opacity: .62;
}
[data-chat-app] .chat-message-checks i {
    display: inline-block;
    margin: 0;
    font-size: 11px;
    line-height: 1;
}
[data-chat-app] .chat-message-checks i + i {
    margin-left: -5px;
}
[data-chat-app] .chat-message-checks--sent,
[data-chat-app] .chat-message-checks--delivered {
    color: currentColor;
    opacity: .56;
}
[data-chat-app] .chat-message-checks--read {
    color: #8fc2ff;
    opacity: 1;
}
[data-chat-app] .chat-message-checks--read i {
    filter: drop-shadow(0 0 2px rgba(77, 141, 255, .2));
}
</style>

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
                    <button
                        type="button"
                        class="chat-sidebar__close btn btn-outline-secondary rounded-circle d-lg-none flex-shrink-0"
                        data-bs-dismiss="offcanvas"
                        title="<?= htmlSC(return_translation('admin_btn_close')) ?>"
                        aria-label="<?= htmlSC(return_translation('admin_btn_close')) ?>"
                    >
                        <i class="ci-close" aria-hidden="true"></i>
                    </button>
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
                data-stream-url="<?= htmlSC($chat_stream_url ?? '') ?>"
                data-send-url="<?= htmlSC($chat_send_url) ?>"
                data-edit-url="<?= htmlSC($chat_edit_url) ?>"
                data-react-url="<?= htmlSC($chat_react_url) ?>"
                data-delete-url="<?= htmlSC($chat_delete_url) ?>"
                data-clear-url="<?= htmlSC($chat_clear_url) ?>"
                data-audit-url="<?= htmlSC($chat_audit_url) ?>"
                data-audit-clear-url="<?= htmlSC($chat_audit_clear_url) ?>"
                data-current-user-avatar="<?= htmlSC(get_user_avatar(get_user()['avatar'] ?? null, 'sm')) ?>"
                data-verified-title="<?= htmlSC(return_translation('tpl_verified_customer')) ?>"
                data-empty-text="<?= htmlSC(return_translation('chat_empty_dialog')) ?>"
                data-load-error-text="<?= htmlSC(return_translation('chat_load_error')) ?>"
                data-new-message-text="<?= htmlSC(return_translation('chat_new_message')) ?>"
                data-attachment-label="<?= htmlSC(return_translation('chat_attachment_label')) ?>"
                data-file-too-large-text="<?= htmlSC(return_translation('chat_file_size_error')) ?>"
                data-file-type-error-text="<?= htmlSC(return_translation('chat_file_type_error')) ?>"
                data-max-file-size="<?= (int)($chat_max_file_size ?? 0) ?>"
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
                data-voice-record-text="<?= htmlSC(return_translation('chat_voice_record')) ?>"
                data-voice-recording-text="<?= htmlSC(return_translation('chat_voice_recording')) ?>"
                data-voice-stop-text="<?= htmlSC(return_translation('chat_voice_stop')) ?>"
                data-voice-cancel-text="<?= htmlSC(return_translation('chat_voice_cancel')) ?>"
                data-voice-ready-text="<?= htmlSC(return_translation('chat_voice_ready')) ?>"
                data-voice-message-text="<?= htmlSC(return_translation('chat_attachment_voice')) ?>"
                data-voice-play-text="<?= htmlSC(return_translation('chat_voice_play')) ?>"
                data-voice-pause-text="<?= htmlSC(return_translation('chat_voice_pause')) ?>"
                data-voice-seek-text="<?= htmlSC(return_translation('chat_voice_seek')) ?>"
                data-voice-unsupported-text="<?= htmlSC(return_translation('chat_voice_unsupported')) ?>"
                data-voice-permission-error-text="<?= htmlSC(return_translation('chat_voice_permission_error')) ?>"
                data-voice-max-duration-text="<?= htmlSC(return_translation('chat_voice_max_duration')) ?>"
                data-drop-title-text="<?= htmlSC(return_translation('chat_attachment_drop_title')) ?>"
                data-drop-subtitle-text="<?= htmlSC(return_translation('chat_attachment_drop_subtitle')) ?>"
                data-delete-message-text="<?= htmlSC(return_translation('chat_message_deleted')) ?>"
                data-delete-messages-text="<?= htmlSC(return_translation('chat_messages_deleted')) ?>"
                data-clear-chat-text="<?= htmlSC(return_translation('chat_conversation_cleared')) ?>"
                data-action-delete-text="<?= htmlSC(return_translation('chat_action_delete')) ?>"
                data-action-reply-text="<?= htmlSC(return_translation('chat_action_reply')) ?>"
                data-action-edit-text="<?= htmlSC(return_translation('chat_action_edit')) ?>"
                data-action-react-text="<?= htmlSC(return_translation('chat_action_react')) ?>"
                data-reaction-users-text="<?= htmlSC(return_translation('chat_reaction_users')) ?>"
                data-reaction-error-text="<?= htmlSC(return_translation('chat_reaction_error')) ?>"
                data-status-sent-text="<?= htmlSC(return_translation('chat_status_sent')) ?>"
                data-status-delivered-text="<?= htmlSC(return_translation('chat_status_delivered')) ?>"
                data-status-read-text="<?= htmlSC(return_translation('chat_status_read')) ?>"
                data-edit-label-text="<?= htmlSC(return_translation('chat_edit_label')) ?>"
                data-edit-message-required-text="<?= htmlSC(return_translation('chat_edit_message_required')) ?>"
                data-edit-error-text="<?= htmlSC(return_translation('chat_edit_error')) ?>"
                data-reply-you-text="<?= htmlSC(return_translation('chat_reply_you')) ?>"
                data-reply-attachment-text="<?= htmlSC(return_translation('chat_reply_attachment')) ?>"
                data-reply-deleted-text="<?= htmlSC(return_translation('chat_reply_deleted')) ?>"
                data-reply-cancel-text="<?= htmlSC(return_translation('chat_reply_cancel')) ?>"
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
                data-audit-deleted-count-text="<?= htmlSC(return_translation('chat_audit_label_deleted_count')) ?>"
                data-audit-attachment-count-text="<?= htmlSC(return_translation('chat_audit_label_attachment_count')) ?>"
                data-audit-load-error-text="<?= htmlSC(return_translation('chat_audit_load_error')) ?>"
                data-audit-clear-error-text="<?= htmlSC(return_translation('chat_audit_clear_error')) ?>"
                data-audit-cleared-text="<?= htmlSC(return_translation('chat_audit_cleared')) ?>"
                data-confirm-clear-audit-text="<?= htmlSC(return_translation('chat_confirm_clear_audit')) ?>"
                data-confirm-reason-label="<?= htmlSC(return_translation('chat_confirm_reason_label')) ?>"
                data-confirm-reason-placeholder="<?= htmlSC(return_translation('chat_confirm_reason_placeholder')) ?>"
                data-selection-count-text="<?= htmlSC(return_translation('chat_selection_count')) ?>"
                data-can-moderate="<?= !empty($chatPermissions['can_moderate']) ? '1' : '0' ?>"
                data-can-bulk-delete="<?= !empty($chatPermissions['can_bulk_delete']) ? '1' : '0' ?>"
                data-can-clear-chat="<?= !empty($chatPermissions['can_clear_chat']) ? '1' : '0' ?>"
                data-can-view-audit="<?= !empty($chatPermissions['can_view_audit']) ? '1' : '0' ?>"
                data-can-delete-audit="<?= !empty($chatPermissions['can_delete_audit']) ? '1' : '0' ?>"
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
                                        <span class="position-relative flex-shrink-0">
                                            <img
                                                src="<?= get_user_avatar($active_contact['avatar'] ?? null, 'sm') ?>"
                                                alt="<?= htmlSC($active_contact['name']) ?>"
                                                class="chat-current-avatar rounded-circle border object-fit-cover"
                                                data-chat-current-avatar
                                            >
                                            <span
                                                class="chat-contact-presence <?= !empty($active_contact['is_online']) ? 'is-online' : 'is-offline' ?>"
                                                data-chat-current-presence
                                                aria-hidden="true"
                                            ></span>
                                        </span>
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
                                    <div class="chat-thread__search-field position-relative flex-grow-1">
                                        <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3 text-body-secondary"></i>
                                        <input
                                            type="search"
                                            class="form-control form-icon-start"
                                            placeholder="<?= print_translation('chat_message_search_placeholder') ?>"
                                            data-chat-message-search
                                        >
                                    </div>
                                    <span class="badge rounded-pill text-body-emphasis bg-body-tertiary px-3 py-2 d-none" data-chat-message-search-results></span>
                                    <span class="chat-selection-count badge rounded-pill text-body-emphasis bg-body-tertiary d-none" data-chat-selection-count aria-live="polite"></span>
                                    <button
                                        type="button"
                                        class="chat-selection-action btn btn-outline-secondary rounded-pill d-none"
                                        title="<?= htmlSC(return_translation('chat_selection_cancel')) ?>"
                                        aria-label="<?= htmlSC(return_translation('chat_selection_cancel')) ?>"
                                        data-chat-selection-cancel
                                    >
                                        <i class="ci-close" aria-hidden="true"></i>
                                        <span class="chat-selection-action__label"><?= print_translation('chat_selection_cancel') ?></span>
                                    </button>
                                    <button
                                        type="button"
                                        class="chat-selection-action btn btn-danger rounded-pill d-none"
                                        title="<?= htmlSC(return_translation('chat_delete_selected_btn')) ?>"
                                        aria-label="<?= htmlSC(return_translation('chat_delete_selected_btn')) ?>"
                                        data-chat-delete-selected
                                    >
                                        <i class="ci-trash" aria-hidden="true"></i>
                                        <span class="chat-selection-action__label"><?= print_translation('chat_delete_selected_btn') ?></span>
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
                                    <input type="hidden" name="reply_to_id" value="" data-chat-reply-to-id>
                                    <input class="visually-hidden position-absolute" type="text" id="chatSiteFileSelection" data-chat-site-file-input>

                                    <div class="chat-composer" data-chat-dropzone>
                                        <div class="chat-composer__dropzone d-none" data-chat-dropzone-overlay>
                                            <div class="text-center">
                                                <div class="fw-semibold mb-1"><?= print_translation('chat_attachment_drop_title') ?></div>
                                                <div class="small text-body-secondary"><?= print_translation('chat_attachment_drop_subtitle') ?></div>
                                            </div>
                                        </div>

                                        <!-- FIREBALL_CHAT21_EDIT -->
                                        <div class="chat-composer-edit d-none" data-chat-edit-preview>
                                            <span class="chat-composer-edit__icon" aria-hidden="true">
                                                <i class="ci-edit-2"></i>
                                            </span>
                                            <div class="chat-composer-edit__body">
                                                <strong class="chat-composer-edit__title"><?= print_translation('chat_edit_title') ?></strong>
                                                <span class="chat-composer-edit__text" data-chat-edit-original></span>
                                            </div>
                                            <button
                                                type="button"
                                                class="chat-composer-edit__cancel"
                                                data-chat-edit-cancel
                                                title="<?= htmlSC(return_translation('chat_edit_cancel')) ?>"
                                                aria-label="<?= htmlSC(return_translation('chat_edit_cancel')) ?>"
                                            >
                                                <i class="ci-close" aria-hidden="true"></i>
                                            </button>
                                        </div>

                                        <!-- FIREBALL_CHAT21_REPLY -->
                                        <div class="chat-composer-reply d-none" data-chat-reply-preview>
                                            <div class="chat-composer-reply__body min-w-0">
                                                <strong class="chat-composer-reply__author text-truncate" data-chat-reply-author></strong>
                                                <span class="chat-composer-reply__text text-truncate" data-chat-reply-text></span>
                                            </div>
                                            <button
                                                type="button"
                                                class="chat-composer-reply__cancel btn btn-sm btn-outline-secondary rounded-circle"
                                                data-chat-reply-cancel
                                                title="<?= htmlSC(return_translation('chat_reply_cancel')) ?>"
                                                aria-label="<?= htmlSC(return_translation('chat_reply_cancel')) ?>"
                                            >
                                                <i class="ci-close" aria-hidden="true"></i>
                                            </button>
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
                                                        class="chat-composer__attach btn btn-outline-secondary rounded-circle d-none d-md-inline-flex"
                                                        title="<?= htmlSC(return_translation('admin_btn_choose_file')) ?>"
                                                        data-file-manager-open
                                                        data-file-manager-input="chatSiteFileSelection"
                                                        data-file-manager-dir="chat"
                                                        data-file-manager-url="<?= htmlSC($chat_file_manager_url) ?>"
                                                    >
                                                        <i class="ci-folder"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button
                                                    type="button"
                                                    class="chat-composer__attach chat-composer__record btn btn-outline-secondary rounded-circle"
                                                    title="<?= htmlSC(return_translation('chat_voice_record')) ?>"
                                                    aria-label="<?= htmlSC(return_translation('chat_voice_record')) ?>"
                                                    data-chat-record-voice
                                                >
                                                    <i class="ci-mic" aria-hidden="true"></i>
                                                </button>

                                            </div>
                                            <textarea
                                                class="form-control border-0 shadow-none bg-transparent chat-composer__message"
                                                name="message"
                                                rows="1"
                                                maxlength="2000"
                                                placeholder="<?= print_translation('chat_message_placeholder') ?>"
                                                data-chat-message-input
                                            ></textarea>
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

                                        <div class="chat-voice-recorder d-none" data-chat-voice-recorder role="status" aria-live="polite">
                                            <div class="chat-voice-recorder__status min-w-0">
                                                <span class="chat-voice-recorder__dot" aria-hidden="true"></span>
                                                <span class="fw-semibold text-truncate" data-chat-recording-label><?= print_translation('chat_voice_recording_short') ?></span>
                                                <time class="chat-voice-recorder__timer" data-chat-recording-timer>00:00</time>
                                            </div>
                                            <div class="chat-voice-recorder__actions d-flex align-items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="btn btn-danger rounded-pill"
                                                    title="<?= htmlSC(return_translation('chat_voice_stop')) ?>"
                                                    aria-label="<?= htmlSC(return_translation('chat_voice_stop')) ?>"
                                                    data-chat-record-stop
                                                >
                                                    <i class="ci-stop-circle" aria-hidden="true"></i>
                                                    <span><?= print_translation('chat_voice_stop_short') ?></span>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="btn btn-outline-secondary rounded-pill"
                                                    title="<?= htmlSC(return_translation('chat_voice_cancel')) ?>"
                                                    aria-label="<?= htmlSC(return_translation('chat_voice_cancel')) ?>"
                                                    data-chat-record-cancel
                                                >
                                                    <i class="ci-close" aria-hidden="true"></i>
                                                    <span><?= print_translation('chat_voice_cancel_short') ?></span>
                                                </button>
                                            </div>
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
            </div>
        <?php endif; ?>
    </section>
</main>

<?php if (!empty($contacts)): ?>
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
                        <?php if (!empty($chatPermissions['can_delete_audit'])): ?>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-danger rounded-pill" data-chat-clear-audit>
                                    <i class="ci-trash me-2" aria-hidden="true"></i>
                                    <?= print_translation('chat_audit_clear_btn') ?>
                                </button>
                            </div>
                        <?php endif; ?>
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
                            <div class="text-start mt-4 d-none" data-chat-confirm-reason-wrap>
                                <label class="form-label" for="chatConfirmReason"><?= print_translation('chat_confirm_reason_label') ?></label>
                                <textarea
                                    class="form-control"
                                    id="chatConfirmReason"
                                    rows="2"
                                    maxlength="255"
                                    placeholder="<?= htmlSC(return_translation('chat_confirm_reason_placeholder')) ?>"
                                    data-chat-confirm-reason
                                ></textarea>
                            </div>
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
<?php endif; ?>
