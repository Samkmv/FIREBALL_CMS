<?php
$statusLabels = [
    'active' => [return_translation('admin_plugins_status_active'), 'text-success bg-success-subtle'],
    'inactive' => [return_translation('admin_plugins_status_inactive'), 'text-secondary bg-secondary-subtle'],
    'not_installed' => [return_translation('admin_plugins_status_not_installed'), 'text-body bg-body-tertiary'],
];
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_plugins_title'),
    'subtitle' => return_translation('admin_plugins_subtitle'),
    'actions' => '<a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="' . base_href('/admin/docs/plugins') . '"><i class="ci-book-open"></i>' . htmlSC(return_translation('admin_plugins_docs')) . '</a>',
]) ?>

    <?php if (empty($plugins)): ?>
        <div class="border rounded-5 p-4 p-md-5 text-center">
            <i class="ci-box fs-1 text-body-tertiary d-block mb-3"></i>
            <h2 class="h5 mb-2"><?= print_translation('admin_plugins_empty_title') ?></h2>
            <p class="text-body-secondary mb-0"><?= print_translation('admin_plugins_empty_text_before') ?> <code>/plugins</code><?= print_translation('admin_plugins_empty_text_after') ?></p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($plugins as $plugin): ?>
                <?php
                $status = (string)($plugin['status'] ?? 'not_installed');
                $statusData = $statusLabels[$status] ?? [$status, 'text-body bg-body-tertiary'];
                $isValid = !empty($plugin['valid']);
                $isInstalled = !empty($plugin['installed']);
                $isActive = $status === 'active';
                ?>
                <div class="col-md-6 col-xl-4">
                    <article class="card h-100 rounded-5 border <?= $isActive ? 'border-success shadow-sm' : '' ?>">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                                <div>
                                    <h2 class="h5 mb-1"><?= htmlSC((string)$plugin['name']) ?></h2>
                                    <code class="small"><?= htmlSC((string)$plugin['slug']) ?></code>
                                </div>
                                <span class="badge rounded-pill <?= htmlSC($statusData[1]) ?>"><?= htmlSC($statusData[0]) ?></span>
                            </div>

                            <p class="text-body-secondary mb-3"><?= htmlSC((string)$plugin['description']) ?></p>

                            <dl class="row small gy-2 mb-0">
                                <dt class="col-5 text-body-secondary fw-normal"><?= print_translation('admin_plugins_version') ?></dt>
                                <dd class="col-7 mb-0 text-end"><?= htmlSC((string)$plugin['version']) ?></dd>
                                <dt class="col-5 text-body-secondary fw-normal"><?= print_translation('admin_plugins_author') ?></dt>
                                <dd class="col-7 mb-0 text-end"><?= htmlSC((string)$plugin['author']) ?></dd>
                            </dl>

                            <?php if (!$isValid || !empty($plugin['error']) || !empty($plugin['load_error'])): ?>
                                <div class="alert alert-warning small mt-3 mb-0" role="alert">
                                    <?= htmlSC((string)($plugin['error'] ?: $plugin['load_error'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent border-0 p-4 pt-0">
                            <div class="d-grid gap-2">
                                <?php if (!$isInstalled): ?>
                                    <form action="<?= base_href('/admin/plugins/install') ?>" method="post">
                                        <?= get_csrf_field() ?>
                                        <input type="hidden" name="slug" value="<?= htmlSC((string)$plugin['slug']) ?>">
                                        <button class="btn btn-dark rounded-pill w-100" type="submit" <?= $isValid ? '' : 'disabled' ?>>
                                            <?= print_translation('admin_plugins_install') ?>
                                        </button>
                                    </form>
                                <?php elseif (!$isActive): ?>
                                    <form action="<?= base_href('/admin/plugins/activate') ?>" method="post">
                                        <?= get_csrf_field() ?>
                                        <input type="hidden" name="slug" value="<?= htmlSC((string)$plugin['slug']) ?>">
                                        <button class="btn btn-dark rounded-pill w-100" type="submit" <?= $isValid ? '' : 'disabled' ?>>
                                            <?= print_translation('admin_plugins_activate') ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form action="<?= base_href('/admin/plugins/deactivate') ?>" method="post" data-admin-delete-form data-delete-message="<?= htmlSC(return_translation('admin_plugins_deactivate_confirm')) ?>" data-delete-item="<?= htmlSC((string)$plugin['name']) ?>">
                                        <?= get_csrf_field() ?>
                                        <input type="hidden" name="slug" value="<?= htmlSC((string)$plugin['slug']) ?>">
                                        <button class="btn btn-outline-secondary rounded-pill w-100" type="submit">
                                            <?= print_translation('admin_plugins_deactivate') ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?= view()->renderPartial('admin/shell_close') ?>
