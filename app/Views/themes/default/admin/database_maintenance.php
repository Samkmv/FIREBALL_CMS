<?php
$actions = (array)($actions ?? []);
$safeActions = (array)($actions['safe'] ?? []);
$dangerousActions = (array)($actions['dangerous'] ?? []);
$logs = (array)($logs ?? []);
$logsTotal = max(0, (int)($logs_total ?? count($logs)));
$logsPagination = $logs_pagination ?? null;
$confirmationPhrase = (string)($confirmation_phrase ?? 'СБРОСИТЬ FIREBALL');

$actionLabel = static fn(string $action): string => return_translation('admin_maintenance_action_' . $action);
$actionDescription = static fn(string $action): string => return_translation('admin_maintenance_action_' . $action . '_desc');
?>

<?php ob_start(); ?>
<a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin') ?>">
    <i class="ci-arrow-left"></i><?= print_translation('admin_analytics_back_to_dashboard') ?>
</a>
<?php $adminPageActions = ob_get_clean(); ?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_maintenance_heading'),
    'subtitle' => return_translation('admin_maintenance_subtitle'),
    'actions' => $adminPageActions,
]) ?>

    <div class="alert alert-warning rounded-4 mb-4">
        <div class="fw-semibold mb-1"><?= print_translation('admin_maintenance_warning_title') ?></div>
        <div><?= print_translation('admin_maintenance_warning_text') ?></div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-6">
            <div class="border rounded-5 p-3 p-md-4 h-100">
                <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                    <div>
                        <h2 class="h5 mb-1"><?= print_translation('admin_maintenance_safe_title') ?></h2>
                        <p class="text-body-secondary mb-0"><?= print_translation('admin_maintenance_safe_subtitle') ?></p>
                    </div>
                    <span class="badge text-bg-success rounded-pill"><?= print_translation('admin_maintenance_safe_badge') ?></span>
                </div>

                <div class="d-grid gap-3">
                    <?php foreach ($safeActions as $action): ?>
                        <form class="border rounded-4 p-3 d-flex flex-column flex-sm-row align-items-stretch align-items-sm-start justify-content-between gap-3 admin-maintenance-action-card" action="<?= base_href('/admin/system/database-maintenance/run') ?>" method="post">
                            <?= get_csrf_field() ?>
                            <input type="hidden" name="action" value="<?= htmlSC($action) ?>">
                            <div class="min-w-0 admin-maintenance-action-card__content">
                                <div class="fw-semibold"><?= htmlSC($actionLabel($action)) ?></div>
                                <div class="small text-body-secondary text-break"><?= htmlSC($actionDescription($action)) ?></div>
                            </div>
                            <button class="btn btn-outline-secondary rounded-pill flex-shrink-0 admin-maintenance-action-card__button" type="submit">
                                <?= print_translation('admin_maintenance_run') ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="border border-danger-subtle rounded-5 p-3 p-md-4 h-100">
                <div class="d-flex align-items-start justify-content-between gap-3 mb-4">
                    <div>
                        <h2 class="h5 mb-1"><?= print_translation('admin_maintenance_danger_title') ?></h2>
                        <p class="text-body-secondary mb-0"><?= print_translation('admin_maintenance_danger_subtitle') ?></p>
                    </div>
                    <span class="badge text-bg-danger rounded-pill"><?= print_translation('admin_maintenance_danger_badge') ?></span>
                </div>

                <div class="d-grid gap-3">
                    <?php foreach ($dangerousActions as $action): ?>
                        <?php $modalId = 'maintenanceDangerModal' . preg_replace('/[^a-zA-Z0-9]/', '', $action); ?>
                        <div class="border rounded-4 p-3 d-flex flex-column flex-sm-row align-items-stretch align-items-sm-start justify-content-between gap-3 admin-maintenance-action-card">
                            <div class="min-w-0 admin-maintenance-action-card__content">
                                <div class="fw-semibold"><?= htmlSC($actionLabel($action)) ?></div>
                                <div class="small text-body-secondary text-break"><?= htmlSC($actionDescription($action)) ?></div>
                            </div>
                            <button class="btn btn-outline-danger rounded-pill flex-shrink-0 admin-maintenance-action-card__button" type="button" data-bs-toggle="modal" data-bs-target="#<?= htmlSC($modalId) ?>">
                                <?= print_translation('admin_maintenance_open_confirm') ?>
                            </button>
                        </div>

                        <div class="modal fade" id="<?= htmlSC($modalId) ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <form class="modal-content" action="<?= base_href('/admin/system/database-maintenance/run') ?>" method="post" data-maintenance-danger-form data-confirm-phrase="<?= htmlSC($confirmationPhrase) ?>">
                                    <?= get_csrf_field() ?>
                                    <input type="hidden" name="action" value="<?= htmlSC($action) ?>">
                                    <div class="modal-header border-0 pb-0">
                                        <div>
                                            <h3 class="modal-title h5"><?= htmlSC($actionLabel($action)) ?></h3>
                                            <p class="text-body-secondary small mb-0"><?= print_translation('admin_maintenance_backup_required') ?></p>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlSC(return_translation('admin_btn_close')) ?>"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="alert alert-danger rounded-4">
                                            <?= print_translation('admin_maintenance_danger_confirm_text') ?>
                                        </div>
                                        <p class="small text-body-secondary mb-3">
                                            <?= htmlSC($actionDescription($action)) ?>
                                        </p>
                                        <?= view()->renderPartial('incs/password_field', [
                                            'id' => 'maintenance-current-password-' . $action,
                                            'name' => 'current_password',
                                            'label' => return_translation('admin_maintenance_current_password'),
                                            'autocomplete' => 'current-password',
                                            'wrapper_class' => 'mb-3',
                                            'required' => true,
                                        ]) ?>
                                        <div>
                                            <label class="form-label">
                                                <?= print_translation('admin_maintenance_confirmation_phrase') ?>
                                                <code><?= htmlSC($confirmationPhrase) ?></code>
                                            </label>
                                            <input class="form-control" type="text" name="confirmation_phrase" required data-maintenance-confirm-input autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="modal-footer border-0 pt-0">
                                        <button class="btn btn-outline-secondary rounded-pill" type="button" data-bs-dismiss="modal">
                                            <?= print_translation('admin_maintenance_cancel') ?>
                                        </button>
                                        <button class="btn btn-danger rounded-pill" type="submit" data-maintenance-confirm-submit disabled>
                                            <?= print_translation('admin_maintenance_confirm_run') ?>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table>
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3 flex-wrap">
            <div>
                <h2 class="h5 mb-1"><?= print_translation('admin_maintenance_logs_title') ?></h2>
                <p class="text-body-secondary mb-0"><?= print_translation('admin_maintenance_logs_subtitle') ?></p>
            </div>
            <?php if ($logsTotal > 0): ?>
                <form
                    action="<?= base_href('/admin/system/database-maintenance/logs/clear') ?>"
                    method="post"
                    data-admin-delete-form
                    data-delete-message="<?= htmlSC(return_translation('admin_maintenance_logs_clear_confirm')) ?>"
                >
                    <?= get_csrf_field() ?>
                    <button class="btn btn-outline-danger rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                        <i class="ci-trash"></i>
                        <?= print_translation('admin_maintenance_logs_clear') ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if (empty($logs)): ?>
            <div class="admin-table-state"><?= print_translation('admin_maintenance_logs_empty') ?></div>
        <?php else: ?>
            <?php $mobileCards = []; ?>
            <?php ob_start(); ?>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?= print_translation('admin_maintenance_log_date') ?></th>
                        <th><?= print_translation('admin_maintenance_log_user') ?></th>
                        <th><?= print_translation('admin_maintenance_log_action') ?></th>
                        <th><?= print_translation('admin_maintenance_log_result') ?></th>
                        <th><?= print_translation('admin_maintenance_log_backup') ?></th>
                        <th><?= print_translation('admin_maintenance_log_error') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        $isSuccess = (string)($log['result'] ?? '') === 'success';
                        $resultLabel = $isSuccess
                            ? return_translation('admin_maintenance_result_success')
                            : return_translation('admin_maintenance_result_error');
                        $resultClass = $isSuccess ? 'text-success bg-success-subtle' : 'text-danger bg-danger-subtle';
                        $backupName = basename((string)($log['backup_path'] ?? ''));
                        $mobileCards[] = [
                            'id' => (int)($log['id'] ?? 0),
                            'title' => $actionLabel((string)($log['action'] ?? '')),
                            'slug' => (string)($log['user_name'] ?? ''),
                            'slug_label' => return_translation('admin_maintenance_log_user'),
                            'category' => (string)($log['ip_address'] ?? ''),
                            'category_label' => 'IP',
                            'status' => [[
                                'label' => $resultLabel,
                                'class' => $resultClass,
                            ]],
                            'status_label' => return_translation('admin_maintenance_log_result'),
                            'published_at' => (string)($log['created_at'] ?? ''),
                            'published_at_label' => return_translation('admin_maintenance_log_date'),
                            'extra_fields' => [
                                [
                                    'label' => return_translation('admin_maintenance_log_backup'),
                                    'value' => $backupName,
                                ],
                                [
                                    'label' => return_translation('admin_maintenance_log_error'),
                                    'value' => (string)($log['error'] ?? ''),
                                ],
                            ],
                        ];
                        ?>
                        <tr>
                            <td class="text-body-secondary">#<?= (int)($log['id'] ?? 0) ?></td>
                            <td class="text-nowrap"><?= htmlSC((string)($log['created_at'] ?? '')) ?></td>
                            <td class="text-break">
                                <div class="fw-medium"><?= htmlSC((string)($log['user_name'] ?? '')) ?></div>
                                <div class="small text-body-secondary text-break"><?= htmlSC((string)($log['ip_address'] ?? '')) ?></div>
                            </td>
                            <td class="text-break"><?= htmlSC($actionLabel((string)($log['action'] ?? ''))) ?></td>
                            <td>
                                <span class="badge fs-xs rounded-pill <?= htmlSC($resultClass) ?>">
                                    <?= htmlSC($resultLabel) ?>
                                </span>
                            </td>
                            <td class="small text-break"><?= htmlSC($backupName) ?></td>
                            <td class="small text-danger text-break"><?= htmlSC((string)($log['error'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            <?php $adminTableContent = ob_get_clean(); ?>
            <?= view()->renderPartial('admin/partials/table', [
                'content' => $adminTableContent,
                'table_class' => 'admin-maintenance-log-table',
                'mobile_cards' => $mobileCards,
                'mobile_breakpoint' => 'xl',
            ]) ?>

            <?= view()->renderPartial('admin/partials/table_footer', [
                'visible' => count($logs),
                'total' => $logsTotal,
                'pagination' => $logsPagination,
            ]) ?>
        <?php endif; ?>
    </div>

    <script>
        document.querySelectorAll('[data-maintenance-danger-form]').forEach(function (form) {
            const phrase = form.getAttribute('data-confirm-phrase') || '';
            const input = form.querySelector('[data-maintenance-confirm-input]');
            const submit = form.querySelector('[data-maintenance-confirm-submit]');
            if (!input || !submit) {
                return;
            }

            const syncState = function () {
                submit.disabled = input.value !== phrase;
            };

            input.addEventListener('input', syncState);
            syncState();
        });
    </script>

<?= view()->renderPartial('admin/shell_close') ?>
