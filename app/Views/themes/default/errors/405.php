<!DOCTYPE html><html lang="en" data-bs-theme="light" data-pwa="true"><head>
    <meta charset="utf-8">

    <!-- Viewport -->
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, viewport-fit=cover">

    <!-- SEO Meta Tags -->
    <title>FBL - <?= $title ?? '405 - Method Not Allowed' ?></title>
    <meta name="description" content="Cartzilla - Multipurpose E-Commerce Bootstrap HTML Template">
    <meta name="keywords" content="online shop, e-commerce, online store, market, multipurpose, product landing, cart, checkout, ui kit, light and dark mode, bootstrap, html5, css3, javascript, gallery, slider, mobile, pwa">
    <meta name="author" content="Createx Studio">

    <!-- Webmanifest + Favicon / App icons -->
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="<?= base_url('/assets/default/app-icons/icon-32x32.png') ?>" sizes="32x32">
    <link rel="apple-touch-icon" href="<?= base_url('/assets/default/app-icons/icon-180x180.png') ?>">

    <!-- Theme switcher (color modes) -->
    <script src="<?= base_url('/assets/default/js/theme-switcher.js') ?>"></script>

    <!-- Preloaded local web font (Inter) -->
    <link rel="preload" href="<?= base_url('/assets/default/fonts/inter-variable-latin.woff2') ?>" as="font" type="font/woff2" crossorigin="">

    <!-- Font icons -->
    <link rel="preload" href="<?= base_url('/assets/default/icons/cartzilla-icons.woff2') ?>" as="font" type="font/woff2" crossorigin="">
    <link rel="stylesheet" href="<?= base_url('/assets/default/icons/cartzilla-icons.min.css') ?>">

    <!-- Vendor styles -->
    <link rel="stylesheet" href="<?= base_url('/assets/default/vendor/swiper/swiper-bundle.min.css') ?>">

    <!-- Bootstrap + Theme styles -->
    <link rel="preload" href="<?= base_url('/assets/default/css/theme.min.css') ?>" as="style">
    <link rel="preload" href="<?= base_url('/assets/default/css/theme.rtl.min.css') ?>" as="style">
    <link rel="stylesheet" href="<?= base_url('/assets/default/css/theme.min.css') ?>" id="theme-styles">

</head>

<body>

<!-- Page content -->
<main class="content-wrapper d-flex align-items-center justify-content-center">
    <div class="container">

        <!-- Error message -->
        <section class="text-center py-5 px-2 px-sm-0 my-2 my-md-3 my-lg-4 my-xl-5 mx-auto" style="max-width: 636px">
            <h1 class="display-1">405</h1>
            <h1>Method Not Allowed</h1>
            <p class="pb-3"><?= $error ?? ''; ?></p>
            <a class="btn btn-lg btn-primary" href="<?= base_url('/') ?>">Go to homepage</a>
        </section>

    </div>
</main>

</body>

</html>
