<?php
$identityAvailable = \App\Services\AuthIdentity::available();
$authPolicy = (new \App\Services\AuthSettings())->all();
$showPhone = $identityAvailable && $authPolicy['auth_phone_mode'] !== 'off';
?>
<?php if ($showPhone && $authPolicy['auth_phone_mode'] === 'required' && empty($user['phone_normalized'])): ?>
<div class="alert alert-info" role="status"><?= print_translation('auth_phone_legacy_notice') ?></div>
<?php endif; ?>
<?php if ($identityAvailable && !empty($user['pending_email'])): ?>
<div class="alert alert-info" role="status">
    <?= print_translation('auth_verify_pending') ?> <?= htmlSC($user['pending_email']) ?>
    <a class="alert-link" href="<?= base_href('/verify-email') ?>"><?= print_translation('auth_verify_resend') ?></a>
</div>
<?php elseif ($identityAvailable && empty($user['email_verified_at'])): ?>
<div class="alert alert-info" role="status">
    <?= print_translation('auth_verify_profile_notice') ?>
    <a class="alert-link" href="<?= base_href('/verify-email') ?>"><?= print_translation('auth_verify_resend') ?></a>
</div>
<?php endif; ?>
<form class="border rounded-5 p-4 p-md-5 mb-4" action="<?= base_href('/profile/settings?section=information') ?>" method="post" novalidate>
    <?= get_csrf_field() ?>
    <input type="hidden" name="profile_action" value="details">

    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-4">
        <div>
            <h2 class="h5 mb-1"><?= print_translation('auth_settings_information') ?></h2>
            <p class="text-body-secondary mb-0"><?= print_translation('auth_settings_information_hint') ?></p>
        </div>
        <span class="d-inline-flex align-items-center gap-2 text-body-secondary small">
            <i class="ci-edit"></i>
            <span>@<?= htmlSC($user['login'] ?? '') ?></span>
        </span>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="profile-name"><?= print_translation('auth_profile_name') ?></label>
            <input id="profile-name" type="text" name="name" value="<?= old('name') ?: htmlSC($user['name']) ?>" class="form-control <?= get_validation_class('name') ?>">
            <?= get_errors('name') ?>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="profile-login"><?= print_translation('auth_profile_login') ?></label>
            <input id="profile-login" type="text" name="login" value="<?= old('login') ?: htmlSC($user['login'] ?? '') ?>" class="form-control <?= get_validation_class('login') ?>">
            <?= get_errors('login') ?>
        </div>
        <div class="col-12">
            <label class="form-label" for="profile-email"><?= print_translation('auth_profile_email') ?></label>
            <input id="profile-email" type="email" name="email" value="<?= old('email') ?: htmlSC($user['email']) ?>" class="form-control <?= get_validation_class('email') ?>" autocomplete="email" maxlength="190" required>
            <?= get_errors('email') ?>
        </div>
        <?php if ($showPhone): ?>
        <div class="col-12">
            <label class="form-label" for="profile-phone"><?= print_translation('auth_identifier_phone') ?></label>
            <input id="profile-phone" type="tel" name="phone" value="<?= htmlSC((string)(session()->get('form_data.phone') ?? $user['phone_normalized'] ?? '')) ?>" class="form-control <?= get_validation_class('phone') ?>" inputmode="tel" autocomplete="tel" maxlength="25" placeholder="+7 (999) 123-45-67" data-auth-phone>
            <?= get_errors('phone') ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="mt-3">
        <?= view()->renderPartial('incs/password_field', [
            'id' => 'profile-details-password', 'name' => 'current_password',
            'label' => return_translation('auth_profile_current_password'),
            'autocomplete' => 'current-password',
            'hint' => return_translation('auth_settings_identity_password_hint'),
        ]) ?>
    </div>
    <div class="pt-4">
        <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit">
            <i class="ci-settings"></i>
            <span><?= print_translation('auth_profile_save') ?></span>
        </button>
    </div>
</form>
<?php if ($showPhone): ?>
<script src="<?= asset_versioned_url(base_url('/assets/default/js/auth-phone.js'), WWW . '/assets/default/js/auth-phone.js') ?>" defer></script>
<?php endif; ?>

    <form class="border rounded-5 p-4 p-md-5" action="<?= base_href('/profile/settings?section=information') ?>" method="post" enctype="multipart/form-data">
        <?= get_csrf_field() ?>
        <input type="hidden" name="profile_action" value="avatar">

        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="ci-camera text-body-tertiary fs-4 flex-shrink-0" aria-hidden="true"></i>
            <h2 class="h6 mb-0"><?= print_translation('auth_profile_avatar') ?></h2>
        </div>

        <div class="mb-3">
            <label class="form-label" for="profile-avatar-file"><?= print_translation('auth_profile_avatar') ?></label>
            <input id="profile-avatar-file" class="form-control <?= get_validation_class('avatar_file') ?>" type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp,image/gif" aria-describedby="profile-avatar-hint">
            <p id="profile-avatar-hint" class="form-text mb-0"><?= print_translation('auth_profile_avatar_hint') ?></p>
            <?= get_errors('avatar_file') ?>
        </div>

        <button class="btn btn-dark rounded-pill d-inline-flex align-items-center justify-content-center gap-2" type="submit">
            <i class="ci-image"></i>
            <span><?= print_translation('auth_profile_avatar_save') ?></span>
        </button>
    </form>
