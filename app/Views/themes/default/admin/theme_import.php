<?php
$theme = $imported_theme ?? null;
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_themes_import_heading'),
    'subtitle' => return_translation('admin_themes_import_subtitle'),
    'actions' => '<a class="btn btn-outline-secondary rounded-pill" href="' . base_href('/admin/themes') . '">' . htmlSC(return_translation('admin_themes_back_to_list')) . '</a>',
]) ?>

    <?php if ($theme): ?>
        <div class="border rounded-5 p-3 p-md-4 mb-4">
            <div class="row g-4 align-items-center">
                <div class="col-md-4">
                    <div class="ratio rounded-4 overflow-hidden bg-body-tertiary" style="--cz-aspect-ratio: 60%;">
                        <img src="<?= htmlSC((string)($theme['preview_url'] ?? '')) ?>" alt="<?= htmlSC((string)$theme['name']) ?>" class="w-100 h-100 object-fit-cover">
                    </div>
                </div>
                <div class="col-md-8">
                    <h2 class="h4 mb-2"><?= htmlSC((string)$theme['name']) ?></h2>
                    <p class="text-body-secondary mb-3"><?= htmlSC((string)$theme['description']) ?></p>
                    <dl class="row gy-2 mb-4">
                        <dt class="col-sm-3 text-body-secondary fw-normal">Slug</dt>
                        <dd class="col-sm-9 mb-0"><code><?= htmlSC((string)$theme['slug']) ?></code></dd>
                        <dt class="col-sm-3 text-body-secondary fw-normal"><?= print_translation('admin_themes_author') ?></dt>
                        <dd class="col-sm-9 mb-0"><?= htmlSC((string)$theme['author']) ?></dd>
                        <dt class="col-sm-3 text-body-secondary fw-normal"><?= print_translation('admin_themes_version') ?></dt>
                        <dd class="col-sm-9 mb-0"><?= htmlSC((string)$theme['version']) ?></dd>
                    </dl>
                    <div class="d-flex flex-wrap gap-2">
                        <form action="<?= base_href('/admin/themes/activate') ?>" method="post">
                            <?= get_csrf_field() ?>
                            <input type="hidden" name="slug" value="<?= htmlSC((string)$theme['slug']) ?>">
                            <button class="btn btn-dark rounded-pill" type="submit"><?= print_translation('admin_themes_activate') ?></button>
                        </form>
                        <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/themes') ?>"><?= print_translation('admin_themes_back_to_list') ?></a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= base_href('/admin/themes/import') ?>" method="post" enctype="multipart/form-data">
        <?= get_csrf_field() ?>
        <div class="mb-3">
            <label class="form-label"><?= print_translation('admin_themes_import_file') ?></label>
            <input class="form-control" type="file" name="theme_zip" accept=".zip,application/zip" required>
            <div class="form-text"><?= print_translation('admin_themes_import_hint') ?></div>
        </div>
        <button class="btn btn-dark rounded-pill" type="submit"><?= print_translation('admin_themes_import_submit') ?></button>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
