<section class="container py-5 my-2 my-md-4 my-lg-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 col-xl-5">
            <div class="border rounded-5 p-4 p-md-5">
                <h1 class="h3 mb-2"><?= print_translation('auth_reset_heading') ?></h1>
                <p class="text-body-secondary mb-4"><?= print_translation('auth_reset_subtitle') ?></p>

                <form action="<?= base_href('/reset-password') ?>" method="post" novalidate>
                    <?= get_csrf_field() ?>
                    <input type="hidden" name="token" value="<?= htmlSC($token) ?>">

                    <div class="mb-3">
                        <label class="form-label"><?= print_translation('auth_login_email') ?></label>
                        <input type="email" class="form-control" value="<?= htmlSC($reset_request['email'] ?? '') ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="reset-password"><?= print_translation('auth_reset_password') ?></label>
                        <input id="reset-password" type="password" name="password" class="form-control <?= get_validation_class('password') ?>" placeholder="••••••••">
                        <?= get_errors('password') ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="reset-password-confirmation"><?= print_translation('auth_reset_password_confirmation') ?></label>
                        <input id="reset-password-confirmation" type="password" name="password_confirmation" class="form-control <?= get_validation_class('password_confirmation') ?>" placeholder="••••••••">
                        <?= get_errors('password_confirmation') ?>
                    </div>

                    <button type="submit" class="btn btn-dark w-100 rounded-pill"><?= print_translation('auth_reset_submit') ?></button>
                </form>
            </div>
        </div>
    </div>
</section>
