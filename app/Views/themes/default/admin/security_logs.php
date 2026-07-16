<?php
$sortIndicator = static function (string $column) use ($sort, $direction): string {
    if (($sort ?? '') !== $column) {
        return '';
    }

    return strtolower((string)$direction) === 'asc' ? ' ↑' : ' ↓';
};

ob_start();
?>
<?php if (check_creator() && (int)($logs_total ?? 0) > 0): ?>
    <form
        action="<?= base_href('/admin/security/logs/clear') ?>"
        method="post"
        data-admin-delete-form
        data-delete-message="<?= htmlSC(return_translation('admin_security_logs_clear_confirm')) ?>"
        data-delete-confirm-label="<?= htmlSC(return_translation('admin_security_logs_clear')) ?>"
    >
        <?= get_csrf_field() ?>
        <button class="btn btn-outline-danger rounded-pill d-inline-flex align-items-center gap-2" type="submit">
            <i class="ci-trash"></i>
            <?= print_translation('admin_security_logs_clear') ?>
        </button>
    </form>
<?php endif; ?>
<?php $securityLogActions = trim((string)ob_get_clean()); ?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_security_logs_title'),
    'subtitle' => return_translation('admin_security_logs_subtitle'),
    'actions' => $securityLogActions,
]) ?>

    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table data-ajax-table="security-logs">
        <form method="get" class="position-relative mb-3" style="max-width: 280px" data-admin-table-form>
            <input type="hidden" name="sort" value="<?= htmlSC((string)($sort ?? '')) ?>">
            <input type="hidden" name="direction" value="<?= htmlSC((string)($direction ?? '')) ?>">
            <input type="hidden" name="page" value="1">
            <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3"></i>
            <input
                id="security-log-search"
                class="table-search form-control form-icon-start"
                type="search"
                name="search"
                value="<?= htmlSC((string)($search ?? '')) ?>"
                placeholder="<?= print_translation('admin_table_search_placeholder') ?>"
                aria-label="<?= htmlSC(return_translation('admin_table_search_placeholder')) ?>"
                autocomplete="off"
                data-admin-table-search
            >
        </form>
        <?php $mobileCards = []; ?>
        <?php ob_start(); ?>
            <colgroup>
                <col class="admin-security-log-table__col-id">
                <col class="admin-security-log-table__col-event">
                <col class="admin-security-log-table__col-actor">
                <col class="admin-security-log-table__col-target">
                <col class="admin-security-log-table__col-result">
                <col class="admin-security-log-table__col-reason">
                <col class="admin-security-log-table__col-ip">
                <col class="admin-security-log-table__col-user-agent">
                <col class="admin-security-log-table__col-date">
            </colgroup>
            <thead><tr>
                    <th>ID</th>
                    <th><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('event', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_security_log_event') ?><?= $sortIndicator('event') ?></a></th>
                    <th><?= print_translation('admin_security_log_actor') ?></th>
                    <th><?= print_translation('admin_security_log_target') ?></th>
                    <th><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('result', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_security_log_result') ?><?= $sortIndicator('result') ?></a></th>
                    <th><?= print_translation('admin_security_log_reason') ?></th>
                    <th>IP</th>
                    <th>User-Agent</th>
                    <th><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('created_at', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_security_log_created_at') ?><?= $sortIndicator('created_at') ?></a></th>
                </tr></thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="9" class="text-center text-body-secondary py-5"><?= print_translation('admin_table_empty') ?></td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $event = trim((string)($log['event'] ?? '')) ?: '—';
                    $actor = trim((string)($log['actor_login'] ?? '')) ?: '—';
                    $target = trim((string)($log['target_login'] ?? '')) ?: '—';
                    $result = strtolower(trim((string)($log['result'] ?? ''))) ?: 'unknown';
                    $reason = trim((string)($log['reason'] ?? '')) ?: '—';
                    $ipAddress = trim((string)($log['ip_address'] ?? '')) ?: '—';
                    $userAgent = trim((string)($log['user_agent'] ?? '')) ?: '—';
                    $createdAtTimestamp = strtotime((string)($log['created_at'] ?? ''));
                    $createdAt = $createdAtTimestamp !== false ? date('d.m.Y H:i', $createdAtTimestamp) : '—';
                    $resultClass = match ($result) {
                        'success' => 'text-success bg-success-subtle',
                        'failed', 'error' => 'text-danger bg-danger-subtle',
                        'denied', 'warning' => 'text-warning bg-warning-subtle',
                        default => 'text-secondary bg-secondary-subtle',
                    };
                    $eventHtml = str_replace('_', '_<wbr>', htmlSC($event));
                    $reasonHtml = str_replace('_', '_<wbr>', htmlSC($reason));
                    $mobileCards[] = [
                        'id' => (int)($log['id'] ?? 0),
                        'title' => $event,
                        'slug' => $actor,
                        'slug_label' => return_translation('admin_security_log_actor'),
                        'category' => $target,
                        'category_label' => return_translation('admin_security_log_target'),
                        'author' => $ipAddress,
                        'author_label' => 'IP',
                        'status' => [[
                            'label' => $result,
                            'class' => $resultClass,
                        ]],
                        'status_label' => return_translation('admin_security_log_result'),
                        'published_at' => $createdAt,
                        'published_at_label' => return_translation('admin_security_log_created_at'),
                        'extra_fields' => [
                            [
                                'label' => return_translation('admin_security_log_reason'),
                                'value' => $reason,
                            ],
                            [
                                'label' => 'User-Agent',
                                'value' => $userAgent,
                            ],
                        ],
                    ];
                    ?>
                    <tr>
                        <th class="text-body-secondary text-nowrap fw-normal" scope="row">#<?= (int)($log['id'] ?? 0) ?></th>
                        <td class="fw-medium admin-security-log-table__event"><?= $eventHtml ?></td>
                        <td><?= htmlSC($actor) ?></td>
                        <td><?= htmlSC($target) ?></td>
                        <td><span class="badge fs-xs rounded-pill <?= htmlSC($resultClass) ?>"><?= htmlSC($result) ?></span></td>
                        <td><span class="admin-security-log-table__clamp" title="<?= htmlSC($reason) ?>"><?= $reasonHtml ?></span></td>
                        <td class="text-nowrap"><?= htmlSC($ipAddress) ?></td>
                        <td class="small text-body-secondary"><span class="admin-security-log-table__clamp admin-security-log-table__user-agent" title="<?= htmlSC($userAgent) ?>"><?= htmlSC($userAgent) ?></span></td>
                        <td class="text-nowrap"><?= htmlSC($createdAt) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        <?php $adminTableContent = ob_get_clean(); ?>
        <?= view()->renderPartial('admin/partials/table', [
            'content' => $adminTableContent,
            'table_class' => 'admin-security-log-table',
            'mobile_cards' => $mobileCards,
            'mobile_breakpoint' => 'xxl',
        ]) ?>
        <?= view()->renderPartial('admin/partials/table_footer', [
            'visible' => count($logs),
            'total' => (int)$total,
            'pagination' => $pagination,
        ]) ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
