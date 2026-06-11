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

                    <?= view()->renderPartial('incs/password_field', [
                        'id' => 'reset-password',
                        'name' => 'password',
                        'label' => return_translation('auth_reset_password'),
                        'placeholder' => '********',
                        'autocomplete' => 'new-password',
                        'wrapper_class' => 'mb-3',
                        'required' => true,
                        'minlength' => 8,
                        'hint' => return_translation('auth_validation_password_strength'),
                    ]) ?>

                    <?= view()->renderPartial('incs/password_field', [
                        'id' => 'reset-password-confirmation',
                        'name' => 'password_confirmation',
                        'label' => return_translation('auth_reset_password_confirmation'),
                        'placeholder' => '********',
                        'autocomplete' => 'new-password',
                        'wrapper_class' => 'mb-4',
                        'required' => true,
                        'minlength' => 8,
                    ]) ?>

                    <button type="submit" class="btn btn-dark w-100 rounded-pill"><?= print_translation('auth_reset_submit') ?></button>
                </form>
            </div>
        </div>
    </div>
</section>
