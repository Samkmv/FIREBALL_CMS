<?php echo $this->partial('chat/viewport', get_defined_vars()); ?>
<style id="fireball-chat31-group-ui-v2">
/* FIREBALL_CHAT30_GROUPS */
/* FIREBALL_CHAT31_GROUP_UI_V2 */
[data-group-chat-app] { --group-member-width: 18rem; }
[data-group-chat-app] .chat-app-layout { height: clamp(38rem, calc(100dvh - 10.5rem), 52rem); }
[data-group-chat-app] .group-members-sidebar { display:flex; flex-direction:column; width:100%; min-height:0; }
[data-group-chat-app] .group-members-list { min-height:0; overflow-y:auto; }
[data-group-chat-app] .group-member-row { display:flex; align-items:center; gap:.75rem; padding:.75rem 1rem; border-bottom:1px solid rgba(148,163,184,.10); }
[data-group-chat-app] .group-member-avatar { width:2.5rem; height:2.5rem; object-fit:cover; }
[data-group-chat-app] .group-current-avatar { width:2.75rem; height:2.75rem; }
[data-group-chat-app] .group-chat-sender { margin:0 .65rem .25rem; color:var(--cz-body-color); font-size:.72rem; font-weight:600; opacity:.66; }
[data-group-chat-app] .chat-composer__row { grid-template-columns:minmax(0,1fr) auto; }
@media (min-width:992px) {
    [data-group-chat-app] .chat-app-layout { grid-template-columns:var(--group-member-width) minmax(0,1fr); }
}
@media (max-width:991.98px) {
    [data-group-chat-app] .chat-app-layout { height:clamp(36rem, calc(100dvh - 8rem), 50rem); }
}
</style>

<main class="content-wrapper">
    <section class="container-fluid chat-page"
        data-group-chat-app
        data-conversation-id="<?= (int)$group['id'] ?>"
        data-messages-url="<?= htmlSC($chat_group_messages_url) ?>"
        data-send-url="<?= htmlSC($chat_group_send_url) ?>"
        data-stream-url="<?= htmlSC($chat_group_stream_url) ?>"
        data-load-error-text="<?= htmlSC(return_translation('chat_group_load_error')) ?>"
        data-send-error-text="<?= htmlSC(return_translation('chat_group_send_error')) ?>"
        data-empty-text="<?= htmlSC(return_translation('chat_group_empty')) ?>"
    >
        <div class="chat-app-shell">
            <div class="chat-app-layout">
                <aside class="chat-layout-sidebar d-none d-lg-flex">
                    <div class="chat-sidebar group-members-sidebar">
                        <div class="chat-sidebar__head">
                            <div class="chat-sidebar__title-row">
                                <a href="<?= htmlSC($chat_index_url) ?>"
                                   class="chat-sidebar__close btn btn-outline-secondary rounded-circle flex-shrink-0"
                                   title="<?= htmlSC(return_translation('chat_group_back')) ?>"
                                   aria-label="<?= htmlSC(return_translation('chat_group_back')) ?>">
                                    <i class="ci-arrow-left" aria-hidden="true"></i>
                                </a>
                                <span class="chat-sidebar__title-icon" aria-hidden="true"><i class="ci-user"></i></span>
                                <div class="min-w-0">
                                    <div class="fw-semibold text-truncate"><?= print_translation('chat_group_members') ?></div>
                                    <div class="small text-body-secondary">
                                        <?= (int)$group['member_count'] ?> <?= print_translation('chat_group_members_short') ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="group-members-list">
                            <?php foreach ($members as $member): ?>
                                <div class="group-member-row">
                                    <img src="<?= get_user_avatar($member['avatar'] ?? null, 'sm') ?>"
                                         alt="<?= htmlSC($member['name']) ?>"
                                         class="group-member-avatar rounded-circle border flex-shrink-0">
                                    <div class="min-w-0 flex-grow-1">
                                        <div class="d-flex align-items-center gap-2 min-w-0">
                                            <strong class="d-block text-truncate"><?= htmlSC($member['name']) ?></strong>
                                            <?php if (($member['group_role'] ?? '') === 'owner'): ?>
                                                <i class="ci-star-filled text-warning flex-shrink-0" aria-hidden="true"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-body-secondary text-truncate">
                                            <?= htmlSC(get_user_role_label((string)($member['role'] ?? 'user'))) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </aside>

                <div class="chat-layout-main">
                    <div class="chat-thread">
                        <div class="chat-thread__head">
                            <div class="chat-thread__topline">
                                <div class="d-flex align-items-center gap-3 min-w-0">
                                    <a href="<?= htmlSC($chat_index_url) ?>"
                                       class="chat-thread__mobile-chats btn btn-outline-secondary d-lg-none flex-shrink-0"
                                       title="<?= htmlSC(return_translation('chat_group_back')) ?>"
                                       aria-label="<?= htmlSC(return_translation('chat_group_back')) ?>">
                                        <i class="ci-arrow-left" aria-hidden="true"></i>
                                    </a>
                                    <span class="rounded-circle border bg-body-tertiary d-inline-flex align-items-center justify-content-center group-current-avatar flex-shrink-0">
                                        <i class="ci-user" aria-hidden="true"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <strong class="d-block text-truncate"><?= htmlSC($group['title']) ?></strong>
                                        <div class="chat-current-meta small text-body-secondary">
                                            <?= (int)$group['member_count'] ?> <?= print_translation('chat_group_members_short') ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="chat-thread__toolbar">
                                    <span class="chat-thread__encryption badge text-body-emphasis bg-body-tertiary d-none d-md-inline-flex align-items-center gap-2">
                                        <i class="ci-lock" aria-hidden="true"></i>
                                        <?= print_translation('chat_encryption_badge') ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="chat-thread__body">
                            <div class="chat-messages-surface h-100" data-group-chat-messages>
                                <div class="chat-dialog-empty">
                                    <span class="chat-dialog-empty__icon" aria-hidden="true"><i class="ci-chat"></i></span>
                                    <p class="text-body-secondary mb-0"><?= print_translation('chat_loading') ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="chat-thread__composer border-top">
                            <form data-group-chat-form>
                                <?= get_csrf_field() ?>
                                <input type="hidden" name="conversation_id" value="<?= (int)$group['id'] ?>">
                                <div class="chat-composer">
                                    <div class="chat-composer__row">
                                        <textarea class="form-control border-0 shadow-none bg-transparent chat-composer__message"
                                                  name="message"
                                                  rows="1"
                                                  maxlength="2000"
                                                  placeholder="<?= htmlSC(return_translation('chat_message_placeholder')) ?>"
                                                  aria-label="<?= htmlSC(return_translation('chat_message_placeholder')) ?>"
                                                  data-group-chat-input></textarea>
                                        <button type="submit"
                                                class="chat-composer__submit btn btn-primary rounded-circle"
                                                title="<?= htmlSC(return_translation('chat_send_btn')) ?>"
                                                aria-label="<?= htmlSC(return_translation('chat_send_btn')) ?>">
                                            <i class="ci-send" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
