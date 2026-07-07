<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_security_logs_title'),
    'subtitle' => return_translation('admin_security_logs_subtitle'),
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
            <thead><tr>
                    <th>ID</th>
                    <th><?= print_translation('admin_security_log_event') ?></th>
                    <th><?= print_translation('admin_security_log_actor') ?></th>
                    <th><?= print_translation('admin_security_log_target') ?></th>
                    <th><?= print_translation('admin_security_log_result') ?></th>
                    <th><?= print_translation('admin_security_log_reason') ?></th>
                    <th>IP</th>
                    <th>User-Agent</th>
                    <th><?= print_translation('admin_security_log_created_at') ?></th>
                </tr></thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="9" class="text-center text-body-secondary py-5"><?= print_translation('admin_table_empty') ?></td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $isSuccess = ($log['result'] ?? '') === 'success';
                    $resultClass = $isSuccess ? 'text-success bg-success-subtle' : 'text-warning bg-warning-subtle';
                    $mobileCards[] = [
                        'id' => (int)($log['id'] ?? 0),
                        'title' => (string)($log['event'] ?? ''),
                        'slug' => (string)($log['actor_login'] ?? '-'),
                        'slug_label' => return_translation('admin_security_log_actor'),
                        'category' => (string)($log['target_login'] ?? '-'),
                        'category_label' => return_translation('admin_security_log_target'),
                        'author' => (string)($log['ip_address'] ?? ''),
                        'author_label' => 'IP',
                        'status' => [[
                            'label' => (string)($log['result'] ?? ''),
                            'class' => $resultClass,
                        ]],
                        'status_label' => return_translation('admin_security_log_result'),
                        'published_at' => date('d.m.Y H:i', strtotime((string)($log['created_at'] ?? 'now'))),
                        'published_at_label' => return_translation('admin_security_log_created_at'),
                        'extra_fields' => [
                            [
                                'label' => return_translation('admin_security_log_reason'),
                                'value' => (string)($log['reason'] ?? ''),
                            ],
                            [
                                'label' => 'User-Agent',
                                'value' => (string)($log['user_agent'] ?? ''),
                            ],
                        ],
                    ];
                    ?>
                    <tr>
                        <td class="text-body-secondary">#<?= (int)($log['id'] ?? 0) ?></td>
                        <td class="fw-medium text-break"><?= htmlSC($log['event'] ?? '') ?></td>
                        <td class="text-break"><?= htmlSC($log['actor_login'] ?? '-') ?></td>
                        <td class="text-break"><?= htmlSC($log['target_login'] ?? '-') ?></td>
                        <td><span class="badge fs-xs rounded-pill <?= htmlSC($resultClass) ?>"><?= htmlSC($log['result'] ?? '') ?></span></td>
                        <td class="text-break"><?= htmlSC($log['reason'] ?? '') ?></td>
                        <td class="text-break"><?= htmlSC($log['ip_address'] ?? '') ?></td>
                        <td class="small text-body-secondary text-break"><?= htmlSC($log['user_agent'] ?? '') ?></td>
                        <td class="text-nowrap"><?= htmlSC(date('d.m.Y H:i', strtotime($log['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        <?php $adminTableContent = ob_get_clean(); ?>
        <?= view()->renderPartial('admin/partials/table', [
            'content' => $adminTableContent,
            'table_class' => 'admin-security-log-table',
            'mobile_cards' => $mobileCards,
            'mobile_breakpoint' => 'xl',
        ]) ?>
        <?= view()->renderPartial('admin/partials/table_footer', [
            'visible' => count($logs),
            'total' => (int)$total,
            'pagination' => $pagination,
        ]) ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
