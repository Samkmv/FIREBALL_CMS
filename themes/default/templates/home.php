<?php
/** Apple-inspired homepage. Content and access rules come from the CMS. */
$postUrl = static fn(array $post): string => base_href('/posts/' . $post['slug']);
$canViewPaidVideos = (bool)apply_filters('public_video_access_allowed', true, get_user() ?: []);
$heroStream = 'https://cdn.livespotting.com/vpu/ehlpzb4g/nkw9elfh_hub.m3u8';
$heroHlsScript = theme_asset_versioned('vendor/hls.js/hls.min.js');
$homeCityCategories = array_values(array_filter(
    (new \App\Models\Post())->getNavigationCategories(),
    static fn(array $category): bool => (int)($category['total'] ?? 0) > 0
));
$homePublishedCount = array_sum(array_column($homeCityCategories, 'total'));
$popularCameras = [];
foreach (array_slice($featured_posts ?? [], 0, 10) as $post) {
    $cameraTitle = trim((string)($post['title'] ?? ''));
    if ($cameraTitle === '') continue;
    $popularCameras[] = [
        'title' => $cameraTitle,
        'city' => trim((string)($post['category_label'] ?? $post['category'] ?? return_translation('home_index_category_fallback'))),
        'category_url' => base_href('/posts') . (!empty($post['category_slug']) ? '?category=' . rawurlencode((string)$post['category_slug']) : ''),
        'date' => date('d.m.Y', strtotime((string)($post['published_at'] ?? 'now'))),
        'image' => (string)($post['image_thumb'] ?? get_image($post['image'] ?? '')),
        'image_srcset' => (string)($post['image_srcset'] ?? ''),
        'image_width' => (int)(($post['image_width'] ?? 0) ?: 416),
        'image_height' => (int)(($post['image_height'] ?? 0) ?: 305),
        'url' => $postUrl($post),
        'locked' => isset($post['subscription_access']) && empty($post['subscription_access']['allowed']),
        'favorite_post' => $post,
    ];
}
$objectCards = [
    ['title' => return_translation('home_index_object_apartments_title'), 'text' => return_translation('home_index_object_apartments_text'), 'image' => theme_asset('images/home/home.webp'), 'icon' => 'ci-home'],
    ['title' => return_translation('home_index_object_business_title'), 'text' => return_translation('home_index_object_business_text'), 'image' => theme_asset('images/home/business.webp'), 'icon' => 'ci-briefcase'],
    ['title' => return_translation('home_index_object_public_title'), 'text' => return_translation('home_index_object_public_text'), 'image' => theme_asset('images/home/social.webp'), 'icon' => 'ci-globe'],
];
$benefits = [
    ['icon' => 'ci-monitor', 'title' => return_translation('home_index_benefit_simple_title'), 'text' => return_translation('home_index_benefit_simple_text')],
    ['icon' => 'ci-check-shield', 'title' => return_translation('home_index_benefit_reliable_title'), 'text' => return_translation('home_index_benefit_reliable_text')],
    ['icon' => 'ci-lock', 'title' => return_translation('home_index_benefit_secure_title'), 'text' => return_translation('home_index_benefit_secure_text')],
    ['icon' => 'ci-eye', 'title' => return_translation('home_index_benefit_transparent_title'), 'text' => return_translation('home_index_benefit_transparent_text')],
];
$homeWatchUrl = !$canViewPaidVideos ? base_href('/subscriptions/plans') : (!empty($popularCameras) ? '#home-popular-cameras' : base_href('/posts'));
$homeWatchLabel = !$canViewPaidVideos ? return_translation('subscriptions_view_plans') : return_translation('home_index_hero_watch_cameras');
$benefitsTitleLines = preg_split('/(?<=[.!?。！？])\s+/u', return_translation('home_index_benefits_title'), 2);
?>
<main class="home-page home-page--apple content-wrapper" data-home-page>
    <div class="home-scroll-progress" aria-hidden="true"><span data-home-progress></span></div>
    <section class="home-hero" aria-labelledby="home-hero-title">
        <div class="home-hero__media" aria-hidden="true">
            <?php if ($canViewPaidVideos): ?>
                <video class="home-hero__video" data-player-native data-home-hero-video data-home-hero-src="<?= htmlSC($heroStream) ?>" muted autoplay playsinline preload="none" tabindex="-1"></video>
            <?php endif; ?>
        </div>
        <div class="home-hero__overlay" aria-hidden="true"></div>
        <div class="container home-hero__inner">
            <div class="home-hero__content">
                <span class="home-eyebrow home-appear"><span class="home-live-dot" aria-hidden="true"></span><?= htmlSC(return_translation('home_index_eyebrow')) ?></span>
                <h1 class="home-hero__title" id="home-hero-title">
                    <span class="home-hero__line home-appear"><?= htmlSC(return_translation('home_index_hero_title')) ?></span>
                    <span class="home-hero__line home-appear"><span class="home-gradient-text"><?= htmlSC(return_translation('home_index_hero_title_accent')) ?></span></span>
                </h1>
                <p class="home-hero__lead home-appear"><?= htmlSC(return_translation('home_index_hero_lead')) ?></p>
                <div class="home-hero__actions">
                    <span class="home-hero__action home-appear"><a class="home-button home-button--primary" href="<?= htmlSC($homeWatchUrl) ?>"><?= htmlSC($homeWatchLabel) ?><i class="ci-arrow-up-right" aria-hidden="true"></i></a></span>
                    <span class="home-hero__action home-appear"><a class="home-button home-button--glass" href="<?= base_href('/contacts') ?>"><span><?= htmlSC(return_translation('home_index_connect_object')) ?></span><i class="ci-chevron-right" aria-hidden="true"></i></a></span>
                </div>
            </div>
        </div>
        <div class="container home-hero__footer home-appear" data-home-appear-delay="1000">
            <a class="home-hero__scroll" href="#home-objects"><span><?= htmlSC(return_translation('home_index_scroll_hint')) ?></span><i class="ci-arrow-down" aria-hidden="true"></i></a>
            <p class="home-hero__note"><?= htmlSC(return_translation('home_index_hero_text')) ?></p>
            <?php if ($canViewPaidVideos): ?>
                <button class="home-motion-toggle" type="button" data-home-motion-toggle data-pause-label="<?= htmlSC(return_translation('home_index_pause_video')) ?>" data-play-label="<?= htmlSC(return_translation('home_index_play_video')) ?>" aria-label="<?= htmlSC(return_translation('home_index_pause_video')) ?>" aria-pressed="false"><i class="ci-pause" aria-hidden="true"></i></button>
            <?php else: ?>
                <span class="home-glass-label"><i class="ci-lock" aria-hidden="true"></i><?= htmlSC(return_translation('subscriptions_locked_badge')) ?></span>
            <?php endif; ?>
        </div>
    </section>

    <section class="home-stats" id="home-discover" aria-label="<?= htmlSC(return_translation('home_index_stats_aria')) ?>">
        <div class="container">
            <div class="home-stats__grid home-appear" data-home-stats>
                <div class="home-stat"><strong><span data-home-counter="<?= (int)$homePublishedCount ?>"><?= (int)$homePublishedCount ?></span></strong><span><?= htmlSC(return_translation('home_index_stats_cameras')) ?></span></div>
                <div class="home-stat"><strong class="home-gradient-text">24/7</strong><span><?= htmlSC(return_translation('home_index_stats_access')) ?></span></div>
                <div class="home-stat"><strong><span data-home-counter="<?= count($homeCityCategories) ?>"><?= count($homeCityCategories) ?></span></strong><span><?= htmlSC(return_translation('home_index_stats_archive_days')) ?></span></div>
            </div>
        </div>
    </section>

    <?php if (!empty($popularCameras)): ?>
        <section class="home-section home-featured" id="home-popular-cameras" aria-labelledby="home-featured-title">
            <div class="container">
                <div class="home-featured-layout">
                    <div class="home-section-head home-featured-head home-appear">
                        <div><span class="home-section-kicker home-gradient-text"><?= htmlSC(return_translation('home_index_featured_kicker')) ?></span><h2 id="home-featured-title"><?= htmlSC(return_translation('home_index_featured_posts')) ?><span class="home-heading-dot">.</span></h2><p><?= htmlSC(return_translation('home_index_featured_posts_subtitle')) ?></p></div>
                        <a class="home-text-link" href="<?= base_href('/posts') ?>"><span class="home-text-link__label"><?= htmlSC(return_translation('home_index_featured_all')) ?></span><i class="ci-chevron-right" aria-hidden="true"></i></a>
                        <div class="home-slider-controls"><div class="home-slider-actions"><button class="home-slider-btn" type="button" data-home-slider-prev aria-label="<?= htmlSC(return_translation('home_index_slider_prev')) ?>"><i class="ci-chevron-left" aria-hidden="true"></i></button><button class="home-slider-btn" type="button" data-home-slider-next aria-label="<?= htmlSC(return_translation('home_index_slider_next')) ?>"><i class="ci-chevron-right" aria-hidden="true"></i></button></div><span class="home-slider-track" aria-hidden="true"><span data-home-slider-progress></span></span></div>
                    </div>
                    <div class="home-camera-slider home-appear" data-home-slider tabindex="0" role="region" aria-label="<?= htmlSC(return_translation('home_index_slider_progress')) ?>">
                        <?php foreach ($popularCameras as $camera): ?>
                            <article class="home-camera-card">
                                <div class="post-favorite-media">
                                    <a class="home-camera-card__media" href="<?= htmlSC($camera['url']) ?>" aria-label="<?= htmlSC($camera['title']) ?>">
                                        <img src="<?= htmlSC($camera['image']) ?>" srcset="<?= htmlSC($camera['image_srcset']) ?>" sizes="(max-width: 575px) 78vw, 286px" data-image-fallback="<?= htmlSC(base_url('/assets/img/no-image.png')) ?>" onerror="this.onerror=null;this.removeAttribute('srcset');this.src=this.dataset.imageFallback;" referrerpolicy="no-referrer" width="<?= $camera['image_width'] ?>" height="<?= $camera['image_height'] ?>" alt="" loading="lazy" decoding="async">
                                        <span class="home-online-badge"><?php if ($camera['locked']): ?><i class="ci-lock" aria-hidden="true"></i><?= htmlSC(return_translation('subscriptions_locked_badge')) ?><?php else: ?><span class="home-online-dot" aria-hidden="true"></span><?= htmlSC(return_translation('home_index_camera_online')) ?><?php endif; ?></span>
                                        <span class="home-camera-card__open" aria-hidden="true"><i class="ci-arrow-up-right"></i></span>
                                    </a>
                                    <?= $this->partial('favorite_button', ['post' => $camera['favorite_post'], 'placement' => 'media']) ?>
                                </div>
                                <div class="home-camera-card__body">
                                    <div class="home-camera-card__meta"><a href="<?= htmlSC($camera['category_url']) ?>"><?= htmlSC($camera['city']) ?></a><span><?= htmlSC($camera['date']) ?></span></div>
                                    <h3 class="text-break"><a href="<?= htmlSC($camera['url']) ?>"><?= htmlSC($camera['title']) ?></a></h3>
                                    <a class="home-text-link" href="<?= htmlSC($camera['url']) ?>"><span class="home-text-link__label"><?= htmlSC(return_translation('home_index_featured_posts_watch')) ?></span><i class="ci-chevron-right" aria-hidden="true"></i></a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="home-section home-objects" id="home-objects" aria-labelledby="home-objects-title">
        <div class="container">
            <div class="home-section-head home-appear"><div><span class="home-section-kicker home-gradient-text"><?= htmlSC(return_translation('home_index_use_cases_kicker')) ?></span><h2 id="home-objects-title"><?= htmlSC(return_translation('home_index_use_cases_title')) ?><span class="home-heading-dot">.</span></h2><p><?= htmlSC(return_translation('home_index_use_cases_subtitle')) ?></p></div></div>
            <div class="home-object-grid">
                <?php foreach ($objectCards as $index => $card): ?>
                    <article class="home-object-card home-appear <?= $index === 0 ? 'home-object-card--large' : '' ?>">
                        <img src="<?= htmlSC($card['image']) ?>" alt="" width="1536" height="1024" loading="lazy" decoding="async">
                        <div class="home-object-card__content"><span class="home-object-card__number">0<?= $index + 1 ?></span><h3><?= htmlSC($card['title']) ?></h3><p><?= htmlSC($card['text']) ?></p></div>
                        <a class="home-object-card__link" href="<?= base_href('/contacts') ?>" aria-label="<?= htmlSC($card['title'] . ': ' . return_translation('home_index_connect_object')) ?>"><i class="ci-arrow-up-right" aria-hidden="true"></i></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="home-section home-benefits" aria-labelledby="home-benefits-title">
        <div class="container">
            <div class="home-section-head home-section-head--center home-appear">
                <div>
                    <span class="home-section-kicker home-gradient-text"><?= htmlSC(return_translation('home_index_benefits_kicker')) ?></span>
                    <h2 id="home-benefits-title">
                        <?php foreach ($benefitsTitleLines as $index => $line): ?>
                            <span class="home-benefits-title__line"><?= htmlSC($line) ?><?php if ($index === count($benefitsTitleLines) - 1): ?><span class="home-gradient-text">.</span><?php endif; ?></span>
                        <?php endforeach; ?>
                    </h2>
                </div>
            </div>
            <div class="home-benefit-grid">
                <?php foreach ($benefits as $benefit): ?>
                    <article class="home-benefit-card home-appear"><div class="home-benefit-card__icon"><i class="<?= htmlSC($benefit['icon']) ?>" aria-hidden="true"></i></div><h3><?= htmlSC($benefit['title']) ?></h3><p><?= htmlSC($benefit['text']) ?></p></article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="home-section home-geo" aria-labelledby="home-geo-title">
        <div class="container">
            <div class="home-geo-card home-appear">
                <div class="home-geo-card__content">
                    <span class="home-section-kicker home-gradient-text"><?= htmlSC(return_translation('home_index_coverage_kicker')) ?></span>
                    <h2 id="home-geo-title"><?= htmlSC(return_translation('home_index_cities_title')) ?><span class="home-gradient-text">.</span></h2>
                    <p><?= htmlSC(return_translation('home_index_cities_subtitle')) ?></p>
                    <nav class="home-city-list" aria-label="<?= htmlSC(return_translation('home_index_cities_title')) ?>">
                        <?php foreach ($homeCityCategories as $city): ?>
                            <a class="home-city-link" href="<?= base_href('/posts') . '?category=' . rawurlencode((string)$city['slug']) ?>">
                                <i class="ci-grid home-city-link__icon" aria-hidden="true"></i>
                                <span class="home-city-link__label"><?= htmlSC((string)($city['label'] ?? $city['name'] ?? $city['slug'])) ?></span>
                                <small><span class="visually-hidden"><?= htmlSC(return_translation('home_index_category_items')) ?>: </span><?= (int)$city['total'] ?></small>
                            </a>
                        <?php endforeach; ?>
                        <a class="home-city-link home-city-link--all" href="<?= base_href('/posts') ?>">
                            <span class="home-city-link__label"><?= htmlSC(return_translation('home_index_cities_all')) ?></span>
                            <i class="ci-arrow-up-right" aria-hidden="true"></i>
                        </a>
                    </nav>
                </div>
            </div>
        </div>
    </section>

    <section class="home-section home-cta-wrap" aria-labelledby="home-cta-title">
        <div class="container"><div class="home-cta home-appear"><span class="home-section-kicker home-gradient-text"><?= htmlSC(return_translation('home_index_cta_kicker')) ?></span><h2 id="home-cta-title"><?= htmlSC(return_translation('home_index_cta_title')) ?><span class="home-gradient-text">.</span></h2><p><?= htmlSC(return_translation('home_index_cta_text')) ?></p><a class="home-button home-button--primary" href="<?= base_href('/contacts') ?>"><?= htmlSC(return_translation('home_index_connect_object')) ?><i class="ci-arrow-up-right" aria-hidden="true"></i></a></div></div>
    </section>
</main>

<script>
(function () {
    const root = document.querySelector('.home-page');
    if (!root) {
        return;
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const coarsePointer = window.matchMedia('(pointer: coarse)').matches;
    const saveData = Boolean(navigator.connection && navigator.connection.saveData);
    let heroMotionPaused = reduceMotion || saveData;
    let heroInView = true;
    const hero = root.querySelector('.home-hero');
    const syncHeaderHeight = () => {
        const height = document.querySelector('.public-navbar')?.getBoundingClientRect().height || 0;
        root.style.setProperty('--home-header-height', `${height}px`);
    };
    syncHeaderHeight();
    window.addEventListener('resize', syncHeaderHeight, { passive: true });
    const heroVideo = root.querySelector('[data-home-hero-video]');
    const heroStream = heroVideo ? (heroVideo.dataset.homeHeroSrc || '') : '';
    const heroHlsScript = <?= json_encode($heroHlsScript, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    const userAgent = window.navigator.userAgent || '';
    const isYandexBrowser = /YaBrowser|YaApp_Android/i.test(userAgent);
    const isChromiumBrowser = /Chrome|Chromium|CriOS|Edg|OPR|YaBrowser/i.test(userAgent) && !/Firefox|FxiOS/i.test(userAgent);
    const homeVideoLocale = ((document.documentElement.getAttribute('lang') || 'en').toLowerCase().startsWith('ru')) ? 'ru' : 'en';
    const homeVideoMessages = {
        ru: {
            loading: 'Загрузка видео...',
            connecting: 'Подключение...',
            reconnecting: 'Повторное подключение...',
            ready: 'Видео загружено',
            paused: 'Поток остановлен, выполняется восстановление...',
            unavailable: 'Источник временно недоступен',
        },
        en: {
            loading: 'Loading video...',
            connecting: 'Connecting...',
            reconnecting: 'Reconnecting...',
            ready: 'Video loaded',
            paused: 'Stream paused, recovering...',
            unavailable: 'Source is temporarily unavailable',
        }
    };
    const homeVideoText = (key) => {
        const messages = homeVideoMessages[homeVideoLocale] || homeVideoMessages.en;
        return messages[key] || homeVideoMessages.en[key] || key;
    };
    const setHeroStatus = () => {};
    const clearHeroStatus = () => {};
    const updateHeroDebug = () => {};
    // FIREBALL_HERO_PATCH_2026_09: resilient hls.js loader
    let heroHlsPromise = null;
    const loadHeroHls = () => {
        if (typeof window.Hls === 'function') {
            return Promise.resolve(window.Hls);
        }
        if (heroHlsPromise) {
            return heroHlsPromise;
        }

        // Если предыдущая попытка оборвалась, старый <script> уже бесполезен.
        const staleScript = document.querySelector('script[data-home-hero-hls]');
        if (staleScript) {
            staleScript.remove();
        }

        heroHlsPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = heroHlsScript;
            script.async = true;
            script.dataset.homeHeroHls = 'true';

            setHeroStatus(homeVideoText('connecting'));
            updateHeroDebug({ hlsState: 'loading_hls_script' });

            script.onload = () => {
                if (typeof window.Hls === 'function') {
                    resolve(window.Hls);
                    return;
                }

                script.remove();
                reject(new Error('HLS is unavailable'));
            };

            script.onerror = () => {
                script.remove();
                reject(new Error('Failed to load hls.js'));
            };

            document.head.appendChild(script);
        })
            .catch((error) => {
                // Важно: разрешаем следующую попытку загрузить hls.js заново.
                heroHlsPromise = null;
                throw error;
            });

        return heroHlsPromise;
    };
    let heroHls = null;
    let heroPlayPromise = null;
    let heroPlaybackStarted = false;
    let heroInitialized = false;
    let heroPlayAttempts = 0;
    let heroGestureFallbackBound = false;
    let heroRecoveryTimer = null;
    let heroHealthTimer = null;
    let heroLastCurrentTime = 0;
    let heroStalledChecks = 0;
    let heroMediaRecoveries = 0;
    const maxHeroPlayAttempts = isChromiumBrowser ? 8 : 4;
    const heroRecoveryDelay = isChromiumBrowser ? 1200 : 2500;
    const heroHealthInterval = isChromiumBrowser ? 2000 : 3000;
    const heroStalledThreshold = isChromiumBrowser ? 2 : 3;
    const markHeroVideoReady = () => {
        if (!hero || heroPlaybackStarted) {
            return;
        }
        heroPlaybackStarted = true;
        hero.classList.add('home-hero--video-ready');
        hero.classList.remove('home-hero--video-paused');
        setHeroStatus(homeVideoText('ready'), 'success');
        clearHeroStatus(1600);
        updateHeroDebug({ errorType: '', hlsState: heroHls ? 'playing' : 'native_playing' });
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

            // Явное действие пользователя должно обходить исчерпанный autoplay retry limit.
            heroPlayAttempts = 0;
            playHeroVideo();
        };
        document.addEventListener('pointerdown', retryOnGesture, { passive: true });
        document.addEventListener('keydown', retryOnGesture);
    };
    const playHeroVideo = () => {
        if (!heroVideo || heroMotionPaused || !heroInView || document.hidden || heroPlayPromise || (!heroVideo.paused && !heroVideo.ended)) {
            return;
        }
        if (!heroPlaybackStarted && heroPlayAttempts >= maxHeroPlayAttempts) {
            return;
        }
        if (!heroPlaybackStarted) {
            heroPlayAttempts += 1;
        }
        setHeroStatus(heroPlayAttempts > 1 ? homeVideoText('reconnecting') : homeVideoText('loading'));
        updateHeroDebug({
            hlsState: heroHls ? 'play_attempt_' + heroPlayAttempts : 'native_play_attempt_' + heroPlayAttempts,
            errorType: '',
        });
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
            setHeroStatus(homeVideoText('paused'), 'warning');
            updateHeroDebug({ errorType: 'play_exception' });
            bindHeroGestureFallback();
            scheduleHeroRecovery(false);
            return;
        }

        if (heroPlayPromise && typeof heroPlayPromise.then === 'function') {
            heroPlayPromise
                .catch((error) => {
                    if (hero) {
                        hero.classList.add('home-hero--video-paused');
                    }
                    setHeroStatus(homeVideoText('paused'), 'warning');
                    updateHeroDebug({ errorType: error && error.name ? error.name : 'play_rejected' });
                    bindHeroGestureFallback();
                    scheduleHeroRecovery(false);
                })
                .finally(() => {
                    heroPlayPromise = null;
                });
        } else {
            heroPlayPromise = null;
        }
    };
    const scheduleHeroRecovery = (recreateHls = false) => {
        if (!heroVideo || heroMotionPaused || !heroInView || document.hidden || reduceMotion || heroRecoveryTimer) {
            return;
        }

        setHeroStatus(homeVideoText('reconnecting'), 'warning');
        updateHeroDebug({
            hlsState: recreateHls ? 'scheduled_recreate' : 'scheduled_recover',
            errorType: heroVideo.error ? 'media_error_' + heroVideo.error.code : '',
        });

        heroRecoveryTimer = window.setTimeout(() => {
            heroRecoveryTimer = null;

            if (document.hidden) {
                return;
            }

            if (recreateHls && heroHls) {
                try {
                    heroHls.destroy();
                } catch (error) {
                }
                heroHls = null;
                heroInitialized = false;
                heroVideo.removeAttribute('src');
                heroVideo.load();
                initializeHeroVideo();
                return;
            }

            if (heroHls) {
                try {
                    updateHeroDebug({ hlsState: 'start_load' });
                    heroHls.startLoad(-1);
                } catch (error) {
                    try {
                        heroHls.destroy();
                    } catch (destroyError) {
                    }
                    heroHls = null;
                    heroInitialized = false;
                    initializeHeroVideo();
                    return;
                }
            } else if (heroVideo.canPlayType('application/vnd.apple.mpegurl') || heroVideo.canPlayType('application/x-mpegURL')) {
                heroVideo.src = heroStream;
                heroVideo.load();
            } else if (!heroInitialized) {
                initializeHeroVideo();
                return;
            }

            playHeroVideo();
        }, heroRecoveryDelay);
    };
    const initializeHeroVideo = () => {
        if (!heroVideo || !heroStream || heroInitialized || heroMotionPaused || !heroInView || reduceMotion) {
            if (heroVideo && heroStream) {
                updateHeroDebug({
                    hlsState: reduceMotion ? 'reduced_motion_skip' : (saveData ? 'save_data_skip' : 'not_initialized'),
                });
            }
            return;
        }
        heroInitialized = true;
        setHeroStatus(homeVideoText('connecting'));
        updateHeroDebug({ hlsState: 'initializing' });
        heroVideo.addEventListener('playing', revealHeroVideo, { once: true });

        if (heroVideo.canPlayType('application/vnd.apple.mpegurl') || heroVideo.canPlayType('application/x-mpegURL')) {
            heroVideo.src = heroStream;
            updateHeroDebug({ playbackMode: 'native_hls', hlsState: 'native_source_set' });
            playHeroVideo();
            return;
        }

        loadHeroHls()
            .then((Hls) => {
                if (heroMotionPaused || !heroInView || document.hidden) {
                    heroInitialized = false;
                    return;
                }
                if (!Hls.isSupported()) {
                    heroInitialized = false;
                    setHeroStatus(homeVideoText('unavailable'), 'error');
                    updateHeroDebug({
                        hlsState: 'unsupported',
                        errorType: 'hls_not_supported',
                    });
                    return;
                }
                heroHls = new Hls({
                    enableWorker: true,
                    lowLatencyMode: false,
                    liveDurationInfinity: true,
                    liveSyncDurationCount: isChromiumBrowser ? 5 : 3,
                    liveMaxLatencyDurationCount: isChromiumBrowser ? 15 : 8,
                    maxLiveSyncPlaybackRate: 1,
                    maxBufferLength: isChromiumBrowser ? 45 : 30,
                    maxMaxBufferLength: isChromiumBrowser ? 90 : 60,
                    backBufferLength: 60,
                    capLevelToPlayerSize: true,
                    maxBufferHole: .5,
                    nudgeOffset: .1,
                    nudgeMaxRetry: 5,
                    startFragPrefetch: true,
                    manifestLoadingTimeOut: 10000,
                    manifestLoadingMaxRetry: 8,
                    manifestLoadingRetryDelay: 1000,
                    manifestLoadingMaxRetryTimeout: 8000,
                    levelLoadingTimeOut: 10000,
                    levelLoadingMaxRetry: 8,
                    levelLoadingRetryDelay: 1000,
                    levelLoadingMaxRetryTimeout: 8000,
                    fragLoadingTimeOut: 20000,
                    fragLoadingMaxRetry: 8,
                    fragLoadingRetryDelay: 1000,
                    fragLoadingMaxRetryTimeout: 10000,
                });
                heroHls.on(Hls.Events.MEDIA_ATTACHED, () => {
                    setHeroStatus(homeVideoText('loading'));
                    updateHeroDebug({ hlsState: 'media_attached' });
                    heroHls.loadSource(heroStream);
                });
                heroHls.on(Hls.Events.MANIFEST_PARSED, () => {
                    setHeroStatus(homeVideoText('loading'));
                    updateHeroDebug({ hlsState: 'manifest_parsed' });
                    playHeroVideo();
                });
                heroHls.on(Hls.Events.LEVEL_LOADED, () => {
                    updateHeroDebug({ hlsState: 'level_loaded', errorType: '' });
                    if (heroVideo.paused && !document.hidden) {
                        playHeroVideo();
                    }
                });
                if (Hls.Events.FRAG_BUFFERED) {
                    heroHls.on(Hls.Events.FRAG_BUFFERED, () => {
                        updateHeroDebug({ hlsState: 'fragment_buffered', errorType: '' });
                        if (heroVideo.paused && !document.hidden) {
                            playHeroVideo();
                        }
                    });
                }
                heroHls.on(Hls.Events.ERROR, (event, data) => {
                    if (!data) {
                        return;
                    }

                    updateHeroDebug({
                        hlsState: data.fatal ? 'fatal_error' : 'non_fatal_error',
                        errorType: data.details || data.type || 'hls_error',
                    });

                    if (!data.fatal) {
                        const details = String(data.details || '');
                        if (/buffer|stalled|nudge|fragLoad|levelLoad|manifestLoad/i.test(details)) {
                            scheduleHeroRecovery(false);
                        }
                        return;
                    }

                    if (data.type === Hls.ErrorTypes.NETWORK_ERROR) {
                        scheduleHeroRecovery(false);
                    } else if (data.type === Hls.ErrorTypes.MEDIA_ERROR) {
                        heroMediaRecoveries += 1;
                        if (heroMediaRecoveries <= 2) {
                            heroHls.recoverMediaError();
                            scheduleHeroRecovery(false);
                        } else {
                            heroMediaRecoveries = 0;
                            scheduleHeroRecovery(true);
                        }
                    } else {
                        scheduleHeroRecovery(true);
                    }
                });
                heroHls.attachMedia(heroVideo);
            })
            .catch((error) => {
                heroInitialized = false;
                setHeroStatus(homeVideoText('unavailable'), 'error');
                updateHeroDebug({
                    hlsState: 'init_failed',
                    errorType: error && error.message ? error.message : 'hls_init_failed',
                });

                // Сетевой сбой при загрузке hls.js не должен требовать перезагрузки страницы.
                scheduleHeroRecovery(true);
            });
    };
    const scheduleHeroInit = () => {
        if (coarsePointer && 'IntersectionObserver' in window && hero) {
            const heroObserver = new IntersectionObserver((entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    heroObserver.disconnect();
                    initializeHeroVideo();
                }
            }, { threshold: 0.25 });
            heroObserver.observe(hero);
            return;
        }

        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(initializeHeroVideo, { timeout: 1200 });
            return;
        }

        window.setTimeout(initializeHeroVideo, 250);
    };
    scheduleHeroInit();
    if (heroVideo && !reduceMotion) {
        heroVideo.addEventListener('playing', () => {
            if (heroMotionPaused || !heroInView || document.hidden) {
                heroVideo.pause();
                return;
            }
            if (heroRecoveryTimer) {
                clearTimeout(heroRecoveryTimer);
                heroRecoveryTimer = null;
            }
            heroStalledChecks = 0;
            heroMediaRecoveries = 0;
            heroLastCurrentTime = heroVideo.currentTime;
            if (hero) {
                hero.classList.remove('home-hero--video-paused');
            }
            updateHeroDebug({ hlsState: heroHls ? 'playing' : 'native_playing', errorType: '' });
        });
        ['stalled', 'waiting', 'ended', 'error'].forEach((eventName) => {
            heroVideo.addEventListener(eventName, () => {
                if (heroMotionPaused || !heroInView || document.hidden) return;
                setHeroStatus(eventName === 'error' ? homeVideoText('unavailable') : homeVideoText('reconnecting'), eventName === 'error' ? 'error' : 'warning');
                updateHeroDebug({
                    hlsState: eventName,
                    errorType: eventName === 'error' && heroVideo.error ? 'media_error_' + heroVideo.error.code : eventName,
                });
                if (heroHls) {
                    try {
                        heroHls.startLoad(-1);
                    } catch (error) {
                    }
                }
                scheduleHeroRecovery(eventName === 'error');
            });
        });
        heroVideo.addEventListener('pause', () => {
            if (!document.hidden && !heroVideo.ended) {
                setHeroStatus(homeVideoText('paused'), 'warning');
                updateHeroDebug({ hlsState: 'pause' });
                scheduleHeroRecovery(false);
            }
        });

        heroHealthTimer = window.setInterval(() => {
            if (heroMotionPaused || !heroInView || document.hidden || !heroInitialized) {
                return;
            }

            if (heroVideo.paused || heroVideo.ended) {
                updateHeroDebug({ hlsState: heroVideo.ended ? 'ended' : 'paused' });
                scheduleHeroRecovery(false);
                return;
            }

            if (Math.abs(heroVideo.currentTime - heroLastCurrentTime) < 0.05) {
                heroStalledChecks += 1;
                if (heroStalledChecks >= heroStalledThreshold) {
                    heroStalledChecks = 0;
                    updateHeroDebug({ hlsState: 'current_time_stalled' });
                    scheduleHeroRecovery(false);
                }
            } else {
                heroStalledChecks = 0;
                heroLastCurrentTime = heroVideo.currentTime;
                updateHeroDebug({ hlsState: 'playing' });
            }
        }, heroHealthInterval);

        window.addEventListener('online', () => {
            if (heroMotionPaused || !heroInView || document.hidden) return;
            heroPlayAttempts = 0;
            updateHeroDebug({ hlsState: 'network_online', errorType: '' });

            if (heroHls) {
                try {
                    heroHls.startLoad(-1);
                } catch (error) {
                    scheduleHeroRecovery(true);
                    return;
                }

                playHeroVideo();
                return;
            }

            heroInitialized = false;
            initializeHeroVideo();
        });

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !heroMotionPaused && heroInView) {
                if (heroHls) {
                    try {
                        heroHls.startLoad(-1);
                    } catch (error) {
                        scheduleHeroRecovery(true);
                    }
                }
                updateHeroDebug({ hlsState: 'visibility_resume' });
                playHeroVideo();
            }
        });
        window.addEventListener('pageshow', (event) => {
            if (heroMotionPaused || !heroInView || document.hidden) return;
            if (heroHls) {
                try {
                    heroHls.startLoad(-1);
                } catch (error) {
                    scheduleHeroRecovery(true);
                }
            }
            updateHeroDebug({ hlsState: event.persisted ? 'pageshow_bfcache' : 'pageshow' });
            playHeroVideo();
        });
        window.addEventListener('pagehide', () => {
            if (heroRecoveryTimer) {
                clearTimeout(heroRecoveryTimer);
                heroRecoveryTimer = null;
            }
            if (heroHls) {
                try {
                    heroHls.stopLoad();
                } catch (error) {
                }
            }
        }, { once: true });
    }

    // A single observer owns the homepage reveals. Content stays readable without JS.
    const revealItems = Array.from(root.querySelectorAll('.home-appear'));
    const showRevealItem = (item) => item.classList.add('home-appear--visible');
    if (reduceMotion || !('IntersectionObserver' in window)) {
        revealItems.forEach(showRevealItem);
    } else {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                showRevealItem(entry.target);
                revealObserver.unobserve(entry.target);
            });
        }, { rootMargin: '0px 0px -40px 0px', threshold: 0.06 });
        revealItems.forEach((item) => {
            const group = item.closest('.home-benefit-grid, .home-object-grid, .home-hero__content');
            const isHeroItem = Boolean(item.closest('.home-hero__content'));
            const groupIndex = group ? Array.from(group.querySelectorAll('.home-appear')).indexOf(item) : 0;
            const delay = item.hasAttribute('data-home-appear-delay') ? Number(item.dataset.homeAppearDelay) : Math.min(groupIndex * (isHeroItem ? 170 : 85), isHeroItem ? 850 : 255);
            item.style.setProperty('--home-appear-delay', `${delay}ms`);
        });
        root.classList.add('home-appear-ready');
        window.requestAnimationFrame(() => window.requestAnimationFrame(() => {
            revealItems.forEach((item) => {
                const rect = item.getBoundingClientRect();
                if (rect.top < window.innerHeight && rect.bottom > 0) showRevealItem(item);
                else revealObserver.observe(item);
            });
        }));
        root.addEventListener('focusin', (event) => {
            const item = event.target.closest('.home-appear');
            if (item) {
                item.style.setProperty('--home-appear-delay', '0ms');
                showRevealItem(item);
                revealObserver.unobserve(item);
            }
        });
    }

    const counters = root.querySelectorAll('[data-home-counter]');
    const runCounters = () => counters.forEach((counter) => {
        const target = Number(counter.dataset.homeCounter);
        if (!Number.isFinite(target) || target <= 0 || counter.dataset.homeCounterDone) return;
        counter.dataset.homeCounterDone = '1';
        if (reduceMotion) return;
        const start = performance.now();
        const tick = (now) => {
            const progress = Math.min((now - start) / 1000, 1);
            counter.textContent = String(Math.round(target * (1 - Math.pow(1 - progress, 3))));
            if (progress < 1) window.requestAnimationFrame(tick);
        };
        window.requestAnimationFrame(tick);
    });
    const stats = root.querySelector('[data-home-stats]');
    if (stats && 'IntersectionObserver' in window) {
        const statsObserver = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                runCounters();
                statsObserver.disconnect();
            }
        }, { threshold: 0.3 });
        statsObserver.observe(stats);
    } else runCounters();

    const motionToggle = root.querySelector('[data-home-motion-toggle]');
    const syncHeroAmbientMotion = () => {
        hero?.classList.toggle('home-hero--ambient-paused', heroMotionPaused || !heroInView || document.hidden);
    };
    const syncMotionButton = () => {
        syncHeroAmbientMotion();
        if (!motionToggle) return;
        motionToggle.setAttribute('aria-pressed', String(heroMotionPaused));
        motionToggle.setAttribute('aria-label', heroMotionPaused ? motionToggle.dataset.playLabel : motionToggle.dataset.pauseLabel);
        motionToggle.querySelector('i').className = heroMotionPaused ? 'ci-play' : 'ci-pause';
    };
    const suspendHero = () => {
        if (!heroVideo) return;
        if (heroRecoveryTimer) {
            clearTimeout(heroRecoveryTimer);
            heroRecoveryTimer = null;
        }
        heroVideo.autoplay = false;
        heroVideo.pause();
        if (heroHls) heroHls.stopLoad();
    };
    const resumeHero = () => {
        if (!heroVideo || heroMotionPaused || !heroInView || document.hidden) return;
        heroPlayAttempts = 0;
        if (!heroInitialized) initializeHeroVideo();
        else {
            if (heroHls) heroHls.startLoad(-1);
            playHeroVideo();
        }
    };
    syncMotionButton();
    motionToggle?.addEventListener('click', () => {
        heroMotionPaused = !heroMotionPaused;
        syncMotionButton();
        if (heroMotionPaused) suspendHero();
        else resumeHero();
    });
    if (hero && 'IntersectionObserver' in window) {
        const mediaObserver = new IntersectionObserver((entries) => {
            heroInView = entries.some((entry) => entry.isIntersecting);
            syncHeroAmbientMotion();
            if (heroInView) resumeHero();
            else suspendHero();
        }, { rootMargin: '100px 0px', threshold: 0 });
        mediaObserver.observe(hero);
    }
    document.addEventListener('visibilitychange', () => {
        syncHeroAmbientMotion();
        if (document.hidden) suspendHero();
        else resumeHero();
    });

    // Track reading progress without moving the background video.
    const pageProgress = root.querySelector('[data-home-progress]');
    let scrollFrame = 0;
    const updateScroll = () => {
        scrollFrame = 0;
        const pageHeight = document.documentElement.scrollHeight - window.innerHeight;
        if (pageProgress) pageProgress.style.transform = `scaleX(${pageHeight > 0 ? Math.min(1, Math.max(0, window.scrollY / pageHeight)) : 0})`;
    };
    const scheduleScroll = () => {
        if (!scrollFrame) scrollFrame = window.requestAnimationFrame(updateScroll);
    };
    window.addEventListener('scroll', scheduleScroll, { passive: true });
    window.addEventListener('resize', scheduleScroll, { passive: true });
    updateScroll();

    const slider = root.querySelector('[data-home-slider]');
    if (!slider) return;
    const previous = root.querySelector('[data-home-slider-prev]');
    const next = root.querySelector('[data-home-slider-next]');
    const sliderProgress = root.querySelector('[data-home-slider-progress]');
    const updateSlider = () => {
        const limit = slider.scrollWidth - slider.clientWidth;
        if (previous) previous.disabled = slider.scrollLeft <= 2;
        if (next) next.disabled = limit <= 2 || slider.scrollLeft >= limit - 2;
        if (sliderProgress) sliderProgress.style.transform = `scaleX(${limit <= 0 ? 1 : Math.min(1, (slider.scrollLeft + slider.clientWidth) / slider.scrollWidth)})`;
    };
    const scrollSlider = (direction) => {
        const card = slider.querySelector('.home-camera-card');
        const gap = parseFloat(window.getComputedStyle(slider).gap) || 0;
        slider.scrollBy({ left: direction * ((card?.getBoundingClientRect().width || slider.clientWidth) + gap), behavior: reduceMotion ? 'auto' : 'smooth' });
    };
    previous?.addEventListener('click', () => scrollSlider(-1));
    next?.addEventListener('click', () => scrollSlider(1));
    slider.addEventListener('keydown', (event) => {
        if (event.target !== slider || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
        event.preventDefault();
        scrollSlider(event.key === 'ArrowRight' ? 1 : -1);
    });
    slider.addEventListener('scroll', updateSlider, { passive: true });
    window.addEventListener('resize', updateSlider, { passive: true });
    updateSlider();
})();
</script>
