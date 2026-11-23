<?php
require __DIR__ . '/open.php';
$parameters = array_filter($filters, static fn(string $value): bool => $value !== '');
$catalogUrl = static function (array $changes = []) use ($parameters, $url): string {
    $query = http_build_query(array_filter(array_replace($parameters, $changes), static fn($value): bool => $value !== ''), '', '&', PHP_QUERY_RFC3986);
    return $url('/shop' . ($query !== '' ? '?' . $query : ''));
};
$active = [];
if ($filters['category'] !== '') $active['category'] = $categoryName ?: $filters['category'];
if ($filters['color'] !== '') $active['color'] = $colors[$filters['color']];
if ($filters['min'] !== '') $active['min'] = FireballPluginCommerce::t('price_from') . ' ' . $money(\Fireball\Commerce\Input::price($filters['min']));
if ($filters['max'] !== '') $active['max'] = FireballPluginCommerce::t('price_to') . ' ' . $money(\Fireball\Commerce\Input::price($filters['max']));
if ($filters['available'] !== '') $active['available'] = $t('in_stock_only');
$sortOptions = ['' => $filters['q'] === '' ? 'sort_new' : 'sort_relevance', 'popular' => 'sort_popular', 'price-asc' => 'sort_price_up', 'price-desc' => 'sort_price_down', 'name' => 'sort_name'];
if ($filters['q'] !== '' || $filters['sort'] === 'new') {
    $sortOptions = array_slice($sortOptions, 0, 1, true) + ['new' => 'sort_new'] + $sortOptions;
}
$hasAlternate = $filters['view'] === 'alternate' || array_filter(array_column($products, 'hover_image_path')) !== [];
?>
<nav aria-label="<?= $t('location') ?>" class="mb-3">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= $url('/') ?>"><?= htmlSC(return_translation('tpl_menu_nav_index')) ?></a></li>
        <?php if ($categoryName !== '' || $filters['q'] !== ''): ?><li class="breadcrumb-item"><a href="<?= $url('/shop') ?>"><?= $t('shop_catalog') ?></a></li><?php endif; ?>
        <li class="breadcrumb-item active text-break" aria-current="page"><?= htmlSC($heading) ?></li>
    </ol>
</nav>
<div class="d-flex align-items-baseline justify-content-between gap-2 flex-wrap mb-3">
    <h1 class="h3 text-break mb-0"><?= htmlSC($heading) ?></h1>
    <a class="fs-sm text-decoration-none" href="<?= $url('/shop/favorites') ?>"><i class="ci-heart me-1" aria-hidden="true"></i><?= $t('favorites') ?></a>
</div>
<?php if ($filters['q'] !== ''): ?><p class="text-body-secondary text-break mb-3">«<?= htmlSC($filters['q']) ?>»</p><?php endif; ?>

<div class="commerce-catalog-toolbar bg-body border-bottom mb-3" data-commerce-catalog-toolbar>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 py-3">
        <form action="<?= $url('/shop') ?>" method="get" class="d-flex flex-wrap align-items-center gap-2 flex-grow-1" data-commerce-filter-bar>
            <?php foreach (array_intersect_key($parameters, array_flip(['q', 'min', 'max', 'available', 'view'])) as $key => $value): ?>
                <input type="hidden" name="<?= htmlSC($key) ?>" value="<?= htmlSC($value) ?>">
            <?php endforeach; ?>
            <select class="form-select rounded-pill commerce-catalog-select" name="sort" aria-label="<?= $t('sort') ?>">
                <?php foreach ($sortOptions as $option => $label): ?>
                    <option value="<?= htmlSC($option) ?>" <?= $filters['sort'] === $option ? 'selected' : '' ?>><?= $t($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select rounded-pill commerce-catalog-select d-none d-md-block" name="category" aria-label="<?= $t('category') ?>">
                <option value=""><?= $t('all_categories') ?></option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= htmlSC($category['slug']) ?>" <?= $filters['category'] === $category['slug'] ? 'selected' : '' ?>><?= htmlSC($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($colors !== []): ?>
                <select class="form-select rounded-pill commerce-catalog-select d-none d-lg-block" name="color" aria-label="<?= $t('color_name') ?>">
                    <option value=""><?= $t('all_colors') ?></option>
                    <?php foreach ($colors as $hex => $name): ?>
                        <option value="<?= htmlSC($hex) ?>" <?= $filters['color'] === $hex ? 'selected' : '' ?>><?= htmlSC($name) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-secondary rounded-pill" type="submit" data-commerce-filter-submit><?= $t('apply') ?></button>
            <button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#commerceCatalogFilters" aria-controls="commerceCatalogFilters">
                <i class="ci-filter" aria-hidden="true"></i><?= $t('all_filters') ?>
            </button>
        </form>
        <?php if ($hasAlternate): ?>
            <nav class="nav nav-underline flex-nowrap column-gap-3" aria-label="<?= $t('catalog_photo_view') ?>">
                <?php foreach (['' => 'product_photo', 'alternate' => 'alternate_photo'] as $view => $label): ?>
                    <a class="nav-link py-1 text-nowrap<?= $filters['view'] === $view ? ' active' : '' ?>"
                       href="<?= $catalogUrl(['view' => $view]) ?>" <?= $filters['view'] === $view ? 'aria-current="page"' : '' ?>><?= $t($label) ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>

<?php if ($active !== []): ?>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-4" aria-label="<?= $t('applied_filters') ?>">
        <?php foreach ($active as $key => $label): ?>
            <a class="btn btn-sm btn-secondary rounded-pill d-inline-flex gap-1 align-items-center"
               href="<?= $catalogUrl([$key => '']) ?>"><i class="ci-close" aria-hidden="true"></i><?= htmlSC($label) ?></a>
        <?php endforeach; ?>
        <a class="fs-sm text-decoration-underline" href="<?= $catalogUrl(array_fill_keys(array_keys($active), '')) ?>"><?= $t('reset') ?></a>
    </div>
<?php endif; ?>

<?php if ($products === []): ?>
    <div class="rounded-4 border p-4 text-body-secondary"><?= $t('empty_catalog') ?>
        <?php if ($parameters !== []): ?><a class="ms-2" href="<?= $url('/shop') ?>"><?= $t('all_products') ?></a><?php endif; ?>
    </div>
<?php else: ?>
    <div class="commerce-listing-grid row row-cols-2 row-cols-md-3 row-cols-lg-4 gx-3 gx-lg-4 gy-4 gy-lg-5<?= $filters['view'] === 'alternate' ? ' commerce-alternate-view' : '' ?>" data-commerce-product-grid>
        <?php
        $favoriteReturn = 'catalog';
        $catalogReturnQuery = http_build_query($parameters + ($page > 1 ? ['page' => $page] : []), '', '&', PHP_QUERY_RFC3986);
        ?>
        <?php foreach ($products as $product): ?>
            <div class="col"><?php require __DIR__ . '/popular-card.php'; ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($total > 0): ?>
    <?php $shown = min($total, ($page - 1) * 24 + count($products)); ?>
    <nav class="commerce-catalog-progress text-center mx-auto pt-5" aria-label="<?= $t('pagination') ?>" data-commerce-progress>
        <p class="fs-sm mb-2" aria-live="polite"><?= htmlSC(sprintf(FireballPluginCommerce::t('products_shown'), $shown, $total)) ?></p>
        <progress class="commerce-catalog-meter d-block w-100 mb-3" value="<?= $shown ?>" max="<?= $total ?>" aria-label="<?= $t('catalog_progress') ?>"></progress>
        <?php if ($page > 1): ?><a class="btn btn-sm btn-link" href="<?= $catalogUrl(['page' => $page - 1]) ?>" rel="prev"><?= $t('previous') ?></a><?php endif; ?>
        <?php if ($shown < $total): ?>
            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= $catalogUrl(['page' => $page + 1]) ?>" data-commerce-more rel="next">
                <?= $t('show_more') ?><i class="ci-chevron-down ms-2" aria-hidden="true"></i>
            </a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
</div>

<div class="offcanvas offcanvas-start" tabindex="-1" id="commerceCatalogFilters" aria-labelledby="commerceCatalogFiltersLabel" style="max-width:100%">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="commerceCatalogFiltersLabel"><?= $t('all_filters') ?></h2>
        <button class="btn-close" type="button" data-bs-dismiss="offcanvas" aria-label="<?= $t('close_filters') ?>"></button>
    </div>
    <form class="offcanvas-body d-flex flex-column" action="<?= $url('/shop') ?>" method="get">
        <?php foreach (array_intersect_key($parameters, array_flip(['q', 'sort', 'view'])) as $key => $value): ?>
            <input type="hidden" name="<?= htmlSC($key) ?>" value="<?= htmlSC($value) ?>">
        <?php endforeach; ?>
        <div class="mb-4">
            <label for="commerce-catalog-category" class="form-label fw-semibold"><?= $t('category') ?></label>
            <select class="form-select rounded-pill" id="commerce-catalog-category" name="category">
                <option value=""><?= $t('all_categories') ?></option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= htmlSC($category['slug']) ?>" <?= $filters['category'] === $category['slug'] ? 'selected' : '' ?>><?= htmlSC($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <fieldset class="border-0 mb-4">
            <legend class="h6 mb-2"><?= $t('price_rub') ?></legend>
            <div class="row g-2">
                <?php foreach (['min' => 'price_from', 'max' => 'price_to'] as $key => $label): ?>
                    <div class="col-6">
                        <label for="commerce-catalog-<?= $key ?>" class="form-label fs-sm"><?= $t($label) ?></label>
                        <input class="form-control rounded-pill" id="commerce-catalog-<?= $key ?>" name="<?= $key ?>" inputmode="decimal"
                               maxlength="11" value="<?= htmlSC($filters[$key]) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php if ($colors !== []): ?>
            <fieldset class="border-0 mb-4">
                <legend class="h6 mb-2"><?= $t('color_name') ?></legend>
                <div class="d-flex gap-2 flex-wrap">
                    <?php foreach (['' => FireballPluginCommerce::t('all_colors')] + $colors as $hex => $name): ?>
                        <label class="btn btn-sm btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2">
                            <input type="radio" class="form-check-input mt-0" name="color" value="<?= htmlSC($hex) ?>" <?= $filters['color'] === $hex ? 'checked' : '' ?>>
                            <?php if ($hex !== ''): ?><span class="rounded-circle commerce-catalog-dot" style="background-color:<?= htmlSC($hex) ?>" aria-hidden="true"></span><?php endif; ?>
                            <?= htmlSC($name) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        <?php endif; ?>
        <label class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="available" value="1" <?= $filters['available'] !== '' ? 'checked' : '' ?>>
            <?= $t('in_stock_only') ?>
        </label>
        <div class="d-flex flex-wrap gap-2 mt-auto">
            <button class="btn btn-primary rounded-pill flex-grow-1" type="submit"><?= $t('apply') ?></button>
            <a class="btn btn-outline-secondary rounded-pill" href="<?= $catalogUrl(array_fill_keys(array_keys($active), '')) ?>"><?= $t('reset') ?></a>
        </div>
    </form>
</div>
