<?php
$legalInformation = \FBL\Theme::getLegalInformationMenu();
$privacyUrl = '#';
$privacySettings = (new \App\Models\SiteSetting())->all();
$configuredPrivacyPageId = (int)($privacySettings['cookie_policy_page_id'] ?? 0);
$useConfiguredPrivacyPage = ($privacySettings['cookie_policy_use_on_registration'] ?? '0') === '1';
$formErrors = session()->get('form_errors') ?: [];
$errorText = static function (string $field, string $fallback) use ($formErrors): string {
    return htmlSC((string)($formErrors[$field][0] ?? return_translation($fallback)));
};
if ($useConfiguredPrivacyPage && $configuredPrivacyPageId > 0) {
    $configuredPrivacyPage = (new \App\Models\Page())->findPublishedById($configuredPrivacyPageId);
    if ($configuredPrivacyPage) {
        $privacyUrl = (string)$configuredPrivacyPage['url'];
    }
}
if ($privacyUrl === '#') {
    foreach ($legalInformation as $legalPage) {
        $legalSlug = mb_strtolower((string)($legalPage['slug'] ?? ''));
        $legalTitle = mb_strtolower((string)($legalPage['title'] ?? $legalPage['label'] ?? ''));
        if (str_contains($legalSlug, 'privacy') || str_contains($legalTitle, 'конфиденциаль')
            || str_contains($legalTitle, 'privacy') || str_contains($legalTitle, 'datenschutz')
            || str_contains($legalTitle, '隐私')
        ) {
            $privacyUrl = (string)($legalPage['url'] ?? $legalPage['href'] ?? '#');
            break;
        }
    }
}
?>
<section class="container py-5 my-2 my-md-4 my-lg-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 col-xl-5">
            <div class="border rounded-5 p-4 p-md-5">
                <h1 class="h3 mb-2"><?= print_translation('auth_register_heading') ?></h1>
                <p class="text-body-secondary mb-4"><?= print_translation('auth_register_subtitle') ?></p>

                <form class="needs-validation" action="<?= base_href('/register') ?>" method="post" novalidate>
                    <?= get_csrf_field() ?>

                    <div class="position-relative mb-4">
                        <label class="form-label" for="register-name"><?= print_translation('auth_register_name') ?></label>
                        <input id="register-name" type="text" name="name" value="<?= old('name') ?>" class="form-control form-control-lg <?= get_validation_class('name') ?>" placeholder="<?= htmlSC(return_translation('auth_register_name')) ?>" autocomplete="name" required>
                        <div class="invalid-feedback"><?= $errorText('name', 'auth_validation_name_required') ?></div>
                    </div>

                    <div class="position-relative mb-4">
                        <label class="form-label" for="register-login"><?= print_translation('auth_register_login_field') ?></label>
                        <input id="register-login" type="text" name="login" value="<?= old('login') ?>" class="form-control form-control-lg <?= get_validation_class('login') ?>" placeholder="user-login" autocomplete="username" minlength="3" pattern="[a-zA-Z0-9-]+" required>
                        <div class="invalid-feedback"><?= $errorText('login', 'auth_validation_login_format') ?></div>
                    </div>

                    <div class="position-relative mb-4">
                        <label class="form-label" for="register-email"><?= print_translation('auth_register_email') ?></label>
                        <input id="register-email" type="email" name="email" value="<?= old('email') ?>" class="form-control form-control-lg <?= get_validation_class('email') ?>" placeholder="name@example.com" autocomplete="email" required>
                        <div class="invalid-feedback"><?= $errorText('email', 'auth_validation_email_invalid') ?></div>
                    </div>

                    <?= view()->renderPartial('incs/password_field', [
                        'id' => 'register-password',
                        'name' => 'password',
                        'label' => return_translation('auth_register_password'),
                        'placeholder' => return_translation('auth_register_password_placeholder'),
                        'autocomplete' => 'new-password',
                        'input_class' => 'form-control-lg',
                        'wrapper_class' => 'password-field--auth mb-4',
                        'required' => true,
                        'minlength' => 8,
                        'hint' => return_translation('auth_validation_password_strength'),
                        'error_fallback' => return_translation('auth_validation_password_strength'),
                    ]) ?>

                    <?= view()->renderPartial('incs/password_field', [
                        'id' => 'register-password-confirmation',
                        'name' => 'password_confirmation',
                        'label' => return_translation('auth_register_password_confirmation'),
                        'placeholder' => return_translation('auth_register_password_confirmation'),
                        'autocomplete' => 'new-password',
                        'input_class' => 'form-control-lg',
                        'wrapper_class' => 'password-field--auth mb-4',
                        'required' => true,
                        'minlength' => 8,
                        'error_fallback' => return_translation('auth_validation_password_confirmation_required'),
                    ]) ?>

                    <div class="form-check mb-4">
                        <input type="checkbox" class="form-check-input <?= get_validation_class('privacy_accepted') ?>" id="privacy-accepted" name="privacy_accepted" value="1" <?= old('privacy_accepted') === '1' ? 'checked' : '' ?> required>
                        <label for="privacy-accepted" class="form-check-label">
                            <?= print_translation('auth_register_privacy_prefix') ?>
                            <a class="text-dark-emphasis" href="<?= htmlSC($privacyUrl) ?>" <?= $privacyUrl === '#' ? '' : 'target="_blank" rel="noopener noreferrer"' ?>><?= print_translation('auth_register_privacy_link') ?></a>
                        </label>
                        <div class="invalid-feedback"><?= $errorText('privacy_accepted', 'auth_validation_privacy_required') ?></div>
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
