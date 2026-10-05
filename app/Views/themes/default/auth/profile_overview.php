<?php if ($profileSubscriptionsAvailable): ?>
<section class="profile-panel">
    <h2><?= print_translation('auth_profile_active_subscriptions') ?></h2>
    <p class="profile-panel-intro"><?= print_translation('auth_profile_subscriptions_hint') ?></p>
    <?php if ($profileSubscriptions === []): ?>
        <div class="profile-empty text-body-secondary"><i class="ci-award" aria-hidden="true"></i><p class="mb-0"><?= print_translation('auth_profile_no_subscriptions') ?></p></div>
    <?php else: ?>
        <div class="vstack gap-3">
            <?php foreach ($profileSubscriptions as $subscription): ?>
                <a class="profile-subscription profile-service-card btn-outline-secondary" href="<?= htmlSC($subscription['href']) ?>">
                    <span class="profile-subscription-icon"><i class="<?= htmlSC($subscription['icon'] ?? 'ci-award') ?>" aria-hidden="true"></i></span>
                    <div class="profile-subscription-body">
                        <div class="profile-card-title"><h3><?= htmlSC($subscription['name']) ?></h3><span class="badge rounded-pill <?= ($subscription['status'] ?? '') === 'active' ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= htmlSC($subscription['status_label']) ?></span></div>
                        <?php if (!empty($subscription['description'])): ?><p class="profile-subscription-description"><?= htmlSC($subscription['description']) ?></p><?php endif; ?>
                        <div class="profile-subscription-meta">
                            <span><i class="ci-calendar" aria-hidden="true"></i><?= print_translation('auth_profile_valid_until') ?>: <?= !empty($subscription['ends_at']) ? htmlSC(date('d.m.Y', strtotime($subscription['ends_at']))) : print_translation('auth_profile_unlimited') ?></span>
                            <span><i class="ci-tag" aria-hidden="true"></i><?= print_translation('auth_profile_plan') ?>: <?= htmlSC($subscription['plan_name'] ?? $subscription['name']) ?></span>
                        </div>
                    </div>
                    <i class="ci-chevron-right ms-auto" aria-hidden="true"></i>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
<section class="profile-panel">
    <h2><?= print_translation('auth_profile_account') ?></h2>
    <p class="profile-panel-intro"><?= print_translation('auth_profile_account_hint') ?></p>
    <div class="profile-account-cards">
        <section class="profile-account-card">
            <span class="profile-status-icon"><i class="ci-shield" aria-hidden="true"></i></span>
            <div class="profile-status-body">
                <div class="profile-card-title"><h3><?= print_translation('auth_settings_security') ?></h3><span class="badge rounded-pill <?= $twoFactorEnabled ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= print_translation($twoFactorEnabled ? 'auth_two_factor_status_enabled' : 'auth_two_factor_status_disabled') ?></span></div>
                <h4><?= print_translation('auth_two_factor_heading') ?></h4>
                <p><?= print_translation($twoFactorEnabled ? 'auth_two_factor_enabled_hint' : 'auth_two_factor_subtitle') ?></p>
                <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= base_href('/profile/settings?section=security') ?>"><?= print_translation('auth_profile_manage_security') ?><i class="ci-chevron-right ms-2" aria-hidden="true"></i></a>
            </div>
        </section>
        <section class="profile-account-card">
            <span class="profile-status-icon"><i class="ci-bell" aria-hidden="true"></i></span>
            <div class="profile-status-body">
                <div class="profile-card-title"><h3><?= print_translation('auth_settings_notifications') ?></h3><span class="badge rounded-pill text-bg-secondary" data-pwa-push-status role="status" aria-live="polite"><?= print_translation($pushStatusKey) ?></span></div>
                <h4><?= print_translation('auth_profile_push_heading') ?></h4>
                <p data-pwa-push-status-hint><?= print_translation($pushReady ? 'auth_profile_push_hint_checking' : 'auth_profile_push_hint_unavailable') ?></p>
                <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= base_href('/profile/settings?section=notifications') ?>"><?= print_translation('auth_profile_manage_notifications') ?><i class="ci-chevron-right ms-2" aria-hidden="true"></i></a>
            </div>
        </section>
    </div>
</section>
