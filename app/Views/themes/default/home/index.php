<!-- Page content -->
<main class="content-wrapper">
    <?php $postUrl = static fn(array $post): string => base_href('/posts/' . $post['slug']); ?>

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

    <?php if (!empty($featured_posts)): ?>
        <section class="container py-5 mt-1 my-sm-2 my-md-3 my-lg-4 my-xl-5">
            <div class="row py-2 py-xxl-3">
                <div class="col-lg-3 pb-2 mb-4">
                    <h2 class="text-center text-lg-start mb-lg-5"><?= print_translation('home_index_featured_posts') ?></h2>

                    <!-- External slider prev/next buttons -->
                    <div class="d-flex justify-content-center justify-content-lg-start gap-2">
                        <button type="button" id="prev" class="btn btn-lg btn-icon btn-outline-secondary rounded-circle animate-slide-start me-1" aria-label="Previous slide">
                            <i class="ci-chevron-left fs-xl animate-target"></i>
                        </button>
                        <button type="button" id="next" class="btn btn-lg btn-icon btn-outline-secondary rounded-circle animate-slide-end" aria-label="Next slide">
                            <i class="ci-chevron-right fs-xl animate-target"></i>
                        </button>
                    </div>
                </div>
                <div class="col-lg-9">

                    <!-- Slider -->
                    <div class="swiper" data-swiper="{
                  &quot;slidesPerView&quot;: 1,
                  &quot;spaceBetween&quot;: 24,
                  &quot;navigation&quot;: {
                    &quot;prevEl&quot;: &quot;#prev&quot;,
                    &quot;nextEl&quot;: &quot;#next&quot;
                  },
                  &quot;scrollbar&quot;: {
                    &quot;el&quot;: &quot;.swiper-scrollbar&quot;
                  },
                  &quot;breakpoints&quot;: {
                    &quot;500&quot;: {
                      &quot;slidesPerView&quot;: 2
                    },
                    &quot;768&quot;: {
                      &quot;slidesPerView&quot;: 3
                    }
                  }
                }">
                        <div class="swiper-wrapper pb-3 mb-2 mb-sm-3 mb-md-4">
                            <?php foreach ($featured_posts as $post): ?>
                                <!-- Article -->
                                <article class="swiper-slide">
                                    <a class="ratio d-flex hover-effect-scale rounded-4 overflow-hidden" href="<?= $postUrl($post) ?>" style="--cz-aspect-ratio: calc(260 / 306 * 100%)">
                                        <img src="<?= get_image($post['image']) ?>" class="hover-effect-target w-100 h-100 object-fit-cover" alt="<?= htmlSC($post['title']) ?>">
                                    </a>
                                    <div class="pt-4">
                                        <div class="nav pb-2 mb-1">
                                            <a class="nav-link text-body fs-xs text-uppercase p-0" href="<?= base_href('/posts') . '?category=' . rawurlencode((string)($post['category_slug'] ?? $post['category'])) ?>">
                                                <?= htmlSC($post['category_label'] ?? $post['category']) ?>
                                            </a>
                                        </div>
                                        <h3 class="h6 mb-3">
                                            <a class="hover-effect-underline" href="<?= $postUrl($post) ?>"><?= htmlSC($post['title']) ?></a>
                                        </h3>
                                        <div class="nav align-items-center gap-2 fs-xs">
                                            <span class="nav-link text-body-secondary fs-xs fw-normal p-0"><?= htmlSC($post['author_name'] ?? '') ?></span>
                                            <hr class="vr my-1 mx-1">
                                            <span class="text-body-secondary"><?= date('d.m.Y', strtotime($post['published_at'])) ?></span>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <!-- Slider scrollbar -->
                        <div class="swiper-scrollbar position-static" style="height: .125rem"></div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

</main>
