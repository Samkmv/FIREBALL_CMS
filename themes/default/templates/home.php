<?php

/**
 * Home Template
 *
 * Available variables:
 *
 * $page
 * $posts
 * $settings
 * $user
 * $locale
 */

$postUrl = static fn(array $post): string => base_href('/posts/' . $post['slug']);
$heroStream = 'https://cdn.livespotting.com/vpu/ehlpzb4g/nkw9elfh_hub.m3u8';
$heroHlsScript = theme_asset('vendor/hls.js/hls.min.js') . '?v=' . filemtime(theme()->assetPath('vendor/hls.js/hls.min.js'));
$homeCityCategories = array_values(array_filter(
    (new \App\Models\Post())->getNavigationCategories(),
    static fn(array $category): bool => (int)($category['total'] ?? 0) > 0
));

$popularCameras = [];
foreach (array_slice($featured_posts ?? [], 0, 10) as $post) {
    $cameraTitle = trim((string)($post['title'] ?? ''));
    if ($cameraTitle === '') {
        continue;
    }

    $popularCameras[] = [
        'title' => $cameraTitle,
        'city' => trim((string)($post['category_label'] ?? $post['category'] ?? 'MAXIPAPA')),
        'category_url' => base_href('/posts') . (!empty($post['category_slug']) ? '?category=' . rawurlencode((string)$post['category_slug']) : ''),
        'date' => date('d.m.Y', strtotime((string)($post['published_at'] ?? 'now'))),
        'image' => get_image($post['image'] ?? ''),
        'url' => $postUrl($post),
    ];
}

$objectCards = [
    [
        'title' => return_translation('home_index_object_apartments_title'),
        'text' => return_translation('home_index_object_apartments_text'),
        'image' => theme_asset('images/about/v2/hero.jpg'),
    ],
    [
        'title' => return_translation('home_index_object_business_title'),
        'text' => return_translation('home_index_object_business_text'),
        'image' => theme_asset('images/about/v2/feature02.jpg'),
    ],
    [
        'title' => return_translation('home_index_object_public_title'),
        'text' => return_translation('home_index_object_public_text'),
        'image' => theme_asset('images/about/v2/feature03.jpg'),
    ],
];
$benefits = [
    ['icon' => 'ci-monitor', 'title' => return_translation('home_index_benefit_simple_title'), 'text' => return_translation('home_index_benefit_simple_text')],
    ['icon' => 'ci-check-shield', 'title' => return_translation('home_index_benefit_reliable_title'), 'text' => return_translation('home_index_benefit_reliable_text')],
    ['icon' => 'ci-lock', 'title' => return_translation('home_index_benefit_secure_title'), 'text' => return_translation('home_index_benefit_secure_text')],
    ['icon' => 'ci-eye', 'title' => return_translation('home_index_benefit_transparent_title'), 'text' => return_translation('home_index_benefit_transparent_text')],
];
$featuredCount = count($popularCameras);

?>
<main class="home-page content-wrapper">
    <section class="home-hero">
        <div class="home-hero__media" aria-hidden="true">
            <video
                class="home-hero__video"
                data-home-hero-video
                data-home-hero-src="<?= htmlSC($heroStream) ?>"
                muted
                autoplay
                playsinline
                preload="none"
            ></video>
        </div>
        <div class="home-hero__overlay" aria-hidden="true"></div>
        <div class="container home-hero__inner">
            <div class="home-hero__content home-reveal">
                <span class="home-eyebrow"><span class="home-live-dot"></span> MAXIPAPA live platform</span>
                <h1 class="home-hero__title"><?= print_translation('home_index_hero_title') ?></h1>
                <p class="home-hero__lead"><?= print_translation('home_index_hero_lead') ?></p>
                <p class="home-hero__text"><?= print_translation('home_index_hero_text') ?></p>
                <div class="home-hero__actions">
                    <a class="btn btn-light rounded-pill px-4 py-3 fw-semibold" href="<?= !empty($popularCameras) ? '#home-popular-cameras' : base_href('/posts') ?>"><?= print_translation('home_index_hero_watch_cameras') ?></a>
                    <a class="btn btn-outline-secondary rounded-pill px-4 py-3 fw-semibold" href="<?= base_href('/contacts') ?>"><?= print_translation('home_index_connect_object') ?></a>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section home-stats" aria-label="<?= htmlSC(return_translation('home_index_stats_aria')) ?>">
        <div class="container">
            <div class="home-stats__grid home-reveal" data-home-stats>
                <div class="home-stat">
                    <strong><span data-home-counter="365">365</span>+</strong>
                    <span><?= print_translation('home_index_stats_cameras') ?></span>
                </div>
                <div class="home-stat">
                    <strong>24/7</strong>
                    <span><?= print_translation('home_index_stats_access') ?></span>
                </div>
                <div class="home-stat">
                    <strong><span data-home-counter="7">7</span></strong>
                    <span><?= print_translation('home_index_stats_archive_days') ?></span>
                </div>
            </div>
        </div>
    </section>

    <?php if (!empty($popularCameras)): ?>
        <section class="container-start pt-5" id="home-popular-cameras">
            <div class="row align-items-center g-0 pt-2 pt-sm-3 pt-md-4 pt-lg-5">
                <div class="col-md-4 col-lg-3 pb-1 pb-md-0 pe-3 ps-md-0 mb-4 mb-md-0">
                    <div class="d-flex flex-md-column align-items-end align-items-md-start home-featured-toolbar home-reveal">
                        <div class="home-featured-head mb-md-5 me-3 me-md-0">
                            <span class="home-section-kicker">Featured on homepage</span>
                            <h2><?= print_translation('home_index_featured_posts') ?></h2>
                            <p><?= print_translation('home_index_featured_posts_subtitle') ?></p>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" id="prev-home-featured" class="btn btn-icon btn-outline-secondary rounded-circle animate-slide-start me-1 home-featured-nav-btn" aria-label="<?= htmlSC(return_translation('home_index_slider_prev')) ?>">
                                <i class="ci-chevron-left fs-xl animate-target"></i>
                            </button>
                            <button type="button" id="next-home-featured" class="btn btn-icon btn-outline-secondary rounded-circle animate-slide-end home-featured-nav-btn" aria-label="<?= htmlSC(return_translation('home_index_slider_next')) ?>">
                                <i class="ci-chevron-right fs-xl animate-target"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-md-8 col-lg-9">
                    <div class="ps-md-4 ps-lg-5 home-reveal">
                        <div class="swiper" data-swiper="{
                            &quot;slidesPerView&quot;: &quot;auto&quot;,
                            &quot;spaceBetween&quot;: 24,
                            &quot;slidesOffsetAfter&quot;: 16,
                            &quot;loop&quot;: false,
                            &quot;rewind&quot;: false,
                            &quot;watchOverflow&quot;: true,
                            &quot;breakpoints&quot;: {
                                &quot;768&quot;: {
                                    &quot;slidesOffsetAfter&quot;: 24
                                },
                                &quot;992&quot;: {
                                    &quot;slidesOffsetAfter&quot;: 48
                                }
                            },
                            &quot;navigation&quot;: {
                                &quot;prevEl&quot;: &quot;#prev-home-featured&quot;,
                                &quot;nextEl&quot;: &quot;#next-home-featured&quot;
                            }<?= $featuredCount > 1 ? ',' : '' ?>
                            <?php if ($featuredCount > 1): ?>
                            &quot;pagination&quot;: {
                                &quot;el&quot;: &quot;#home-featured-progress&quot;,
                                &quot;type&quot;: &quot;progressbar&quot;
                            }
                            <?php endif; ?>
                        }">
                            <div class="swiper-wrapper">
                                <?php foreach ($popularCameras as $camera): ?>
                                    <div class="swiper-slide w-auto h-auto">
                                        <article class="col" style="width: 306px; max-width: 72vw;">
                                            <a class="ratio d-flex hover-effect-scale rounded overflow-hidden" href="<?= htmlSC($camera['url']) ?>" style="--cz-aspect-ratio: calc(305 / 416 * 100%)">
                                                <img src="<?= htmlSC($camera['image']) ?>" class="hover-effect-target w-100 h-100 object-fit-cover" alt="<?= htmlSC($camera['title']) ?>" loading="lazy">
                                                <span class="home-online-badge">
                                                    <span class="home-online-dot" aria-hidden="true"></span>
                                                    <?= print_translation('home_index_camera_online') ?>
                                                </span>
                                            </a>
                                            <div class="pt-4">
                                                <div class="nav align-items-center gap-2 pb-2 mt-n1 mb-1">
                                                    <a class="nav-link text-body fs-xs text-uppercase p-0" href="<?= htmlSC($camera['category_url']) ?>">
                                                        <?= htmlSC($camera['city']) ?>
                                                    </a>
                                                    <hr class="vr my-1 mx-1">
                                                    <span class="text-body-tertiary fs-xs"><?= htmlSC($camera['date']) ?></span>
                                                </div>
                                                <h3 class="h5 mb-0">
                                                    <a class="hover-effect-underline" href="<?= htmlSC($camera['url']) ?>"><?= htmlSC($camera['title']) ?></a>
                                                </h3>
                                                <a class="btn btn-outline-secondary rounded-pill btn-sm mt-3" href="<?= htmlSC($camera['url']) ?>">
                                                    <?= print_translation('home_index_featured_posts_watch') ?>
                                                </a>
                                            </div>
                                        </article>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php if ($featuredCount > 1): ?>
                            <div id="home-featured-progress" class="swiper-pagination home-featured-progress" aria-label="<?= htmlSC(return_translation('home_index_slider_progress')) ?>"></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="home-section home-objects">
        <div class="container">
            <div class="home-section-head home-reveal">
                <div>
                    <span class="home-section-kicker">Use cases</span>
                    <h2><?= print_translation('home_index_use_cases_title') ?></h2>
                    <p><?= print_translation('home_index_use_cases_subtitle') ?></p>
                </div>
            </div>

            <div class="home-object-grid">
                <?php foreach ($objectCards as $index => $card): ?>
                    <article class="home-object-card home-reveal <?= $index === 0 ? 'home-object-card--large' : '' ?>">
                        <img src="<?= htmlSC($card['image']) ?>" alt="<?= htmlSC($card['title']) ?>" loading="lazy">
                        <div class="home-object-card__content">
                            <span>0<?= $index + 1 ?></span>
                            <h3><?= htmlSC($card['title']) ?></h3>
                            <p><?= htmlSC($card['text']) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="home-section home-benefits">
        <div class="container">
            <div class="home-section-head home-reveal">
                <div>
                    <span class="home-section-kicker">Why MAXIPAPA</span>
                    <h2><?= print_translation('home_index_benefits_title') ?></h2>
                </div>
            </div>
            <div class="home-benefit-grid">
                <?php foreach ($benefits as $benefit): ?>
                    <article class="home-benefit-card home-reveal">
                        <div class="home-benefit-card__icon"><i class="<?= htmlSC($benefit['icon']) ?>"></i></div>
                        <h3><?= htmlSC($benefit['title']) ?></h3>
                        <p><?= htmlSC($benefit['text']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="home-section home-geo">
        <div class="container">
            <div class="home-geo-card home-reveal">
                <div class="home-geo-card__content">
                    <span class="home-section-kicker">Coverage</span>
                    <h2><?= print_translation('home_index_cities_title') ?></h2>
                    <p><?= print_translation('home_index_cities_subtitle') ?></p>
                    <div class="home-city-list" aria-label="<?= htmlSC(return_translation('home_index_cities_title')) ?>">
                        <?php if (!empty($homeCityCategories)): ?>
                            <?php foreach ($homeCityCategories as $city): ?>
                                <a class="btn btn-outline-secondary rounded-pill home-city-link" href="<?= base_href('/posts') . '?category=' . rawurlencode((string)$city['slug']) ?>">
                                    <span><?= htmlSC((string)($city['label'] ?? $city['name'] ?? $city['slug'])) ?></span>
                                    <small><?= (int)($city['total'] ?? 0) ?></small>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <a class="btn btn-dark rounded-pill home-city-link home-city-link--all" href="<?= base_href('/posts') ?>">
                            <span><?= print_translation('home_index_cities_all') ?></span>
                            <i class="ci-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section home-cta-wrap">
        <div class="container">
            <div class="home-cta home-reveal">
                <div class="home-cta__glow home-cta__glow--one" aria-hidden="true"></div>
                <div class="home-cta__glow home-cta__glow--two" aria-hidden="true"></div>
                <span class="home-section-kicker">Start live</span>
                <h2><?= print_translation('home_index_cta_title') ?></h2>
                <p><?= print_translation('home_index_cta_text') ?></p>
                <a class="btn btn-light rounded-pill px-4 py-3 fw-semibold" href="<?= base_href('/contacts') ?>"><?= print_translation('home_index_connect_object') ?></a>
            </div>
        </div>
    </section>
</main>

<script>
(function () {
    const root = document.querySelector('.home-page');
    if (!root) {
        return;
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const hero = root.querySelector('.home-hero');
    const heroVideo = root.querySelector('[data-home-hero-video]');
    const heroStream = heroVideo ? (heroVideo.dataset.homeHeroSrc || '') : '';
    const heroHlsScript = <?= json_encode($heroHlsScript, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    let heroHlsPromise = null;
    const loadHeroHls = () => new Promise((resolve, reject) => {
        if (typeof window.Hls === 'function') {
            resolve(window.Hls);
            return;
        }
        if (heroHlsPromise) {
            return heroHlsPromise.then(resolve, reject);
        }

        heroHlsPromise = new Promise((loadResolve, loadReject) => {
            const existing = document.querySelector('script[data-home-hero-hls]');
            if (existing) {
                existing.addEventListener('load', () => typeof window.Hls === 'function'
                    ? loadResolve(window.Hls)
                    : loadReject(new Error('HLS is unavailable')), { once: true });
                existing.addEventListener('error', loadReject, { once: true });
                return;
            }

            const script = document.createElement('script');
            script.src = heroHlsScript;
            script.async = true;
            script.dataset.homeHeroHls = 'true';
            script.onload = () => typeof window.Hls === 'function'
                ? loadResolve(window.Hls)
                : loadReject(new Error('HLS is unavailable'));
            script.onerror = loadReject;
            document.head.appendChild(script);
        });

        heroHlsPromise.then(resolve, reject);
    });
    let heroHls = null;
    let heroPlayPromise = null;
    let heroPlaybackStarted = false;
    let heroInitialized = false;
    let heroPlayAttempts = 0;
    let heroGestureFallbackBound = false;
    const maxHeroPlayAttempts = 2;
    const markHeroVideoReady = () => {
        if (!hero || heroPlaybackStarted) {
            return;
        }
        heroPlaybackStarted = true;
        hero.classList.add('home-hero--video-ready');
        hero.classList.remove('home-hero--video-paused');
    };
    const revealHeroVideo = () => {
        if (typeof heroVideo.requestVideoFrameCallback === 'function') {
            heroVideo.requestVideoFrameCallback(markHeroVideoReady);
            return;
        }
        window.requestAnimationFrame(markHeroVideoReady);
    };
    const bindHeroGestureFallback = () => {
        if (heroGestureFallbackBound) {
            return;
        }
        heroGestureFallbackBound = true;
        const retryOnGesture = () => {
            document.removeEventListener('pointerdown', retryOnGesture);
            document.removeEventListener('keydown', retryOnGesture);
            heroGestureFallbackBound = false;
            playHeroVideo();
        };
        document.addEventListener('pointerdown', retryOnGesture, { passive: true });
        document.addEventListener('keydown', retryOnGesture);
    };
    const playHeroVideo = () => {
        if (!heroVideo || heroPlayPromise || (!heroVideo.paused && !heroVideo.ended)) {
            return;
        }
        if (!heroPlaybackStarted && heroPlayAttempts >= maxHeroPlayAttempts) {
            return;
        }
        if (!heroPlaybackStarted) {
            heroPlayAttempts += 1;
        }
        heroVideo.muted = true;
        heroVideo.defaultMuted = true;
        heroVideo.autoplay = true;
        heroVideo.setAttribute('muted', '');
        heroVideo.setAttribute('playsinline', '');
        heroVideo.setAttribute('webkit-playsinline', '');

        try {
            heroPlayPromise = heroVideo.play();
        } catch (error) {
            heroPlayPromise = null;
            if (hero) {
                hero.classList.add('home-hero--video-paused');
            }
            bindHeroGestureFallback();
            return;
        }

        if (heroPlayPromise && typeof heroPlayPromise.then === 'function') {
            heroPlayPromise
                .catch(() => {
                    if (hero) {
                        hero.classList.add('home-hero--video-paused');
                    }
                    bindHeroGestureFallback();
                })
                .finally(() => {
                    heroPlayPromise = null;
                });
        } else {
            heroPlayPromise = null;
        }
    };
    const initializeHeroVideo = () => {
        if (!heroVideo || !heroStream || heroInitialized || reduceMotion) {
            return;
        }
        heroInitialized = true;
        heroVideo.addEventListener('playing', revealHeroVideo, { once: true });

        if (heroVideo.canPlayType('application/vnd.apple.mpegurl') || heroVideo.canPlayType('application/x-mpegURL')) {
            heroVideo.src = heroStream;
            playHeroVideo();
            return;
        }

        loadHeroHls()
            .then((Hls) => {
                if (!Hls.isSupported()) {
                    return;
                }
                let recoveries = 0;
                heroHls = new Hls({
                    lowLatencyMode: false,
                    liveSyncDurationCount: 3,
                    liveMaxLatencyDurationCount: 8,
                    maxLiveSyncPlaybackRate: 1,
                    backBufferLength: 30,
                });
                heroHls.on(Hls.Events.MEDIA_ATTACHED, () => {
                    heroHls.loadSource(heroStream);
                });
                heroHls.on(Hls.Events.MANIFEST_PARSED, playHeroVideo);
                heroHls.on(Hls.Events.ERROR, (event, data) => {
                    if (!data || !data.fatal || recoveries >= 1) {
                        return;
                    }
                    recoveries += 1;
                    if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                        heroHls.startLoad();
                    } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                        heroHls.recoverMediaError();
                    } else {
                        heroHls.destroy();
                        heroHls = null;
                    }
                });
                heroHls.attachMedia(heroVideo);
            })
            .catch(() => {});
    };
    initializeHeroVideo();
    if (heroVideo && !reduceMotion) {
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && heroVideo.paused) {
                playHeroVideo();
            }
        });
        window.addEventListener('pageshow', (event) => {
            if (event.persisted && heroVideo.paused) {
                if (heroHls) {
                    heroHls.startLoad();
                }
                playHeroVideo();
            }
        });
        window.addEventListener('pagehide', () => {
            if (heroHls) {
                heroHls.stopLoad();
            }
        }, { once: true });
    }

    const revealItems = root.querySelectorAll('.home-reveal');
    if (reduceMotion || !('IntersectionObserver' in window)) {
        revealItems.forEach((item) => item.classList.add('home-reveal--visible'));
    } else {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('home-reveal--visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });
        revealItems.forEach((item) => revealObserver.observe(item));
    }

    const counters = root.querySelectorAll('[data-home-counter]');
    const runCounters = () => {
        counters.forEach((counter) => {
            const target = parseInt(counter.getAttribute('data-home-counter') || '0', 10);
            if (!target || counter.dataset.homeCounterDone === '1') {
                return;
            }
            counter.dataset.homeCounterDone = '1';
            if (reduceMotion) {
                counter.textContent = String(target);
                return;
            }
            const duration = 1100;
            const start = performance.now();
            const tick = (time) => {
                const progress = Math.min((time - start) / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                counter.textContent = String(Math.round(target * eased));
                if (progress < 1) {
                    requestAnimationFrame(tick);
                }
            };
            requestAnimationFrame(tick);
        });
    };

    const stats = root.querySelector('[data-home-stats]');
    if (stats && 'IntersectionObserver' in window) {
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    runCounters();
                    statsObserver.disconnect();
                }
            });
        }, { threshold: 0.35 });
        statsObserver.observe(stats);
    } else {
        runCounters();
    }

    const slider = root.querySelector('[data-home-slider]');
    if (!slider) {
        return;
    }

    const scrollSlider = (direction) => {
        const card = slider.querySelector('.home-camera-card');
        const distance = card ? card.getBoundingClientRect().width + 24 : slider.clientWidth * 0.85;
        slider.scrollBy({ left: direction * distance, behavior: reduceMotion ? 'auto' : 'smooth' });
    };

    root.querySelector('[data-home-slider-prev]')?.addEventListener('click', () => scrollSlider(-1));
    root.querySelector('[data-home-slider-next]')?.addEventListener('click', () => scrollSlider(1));

    let isDragging = false;
    let startX = 0;
    let startScroll = 0;
    slider.addEventListener('pointerdown', (event) => {
        isDragging = true;
        startX = event.clientX;
        startScroll = slider.scrollLeft;
        slider.classList.add('home-slider--dragging');
        slider.setPointerCapture(event.pointerId);
    });
    slider.addEventListener('pointermove', (event) => {
        if (!isDragging) {
            return;
        }
        slider.scrollLeft = startScroll - (event.clientX - startX);
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach((eventName) => {
        slider.addEventListener(eventName, () => {
            isDragging = false;
            slider.classList.remove('home-slider--dragging');
        });
    });
})();
</script>
