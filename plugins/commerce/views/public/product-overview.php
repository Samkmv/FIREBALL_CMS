<?php
$photos = \Fireball\Commerce\ProductImage::photos($product);
?>
<div class="row g-4 g-lg-5">
    <div class="col-md-6">
        <div class="position-relative">
        <?php if (count($photos) > 1): ?>
            <div class="commerce-product-gallery" data-commerce-gallery>
                <div class="swiper position-relative" data-commerce-gallery-slider>
                    <div class="swiper-wrapper">
                        <?php foreach ($photos as $index => $photo): ?>
                            <div class="swiper-slide">
                                <a class="commerce-product-photo rounded-4" href="<?= $media($photo) ?>" data-glightbox data-gallery="commerce-product">
                                    <img src="<?= $media($photo) ?>" alt="<?= $productTitle ?> · <?= $index + 1 ?>" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="commerce-gallery-arrow commerce-gallery-prev btn btn-icon btn-outline-secondary rounded-circle" type="button" aria-label="<?= $t('previous') ?>" data-gallery-prev><i class="ci-chevron-left" aria-hidden="true"></i></button>
                    <button class="commerce-gallery-arrow commerce-gallery-next btn btn-icon btn-outline-secondary rounded-circle" type="button" aria-label="<?= $t('next') ?>" data-gallery-next><i class="ci-chevron-right" aria-hidden="true"></i></button>
                </div>
                <div class="commerce-gallery-thumbs d-flex gap-2 mt-3 pb-1" data-gallery-thumbs>
                    <?php foreach ($photos as $index => $photo): ?>
                        <button class="commerce-gallery-thumb btn btn-outline-secondary flex-shrink-0 p-1 rounded-3" type="button"
                                data-photo-index="<?= $index ?>" aria-label="<?= htmlSC(sprintf(FireballPluginCommerce::t('show_product_photo'), $index + 1)) ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>">
                            <img src="<?= $media($photo) ?>" width="56" height="56" alt="" decoding="async">
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php elseif ($photos !== []): ?>
            <a class="commerce-product-photo rounded-4" href="<?= $media($photos[0]) ?>" data-glightbox>
                <img src="<?= $media($photos[0]) ?>" alt="<?= $productTitle ?>" fetchpriority="high" decoding="async">
            </a>
        <?php else: ?>
            <div class="commerce-product-photo rounded-4"><i class="ci-image" aria-hidden="true"></i></div>
        <?php endif; ?>
        <?php require __DIR__ . '/product-badges.php'; ?>
        </div>
    </div>
    <div class="col-md-6">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <span class="text-body-secondary small"><?= $t('sku') ?> <?= htmlSC($product['sku']) ?></span>
            <span class="badge rounded-pill text-bg-<?= $product['stock'] > 0 ? 'success' : 'secondary' ?>">
                <?= $product['stock'] > 0 ? htmlSC(sprintf(FireballPluginCommerce::t('available_quantity'), (int)$product['stock'])) : $t('out_of_stock') ?>
            </span>
        </div>
        <?php require __DIR__ . '/swatches.php'; ?>
        <div class="display-6 fw-semibold mb-3"><?php require __DIR__ . '/product-price.php'; ?></div>
        <div class="mb-4" data-commerce-purchase>
            <?php $showQuantity = true; require __DIR__ . '/product-purchase.php'; ?>
        </div>
        <?php if ($product['description'] !== ''): ?>
            <div class="border-top pt-4 mb-4">
                <h2 class="h6"><?= $t('description') ?></h2>
                <p class="commerce-copy text-body-secondary mb-3"><?= htmlSC($product['description']) ?></p>
                <a class="text-decoration-none" href="<?= $url($productPath . '/details') ?>"><?= $t('product_details') ?><i class="ci-chevron-right ms-1" aria-hidden="true"></i></a>
            </div>
        <?php endif; ?>
        <?php if ($settings['pickup_enabled'] || $settings['delivery_enabled']): ?>
            <div class="border-top pt-4">
                <h2 class="h6 mb-3"><?= $t('fulfillment') ?></h2>
                <?php if ($settings['pickup_enabled']): ?>
                    <div class="d-flex gap-2 mb-2">
                        <i class="ci-map-pin fs-lg text-body-secondary" aria-hidden="true"></i>
                        <div><strong><?= $t('pickup') ?></strong><?php if ($settings['pickup_address'] !== ''): ?><div class="text-body-secondary fs-sm"><?= htmlSC($settings['pickup_address']) ?></div><?php endif; ?></div>
                    </div>
                <?php endif; ?>
                <?php if ($settings['delivery_enabled']): ?>
                    <div class="d-flex gap-2">
                        <i class="ci-truck fs-lg text-body-secondary" aria-hidden="true"></i>
                        <div><strong><?= $t('delivery') ?></strong><div class="text-body-secondary fs-sm"><?= $money((int)$settings['delivery_minor']) ?></div></div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
