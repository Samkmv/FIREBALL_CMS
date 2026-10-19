<?php
$form = session()->get('form_data') ?: [];
$values = $form + $settings;
?>
<?= view()->renderPartial('admin/shell_open', ['title' => return_translation('auth_policy_title'), 'subtitle' => return_translation('auth_policy_subtitle')]) ?>
<?= view()->renderPartial('admin/settings_tabs', ['active' => 'authentication']) ?>
<?php if (!$auth_schema_ready): ?><div class="alert alert-warning"><?= print_translation('auth_schema_required') ?></div><?php endif; ?>
<form action="<?= base_href('/admin/settings/authentication') ?>" method="post" class="card border rounded-4">
    <?= get_csrf_field() ?>
    <div class="card-body p-3 p-md-4">
        <h2 class="h5 mb-3"><?= print_translation('auth_policy_registration') ?></h2>
        <p class="text-body-secondary"><?= print_translation('auth_policy_email_required') ?></p>
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label for="auth-phone-mode" class="form-label"><?= print_translation('auth_identifier_phone') ?></label>
                <select id="auth-phone-mode" name="auth_phone_mode" class="form-select">
                    <?php foreach (['off', 'optional', 'required'] as $mode): ?><option value="<?= $mode ?>" <?= $values['auth_phone_mode'] === $mode ? 'selected' : '' ?>><?= print_translation('auth_phone_mode_' . $mode) ?></option><?php endforeach; ?>
                </select><?= get_errors('auth_phone_mode') ?>
            </div>
            <div class="col-md-6">
                <label for="auth-login-mode" class="form-label"><?= print_translation('auth_policy_login_mode') ?></label>
                <select id="auth-login-mode" name="auth_login_mode" class="form-select">
                    <?php foreach (['manual', 'automatic'] as $mode): ?><option value="<?= $mode ?>" <?= $values['auth_login_mode'] === $mode ? 'selected' : '' ?>><?= print_translation('auth_login_mode_' . $mode) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <h2 class="h5 mb-3"><?= print_translation('auth_policy_login') ?></h2>
        <p class="text-body-secondary"><?= print_translation('auth_policy_methods_hint') ?></p>
        <?php foreach (['login', 'email', 'phone'] as $method): $key = 'auth_login_' . $method; ?>
            <div class="form-check form-switch mb-3">
                <input type="hidden" name="<?= $key ?>" value="0">
                <input class="form-check-input" id="<?= $key ?>" name="<?= $key ?>" type="checkbox" value="1" <?= $values[$key] === '1' ? 'checked' : '' ?>>
                <label class="form-check-label" for="<?= $key ?>"><?= print_translation('auth_policy_method_' . $method) ?></label>
                <?= get_errors($key) ?>
            </div>
        <?php endforeach; ?>
        <div class="border-top pt-4 mt-4">
            <h2 class="h5"><?= print_translation('auth_policy_recovery') ?></h2>
            <p class="text-body-secondary mb-0"><?= print_translation('auth_policy_recovery_hint') ?></p>
            <a href="<?= base_href('/admin/settings/mail') ?>" class="d-inline-block mt-2"><?= print_translation('admin_settings_tab_mail') ?></a>
        </div>
        <button class="btn btn-dark rounded-pill mt-4" type="submit" <?= $auth_schema_ready ? '' : 'disabled' ?>><?= print_translation('auth_policy_save') ?></button>
    </div>
</form>
<?= view()->renderPartial('admin/shell_close') ?>
