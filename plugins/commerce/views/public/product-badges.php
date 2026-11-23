<?php
$previousPrice = \Fireball\Commerce\Catalog::previousPrice($product);
$isNew = !empty($product['is_new']);
?>
<?php if ($isNew || $previousPrice !== null): ?>
    <div class="commerce-product-badges d-flex flex-wrap gap-1">
        <?php if ($isNew): ?><span class="badge rounded-pill text-bg-warning"><?= $t('new_badge') ?></span><?php endif; ?>
        <?php if ($previousPrice !== null): ?>
            <?php $discount = intdiv(($previousPrice - (int)$product['price_minor']) * 100, $previousPrice); ?>
            <span class="badge rounded-pill text-bg-danger"><?= $discount > 0
                ? htmlSC(sprintf(FireballPluginCommerce::t('discount_percent'), $discount)) : $t('discount_badge') ?></span>
        <?php endif; ?>
    </div>
<?php endif; ?>
