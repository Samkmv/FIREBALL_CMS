<?php
$t = static fn(string $key): string => htmlSC(FireballPluginCalendar::t($key));
$isAdminContext = !empty($admin_context);
$canManage = !empty($can_manage);
$pushReady = !empty($push_status['global_enabled'])
    && !empty($push_status['vapid_ready'])
    && !empty($push_status['secure_context'])
    && !empty($push_status['user_enabled'])
    && (int)($push_status['active_subscriptions'] ?? 0) > 0;
$labelKeys = [
    'today', 'month', 'week', 'day', 'list', 'loading', 'empty_day', 'no_upcoming', 'more', 'event_create', 'event_edit',
    'saved', 'deleted', 'duplicated', 'delete_confirm', 'error_load', 'error_generic', 'retry', 'event_count',
    'status_scheduled', 'status_completed', 'status_cancelled', 'shared_badge', 'repeating_badge',
    'unit_minutes', 'unit_hours', 'unit_days', 'remove_reminder', 'mark_complete', 'restore_scheduled',
    'day_sun', 'day_mon', 'day_tue', 'day_wed', 'day_thu', 'day_fri', 'day_sat',
];
$labels = [];
foreach ($labelKeys as $key) {
    $labels[$key] = FireballPluginCalendar::t('calendar_' . $key);
}
$config = [
    'locale' => current_locale(),
    'timezone' => date_default_timezone_get(),
    'isAdmin' => (bool)$is_admin,
    'canManage' => $canManage,
    'eventsUrl' => (string)$events_url,
    'csrf' => (string)session()->get('needCSRFToken', ''),
    'labels' => $labels,
];
?>

<?php if ($isAdminContext): ?>
    <?= view()->renderPartial('admin/shell_open', [
        'title' => FireballPluginCalendar::t('calendar_title'),
        'subtitle' => FireballPluginCalendar::t('calendar_subtitle'),
        'show_header' => false,
        'content_class' => 'fb-content--edge-workspace',
    ]) ?>
<?php endif; ?>

<main class="fb-calendar-page <?= $isAdminContext ? 'fb-calendar-page--admin' : '' ?>" data-calendar-app>
    <section class="fb-calendar-hero">
        <div class="fb-calendar-frame fb-calendar-frame--hero py-4">
            <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-4">
                <div>
                    <div class="fb-calendar-eyebrow"><i class="ci-bell"></i> <?= $t('calendar_reminders') ?></div>
                    <h1 class="display-6 fw-semibold mb-2"><?= $t('calendar_title') ?></h1>
                    <p class="text-body-secondary fs-lg mb-0"><?= $t('calendar_subtitle') ?></p>
                </div>
                <?php if ($canManage): ?>
                    <button class="btn btn-dark btn-lg rounded-pill d-inline-flex align-items-center justify-content-center gap-2" type="button" data-calendar-create>
                        <i class="ci-plus"></i>
                        <span><?= $t('calendar_new_event') ?></span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <div class="fb-calendar-frame fb-calendar-frame--content pb-4">
        <div class="fb-calendar-status-card <?= $pushReady ? 'is-ready' : 'is-off' ?>">
            <span class="fb-calendar-status-card__icon"><i class="<?= $pushReady ? 'ci-bell' : 'ci-bell-off' ?>"></i></span>
            <div class="min-w-0">
                <strong class="d-block"><?= $t($pushReady ? 'calendar_push_ready' : 'calendar_push_off') ?></strong>
                <span class="small text-body-secondary"><?= $t($pushReady ? 'calendar_push_ready_hint' : 'calendar_push_off_hint') ?></span>
            </div>
            <?php if (!$pushReady): ?>
                <a class="btn btn-sm btn-outline-secondary rounded-pill ms-lg-auto" href="<?= base_href('/profile') ?>"><?= $t('calendar_open_profile') ?></a>
            <?php endif; ?>
        </div>

        <div class="fb-calendar-shell">
            <header class="fb-calendar-toolbar">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-outline-secondary rounded-pill" type="button" data-calendar-today><?= $t('calendar_today') ?></button>
                    <div class="btn-group fb-calendar-arrows" role="group">
                        <button class="btn btn-outline-secondary" type="button" data-calendar-prev aria-label="<?= $t('calendar_previous') ?>"><i class="ci-chevron-left"></i></button>
                        <button class="btn btn-outline-secondary" type="button" data-calendar-next aria-label="<?= $t('calendar_next') ?>"><i class="ci-chevron-right"></i></button>
                    </div>
                    <h2 class="fb-calendar-period mb-0" data-calendar-period></h2>
                </div>
                <div class="fb-calendar-view-switch" role="group" aria-label="<?= $t('calendar_title') ?>">
                    <?php foreach (['month', 'week', 'day', 'list'] as $view): ?>
                        <button class="btn" type="button" data-calendar-view="<?= $view ?>"><?= $t('calendar_' . $view) ?></button>
                    <?php endforeach; ?>
                </div>
            </header>

            <div class="fb-calendar-body">
                <section class="fb-calendar-main" aria-live="polite">
                    <div class="fb-calendar-loading" data-calendar-loading>
                        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                        <span><?= $t('calendar_loading') ?></span>
                    </div>
                    <div data-calendar-stage></div>
                </section>

                <aside class="fb-calendar-aside">
                    <div class="fb-calendar-aside__heading">
                        <div>
                            <span class="small text-uppercase text-body-tertiary fw-semibold"><?= $t('calendar_upcoming') ?></span>
                            <div class="fw-semibold mt-1" data-calendar-upcoming-count></div>
                        </div>
                        <span class="fb-calendar-aside__icon"><i class="ci-clock"></i></span>
                    </div>
                    <div class="fb-calendar-upcoming" data-calendar-upcoming></div>
                </aside>
            </div>
        </div>
    </div>

    <div class="modal fade" id="calendarEventModal" tabindex="-1" aria-labelledby="calendarEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content fb-calendar-modal">
                <form data-calendar-form>
                    <div class="modal-header border-0 px-4 px-md-5 pt-4 pb-2">
                        <div>
                            <div class="fb-calendar-modal__eyebrow"><?= $t('calendar_details') ?></div>
                            <h2 class="modal-title h4" id="calendarEventModalLabel" data-calendar-modal-title><?= $t('calendar_event_create') ?></h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $t('calendar_close') ?>"></button>
                    </div>
                    <div class="modal-body px-4 px-md-5 py-4">
                        <input type="hidden" name="id" value="">
                        <div class="row g-4">
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="calendar-title"><?= $t('calendar_field_title') ?></label>
                                <div class="fb-calendar-title-input">
                                    <input class="form-control form-control-lg" id="calendar-title" name="title" maxlength="160" placeholder="<?= $t('calendar_title_placeholder') ?>" required>
                                    <input class="form-control form-control-color" name="color" type="color" value="#6f5ef9" title="<?= $t('calendar_field_color') ?>">
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="calendar-description"><?= $t('calendar_field_description') ?></label>
                                <textarea class="form-control" id="calendar-description" name="description" rows="3" maxlength="5000" placeholder="<?= $t('calendar_description_placeholder') ?>"></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-check form-switch fb-calendar-switch">
                                    <input class="form-check-input" type="checkbox" name="all_day" value="1">
                                    <span class="form-check-label fw-semibold"><?= $t('calendar_all_day') ?></span>
                                </label>
                            </div>

                            <div class="col-md-6">
                                <div class="fb-calendar-date-box">
                                    <div class="fw-semibold mb-3"><i class="ci-log-in me-2 text-body-tertiary"></i><?= $t('calendar_start') ?></div>
                                    <div class="row g-2">
                                        <div class="col-7">
                                            <label class="form-label small" for="calendar-start-date"><?= $t('calendar_date') ?></label>
                                            <div class="position-relative fb-calendar-picker-field">
                                                <input class="form-control form-icon-end" id="calendar-start-date" type="text" name="start_date" inputmode="numeric" autocomplete="off" data-calendar-date-picker required>
                                                <i class="ci-calendar position-absolute top-50 end-0 translate-middle-y me-3" aria-hidden="true"></i>
                                            </div>
                                        </div>
                                        <div class="col-5" data-calendar-time-field>
                                            <label class="form-label small" for="calendar-start-time"><?= $t('calendar_time') ?></label>
                                            <div class="position-relative fb-calendar-picker-field">
                                                <input class="form-control form-icon-end" id="calendar-start-time" type="text" name="start_time" value="09:00" inputmode="numeric" autocomplete="off" data-calendar-time-picker required>
                                                <i class="ci-clock position-absolute top-50 end-0 translate-middle-y me-3" aria-hidden="true"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="fb-calendar-date-box">
                                    <div class="fw-semibold mb-3"><i class="ci-log-out me-2 text-body-tertiary"></i><?= $t('calendar_end') ?></div>
                                    <div class="row g-2">
                                        <div class="col-7">
                                            <label class="form-label small" for="calendar-end-date"><?= $t('calendar_date') ?></label>
                                            <div class="position-relative fb-calendar-picker-field">
                                                <input class="form-control form-icon-end" id="calendar-end-date" type="text" name="end_date" inputmode="numeric" autocomplete="off" data-calendar-date-picker required>
                                                <i class="ci-calendar position-absolute top-50 end-0 translate-middle-y me-3" aria-hidden="true"></i>
                                            </div>
                                        </div>
                                        <div class="col-5" data-calendar-time-field>
                                            <label class="form-label small" for="calendar-end-time"><?= $t('calendar_time') ?></label>
                                            <div class="position-relative fb-calendar-picker-field">
                                                <input class="form-control form-icon-end" id="calendar-end-time" type="text" name="end_time" value="10:00" inputmode="numeric" autocomplete="off" data-calendar-time-picker required>
                                                <i class="ci-clock position-absolute top-50 end-0 translate-middle-y me-3" aria-hidden="true"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="calendar-recurrence"><?= $t('calendar_recurrence') ?></label>
                                <select class="form-select" id="calendar-recurrence" name="recurrence">
                                    <?php foreach (['none', 'daily', 'weekly', 'monthly', 'yearly'] as $value): ?>
                                        <option value="<?= $value ?>"><?= $t('calendar_recurrence_' . $value) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6" data-calendar-recurrence-until hidden>
                                <label class="form-label" for="calendar-recurrence-until"><?= $t('calendar_recurrence_until') ?></label>
                                <div class="position-relative fb-calendar-picker-field">
                                    <input class="form-control form-icon-end" id="calendar-recurrence-until" type="text" name="recurrence_until" inputmode="numeric" autocomplete="off" data-calendar-date-picker>
                                    <i class="ci-calendar position-absolute top-50 end-0 translate-middle-y me-3" aria-hidden="true"></i>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="calendar-status"><?= $t('calendar_status') ?></label>
                                <select class="form-select" id="calendar-status" name="status">
                                    <?php foreach (['scheduled', 'completed', 'cancelled'] as $value): ?>
                                        <option value="<?= $value ?>"><?= $t('calendar_status_' . $value) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if ($is_admin): ?>
                                <div class="col-md-6">
                                    <label class="form-label" for="calendar-visibility"><?= $t('calendar_visibility') ?></label>
                                    <select class="form-select" id="calendar-visibility" name="visibility">
                                        <?php foreach (['personal', 'all', 'role', 'users'] as $value): ?>
                                            <option value="<?= $value ?>"><?= $t('calendar_visibility_' . $value) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12" data-calendar-audience-role hidden>
                                    <label class="form-label" for="calendar-audience-role"><?= $t('calendar_select_role') ?></label>
                                    <select class="form-select" id="calendar-audience-role" name="audience_role">
                                        <option value=""></option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?= htmlSC((string)$role['slug']) ?>"><?= htmlSC((string)$role['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12" data-calendar-audience-users hidden>
                                    <label class="form-label" for="calendar-audience-users"><?= $t('calendar_select_users') ?></label>
                                    <select class="form-select" id="calendar-audience-users" name="audience_user_ids[]" multiple size="5">
                                        <?php foreach ($users as $item): ?>
                                            <option value="<?= (int)$item['id'] ?>"><?= htmlSC((string)$item['name']) ?> · @<?= htmlSC((string)$item['login']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <input type="hidden" name="visibility" value="personal">
                            <?php endif; ?>

                            <div class="col-12">
                                <div class="fb-calendar-reminders-panel">
                                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                                        <div>
                                            <h3 class="h6 mb-1"><i class="ci-bell me-2"></i><?= $t('calendar_reminders') ?></h3>
                                            <p class="small text-body-secondary mb-0"><?= $t('calendar_reminder_hint') ?></p>
                                        </div>
                                        <button class="btn btn-sm btn-outline-secondary rounded-pill" type="button" data-calendar-add-reminder><i class="ci-plus me-1"></i><?= $t('calendar_add_reminder') ?></button>
                                    </div>
                                    <div class="fb-calendar-quick-reminders mb-3">
                                        <span class="small text-body-secondary me-1"><?= $t('calendar_quick_reminders') ?>:</span>
                                        <button type="button" data-calendar-quick-reminder="10:days:09:00"><?= $t('calendar_quick_10_days') ?></button>
                                        <button type="button" data-calendar-quick-reminder="3:days:09:00"><?= $t('calendar_quick_3_days') ?></button>
                                        <button type="button" data-calendar-quick-reminder="1:days:09:00"><?= $t('calendar_quick_1_day') ?></button>
                                        <button type="button" data-calendar-quick-reminder="1:hours:"><?= $t('calendar_quick_1_hour') ?></button>
                                    </div>
                                    <div class="vstack gap-2" data-calendar-reminders></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 px-4 px-md-5 pb-4 pt-2">
                        <div class="dropdown me-auto" data-calendar-more-actions hidden>
                            <button class="btn btn-outline-secondary rounded-pill dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="ci-more-horizontal"></i></button>
                            <ul class="dropdown-menu">
                                <li><button class="dropdown-item" type="button" data-calendar-duplicate><i class="ci-copy me-2"></i><?= $t('calendar_duplicate') ?></button></li>
                                <li><button class="dropdown-item" type="button" data-calendar-toggle-status><i class="ci-check-circle me-2"></i><span><?= $t('calendar_mark_complete') ?></span></button></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><button class="dropdown-item text-danger" type="button" data-calendar-delete><i class="ci-trash me-2"></i><?= $t('calendar_delete') ?></button></li>
                            </ul>
                        </div>
                        <button class="btn btn-outline-secondary rounded-pill" type="button" data-bs-dismiss="modal"><?= $t('calendar_cancel') ?></button>
                        <button class="btn btn-dark rounded-pill px-4" type="submit" data-calendar-save><span><?= $t('calendar_save') ?></span></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <template data-calendar-reminder-template>
        <div class="fb-calendar-reminder-row" data-calendar-reminder-row>
            <input type="hidden" data-reminder-id value="0">
            <div class="fb-calendar-reminder-row__value">
                <span class="small text-body-secondary"><?= $t('calendar_remind_before') ?></span>
                <input class="form-control" type="number" min="1" max="10" value="1" data-reminder-value aria-label="<?= $t('calendar_remind_before') ?>">
                <select class="form-select" data-reminder-unit>
                    <option value="days"><?= $t('calendar_unit_days') ?></option>
                    <option value="hours"><?= $t('calendar_unit_hours') ?></option>
                    <option value="minutes"><?= $t('calendar_unit_minutes') ?></option>
                </select>
            </div>
            <label class="fb-calendar-reminder-row__time"><span class="small text-body-secondary"><?= $t('calendar_at_time') ?></span><input class="form-control" type="time" value="09:00" data-reminder-time></label>
            <div class="fb-calendar-reminder-row__channels">
                <label class="form-check"><input class="form-check-input" type="checkbox" checked data-reminder-site><span class="form-check-label"><?= $t('calendar_channel_site') ?></span></label>
                <label class="form-check"><input class="form-check-input" type="checkbox" checked data-reminder-push><span class="form-check-label"><?= $t('calendar_channel_push') ?></span></label>
            </div>
            <button class="btn btn-icon btn-ghost-danger rounded-circle" type="button" data-calendar-remove-reminder aria-label="<?= $t('calendar_remove_reminder') ?>"><i class="ci-trash"></i></button>
        </div>
    </template>

    <script type="application/json" data-calendar-config><?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</main>

<?php if ($isAdminContext): ?>
    <?= view()->renderPartial('admin/shell_close') ?>
<?php endif; ?>
