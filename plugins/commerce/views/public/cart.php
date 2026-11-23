<?php require __DIR__ . '/open.php'; ?>
<h1 class="h3 mb-4"><?= $t('cart') ?></h1>
<div class="text-danger small mb-2" role="status" data-commerce-cart-page-message></div>
<div data-commerce-cart-page><?php require __DIR__ . '/cart-content.php'; ?></div>
</div>
