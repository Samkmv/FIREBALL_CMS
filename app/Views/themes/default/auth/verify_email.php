<section class="container py-5 my-2 my-md-4 my-lg-5">
    <div class="row justify-content-center"><div class="col-md-8 col-lg-6 col-xl-5">
        <div class="border rounded-5 p-4 p-md-5">
            <h1 class="h3 mb-3"><?= print_translation('auth_verify_title') ?></h1>
            <?php if (!empty($token)): ?>
                <p class="text-body-secondary"><?= print_translation('auth_verify_confirm_hint') ?></p>
                <form action="<?= base_href('/verify-email') ?>" method="post">
                    <?= get_csrf_field() ?><input type="hidden" name="token" value="<?= htmlSC($token) ?>">
                    <button type="submit" class="btn btn-dark w-100 rounded-pill"><?= print_translation('auth_verify_confirm') ?></button>
                </form>
            <?php else: ?>
                <p class="text-body-secondary"><?= print_translation(check_auth() ? 'auth_verify_resend_signed_hint' : 'auth_verify_resend_hint') ?></p>
                <form action="<?= base_href('/verify-email') ?>" method="post">
                    <?= get_csrf_field() ?><input type="hidden" name="verification_action" value="resend">
                    <?php if (!check_auth()): ?>
                        <label for="verify-email" class="form-label"><?= print_translation('auth_identifier_email') ?></label>
                        <input id="verify-email" name="email" type="email" class="form-control mb-3" autocomplete="email" maxlength="190" required>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-outline-secondary w-100 rounded-pill"><?= print_translation('auth_verify_resend') ?></button>
                </form>
            <?php endif; ?>
            <a href="<?= base_href('/login') ?>" class="d-inline-block mt-4"><?= print_translation('auth_verify_back_to_login') ?></a>
        </div>
    </div></div>
</section>
