<?php
$adminShellTitle = (string)($title ?? '');
$adminShellSubtitle = (string)($subtitle ?? '');
$adminShellActions = (string)($actions ?? '');
$adminShellContainerClass = (string)($container_class ?? '');
$adminShellSidebarColClass = (string)($sidebar_col_class ?? '');
$adminShellMainColClass = (string)($main_col_class ?? '');
?>

<a class="fb-skip-link" href="#fb-admin-content"><?= print_translation('admin_ui_skip_content') ?></a>

<div class="fb-admin" data-fb-admin data-admin-shell>
    <aside class="fb-sidebar d-none d-lg-flex" data-fb-sidebar aria-label="<?= htmlSC(return_translation('admin_mobile_menu_btn')) ?>">
        <?= view()->renderPartial('admin/sidebar', ['variant' => 'desktop']) ?>
    </aside>

    <div
        class="offcanvas offcanvas-start fb-sidebar-drawer d-lg-none"
        id="adminSidebar"
        tabindex="-1"
        aria-labelledby="adminSidebarLabel"
        data-bs-theme="dark"
    >
        <?= view()->renderPartial('admin/sidebar', ['variant' => 'mobile']) ?>
    </div>

    <div class="fb-admin-main">
        <?= view()->renderPartial('admin/topbar') ?>

        <main class="fb-content" id="fb-admin-content" tabindex="-1">
            <div class="fb-alert-root" data-fb-alert-root>
                <?php get_alerts(); ?>
            </div>

            <header class="fb-page-header">
                <div class="fb-page-heading">
                    <h1 class="fb-page-title"><?= htmlSC($adminShellTitle) ?></h1>
                    <?php if ($adminShellSubtitle !== ''): ?>
                        <p class="fb-page-subtitle"><?= htmlSC($adminShellSubtitle) ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($adminShellActions !== ''): ?>
                    <div class="fb-page-actions">
                        <?= $adminShellActions ?>
                    </div>
                <?php endif; ?>
            </header>

            <div class="fb-page-content <?= htmlSC($adminShellContainerClass) ?>" data-fb-page-content>
