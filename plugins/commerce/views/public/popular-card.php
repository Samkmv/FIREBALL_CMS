<?php
$productUrl = $url('/shop/product/' . $product['slug']);
$favorite = in_array((int)$product['id'], $favorites, true);
$mainPhoto = $product['image_path'] ?: ($product['hover_image_path'] ?? null);
$alternatePhoto = $product['image_path'] ? ($product['hover_image_path'] ?? null) : null;
?>
<article class="commerce-popular-card h-100 d-flex flex-column" data-commerce-card="<?= (int)$product['id'] ?>">
    <div class="position-relative">
        <a class="commerce-popular-image position-relative rounded-3 overflow-hidden bg-body-tertiary mb-3" href="<?= $productUrl ?>" aria-label="<?= htmlSC($product['name']) ?>">
            <?php if ($mainPhoto): ?>
                <img src="<?= $media($mainPhoto) ?>" alt="" loading="lazy" decoding="async">
                <?php if ($alternatePhoto): ?><img class="commerce-hover-image" src="<?= $media($alternatePhoto) ?>" alt="" loading="lazy" decoding="async"><?php endif; ?>
            <?php else: ?>
                <i class="ci-image fs-2 text-body-secondary" aria-hidden="true"></i>
            <?php endif; ?>
        </a>
        <?php require __DIR__ . '/product-badges.php'; ?>
    </div>
    <?php require __DIR__ . '/swatches.php'; ?>
    <h3 class="h6 mb-2"><a class="text-body-emphasis text-decoration-none text-break" href="<?= $productUrl ?>"><?= htmlSC($product['name']) ?></a></h3>
    <strong class="mb-3"><?php require __DIR__ . '/product-price.php'; ?></strong>
    <div class="d-flex gap-2 align-items-center flex-wrap mt-auto">
        <form method="post" action="<?= $url('/shop/cart/add') ?>" data-commerce-add>
            <?= get_csrf_field() ?><input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
            <?php $cartActionVariant = 'card'; require __DIR__ . '/product-cart-actions.php'; ?>
        </form>
        <form method="post" action="<?= $url('/shop/favorites/toggle') ?>" data-commerce-favorite data-favorite-error="<?= $t('favorite_request_failed') ?>">
            <?= get_csrf_field() ?><input type="hidden" name="id" value="<?= (int)$product['id'] ?>"><input type="hidden" name="return" value="<?= htmlSC($favoriteReturn) ?>">
            <?php if ($favoriteReturn === 'catalog'): ?><input type="hidden" name="catalog_query" value="<?= htmlSC($catalogReturnQuery) ?>"><?php endif; ?>
            <button class="btn btn-icon btn-sm <?= $favorite ? 'btn-dark' : 'btn-outline-secondary' ?> rounded-circle" type="submit" aria-label="<?= $t('favorites') ?> · <?= htmlSC($product['name']) ?>" aria-pressed="<?= $favorite ? 'true' : 'false' ?>">
                <i class="ci-heart" aria-hidden="true"></i>
            </button>
        </form>
    </div>
</article>
