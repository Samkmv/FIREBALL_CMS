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
$pushReady = !empty($pushStatus['pwa_enabled']) && !empty($pushStatus['global_enabled']) && !empty($pushStatus['vapid_ready']) && !empty($pushStatus['secure_context']);
// PHP cannot know this browser's permission or endpoint. Never infer it from other devices.
$pushStatusKey = $pushReady ? 'auth_profile_push_status_checking' : 'auth_profile_push_status_unavailable';
$pushLabels = [];
foreach (['checking', 'enabled', 'disabled', 'unavailable', 'unsupported', 'permission', 'install', 'error'] as $state) {
    $pushLabels[$state] = return_translation('auth_profile_push_status_' . $state);
    $pushLabels[$state . 'Hint'] = return_translation('auth_profile_push_hint_' . $state);
}
$pushLabels['actionError'] = return_translation('auth_profile_push_action_error');
$pushLabels['retry'] = return_translation('auth_profile_push_retry');
$profileMenuItems = apply_filters('profile_menu', [], $user);
if (is_array($profileMenuItems)) {
    $profileMenuItems = array_values(array_filter($profileMenuItems, 'is_array'));
    usort($profileMenuItems, static fn(array $a, array $b): int => (int)($a['order'] ?? 100) <=> (int)($b['order'] ?? 100));
} else {
    $profileMenuItems = [];
}
$profileRoutes = [
    'information' => ['ci-settings', 'auth_settings_information', 'auth_profile_information_subtitle'],
    'security' => ['ci-shield', 'auth_settings_security', 'auth_profile_security_subtitle'],
    'notifications' => ['ci-bell', 'auth_settings_notifications', 'auth_profile_notifications_subtitle'],
    'sessions' => ['ci-monitor', 'account_sessions', 'auth_profile_sessions_subtitle'],
    'favorites' => ['ci-heart', 'account_favorites', 'auth_profile_favorites_subtitle'],
];
$profileHeadingKey = $isSettings ? ($profileRoutes[$settingsSection][1] ?? 'auth_settings_title') : 'auth_profile_heading';
$profileSubtitleKey = $isSettings ? ($profileRoutes[$settingsSection][2] ?? 'auth_settings_subtitle') : 'auth_profile_overview_subtitle';
$profileCurrentPath = rtrim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
$profileCoreMenu = [
    ['href' => base_href('/profile'), 'label' => return_translation('auth_profile_overview'), 'icon' => 'ci-user',
        'active' => !$isSettings && $profileCurrentPath === rtrim((string)parse_url(base_href('/profile'), PHP_URL_PATH), '/')],
];
foreach ($profileRoutes as $section => [$icon, $label]) {
    $profileCoreMenu[] = [
        'href' => base_href(in_array($section, ['sessions', 'favorites'], true) ? '/profile/' . $section : '/profile/settings?section=' . $section),
        'label' => return_translation($label),
        'icon' => $icon,
        // Settings share the same path; their query section determines the active link.
        'active' => $isSettings && $settingsSection === $section,
    ];
}
$profileMenuItems = array_merge($profileCoreMenu, $profileMenuItems);
$profileMenuItems[] = ['href' => base_href('/chat'), 'label' => return_translation('tpl_auth_chat'), 'icon' => 'ci-chat'];
$profileSubscriptionsAvailable = !$isSettings && (bool)apply_filters('profile_subscriptions_available', false);
$profileSubscriptions = $profileSubscriptionsAvailable ? apply_filters('profile_subscriptions', [], $user) : [];
$profileSubscriptions = is_array($profileSubscriptions) ? array_filter($profileSubscriptions, 'is_array') : [];
?>
<section class="container profile-layout" data-pwa-push-labels="<?= htmlSC(json_encode($pushLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
    <aside class="profile-sidebar">
        <div class="profile-identity">
            <img class="profile-avatar" src="<?= get_user_avatar($user['avatar'] ?? null, 'lg') ?>" alt="<?= htmlSC($user['name']) ?>" width="92" height="92">
            <span class="badge <?= $roleBadgeClass ?> rounded-pill px-3"><?= htmlSC(get_user_role_label($roleSlug)) ?></span>
            <h2 class="h6 mb-1 mt-2 text-break"><?= htmlSC($user['name']) ?><?= render_public_verified_badge($roleSlug) ?></h2>
            <p class="text-body-secondary mb-1 text-break">@<?= htmlSC($user['login'] ?? '') ?></p>
            <p class="profile-email text-body-secondary mb-0"><i class="ci-mail" aria-hidden="true"></i><span><?= htmlSC($user['email']) ?></span></p>
        </div>
        <dl class="profile-meta">
            <div><dt><i class="ci-id-card" aria-hidden="true"></i><?= print_translation('auth_profile_id') ?>:</dt><dd>#<?= (int)$user['id'] ?></dd></div>
            <div class="profile-meta-registration"><dt title="<?= htmlSC(return_translation('auth_profile_created_at')) ?>"><i class="ci-calendar" aria-hidden="true"></i><span class="profile-meta-label" aria-hidden="true"><?= print_translation('auth_profile_created_at_short') ?>:</span><span class="visually-hidden"><?= print_translation('auth_profile_created_at') ?>:</span></dt><dd><?= htmlSC($createdAt) ?></dd></div>
        </dl>
        <nav class="profile-nav-services" aria-label="<?= print_translation('auth_profile_menu') ?>">
            <h3><?= print_translation('auth_profile_menu') ?></h3>
            <ul class="nav nav-tabs flex-column gap-1" data-profile-route-nav>
                <?php foreach ($profileMenuItems as $item): ?>
                    <?php
                    if (empty($item['href']) || empty($item['label'])) { continue; }
                    $active = (bool)($item['active'] ?? ($profileCurrentPath !== '' && $profileCurrentPath === rtrim((string)parse_url((string)$item['href'], PHP_URL_PATH), '/')));
                    ?>
                    <li class="nav-item">
                        <a class="nav-link d-flex align-items-center gap-2<?= $active ? ' active' : '' ?>" href="<?= htmlSC($item['href']) ?>"<?= $active ? ' aria-current="page"' : '' ?>><i class="<?= htmlSC($item['icon'] ?? 'ci-chevron-right') ?> fs-base flex-shrink-0" aria-hidden="true"></i><span><?= htmlSC($item['label']) ?></span></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <nav class="profile-nav-footer" aria-label="<?= print_translation('auth_profile_heading') ?>">
            <ul class="nav nav-tabs flex-column gap-1">
                <?php if (check_admin()): ?><li class="nav-item"><a class="nav-link d-flex align-items-center gap-2" href="<?= base_href('/admin') ?>"><i class="ci-layout fs-base flex-shrink-0" aria-hidden="true"></i><span><?= print_translation('auth_profile_admin_link') ?></span></a></li><?php endif; ?>
                <li class="nav-item">
                    <form action="<?= base_href('/logout') ?>" method="post"><?= get_csrf_field() ?><button class="nav-link d-flex align-items-center gap-2 profile-logout" type="submit"><i class="ci-log-out fs-base flex-shrink-0" aria-hidden="true"></i><span><?= print_translation('auth_profile_logout') ?></span></button></form>
                </li>
            </ul>
        </nav>
    </aside>
    <div class="profile-content">
        <header class="profile-heading">
            <h1 class="h3 mb-1"><?= print_translation($profileHeadingKey) ?></h1>
            <p class="text-body-secondary mb-0"><?= print_translation($profileSubtitleKey) ?></p>
        </header>
        <?php if ($isSettings): ?>
            <?php require __DIR__ . '/profile_' . $settingsSection . '.php'; ?>
        <?php else: ?>
            <?php require __DIR__ . '/profile_overview.php'; ?>
        <?php endif; ?>
    </div>
</section>
