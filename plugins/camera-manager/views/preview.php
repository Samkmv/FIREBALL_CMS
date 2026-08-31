<?= view()->renderPartial('admin/shell_open', [
    'title' => 'Предпросмотр streams.pl',
    'subtitle' => 'Показывается только управляемый блок; логин и пароль RTSP скрыты.',
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <div class="table-responsive border rounded-5 mb-4" data-admin-simplebar data-simplebar-auto-hide="false">
        <table class="table align-middle mb-0">
            <thead><tr><th>Stream key</th><th>Объект / камера</th><th>Internal RTSP URL</th><th>HLS / Poster</th><th>Enabled</th></tr></thead>
            <tbody>
            <?php if (empty($preview_rows)): ?><tr><td colspan="5" class="text-center text-body-secondary py-4">Камер пока нет.</td></tr><?php endif; ?>
            <?php foreach ($preview_rows as $row): ?>
                <tr>
                    <td><code><?= htmlSC((string)$row['stream_key']) ?></code></td>
                    <td><div><?= htmlSC((string)$row['site']) ?></div><div class="small text-body-secondary"><?= htmlSC((string)$row['camera']) ?></div></td>
                    <td><code class="small text-break"><?= htmlSC((string)$row['rtsp_url']) ?></code></td>
                    <td><a href="<?= htmlSC((string)$row['hls_url']) ?>" target="_blank" rel="noopener">HLS</a> · <a href="<?= htmlSC((string)$row['poster_url']) ?>" target="_blank" rel="noopener">Poster</a></td>
                    <td><span class="badge rounded-pill text-bg-<?= !empty($row['enabled']) ? 'success' : 'secondary' ?>"><?= !empty($row['enabled']) ? 'Да' : 'Нет' ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($preview_error !== ''): ?>
        <div class="alert alert-danger rounded-4"><?= htmlSC($preview_error) ?></div>
    <?php else: ?>
        <div class="alert alert-info rounded-4">Публикация заменяет только блок между маркерами Camera Manager. Остальной <code>streams.pl</code> остаётся без изменений.</div>
        <pre class="border rounded-5 p-4 bg-body-tertiary overflow-auto"><code><?= htmlSC($preview) ?></code></pre>
    <?php endif; ?>

    <?php if ($latest_publication): ?>
        <div class="border rounded-5 p-4 mt-4"><h2 class="h5">Последняя операция</h2><dl class="row mb-0"><dt class="col-md-3">Статус</dt><dd class="col-md-9"><?= htmlSC((string)$latest_publication['status']) ?></dd><dt class="col-md-3">Время</dt><dd class="col-md-9"><?= htmlSC((string)$latest_publication['created_at']) ?></dd><dt class="col-md-3">Результат</dt><dd class="col-md-9"><?= htmlSC((string)$latest_publication['message']) ?></dd><?php if (!empty($latest_publication['backup_path'])): ?><dt class="col-md-3">Резервная копия</dt><dd class="col-md-9"><code><?= htmlSC((string)$latest_publication['backup_path']) ?></code></dd><?php endif; ?></dl></div>
    <?php endif; ?>

<?= view()->renderPartial('admin/shell_close') ?>
