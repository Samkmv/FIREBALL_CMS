<div class="d-flex flex-wrap gap-2 mt-3" data-commerce-widget>
    <?php foreach ([
        '/admin/commerce/orders?status=new' => 'new_orders',
        '/admin/commerce/products?stock=low' => 'low_stock',
        '/shop' => 'storefront',
    ] as $path => $label): ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?= htmlSC(base_href($path)) ?>"><?= htmlSC(FireballPluginCommerce::t($label)) ?></a>
    <?php endforeach; ?>
</div>
