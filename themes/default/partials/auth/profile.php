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
$profileSubscriptionsAvailable = !$isSettings && (bool)apply_filters('profile_subscriptions_available', false);
$profileSubscriptions = $profileSubscriptionsAvailable ? apply_filters('profile_subscriptions', [], $user) : [];
$profileSubscriptions = is_array($profileSubscriptions) ? array_filter($profileSubscriptions, 'is_array') : [];
?>
<section class="container profile-layout">
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
            <div><dt><i class="ci-calendar" aria-hidden="true"></i><?= print_translation('auth_profile_created_at') ?>:</dt><dd><?= htmlSC($createdAt) ?></dd></div>
        </dl>
        <nav class="profile-nav" aria-label="<?= print_translation('auth_profile_heading') ?>">
            <a href="<?= base_href('/profile') ?>" <?= !$isSettings ? 'aria-current="page"' : '' ?>><i class="ci-user" aria-hidden="true"></i><?= print_translation('auth_profile_overview') ?></a>
            <?php foreach (['information' => ['ci-settings', 'auth_settings_title'], 'security' => ['ci-shield', 'auth_settings_security'], 'notifications' => ['ci-bell', 'auth_settings_notifications']] as $section => [$icon, $label]): ?>
                <a href="<?= base_href('/profile/settings?section=' . $section) ?>" <?= $isSettings && $settingsSection === $section ? 'aria-current="page"' : '' ?>><i class="<?= $icon ?>" aria-hidden="true"></i><?= print_translation($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <nav class="profile-nav profile-nav-services" aria-label="<?= print_translation('auth_profile_services') ?>">
            <h3><?= print_translation('auth_profile_services') ?></h3>
            <?php foreach ($profileMenuItems as $item): ?>
                <?php if (empty($item['href']) || empty($item['label'])) { continue; } ?>
                <a href="<?= htmlSC($item['href']) ?>"><i class="<?= htmlSC($item['icon'] ?? 'ci-chevron-right') ?>" aria-hidden="true"></i><span><?= htmlSC($item['label']) ?></span></a>
            <?php endforeach; ?>
            <a href="<?= base_href('/chat') ?>"><i class="ci-chat" aria-hidden="true"></i><?= print_translation('tpl_auth_chat') ?></a>
        </nav>
        <div class="profile-nav profile-nav-footer">
            <?php if (check_admin()): ?><a href="<?= base_href('/admin') ?>"><i class="ci-layout" aria-hidden="true"></i><?= print_translation('auth_profile_admin_link') ?></a><?php endif; ?>
            <form action="<?= base_href('/logout') ?>" method="post"><?= get_csrf_field() ?><button type="submit"><i class="ci-log-out" aria-hidden="true"></i><?= print_translation('auth_profile_logout') ?></button></form>
        </div>
    </aside>
    <div class="profile-content">
        <header class="profile-heading">
            <h1 class="h3 mb-1"><?= print_translation($isSettings ? 'auth_settings_title' : 'auth_profile_heading') ?></h1>
            <p class="text-body-secondary mb-0"><?= print_translation($isSettings ? 'auth_settings_subtitle' : 'auth_profile_overview_subtitle') ?></p>
        </header>
        <?php if ($isSettings): ?>
            <?= $this->partial('auth/profile_' . $settingsSection, get_defined_vars()) ?>
        <?php else: ?>
            <?= $this->partial('auth/profile_overview', get_defined_vars()) ?>
        <?php endif; ?>
    </div>
</section>
