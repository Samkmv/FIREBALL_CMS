<!-- Page content -->
<main class="content-wrapper">
    <?php $postUrl = static fn(array $post): string => base_href('/posts/' . $post['slug']); ?>


    <section class="container pt-5">
        <div class="row pt-2 pt-sm-3 pt-md-4 pt-lg-5">
            <div class="col-md-5 col-lg-6 pb-1 pb-sm-2 pb-md-0 mb-4 mb-md-0">
                <div class="ratio ratio-1x1">
                    <img src="https://i.pinimg.com/originals/61/e9/75/61e9750492779f437d343b81f50a5692.jpg" style="object-fit: cover;" class="rounded-5" alt="Image">
                </div>
            </div>
            <div class="col-md-7 col-lg-6 pt-md-3 pt-xl-4 pt-xxl-5">
                <div class="ps-md-3 ps-lg-4 ps-xl-5 ms-xxl-4">
                    <h3 class="h1 pb-1 pb-sm-2 pb-lg-3">ВИДЕОТРАНСЛЯЦИЯ ВАШЕГО ДВОРА ДЛЯ ВСЕХ ЖИТЕЛЕЙ МКД</h3>
                    <ul>
                        <li>Удобное использование без скачивания приложений и паролей</li>
                        <li>Наблюдение в реальном времени с любого устройства</li>
                        <li>Легко делиться трансляцией с камер с близкими людьми</li>
                        <li>Постоянный контроль сохранности вашего имущества</li>
                    </ul>

                    <!-- Accordion -->
                    <div class="accordion accordion-alt-icon" id="principles">

                        <!-- Item (expanded) -->
                        <div class="accordion-item">
                            <h3 class="accordion-header" id="headingFocus">
                                <button type="button" class="accordion-button animate-underline collapsed" data-bs-toggle="collapse" data-bs-target="#focus" aria-expanded="false" aria-controls="focus">
                                    <span class="animate-target me-2">Управляющие компании и ТСЖ</span>
                                </button>
                            </h3>
                            <div class="accordion-collapse collapse" id="focus" aria-labelledby="headingFocus" data-bs-parent="#principles" style="">
                                <div class="accordion-body">
                                    <section class="video-surveillance">
                                        <div class="container">
                                            <section class="video-surveillance">
                                                <div class="container">

                                                    <h5 class="subtitle">
                                                        Наша цель — сделать видеонаблюдение доступным каждому.
                                                    </h5>

                                                    <h6>
                                                        Видеонаблюдение для многоквартирных домов
                                                    </h6>

                                                    <p>
                                                        Онлайн-сервис «MAXIPAPA» помогает управляющим компаниям
                                                        контролировать дома, подъезды, дворы и парковки
                                                        из одной системы — в любое время и с любого устройства.
                                                    </p>

                                                    <ul>
                                                        <li>Централизованный контроль всех камер</li>
                                                        <li>Повышение безопасности жителей</li>
                                                        <li>Видеоархив и быстрый поиск записей</li>
                                                        <li>Удобный доступ с компьютера и телефона</li>
                                                        <li>Круглосуточная техническая поддержка</li>
                                                    </ul>

                                                    <p>
                                                        Меньше жалоб, больше контроля и спокойствия для жителей.
                                                    </p>

                                                </div>
                                            </section>
                                        </div>
                                    </section>
                                </div>
                            </div>
                        </div>

                        <!-- Item -->
<!--                        <div class="accordion-item">-->
<!--                            <h3 class="accordion-header" id="headingReputation">-->
<!--                                <button type="button" class="accordion-button animate-underline collapsed" data-bs-toggle="collapse" data-bs-target="#reputation" aria-expanded="false" aria-controls="reputation">-->
<!--                                    <span class="animate-target me-2">Betting on reputation</span>-->
<!--                                </button>-->
<!--                            </h3>-->
<!--                            <div class="accordion-collapse collapse" id="reputation" aria-labelledby="headingReputation" data-bs-parent="#principles" style="">-->
<!--                                <div class="accordion-body">We value a solid reputation built on integrity, transparency, and quality - ensuring our customers trust and rely on our brand.</div>-->
<!--                            </div>-->
<!--                        </div>-->

                        <!-- Item -->
<!--                        <div class="accordion-item">-->
<!--                            <h3 class="accordion-header" id="headingFast">-->
<!--                                <button type="button" class="accordion-button animate-underline" data-bs-toggle="collapse" data-bs-target="#fast" aria-expanded="true" aria-controls="fast">-->
<!--                                    <span class="animate-target me-2">Fast, convenient and enjoyable</span>-->
<!--                                </button>-->
<!--                            </h3>-->
<!--                            <div class="accordion-collapse collapse show" id="fast" aria-labelledby="headingFast" data-bs-parent="#principles" style="">-->
<!--                                <div class="accordion-body">We've streamlined our process for speed, convenience, and an enjoyable shopping experience, redefining online standards for our delighted customers.</div>-->
<!--                            </div>-->
<!--                        </div>-->

                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="container-start pt-5">
        <div class="row align-items-center g-0 pt-2 pt-sm-3 pt-md-4 pt-lg-5">
            <div class="col-md-4 col-lg-3 pb-1 pb-md-0 pe-3 ps-md-0 mb-4 mb-md-0">
                <div class="d-flex flex-md-column align-items-end align-items-md-start">
                    <div class="mb-md-5 me-3 me-md-0">
                        <h3 class="h1 mb-0">ПОДКЛЮЧАЙТЕ НОВЫЕ ИНТЕРЕСНЫЕ ОБЪЕКТЫ ГОРОДА</h3>
                    </div>

                    <!-- External slider prev/next buttons -->
                    <div class="d-flex gap-2">
                        <button type="button" id="prev-values" class="btn btn-icon btn-outline-secondary rounded-circle animate-slide-start me-1" aria-label="Previous slide" tabindex="0" aria-controls="swiper-wrapper-26c106b45e289b75b">
                            <i class="ci-chevron-left fs-xl animate-target"></i>
                        </button>
                        <button type="button" id="next-values" class="btn btn-icon btn-outline-secondary rounded-circle animate-slide-end" aria-label="Next slide" tabindex="0" aria-controls="swiper-wrapper-26c106b45e289b75b">
                            <i class="ci-chevron-right fs-xl animate-target"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-md-8 col-lg-9">
                <div class="ps-md-4 ps-lg-5">
                    <div class="swiper swiper-initialized swiper-horizontal swiper-backface-hidden" data-swiper="{
                &quot;slidesPerView&quot;: &quot;auto&quot;,
                &quot;spaceBetween&quot;: 24,
                &quot;loop&quot;: false,
                &quot;rewind&quot;: false,
                &quot;watchOverflow&quot;: true,
                &quot;navigation&quot;: {
                  &quot;prevEl&quot;: &quot;#prev-values&quot;,
                  &quot;nextEl&quot;: &quot;#next-values&quot;
                }
              }">
                        <div class="swiper-wrapper" id="swiper-wrapper-26c106b45e289b75b" aria-live="polite" style="transition-duration: 0ms; transform: translate3d(-660px, 0px, 0px); transition-delay: 0ms;">

                            <!-- Item -->

                            <div class="swiper-slide w-auto h-auto" style="margin-right: 24px;" role="group" aria-label="1 / 6" data-swiper-slide-index="0">
                                <div class="card h-100 rounded-4 px-3" style="max-width: 306px">
                                    <div class="card-body py-5 px-3">
                                        <div class="h4 h5 d-flex align-items-center">
                                            <i class="ci-user-plus fs-4 me-3"></i>
                                            People
                                        </div>
                                        <p class="mb-0">The most important value of the Company is people (employees, partners, clients). Behind any success there is, first and foremost, a specific person. It is he who creates the product, technology, and innovation.</p>
                                    </div>
                                </div>
                            </div><div class="swiper-slide w-auto h-auto swiper-slide-prev" style="margin-right: 24px;" role="group" aria-label="2 / 6" data-swiper-slide-index="1">
                                <div class="card h-100 rounded-4 px-3" style="max-width: 306px">
                                    <div class="card-body py-5 px-3">
                                        <div class="h4 h5 d-flex align-items-center">
                                            <i class="ci-shopping-bag fs-4 me-3"></i>
                                            Service
                                        </div>
                                        <p class="mb-0">Care, attention, desire and ability to be helpful (to a colleague in his department, other departments, clients, customers and all other people who surround us).</p>
                                    </div>
                                </div>
                            </div><div class="swiper-slide w-auto h-auto swiper-slide-active" style="margin-right: 24px;" role="group" aria-label="3 / 6" data-swiper-slide-index="2">
                                <div class="card h-100 rounded-4 px-3" style="max-width: 306px">
                                    <div class="card-body py-5 px-3">
                                        <div class="h4 h5 d-flex align-items-center">
                                            <i class="ci-trending-up fs-4 me-3"></i>
                                            Responsibility
                                        </div>
                                        <p class="mb-0">Responsibility is our key quality. We don't shift it to external circumstances or other people. If we see something that could be improved, we don't just criticize, but offer our own options.</p>
                                    </div>
                                </div>
                            </div><div class="swiper-slide w-auto h-auto swiper-slide-next" style="margin-right: 24px;" role="group" aria-label="4 / 6" data-swiper-slide-index="3">
                                <div class="card h-100 rounded-4 px-3" style="max-width: 306px">
                                    <div class="card-body py-5 px-3">
                                        <div class="h4 h5 d-flex align-items-center">
                                            <i class="ci-rocket fs-4 me-3"></i>
                                            Innovation
                                        </div>
                                        <p class="mb-0">We foster a culture of continuous improvement and innovation. Embracing change and staying ahead of the curve are essential for our success. We encourage creative thinking, experimentation, and the pursuit of new ideas.</p>
                                    </div>
                                </div>
                            </div><div class="swiper-slide w-auto h-auto" style="margin-right: 24px;" role="group" aria-label="5 / 6" data-swiper-slide-index="4">
                                <div class="card h-100 rounded-4 px-3" style="max-width: 306px">
                                    <div class="card-body py-5 px-3">
                                        <div class="h4 h5 d-flex align-items-center">
                                            <i class="ci-star fs-4 me-3"></i>
                                            Leadership
                                        </div>
                                        <p class="mb-0">Cartzilla people are young, ambitious and energetic individuals. With identified leadership qualities, with a desire to be the best at what they do.</p>
                                    </div>
                                </div>
                            </div><div class="swiper-slide w-auto h-auto" style="margin-right: 24px;" role="group" aria-label="6 / 6" data-swiper-slide-index="5">
                                <div class="card h-100 rounded-4 px-3" style="max-width: 306px">
                                    <div class="card-body py-5 px-3">
                                        <div class="h4 h5 d-flex align-items-center">
                                            <i class="ci-leaf fs-4 me-3"></i>
                                            Sustainability
                                        </div>
                                        <p class="mb-0">We are committed to minimizing our environmental impact and promoting sustainable practices. From responsible sourcing to eco-friendly packaging, we aim to make a positive contribution to the well-being of our planet.</p>
                                    </div>
                                </div>
                            </div></div>
                        <span class="swiper-notification" aria-live="assertive" aria-atomic="true"></span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="container py-5 mt-md-2 mt-lg-4">
        <div class="row row-cols-3 row-cols-md-3 g-4">
            <div class="col text-center">
                <div class="display-4 text-dark-emphasis mb-2">365</div>
                <p class="fs-sm mb-0">Наних камер</p>
            </div>
            <div class="col text-center">
                <div class="display-4 text-dark-emphasis mb-2">24/7</div>
                <p class="fs-sm mb-0">Онлайн</p>
            </div>
            <div class="col text-center">
                <div class="display-4 text-dark-emphasis mb-2">7</div>
                <p class="fs-sm mb-0">Дней архива </p>
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
                                        <img src="<?= htmlSC(get_image($post['image'])) ?>" data-image-fallback="<?= htmlSC(base_url('/assets/img/no-image.png')) ?>" onerror="this.onerror=null;this.removeAttribute('srcset');this.src=this.dataset.imageFallback;" class="hover-effect-target w-100 h-100 object-fit-cover" alt="<?= htmlSC($post['title']) ?>">
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
