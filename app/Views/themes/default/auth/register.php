<section class="container py-5 my-2 my-md-4 my-lg-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 col-xl-5">
            <div class="border rounded-5 p-4 p-md-5">
                <h1 class="h3 mb-2"><?= print_translation('auth_register_heading') ?></h1>
                <p class="text-body-secondary mb-4"><?= print_translation('auth_register_subtitle') ?></p>

                <form action="<?= base_href('/register') ?>" method="post" novalidate>
                    <?= get_csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="register-name"><?= print_translation('auth_register_name') ?></label>
                        <input id="register-name" type="text" name="name" value="<?= old('name') ?>" class="form-control <?= get_validation_class('name') ?>" placeholder="<?= print_translation('auth_register_name') ?>">
                        <?= get_errors('name') ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="register-login"><?= print_translation('auth_register_login_field') ?></label>
                        <input id="register-login" type="text" name="login" value="<?= old('login') ?>" class="form-control <?= get_validation_class('login') ?>" placeholder="admin">
                        <?= get_errors('login') ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="register-email"><?= print_translation('auth_register_email') ?></label>
                        <input id="register-email" type="email" name="email" value="<?= old('email') ?>" class="form-control <?= get_validation_class('email') ?>" placeholder="name@example.com">
                        <?= get_errors('email') ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="register-password"><?= print_translation('auth_register_password') ?></label>
                        <input id="register-password" type="password" name="password" class="form-control <?= get_validation_class('password') ?>" placeholder="••••••••">
                        <?= get_errors('password') ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="register-password-confirmation"><?= print_translation('auth_register_password_confirmation') ?></label>
                        <input id="register-password-confirmation" type="password" name="password_confirmation" class="form-control <?= get_validation_class('password_confirmation') ?>" placeholder="••••••••">
                        <?= get_errors('password_confirmation') ?>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 rounded-pill"><?= print_translation('auth_register_submit') ?></button>
                </form>

                <p class="text-body-secondary fs-sm mt-4 mb-0">
                    <?= print_translation('auth_register_login_text') ?>
                    <a href="<?= base_href('/login') ?>" class="fw-medium text-decoration-none"><?= print_translation('auth_register_login_link') ?></a>
                </p>
            </div>
        </div>
    </div>
</section>
