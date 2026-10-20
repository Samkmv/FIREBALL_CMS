<?php
$verificationEmail = (string)($verification_email ?? '');
$codeReady = !empty($verification_code_ready) && $verificationEmail !== '';
$verificationRequired = $verification_required ?? true;
?>
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
                <?php if (!$verificationRequired): ?>
                    <p class="text-body-secondary"><?= print_translation('auth_verify_optional_hint') ?></p>
                <?php endif; ?>
                <?php if ($codeReady): ?>
                    <p id="verify-code-hint" class="text-body-secondary"><?= print_translation('auth_verify_code_hint') ?></p>
                    <form action="<?= base_href('/verify-email') ?>" method="post" aria-describedby="verify-code-hint">
                        <?= get_csrf_field() ?>
                        <input type="hidden" name="verification_action" value="confirm_code">
                        <label for="verify-code-email" class="form-label"><?= print_translation('auth_identifier_email') ?></label>
                        <input id="verify-code-email" name="email" type="email" value="<?= htmlSC($verificationEmail) ?>" class="form-control mb-3" autocomplete="email" readonly>
                        <label for="verify-code" class="form-label"><?= print_translation('auth_verify_code_label') ?> <span class="text-danger" aria-hidden="true">*</span></label>
                        <input id="verify-code" name="verification_code" type="text" class="form-control form-control-lg text-center mb-2 <?= get_validation_class('verification_code') ?>" inputmode="numeric" autocomplete="one-time-code" autocapitalize="none" spellcheck="false" minlength="6" maxlength="6" pattern="[0-9]{6}" placeholder="000000" aria-describedby="verify-code-expiry" required autofocus>
                        <?= get_errors('verification_code') ?>
                        <p id="verify-code-expiry" class="text-body-secondary fs-sm mb-3"><?= print_translation('auth_verify_code_expiry') ?></p>
                        <button type="submit" class="btn btn-dark w-100 rounded-pill"><?= print_translation('auth_verify_code_submit') ?></button>
                    </form>
                    <div class="border-top pt-4 mt-4">
                <?php endif; ?>
                <p id="verify-email-hint" class="text-body-secondary fs-sm"><?= print_translation(check_auth() ? 'auth_verify_resend_signed_hint' : 'auth_verify_resend_hint') ?></p>
                <form action="<?= base_href('/verify-email') ?>" method="post" aria-describedby="verify-email-hint">
                    <?= get_csrf_field() ?><input type="hidden" name="verification_action" value="resend">
                    <?php if (!check_auth()): ?>
                        <label for="verify-email" class="form-label"><?= print_translation('auth_identifier_email') ?></label>
                        <input id="verify-email" name="email" type="email" value="<?= htmlSC($verificationEmail) ?>" class="form-control mb-3" autocomplete="email" maxlength="190" required>
                    <?php else: ?>
                        <p class="fw-medium text-break"><?= htmlSC($verificationEmail) ?></p>
                        <input type="hidden" name="email" value="<?= htmlSC($verificationEmail) ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-outline-secondary w-100 rounded-pill"><?= print_translation($codeReady ? 'auth_verify_code_resend' : 'auth_verify_send') ?></button>
                </form>
                <?php if ($codeReady): ?></div><?php endif; ?>
            <?php endif; ?>
            <a href="<?= base_href('/login') ?>" class="d-inline-block mt-4"><?= print_translation('auth_verify_back_to_login') ?></a>
        </div>
    </div></div>
</section>
