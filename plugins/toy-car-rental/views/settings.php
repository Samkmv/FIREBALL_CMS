<?= view()->renderPartial('admin/shell_open', [
    'title' => FireballPluginToyCarRental::t('toy_rental_settings_title'),
    'subtitle' => FireballPluginToyCarRental::t('toy_rental_settings_subtitle'),
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <form class="fb-card p-3 p-md-4" action="<?= base_href('/admin/toy-rental/settings') ?>" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="toyFixedDurations"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_fixed_durations')) ?></label>
                <input class="form-control" id="toyFixedDurations" type="text" name="fixed_durations" required value="<?= htmlSC(implode(', ', $settings['fixed_durations'])) ?>" placeholder="5, 10, 15, 30">
                <div class="form-text"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_fixed_durations_hint')) ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="toyMaxRide"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_max_ride')) ?></label>
                <input class="form-control" id="toyMaxRide" type="number" name="max_ride_minutes" min="1" max="1440" required value="<?= (int)$settings['max_ride_minutes'] ?>">
                <div class="form-text"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_max_ride_hint')) ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_default_duration')) ?></label>
                <input class="form-control" type="number" name="default_duration" min="1" step="1" value="<?= (int)$settings['default_duration'] ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_default_minute_price')) ?></label>
                <input class="form-control" type="number" name="default_minute_price" min="0" step="0.01" value="<?= htmlSC((string)$settings['default_minute_price']) ?>">
                <div class="form-text"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_fixed_price_formula')) ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_currency')) ?></label>
                <input class="form-control" type="text" name="currency" value="<?= htmlSC((string)$settings['currency']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_auto_refresh')) ?></label>
                <input class="form-control" type="number" name="auto_refresh_seconds" min="0" step="1" value="<?= (int)$settings['auto_refresh_seconds'] ?>">
                <div class="form-text"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_auto_refresh_hint')) ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label d-none d-md-block">&nbsp;</label>
                <div class="form-check form-switch mb-0 py-2">
                    <input class="form-check-input" type="checkbox" name="sound_enabled" value="1" id="toySound" <?= !empty($settings['sound_enabled']) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="toySound"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_sound')) ?></label>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label d-none d-md-block">&nbsp;</label>
                <div class="form-check form-switch mb-0 py-2">
                    <input class="form-check-input" type="checkbox" name="overdue_push_enabled" value="1" id="toyOverduePush" <?= !empty($settings['overdue_push_enabled']) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-medium" for="toyOverduePush"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_overdue_push')) ?></label>
                    <div class="form-text"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_overdue_push_hint')) ?></div>
                </div>
            </div>
        </div>
        <button class="btn btn-primary mt-4" type="submit"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_save')) ?></button>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
