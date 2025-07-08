<!DOCTYPE html><html lang="en" data-bs-theme="light" data-pwa="true"><head>
    <meta charset="utf-8">

    <?= get_csrf_meta() ?>

    <!-- Viewport -->
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, viewport-fit=cover">

    <!-- SEO Meta Tags -->
    <title><?= SITE_NAME . ' - ' . $title ?? '' ?></title>
    <meta name="description" content="Cartzilla - Multipurpose E-Commerce Bootstrap HTML Template">
    <meta name="keywords" content="online shop, e-commerce, online store, market, multipurpose, product landing, cart, checkout, ui kit, light and dark mode, bootstrap, html5, css3, javascript, gallery, slider, mobile, pwa">
    <meta name="author" content="Createx Studio">

    <!-- Webmanifest + Favicon / App icons -->
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <link rel="manifest" href="<?= base_url('/assets/default/manifest.json') ?>">
    <link rel="icon" type="image/png" href="<?= base_url('/assets/img/fbl_logo.png') ?>" sizes="1000x1000">
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
    <link rel="stylesheet" href="<?= base_url('/assets/default/css/style.css') ?>">

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
                        <div class="dropdown-menu show position-static d-flex flex-column gap-4 shadow-none p-4">
                            <div>

                                <?php
                                    new \App\Widgets\Menu\Menu([
                                        'table' => 'categories',
                                        'tpl' => 'shop_mobile_menu_tpl',
                                        'container' => 'ul',
                                        'class' => 'nav flex-column gap-2 mt-n2',
                                        'cacheTime' => 20,
                                        'cacheKey' => 'mobile-catalog-menu'
                                    ]);
                                ?>

                            </div>
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
        <a class="btn btn-lg btn-outline-secondary w-100 rounded-pill" href="account-signin.html">
            <i class="ci-user fs-lg ms-n1 me-2"></i>
            Аккаунт
        </a>
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
        <button type="button" class="navbar-toggler d-block flex-shrink-0 me-3 me-sm-4" data-bs-toggle="offcanvas" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navbar brand (Logo) -->
        <a class="navbar-brand fs-2 p-0 pe-lg-2 pe-xxl-0 me-0 me-sm-3 me-md-4 me-xxl-5" href="<?= base_href('/')?>"><?= print_translation('tpl_menu_logo') ?></a>

        <!-- Categories dropdown visible on screens > 991px wide (lg breakpoint) -->
        <div class="dropdown d-none d-lg-block w-100 me-4" style="max-width: 200px">
            <button type="button" class="btn btn-lg btn-secondary w-100 border-0 rounded-pill" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="ci-grid fs-lg me-2 ms-n1"></i>
                <?= print_translation('tpl_menu_btn_catalog') ?>
                <i class="ci-chevron-down fs-lg me-2 ms-auto me-n1"></i>
            </button>

            <?php
                new \App\Widgets\Menu\Menu([
                    'table' => 'categories',
                    'tpl' => 'shop_desktop_menu_tpl',
                    'container' => 'div',
                    'class' => 'dropdown-menu rounded-4 p-4',
                    'prepend' => '<div class="row" style=" overflow: scroll; max-height: 600px;">',
                    'append' => '</div>',
                    'attrs' => ['style' => '--cz-dropdown-spacer: .75rem; margin-left: -75px; min-width: 1000px;'],
                    'cacheTime' => 20,
                    'cacheKey' => 'desktop-catalog-menu'
                ]);
            ?>

        </div>

        <!-- Search bar visible on screens > 768px wide (md breakpoint) -->
        <div class="position-relative w-100 d-none d-md-block me-3 me-xl-4">
            <input type="search" class="form-control form-control-lg rounded-pill" placeholder="<?= print_translation('tpl_menu_search') ?>" aria-label="Search">
            <button type="button" class="btn btn-icon btn-ghost fs-lg btn-secondary text-bo border-0 position-absolute top-0 end-0 rounded-circle mt-1 me-1" aria-label="Search button">
                <i class="ci-search"></i>
            </button>
        </div>

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

            <!-- Account button visible on screens > 768px wide (md breakpoint) -->
            <a class="btn btn-icon fs-lg btn-outline-secondary border-0 rounded-circle animate-scale d-none d-md-inline-flex" href="account-signin.html">
                <i class="ci-user animate-target"></i>
                <span class="visually-hidden">Account</span>
            </a>

            <!-- Cart button -->
            <button type="button" class="btn btn-icon fs-xl btn-outline-secondary position-relative border-0 rounded-circle animate-scale" data-bs-toggle="offcanvas" data-bs-target="#shoppingCart" aria-controls="shoppingCart" aria-label="Shopping cart">
                <span id="countCart" class="position-absolute top-0 start-100 badge fs-xs text-bg-primary rounded-pill ms-n3 z-2" style="--cz-badge-padding-y: .25em; --cz-badge-padding-x: .42em">
                    <?= \App\Helpers\Cart\Cart::getCartQuantityTotal() ?>
                </span>
                <i class="ci-shopping-cart animate-target"></i>
            </button>
        </div>
    </div>

    <!-- Search collapse available on screens < 768px wide (md breakpoint) -->
    <div class="collapse d-md-none" id="searchBar">
        <div class="container pt-2 pb-3">
            <div class="position-relative">
                <i class="ci-search position-absolute top-50 translate-middle-y d-flex fs-lg ms-3"></i>
                <input type="search" class="form-control form-icon-start rounded-pill" placeholder="<?= print_translation('tpl_menu_search') ?>" data-autofocus="collapse">
            </div>
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

        <!-- Columns with links that are turned into accordion on screens < 500px wide (sm breakpoint) -->
        <div class="accordion py-5" id="footerLinks">
            <div class="row">
                <div class="col-md-4 d-sm-flex flex-md-column align-items-center align-items-md-start pb-3 mb-sm-4">
                    <h4 class="mb-sm-0 mb-md-4 me-4">
                        <a class="text-dark-emphasis text-decoration-none" href="index.html">Cartzilla</a>
                    </h4>
                    <p class="text-body fs-sm text-sm-end text-md-start mb-sm-0 mb-md-3 ms-0 ms-sm-auto ms-md-0 me-4">Got questions? Contact us 24/7</p>
                    <div class="dropdown" style="max-width: 250px">
                        <button type="button" class="btn btn-secondary dropdown-toggle justify-content-between w-100" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Help and consultation
                        </button>
                        <ul class="dropdown-menu" style="">
                            <li><a class="dropdown-item" href="#!">Help center &amp; FAQ</a></li>
                            <li><a class="dropdown-item" href="#!">Support chat</a></li>
                            <li><a class="dropdown-item" href="#!">Open support ticket</a></li>
                            <li><a class="dropdown-item" href="#!">Call center</a></li>
                        </ul>
                    </div><div class="d-flex justify-content-center justify-content-lg-start gap-2 pt-2 pt-md-3">
                        <a class="btn btn-icon fs-base btn-outline-secondary border-0" href="#!" data-bs-toggle="tooltip" data-bs-template="<div class=&quot;tooltip fs-xs mb-n2&quot; role=&quot;tooltip&quot;><div class=&quot;tooltip-inner bg-transparent text-white p-0&quot;></div></div>" aria-label="Follow us on Instagram" data-bs-original-title="Instagram">
                            <i class="ci-instagram"></i>
                        </a>
                        <a class="btn btn-icon fs-base btn-outline-secondary border-0" href="#!" data-bs-toggle="tooltip" data-bs-template="<div class=&quot;tooltip fs-xs mb-n2&quot; role=&quot;tooltip&quot;><div class=&quot;tooltip-inner bg-transparent text-white p-0&quot;></div></div>" aria-label="Follow us on Facebook" data-bs-original-title="Facebook">
                            <i class="ci-facebook"></i>
                        </a>
                        <a class="btn btn-icon fs-base btn-outline-secondary border-0" href="#!" data-bs-toggle="tooltip" data-bs-template="<div class=&quot;tooltip fs-xs mb-n2&quot; role=&quot;tooltip&quot;><div class=&quot;tooltip-inner bg-transparent text-white p-0&quot;></div></div>" aria-label="Follow us on Telegram" data-bs-original-title="Telegram">
                            <i class="ci-telegram"></i>
                        </a>
                        <a class="btn btn-icon fs-base btn-outline-secondary border-0" href="#!" data-bs-toggle="tooltip" data-bs-template="<div class=&quot;tooltip fs-xs mb-n2&quot; role=&quot;tooltip&quot;><div class=&quot;tooltip-inner bg-transparent text-white p-0&quot;></div></div>" aria-label="Follow us on WhatsApp" data-bs-original-title="WhatsApp">
                            <i class="ci-whatsapp"></i>
                        </a>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="row row-cols-1 row-cols-sm-3 gx-3 gx-md-4">
                        <div class="accordion-item col border-0">
                            <h6 class="accordion-header" id="companyHeading">
                                <span class="text-dark-emphasis d-none d-sm-block">Company</span>
                                <button type="button" class="accordion-button py-3 d-sm-none collapsed" data-bs-toggle="collapse" data-bs-target="#companyLinks" aria-expanded="false" aria-controls="companyLinks">Company</button>
                            </h6>
                            <div class="accordion-collapse d-sm-block collapse" id="companyLinks" aria-labelledby="companyHeading" data-bs-parent="#footerLinks" style="">
                                <ul class="nav flex-column gap-2 pt-sm-3 pb-3 mt-n1 mb-1">
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="#!">About company</a>
                                    </li>
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="#!">Our team</a>
                                    </li>
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="#!">Careers</a>
                                    </li>
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="#!">Contact us</a>
                                    </li>
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="#!">News</a>
                                    </li>
                                </ul>
                            </div>
                            <hr class="d-sm-none my-0">
                        </div>
                        <div class="accordion-item col border-0">
                            <h6 class="accordion-header" id="accountHeading">
                                <span class="text-dark-emphasis d-none d-sm-block">Account</span>
                                <button type="button" class="accordion-button py-3 d-sm-none collapsed" data-bs-toggle="collapse" data-bs-target="#accountLinks" aria-expanded="false" aria-controls="accountLinks">Account</button>
                            </h6>
                            <div class="accordion-collapse d-sm-block collapse" id="accountLinks" aria-labelledby="accountHeading" data-bs-parent="#footerLinks" style="">
                                <ul class="nav flex-column gap-2 pt-sm-3 pb-3 mt-n1 mb-1">
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="#!">Your account</a>
                                    </li>
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="#!">Shipping rates &amp; policies</a>
                                    </li>
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="#!">Refunds &amp; replacements</a>
                                    </li>
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="#!">Delivery info</a>
                                    </li>
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="#!">Order tracking</a>
                                    </li>
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="#!">Taxes &amp; fees</a>
                                    </li>
                                </ul>
                            </div>
                            <hr class="d-sm-none my-0">
                        </div>
                        <div class="accordion-item col border-0">
                            <h6 class="accordion-header" id="customerHeading">
                                <span class="text-dark-emphasis d-none d-sm-block">Customer service</span>
                                <button type="button" class="accordion-button py-3 d-sm-none collapsed" data-bs-toggle="collapse" data-bs-target="#customerLinks" aria-expanded="false" aria-controls="customerLinks">Customer service</button>
                            </h6>
                            <div class="accordion-collapse d-sm-block collapse" id="customerLinks" aria-labelledby="customerHeading" data-bs-parent="#footerLinks" style="">
                                <ul class="nav flex-column gap-2 pt-sm-3 pb-3 mt-n1 mb-1">
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="#!">Payment methods</a>
                                    </li>
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="#!">Money back guarantee</a>
                                    </li>
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="#!">Product returns</a>
                                    </li>
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="#!">Support center</a>
                                    </li>
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="#!">Shipping</a>
                                    </li>
                                    <li class="d-flex w-100 pt-1">
                                        <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="#!">Terms &amp; conditions</a>
                                    </li>
                                </ul>
                            </div>
                            <hr class="d-sm-none my-0">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category / tag links -->


        <!-- Copyright + Payment methods -->
        <div class="d-md-flex align-items-center border-top py-4">
            <div class="d-flex gap-2 gap-sm-3 justify-content-center ms-md-auto mb-4 mb-md-0 order-md-2">
                <div>
                    <img src="<?= base_url('/assets/default/img/payment-methods/visa-dark-mode.svg') ?>" alt="Visa">
                </div>
                <div>
                    <img src="<?= base_url('/assets/default/img/payment-methods/mastercard.svg') ?>" alt="Mastercard">
                </div>
                <div>
                    <img src="<?= base_url('/assets/default/img/payment-methods/paypal-dark-mode.svg') ?>" alt="PayPal">
                </div>
                <div>
                    <img src="<?= base_url('/assets/default/img/payment-methods/google-pay-dark-mode.svg') ?>" alt="Google Pay">
                </div>
                <div>
                    <img src="<?= base_url('/assets/default/img/payment-methods/apple-pay-dark-mode.svg') ?>" alt="Apple Pay">
                </div>
            </div>
            <p class="text-body fs-xs text-center text-md-start mb-0 me-4 order-md-1">© All rights reserved. <?= date('Y'); ?> г.</p>
        </div>
    </div>
</footer>

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

<?php if (!empty($footer_scripts)): ?>
    <?php foreach ($footer_scripts as $footer_script): ?>

        <script src="<?= $footer_script; ?>"></script>

    <?php endforeach; ?>
<?php endif; ?>

<!-- Bootstrap + Theme scripts -->
<script src="<?= base_url('/assets/default/js/theme.min.js') ?>"></script>

<!-- Customs scripts -->
<script src="<?= base_url('/assets/default/js/main.js') ?>"></script>


</body></html>
