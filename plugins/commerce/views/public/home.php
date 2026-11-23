<?php
require __DIR__ . '/context.php';
$storeName = $settings['name'] !== '' ? $settings['name'] : FireballPluginCommerce::t('catalog');
$slides = $slides ?: [[
    'title' => $storeName, 'subtitle' => $settings['introduction'], 'button_label' => '',
    'image_path' => $products[0]['image_path'] ?? null, 'category_slug' => null,
]];
$multipleSlides = count($slides) > 1;
?>
<h1 class="visually-hidden"><?= htmlSC($storeName) ?></h1>
<section class="bg-body-tertiary commerce-hero" aria-label="<?= $t('slides') ?>" data-commerce-hero>
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 col-lg-5 position-relative z-1 py-4 py-md-5 text-center text-md-start">
                <div class="swiper commerce-hero-copy" data-commerce-hero-copy>
                    <div class="swiper-wrapper">
                        <?php foreach ($slides as $slide): ?>
                            <?php $destination = $slide['category_slug'] ? '/shop?category=' . rawurlencode($slide['category_slug']) : '/shop'; ?>
                            <div class="swiper-slide">
                                <?php if ($slide['subtitle'] !== ''): ?><p class="fs-xl mb-2 mb-lg-3 mb-xl-4"><?= htmlSC($slide['subtitle']) ?></p><?php endif; ?>
                                <h2 class="display-4 text-uppercase mb-4 mb-lg-5"><?= htmlSC($slide['title']) ?></h2>
                                <a class="btn btn-lg btn-outline-dark" href="<?= $url($destination) ?>">
                                    <?= $slide['button_label'] !== '' ? htmlSC($slide['button_label']) : $t('to_catalog') ?>
                                    <i class="ci-arrow-up-right fs-lg ms-2 me-n1" aria-hidden="true"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if ($multipleSlides): ?>
                    <div class="commerce-hero-controls d-flex justify-content-center justify-content-md-start align-items-center gap-2 mt-4 mt-lg-5">
                        <div class="swiper-pagination position-static w-auto d-flex align-items-center gap-2" data-commerce-hero-dots data-slide-label="<?= $t('slide_number') ?>"></div>
                        <button class="btn btn-icon btn-sm btn-ghost rounded-circle" type="button" data-commerce-hero-pause data-label-pause="<?= $t('pause_slides') ?>" data-label-play="<?= $t('play_slides') ?>" aria-label="<?= $t('pause_slides') ?>" hidden><i class="ci-pause" aria-hidden="true"></i></button>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($multipleSlides || $slides[0]['image_path']): ?>
                <div class="col-md-6 col-lg-7 align-self-end">
                    <div class="swiper commerce-hero-gallery" data-commerce-hero-gallery aria-hidden="true">
                        <div class="swiper-wrapper">
                            <?php foreach ($slides as $index => $slide): ?>
                                <div class="swiper-slide commerce-hero-image">
                                    <?php if ($slide['image_path']): ?>
                                        <img src="<?= $media($slide['image_path']) ?>" alt="" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="container py-5 my-2 my-sm-3 mb-md-2 mt-lg-4 my-xl-5" aria-labelledby="commerce-home-categories">
    <h2 class="visually-hidden" id="commerce-home-categories"><?= $t('categories') ?></h2>
    <?php if ($categories === []): ?>
        <p class="mb-0 text-body-secondary"><?= $t('empty_categories') ?></p>
    <?php else: ?>
        <div class="commerce-categories overflow-x-auto pb-1">
            <div class="row row-cols-md-4 row-cols-xl-6 flex-nowrap flex-md-wrap justify-content-md-center g-0 gap-4 gap-md-0 text-center">
                <?php foreach ($categories as $category): ?>
                    <?php
                    $links = $categoryProducts[$category['id']] ?? [];
                    $picture = $category['image_path'] ?: (array_values(array_filter(array_column($links, 'image_path')))[0] ?? null);
                    $categoryUrl = $url('/shop?category=' . rawurlencode($category['slug']));
                    ?>
                    <div class="col commerce-category px-md-2 mb-md-4">
                        <a class="commerce-category-image rounded-circle mx-auto mb-3 overflow-hidden bg-body-tertiary" href="<?= $categoryUrl ?>" aria-label="<?= htmlSC($category['name']) ?>">
                            <?php if ($picture): ?>
                                <img src="<?= $media($picture) ?>" alt="" width="164" height="164" loading="lazy" decoding="async">
                            <?php else: ?>
                                <i class="ci-image fs-2 text-body-secondary" aria-hidden="true"></i>
                            <?php endif; ?>
                        </a>
                        <h3 class="h5 mb-2"><a class="text-body-emphasis text-decoration-none" href="<?= $categoryUrl ?>"><?= htmlSC($category['name']) ?></a></h3>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($links as $link): ?>
                                <li class="mb-1"><a class="text-body-secondary text-decoration-none fs-sm" href="<?= $url('/shop/product/' . $link['slug']) ?>"><?= htmlSC($link['name']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<section class="container pb-5 mt-md-n2 mb-2 mb-sm-3 mb-md-4 mb-xl-5" id="commerce-popular">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 border-bottom pb-3 mb-4">
        <h2 class="h3 mb-0"><?= $t('popular_products') ?></h2>
        <div class="d-flex gap-3 flex-wrap align-items-center">
            <a class="text-body-secondary text-decoration-none fs-sm" href="<?= $url('/shop/favorites') ?>"><?= $t('favorites') ?></a>
            <a class="text-body-emphasis text-decoration-none fs-sm fw-medium" href="<?= $url('/shop') ?>"><?= $t('all_products') ?><i class="ci-chevron-right ms-1" aria-hidden="true"></i></a>
        </div>
    </div>
    <?php if ($products === []): ?>
        <p class="text-body-secondary mb-0"><?= $t('empty_catalog') ?></p>
    <?php else: ?>
        <div class="commerce-popular position-relative" data-commerce-popular>
            <div class="swiper" data-commerce-popular-slider>
                <div class="swiper-wrapper">
                    <?php foreach ($products as $product): ?>
                        <div class="swiper-slide h-auto"><?php require __DIR__ . '/popular-card.php'; ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="commerce-popular-controls d-flex justify-content-center gap-2 mt-4">
                <button class="commerce-popular-prev btn btn-icon btn-outline-secondary rounded-circle" type="button" data-commerce-prev aria-label="<?= $t('previous') ?>"><i class="ci-chevron-left" aria-hidden="true"></i></button>
                <button class="commerce-popular-next btn btn-icon btn-outline-secondary rounded-circle" type="button" data-commerce-next aria-label="<?= $t('next') ?>"><i class="ci-chevron-right" aria-hidden="true"></i></button>
            </div>
        </div>
    <?php endif; ?>
</section>
