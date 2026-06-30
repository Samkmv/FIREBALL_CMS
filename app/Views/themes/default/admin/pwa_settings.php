<?php
$formData = session()->get('form_data') ?: [];
$value = static fn(string $key, string $default = ''): string => (string)($formData[$key] ?? ($settings[$key] ?? $default));
$checked = static fn(string $key, string $default = '0'): string => $value($key, $default) === '1' ? 'checked' : '';
$statusBadge = static function (bool $ok): string {
    return $ok
        ? '<span class="badge fs-xs text-success bg-success-subtle rounded-pill">' . htmlSC(return_translation('admin_pwa_status_ok')) . '</span>'
        : '<span class="badge fs-xs text-danger bg-danger-subtle rounded-pill">' . htmlSC(return_translation('admin_pwa_status_problem')) . '</span>';
};
$imageField = static function (string $name, string $label, string $hint) use ($value): void {
    $id = 'pwa_' . $name;
    ?>
    <label class="form-label" for="<?= htmlSC($id) ?>"><?= htmlSC($label) ?></label>
    <div class="input-group">
        <input class="form-control <?= get_validation_class($name) ?>" type="text" id="<?= htmlSC($id) ?>" name="<?= htmlSC($name) ?>" value="<?= htmlSC($value($name)) ?>" data-file-preview-image="#<?= htmlSC($id) ?>_preview_image" data-file-preview-text="#<?= htmlSC($id) ?>_preview_text">
        <button class="btn btn-outline-secondary" type="button" data-file-manager-open data-file-manager-input="<?= htmlSC($id) ?>" data-file-manager-dir="branding" data-file-manager-url="<?= base_href('/admin/files') ?>">
            <i class="ci-folder me-2"></i><?= print_translation('admin_btn_choose_file') ?>
        </button>
    </div>
    <div class="form-text"><?= htmlSC($hint) ?></div>
    <?= get_errors($name) ?>
    <?php $current = $value($name); ?>
    <div class="d-flex align-items-center gap-3 mt-3 <?= $current === '' ? 'd-none' : '' ?>" id="<?= htmlSC($id) ?>_preview_wrap">
        <span class="border rounded-3 d-inline-flex align-items-center justify-content-center bg-body-tertiary overflow-hidden" style="width: 2.75rem; height: 2.75rem;">
            <img id="<?= htmlSC($id) ?>_preview_image" src="<?= htmlSC($current !== '' ? get_image($current) : '') ?>" alt="" style="width: 100%; height: 100%; object-fit: contain;">
        </span>
        <code class="small text-break" id="<?= htmlSC($id) ?>_preview_text"><?= htmlSC($current) ?></code>
    </div>
    <?php
};
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_pwa_title'),
    'subtitle' => return_translation('admin_pwa_subtitle'),
    'actions' => '',
]) ?>

    <?= view()->renderPartial('admin/settings_tabs', ['active' => 'pwa']) ?>

    <div class="row g-3 mb-4">
        <?php foreach ([
            'https' => return_translation('admin_pwa_status_https'),
            'manifest' => return_translation('admin_pwa_status_manifest'),
            'service_worker' => return_translation('admin_pwa_status_service_worker'),
            'push' => return_translation('admin_pwa_status_push'),
            'vapid' => return_translation('admin_pwa_status_vapid'),
        ] as $key => $label): ?>
            <div class="col-sm-6 col-xl">
                <div class="border rounded-4 p-3 h-100">
                    <div class="small text-body-secondary mb-2"><?= htmlSC($label) ?></div>
                    <?= $statusBadge(!empty($status[$key])) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <form class="border rounded-5 p-3 p-md-4 mb-4" action="<?= base_href('/admin/settings/pwa') ?>" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="pwa_enabled" name="pwa_enabled" value="1" <?= $checked('pwa_enabled', '1') ?>>
                    <label class="form-check-label" for="pwa_enabled"><?= print_translation('admin_pwa_enabled') ?></label>
                </div>
                <div class="form-text"><?= print_translation('admin_pwa_enabled_hint') ?></div>
            </div>
            <div class="col-md-6">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="pwa_push_enabled" name="pwa_push_enabled" value="1" <?= $checked('pwa_push_enabled') ?>>
                    <label class="form-check-label" for="pwa_push_enabled"><?= print_translation('admin_pwa_push_enabled') ?></label>
                </div>
                <div class="form-text"><?= print_translation('admin_pwa_push_enabled_hint') ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="pwa_app_name"><?= print_translation('admin_pwa_app_name') ?></label>
                <input class="form-control" type="text" id="pwa_app_name" name="pwa_app_name" value="<?= htmlSC($value('pwa_app_name')) ?>" maxlength="80">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="pwa_short_name"><?= print_translation('admin_pwa_short_name') ?></label>
                <input class="form-control" type="text" id="pwa_short_name" name="pwa_short_name" value="<?= htmlSC($value('pwa_short_name')) ?>" maxlength="32">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="pwa_orientation"><?= print_translation('admin_pwa_orientation') ?></label>
                <select class="form-select" id="pwa_orientation" name="pwa_orientation">
                    <?php foreach (['any', 'portrait', 'portrait-primary', 'landscape', 'landscape-primary'] as $orientation): ?>
                        <option value="<?= htmlSC($orientation) ?>" <?= $value('pwa_orientation', 'any') === $orientation ? 'selected' : '' ?>><?= htmlSC($orientation) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="pwa_description"><?= print_translation('admin_pwa_description') ?></label>
                <textarea class="form-control" id="pwa_description" name="pwa_description" rows="3" maxlength="240"><?= htmlSC($value('pwa_description')) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="pwa_theme_color"><?= print_translation('admin_pwa_theme_color') ?></label>
                <input class="form-control form-control-color w-100 <?= get_validation_class('pwa_theme_color') ?>" type="color" id="pwa_theme_color" name="pwa_theme_color" value="<?= htmlSC($value('pwa_theme_color', '#181d25')) ?>">
                <?= get_errors('pwa_theme_color') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="pwa_background_color"><?= print_translation('admin_pwa_background_color') ?></label>
                <input class="form-control form-control-color w-100 <?= get_validation_class('pwa_background_color') ?>" type="color" id="pwa_background_color" name="pwa_background_color" value="<?= htmlSC($value('pwa_background_color', '#ffffff')) ?>">
                <?= get_errors('pwa_background_color') ?>
            </div>
            <div class="col-md-6">
                <?php $imageField('pwa_logo', return_translation('admin_pwa_logo'), return_translation('admin_pwa_logo_hint')); ?>
            </div>
            <div class="col-md-6">
                <?php $imageField('pwa_startup_image', return_translation('admin_pwa_startup_image'), return_translation('admin_pwa_startup_image_hint')); ?>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-4">
            <button class="btn btn-primary rounded-pill" type="submit">
                <i class="ci-save me-2"></i><?= print_translation('admin_btn_save') ?>
            </button>
            <button class="btn btn-outline-secondary rounded-pill" type="button" data-pwa-enable-push>
                <i class="ci-bell me-2"></i><?= print_translation('admin_pwa_subscribe_device') ?>
            </button>
        </div>
    </form>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="border rounded-5 p-3 p-md-4 h-100">
                <h2 class="h5 mb-3"><?= print_translation('admin_pwa_vapid_title') ?></h2>
                <label class="form-label"><?= print_translation('admin_pwa_vapid_public_key') ?></label>
                <textarea class="form-control font-monospace small mb-3" rows="4" readonly><?= htmlSC($settings['pwa_vapid_public_key'] ?? '') ?></textarea>
                <label class="form-label"><?= print_translation('admin_pwa_vapid_private_key') ?></label>
                <textarea class="form-control font-monospace small" rows="4" readonly><?= htmlSC($settings['pwa_vapid_private_key'] ?? '') ?></textarea>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <form action="<?= base_href('/admin/settings/pwa/vapid') ?>" method="post">
                        <?= get_csrf_field() ?>
                        <button class="btn btn-outline-secondary rounded-pill" type="submit">
                            <i class="ci-refresh-cw me-2"></i><?= print_translation('admin_pwa_generate_vapid') ?>
                        </button>
                    </form>
                    <form action="<?= base_href('/admin/settings/pwa/test-push') ?>" method="post">
                        <?= get_csrf_field() ?>
                        <button class="btn btn-outline-primary rounded-pill" type="submit">
                            <i class="ci-send me-2"></i><?= print_translation('admin_pwa_test_push') ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="border rounded-5 p-3 p-md-4 h-100">
                <h2 class="h5 mb-3"><?= print_translation('admin_pwa_devices_title') ?></h2>
                <?php if (empty($devices)): ?>
                    <div class="text-body-secondary text-center py-4"><?= print_translation('admin_table_empty') ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th><?= print_translation('admin_pwa_device') ?></th><th><?= print_translation('admin_pwa_platform') ?></th><th class="text-nowrap"><?= print_translation('admin_pwa_last_seen') ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($devices as $device): ?>
                                <tr>
                                    <td><div class="text-break small"><?= htmlSC((string)($device['user_agent'] ?? '')) ?></div></td>
                                    <td><?= htmlSC((string)($device['platform'] ?? '')) ?> / <?= htmlSC((string)($device['browser'] ?? '')) ?></td>
                                    <td class="text-nowrap"><?= htmlSC((string)($device['last_seen_at'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-12">
            <div class="border rounded-5 p-3 p-md-4">
                <h2 class="h5 mb-3"><?= print_translation('admin_pwa_notifications_title') ?></h2>
                <?php if (empty($notifications)): ?>
                    <div class="text-body-secondary text-center py-4"><?= print_translation('admin_table_empty') ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th><?= print_translation('admin_pwa_notification') ?></th><th><?= print_translation('admin_pwa_status') ?></th><th class="text-nowrap"><?= print_translation('admin_pwa_sent') ?></th><th class="text-nowrap"><?= print_translation('admin_pwa_date') ?></th></tr></thead>
                            <tbody>
                            <?php foreach ($notifications as $notification): ?>
                                <tr>
                                    <td><div class="fw-medium"><?= htmlSC((string)$notification['title']) ?></div><div class="small text-body-secondary"><?= htmlSC((string)($notification['body'] ?? '')) ?></div></td>
                                    <td><?= htmlSC((string)$notification['status']) ?></td>
                                    <td class="text-nowrap"><?= (int)$notification['sent_count'] ?> / <?= (int)$notification['failed_count'] ?></td>
                                    <td class="text-nowrap"><?= htmlSC((string)$notification['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
