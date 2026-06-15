<?php
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
?>

<section class="container py-5 my-2 my-md-4 my-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4 mb-lg-5">
        <div>
            <h1 class="h3 mb-2"><?= print_translation('auth_profile_heading') ?></h1>
            <p class="text-body-secondary mb-0"><?= print_translation('auth_profile_subtitle') ?></p>
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
                        <h2 class="h5 mb-1"><?= htmlSC($user['name']) ?></h2>
                        <div class="text-body-secondary">@<?= htmlSC($user['login'] ?? '') ?></div>
                        <div class="d-inline-flex align-items-center gap-2 text-body-secondary small mt-2">
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
                            <i class="ci-user-check text-body-tertiary fs-base"></i>
                            <span class="text-body-secondary"><?= print_translation('auth_profile_role') ?>:</span>
                            <span class="fw-medium ms-auto"><?= htmlSC(get_user_role_label($roleSlug)) ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <i class="ci-calendar text-body-tertiary fs-base"></i>
                            <span class="text-body-secondary"><?= print_translation('auth_profile_created_at') ?>:</span>
                            <span class="fw-medium ms-auto text-end"><?= htmlSC($createdAt) ?></span>
                        </div>
                    </div>
                </div>

                <form class="border rounded-5 p-4" action="<?= base_href('/profile') ?>" method="post" enctype="multipart/form-data">
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
                        <input class="form-control <?= get_validation_class('avatar_file') ?>" type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp,image/gif">
                        <?= get_errors('avatar_file') ?>
                    </div>

                    <button class="btn btn-dark rounded-pill w-100 d-inline-flex align-items-center justify-content-center gap-2" type="submit">
                        <i class="ci-image"></i>
                        <span><?= print_translation('auth_profile_avatar_save') ?></span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="col-lg-8 col-xl-9">
            <form class="border rounded-5 p-4 p-md-5 mb-4" action="<?= base_href('/profile') ?>" method="post" novalidate>
                <?= get_csrf_field() ?>
                <input type="hidden" name="profile_action" value="details">

                <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap mb-4">
                    <div>
                        <h2 class="h5 mb-1"><?= print_translation('auth_profile_edit_heading') ?></h2>
                        <p class="text-body-secondary mb-0"><?= print_translation('auth_profile_edit_subtitle') ?></p>
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

                <hr class="my-4">

                <div class="row g-3">
                    <div class="col-12">
                        <?= view()->renderPartial('incs/password_field', [
                            'id' => 'profile-current-password',
                            'name' => 'current_password',
                            'label' => return_translation('auth_profile_current_password'),
                            'placeholder' => '********',
                            'autocomplete' => 'current-password',
                            'hint' => return_translation('auth_profile_current_password_hint'),
                        ]) ?>
                    </div>
                    <div class="col-md-6">
                        <?= view()->renderPartial('incs/password_field', [
                            'id' => 'profile-password',
                            'name' => 'password',
                            'label' => return_translation('auth_profile_new_password'),
                            'placeholder' => '********',
                            'autocomplete' => 'new-password',
                            'hint' => return_translation('auth_profile_password_hint'),
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

            <div class="border rounded-5 p-4 p-md-5 mb-4">
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
                    <form action="<?= base_href('/profile') ?>" method="post" novalidate>
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
                    <form action="<?= base_href('/profile') ?>" method="post" novalidate>
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
                    <form action="<?= base_href('/profile') ?>" method="post" novalidate>
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

            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
                <div class="col">
                    <div class="border rounded-5 p-4 h-100">
                        <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2">
                            <i class="ci-user"></i>
                            <span><?= print_translation('auth_profile_name') ?></span>
                        </div>
                        <div class="h6 mb-0 text-break"><?= htmlSC($user['name']) ?></div>
                    </div>
                </div>
                <div class="col">
                    <div class="border rounded-5 p-4 h-100">
                        <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2">
                            <i class="ci-hash"></i>
                            <span><?= print_translation('auth_profile_login') ?></span>
                        </div>
                        <div class="h6 mb-0 text-break">@<?= htmlSC($user['login'] ?? '') ?></div>
                    </div>
                </div>
                <div class="col">
                    <div class="border rounded-5 p-4 h-100">
                        <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2">
                            <i class="ci-mail"></i>
                            <span><?= print_translation('auth_profile_email') ?></span>
                        </div>
                        <div class="h6 mb-0 text-break"><?= htmlSC($user['email']) ?></div>
                    </div>
                </div>
                <div class="col">
                    <div class="border rounded-5 p-4 h-100">
                        <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2">
                            <i class="ci-id-card"></i>
                            <span><?= print_translation('auth_profile_id') ?></span>
                        </div>
                        <div class="h6 mb-0">#<?= (int)$user['id'] ?></div>
                    </div>
                </div>
                <div class="col">
                    <div class="border rounded-5 p-4 h-100">
                        <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2">
                            <i class="ci-shield"></i>
                            <span><?= print_translation('auth_profile_role') ?></span>
                        </div>
                        <div class="h6 mb-0"><?= htmlSC(get_user_role_label($roleSlug)) ?></div>
                    </div>
                </div>
                <div class="col">
                    <div class="border rounded-5 p-4 h-100">
                        <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2">
                            <i class="ci-calendar"></i>
                            <span><?= print_translation('auth_profile_created_at') ?></span>
                        </div>
                        <div class="h6 mb-0"><?= htmlSC($createdAt) ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
