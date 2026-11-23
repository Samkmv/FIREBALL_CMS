<?php $tab = 'overview'; require __DIR__ . '/open.php'; ?>
<div class="row g-3 mb-4">
    <?php foreach ([
        ['new_orders', 'ci-shopping-bag', '/orders?status=new', (int)$stats['new_orders']],
        ['products', 'ci-package', '/products', (int)$stats['products']],
        ['low_stock', 'ci-alert-triangle', '/products?stock=low', (int)$stats['low_stock']],
        ['paid_total', 'ci-credit-card', '/orders', $money((int)$stats['paid_total'])],
    ] as [$label, $icon, $path, $value]): ?>
        <div class="col-6 col-xl-3">
            <a class="fb-card d-block h-100 p-3 text-reset text-decoration-none" href="<?= $url('/admin/commerce' . $path) ?>">
                <span class="d-flex gap-2 align-items-center small text-body-secondary"><i class="<?= $icon ?>" aria-hidden="true"></i><?= $t($label) ?></span>
                <strong class="d-block fs-4 mt-1"><?= $value ?></strong>
            </a>
        </div>
    <?php endforeach; ?>
</div>
<div class="d-flex flex-wrap gap-2">
    <a class="btn btn-primary" href="<?= $url('/admin/commerce/products/edit/0') ?>"><i class="ci-plus me-1" aria-hidden="true"></i><?= $t('add_product') ?></a>
    <a class="btn btn-outline-secondary" href="<?= $url('/admin/commerce/settings') ?>"><?= $t('settings') ?></a>
</div>
<?= view()->renderPartial('admin/shell_close') ?>
