<?php
$items = $catalog['plugins'];
$selectedCategory = $catalog['category'];
$searchQuery = $catalog['query'];
$filterUrl = static function (string $category = '') use ($searchQuery): string {
    $parameters = http_build_query(array_filter(
        ['category' => $category, 'q' => $searchQuery], static fn(string $value): bool => $value !== ''
    ), '', '&', PHP_QUERY_RFC3986);
    return base_href('/admin/plugins/catalog') . ($parameters !== '' ? '?' . $parameters : '');
};
$available = count(array_filter($items, static fn(array $item): bool => empty($item['installed'])
    && !empty($item['valid']) && empty($item['error']) && empty($item['incompatible'])));
$actions = '<div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-secondary rounded-pill" href="'
    . htmlSC(base_href('/admin/plugins')) . '">' . htmlSC(return_translation('admin_plugins_title')) . '</a>'
    . '<form action="' . htmlSC(base_href('/admin/plugins/catalog/refresh')) . '" method="post">'
    . get_csrf_field()
    . '<input type="hidden" name="category" value="' . htmlSC($selectedCategory) . '">'
    . '<input type="hidden" name="q" value="' . htmlSC($searchQuery) . '">'
    . '<button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit">'
    . '<i class="ci-refresh-cw" aria-hidden="true"></i>' . htmlSC(return_translation('admin_plugin_marketplace_refresh'))
    . '</button></form></div>';
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_plugin_marketplace_title'),
    'subtitle' => return_translation('admin_plugin_marketplace_subtitle'),
    'actions' => $actions,
]) ?>

<section class="fb-plugin-overview mb-4">
    <div class="fb-plugin-overview-copy">
        <span class="fb-plugin-overview-icon" aria-hidden="true"><i class="ci-package"></i></span>
        <div>
            <h2><?= print_translation('admin_plugin_marketplace_catalog') ?></h2>
            <p><?= print_translation('admin_plugin_marketplace_help') ?></p>
            <?php if ($catalog['checked_at'] > 0): ?>
                <p class="mt-2 small"><?= print_translation('admin_plugin_marketplace_checked') ?> <?= htmlSC(date('d.m.Y H:i', $catalog['checked_at'])) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <div class="fb-plugin-overview-stats">
        <span class="fb-plugin-stat"><strong><?= $available ?></strong><span><?= print_translation('admin_plugin_marketplace_available') ?></span></span>
    </div>
</section>

<form action="<?= htmlSC(base_href('/admin/plugins/catalog')) ?>" method="get" class="mb-3" role="search">
    <label class="form-label" for="plugin-catalog-search"><?= print_translation('admin_plugin_catalog_search') ?></label>
    <?php if ($selectedCategory !== ''): ?>
        <input type="hidden" name="category" value="<?= htmlSC($selectedCategory) ?>">
    <?php endif; ?>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <input class="form-control flex-grow-1 w-auto" id="plugin-catalog-search" type="search" name="q" maxlength="200"
               value="<?= htmlSC($searchQuery) ?>" placeholder="<?= htmlSC(return_translation('admin_plugin_catalog_search_placeholder')) ?>">
        <button class="btn btn-dark rounded-pill" type="submit"><?= print_translation('admin_plugin_catalog_find') ?></button>
        <?php if ($searchQuery !== '' || $selectedCategory !== ''): ?>
            <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/catalog')) ?>"><?= print_translation('admin_plugin_catalog_reset') ?></a>
        <?php endif; ?>
    </div>
</form>

<nav class="d-flex flex-wrap gap-2 mb-4" aria-label="<?= htmlSC(return_translation('admin_plugin_catalog_categories')) ?>">
    <a class="btn btn-sm rounded-pill <?= $selectedCategory === '' ? 'btn-dark' : 'btn-outline-secondary' ?>"
       href="<?= htmlSC($filterUrl()) ?>" <?= $selectedCategory === '' ? 'aria-current="page"' : '' ?>>
        <?= print_translation('admin_plugin_catalog_all') ?> <span class="ms-1"><?= (int)$catalog['total'] ?></span>
    </a>
    <?php foreach ($catalog['categories'] as $category => $data): ?>
        <a class="btn btn-sm rounded-pill <?= $selectedCategory === $category ? 'btn-dark' : 'btn-outline-secondary' ?>"
           href="<?= htmlSC($filterUrl($category)) ?>" <?= $selectedCategory === $category ? 'aria-current="page"' : '' ?>>
            <?= htmlSC($data['label']) ?> <span class="ms-1"><?= (int)$data['count'] ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($catalog['error'] !== ''): ?>
    <div class="alert alert-warning rounded-4" role="alert">
        <p class="mb-1"><?= print_translation('admin_plugin_marketplace_load_failed') ?></p>
        <p class="mb-0"><?= htmlSC($catalog['error']) ?></p>
    </div>
<?php endif; ?>

<?php if ($items === []): ?>
    <div class="border rounded-5 p-4 p-md-5 text-center">
        <i class="ci-package fs-1 text-body-tertiary d-block mb-3" aria-hidden="true"></i>
        <p class="mb-0"><?= print_translation($searchQuery !== '' || $selectedCategory !== '' ? 'admin_plugin_catalog_no_results' : 'admin_plugin_marketplace_empty') ?></p>
    </div>
<?php else: ?>
    <div class="row g-4 align-items-start fb-plugin-grid">
        <?php foreach ($items as $item): ?>
            <?php
            $problem = trim((string)($item['error'] ?? '')) ?: trim((string)($item['incompatible'] ?? ''));
            $canInstall = !empty($item['valid']) && $problem === '';
            ?>
            <div class="col-md-6 col-xl-4">
                <article class="card rounded-5 fb-plugin-card" id="plugin-<?= htmlSC($item['slug']) ?>">
                    <div class="card-body fb-plugin-card-body">
                        <div class="fb-plugin-card-header">
                            <span class="fb-plugin-card-icon" aria-hidden="true"><i class="ci-package"></i></span>
                            <div class="fb-plugin-card-heading">
                                <h2><?= htmlSC($item['name']) ?></h2>
                                <code><?= htmlSC($item['slug']) ?></code>
                            </div>
                        </div>
                        <span class="badge rounded-pill text-body bg-body-tertiary mt-3"><?= htmlSC($catalog['categories'][$item['category']]['label']) ?></span>
                        <p class="fb-plugin-card-description"><?= htmlSC($item['description']) ?></p>
                        <div class="fb-plugin-version-strip">
                            <span><small><?= print_translation('admin_plugins_version') ?></small><strong><?= htmlSC($item['version']) ?></strong></span>
                            <span><small><?= print_translation('admin_plugins_author') ?></small><strong><?= htmlSC($item['author']) ?></strong></span>
                        </div>
                        <p class="small text-body-secondary mb-0"><?= print_translation(!empty($item['local']) ? 'admin_plugin_marketplace_local' : 'admin_plugin_marketplace_remote') ?></p>
                        <?php if ($problem !== ''): ?>
                            <div class="alert alert-warning rounded-4 mb-0" role="alert"><?= htmlSC($problem) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer fb-plugin-card-footer">
                        <div class="fb-plugin-card-actions">
                            <form class="fb-plugin-action-primary" action="<?= htmlSC(base_href('/admin/plugins/catalog/install')) ?>" method="post">
                                <?= get_csrf_field() ?>
                                <input type="hidden" name="slug" value="<?= htmlSC($item['slug']) ?>">
                                <input type="hidden" name="category" value="<?= htmlSC($selectedCategory) ?>">
                                <input type="hidden" name="q" value="<?= htmlSC($searchQuery) ?>">
                                <button class="btn btn-dark rounded-pill w-100 d-inline-flex align-items-center justify-content-center gap-2" type="submit" <?= $canInstall ? '' : 'disabled' ?>>
                                    <i class="ci-download" aria-hidden="true"></i><?= print_translation('admin_plugins_install') ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?= view()->renderPartial('admin/shell_close') ?>
