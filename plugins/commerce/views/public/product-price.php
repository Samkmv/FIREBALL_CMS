<?php $previousPrice = \Fireball\Commerce\Catalog::previousPrice($product); ?>
<span class="<?= $previousPrice === null ? '' : 'text-danger' ?>"><?= $money((int)$product['price_minor']) ?></span>
<?php if ($previousPrice !== null): ?>
    <s class="ms-1 text-body-secondary fw-normal fs-sm" aria-label="<?= $t('compare_at') ?> <?= $money($previousPrice) ?>"><?= $money($previousPrice) ?></s>
<?php endif; ?>
