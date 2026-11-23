<?php
require __DIR__ . '/context.php';
$formatOperation = static fn(string $key, string $name): string => htmlSC(sprintf(FireballPluginCommerce::t($key), $name));
?>
<div class="min-h-0 overflow-y-auto px-2 pt-2" data-commerce-cart-items>
    <?php if ($review['lines'] === []): ?>
        <p class="text-body-secondary mb-0 px-2"><?= $t('empty_cart') ?></p>
    <?php endif; ?>
    <?php foreach ($review['lines'] as $line): ?>
        <?php $id = (int)$line['id']; $name = $line['name']; ?>
        <div class="d-flex align-items-start gap-3 pb-3 mb-3 border-bottom" data-commerce-cart-row="<?= $id ?>">
            <?php if (!empty($line['image_path'])): ?>
                <img class="rounded-3 object-fit-contain bg-body-tertiary flex-shrink-0" src="<?= $media($line['image_path']) ?>" alt="" width="72" height="72" loading="lazy">
            <?php else: ?>
                <span class="commerce-cart-thumb rounded-3 flex-shrink-0"><i class="ci-image" aria-hidden="true"></i></span>
            <?php endif; ?>
            <div class="min-w-0 flex-grow-1">
                <?php if ($line['available']): ?>
                    <a class="text-body-emphasis fw-medium text-break text-decoration-none" href="<?= $url('/shop/product/' . $line['slug']) ?>"><?= htmlSC($name) ?></a>
                <?php else: ?><span class="fw-medium text-break"><?= htmlSC($name) ?></span><?php endif; ?>
                <div class="fw-semibold my-1"><?= $money((int)$line['line_minor']) ?></div>
                <?php if (!$line['valid']): ?>
                    <div class="text-danger fs-xs mb-2"><?= $line['available'] && $line['stock'] > 0
                        ? htmlSC(sprintf(FireballPluginCommerce::t('available_quantity'), (int)$line['stock'])) : $t('unavailable') ?></div>
                <?php endif; ?>
                <form class="d-flex align-items-center justify-content-between gap-2" action="<?= $url('/shop/cart/line') ?>" method="post" data-commerce-cart-line>
                    <?= get_csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <div class="commerce-drawer-quantity rounded-pill border">
                        <button type="submit" name="operation" value="decrease" class="btn btn-icon btn-sm" aria-label="<?= $formatOperation('cart_decrease', $name) ?>"><i class="ci-minus" aria-hidden="true"></i></button>
                        <span class="text-center fs-sm"><span class="visually-hidden"><?= $t('quantity') ?> </span><?= (int)$line['quantity'] ?></span>
                        <button type="submit" name="operation" value="increase" class="btn btn-icon btn-sm" aria-label="<?= $formatOperation('cart_increase', $name) ?>"
                            <?= !$line['available'] || $line['quantity'] >= min(99, (int)$line['stock']) ? 'disabled' : '' ?>><i class="ci-plus" aria-hidden="true"></i></button>
                    </div>
                    <button type="submit" name="operation" value="remove" class="btn btn-icon btn-sm btn-outline-secondary rounded-circle" aria-label="<?= $formatOperation('cart_remove', $name) ?>">
                        <i class="ci-trash" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<div class="border-top mt-auto pt-3 px-2 pb-2 flex-shrink-0">
    <?php if ($review['lines'] === []): ?>
        <a class="btn btn-lg btn-primary rounded-pill w-100" href="<?= $url('/shop') ?>"><?= $t('to_catalog') ?></a>
    <?php else: ?>
        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
            <span><?= $t('cart_subtotal') ?></span><strong class="fs-5"><?= $money((int)$review['subtotal']) ?></strong>
        </div>
        <form class="mb-3" method="post" action="<?= $url('/shop/cart/clear') ?>" data-commerce-cart-clear>
            <?= get_csrf_field() ?><button class="btn btn-sm btn-link p-0" type="submit"><?= $t('clear_cart') ?></button>
        </form>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-lg btn-secondary rounded-pill flex-fill" href="<?= $url('/shop/cart') ?>"><?= $t('view_cart') ?></a>
            <?php if ($review['valid']): ?>
                <a class="btn btn-lg btn-primary rounded-pill flex-fill" href="<?= $url('/shop/checkout') ?>"><?= $t('place_order') ?></a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
