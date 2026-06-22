<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_security_logs_title'),
    'subtitle' => return_translation('admin_security_logs_subtitle'),
]) ?>

    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table>
        <form method="get" class="row g-2 align-items-end mb-3" data-admin-table-form>
            <div class="col-md-5">
                <label class="form-label" for="security-log-search"><?= print_translation('admin_table_search_placeholder') ?></label>
                <input id="security-log-search" class="form-control" type="search" name="search" value="<?= htmlSC((string)($search ?? '')) ?>" data-admin-table-search>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-outline-secondary rounded-pill" type="submit"><?= print_translation('admin_btn_apply') ?></button>
            </div>
        </form>
        <div class="table-responsive overflow-auto admin-table-scroll">
            <table class="table align-middle mb-0">
                <thead><tr>
                    <th><?= print_translation('admin_security_log_event') ?></th>
                    <th><?= print_translation('admin_security_log_actor') ?></th>
                    <th><?= print_translation('admin_security_log_target') ?></th>
                    <th><?= print_translation('admin_security_log_result') ?></th>
                    <th><?= print_translation('admin_security_log_reason') ?></th>
                    <th>IP</th>
                    <th><?= print_translation('admin_security_log_created_at') ?></th>
                </tr></thead>
                <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-center text-body-secondary py-5"><?= print_translation('admin_table_empty') ?></td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= htmlSC($log['event']) ?></td>
                        <td><?= htmlSC($log['actor_login'] ?? '-') ?></td>
                        <td><?= htmlSC($log['target_login'] ?? '-') ?></td>
                        <td><span class="badge rounded-pill <?= ($log['result'] ?? '') === 'success' ? 'text-success bg-success-subtle' : 'text-warning bg-warning-subtle' ?>"><?= htmlSC($log['result']) ?></span></td>
                        <td class="text-break"><?= htmlSC($log['reason'] ?? '') ?></td>
                        <td class="text-nowrap"><?= htmlSC($log['ip_address'] ?? '') ?></td>
                        <td class="text-nowrap"><?= htmlSC(date('d.m.Y H:i', strtotime($log['created_at']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?= view()->renderPartial('admin/partials/table_footer', [
            'visible' => count($logs),
            'total' => (int)$total,
            'pagination' => $pagination,
        ]) ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
