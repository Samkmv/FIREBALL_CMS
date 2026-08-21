<?php
$currentUser = get_user() ?: [];
$currentUserAvatar = get_user_avatar($currentUser['avatar'] ?? null, 'sm');
$currentAdminLocale = current_locale();
$adminLanguageSwitchPath = uri_without_lang() ?: '/';
?>

<header class="fb-topbar">
    <div class="fb-topbar-left">
        <button
            type="button"
            class="fb-icon-button fb-topbar-menu d-lg-none"
            data-bs-toggle="offcanvas"
            data-bs-target="#adminSidebar"
            aria-controls="adminSidebar"
            aria-label="<?= htmlSC(return_translation('admin_mobile_menu_btn')) ?>"
        >
            <i class="ci-menu" aria-hidden="true"></i>
        </button>

        <a class="fb-topbar-brand d-lg-none" href="<?= base_href('/admin') ?>">
            <span class="fb-brand-mark" aria-hidden="true"><img src="<?= base_url('/assets/default/icons/fireball-cms.svg') ?>" alt=""></span>
            <span>FIREBALL</span>
        </a>
    </div>

    <button
        type="button"
        class="fb-global-search fb-topbar-action"
        data-fb-command-open
        aria-label="<?= htmlSC(return_translation('admin_ui_search_placeholder')) ?>"
        aria-keyshortcuts="Control+K Meta+K"
    >
        <i class="ci-search" aria-hidden="true"></i>
        <span><?= print_translation('admin_ui_search_placeholder') ?></span>
        <kbd><?= print_translation('admin_ui_command_shortcut') ?></kbd>
    </button>

    <div class="fb-topbar-actions">
        <div class="dropdown theme-switcher" data-theme-label="<?= htmlSC(return_translation('admin_ui_theme')) ?>">
            <button
                type="button"
                class="fb-icon-button fb-topbar-action theme-icon-active"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                aria-label="<?= htmlSC(return_translation('admin_ui_theme')) ?>"
            >
                <i class="ci-sun" aria-hidden="true"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end fb-dropdown-menu">
                <button class="dropdown-item d-flex align-items-center gap-2" type="button" data-bs-theme-value="light" aria-pressed="false">
                    <span class="theme-icon"><i class="ci-sun" aria-hidden="true"></i></span>
                    <?= print_translation('admin_ui_theme_light') ?>
                </button>
                <button class="dropdown-item d-flex align-items-center gap-2" type="button" data-bs-theme-value="dark" aria-pressed="false">
                    <span class="theme-icon"><i class="ci-moon" aria-hidden="true"></i></span>
                    <?= print_translation('admin_ui_theme_dark') ?>
                </button>
                <button class="dropdown-item d-flex align-items-center gap-2" type="button" data-bs-theme-value="auto" aria-pressed="false">
                    <span class="theme-icon"><i class="ci-monitor" aria-hidden="true"></i></span>
                    <?= print_translation('admin_ui_theme_system') ?>
                </button>
            </div>
        </div>

        <?php if (MULTILANGS && count(LANGS) > 1): ?>
            <div class="dropdown fb-language-switcher">
                <button
                    type="button"
                    class="fb-icon-button fb-topbar-action"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                    aria-label="<?= htmlSC(return_translation('admin_ui_language')) ?>"
                    title="<?= htmlSC(return_translation('admin_ui_language')) ?>"
                >
                    <i class="ci-globe" aria-hidden="true"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end fb-dropdown-menu fb-language-menu">
                    <div class="fb-dropdown-heading fb-language-menu-heading">
                        <strong><?= print_translation('admin_ui_language') ?></strong>
                        <span><?= htmlSC(strtoupper($currentAdminLocale)) ?></span>
                    </div>
                    <?php foreach (LANGS as $localeCode => $locale): ?>
                        <?php
                        $localeCode = (string)$localeCode;
                        $isCurrentLocale = $localeCode === $currentAdminLocale;
                        $localeTitle = (string)($locale['title'] ?? strtoupper($localeCode));
                        ?>
                        <a
                            class="dropdown-item d-flex align-items-center gap-2<?= $isCurrentLocale ? ' active' : '' ?>"
                            href="<?= htmlSC(locale_switch_url($localeCode, $adminLanguageSwitchPath)) ?>"
                            lang="<?= htmlSC($localeCode) ?>"
                            hreflang="<?= htmlSC($localeCode) ?>"
                            <?= $isCurrentLocale ? 'aria-current="true"' : '' ?>
                        >
                            <span class="fb-language-code" aria-hidden="true"><?= htmlSC(strtoupper($localeCode)) ?></span>
                            <span><?= htmlSC($localeTitle) ?></span>
                            <?php if ($isCurrentLocale): ?>
                                <i class="ci-check ms-auto" aria-hidden="true"></i>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div
            class="dropdown"
            data-notifications-center
            data-feed-url="<?= base_href('/notifications/feed') ?>"
            data-read-url="<?= base_href('/notifications/read') ?>"
            data-clear-url="<?= base_href('/notifications/clear') ?>"
            data-clear-confirm="<?= htmlSC(return_translation('notification_clear_confirm')) ?>"
            data-clear-success="<?= htmlSC(return_translation('notification_cleared')) ?>"
            data-empty-text="<?= htmlSC(return_translation('notification_empty')) ?>"
            data-chat-source-label="<?= htmlSC(return_translation('notification_source_chat')) ?>"
        >
            <button
                type="button"
                class="fb-icon-button fb-topbar-action position-relative"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                aria-label="<?= htmlSC(return_translation('tpl_notifications')) ?>"
                data-fb-notifications-toggle
            >
                <i class="ci-bell" aria-hidden="true"></i>
                <span class="fb-notification-badge d-none" data-notifications-badge>0</span>
            </button>
            <div class="dropdown-menu dropdown-menu-end fb-notifications-menu">
                <div class="fb-dropdown-heading fb-notifications-heading">
                    <strong><?= print_translation('tpl_notifications') ?></strong>
                    <button type="button" class="fb-notifications-clear d-none" data-notifications-clear>
                        <i class="ci-trash" aria-hidden="true"></i>
                        <span><?= print_translation('notification_clear_all') ?></span>
                    </button>
                </div>
                <div class="list-group list-group-flush" data-notifications-list>
                    <div class="px-3 py-3 text-body-secondary small"><?= print_translation('notification_loading') ?></div>
                </div>
            </div>
        </div>

        <div class="dropdown fb-profile-dropdown">
            <button
                type="button"
                class="fb-profile-trigger"
                data-bs-toggle="dropdown"
                aria-expanded="false"
                aria-label="<?= htmlSC(return_translation('tpl_auth_account')) ?>"
            >
                <img src="<?= $currentUserAvatar ?>" alt="">
                <span class="fb-profile-trigger-copy">
                    <strong><?= htmlSC((string)($currentUser['name'] ?? '')) ?></strong>
                    <span><?= htmlSC(get_user_role_label((string)($currentUser['role'] ?? 'user'))) ?></span>
                </span>
                <i class="ci-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end fb-dropdown-menu fb-profile-menu">
                <div class="fb-profile-menu-head">
                    <img src="<?= $currentUserAvatar ?>" alt="">
                    <div>
                        <strong><?= htmlSC((string)($currentUser['name'] ?? '')) ?></strong>
                        <span>@<?= htmlSC((string)($currentUser['login'] ?? '')) ?></span>
                    </div>
                </div>
                <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/') ?>">
                    <i class="ci-home" aria-hidden="true"></i><?= print_translation('tpl_menu_nav_index') ?>
                </a>
                <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/profile') ?>">
                    <i class="ci-user" aria-hidden="true"></i><?= print_translation('tpl_auth_profile') ?>
                </a>
                <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/chat') ?>">
                    <i class="ci-chat" aria-hidden="true"></i><?= print_translation('tpl_auth_chat') ?>
                </a>
                <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/admin/settings') ?>">
                    <i class="ci-settings" aria-hidden="true"></i><?= print_translation('admin_nav_settings') ?>
                </a>
                <div class="dropdown-divider"></div>
                <form action="<?= base_href('/logout') ?>" method="post">
                    <?= get_csrf_field() ?>
                    <button class="dropdown-item d-flex align-items-center gap-2 text-danger" type="submit">
                        <i class="ci-log-out" aria-hidden="true"></i><?= print_translation('tpl_auth_logout') ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
