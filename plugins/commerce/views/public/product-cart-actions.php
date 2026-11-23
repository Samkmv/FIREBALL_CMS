<?php
$cartProductId = (int)$product['id'];
$alreadyInCart = !empty($cartItems[$cartProductId]);
$canAddToCart = (int)$product['stock'] > 0;
$cartActionClasses = match ($cartActionVariant) {
    'card' => 'btn-sm rounded-pill',
    'product' => 'flex-grow-1',
    default => '',
};
$addTone = $cartActionVariant === 'card' ? 'btn-dark' : 'btn-primary';
?>
<button class="btn <?= $cartActionClasses ?> <?= $canAddToCart ? $addTone : 'btn-secondary' ?>" type="submit"
        <?= $cartActionVariant === 'mobile' ? 'form="commerce-product-purchase"' : '' ?>
        data-commerce-cart-buy="<?= $cartProductId ?>" <?= $alreadyInCart ? 'hidden' : '' ?> <?= $canAddToCart ? '' : 'disabled' ?>>
    <?php if ($cartActionVariant !== 'card' && $canAddToCart): ?><i class="ci-shopping-cart me-2" aria-hidden="true"></i><?php endif; ?>
    <?= $t($canAddToCart ? 'add_to_cart' : 'out_of_stock') ?>
</button>
<a class="btn <?= $cartActionClasses ?> btn-outline-success" href="<?= $url('/shop/cart') ?>"
   data-commerce-cart-open="<?= $cartProductId ?>" <?= $alreadyInCart ? '' : 'hidden' ?>>
    <i class="ci-check me-1" aria-hidden="true"></i><?= $t('in_cart') ?>
</a>
