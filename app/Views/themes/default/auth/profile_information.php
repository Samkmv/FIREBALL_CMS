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
            <input id="profile-email" type="email" name="email" value="<?= old('email') ?: htmlSC($user['email']) ?>" class="form-control <?= get_validation_class('email') ?>">
            <?= get_errors('email') ?>
        </div>
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

    <form class="border rounded-5 p-4" action="<?= base_href('/profile/settings?section=information') ?>" method="post" enctype="multipart/form-data">
        <?= get_csrf_field() ?>
        <input type="hidden" name="profile_action" value="avatar">

        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="ci-camera text-body-tertiary fs-4"></i>
            <div>
                <h2 class="h6 mb-1"><?= print_translation('auth_profile_avatar') ?></h2>
                <p class="text-body-secondary small mb-0"><?= print_translation('auth_profile_avatar_hint') ?></p>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="profile-avatar-file"><?= print_translation('auth_profile_avatar') ?></label>
            <input id="profile-avatar-file" class="form-control <?= get_validation_class('avatar_file') ?>" type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp,image/gif">
            <?= get_errors('avatar_file') ?>
        </div>

        <button class="btn btn-dark rounded-pill w-100 d-inline-flex align-items-center justify-content-center gap-2" type="submit">
            <i class="ci-image"></i>
            <span><?= print_translation('auth_profile_avatar_save') ?></span>
        </button>
    </form>
