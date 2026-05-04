<?php
$roleSlug = (string)($user['role'] ?? 'user');
$roleBadgeClass = match ($roleSlug) {
    'creator' => 'text-bg-warning',
    'admin' => 'text-bg-info',
    default => 'text-bg-secondary',
};
$createdAt = !empty($user['created_at']) ? date('d.m.Y H:i', strtotime((string)$user['created_at'])) : '—';
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
                    <div class="col-md-6">
                        <label class="form-label" for="profile-password"><?= print_translation('auth_profile_new_password') ?></label>
                        <input id="profile-password" type="password" name="password" class="form-control <?= get_validation_class('password') ?>" placeholder="••••••••">
                        <div class="form-text"><?= print_translation('auth_profile_password_hint') ?></div>
                        <?= get_errors('password') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="profile-password-confirmation"><?= print_translation('auth_profile_password_confirmation') ?></label>
                        <input id="profile-password-confirmation" type="password" name="password_confirmation" class="form-control <?= get_validation_class('password_confirmation') ?>" placeholder="••••••••">
                        <?= get_errors('password_confirmation') ?>
                    </div>
                </div>

                <div class="pt-4">
                    <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                        <i class="ci-settings"></i>
                        <span><?= print_translation('auth_profile_save') ?></span>
                    </button>
                </div>
            </form>

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
