<?php
require __DIR__ . '/open.php';
$productPath = '/shop/product/' . $product['slug'];
$productTitle = htmlSC($product['name']);
$ratingLabel = sprintf(FireballPluginCommerce::t('product_rating'), number_format($rating['average'], 1), $rating['total']);
$tabs = ['overview' => ['product_overview', ''], 'details' => ['product_details', '/details']];
if ($reviewsReady) {
    $tabs['reviews'] = ['reviews', '/reviews'];
}
?>
<nav aria-label="<?= $t('location') ?>" class="mb-4">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= $url('/') ?>"><?= htmlSC(return_translation('tpl_menu_nav_index')) ?></a></li>
        <li class="breadcrumb-item"><a href="<?= $url('/shop') ?>"><?= $t('catalog') ?></a></li>
        <?php if ($product['category_name']): ?>
            <li class="breadcrumb-item"><a href="<?= $url('/shop?category=' . rawurlencode($product['category_slug'])) ?>"><?= htmlSC($product['category_name']) ?></a></li>
        <?php endif; ?>
        <li class="breadcrumb-item active text-break" aria-current="page"><?= $productTitle ?></li>
    </ol>
</nav>
<h1 class="h3 text-break mb-4"><?= $productTitle ?></h1>
<div class="commerce-product-nav d-flex align-items-center justify-content-between gap-3 border-bottom mb-4 mb-lg-5">
    <nav class="nav nav-underline flex-wrap column-gap-3 column-gap-sm-4 row-gap-0" aria-label="<?= $t('product_navigation') ?>">
        <?php foreach ($tabs as $key => [$label, $suffix]): ?>
            <a class="nav-link text-nowrap<?= $section === $key ? ' active' : '' ?>" href="<?= $url($productPath . $suffix) ?>" <?= $section === $key ? 'aria-current="page"' : '' ?>>
                <?= $t($label) ?><?= $key === 'reviews' && $rating['total'] > 0 ? ' (' . (int)$rating['total'] . ')' : '' ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php if ($rating['total'] > 0): ?>
        <a class="d-none d-md-inline-flex align-items-center gap-2 text-body-secondary text-decoration-none fs-sm" href="<?= $url($productPath . '/reviews') ?>" aria-label="<?= htmlSC($ratingLabel) ?>">
            <?php $stars = $rating['average']; require __DIR__ . '/product-stars.php'; ?>
            <span><?= htmlSC(number_format($rating['average'], 1)) ?> / 5</span>
        </a>
    <?php endif; ?>
</div>
<?php if ($section === 'overview'): ?>
    <?php require __DIR__ . '/product-overview.php'; ?>
<?php else: ?>
    <div class="row g-4 g-lg-5">
        <aside class="col-md-5 order-md-2">
            <div class="commerce-product-sidebar position-sticky border rounded-4 p-3 p-lg-4" data-commerce-purchase>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <?php if (!empty($product['image_path'] ?: ($product['hover_image_path'] ?? null))): ?>
                        <img class="commerce-product-mini object-fit-contain bg-body-tertiary rounded-3" src="<?= $media($product['image_path'] ?: $product['hover_image_path']) ?>" width="88" height="88" alt="">
                    <?php endif; ?>
                    <div class="min-w-0">
                        <div class="fw-medium text-break mb-2"><?= $productTitle ?></div>
                        <strong class="h5 mb-0"><?php require __DIR__ . '/product-price.php'; ?></strong>
                    </div>
                </div>
                <?php $showQuantity = false; require __DIR__ . '/product-purchase.php'; ?>
            </div>
        </aside>
        <div class="col-md-7 order-md-1">
            <?php require __DIR__ . ($section === 'details' ? '/product-details.php' : '/product-reviews.php'); ?>
        </div>
    </div>
<?php endif; ?>
<?php if ($viewed !== []): ?>
    <section class="pt-5 mt-lg-4" aria-labelledby="commerce-viewed-title">
        <h2 class="h3 border-bottom pb-3 mb-4" id="commerce-viewed-title"><?= $t('recently_viewed') ?></h2>
        <div class="commerce-popular position-relative" data-commerce-popular>
            <div class="swiper" data-commerce-popular-slider>
                <div class="swiper-wrapper">
                    <?php foreach ($viewed as $previousProduct): ?>
                        <div class="swiper-slide h-auto">
                            <a class="text-body-emphasis text-decoration-none" href="<?= $url('/shop/product/' . $previousProduct['slug']) ?>">
                                <div class="commerce-product-photo rounded-3 mb-2">
                                    <?php if ($previousProduct['image_path']): ?>
                                        <img src="<?= $media($previousProduct['image_path']) ?>" loading="lazy" decoding="async" alt="">
                                    <?php else: ?><i class="ci-image" aria-hidden="true"></i><?php endif; ?>
                                </div>
                                <span class="d-block fw-medium text-break mb-1"><?= htmlSC($previousProduct['name']) ?></span>
                            </a>
                            <strong class="fs-sm"><?= $money((int)$previousProduct['price_minor']) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="commerce-popular-controls d-flex justify-content-center gap-2 mt-4">
                <button class="commerce-popular-prev btn btn-icon btn-outline-secondary rounded-circle" type="button" data-commerce-prev aria-label="<?= $t('previous') ?>"><i class="ci-chevron-left" aria-hidden="true"></i></button>
                <button class="commerce-popular-next btn btn-icon btn-outline-secondary rounded-circle" type="button" data-commerce-next aria-label="<?= $t('next') ?>"><i class="ci-chevron-right" aria-hidden="true"></i></button>
            </div>
        </div>
    </section>
<?php endif; ?>
<?php if ($product['stock'] > 0): ?>
    <div class="commerce-mobile-purchase d-md-none" data-commerce-sticky-purchase hidden>
        <div class="container d-flex align-items-center justify-content-between gap-3">
            <strong class="text-nowrap"><?= $money((int)$product['price_minor']) ?></strong>
            <?php $cartActionVariant = 'mobile'; require __DIR__ . '/product-cart-actions.php'; ?>
        </div>
    </div>
<?php endif; ?>
</div>
