<?php
$adminShellTitle = (string)($title ?? '');
$adminShellSubtitle = (string)($subtitle ?? '');
$adminShellActions = (string)($actions ?? '');
$adminShellContainerClass = (string)($container_class ?? 'container-fluid px-3 px-lg-4 px-xxl-5');
$adminShellSidebarColClass = (string)($sidebar_col_class ?? 'col-lg-4 col-xl-3');
$adminShellMainColClass = (string)($main_col_class ?? 'col-lg-8 col-xl-9');
$currentUser = get_user() ?: [];
$roleSlug = (string)($currentUser['role'] ?? 'user');
$roleBadgeClass = match ($roleSlug) {
    'creator' => 'text-bg-warning',
    'admin' => 'text-bg-info',
    default => 'text-bg-secondary',
};
?>

<section class="<?= htmlSC($adminShellContainerClass) ?> py-4 py-lg-5" data-admin-shell>
    <div class="row g-4 g-xl-5 align-items-start">
        <aside class="<?= htmlSC($adminShellSidebarColClass) ?>">
            <div class="position-sticky" style="top: 7rem;" data-admin-shell-sidebar>
                <div class="border rounded-5 p-4 mb-4 admin-shell-profile-card">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img
                            src="<?= get_user_avatar($currentUser['avatar'] ?? null, 'sm') ?>"
                            alt="<?= htmlSC((string)($currentUser['name'] ?? '')) ?>"
                            class="rounded-circle border object-fit-cover flex-shrink-0"
                            style="width: 56px; height: 56px;"
                        >
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate"><?= htmlSC((string)($currentUser['name'] ?? '')) ?></div>
                            <div class="small text-body-secondary text-truncate">@<?= htmlSC((string)($currentUser['login'] ?? '')) ?></div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <span class="badge <?= $roleBadgeClass ?> rounded-pill px-3"><?= htmlSC(get_user_role_label($roleSlug)) ?></span>
                        <a class="btn btn-sm btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/profile') ?>">
                            <i class="ci-user"></i>
                            <span><?= print_translation('tpl_auth_profile') ?></span>
                        </a>
                    </div>
                </div>

                <?= view()->renderPartial('admin/nav') ?>

                <div class="border rounded-5 p-3 p-xl-4 mt-4 admin-shell-quicklinks">
                    <div class="fw-bold mb-3"><?= print_translation('footer_heading_admin') ?></div>
                    <div class="d-grid gap-2">
                        <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center justify-content-center gap-2" href="<?= base_href('/') ?>">
                            <i class="ci-home"></i>
                            <span><?= print_translation('tpl_menu_nav_index') ?></span>
                        </a>
                        <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center justify-content-center gap-2" href="<?= base_href('/chat') ?>">
                            <i class="ci-chat"></i>
                            <span><?= print_translation('tpl_auth_chat') ?></span>
                        </a>
                    </div>
                </div>
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
