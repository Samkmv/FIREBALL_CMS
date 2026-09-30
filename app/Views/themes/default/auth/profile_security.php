<form class="border rounded-5 p-4 p-md-5 mb-4" action="<?= base_href('/profile/settings?section=security') ?>" method="post" novalidate>
    <?= get_csrf_field() ?>
    <input type="hidden" name="profile_action" value="password">
    <h2 class="h5 mb-2"><?= print_translation('auth_settings_password') ?></h2>
    <p class="text-body-secondary mb-4"><?= print_translation('auth_settings_password_hint') ?></p>
    <div class="row g-3">
        <div class="col-12">
            <?= view()->renderPartial('incs/password_field', [
                'id' => 'profile-current-password',
                'name' => 'current_password',
                'label' => return_translation('auth_profile_current_password'),
                'placeholder' => '********',
                'autocomplete' => 'current-password',

            ]) ?>
        </div>
        <div class="col-md-6">
            <?= view()->renderPartial('incs/password_field', [
                'id' => 'profile-password',
                'name' => 'password',
                'label' => return_translation('auth_profile_new_password'),
                'placeholder' => '********',
                'autocomplete' => 'new-password',
                'hint' => return_translation('auth_settings_password_rules'),
            ]) ?>
        </div>
        <div class="col-md-6">
            <?= view()->renderPartial('incs/password_field', [
                'id' => 'profile-password-confirmation',
                'name' => 'password_confirmation',
                'label' => return_translation('auth_profile_password_confirmation'),
                'placeholder' => '********',
                'autocomplete' => 'new-password',
            ]) ?>
        </div>
    </div>

    <div class="pt-4">
        <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit">
            <i class="ci-settings"></i>
            <span><?= print_translation('auth_profile_save') ?></span>
        </button>
    </div>
</form>


<div class="border rounded-5 p-4 p-md-5 mb-4" id="profile-two-factor" data-profile-scroll-section>
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="ci-shield fs-4"></i>
                <h2 class="h5 mb-0"><?= print_translation('auth_two_factor_heading') ?></h2>
            </div>
            <p class="text-body-secondary mb-0"><?= print_translation('auth_two_factor_subtitle') ?></p>
        </div>
        <span class="badge rounded-pill <?= $twoFactorEnabled ? 'text-bg-success' : 'text-bg-secondary' ?>">
            <?= htmlSC(return_translation($twoFactorEnabled
                ? 'auth_two_factor_status_enabled'
                : 'auth_two_factor_status_disabled')) ?>
        </span>
    </div>

    <?php if ($recoveryCodes !== []): ?>
        <div class="alert alert-warning mb-4">
            <h3 class="h6 mb-2"><?= print_translation('auth_two_factor_recovery_heading') ?></h3>
            <p class="small mb-3"><?= print_translation('auth_two_factor_recovery_hint') ?></p>
            <div class="row row-cols-1 row-cols-sm-2 g-2 font-monospace">
                <?php foreach ($recoveryCodes as $recoveryCode): ?>
                    <div class="col"><code class="text-body"><?= htmlSC($recoveryCode) ?></code></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($twoFactorEnabled): ?>
        <p class="text-body-secondary"><?= print_translation('auth_two_factor_enabled_hint') ?></p>
        <form action="<?= base_href('/profile/settings?section=security') ?>" method="post" novalidate data-profile-scroll-target="#profile-two-factor">
            <?= get_csrf_field() ?>
            <input type="hidden" name="profile_action" value="two_factor_disable">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="two-factor-disable-password"><?= print_translation('auth_profile_current_password') ?></label>
                    <input id="two-factor-disable-password" class="form-control" type="password" name="current_password" autocomplete="current-password" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="two-factor-disable-code"><?= print_translation('auth_two_factor_code') ?></label>
                    <input id="two-factor-disable-code" class="form-control" type="text" name="code" autocomplete="one-time-code" maxlength="11" required>
                </div>
            </div>
            <?= get_errors('two_factor_disable') ?>
            <button class="btn btn-outline-danger rounded-pill mt-3" type="submit">
                <?= print_translation('auth_two_factor_disable') ?>
            </button>
        </form>
    <?php elseif ($twoFactorSetup): ?>
        <ol class="text-body-secondary ps-4">
            <li class="mb-2"><?= print_translation('auth_two_factor_setup_step_app') ?></li>
            <li class="mb-2">
                <?php if (!empty($two_factor_qr_code)): ?>
                    <div class="bg-white border rounded-4 p-3 my-3 d-inline-flex">
                        <img
                            src="<?= htmlSC($two_factor_qr_code) ?>"
                            width="280"
                            height="280"
                            alt="<?= htmlSC(return_translation('auth_two_factor_qr_alt')) ?>"
                            class="img-fluid"
                        >
                    </div>
                    <p class="small mb-3"><?= print_translation('auth_two_factor_qr_hint') ?></p>
                <?php endif; ?>
                <?= print_translation('auth_two_factor_setup_step_secret') ?>
                <div class="bg-body-tertiary rounded-3 p-3 mt-2 font-monospace text-break user-select-all">
                    <?= htmlSC((string)$twoFactorSetup['secret']) ?>
                </div>
                <a class="btn btn-outline-secondary btn-sm rounded-pill mt-2" href="<?= htmlSC($two_factor_uri ?? '') ?>">
                    <?= print_translation('auth_two_factor_open_app') ?>
                </a>
            </li>
            <li><?= print_translation('auth_two_factor_setup_step_confirm') ?></li>
        </ol>
        <form action="<?= base_href('/profile/settings?section=security') ?>" method="post" novalidate data-profile-scroll-target="#profile-two-factor">
            <?= get_csrf_field() ?>
            <input type="hidden" name="profile_action" value="two_factor_confirm">
            <div class="mb-3">
                <label class="form-label" for="two-factor-confirm-code"><?= print_translation('auth_two_factor_code') ?></label>
                <input id="two-factor-confirm-code" class="form-control <?= get_validation_class('two_factor_code') ?>" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required>
                <?= get_errors('two_factor_code') ?>
            </div>
            <button class="btn btn-dark rounded-pill" type="submit">
                <?= print_translation('auth_two_factor_confirm') ?>
            </button>
        </form>
    <?php else: ?>
        <p class="text-body-secondary"><?= print_translation('auth_two_factor_disabled_hint') ?></p>
        <form action="<?= base_href('/profile/settings?section=security') ?>" method="post" novalidate data-profile-scroll-target="#profile-two-factor">
            <?= get_csrf_field() ?>
            <input type="hidden" name="profile_action" value="two_factor_prepare">
            <div class="mb-3">
                <label class="form-label" for="two-factor-current-password"><?= print_translation('auth_profile_current_password') ?></label>
                <input id="two-factor-current-password" class="form-control <?= get_validation_class('two_factor_current_password') ?>" type="password" name="current_password" autocomplete="current-password" required>
                <?= get_errors('two_factor_current_password') ?>
            </div>
            <button class="btn btn-dark rounded-pill" type="submit">
                <?= print_translation('auth_two_factor_enable') ?>
            </button>
        </form>
    <?php endif; ?>
</div>
