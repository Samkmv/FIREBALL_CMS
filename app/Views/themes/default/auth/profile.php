<?php
$isSettings = !empty($is_settings);
$settingsSection = $settings_section ?? 'information';
$roleSlug = (string)($user['role'] ?? 'user');
$roleBadgeClass = match ($roleSlug) {
    'creator' => 'text-bg-warning',
    'admin' => 'text-bg-info',
    default => 'text-bg-secondary',
};
$createdAt = !empty($user['created_at']) ? date('d.m.Y H:i', strtotime((string)$user['created_at'])) : '—';
$twoFactorEnabled = !empty($user['two_factor_enabled_at']) && !empty($user['two_factor_secret']);
$twoFactorSetup = is_array($two_factor_setup ?? null) ? $two_factor_setup : null;
$recoveryCodes = is_array($two_factor_recovery_codes ?? null) ? $two_factor_recovery_codes : [];
$pushStatus = is_array($push_status ?? null) ? $push_status : [];
$pushReady = !empty($pushStatus['global_enabled']) && !empty($pushStatus['vapid_ready']) && !empty($pushStatus['secure_context']);
$pushEnabled = $pushReady && !empty($pushStatus['user_enabled']) && (int)($pushStatus['active_subscriptions'] ?? 0) > 0;
$pushStatusKey = $pushEnabled
    ? 'auth_profile_push_status_enabled'
    : ($pushReady ? 'auth_profile_push_status_disabled' : 'auth_profile_push_status_unavailable');
$profileMenuItems = apply_filters('profile_menu', [], $user);
if (is_array($profileMenuItems)) {
    $profileMenuItems = array_values(array_filter($profileMenuItems, 'is_array'));
    usort($profileMenuItems, static fn(array $a, array $b): int => (int)($a['order'] ?? 100) <=> (int)($b['order'] ?? 100));
} else {
    $profileMenuItems = [];
}
?>

<section class="container py-4 py-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4 mb-lg-5">
        <div>
            <h1 class="h3 mb-2"><?= print_translation($isSettings ? 'auth_settings_title' : 'auth_profile_heading') ?></h1>
            <p class="text-body-secondary mb-0"><?= print_translation($isSettings ? 'auth_settings_subtitle' : 'auth_profile_overview_subtitle') ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if (check_admin()): ?>
                <a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin') ?>">
                    <i class="ci-layout"></i>
                    <span><?= print_translation('auth_profile_admin_link') ?></span>
                </a>
            <?php endif; ?>
            <form action="<?= base_href('/logout') ?>" method="post">
                <?= get_csrf_field() ?>
                <button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                    <i class="ci-log-out"></i>
                    <span><?= print_translation('auth_profile_logout') ?></span>
                </button>
            </form>
        </div>
    </div>

    <div class="row g-4 g-xl-5">
        <aside class="col-lg-4 col-xl-3">
            <div class="position-sticky" style="top: 7rem;">
                <div class="border rounded-5 p-4 mb-4">
                    <div class="text-center mb-4">
                        <div class="position-relative d-inline-flex align-items-center justify-content-center mb-3">
                            <img
                                src="<?= get_user_avatar($user['avatar'] ?? null, 'lg') ?>"
                                alt="<?= htmlSC($user['name']) ?>"
                                class="rounded-circle border object-fit-cover"
                                style="width: 120px; height: 120px;"
                            >
                        </div>
                        <div class="d-flex justify-content-center mb-3">
                            <span class="badge <?= $roleBadgeClass ?> rounded-pill px-3"><?= htmlSC(get_user_role_label($roleSlug)) ?></span>
                        </div>
                        <h2 class="h5 mb-1"><?= htmlSC($user['name']) ?><?= render_public_verified_badge($roleSlug) ?></h2>
                        <div class="text-body-secondary">@<?= htmlSC($user['login'] ?? '') ?></div>
                        <div class="d-flex justify-content-center align-items-center gap-2 text-body-secondary small mt-2 text-break">
                            <i class="ci-mail"></i>
                            <span><?= htmlSC($user['email']) ?></span>
                        </div>
                    </div>

                    <div class="vstack gap-3 small">
                        <div class="d-flex align-items-center gap-2">
                            <i class="ci-id-card text-body-tertiary fs-base"></i>
                            <span class="text-body-secondary"><?= print_translation('auth_profile_id') ?>:</span>
                            <span class="fw-medium ms-auto">#<?= (int)$user['id'] ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="ci-calendar text-body-tertiary fs-base"></i>
                            <span class="text-body-secondary"><?= print_translation('auth_profile_created_at') ?>:</span>
                            <span class="fw-medium ms-auto text-end"><?= htmlSC($createdAt) ?></span>
                        </div>
                    </div>
                </div>

                <nav class="vstack gap-2" aria-label="<?= print_translation('auth_settings_title') ?>">
                    <a class="btn <?= !$isSettings ? 'btn-dark' : 'btn-outline-secondary' ?> rounded-pill justify-content-start" href="<?= base_href('/profile') ?>" <?= !$isSettings ? 'aria-current="page"' : '' ?>>
                        <i class="ci-user me-2"></i><?= print_translation('auth_profile_overview') ?>
                    </a>
                    <a class="btn <?= $isSettings ? 'btn-dark' : 'btn-outline-secondary' ?> rounded-pill justify-content-start" href="<?= base_href('/profile/settings') ?>" <?= $isSettings ? 'aria-current="location"' : '' ?>>
                        <i class="ci-settings me-2"></i><?= print_translation('auth_settings_title') ?>
                    </a>
                </nav>
            </div>
        </aside>
        <div class="col-lg-8 col-xl-9">
            <?php if ($isSettings): ?>
                <nav class="overflow-x-auto mb-4" aria-label="<?= print_translation('auth_settings_title') ?>">
                    <ul class="nav nav-pills flex-nowrap gap-2 text-nowrap pb-1">
                        <?php foreach (['information', 'security', 'notifications'] as $section): ?>
                            <li class="nav-item">
                                <a class="nav-link <?= $settingsSection === $section ? 'active' : '' ?>" href="<?= base_href('/profile/settings?section=' . $section) ?>" <?= $settingsSection === $section ? 'aria-current="page"' : '' ?>><?= print_translation('auth_settings_' . $section) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
                <?php require __DIR__ . '/profile_' . $settingsSection . '.php'; ?>
            <?php else: ?>
                <?php require __DIR__ . '/profile_overview.php'; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
