<?php
$pageTitle = $title ?? return_translation('error_405_title');
$message = trim((string)($error ?? '')) !== '' ? (string)$error : return_translation('error_405_message');
?>
<!DOCTYPE html><html lang="<?= htmlSC(current_locale()) ?>" data-bs-theme="light" data-pwa="true"><head>
    <meta charset="utf-8">

    <!-- Viewport -->
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <!-- SEO Meta Tags -->
    <title>FBL - <?= htmlSC($pageTitle) ?></title>
    <meta name="description" content="Cartzilla - Multipurpose E-Commerce Bootstrap HTML Template">
    <meta name="keywords" content="online shop, e-commerce, online store, market, multipurpose, product landing, cart, checkout, ui kit, light and dark mode, bootstrap, html5, css3, javascript, gallery, slider, mobile, pwa">
    <meta name="author" content="Createx Studio">

    <!-- Webmanifest + Favicon / App icons -->
    <?= pwa_head_tags() ?>

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
            <h1><?= print_translation('error_405_heading') ?></h1>
            <p class="pb-3"><?= htmlSC($message) ?></p>
            <a class="btn btn-lg btn-primary" href="<?= base_href('/') ?>"><?= print_translation('error_home_button') ?></a>
        </section>

    </div>
</main>

</body>

</html>
