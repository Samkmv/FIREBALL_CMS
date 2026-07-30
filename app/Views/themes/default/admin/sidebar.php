<?php
$currentUser = get_user() ?: [];
$sidebarVariant = (string)($variant ?? 'desktop');
$isMobileSidebar = $sidebarVariant === 'mobile';
$roleSlug = (string)($currentUser['role'] ?? 'user');
?>

<div class="fb-sidebar-inner">
    <div class="fb-sidebar-head">
        <a class="fb-brand" href="<?= base_href('/admin') ?>" aria-label="<?= htmlSC(return_translation('admin_dashboard_heading')) ?>">
            <span class="fb-brand-mark" aria-hidden="true"><i class="ci-zap"></i></span>
            <span class="fb-brand-copy">
                <strong>FIREBALL</strong>
                <span>CMS</span>
            </span>
        </a>

        <?php if ($isMobileSidebar): ?>
            <button
                type="button"
                class="fb-icon-button fb-sidebar-close"
                data-bs-dismiss="offcanvas"
                aria-label="<?= htmlSC(return_translation('admin_btn_close')) ?>"
            >
                <i class="ci-close" aria-hidden="true"></i>
            </button>
        <?php else: ?>
            <button
                type="button"
                class="fb-icon-button fb-sidebar-collapse"
                data-fb-sidebar-toggle
                data-label-collapse="<?= htmlSC(return_translation('admin_ui_sidebar_collapse')) ?>"
                data-label-expand="<?= htmlSC(return_translation('admin_ui_sidebar_expand')) ?>"
                aria-expanded="true"
                aria-label="<?= htmlSC(return_translation('admin_ui_sidebar_collapse')) ?>"
                title="<?= htmlSC(return_translation('admin_ui_sidebar_collapse')) ?>"
            >
                <i class="ci-chevron-left" aria-hidden="true"></i>
            </button>
        <?php endif; ?>
    </div>

    <div
        class="fb-sidebar-scroll"
        data-fb-sidebar-scroll
        data-simplebar
        data-simplebar-auto-hide="false"
        data-simplebar-force-visible="y"
        data-simplebar-scrollbar-min-size="44"
        data-simplebar-scrollbar-max-size="180"
    >
        <?= view()->renderPartial('admin/nav', ['variant' => $sidebarVariant]) ?>
    </div>

    <div class="fb-sidebar-footer">
        <a
            class="fb-sidebar-user"
            href="<?= base_href('/profile') ?>"
            aria-label="<?= htmlSC(return_translation('tpl_auth_profile')) ?>"
            title="<?= htmlSC((string)($currentUser['name'] ?? '')) ?>"
        >
            <img
                class="fb-sidebar-user-avatar"
                src="<?= get_user_avatar($currentUser['avatar'] ?? null, 'sm') ?>"
                alt=""
            >
            <span class="fb-sidebar-user-copy">
                <strong><?= htmlSC((string)($currentUser['name'] ?? '')) ?></strong>
                <span><?= htmlSC(get_user_role_label($roleSlug)) ?></span>
            </span>
            <i class="ci-chevron-right fb-sidebar-user-arrow" aria-hidden="true"></i>
        </a>

        <div class="fb-sidebar-footer-actions">
            <a
                class="fb-sidebar-footer-link"
                href="<?= base_href('/') ?>"
                aria-label="<?= htmlSC(return_translation('tpl_menu_nav_index')) ?>"
                title="<?= htmlSC(return_translation('tpl_menu_nav_index')) ?>"
            >
                <i class="ci-home" aria-hidden="true"></i>
                <span><?= print_translation('tpl_menu_nav_index') ?></span>
            </a>
            <a
                class="fb-sidebar-footer-link"
                href="<?= base_href('/chat') ?>"
                aria-label="<?= htmlSC(return_translation('tpl_auth_chat')) ?>"
                title="<?= htmlSC(return_translation('tpl_auth_chat')) ?>"
            >
                <i class="ci-chat" aria-hidden="true"></i>
                <span><?= print_translation('tpl_auth_chat') ?></span>
            </a>
            <form action="<?= base_href('/logout') ?>" method="post">
                <?= get_csrf_field() ?>
                <button
                    class="fb-sidebar-footer-link"
                    type="submit"
                    aria-label="<?= htmlSC(return_translation('tpl_auth_logout')) ?>"
                    title="<?= htmlSC(return_translation('tpl_auth_logout')) ?>"
                >
                    <i class="ci-log-out" aria-hidden="true"></i>
                    <span><?= print_translation('tpl_auth_logout') ?></span>
                </button>
            </form>
        </div>
    </div>
</div>
