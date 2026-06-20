<?php
$formErrors = session()->get('form_errors') ?: [];
$codeError = (string)($formErrors['code'][0] ?? '');
?>
<section class="two-factor-challenge-section container py-4 py-md-5 my-0 my-md-4 my-lg-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 col-xl-5">
            <div class="two-factor-challenge-card border rounded-5 p-4 p-md-5">
                <div class="d-flex align-items-start align-items-sm-center gap-3 mb-3">
                    <span class="two-factor-challenge-icon bg-body-tertiary fs-3" aria-hidden="true">
                        <i class="ci-shield"></i>
                    </span>
                    <div>
                        <h1 class="h3 mb-1"><?= print_translation('auth_two_factor_challenge_heading') ?></h1>
                        <p class="text-body-secondary mb-0"><?= print_translation('auth_two_factor_challenge_subtitle') ?></p>
                    </div>
                </div>

                <form action="<?= base_href('/two-factor-challenge') ?>" method="post" novalidate>
                    <?= get_csrf_field() ?>
                    <div class="mb-4">
                        <label class="form-label" for="two-factor-code"><?= print_translation('auth_two_factor_code') ?></label>
                        <input
                            id="two-factor-code"
                            class="form-control form-control-lg <?= $codeError !== '' ? 'is-invalid' : '' ?>"
                            type="text"
                            name="code"
                            autocomplete="one-time-code"
                            maxlength="11"
                            autofocus
                            required
                        >
                        <div class="form-text"><?= print_translation('auth_two_factor_challenge_hint') ?></div>
                        <?php if ($codeError !== ''): ?>
                            <div class="invalid-feedback"><?= htmlSC($codeError) ?></div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn btn-dark w-100 rounded-pill">
                        <?= print_translation('auth_two_factor_challenge_submit') ?>
                    </button>
                </form>

                <a class="btn btn-link text-body-secondary w-100 mt-2" href="<?= base_href('/login') ?>">
                    <?= print_translation('auth_two_factor_back_to_login') ?>
                </a>
                <a class="btn btn-link text-body-secondary w-100 mt-1" href="<?= base_href('/two-factor-recovery') ?>">
                    <?= print_translation('auth_two_factor_recovery_link') ?>
                </a>
            </div>
        </div>
    </div>
</section>
