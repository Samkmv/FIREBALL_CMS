<?= view()->renderPartial('admin/shell_open', [
    'title' => FireballPluginToyCarRental::t('toy_rental_settings_title'),
    'subtitle' => FireballPluginToyCarRental::t('toy_rental_settings_subtitle'),
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <?php
    $notificationUsers = $notificationUsers ?? [];
    $selectedNotificationUserIds = array_map('intval', $selectedNotificationUserIds ?? []);
    $customSoundUrl = (string)($customSoundUrl ?? '');
    ?>
    <form class="fb-card p-3 p-md-4 toy-rental-settings" action="<?= base_href('/admin/toy-rental/settings') ?>" method="post" enctype="multipart/form-data">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="toyOperatorRole"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_operator_role')) ?></label>
                <select class="form-select" id="toyOperatorRole" name="operator_role_id">
                    <option value="0"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_operator_role_none')) ?></option>
                    <?php foreach ($operatorRoles as $role): ?>
                        <option value="<?= (int)$role['id'] ?>" <?= (int)$settings['operator_role_id'] === (int)$role['id'] ? 'selected' : '' ?>><?= htmlSC(FireballPluginToyCarRental::operatorRoleLabel($role)) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_operator_role_hint')) ?></div>
            </div>
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
                <div class="form-text"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_billing_rounding_hint')) ?></div>
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
            <div class="col-12">
                <label class="form-label" for="toyCustomSound"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_custom_sound')) ?></label>
                <input class="form-control" id="toyCustomSound" type="file" name="custom_sound" accept=".mp3,.wav,.ogg,.m4a,audio/mpeg,audio/wav,audio/x-wav,audio/ogg,audio/mp4,audio/x-m4a" data-max-size="5242880" aria-describedby="toyCustomSoundHint">
                <div class="form-text" id="toyCustomSoundHint"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_custom_sound_hint')) ?></div>
                <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
                    <button class="btn btn-outline-secondary" type="button" data-toy-rental-sound-preview data-sound-url="<?= htmlSC($customSoundUrl) ?>" data-error-message="<?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_sound_preview_error')) ?>" <?= $customSoundUrl === '' ? 'disabled' : '' ?>>
                        <i class="ci-play me-2" aria-hidden="true"></i><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_sound_preview')) ?>
                    </button>
                    <?php if ($customSoundUrl !== ''): ?>
                        <span class="small text-body-secondary text-break"><?= htmlSC((string)($settings['custom_sound_name'] ?? '')) ?></span>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="remove_custom_sound" value="1" id="toyRemoveCustomSound" data-toy-rental-sound-reset>
                            <label class="form-check-label" for="toyRemoveCustomSound"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_remove_custom_sound')) ?></label>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-text text-danger" data-toy-rental-sound-preview-status role="status" aria-live="polite"></div>
            </div>
            <fieldset class="col-12">
                <legend class="form-label fs-6 mb-2" id="toyNotificationUsersLabel"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_notification_users')) ?></legend>
                <input type="hidden" name="notification_recipients_present" value="1">
                <div class="form-text mt-0 mb-3" id="toyNotificationUsersHint"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_notification_users_hint')) ?></div>
                <input class="form-control mb-3" type="search" data-toy-rental-recipient-search placeholder="<?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_notification_users_search')) ?>" aria-label="<?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_notification_users_search')) ?>" aria-describedby="toyNotificationUsersHint" <?= !$notificationUsers ? 'disabled' : '' ?>>
                <div class="toy-rental-notification-users" data-toy-rental-recipient-list aria-labelledby="toyNotificationUsersLabel">
                    <?php foreach ($notificationUsers as $notificationUser): ?>
                        <?php
                        $notificationUserId = (int)$notificationUser['id'];
                        $notificationUserLogin = trim((string)($notificationUser['login'] ?? ''));
                        $notificationUserName = trim((string)($notificationUser['name'] ?? '')) ?: $notificationUserLogin;
                        $notificationUserRole = FireballPluginToyCarRental::notificationUserRoleLabel($notificationUser);
                        $notificationUserRoleClass = match ((string)($notificationUser['role'] ?? '')) {
                            'creator' => 'text-success bg-success-subtle',
                            'admin' => 'text-warning bg-warning-subtle',
                            default => 'text-secondary bg-secondary-subtle',
                        };
                        ?>
                        <label class="toy-rental-recipient-row" data-toy-rental-recipient data-search-text="<?= htmlSC($notificationUserName . ' ' . $notificationUserLogin . ' ' . $notificationUserRole) ?>">
                            <input class="form-check-input m-0" type="checkbox" name="notification_user_ids[]" value="<?= $notificationUserId ?>" <?= in_array($notificationUserId, $selectedNotificationUserIds, true) ? 'checked' : '' ?>>
                            <span class="toy-rental-recipient-identity">
                                <span class="d-block fw-medium"><?= htmlSC($notificationUserName) ?></span>
                                <?php if ($notificationUserLogin !== ''): ?><span class="d-block small text-body-secondary">@<?= htmlSC($notificationUserLogin) ?></span><?php endif; ?>
                            </span>
                            <span class="badge toy-rental-recipient-role <?= $notificationUserRoleClass ?>"><?= htmlSC($notificationUserRole) ?></span>
                        </label>
                    <?php endforeach; ?>
                    <?php if (!$notificationUsers): ?><div class="p-3 text-body-secondary"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_notification_users_empty')) ?></div><?php endif; ?>
                    <div class="p-3 text-body-secondary" data-toy-rental-recipient-no-results hidden><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_notification_users_no_results')) ?></div>
                </div>
            </fieldset>
            <input type="hidden" name="notification_recipients_complete" value="1">
        </div>
        <button class="btn btn-primary mt-4" type="submit"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_settings_save')) ?></button>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
