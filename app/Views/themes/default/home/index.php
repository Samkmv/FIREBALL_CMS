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

    <section class="container py-5 mt-1 my-sm-2 my-md-3 my-lg-4 my-xl-5">
        <div class="row py-2 py-xxl-3">
            <div class="col-lg-3 pb-2 mb-4">
                <h2 class="text-center text-lg-start mb-lg-5">Записи на главной странице</h2>

                <!-- External slider prev/next buttons -->
                <div class="d-flex justify-content-center justify-content-lg-start gap-2">
                    <button type="button" id="prev" class="btn btn-lg btn-icon btn-outline-secondary rounded-circle animate-slide-start me-1 swiper-button-disabled" aria-label="Previous slide" tabindex="-1" aria-controls="swiper-wrapper-210aecc7abecb4e75" aria-disabled="true" disabled="">
                        <i class="ci-chevron-left fs-xl animate-target"></i>
                    </button>
                    <button type="button" id="next" class="btn btn-lg btn-icon btn-outline-secondary rounded-circle animate-slide-end" aria-label="Next slide" tabindex="0" aria-controls="swiper-wrapper-210aecc7abecb4e75" aria-disabled="false">
                        <i class="ci-chevron-right fs-xl animate-target"></i>
                    </button>
                </div>
            </div>
            <div class="col-lg-9">

                <!-- Slider -->
                <div class="swiper swiper-initialized swiper-horizontal swiper-backface-hidden" data-swiper="{
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
                    <div class="swiper-wrapper pb-3 mb-2 mb-sm-3 mb-md-4" id="swiper-wrapper-210aecc7abecb4e75" aria-live="polite" style="transition-duration: 0ms; transform: translate3d(0px, 0px, 0px); transition-delay: 0ms;">

                        <!-- Article -->
                        <article class="swiper-slide swiper-slide-active" style="width: 306px; margin-right: 24px;" role="group" aria-label="1 / 6">
                            <a class="ratio d-flex hover-effect-scale rounded-4 overflow-hidden" href="#!" style="--cz-aspect-ratio: calc(260 / 306 * 100%)">
                                <img src="assets/img/blog/grid/v2/09.jpg" class="hover-effect-target" alt="Image">
                            </a>
                            <div class="pt-4">
                                <div class="nav pb-2 mb-1">
                                    <a class="nav-link text-body fs-xs text-uppercase p-0" href="#!">Home decoration</a>
                                </div>
                                <h3 class="h6 mb-3">
                                    <a class="hover-effect-underline" href="#!">Decorate your home for the festive season in 3 easy steps</a>
                                </h3>
                                <div class="nav align-items-center gap-2 fs-xs">
                                    <a class="nav-link text-body-secondary fs-xs fw-normal p-0" href="#!">Ava Johnson</a>
                                    <hr class="vr my-1 mx-1">
                                    <span class="text-body-secondary">September 11, 2024</span>
                                </div>
                            </div>
                        </article>

                        <!-- Article -->
                        <article class="swiper-slide swiper-slide-next" style="width: 306px; margin-right: 24px;" role="group" aria-label="2 / 6">
                            <a class="ratio d-flex hover-effect-scale rounded-4 overflow-hidden" href="#!" style="--cz-aspect-ratio: calc(260 / 306 * 100%)">
                                <img src="assets/img/blog/grid/v2/10.jpg" class="hover-effect-target" alt="Image">
                            </a>
                            <div class="pt-4">
                                <div class="nav pb-2 mb-1">
                                    <a class="nav-link text-body fs-xs text-uppercase p-0" href="#!">Furniture</a>
                                </div>
                                <h3 class="h6 mb-3">
                                    <a class="hover-effect-underline" href="#!">Furnishing your space: a guide to choosing the perfect furniture pieces</a>
                                </h3>
                                <div class="nav align-items-center gap-2 fs-xs">
                                    <a class="nav-link text-body-secondary fs-xs fw-normal p-0" href="#!">Oliver Harris</a>
                                    <hr class="vr my-1 mx-1">
                                    <span class="text-body-secondary">September 5, 2024</span>
                                </div>
                            </div>
                        </article>

                        <!-- Article -->
                        <article class="swiper-slide" style="width: 306px; margin-right: 24px;" role="group" aria-label="3 / 6">
                            <a class="ratio d-flex hover-effect-scale rounded-4 overflow-hidden" href="#!" style="--cz-aspect-ratio: calc(260 / 306 * 100%)">
                                <img src="assets/img/blog/grid/v2/11.jpg" class="hover-effect-target" alt="Image">
                            </a>
                            <div class="pt-4">
                                <div class="nav pb-2 mb-1">
                                    <a class="nav-link text-body fs-xs text-uppercase p-0" href="#!">Interior design</a>
                                </div>
                                <h3 class="h6 mb-3">
                                    <a class="hover-effect-underline" href="#!">Transform your living space with these chic interior design tips</a>
                                </h3>
                                <div class="nav align-items-center gap-2 fs-xs">
                                    <a class="nav-link text-body-secondary fs-xs fw-normal p-0" href="#!">Ethan Miller</a>
                                    <hr class="vr my-1 mx-1">
                                    <span class="text-body-secondary">August 23, 2024</span>
                                </div>
                            </div>
                        </article>

                        <!-- Article -->
                        <article class="swiper-slide" style="width: 306px; margin-right: 24px;" role="group" aria-label="4 / 6">
                            <a class="ratio d-flex hover-effect-scale rounded-4 overflow-hidden" href="#!" style="--cz-aspect-ratio: calc(260 / 306 * 100%)">
                                <img src="assets/img/blog/grid/v2/12.jpg" class="hover-effect-target" alt="Image">
                            </a>
                            <div class="pt-4">
                                <div class="nav pb-2 mb-1">
                                    <a class="nav-link text-body fs-xs text-uppercase p-0" href="#!">Lighting</a>
                                </div>
                                <h3 class="h6 mb-3">
                                    <a class="hover-effect-underline" href="#!">Brighten up your home with these stunning lighting hacks</a>
                                </h3>
                                <div class="nav align-items-center gap-2 fs-xs">
                                    <a class="nav-link text-body-secondary fs-xs fw-normal p-0" href="#!">Emily Davies</a>
                                    <hr class="vr my-1 mx-1">
                                    <span class="text-body-secondary">August 18, 2024</span>
                                </div>
                            </div>
                        </article>

                        <!-- Article -->
                        <article class="swiper-slide" style="width: 306px; margin-right: 24px;" role="group" aria-label="5 / 6">
                            <a class="ratio d-flex hover-effect-scale rounded-4 overflow-hidden" href="#!" style="--cz-aspect-ratio: calc(260 / 306 * 100%)">
                                <img src="assets/img/blog/grid/v2/13.jpg" class="hover-effect-target" alt="Image">
                            </a>
                            <div class="pt-4">
                                <div class="nav pb-2 mb-1">
                                    <a class="nav-link text-body fs-xs text-uppercase p-0" href="#!">Home decoration</a>
                                </div>
                                <h3 class="h6 mb-3">
                                    <a class="hover-effect-underline" href="#!">Elevate your space with trendy home decoration ideas</a>
                                </h3>
                                <div class="nav align-items-center gap-2 fs-xs">
                                    <a class="nav-link text-body-secondary fs-xs fw-normal p-0" href="#!">Olivia Anderson</a>
                                    <hr class="vr my-1 mx-1">
                                    <span class="text-body-secondary">August 9, 2024</span>
                                </div>
                            </div>
                        </article>

                        <!-- Article -->
                        <article class="swiper-slide" style="width: 306px; margin-right: 24px;" role="group" aria-label="6 / 6">
                            <a class="ratio d-flex hover-effect-scale rounded-4 overflow-hidden" href="#!" style="--cz-aspect-ratio: calc(260 / 306 * 100%)">
                                <img src="assets/img/blog/grid/v2/14.jpg" class="hover-effect-target" alt="Image">
                            </a>
                            <div class="pt-4">
                                <div class="nav pb-2 mb-1">
                                    <a class="nav-link text-body fs-xs text-uppercase p-0" href="#!">Design trends</a>
                                </div>
                                <h3 class="h6 mb-3">
                                    <a class="hover-effect-underline" href="#!">Discover the latest captivating home design trends shaping spaces</a>
                                </h3>
                                <div class="nav align-items-center gap-2 fs-xs">
                                    <a class="nav-link text-body-secondary fs-xs fw-normal p-0" href="#!">Harry Mitchell</a>
                                    <hr class="vr my-1 mx-1">
                                    <span class="text-body-secondary">July 27, 2024</span>
                                </div>
                            </div>
                        </article>
                    </div>

                    <!-- Slider scrollbar -->
                    <div class="swiper-scrollbar position-static swiper-scrollbar-horizontal" style="height: .125rem"><div class="swiper-scrollbar-drag" style="transform: translate3d(0px, 0px, 0px); width: 467.690184px; transition-duration: 0ms;"></div></div>
                    <span class="swiper-notification" aria-live="assertive" aria-atomic="true"></span></div>
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
