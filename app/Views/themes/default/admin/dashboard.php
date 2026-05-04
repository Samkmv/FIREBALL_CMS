<?php
$updateCenter = $update_center ?? [];
$updateLocal = $updateCenter['local'] ?? [];
$installedVersionLabel = (string)($updateLocal['version'] ?? ($engine_release['version'] ?? '0.0.0'));

echo view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_dashboard_heading'),
    'subtitle' => return_translation('admin_dashboard_subtitle'),
    'actions' => '',
]);
?>

    <div class="row g-3">
        <div class="col-md-6 col-xl-3">
            <div class="border rounded-5 p-4 h-100 admin-shell-profile-card">
                <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2"><i class="ci-file-text"></i><?= print_translation('admin_stat_posts') ?></div>
                <div class="display-6 mb-0"><?= (int)$stats['posts'] ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="border rounded-5 p-4 h-100 admin-shell-profile-card">
                <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2"><i class="ci-mail"></i><?= print_translation('admin_stat_contacts') ?></div>
                <div class="display-6 mb-0"><?= (int)($stats['contact_requests'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="border rounded-5 p-4 h-100 admin-shell-profile-card">
                <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2"><i class="ci-folder"></i><?= print_translation('admin_stat_categories') ?></div>
                <div class="display-6 mb-0"><?= (int)$stats['categories'] ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="border rounded-5 p-4 h-100 admin-shell-profile-card">
                <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2"><i class="ci-user"></i><?= print_translation('admin_stat_users') ?></div>
                <div class="display-6 mb-0"><?= (int)$stats['users'] ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="border rounded-5 p-4 h-100 admin-shell-profile-card">
                <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2"><i class="ci-activity"></i><?= print_translation('admin_stat_visits') ?></div>
                <div class="display-6 mb-0"><?= (int)($stats['site_visits'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="border rounded-5 p-4 h-100 admin-shell-profile-card">
                <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2"><i class="ci-refresh-cw"></i><?= print_translation('admin_update_current_version') ?></div>
                <div class="display-6 mb-0"><?= htmlSC($installedVersionLabel) ?></div>
            </div>
        </div>
    </div>
    <div class="row g-3 mt-1">
        <div class="col-md-6 col-xl-4">
            <a class="border rounded-5 p-4 h-100 d-flex align-items-start gap-3 text-decoration-none text-reset admin-shell-profile-card" href="<?= base_href('/admin/posts/create') ?>">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-dark text-white flex-shrink-0" style="width: 3rem; height: 3rem;"><i class="ci-plus"></i></span>
                <span>
                    <span class="d-block fw-semibold mb-1"><?= print_translation('admin_posts_create') ?></span>
                    <span class="d-block text-body-secondary small"><?= print_translation('admin_posts_subtitle') ?></span>
                </span>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a class="border rounded-5 p-4 h-100 d-flex align-items-start gap-3 text-decoration-none text-reset admin-shell-profile-card" href="<?= base_href('/admin/settings') ?>">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-body-tertiary flex-shrink-0" style="width: 3rem; height: 3rem;"><i class="ci-settings"></i></span>
                <span>
                    <span class="d-block fw-semibold mb-1"><?= print_translation('admin_nav_settings') ?></span>
                    <span class="d-block text-body-secondary small"><?= print_translation('admin_settings_subtitle') ?></span>
                </span>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a class="border rounded-5 p-4 h-100 d-flex align-items-start gap-3 text-decoration-none text-reset admin-shell-profile-card" href="<?= base_href('/admin/updates') ?>">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-body-tertiary flex-shrink-0" style="width: 3rem; height: 3rem;"><i class="ci-refresh-cw"></i></span>
                <span>
                    <span class="d-block fw-semibold mb-1"><?= print_translation('admin_nav_updates') ?></span>
                    <span class="d-block text-body-secondary small"><?= print_translation('admin_update_center_subtitle') ?></span>
                </span>
            </a>
        </div>
    </div>
    <?= view()->renderPartial('admin/shell_close') ?>
