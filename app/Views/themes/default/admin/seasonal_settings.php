<?php
$formData = session()->get('form_data') ?: [];
$values = $formData + $settings;
$mode = $values['seasonal_mode'] ?? 'off';
$theme = theme()->getActiveTheme();
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_seasonal_title'),
    'subtitle' => return_translation('admin_seasonal_subtitle'),
]) ?>

<?= view()->renderPartial('admin/settings_tabs', ['active' => 'seasonal']) ?>

<?php if ($theme['slug'] !== 'default'): ?>
    <p class="alert alert-info"><?= print_translation('admin_seasonal_theme_hint') ?></p>
<?php endif; ?>

<form action="<?= htmlSC(base_href('/admin/settings/seasonal')) ?>" method="post" data-seasonal-settings>
    <?= get_csrf_field() ?>
    <section class="border rounded-4 p-3 p-md-4 mb-4" aria-labelledby="winter-city-title">
        <h2 class="h5" id="winter-city-title"><?= print_translation('admin_seasonal_winter_city') ?></h2>
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label" for="seasonal-mode"><?= print_translation('admin_seasonal_mode') ?></label>
                <select class="form-select <?= get_validation_class('seasonal_mode') ?>" name="seasonal_mode" id="seasonal-mode">
                    <?php foreach (\App\Services\SeasonalAppearance::MODES as $option): ?>
                        <option value="<?= htmlSC($option) ?>" <?= $mode === $option ? 'selected' : '' ?>>
                            <?= print_translation('admin_seasonal_mode_' . $option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= get_errors('seasonal_mode') ?>
            </div>
            <?php foreach (['seasonal_start', 'seasonal_end'] as $key): ?>
                <div class="col-md-4">
                    <label class="form-label" for="<?= $key ?>"><?= print_translation('admin_' . $key) ?></label>
                    <input type="date" class="form-control <?= get_validation_class($key) ?>" name="<?= $key ?>" id="<?= $key ?>"
                           value="<?= htmlSC($values[$key] ?? '') ?>" aria-describedby="seasonal-dates-hint" <?= $mode === 'scheduled' ? 'required' : '' ?>>
                    <?= get_errors($key) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <p id="seasonal-dates-hint" class="small text-body-secondary mb-3">
            <?= print_translation('admin_seasonal_dates_hint') ?> (<?= htmlSC(date_default_timezone_get()) ?>).
        </p>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="seasonal-snow-motion" name="seasonal_snow_motion" value="1"
                   <?= ($values['seasonal_snow_motion'] ?? '0') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label" for="seasonal-snow-motion"><?= print_translation('admin_seasonal_animate_snow') ?></label>
        </div>
        <button class="btn btn-primary rounded-pill" type="submit"><?= print_translation('admin_btn_save') ?></button>
    </section>

    <section class="border rounded-4 p-3 p-md-4" aria-labelledby="seasonal-preview-title">
        <h2 class="h5 mb-1" id="seasonal-preview-title"><?= print_translation('admin_seasonal_preview') ?></h2>
        <p class="small text-body-secondary mb-3"><?= print_translation('admin_seasonal_preview_hint') ?></p>
        <div class="d-flex flex-wrap align-items-end gap-3 mb-3">
            <div>
                <label class="form-label" for="seasonal-device"><?= print_translation('admin_seasonal_device') ?></label>
                <select class="form-select" id="seasonal-device">
                    <option value="desktop"><?= print_translation('admin_seasonal_desktop') ?></option>
                    <option value="mobile"><?= print_translation('admin_seasonal_mobile') ?></option>
                </select>
            </div>
            <div>
                <label class="form-label" for="seasonal-theme"><?= print_translation('admin_seasonal_preview_theme') ?></label>
                <select class="form-select" id="seasonal-theme">
                    <option value="light"><?= print_translation('tpl_menu_btn_switch_light') ?></option>
                    <option value="dark"><?= print_translation('tpl_menu_btn_switch_dark') ?></option>
                </select>
            </div>
            <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" role="switch" id="seasonal-preview-enabled" checked>
                <label class="form-check-label" for="seasonal-preview-enabled"><?= print_translation('admin_seasonal_preview_enabled') ?></label>
            </div>
        </div>
        <div data-seasonal-stage>
            <div class="position-relative overflow-hidden rounded-3 bg-body-tertiary mx-auto" data-seasonal-viewport>
                <iframe class="position-absolute top-0 start-0 border-0 pe-none" style="transform-origin: top left"
                        src="<?= htmlSC(base_href('/admin/settings/seasonal/preview')) ?>"
                        title="<?= htmlSC(return_translation('admin_seasonal_preview')) ?>" tabindex="-1" inert></iframe>
            </div>
        </div>
    </section>
</form>

<?= view()->renderPartial('admin/shell_close') ?>
