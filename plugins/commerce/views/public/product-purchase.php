<?php $favorite = in_array((int)$product['id'], $favorites, true); ?>
<div class="d-flex align-items-end gap-2">
    <form id="commerce-product-purchase" method="post" action="<?= $url('/shop/cart/add') ?>" data-commerce-add class="d-flex align-items-end gap-2 flex-grow-1">
        <?= get_csrf_field() ?><input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
        <?php if ($showQuantity && $product['stock'] > 0): ?>
            <div class="flex-shrink-0" data-commerce-cart-buy="<?= (int)$product['id'] ?>" <?= !empty($cartItems[(int)$product['id']]) ? 'hidden' : '' ?>>
                <label class="visually-hidden" for="commerce-quantity"><?= $t('quantity') ?></label>
                <input class="form-control commerce-quantity" id="commerce-quantity" type="number" name="quantity" min="1" max="<?= min(99, (int)$product['stock']) ?>" value="1" required>
            </div>
        <?php endif; ?>
        <?php $cartActionVariant = 'product'; require __DIR__ . '/product-cart-actions.php'; ?>
    </form>
    <form method="post" action="<?= $url('/shop/favorites/toggle') ?>" data-commerce-favorite data-favorite-error="<?= $t('favorite_request_failed') ?>">
        <?= get_csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
        <input type="hidden" name="return" value="product">
        <input type="hidden" name="section" value="<?= htmlSC($section) ?>">
        <button class="btn btn-icon <?= $favorite ? 'btn-dark' : 'btn-outline-secondary' ?>" type="submit" aria-label="<?= $t('favorites') ?>" aria-pressed="<?= $favorite ? 'true' : 'false' ?>">
            <i class="ci-heart" aria-hidden="true"></i>
        </button>
    </form>
</div>
