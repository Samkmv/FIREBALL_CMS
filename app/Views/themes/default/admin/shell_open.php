<?php
$adminShellTitle = (string)($title ?? '');
$adminShellSubtitle = (string)($subtitle ?? '');
$adminShellActions = (string)($actions ?? '');
$adminShellContainerClass = (string)($container_class ?? 'container-fluid px-3 px-lg-4 px-xxl-5');
$adminShellSidebarColClass = (string)($sidebar_col_class ?? 'col-lg-4 col-xl-3');
$adminShellMainColClass = (string)($main_col_class ?? 'col-lg-8 col-xl-9');
?>

<div class="offcanvas offcanvas-start admin-shell-offcanvas d-lg-none" id="adminSidebar" tabindex="-1" aria-labelledby="adminSidebarLabel">
    <div class="offcanvas-header py-3">
        <h5 class="offcanvas-title" id="adminSidebarLabel"><?= print_translation('admin_dashboard_heading') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body pt-0 pb-4">
        <?= view()->renderPartial('admin/sidebar') ?>
    </div>
</div>

<section class="<?= htmlSC($adminShellContainerClass) ?> py-4 py-lg-5" data-admin-shell>
    <div class="row g-4 g-xl-5 align-items-start">
        <aside class="<?= htmlSC($adminShellSidebarColClass) ?> d-none d-lg-block">
            <div class="position-sticky" style="top: 7rem;" data-admin-shell-sidebar>
                <?= view()->renderPartial('admin/sidebar') ?>
            </div>
        </aside>

        <div class="<?= htmlSC($adminShellMainColClass) ?>">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4 mb-lg-5">
                <div>
                    <h1 class="h3 mb-1"><?= htmlSC($adminShellTitle) ?></h1>
                    <?php if ($adminShellSubtitle !== ''): ?>
                        <p class="text-body-secondary mb-0"><?= htmlSC($adminShellSubtitle) ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($adminShellActions !== ''): ?>
                    <div class="d-flex flex-wrap gap-2 justify-content-start justify-content-md-end">
                        <?= $adminShellActions ?>
                    </div>
                <?php endif; ?>
            </div>
            <button
                type="button"
                class="fixed-bottom z-sticky w-100 btn btn-lg btn-dark border-0 rounded-0 pb-4 d-lg-none admin-shell-mobile-toggle"
                data-bs-toggle="offcanvas"
                data-bs-target="#adminSidebar"
                aria-controls="adminSidebar"
                data-bs-theme="light"
            >
                <i class="ci-sidebar fs-base me-2"></i>
                <?= print_translation('admin_mobile_menu_btn') ?>
            </button>
