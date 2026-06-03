<?php
$theme = $theme_item ?? [];
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_themes_files_heading') . ': ' . (string)($theme['name'] ?? ''),
    'subtitle' => (string)($theme['path'] ?? ''),
    'actions' => '<a class="btn btn-outline-secondary rounded-pill" href="' . base_href('/admin/themes/edit/' . ($theme['slug'] ?? '')) . '">' . htmlSC(return_translation('admin_themes_edit')) . '</a>',
]) ?>

    <div class="border rounded-5 p-3 p-md-4">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
            <div>
                <h2 class="h5 mb-1"><?= print_translation('admin_themes_files') ?></h2>
                <p class="text-body-secondary mb-0"><?= print_translation('admin_themes_files_hint') ?></p>
            </div>
            <code class="small text-break">/themes/<?= htmlSC((string)($theme['slug'] ?? '')) ?></code>
        </div>
        <div class="list-group list-group-flush">
            <?php foreach ($files as $file): ?>
                <div class="list-group-item px-0 d-flex align-items-center gap-2">
                    <i class="ci-file-text text-body-secondary"></i>
                    <code><?= htmlSC((string)$file) ?></code>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
