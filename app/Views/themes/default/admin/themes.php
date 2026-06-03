<?php
$activeSlug = (string)($active_theme['slug'] ?? 'default');
$actions = '<a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="' . base_href('/admin/themes/import') . '"><i class="ci-upload"></i>' . htmlSC(return_translation('admin_themes_import')) . '</a>'
    . '<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="' . base_href('/admin/themes/create') . '"><i class="ci-plus"></i>' . htmlSC(return_translation('admin_themes_create')) . '</a>';
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_themes_heading'),
    'subtitle' => return_translation('admin_themes_subtitle'),
    'actions' => $actions,
]) ?>

    <div class="row g-4">
        <?php foreach ($themes as $theme): ?>
            <?php
            $isActive = (string)$theme['slug'] === $activeSlug;
            $canDelete = \FBL\Theme::canDeleteTheme((string)$theme['slug']);
            $previewUrl = (string)($theme['preview_url'] ?? base_url('/themes/' . rawurlencode((string)$theme['slug']) . '/preview.png'));
            $sitePreviewUrl = base_href('/admin/themes/preview/' . $theme['slug']);
            ?>
            <div class="col-md-6 col-xl-4">
                <article class="card h-100 rounded-5 border <?= $isActive ? 'border-primary shadow-sm' : '' ?> overflow-hidden">
                    <a class="ratio bg-body-tertiary text-decoration-none" style="--cz-aspect-ratio: 56%;" href="<?= htmlSC($sitePreviewUrl) ?>" target="_blank" rel="noopener noreferrer">
                        <img src="<?= htmlSC($previewUrl) ?>" alt="<?= htmlSC((string)$theme['name']) ?>" class="w-100 h-100 object-fit-cover" onerror="this.classList.add('d-none'); this.nextElementSibling.classList.remove('d-none');">
                        <span class="d-none h-100 w-100 align-items-center justify-content-center text-body-secondary">
                            <span class="text-center px-4">
                                <i class="ci-monitor fs-1 mb-3 d-block"></i>
                                <span class="fw-semibold"><?= htmlSC((string)$theme['name']) ?></span>
                            </span>
                        </span>
                    </a>
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-2">
                            <h2 class="h5 mb-0"><?= htmlSC((string)$theme['name']) ?></h2>
                            <?php if ($isActive): ?>
                                <span class="badge text-bg-success rounded-pill"><?= print_translation('admin_themes_active') ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-body-secondary mb-3"><?= htmlSC((string)$theme['description']) ?></p>
                        <dl class="row small mb-0 gy-2">
                            <dt class="col-5 text-body-secondary fw-normal"><?= print_translation('admin_themes_version') ?></dt>
                            <dd class="col-7 mb-0 text-end"><?= htmlSC((string)$theme['version']) ?></dd>
                            <dt class="col-5 text-body-secondary fw-normal"><?= print_translation('admin_themes_author') ?></dt>
                            <dd class="col-7 mb-0 text-end"><?= htmlSC((string)$theme['author']) ?></dd>
                            <dt class="col-5 text-body-secondary fw-normal">Slug</dt>
                            <dd class="col-7 mb-0 text-end"><code><?= htmlSC((string)$theme['slug']) ?></code></dd>
                        </dl>
                    </div>
                    <div class="card-footer bg-transparent border-0 p-4 pt-0">
                        <div class="d-grid gap-2">
                            <?php if (!$isActive): ?>
                                <form action="<?= base_href('/admin/themes/activate') ?>" method="post">
                                    <?= get_csrf_field() ?>
                                    <input type="hidden" name="slug" value="<?= htmlSC((string)$theme['slug']) ?>">
                                    <button class="btn btn-dark rounded-pill w-100" type="submit">
                                        <?= print_translation('admin_themes_activate') ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary rounded-pill w-100" type="button" disabled>
                                    <?= print_translation('admin_themes_active') ?>
                                </button>
                            <?php endif; ?>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-outline-secondary rounded-pill flex-fill" href="<?= htmlSC($sitePreviewUrl) ?>" target="_blank" rel="noopener noreferrer">
                                    <?= print_translation('admin_themes_preview') ?>
                                </a>
                                <a class="btn btn-outline-secondary rounded-pill flex-fill" href="<?= base_href('/admin/themes/edit/' . $theme['slug']) ?>">
                                    <?= print_translation('admin_themes_edit') ?>
                                </a>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a class="btn btn-outline-secondary rounded-pill flex-fill" href="<?= base_href('/admin/themes/files/' . $theme['slug']) ?>">
                                    <?= print_translation('admin_themes_files') ?>
                                </a>
                                <a class="btn btn-outline-secondary rounded-pill flex-fill" href="<?= base_href('/admin/themes/export/' . $theme['slug']) ?>">
                                    <?= print_translation('admin_themes_export') ?>
                                </a>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if ($canDelete): ?>
                                    <form class="flex-fill" action="<?= base_href('/admin/themes/delete') ?>" method="post" data-admin-delete-form data-delete-message="<?= htmlSC(return_translation('admin_confirm_delete_theme')) ?>" data-delete-item="<?= htmlSC((string)$theme['name']) ?>">
                                        <?= get_csrf_field() ?>
                                        <input type="hidden" name="slug" value="<?= htmlSC((string)$theme['slug']) ?>">
                                        <button class="btn btn-outline-danger rounded-pill w-100" type="submit">
                                            <?= print_translation('admin_themes_delete') ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-outline-secondary rounded-pill flex-fill" type="button" disabled>
                                        <?= print_translation('admin_themes_delete') ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
