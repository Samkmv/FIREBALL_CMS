<?php

$postUrl = static fn(array $post): string => base_href('/posts/' . $post['slug']);
$heroStream = 'https://cdn.livespotting.com/vpu/ehlpzb4g/nkw9elfh_hub.m3u8';
$heroHlsScript = theme_asset('vendor/hls.js/hls.min.js') . '?v=' . filemtime(theme()->assetPath('vendor/hls.js/hls.min.js'));
$homeCityCategories = array_values(array_filter(
    (new \App\Models\Post())->getNavigationCategories(),
    static fn(array $category): bool => (int)($category['total'] ?? 0) > 0
));

$popularCameras = [];
foreach (array_slice($featured_posts ?? [], 0, 6) as $post) {
    $title = trim((string)($post['title'] ?? ''));
    if ($title === '') {
        continue;
    }

    $popularCameras[] = [
        'title' => $title,
        'city' => trim((string)($post['category_label'] ?? $post['category'] ?? 'MAXIPAPA')),
        'category_url' => base_href('/posts') . (!empty($post['category_slug']) ? '?category=' . rawurlencode((string)$post['category_slug']) : ''),
        'date' => date('d.m.Y', strtotime((string)($post['published_at'] ?? 'now'))),
        'image' => get_image($post['image'] ?? ''),
        'url' => $postUrl($post),
    ];
}

$objectCards = [
    [
        'title' => 'Многоквартирные дома',
        'text' => 'Контроль дворов, парковок, детских площадок и общественных зон. Жители получают удобный доступ к камерам и архиву, а управляющие компании — инструмент для повышения безопасности.',
        'image' => theme_asset('images/about/v2/hero.jpg'),
    ],
    [
        'title' => 'Бизнес',
        'text' => 'Покажите клиентам свой объект в прямом эфире. Онлайн-трансляция повышает доверие и помогает демонстрировать работу объекта открыто и честно.',
        'image' => theme_asset('images/about/v2/feature02.jpg'),
    ],
    [
        'title' => 'Общественные пространства',
        'text' => 'Парки, площади, набережные, спортивные зоны и туристические локации становятся доступнее для жителей и гостей города.',
        'image' => theme_asset('images/about/v2/feature03.jpg'),
    ],
];
$benefits = [
    ['icon' => 'ci-monitor', 'title' => 'Простота', 'text' => 'Просмотр работает через браузер без сложных настроек и приложений.'],
    ['icon' => 'ci-check-shield', 'title' => 'Надёжность', 'text' => 'Стабильная работа камер и доступ к архиву.'],
    ['icon' => 'ci-lock', 'title' => 'Безопасность', 'text' => 'Камеры помогают быстро разбирать спорные ситуации.'],
    ['icon' => 'ci-eye', 'title' => 'Прозрачность', 'text' => 'Жители и клиенты видят происходящее в режиме реального времени.'],
];

?>
<main class="home-page content-wrapper">
    <section class="home-hero">
        <div class="home-hero__media" aria-hidden="true">
            <video class="home-hero__video" data-home-hero-video muted autoplay playsinline preload="metadata">
                <source src="<?= htmlSC($heroStream) ?>" type="application/x-mpegURL">
            </video>
        </div>
        <div class="home-hero__overlay" aria-hidden="true"></div>
        <div class="container home-hero__inner">
            <div class="home-hero__content home-reveal">
                <span class="home-eyebrow"><span class="home-live-dot"></span> MAXIPAPA live platform</span>
                <h1 class="home-hero__title">Онлайн-видеонаблюдение нового поколения</h1>
                <p class="home-hero__lead">Следите за двором, домом, парковкой или бизнесом в режиме реального времени из любой точки мира.</p>
                <p class="home-hero__text">24/7 онлайн-трансляции, удобный просмотр с любого устройства и быстрый доступ к архиву записей.</p>
                <div class="home-hero__actions">
                    <a class="btn btn-light rounded-pill px-4 py-3 fw-semibold" href="<?= !empty($popularCameras) ? '#home-popular-cameras' : base_href('/posts') ?>">Смотреть камеры</a>
                    <a class="btn btn-outline-secondary rounded-pill px-4 py-3 fw-semibold" href="<?= base_href('/contacts') ?>">Подключить объект</a>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section home-stats" aria-label="Статистика MAXIPAPA">
        <div class="container">
            <div class="home-stats__grid home-reveal" data-home-stats>
                <div class="home-stat">
                    <strong><span data-home-counter="365">365</span>+</strong>
                    <span>Камер онлайн</span>
                </div>
                <div class="home-stat">
                    <strong>24/7</strong>
                    <span>Доступ к просмотру</span>
                </div>
                <div class="home-stat">
                    <strong><span data-home-counter="7">7</span></strong>
                    <span>Дней архива</span>
                </div>
            </div>
        </div>
    </section>

    <?php if (!empty($popularCameras)): ?>
        <section class="container-start pt-5" id="home-popular-cameras">
            <div class="row align-items-center g-0 pt-2 pt-sm-3 pt-md-4 pt-lg-5">
                <div class="col-md-4 col-lg-3 pb-1 pb-md-0 pe-3 ps-md-0 mb-4 mb-md-0">
                    <div class="d-flex flex-md-column align-items-end align-items-md-start home-reveal">
                        <div class="home-featured-head mb-md-5 me-3 me-md-0">
                            <span class="home-section-kicker">Featured on homepage</span>
                            <h2>Записи на главной</h2>
                            <p>Подборка объектов, которые сейчас вынесены на главную страницу сайта.</p>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" id="prev-home-featured" class="btn btn-icon btn-outline-secondary rounded-circle animate-slide-start me-1" aria-label="Назад">
                                <i class="ci-chevron-left fs-xl animate-target"></i>
                            </button>
                            <button type="button" id="next-home-featured" class="btn btn-icon btn-outline-secondary rounded-circle animate-slide-end" aria-label="Вперёд">
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
                            &quot;loop&quot;: <?= count($popularCameras) > 1 ? 'true' : 'false' ?>,
                            &quot;navigation&quot;: {
                                &quot;prevEl&quot;: &quot;#prev-home-featured&quot;,
                                &quot;nextEl&quot;: &quot;#next-home-featured&quot;
                            }
                        }">
                            <div class="swiper-wrapper">
                                <?php foreach ($popularCameras as $camera): ?>
                                    <div class="swiper-slide w-auto h-auto">
                                        <article class="col" style="width: 306px; max-width: 72vw;">
                                            <a class="ratio d-flex hover-effect-scale rounded overflow-hidden" href="<?= htmlSC($camera['url']) ?>" style="--cz-aspect-ratio: calc(305 / 416 * 100%)">
                                                <img src="<?= htmlSC($camera['image']) ?>" class="hover-effect-target w-100 h-100 object-fit-cover" alt="<?= htmlSC($camera['title']) ?>" loading="lazy">
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
                                            </div>
                                        </article>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
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
                    <h2>Что можно подключить</h2>
                    <p>MAXIPAPA подходит для жилых комплексов, бизнеса и городских пространств.</p>
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
                    <h2>Почему выбирают MAXIPAPA</h2>
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
                    <h2>Города присутствия</h2>
                    <p>Выберите город, чтобы перейти к опубликованным объектам и камерам MAXIPAPA.</p>
                    <?php if (!empty($homeCityCategories)): ?>
                        <div class="home-city-list" aria-label="Города присутствия">
                            <?php foreach ($homeCityCategories as $city): ?>
                                <a class="btn btn-outline-secondary rounded-pill home-city-link" href="<?= base_href('/posts') . '?category=' . rawurlencode((string)$city['slug']) ?>">
                                    <span><?= htmlSC((string)($city['label'] ?? $city['name'] ?? $city['slug'])) ?></span>
                                    <small><?= (int)($city['total'] ?? 0) ?></small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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
                <h2>Подключите видеонаблюдение уже сегодня</h2>
                <p>Создайте безопасное и прозрачное пространство для жителей, клиентов и посетителей.</p>
                <a class="btn btn-light rounded-pill px-4 py-3 fw-semibold" href="<?= base_href('/contacts') ?>">Подключить объект</a>
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
    const heroStream = <?= json_encode($heroStream, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const heroHlsScript = <?= json_encode($heroHlsScript, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const loadHeroHls = () => new Promise((resolve, reject) => {
        if (typeof window.Hls === 'function') {
            resolve(window.Hls);
            return;
        }
        const script = document.createElement('script');
        script.src = heroHlsScript;
        script.async = true;
        script.onload = () => typeof window.Hls === 'function' ? resolve(window.Hls) : reject(new Error('HLS is unavailable'));
        script.onerror = reject;
        document.head.appendChild(script);
    });
    const playHeroVideo = () => {
        const playPromise = heroVideo.play();
        if (playPromise && typeof playPromise.catch === 'function') {
            playPromise.catch(() => {});
        }
    };
    if (heroVideo && !reduceMotion) {
        heroVideo.addEventListener('loadeddata', () => {
            if (hero) {
                hero.classList.add('home-hero--video-ready');
            }
        }, { once: true });
        if (heroVideo.canPlayType('application/vnd.apple.mpegurl') || heroVideo.canPlayType('application/x-mpegURL')) {
            playHeroVideo();
        } else {
            loadHeroHls()
                .then((Hls) => {
                    if (!Hls.isSupported()) {
                        return;
                    }
                    const hls = new Hls({ liveDurationInfinity: true });
                    hls.loadSource(heroStream);
                    hls.attachMedia(heroVideo);
                    hls.on(Hls.Events.MANIFEST_PARSED, playHeroVideo);
                })
                .catch(() => {});
        }
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
