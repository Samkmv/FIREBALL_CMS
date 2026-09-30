<div class="border rounded-5 p-4 p-md-5 mb-4">
    <h2 class="h5 mb-2"><?= print_translation('auth_profile_services') ?></h2>
    <p class="text-body-secondary mb-4"><?= print_translation('auth_profile_services_hint') ?></p>
    <div class="row g-3">
        <?php foreach ($profileMenuItems as $item): ?>
            <?php
            $href = trim((string)($item['href'] ?? ''));
            $label = trim((string)($item['label'] ?? ''));
            if ($href === '' || $label === '') { continue; }
            ?>
            <div class="col-md-6">
                <a class="profile-service-card btn-outline-secondary border rounded-4 p-4 h-100 d-flex align-items-center gap-3 text-body text-decoration-none" href="<?= htmlSC($href) ?>">
                    <i class="<?= htmlSC($item['icon'] ?? 'ci-chevron-right') ?> fs-4 text-body-secondary" aria-hidden="true"></i>
                    <span class="fw-medium"><?= htmlSC($label) ?></span>
                    <i class="ci-chevron-right ms-auto" aria-hidden="true"></i>
                </a>
            </div>
        <?php endforeach; ?>
        <div class="col-md-6">
            <a class="profile-service-card btn-outline-secondary border rounded-4 p-4 h-100 d-flex align-items-center gap-3 text-body text-decoration-none" href="<?= base_href('/chat') ?>">
                <i class="ci-chat fs-4 text-body-secondary" aria-hidden="true"></i>
                <span class="fw-medium"><?= print_translation('tpl_auth_chat') ?></span>
                <i class="ci-chevron-right ms-auto" aria-hidden="true"></i>
            </a>
        </div>
    </div>
</div>
<div class="row g-4">
    <div class="col-md-6">
        <div class="border rounded-5 p-4 h-100 d-flex flex-column align-items-start">
            <i class="ci-shield fs-3 mb-3 text-body-secondary" aria-hidden="true"></i>
            <h2 class="h5 mb-3"><?= print_translation('auth_settings_security') ?></h2>
            <span class="badge rounded-pill mb-3 <?= $twoFactorEnabled ? 'text-bg-success' : 'text-bg-secondary' ?>">
                <?= print_translation($twoFactorEnabled ? 'auth_two_factor_status_enabled' : 'auth_two_factor_status_disabled') ?>
            </span>
            <p class="fw-medium mb-2"><?= print_translation('auth_two_factor_heading') ?></p>
            <p class="text-body-secondary small mb-4"><?= print_translation($twoFactorEnabled ? 'auth_two_factor_enabled_hint' : 'auth_two_factor_subtitle') ?></p>
            <a class="btn btn-outline-secondary rounded-pill mt-auto" href="<?= base_href('/profile/settings?section=security') ?>"><?= print_translation('auth_profile_manage_security') ?></a>
        </div>
    </div>
    <div class="col-md-6">
        <div class="border rounded-5 p-4 h-100 d-flex flex-column align-items-start">
            <i class="ci-bell fs-3 mb-3 text-body-secondary" aria-hidden="true"></i>
            <h2 class="h5 mb-3"><?= print_translation('auth_settings_notifications') ?></h2>
            <span class="badge rounded-pill mb-3 <?= $pushEnabled ? 'text-bg-success' : 'text-bg-secondary' ?>">
                <?= print_translation($pushStatusKey) ?>
            </span>
            <p class="fw-medium mb-2"><?= print_translation('auth_profile_push_heading') ?></p>
            <p class="text-body-secondary small mb-4"><?= print_translation('auth_profile_push_subtitle') ?></p>
            <a class="btn btn-outline-secondary rounded-pill mt-auto" href="<?= base_href('/profile/settings?section=notifications') ?>"><?= print_translation('auth_profile_manage_notifications') ?></a>
        </div>
    </div>
</div>
