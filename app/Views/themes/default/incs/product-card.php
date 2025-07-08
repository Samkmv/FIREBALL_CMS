<?php /** @var array $product */ ?>

<div class="swiper-slide swiper-slide-active product-id-<?= $product['id']?>" style="width: 306px; margin-right: 24px;" role="group" aria-label="1 / 6" data-swiper-slide-index="0">
    <div class="animate-underline">

        <div class="position-absolute top-0 start-0 z-2 mt-2 mt-sm-3 ms-2 ms-sm-3">

        <?php if ($product['is_sale']): ?>

            <span class="badge text-bg-danger">SALE</span>

        <?php endif; ?>

        <?php if ($product['in_stock']): ?>

            <span class="badge text-body-emphasis bg-secondary-subtle"><?= print_translation('home_index_product_card_in_stock') ?></span>

        <?php else: ?>

            <span class="badge text-body-emphasis bg-secondary-subtle"><?= print_translation('home_index_product_card_no_stock') ?></span>

        <?php endif; ?>

        </div>

        <a class="ratio ratio-1x1 d-block mb-3" href="<?= base_href("/product/{$product['slug']}") ?>">
            <img style="object-fit: cover;" src="<?= get_image($product['image']) ?>" class="rounded-4" alt="<?= htmlSC($product['title']) ?>">
        </a>
        <h3 class="mb-2">
            <a class="d-block fs-sm fw-medium text-truncate" href="<?= base_href("/product/{$product['slug']}") ?>">
                <span class="animate-target"><?= htmlSC($product['title']) ?></span>
            </a>
        </h3>
        <div class="h6">$<?= $product['price'] ?>

            <?php if ($product['old_price']): ?>

                <del class="fs-sm fw-normal text-body-tertiary">$<?= $product['old_price'] ?></del>

            <?php endif;?>
        </div>

        <?php if ($product['in_stock']): ?>
        <div class="d-flex gap-2">
            <button type="button" class="btn <?= \App\Helpers\Cart\Cart::hasProductInCart($product['id']) ? 'btn-secondary' : 'btn-dark' ?> w-100 rounded-pill px-3 add-to-cart" data-id="<?= $product['id'] ?>">
                <span class="text"><?= \App\Helpers\Cart\Cart::hasProductInCart($product['id']) ? print_translation('home_index_product_card_added_to_card') : print_translation('home_index_product_card_add_to_card') ?></span>
                <span class="loader spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
            </button>
            <button type="button" class="btn btn-icon btn-secondary rounded-circle animate-pulse" aria-label="Add to wishlist">
                <i class="ci-heart fs-base animate-target"></i>
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>