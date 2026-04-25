<section class="container py-5 my-2 my-md-4 my-lg-5">
    <div class="row justify-content-center">
        <div class="col-xl-9">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <h1 class="h3 mb-2"><?= print_translation('auth_profile_heading') ?></h1>
                    <p class="text-body-secondary mb-0"><?= print_translation('auth_profile_subtitle') ?></p>
                </div>
                <div class="d-flex gap-2">
                    <?php if (check_admin()): ?>
                        <a class="btn btn-dark rounded-pill" href="<?= base_href('/admin') ?>"><?= print_translation('auth_profile_admin_link') ?></a>
                    <?php endif; ?>
                    <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/logout') ?>"><?= print_translation('auth_profile_logout') ?></a>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-4">
                    <form class="border rounded-5 p-4 h-100" action="<?= base_href('/profile') ?>" method="post" enctype="multipart/form-data">
                        <?= get_csrf_field() ?>
                        <input type="hidden" name="profile_action" value="avatar">
                        <div class="text-center mb-4">
                            <img
                                src="<?= get_user_avatar($user['avatar'] ?? null, 'lg') ?>"
                                alt="<?= htmlSC($user['name']) ?>"
                                class="rounded-circle border object-fit-cover mb-3"
                                style="width: 112px; height: 112px;"
                            >
                            <div class="h5 mb-1"><?= htmlSC($user['name']) ?></div>
                            <div class="text-body-secondary">@<?= htmlSC($user['login'] ?? '') ?></div>
                            <div class="text-body-secondary"><?= htmlSC($user['email']) ?></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?= print_translation('auth_profile_avatar') ?></label>
                            <input class="form-control <?= get_validation_class('avatar_file') ?>" type="file" name="avatar_file" accept="image/jpeg,image/png,image/webp,image/gif">
                            <div class="form-text"><?= print_translation('auth_profile_avatar_hint') ?></div>
                            <?= get_errors('avatar_file') ?>
                        </div>

                        <button class="btn btn-dark rounded-pill w-100" type="submit"><?= print_translation('auth_profile_avatar_save') ?></button>
                    </form>
                </div>

                <div class="col-lg-8">
                    <div class="row g-4">
                        <div class="col-12">
                            <form class="border rounded-5 p-4" action="<?= base_href('/profile') ?>" method="post" novalidate>
                                <?= get_csrf_field() ?>
                                <input type="hidden" name="profile_action" value="details">
                                <div class="d-flex align-items-end justify-content-between gap-3 flex-wrap mb-4">
                                    <div>
                                        <h2 class="h5 mb-1"><?= print_translation('auth_profile_edit_heading') ?></h2>
                                        <p class="text-body-secondary mb-0"><?= print_translation('auth_profile_edit_subtitle') ?></p>
                                    </div>
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
                                    <div class="col-md-6">
                                        <label class="form-label" for="profile-email"><?= print_translation('auth_profile_email') ?></label>
                                        <input id="profile-email" type="email" name="email" value="<?= old('email') ?: htmlSC($user['email']) ?>" class="form-control <?= get_validation_class('email') ?>">
                                        <?= get_errors('email') ?>
                                    </div>
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
                                    <button class="btn btn-dark rounded-pill" type="submit"><?= print_translation('auth_profile_save') ?></button>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-5 p-4 h-100">
                                <div class="text-body-tertiary fs-sm mb-2"><?= print_translation('auth_profile_name') ?></div>
                                <div class="h5 mb-0"><?= htmlSC($user['name']) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-5 p-4 h-100">
                                <div class="text-body-tertiary fs-sm mb-2"><?= print_translation('auth_profile_login') ?></div>
                                <div class="h5 mb-0">@<?= htmlSC($user['login'] ?? '') ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-5 p-4 h-100">
                                <div class="text-body-tertiary fs-sm mb-2"><?= print_translation('auth_profile_email') ?></div>
                                <div class="h5 mb-0"><?= htmlSC($user['email']) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-5 p-4 h-100">
                                <div class="text-body-tertiary fs-sm mb-2"><?= print_translation('auth_profile_id') ?></div>
                                <div class="h5 mb-0">#<?= (int)$user['id'] ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-5 p-4 h-100">
                                <div class="text-body-tertiary fs-sm mb-2"><?= print_translation('auth_profile_role') ?></div>
                                <div class="h5 mb-0"><?= htmlSC(get_user_role_label($user['role'] ?? 'user')) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded-5 p-4 h-100">
                                <div class="text-body-tertiary fs-sm mb-2"><?= print_translation('auth_profile_created_at') ?></div>
                                <div class="h5 mb-0"><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
