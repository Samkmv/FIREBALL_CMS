<?= view()->renderPartial('admin/shell_open', [
    'title' => 'Предпросмотр streams.pl',
    'subtitle' => 'Показывается только управляемый блок; логин и пароль RTSP скрыты.',
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

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
