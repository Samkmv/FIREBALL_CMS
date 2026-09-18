<style id="fireball-chat30-groups-view">
/* FIREBALL_CHAT30_GROUPS */
[data-group-chat-app] .group-chat-shell {
    min-height: min(72vh, 820px);
}
[data-group-chat-app] .group-chat-messages {
    min-height: 420px;
    max-height: 62vh;
    overflow-y: auto;
}
[data-group-chat-app] .group-chat-row {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    margin: 8px 0;
}
[data-group-chat-app] .group-chat-row.is-mine {
    justify-content: flex-end;
}
[data-group-chat-app] .group-chat-avatar {
    width: 32px;
    height: 32px;
    object-fit: cover;
    flex: 0 0 auto;
}
[data-group-chat-app] .group-chat-message {
    max-width: min(76%, 720px);
}
[data-group-chat-app] .group-chat-row.is-mine .group-chat-message {
    text-align: right;
}
[data-group-chat-app] .group-chat-sender {
    margin: 0 8px 3px;
    font-size: 11px;
    font-weight: 600;
    color: var(--cz-body-color);
    opacity: .68;
}
[data-group-chat-app] .group-chat-bubble {
    display: inline-block;
    padding: 9px 12px 6px;
    border-radius: 15px;
    background: var(--cz-tertiary-bg);
    text-align: left;
    word-break: break-word;
}
[data-group-chat-app] .group-chat-row.is-mine .group-chat-bubble {
    background: var(--cz-primary);
    color: #fff;
}
[data-group-chat-app] .group-chat-time {
    display: block;
    margin-top: 4px;
    font-size: 10px;
    opacity: .62;
    text-align: right;
}
[data-group-chat-app] .group-chat-composer {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 44px;
    gap: 10px;
    align-items: end;
}
[data-group-chat-app] .group-chat-composer textarea {
    min-height: 44px;
    max-height: 132px;
    resize: none;
}
@media (max-width: 767.98px) {
    [data-group-chat-app] .group-chat-message {
        max-width: 88%;
    }
}
</style>

<main class="content-wrapper">
    <section class="container-fluid py-3 py-lg-4">
        <div
            data-group-chat-app
            data-conversation-id="<?= (int)$group['id'] ?>"
            data-messages-url="<?= htmlSC($chat_group_messages_url) ?>"
            data-send-url="<?= htmlSC($chat_group_send_url) ?>"
            data-stream-url="<?= htmlSC($chat_group_stream_url) ?>"
            data-load-error-text="<?= htmlSC(return_translation('chat_group_load_error')) ?>"
            data-send-error-text="<?= htmlSC(return_translation('chat_group_send_error')) ?>"
            data-empty-text="<?= htmlSC(return_translation('chat_group_empty')) ?>"
        >
            <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mb-3">
                <div class="d-flex align-items-center gap-3 min-w-0">
                    <a
                        href="<?= htmlSC($chat_index_url) ?>"
                        class="btn btn-outline-secondary rounded-circle flex-shrink-0"
                        title="<?= htmlSC(return_translation('chat_group_back')) ?>"
                        aria-label="<?= htmlSC(return_translation('chat_group_back')) ?>"
                    >
                        <i class="ci-arrow-left" aria-hidden="true"></i>
                    </a>
                    <div class="min-w-0">
                        <h1 class="h4 mb-1 text-truncate"><?= htmlSC($group['title']) ?></h1>
                        <div class="small text-body-secondary">
                            <?= (int)$group['member_count'] ?> <?= print_translation('chat_group_members_short') ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm group-chat-shell overflow-hidden">
                <div class="card-header bg-body border-bottom px-3 px-md-4 py-3">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?php foreach ($members as $member): ?>
                            <span
                                class="badge rounded-pill text-body-emphasis bg-body-tertiary d-inline-flex align-items-center gap-2 px-2 py-2"
                                title="<?= htmlSC($member['name']) ?>"
                            >
                                <img
                                    src="<?= get_user_avatar($member['avatar'] ?? null, 'sm') ?>"
                                    alt=""
                                    class="rounded-circle object-fit-cover"
                                    style="width: 22px; height: 22px;"
                                >
                                <span><?= htmlSC($member['name']) ?></span>
                                <?php if (($member['group_role'] ?? '') === 'owner'): ?>
                                    <i class="ci-star-filled small" aria-hidden="true"></i>
                                <?php endif; ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card-body p-3 p-md-4 group-chat-messages" data-group-chat-messages>
                    <div class="text-center text-body-secondary py-5">
                        <?= print_translation('chat_loading') ?>
                    </div>
                </div>

                <div class="card-footer bg-body border-top p-3">
                    <form data-group-chat-form>
                        <?= get_csrf_field() ?>
                        <input type="hidden" name="conversation_id" value="<?= (int)$group['id'] ?>">
                        <div class="group-chat-composer">
                            <textarea
                                class="form-control"
                                name="message"
                                rows="1"
                                maxlength="2000"
                                placeholder="<?= htmlSC(return_translation('chat_group_message_placeholder')) ?>"
                                data-group-chat-input
                            ></textarea>
                            <button
                                type="submit"
                                class="btn btn-primary rounded-circle"
                                title="<?= htmlSC(return_translation('chat_send_btn')) ?>"
                                aria-label="<?= htmlSC(return_translation('chat_send_btn')) ?>"
                            >
                                <i class="ci-send" aria-hidden="true"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</main>
