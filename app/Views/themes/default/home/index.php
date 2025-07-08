<!-- Page content -->
<main class="content-wrapper">

    <!-- Hero slider -->
    <section class="bg-body-tertiary">
        <div class="container">
            <div class="row">

                <!-- Titles master slider -->
                <div class="col-md-6 col-lg-5 d-flex flex-column">
                    <div class="py-4 mt-auto">
                        <div class="swiper pb-1 pt-3 pt-sm-4 py-md-4 py-lg-3" data-swiper="{
                  &quot;spaceBetween&quot;: 24,
                  &quot;loop&quot;: true,
                  &quot;speed&quot;: 400,
                  &quot;controlSlider&quot;: &quot;#heroImages&quot;,
                  &quot;pagination&quot;: {
                    &quot;el&quot;: &quot;#sliderBullets&quot;,
                    &quot;clickable&quot;: true
                  },
                  &quot;autoplay&quot;: {
                    &quot;delay&quot;: 5500,
                    &quot;disableOnInteraction&quot;: false
                  }
                }">
                            <div class="swiper-wrapper align-items-center">

                                <!-- Item -->
                                <div class="swiper-slide text-center text-md-start">
                                    <p class="fs-xl mb-2 mb-lg-3 mb-xl-4">Новая коллекция</p>
                                    <h2 class="display-4 text-uppercase mb-4 mb-xl-5">Новый осенний <br class="d-none d-md-inline">сезон 2024</h2>
                                    <a class="btn btn-lg btn-outline-dark" href="shop-catalog-fashion.html">
                                        Подробнее
                                        <i class="ci-arrow-up-right fs-lg ms-2 me-n1"></i>
                                    </a>
                                </div>

                                <!-- Item -->
                                <div class="swiper-slide text-center text-md-start">
                                    <p class="fs-xl mb-2 mb-lg-3 mb-xl-4">Готовы к вечеринке?</p>
                                    <h2 class="display-4 text-uppercase mb-4 mb-xl-5">Choose outfits for parties</h2>
                                    <a class="btn btn-lg btn-outline-dark" href="shop-catalog-fashion.html">
                                        Подробнее
                                        <i class="ci-arrow-up-right fs-lg ms-2 me-n1"></i>
                                    </a>
                                </div>

                                <!-- Item -->
                                <div class="swiper-slide text-center text-md-start">
                                    <p class="fs-xl mb-2 mb-lg-3 mb-xl-4">Серый цвет смотрится по новому</p>
                                    <h2 class="display-4 text-uppercase mb-4 mb-xl-5">-50% на серый цвет</h2>
                                    <a class="btn btn-lg btn-outline-dark" href="shop-catalog-fashion.html">
                                        Подробнее
                                        <i class="ci-arrow-up-right fs-lg ms-2 me-n1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Slider bullets (pagination) -->
                    <div class="d-flex justify-content-center justify-content-md-start pb-4 pb-xl-5 mt-n1 mt-md-auto mb-md-3 mb-lg-4">
                        <div class="swiper-pagination position-static w-auto pb-1" id="sliderBullets"></div>
                    </div>
                </div>

                <!-- Linked images (controlled slider) -->
                <div class="col-md-6 col-lg-7 align-self-end">
                    <div class="position-relative ms-md-n4">
                        <div class="ratio" style="--cz-aspect-ratio: calc(662 / 770 * 100%)"></div>
                        <div class="swiper position-absolute top-0 start-0 w-100 h-100 user-select-none" id="heroImages" data-swiper="{
                  &quot;allowTouchMove&quot;: false,
                  &quot;loop&quot;: true,
                  &quot;effect&quot;: &quot;fade&quot;,
                  &quot;fadeEffect&quot;: {
                    &quot;crossFade&quot;: true
                  }
                }">
                            <div class="swiper-wrapper">
                                <div class="swiper-slide">
                                    <img src="assets/img/slider/1.png" class="rtl-flip" alt="Image">
                                </div>
                                <div class="swiper-slide">
                                    <img src="assets/img/slider/2.png" class="rtl-flip" alt="Image">
                                </div>
                                <div class="swiper-slide">
                                    <img src="assets/img/slider/3.png" class="rtl-flip" alt="Image">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories -->
    <?php if (!empty($root_categories)): ?>
    <section class="container py-5 my-2 my-sm-3 mb-md-2 mt-lg-4 my-xl-5">
        <div class="overflow-x-auto pt-xxl-3" data-simplebar="" data-simplebar-auto-hide="false">
            <div class="row flex-nowrap flex-md-wrap justify-content-md-center g-0 gap-4 gap-md-0">

                <!-- Category -->
                <?php foreach ($root_categories as $category): ?>

                <div class="col col-md-4 col-lg-3 col-xl-2 mb-4">
                    <div class="category-card w-100 text-center px-1 px-lg-2 px-xxl-3 mx-auto" style="min-width: 165px">
                        <div>
                            <a class="d-block text-decoration-none" href="<?= base_href("/category/{$category['slug']}") ?>">
                                <div class="bg-body-tertiary rounded-pill mb-3 mx-auto" style="max-width: 164px">
                                    <div class="ratio ratio-1x1">
                                        <img style="object-fit: cover;" src="<?= get_image($category['image']) ?>" class="rounded-pill" alt="Image">
                                    </div>
                                </div>
                                <h3 class="h6 text-truncate"><?= htmlSC($category['title']) ?></h3>
                            </a>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>

            </div>
        </div>
    </section>
    <?php endif; ?>


    <!-- Popular products carousel -->
    <?php if (!empty($sales_products)): ?>
    <section class="container pb-5 mt-md-n2 mb-2 mb-sm-3 mb-md-4 mb-xl-5">

        <!-- Heading -->
        <div class="d-flex align-items-center justify-content-between border-bottom pb-3 pb-md-4" style="margin-top: 96px;">
            <h2 class="h3 mb-0"><?= print_translation('home_index_sale') ?></h2>
            <div class="nav ms-3">
                <a class="nav-link animate-underline px-0 py-2" href="shop-catalog-furniture.html">
                    <span class="animate-target"><?= print_translation('home_index_sale_btn') ?></span>
                    <i class="ci-chevron-right fs-base ms-1"></i>
                </a>
            </div>
        </div>

        <!-- Product carousel -->
        <div class="position-relative pb-xxl-3">

            <!-- External slider prev/next buttons visible on screens > 500px wide (sm breakpoint) -->
            <button type="button" class="popular-prev btn btn-icon btn-outline-secondary bg-body rounded-circle animate-slide-start position-absolute top-50 start-0 z-2 translate-middle mt-n5 d-none d-sm-inline-flex" aria-label="Previous slide" tabindex="0" aria-controls="swiper-wrapper-fcce196a4417f586">
                <i class="ci-chevron-left fs-lg animate-target"></i>
            </button>
            <button type="button" class="popular-next btn btn-icon btn-outline-secondary bg-body rounded-circle animate-slide-end position-absolute top-50 start-100 z-2 translate-middle mt-n5 d-none d-sm-inline-flex" aria-label="Next slide" tabindex="0" aria-controls="swiper-wrapper-fcce196a4417f586">
                <i class="ci-chevron-right fs-lg animate-target"></i>
            </button>

            <!-- Slider -->
            <div class="swiper pt-3 pt-sm-4 swiper-initialized swiper-horizontal swiper-backface-hidden" data-swiper="{
            &quot;slidesPerView&quot;: 2,
            &quot;spaceBetween&quot;: 24,
            &quot;loop&quot;: true,
            &quot;navigation&quot;: {
              &quot;prevEl&quot;: &quot;.popular-prev&quot;,
              &quot;nextEl&quot;: &quot;.popular-next&quot;
            },
            &quot;breakpoints&quot;: {
              &quot;768&quot;: {
                &quot;slidesPerView&quot;: 3
              },
              &quot;992&quot;: {
                &quot;slidesPerView&quot;: 4
              }
            }
          }">

                <div class="swiper-wrapper" id="swiper-wrapper-fcce196a4417f586" aria-live="polite">

                    <?php foreach ($sales_products as $product): ?>

                        <?= view()->renderPartial('incs/product-card', ['product' => $product]) ?>

                    <?php endforeach; ?>

                </div>
                <span class="swiper-notification" aria-live="assertive" aria-atomic="true"></span></div>

        </div>

        <!-- External slider prev/next buttons visible on screens < 500px wide (sm breakpoint) -->
        <div class="d-flex justify-content-center gap-2 mt-1 pt-4 d-sm-none">
            <button type="button" class="popular-prev btn btn-icon btn-outline-secondary bg-body rounded-circle animate-slide-start me-1" aria-label="Previous slide" tabindex="0" aria-controls="swiper-wrapper-fcce196a4417f586">
                <i class="ci-chevron-left fs-lg animate-target"></i>
            </button>
            <button type="button" class="popular-next btn btn-icon btn-outline-secondary bg-body rounded-circle animate-slide-end" aria-label="Next slide" tabindex="0" aria-controls="swiper-wrapper-fcce196a4417f586">
                <i class="ci-chevron-right fs-lg animate-target"></i>
            </button>
        </div>
    </section>
    <?php endif; ?>


</main>