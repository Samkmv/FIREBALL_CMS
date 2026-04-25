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
        <section class="container py-5 my-2 my-md-4 my-lg-5">
            <div class="d-flex align-items-end justify-content-between flex-wrap gap-3 mb-4">
                <div>
                    <h2 class="h3 mb-1"><?= print_translation('home_index_featured_posts') ?></h2>
                </div>
                <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/posts') ?>"><?= print_translation('home_index_featured_posts_btn') ?></a>
            </div>

            <div class="row g-4">
                <?php foreach ($featured_posts as $post): ?>
                    <div class="col-6 col-xl-3">
                        <article class="card h-100 border-0 shadow-sm rounded-5 overflow-hidden">
                            <a class="d-block" href="<?= $postUrl($post) ?>">
                                <div class="ratio" style="--cz-aspect-ratio: calc(240 / 416 * 100%)">
                                    <img src="<?= get_image($post['image']) ?>" alt="<?= htmlSC($post['title']) ?>" class="w-100 h-100 object-fit-cover">
                                </div>
                            </a>
                            <div class="card-body p-4">
                                <div class="d-flex flex-wrap align-items-center gap-2 text-body-tertiary fs-sm mb-2">
                                    <span><?= htmlSC($post['category_label'] ?? $post['category']) ?></span>
                                    <span>&bull;</span>
                                    <span><?= date('d.m.Y', strtotime($post['published_at'])) ?></span>
                                </div>
                                <h3 class="h5 mb-2">
                                    <a class="text-decoration-none" href="<?= $postUrl($post) ?>"><?= htmlSC($post['title']) ?></a>
                                </h3>
                                <?php if (!empty($post['excerpt'])): ?>
                                    <p class="text-body mb-3"><?= htmlSC($post['excerpt']) ?></p>
                                <?php endif; ?>
                                <a class="btn btn-outline-secondary btn-sm rounded-pill" href="<?= $postUrl($post) ?>"><?= print_translation('home_index_featured_posts_read_more') ?></a>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
