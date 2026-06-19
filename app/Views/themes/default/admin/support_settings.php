<?php
$formData = session()->get('form_data') ?: [];
$value = static function (string $key, string $default = '') use ($formData, $settings): string {
    return (string)($formData[$key] ?? ($settings[$key] ?? $default));
};
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_support_settings_heading'),
    'subtitle' => return_translation('admin_support_settings_subtitle'),
    'actions' => '',
]) ?>

    <?= view()->renderPartial('admin/support_tabs', ['active' => 'settings']) ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= base_href('/admin/support/settings') ?>" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-4">
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="support_public_enabled" value="0">
                    <input id="support-public-enabled" class="form-check-input" type="checkbox" name="support_public_enabled" value="1" <?= $value('support_public_enabled', '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="support-public-enabled"><?= print_translation('admin_support_public_enabled') ?></label>
                    <div class="form-text"><?= print_translation('admin_support_public_enabled_hint') ?></div>
                </div>
            </div>
            <div class="col-lg-6">
                <label class="form-label" for="support-email"><?= print_translation('admin_support_notification_email') ?></label>
                <input id="support-email" class="form-control <?= get_validation_class('support_notification_email') ?>" type="email" name="support_notification_email" value="<?= htmlSC($value('support_notification_email')) ?>">
                <?= get_errors('support_notification_email') ?>
            </div>
            <div class="col-lg-6">
                <div class="border rounded-4 p-3 h-100">
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="support_notify_new_requests" value="0">
                        <input id="support-notify-new" class="form-check-input" type="checkbox" name="support_notify_new_requests" value="1" <?= $value('support_notify_new_requests', '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="support-notify-new"><?= print_translation('admin_support_notify_new_requests') ?></label>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="support_notify_status_changes" value="0">
                        <input id="support-notify-status" class="form-check-input" type="checkbox" name="support_notify_status_changes" value="1" <?= $value('support_notify_status_changes', '0') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="support-notify-status"><?= print_translation('admin_support_notify_status_changes') ?></label>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="support_autoreply_enabled" value="0">
                    <input id="support-autoreply-enabled" class="form-check-input" type="checkbox" name="support_autoreply_enabled" value="1" <?= $value('support_autoreply_enabled', '0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="support-autoreply-enabled"><?= print_translation('admin_support_autoreply_enabled') ?></label>
                </div>
            </div>
            <div class="col-lg-5">
                <label class="form-label" for="support-autoreply-subject"><?= print_translation('admin_support_autoreply_subject') ?></label>
                <input id="support-autoreply-subject" class="form-control" type="text" name="support_autoreply_subject" value="<?= htmlSC($value('support_autoreply_subject')) ?>" maxlength="190">
            </div>
            <div class="col-lg-7">
                <label class="form-label" for="support-autoreply-message"><?= print_translation('admin_support_autoreply_message') ?></label>
                <textarea id="support-autoreply-message" class="form-control" name="support_autoreply_message" rows="5"><?= htmlSC($value('support_autoreply_message')) ?></textarea>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="support_spam_protection" value="0">
                    <input id="support-spam-protection" class="form-check-input" type="checkbox" name="support_spam_protection" value="1" <?= $value('support_spam_protection', '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="support-spam-protection"><?= print_translation('admin_support_spam_protection') ?></label>
                </div>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-save"></i><?= print_translation('admin_btn_save') ?></button>
            </div>
        </div>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
