<?php
$formData = session()->get('form_data') ?: [];
$value = static function (string $key, string $default = '') use ($formData, $settings): string {
    return (string)($formData[$key] ?? ($settings[$key] ?? $default));
};
$mailEnabled = $value('mail_enabled', '0') === '1';
$encryption = $value('mail_encryption', $settings['mail_secure'] ?? 'none') ?: 'none';
if (!in_array($encryption, ['none', 'ssl', 'tls'], true)) {
    $encryption = 'none';
}
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_mail_settings_title'),
    'subtitle' => return_translation('admin_mail_settings_subtitle'),
    'actions' => '<a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="' . htmlSC(base_href('/admin/settings/mail/logs')) . '"><i class="ci-list"></i>' . htmlSC(return_translation('admin_mail_logs_title')) . '</a>',
]) ?>

    <?= view()->renderPartial('admin/settings_tabs', ['active' => 'mail']) ?>

    <?php if (!$mail_configured): ?>
        <div class="alert alert-warning mb-4"><?= print_translation('admin_mail_not_configured') ?></div>
    <?php endif; ?>

    <form class="border rounded-5 p-3 p-md-4 mb-4" action="<?= base_href('/admin/settings/mail') ?>" method="post">
        <?= get_csrf_field() ?>
        <input type="hidden" name="mail_action" value="save">
        <div class="row g-3">
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="mail_enabled" value="0">
                    <input id="mail-enabled" class="form-check-input" type="checkbox" name="mail_enabled" value="1" <?= $mailEnabled ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="mail-enabled"><?= print_translation('admin_mail_enabled') ?></label>
                </div>
            </div>
            <div class="col-md-7">
                <label class="form-label" for="mail-host"><?= print_translation('admin_mail_host') ?></label>
                <input id="mail-host" class="form-control <?= get_validation_class('mail_host') ?>" type="text" name="mail_host" value="<?= htmlSC($value('mail_host')) ?>">
                <?= get_errors('mail_host') ?>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="mail-port"><?= print_translation('admin_mail_port') ?></label>
                <input id="mail-port" class="form-control <?= get_validation_class('mail_port') ?>" type="number" name="mail_port" value="<?= htmlSC($value('mail_port', '587')) ?>" min="1" max="65535">
                <?= get_errors('mail_port') ?>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="mail-encryption"><?= print_translation('admin_mail_encryption') ?></label>
                <select id="mail-encryption" class="form-select" name="mail_encryption">
                    <?php foreach (['none', 'ssl', 'tls'] as $option): ?>
                        <option value="<?= $option ?>" <?= $encryption === $option ? 'selected' : '' ?>><?= $option ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="mail-username"><?= print_translation('admin_mail_username') ?></label>
                <input id="mail-username" class="form-control" type="text" name="mail_username" value="<?= htmlSC($value('mail_username')) ?>" autocomplete="off">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="mail-password"><?= print_translation('admin_mail_password') ?></label>
                <input id="mail-password" class="form-control" type="password" name="mail_password" value="" autocomplete="new-password" placeholder="<?= htmlSC(return_translation('admin_mail_password_placeholder')) ?>">
                <div class="form-text"><?= print_translation('admin_mail_password_hint') ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="mail-from-email"><?= print_translation('admin_mail_from_email') ?></label>
                <input id="mail-from-email" class="form-control <?= get_validation_class('mail_from_email') ?>" type="email" name="mail_from_email" value="<?= htmlSC($value('mail_from_email')) ?>">
                <?= get_errors('mail_from_email') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="mail-from-name"><?= print_translation('admin_mail_from_name') ?></label>
                <input id="mail-from-name" class="form-control" type="text" name="mail_from_name" value="<?= htmlSC($value('mail_from_name')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="mail-reply-to"><?= print_translation('admin_mail_reply_to_email') ?></label>
                <input id="mail-reply-to" class="form-control <?= get_validation_class('mail_reply_to_email') ?>" type="email" name="mail_reply_to_email" value="<?= htmlSC($value('mail_reply_to_email')) ?>">
                <?= get_errors('mail_reply_to_email') ?>
            </div>
            <div class="col-12">
                <hr class="my-2">
                <h2 class="h5 mb-3"><?= print_translation('admin_security_settings_title') ?></h2>
                <div class="row g-3">
                    <?php foreach ([
                        'allow_email_password_reset' => 'admin_security_allow_email_password_reset',
                        'allow_2fa_email_recovery' => 'admin_security_allow_2fa_email_recovery',
                        'allow_admin_reset_user_2fa' => 'admin_security_allow_admin_reset_user_2fa',
                        'require_admin_password_for_2fa_reset' => 'admin_security_require_admin_password_for_2fa_reset',
                        'require_admin_2fa_for_2fa_reset' => 'admin_security_require_admin_2fa_for_2fa_reset',
                        'notify_user_after_admin_2fa_reset' => 'admin_security_notify_user_after_admin_2fa_reset',
                    ] as $settingKey => $labelKey): ?>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input type="hidden" name="<?= htmlSC($settingKey) ?>" value="0">
                                <input id="<?= htmlSC($settingKey) ?>" class="form-check-input" type="checkbox" name="<?= htmlSC($settingKey) ?>" value="1" <?= $value($settingKey, '1') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="<?= htmlSC($settingKey) ?>"><?= print_translation($labelKey) ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-save"></i><?= print_translation('admin_btn_save') ?></button>
            </div>
        </div>
    </form>

    <form class="border rounded-5 p-3 p-md-4" action="<?= base_href('/admin/settings/mail') ?>" method="post">
        <?= get_csrf_field() ?>
        <input type="hidden" name="mail_action" value="test">
        <div class="row g-3 align-items-end">
            <div class="col-md-8">
                <label class="form-label" for="mail-test-email"><?= print_translation('admin_mail_test_email') ?></label>
                <input id="mail-test-email" class="form-control" type="email" name="test_email" required>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-send"></i><?= print_translation('admin_mail_send_test') ?></button>
            </div>
        </div>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
