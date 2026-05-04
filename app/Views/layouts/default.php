<?php

$postNavigationCategories = (new \App\Models\Post())->getNavigationCategories();
$currentPostCategorySlug = trim((string)request()->get('category', ''));
$siteTitle = site_setting('site_title', SITE_NAME);
$siteDescription = site_setting('site_description', '');
$socialLinks = array_values(array_filter([
    [
        'href' => site_setting('social_telegram', ''),
        'icon' => 'ci-telegram',
        'label' => 'Telegram',
    ],
    [
        'href' => site_setting('social_instagram', ''),
        'icon' => 'ci-instagram',
        'label' => 'Instagram',
    ],
    [
        'href' => site_setting('social_facebook', ''),
        'icon' => 'ci-facebook',
        'label' => 'Facebook',
    ],
    [
        'href' => site_setting('social_youtube', ''),
        'icon' => 'ci-youtube',
        'label' => 'YouTube',
    ],
], static fn(array $link): bool => trim((string)($link['href'] ?? '')) !== ''));
$seoHomeTitle = site_setting('seo_home_title', '');
$seoDefaultTitleSuffix = site_setting('seo_default_title_suffix', '');
$seoMetaDescription = site_setting('seo_meta_description', '');
$seoMetaKeywords = site_setting('seo_meta_keywords', '');
$seoMetaAuthor = site_setting('seo_meta_author', '');
$seoRobots = site_setting('seo_robots', 'index,follow');
$seoOgImage = site_setting('seo_og_image', '');
$seoTwitterCard = site_setting('seo_twitter_card', 'summary_large_image');
$pageTitle = trim((string)($title ?? ''));
$pageSeoTitle = trim((string)($seo_title ?? ''));
$pageSeoDescription = trim((string)($seo_description ?? ''));
$pageSeoKeywords = trim((string)($seo_keywords ?? ''));
$pageSeoImage = trim((string)($seo_image ?? ''));
$pageSeoRobots = trim((string)($seo_robots ?? ''));
$pageSeoCanonical = trim((string)($seo_canonical ?? ''));
$isHomePage = uri_without_lang() === '';
$resolvedTitleBase = $pageSeoTitle !== ''
    ? $pageSeoTitle
    : ($isHomePage ? ($seoHomeTitle !== '' ? $seoHomeTitle : $siteTitle) : ($pageTitle !== '' ? $pageTitle : $siteTitle));
$resolvedTitleSuffix = $seoDefaultTitleSuffix !== '' ? $seoDefaultTitleSuffix : $siteTitle;
$documentTitle = $isHomePage
    ? $resolvedTitleBase
    : ($resolvedTitleBase !== $resolvedTitleSuffix ? $resolvedTitleBase . ' - ' . $resolvedTitleSuffix : $resolvedTitleBase);
$metaDescription = $pageSeoDescription !== ''
    ? $pageSeoDescription
    : ($seoMetaDescription !== '' ? $seoMetaDescription : ($siteDescription !== '' ? $siteDescription : $siteTitle));
$metaKeywords = $pageSeoKeywords !== '' ? $pageSeoKeywords : $seoMetaKeywords;
$metaAuthor = $seoMetaAuthor !== '' ? $seoMetaAuthor : $siteTitle;
$metaRobots = $pageSeoRobots !== '' ? $pageSeoRobots : $seoRobots;
$canonicalUrl = $pageSeoCanonical !== '' ? $pageSeoCanonical : base_href(uri_without_lang());
$normalizeSeoImage = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (filter_var($value, FILTER_VALIDATE_URL)) {
        return $value;
    }

    if (str_starts_with($value, '/')) {
        return base_url($value);
    }

    return get_image(ltrim($value, '/'));
};
$metaImage = $normalizeSeoImage($pageSeoImage !== '' ? $pageSeoImage : $seoOgImage);
$currentLangCode = app()->get('lang')['code'] ?? 'ru';
$ogLocale = match ($currentLangCode) {
    'en' => 'en_US',
    'de' => 'de_DE',
    'zh-cn' => 'zh_CN',
    default => 'ru_RU',
};
$currentUser = check_auth() ? get_user() : null;
$isAdmin = check_admin();
$currentUserAvatar = get_user_avatar($currentUser['avatar'] ?? null, 'sm');
$logoutAction = base_href('/logout');
$footerDescription = $siteDescription !== ''
    ? $siteDescription
    : return_translation('footer_description_fallback');
$footerNavigationLinks = [
    [
        'href' => base_href('/'),
        'label' => return_translation('tpl_menu_nav_index'),
    ],
    [
        'href' => base_href('/posts'),
        'label' => return_translation('footer_nav_posts'),
    ],
    [
        'href' => base_href('/contacts'),
        'label' => return_translation('tpl_menu_nav_contacts'),
    ],
];
$footerAccountLinks = check_auth()
    ? [
        [
            'href' => base_href('/profile'),
            'label' => return_translation('tpl_auth_profile'),
        ],
        [
            'href' => base_href('/chat'),
            'label' => return_translation('tpl_auth_chat'),
        ],
    ]
    : [
        [
            'href' => base_href('/login'),
            'label' => return_translation('tpl_auth_login'),
        ],
        [
            'href' => base_href('/register'),
            'label' => return_translation('tpl_auth_register'),
        ],
        [
            'href' => base_href('/contacts'),
            'label' => return_translation('footer_account_support'),
        ],
    ];
$footerCategoryLinks = array_slice($postNavigationCategories, 0, 6);
$postCategoryUrl = static function (?string $slug = null): string {
    $url = base_href('/posts');
    if ($slug === null || $slug === '') {
        return $url;
    }

    return $url . '?category=' . rawurlencode($slug);
};

?>
<!DOCTYPE html><html lang="<?= htmlSC(app()->get('lang')['code'] ?? 'en') ?>" data-bs-theme="light" data-pwa="true"><head>
    <meta charset="utf-8">

    <?= get_csrf_meta() ?>

    <!-- Viewport -->
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, viewport-fit=cover">

    <!-- SEO Meta Tags -->
    <title><?= htmlSC($documentTitle) ?></title>
    <meta name="description" content="<?= htmlSC($metaDescription) ?>">
    <?php if ($metaKeywords !== ''): ?>
        <meta name="keywords" content="<?= htmlSC($metaKeywords) ?>">
    <?php endif; ?>
    <meta name="author" content="<?= htmlSC($metaAuthor) ?>">
    <meta name="robots" content="<?= htmlSC($metaRobots) ?>">
    <link rel="canonical" href="<?= htmlSC($canonicalUrl) ?>">
    <meta property="og:locale" content="<?= htmlSC($ogLocale) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= htmlSC($siteTitle) ?>">
    <meta property="og:title" content="<?= htmlSC($documentTitle) ?>">
    <meta property="og:description" content="<?= htmlSC($metaDescription) ?>">
    <meta property="og:url" content="<?= htmlSC($canonicalUrl) ?>">
    <?php if ($metaImage !== ''): ?>
        <meta property="og:image" content="<?= htmlSC($metaImage) ?>">
        <meta property="og:image:alt" content="<?= htmlSC($documentTitle) ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="<?= htmlSC($seoTwitterCard !== '' ? $seoTwitterCard : 'summary_large_image') ?>">
    <meta name="twitter:title" content="<?= htmlSC($documentTitle) ?>">
    <meta name="twitter:description" content="<?= htmlSC($metaDescription) ?>">
    <?php if ($metaImage !== ''): ?>
        <meta name="twitter:image" content="<?= htmlSC($metaImage) ?>">
    <?php endif; ?>

    <!-- Webmanifest + Favicon / App icons -->
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <link rel="manifest" href="<?= base_url('/assets/default/manifest.json') ?>">
    <link rel="icon" type="image/png" href="<?= base_url('/assets/img/fbl_logo.png') ?>">
    <link rel="shortcut icon" href="<?= base_url('/assets/img/fbl_logo.png') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('/assets/img/fbl_logo.png') ?>">

    <!-- Theme switcher (color modes) -->
    <script src="<?= base_url('/assets/default/js/theme-switcher.js') ?>"></script>

    <!-- Preloaded local web font (Inter) -->
    <link rel="preload" href="<?= base_url('/assets/default/fonts/inter-variable-latin.woff2') ?>" as="font" type="font/woff2" crossorigin="">

    <!-- Font icons -->
    <link rel="preload" href="<?= base_url('/assets/default/icons/cartzilla-icons.woff2') ?>" as="font" type="font/woff2" crossorigin="">
    <link rel="stylesheet" href="<?= base_url('/assets/default/icons/cartzilla-icons.min.css') ?>">

    <!-- Vendor styles -->
    <link rel="stylesheet" href="<?= base_url('/assets/default/vendor/swiper/swiper-bundle.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('/assets/default/vendor/plyr/plyr.css') ?>">

    <?php if (!empty($styles)): ?>
        <?php foreach ($styles as $style): ?>

            <link rel="stylesheet" href="<?= $style; ?>">

        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Bootstrap + Theme styles -->
    <link rel="preload" href="<?= base_url('/assets/default/css/theme.min.css') ?>" as="style">
    <link rel="preload" href="<?= base_url('/assets/default/css/theme.rtl.min.css') ?>" as="style">
    <link rel="stylesheet" href="<?= base_url('/assets/default/css/theme.min.css') ?>" id="theme-styles">

    <!-- Customs styles -->
    <link rel="stylesheet" href="<?= base_url('/assets/default/vendor/toastr/toastr.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('/assets/default/css/style.css?v=' . filemtime(WWW . '/assets/default/css/style.css')) ?>">

    <!-- Header scripts -->
    <?php if (!empty($header_scripts)): ?>
        <?php foreach ($header_scripts as $header_script): ?>

            <script src="<?= $header_script; ?>"></script>

        <?php endforeach; ?>
    <?php endif; ?>

</head>


<!-- Body -->
<body>

<nav class="offcanvas offcanvas-start" id="navbarNav" tabindex="-1" aria-labelledby="navbarNavLabel">
    <div class="offcanvas-header py-3">
        <h5 class="offcanvas-title" id="navbarNavLabel"><?= print_translation('tpl_menu_nav') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body pt-0 pb-3">

        <!-- Navbar nav -->
        <div class="accordion" id="navigation">

            <!-- Categories collapse visible on screens < 992px wide (lg breakpoint) -->
            <div class="accordion-item border-0 d-lg-none">
                <div class="accordion-header" id="headingCategories">
                    <button type="button" class="accordion-button animate-underline fw-medium collapsed py-2" data-bs-toggle="collapse" data-bs-target="#categoriesMenu" aria-expanded="false" aria-controls="categoriesMenu">
                        <i class="ci-grid fs-lg me-2"></i>
                        <span class="d-block animate-target py-1"><?= print_translation('tpl_menu_btn_catalog') ?></span>
                    </button>
                </div>
                <div class="accordion-collapse collapse" id="categoriesMenu" aria-labelledby="headingCategories" data-bs-parent="#navigation">
                    <div class="accordion-body pb-3">
                        <div class="dropdown-menu show position-static d-flex flex-column gap-2 shadow-none p-4">
                            <a class="nav-link animate-underline d-inline-flex justify-content-between gap-3 p-0 <?= $currentPostCategorySlug === '' ? 'fw-semibold' : '' ?>" href="<?= $postCategoryUrl() ?>">
                                <span class="animate-target"><?= print_translation('tpl_menu_all_posts') ?></span>
                            </a>
                            <?php foreach ($postNavigationCategories as $category): ?>
                                <a class="nav-link animate-underline d-inline-flex justify-content-between gap-3 p-0 <?= $currentPostCategorySlug === $category['slug'] ? 'fw-semibold' : '' ?>" href="<?= $postCategoryUrl($category['slug']) ?>">
                                    <span class="animate-target"><?= htmlSC($category['label']) ?></span>
                                    <span class="text-body-tertiary fs-xs"><?= (int)$category['total'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main menu -->
        <?php echo view()->renderPartial('incs/main_menu_tpl') ?>

    </div>

    <!-- Account button visible on screens < 768px wide (md breakpoint) -->
    <div class="offcanvas-header flex-column align-items-start d-md-none">
        <?php if (check_auth()): ?>
            <a class="btn btn-lg btn-outline-secondary w-100 rounded-pill mb-2" href="<?= base_href('/profile') ?>">
                <img
                    src="<?= $currentUserAvatar ?>"
                    alt="<?= htmlSC($currentUser['name'] ?? '') ?>"
                    class="rounded-circle object-fit-cover ms-n1 me-2"
                    style="width: 28px; height: 28px;"
                >
                <?= print_translation('tpl_auth_profile') ?>
            </a>
            <a class="btn btn-lg btn-outline-secondary w-100 rounded-pill mb-2" href="<?= base_href('/chat') ?>">
                <i class="ci-chat fs-lg ms-n1 me-2"></i>
                <?= print_translation('tpl_auth_chat') ?>
            </a>
            <form action="<?= $logoutAction ?>" method="post" class="w-100">
                <?= get_csrf_field() ?>
                <button class="btn btn-lg btn-dark w-100 rounded-pill" type="submit">
                    <?= print_translation('tpl_auth_logout') ?>
                </button>
            </form>
        <?php else: ?>
            <a class="btn btn-lg btn-outline-secondary w-100 rounded-pill mb-2" href="<?= base_href('/login') ?>">
                <i class="ci-user fs-lg ms-n1 me-2"></i>
                <?= print_translation('tpl_auth_login') ?>
            </a>
            <a class="btn btn-lg btn-dark w-100 rounded-pill" href="<?= base_href('/register') ?>">
                <?= print_translation('tpl_auth_register') ?>
            </a>
        <?php endif; ?>
    </div>
</nav>

<!-- Shopping cart offcanvas -->
<div class="offcanvas offcanvas-end pb-sm-2 px-sm-2" id="shoppingCart" tabindex="-1" aria-labelledby="shoppingCartLabel" style="width: 500px">

    <!-- Header -->
    <div class="offcanvas-header flex-column align-items-start py-3 pt-lg-4">
        <div class="d-flex align-items-center justify-content-between w-100 mb-3 mb-lg-4">
            <h4 class="offcanvas-title" id="shoppingCartLabel"><?= print_translation('tpl_menu_cart') ?></h4>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
    </div>

    <!-- Items -->
    <div class="offcanvas-body d-flex flex-column gap-4 pt-2">

        <?= view()->renderPartial('incs/mini-cart') ?>

    </div>

</div>

<header class="navbar navbar-expand navbar-sticky sticky-top d-block bg-body z-fixed py-1 py-lg-0 py-xl-1 px-0" data-sticky-element="">
    <div class="container justify-content-start py-2 py-lg-3">

        <!-- Offcanvas menu toggler (Hamburger) -->
        <button type="button" class="navbar-toggler d-block flex-shrink-0 me-3 me-sm-4 position-relative" data-bs-toggle="offcanvas" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navbar brand (Logo) -->
        <a class="navbar-brand fs-2 p-0 pe-lg-2 pe-xxl-0 me-0 me-sm-3 me-md-4 me-xxl-5" href="<?= base_href('/')?>"><?= htmlSC($siteTitle) ?></a>

        <!-- Categories dropdown visible on screens > 991px wide (lg breakpoint) -->
        <div class="dropdown d-none d-lg-block w-100 me-4" style="max-width: 240px">
            <button type="button" class="btn btn-lg btn-secondary w-100 border-0 rounded-pill" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ci-grid fs-lg me-2 ms-n1"></i>
                <?= print_translation('tpl_menu_btn_catalog') ?>
                <i class="ci-chevron-down fs-lg me-2 ms-auto me-n1"></i>
            </button>

            <div class="dropdown-menu rounded-4 p-4" style="--cz-dropdown-spacer: .75rem; min-width: 280px;">
                <div class="d-flex flex-column gap-2" style="max-height: 600px; overflow: auto;">
                    <a class="nav-link animate-underline d-flex justify-content-between gap-3 p-0 <?= $currentPostCategorySlug === '' ? 'fw-semibold' : '' ?>" href="<?= $postCategoryUrl() ?>">
                        <span class="animate-target"><?= print_translation('tpl_menu_all_posts') ?></span>
                    </a>
                    <?php foreach ($postNavigationCategories as $category): ?>
                        <a class="nav-link animate-underline d-flex justify-content-between gap-3 p-0 <?= $currentPostCategorySlug === $category['slug'] ? 'fw-semibold' : '' ?>" href="<?= $postCategoryUrl($category['slug']) ?>">
                            <span class="animate-target text-truncate"><?= htmlSC($category['label']) ?></span>
                            <span class="text-body-tertiary fs-xs"><?= (int)$category['total'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- Search bar visible on screens > 768px wide (md breakpoint) -->
        <form action="<?= base_href('/search') ?>" method="get" class="position-relative w-100 d-none d-md-block me-3 me-xl-4" data-search-suggest data-suggest-url="<?= base_href('/search/suggest') ?>">
            <input
                type="search"
                name="q"
                value="<?= htmlSC((string)request()->get('q', '')) ?>"
                class="form-control form-control-lg rounded-pill"
                placeholder="<?= print_translation('tpl_menu_search') ?>"
                aria-label="Search"
                autocomplete="off"
                data-search-suggest-input
            >
            <button type="submit" class="btn btn-icon btn-ghost fs-lg btn-secondary text-bo border-0 position-absolute top-0 end-0 rounded-circle mt-1 me-1" aria-label="Search button">
                <i class="ci-search"></i>
            </button>
            <div class="position-absolute top-100 start-0 end-0 mt-2 d-none z-3" data-search-suggest-results></div>
        </form>

        <!-- Button group -->
        <div class="d-flex align-items-center gap-md-1 gap-lg-2 ms-auto">

            <!-- Theme switcher (light/dark/auto) -->
            <div class="dropdown">
                <button type="button" class="theme-switcher btn btn-icon btn-outline-secondary fs-lg border-0 rounded-circle animate-scale" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Toggle theme (light)">
              <span class="theme-icon-active d-flex animate-target">
                <i class="ci-sun"></i>
              </span>
                </button>
                <ul class="dropdown-menu" style="--cz-dropdown-min-width: 9rem">
                    <li>
                        <button type="button" class="dropdown-item active" data-bs-theme-value="light" aria-pressed="true">
                  <span class="theme-icon d-flex fs-base me-2">
                    <i class="ci-sun"></i>
                  </span>
                            <span class="theme-label"><?= print_translation('tpl_menu_btn_switch_light') ?></span>
                            <i class="item-active-indicator ci-check ms-auto"></i>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item" data-bs-theme-value="dark" aria-pressed="false">
                  <span class="theme-icon d-flex fs-base me-2">
                    <i class="ci-moon"></i>
                  </span>
                            <span class="theme-label"><?= print_translation('tpl_menu_btn_switch_dark') ?></span>
                            <i class="item-active-indicator ci-check ms-auto"></i>
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item" data-bs-theme-value="auto" aria-pressed="false">
                  <span class="theme-icon d-flex fs-base me-2">
                    <i class="ci-auto"></i>
                  </span>
                            <span class="theme-label"><?= print_translation('tpl_menu_btn_switch_auto') ?></span>
                            <i class="item-active-indicator ci-check ms-auto"></i>
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Language switcher -->
            <?php if (MULTILANGS): ?>

                <div class="dropdown">
                    <button type="button" class="btn btn-icon btn-outline-secondary fs-lg border-0 rounded-circle animate-scale" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="d-flex animate-target">
                            <i class="ci-globe"></i>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">

                <?php $request_uri = uri_without_lang(); ?>

                <?php foreach (LANGS as $key => $val): ?>

                    <?php if (app()->get('lang')['code'] == $key) continue; ?>

                    <?php if ($val['base'] == 1): ?>

                        <li>
                            <a class="dropdown-item" href="<?= base_url("{$request_uri}"); ?>"><?= $val['title']; ?></a>
                        </li>

                    <?php else: ?>

                        <li>
                            <a class="dropdown-item" href="<?= base_url("/{$key}{$request_uri}"); ?>"><?= $val['title']; ?></a>
                        </li>

                    <?php endif; ?>

                <?php endforeach; ?>

                    </ul>
                </div>

            <?php endif; ?>

            <!-- Search toggle button visible on screens < 768px wide (md breakpoint) -->
            <button type="button" class="btn btn-icon fs-xl btn-outline-secondary border-0 rounded-circle animate-scale d-md-none" data-bs-toggle="collapse" data-bs-target="#searchBar" aria-controls="searchBar" aria-label="Toggle search bar">
                <i class="ci-search animate-target"></i>
            </button>

            <?php if (check_auth()): ?>
                <div
                    class="dropdown"
                    data-notifications-center
                    data-feed-url="<?= base_href('/notifications/feed') ?>"
                    data-empty-text="<?= htmlSC(return_translation('notification_empty')) ?>"
                >
                    <button
                        type="button"
                        class="btn btn-icon fs-xl btn-outline-secondary border-0 rounded-circle animate-scale position-relative"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                        aria-label="<?= htmlSC(return_translation('tpl_notifications')) ?>"
                    >
                        <i class="ci-bell animate-target"></i>
                        <span
                            class="position-absolute top-0 start-100 badge fs-xs text-bg-danger rounded-pill ms-n3 z-2 d-none"
                            style="--cz-badge-padding-y: .25em; --cz-badge-padding-x: .42em"
                            data-notifications-badge
                        >0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0 overflow-hidden" style="--cz-dropdown-min-width: 24rem; max-width: min(24rem, calc(100vw - 2rem));">
                        <div class="px-3 py-3 border-bottom bg-body">
                            <strong class="d-block"><?= print_translation('tpl_notifications') ?></strong>
                        </div>
                        <div class="list-group list-group-flush" data-notifications-list>
                            <div class="px-3 py-3 text-body-secondary small"><?= print_translation('notification_loading') ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Account button visible on screens > 768px wide (md breakpoint) -->
            <div class="dropdown d-none d-md-block">
                <button type="button" class="btn btn-icon fs-lg btn-outline-secondary border-0 rounded-circle animate-scale position-relative" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php if (check_auth()): ?>
                        <span class="d-block w-100 h-100 rounded-circle overflow-hidden">
                            <img
                                src="<?= $currentUserAvatar ?>"
                                alt="<?= htmlSC($currentUser['name'] ?? '') ?>"
                                class="w-100 h-100 object-fit-cover rounded-circle"
                            >
                        </span>
                    <?php else: ?>
                        <i class="ci-user animate-target"></i>
                    <?php endif; ?>
                    <span class="visually-hidden"><?= print_translation('tpl_auth_account') ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if (check_auth()): ?>
                        <li>
                            <div class="dropdown-header d-flex align-items-center gap-2">
                                <img
                                    src="<?= $currentUserAvatar ?>"
                                    alt="<?= htmlSC($currentUser['name'] ?? '') ?>"
                                    class="rounded-circle object-fit-cover border"
                                    style="width: 32px; height: 32px;"
                                >
                                <div><?= htmlSC($currentUser['name'] ?? '') ?></div>
                            </div>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/profile') ?>">
                                <i class="ci-user fs-base"></i>
                                <span><?= print_translation('tpl_auth_profile') ?></span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/chat') ?>">
                                <i class="ci-chat fs-base"></i>
                                <span><?= print_translation('tpl_auth_chat') ?></span>
                            </a>
                        </li>
                        <?php if (check_admin()): ?>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/admin') ?>">
                                    <i class="ci-layout fs-base"></i>
                                    <span><?= print_translation('tpl_auth_admin') ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li>
                            <form action="<?= $logoutAction ?>" method="post">
                                <?= get_csrf_field() ?>
                                <button class="dropdown-item d-flex align-items-center gap-2 w-100" type="submit">
                                    <i class="ci-log-out fs-base"></i>
                                    <span><?= print_translation('tpl_auth_logout') ?></span>
                                </button>
                            </form>
                        </li>
                    <?php else: ?>
                        <li><a class="dropdown-item" href="<?= base_href('/login') ?>"><?= print_translation('tpl_auth_login') ?></a></li>
                        <li><a class="dropdown-item" href="<?= base_href('/register') ?>"><?= print_translation('tpl_auth_register') ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Cart button -->
<!--            <button type="button" class="btn btn-icon fs-xl btn-outline-secondary position-relative border-0 rounded-circle animate-scale" data-bs-toggle="offcanvas" data-bs-target="#shoppingCart" aria-controls="shoppingCart" aria-label="Shopping cart">-->
<!--                <span id="countCart" class="position-absolute top-0 start-100 badge fs-xs text-bg-primary rounded-pill ms-n3 z-2" style="--cz-badge-padding-y: .25em; --cz-badge-padding-x: .42em">-->
<!--                    --><?php //= \App\Helpers\Cart\Cart::getCartQuantityTotal() ?>
<!--                </span>-->
<!--                <i class="ci-shopping-cart animate-target"></i>-->
<!--            </button>-->

        </div>
    </div>

    <!-- Search collapse available on screens < 768px wide (md breakpoint) -->
    <div class="collapse d-md-none" id="searchBar">
        <div class="container pt-2 pb-3">
            <form action="<?= base_href('/search') ?>" method="get" class="position-relative" data-search-suggest data-suggest-url="<?= base_href('/search/suggest') ?>">
                <i class="ci-search position-absolute top-50 translate-middle-y d-flex fs-lg ms-3"></i>
                <input
                    type="search"
                    name="q"
                    value="<?= htmlSC((string)request()->get('q', '')) ?>"
                    class="form-control form-icon-start rounded-pill pe-5"
                    placeholder="<?= print_translation('tpl_menu_search') ?>"
                    data-autofocus="collapse"
                    autocomplete="off"
                    data-search-suggest-input
                >
                <button type="submit" class="btn btn-icon btn-ghost fs-lg btn-secondary border-0 position-absolute top-50 end-0 translate-middle-y rounded-circle me-2" aria-label="Search button">
                    <i class="ci-search"></i>
                </button>
                <div class="position-absolute top-100 start-0 end-0 mt-2 d-none z-3" data-search-suggest-results></div>
            </form>
        </div>
    </div>
</header>

<!-- Вызов быстрых flash уведомлений -->
<?php get_alerts(); ?>

<?= $this->content; ?>

<!-- Page footer -->
<footer class="footer position-relative bg-dark">
    <span class="position-absolute top-0 start-0 w-100 h-100 bg-body d-none d-block-dark"></span>
    <div class="container position-relative z-1 pt-sm-2 pt-md-3 pt-lg-4" data-bs-theme="dark">
        <div class="accordion py-5" id="footerLinks">
            <div class="row gy-4 gy-lg-0">
                <div class="col-lg-4 pe-lg-5">
                    <a class="d-inline-flex align-items-center text-dark-emphasis text-decoration-none fs-3 fw-semibold mb-3" href="<?= base_href('/') ?>">
                        <?= htmlSC($siteTitle) ?>
                    </a>
                    <p class="text-body mb-0"><?= htmlSC($footerDescription) ?></p>
                    <?php if (!empty($socialLinks)): ?>
                        <div class="pt-4">
                            <div class="text-dark-emphasis small text-uppercase fw-semibold mb-3" style="letter-spacing: .08em;"><?= print_translation('footer_heading_social') ?></div>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($socialLinks as $link): ?>
                                    <a
                                        class="btn btn-icon btn-outline-secondary rounded-circle"
                                        href="<?= htmlSC((string)$link['href']) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        aria-label="<?= htmlSC((string)$link['label']) ?>"
                                    >
                                        <i class="<?= htmlSC((string)$link['icon']) ?>"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-lg-8">
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 gx-3 gx-md-4">
                        <div class="accordion-item col border-0">
                            <h6 class="accordion-header" id="footerNavHeading">
                                <span class="text-dark-emphasis d-none d-sm-block"><?= print_translation('footer_heading_navigation') ?></span>
                                <button type="button" class="accordion-button py-3 d-sm-none collapsed" data-bs-toggle="collapse" data-bs-target="#footerNavLinks" aria-expanded="false" aria-controls="footerNavLinks"><?= print_translation('footer_heading_navigation') ?></button>
                            </h6>
                            <div class="accordion-collapse d-sm-block collapse" id="footerNavLinks" aria-labelledby="footerNavHeading" data-bs-parent="#footerLinks">
                                <ul class="nav flex-column gap-2 pt-sm-3 pb-3 mt-n1 mb-1">
                                    <?php foreach ($footerNavigationLinks as $link): ?>
                                        <li class="d-flex w-100 pt-1">
                                            <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="<?= $link['href'] ?>"><?= $link['label'] ?></a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <hr class="d-sm-none my-0">
                        </div>
                        <div class="accordion-item col border-0">
                            <h6 class="accordion-header" id="footerCategoryHeading">
                                <span class="text-dark-emphasis d-none d-sm-block"><?= print_translation('footer_heading_categories') ?></span>
                                <button type="button" class="accordion-button py-3 d-sm-none collapsed" data-bs-toggle="collapse" data-bs-target="#footerCategoryLinks" aria-expanded="false" aria-controls="footerCategoryLinks"><?= print_translation('footer_heading_categories') ?></button>
                            </h6>
                            <div class="accordion-collapse d-sm-block collapse" id="footerCategoryLinks" aria-labelledby="footerCategoryHeading" data-bs-parent="#footerLinks">
                                <ul class="nav flex-column gap-2 pt-sm-3 pb-3 mt-n1 mb-1">
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="<?= $postCategoryUrl() ?>"><?= print_translation('tpl_menu_all_posts') ?></a>
                                    </li>
                                    <?php foreach ($footerCategoryLinks as $category): ?>
                                        <li class="d-flex w-100 pt-1">
                                            <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="<?= $postCategoryUrl($category['slug']) ?>"><?= htmlSC($category['label']) ?></a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <hr class="d-sm-none my-0">
                        </div>
                        <div class="accordion-item col border-0">
                            <h6 class="accordion-header" id="footerAccountHeading">
                                <span class="text-dark-emphasis d-none d-sm-block"><?= print_translation('footer_heading_account') ?></span>
                                <button type="button" class="accordion-button py-3 d-sm-none collapsed" data-bs-toggle="collapse" data-bs-target="#footerAccountLinks" aria-expanded="false" aria-controls="footerAccountLinks"><?= print_translation('footer_heading_account') ?></button>
                            </h6>
                            <div class="accordion-collapse d-sm-block collapse" id="footerAccountLinks" aria-labelledby="footerAccountHeading" data-bs-parent="#footerLinks">
                                <ul class="nav flex-column gap-2 pt-sm-3 pb-3 mt-n1 mb-1">
                                    <?php foreach ($footerAccountLinks as $link): ?>
                                        <li class="d-flex w-100 pt-1">
                                            <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="<?= $link['href'] ?>"><?= $link['label'] ?></a>
                                        </li>
                                    <?php endforeach; ?>
                                    <?php if (check_auth()): ?>
                                        <li class="d-flex w-100 pt-1">
                                            <form action="<?= $logoutAction ?>" method="post" class="w-100">
                                                <?= get_csrf_field() ?>
                                                <button class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0 border-0 bg-transparent text-start" type="submit">
                                                    <?= print_translation('tpl_auth_logout') ?>
                                                </button>
                                            </form>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <hr class="d-sm-none my-0">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 border-top py-4">
            <p class="text-body fs-sm text-center text-md-start mb-0">
                <?= str_replace(
                    [':year', ':site'],
                    [(string)date('Y'), $siteTitle],
                    return_translation('footer_copyright')
                ) ?>
            </p>
        </div>
    </div>
</footer>

<?php if ($isAdmin): ?>
    <div class="modal fade" id="adminDeleteModal" tabindex="-1" aria-hidden="true" data-admin-delete-modal>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-5 overflow-hidden">
                <div class="modal-body p-4 p-md-5 text-center">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger-subtle text-danger mb-4" style="width: 72px; height: 72px;">
                        <i class="ci-trash fs-2"></i>
                    </div>
                    <h2 class="h4 mb-2"><?= print_translation('admin_delete_modal_title') ?></h2>
                    <p class="text-body-secondary mb-3" data-admin-delete-modal-message><?= print_translation('admin_delete_modal_default_message') ?></p>
                    <div class="rounded-4 bg-body-tertiary px-3 py-3 mb-3 d-none" data-admin-delete-modal-item-wrap>
                        <div class="text-uppercase small fw-semibold text-body-tertiary mb-1" style="letter-spacing: .08em;"><?= print_translation('admin_delete_modal_item_label') ?></div>
                        <div class="fw-semibold text-break" data-admin-delete-modal-item></div>
                    </div>
                    <p class="small text-body-secondary mb-0"><?= print_translation('admin_delete_modal_hint') ?></p>
                </div>
                <div class="modal-footer border-0 justify-content-center gap-2 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" data-bs-dismiss="modal">
                        <i class="ci-close"></i><?= print_translation('admin_btn_cancel') ?>
                    </button>
                    <button type="button" class="btn btn-danger rounded-pill d-inline-flex align-items-center gap-2" data-admin-delete-modal-confirm>
                        <i class="ci-trash"></i><?= print_translation('admin_btn_delete') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Back to top button -->
<div class="floating-buttons position-fixed top-50 end-0 z-sticky me-3 me-xl-4 pb-4">
    <a class="btn-scroll-top btn btn-sm bg-body border-0 rounded-pill shadow animate-slide-end" href="#top">
        Top
        <i class="ci-arrow-right fs-base ms-1 me-n1 animate-target"></i>
        <span class="position-absolute top-0 start-0 w-100 h-100 border rounded-pill z-0"></span>
        <svg class="position-absolute top-0 start-0 w-100 h-100 z-1" viewbox="0 0 62 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x=".75" y=".75" width="60.5" height="30.5" rx="15.25" stroke="currentColor" stroke-width="1.5" stroke-miterlimit="10"></rect>
        </svg>
    </a>
</div>

<script>
    const baseUrl = '<?= base_url(); ?>';
</script>

<script src="<?= base_url('/assets/default/js/jquery-3.7.1.min.js') ?>"></script>

<!-- Vendor scripts -->
<script src="<?= base_url('/assets/default/vendor/swiper/swiper-bundle.min.js') ?>"></script>
<script src="<?= base_url('/assets/default/vendor/toastr/toastr.min.js') ?>"></script>
<script src="<?= base_url('/assets/default/vendor/plyr/plyr.polyfilled.js') ?>"></script>

<?php if (!empty($footer_scripts)): ?>
    <?php foreach ($footer_scripts as $footer_script): ?>

        <script src="<?= $footer_script; ?>"></script>

    <?php endforeach; ?>
<?php endif; ?>

<!-- Bootstrap + Theme scripts -->
<script src="<?= base_url('/assets/default/js/theme.min.js') ?>"></script>

<!-- Customs scripts -->
<script src="<?= base_url('/assets/default/js/plyr-init.js?v=' . filemtime(WWW . '/assets/default/js/plyr-init.js')) ?>"></script>
<?php if ($isAdmin): ?>
    <script src="<?= base_url('/assets/default/js/admin-delete-modal.js?v=' . filemtime(WWW . '/assets/default/js/admin-delete-modal.js')) ?>"></script>
<?php endif; ?>
<script src="<?= base_url('/assets/default/js/main.js?v=' . filemtime(WWW . '/assets/default/js/main.js')) ?>"></script>


</body></html>
