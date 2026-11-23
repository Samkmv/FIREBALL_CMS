<?php
$tab = 'products';
$actions = '<a class="btn btn-primary d-inline-flex align-items-center gap-2" href="'
    . htmlSC(base_href('/admin/commerce/products/edit/0')) . '"><i class="ci-plus" aria-hidden="true"></i>'
    . htmlSC(FireballPluginCommerce::t('add_product')) . '</a>';
require __DIR__ . '/open.php';
$rows = $mobile = [];
foreach ($products as $product) {
    $link = '<a class="fw-semibold text-break" href="' . $url('/admin/commerce/products/edit/' . (int)$product['id']) . '">' . htmlSC($product['name']) . '</a>';
    $status = '<span class="badge rounded-pill text-bg-' . ($product['is_active'] ? 'success' : 'secondary') . '">'
        . $t($product['is_active'] ? 'published' : 'draft') . '</span>';
    $stockLabel = (int)$product['stock'];
    $stockHtml = $stockLabel <= 5 ? '<strong class="text-warning-emphasis">' . $stockLabel . '</strong>' : (string)$stockLabel;
    $rows[] = ['cells' => [
        ['html' => $link], ['value' => $product['sku']], ['value' => $product['category_name'] ?? '—'],
        ['html' => $money((int)$product['price_minor'])], ['html' => $stockHtml], ['html' => $status],
    ]];
    $mobile[] = ['title' => ['html' => $link], 'status' => [['html' => $status]], 'extra_fields' => [
        ['label' => FireballPluginCommerce::t('sku'), 'value' => $product['sku']],
        ['label' => FireballPluginCommerce::t('price'), 'html' => $money((int)$product['price_minor'])],
        ['label' => FireballPluginCommerce::t('stock'), 'html' => $stockHtml],
    ]];
}
?>
<form class="d-flex flex-wrap gap-2 mb-3" method="get" action="<?= $url('/admin/commerce/products') ?>">
    <label class="visually-hidden" for="commerce-admin-search"><?= $t('search') ?></label>
    <input class="form-control w-auto" id="commerce-admin-search" type="search" name="q" value="<?= htmlSC($query) ?>" placeholder="<?= $t('search_hint') ?>">
    <label class="visually-hidden" for="commerce-stock-filter"><?= $t('stock') ?></label>
    <select id="commerce-stock-filter" name="stock" class="form-select w-auto">
        <option value=""><?= $t('all_stock') ?></option><option value="low" <?= $stock === 'low' ? 'selected' : '' ?>><?= $t('low_stock') ?></option>
    </select>
    <button class="btn btn-outline-secondary" type="submit"><?= $t('apply') ?></button>
</form>
<?= view()->renderPartial('admin/partials/table', [
    'columns' => array_map(static fn(string $key): array => ['label' => FireballPluginCommerce::t($key)], ['product', 'sku', 'category', 'price', 'stock', 'status']),
    'rows' => $rows, 'mobile_cards' => $mobile, 'empty_text' => FireballPluginCommerce::t('empty_catalog'),
]) ?>
<?php $paginationPath = '/admin/commerce/products'; $filters = ['q' => $query, 'stock' => $stock]; require __DIR__ . '/../pagination.php'; ?>
<?= view()->renderPartial('admin/shell_close') ?>
