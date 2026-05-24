<section class="container py-5 my-2 my-md-4 my-lg-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 col-xl-5">
            <div class="border rounded-5 p-4 p-md-5">
                <h1 class="h3 mb-2"><?= print_translation('auth_login_heading') ?></h1>
                <p class="text-body-secondary mb-4"><?= print_translation('auth_login_subtitle') ?></p>

                <form action="<?= base_href('/login') ?>" method="post" novalidate>
                    <?= get_csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="login-login"><?= print_translation('auth_login_login') ?></label>
                        <input id="login-login" type="text" name="login" value="<?= old('login') ?>" class="form-control <?= get_validation_class('login') ?>" placeholder="login">
                        <?= get_errors('login') ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="login-password"><?= print_translation('auth_login_password') ?></label>
                        <input id="login-password" type="password" name="password" class="form-control <?= get_validation_class('password') ?>" placeholder="••••••••">
                        <?= get_errors('password') ?>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 rounded-pill"><?= print_translation('auth_login_submit') ?></button>
                </form>

                <div class="border-top pt-4 mt-4">
                    <h2 class="h6 mb-2"><?= print_translation('auth_login_forgot_heading') ?></h2>
                    <p class="text-body-secondary fs-sm mb-3"><?= print_translation('auth_login_forgot_subtitle') ?></p>

                    <form action="<?= base_href('/forgot-password') ?>" method="post" novalidate>
                        <?= get_csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label" for="reset-email"><?= print_translation('auth_login_forgot_email') ?></label>
                            <input id="reset-email" type="email" name="reset_email" value="<?= old('reset_email') ?>" class="form-control <?= get_validation_class('reset_email') ?>" placeholder="name@example.com">
                            <?= get_errors('reset_email') ?>
                        </div>

                        <button type="submit" class="btn btn-outline-dark w-100 rounded-pill"><?= print_translation('auth_login_forgot_submit') ?></button>
                    </form>
                </div>

                <p class="text-body-secondary fs-sm mt-4 mb-0">
                    <?= print_translation('auth_login_register_text') ?>
                    <a href="<?= base_href('/register') ?>" class="fw-medium text-decoration-none"><?= print_translation('auth_login_register_link') ?></a>
                </p>
            </div>
        </div>
    </div>
</section>
