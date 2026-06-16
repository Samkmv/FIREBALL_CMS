<?php
/**
 * Layout Template
 *
 * Available variables: $settings, $user, $locale, $available_locales.
 * Page-specific variables are also available.
 */
?>
<!doctype html>
<html lang="<?= htmlSC(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <?= get_csrf_meta() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlSC($title ?? site_setting('site_title', SITE_NAME)) ?></title>
    <link rel="stylesheet" href="<?= theme_asset('css/style.css') ?>">
</head>
<body>
    <?= render_partial('header', get_defined_vars()) ?>
    <?= render_partial('menu', get_defined_vars()) ?>

    <main class="theme-main">
        <?= $this->content ?>
    </main>

    <?= render_partial('footer', get_defined_vars()) ?>
    <script src="<?= theme_asset('js/theme.js') ?>"></script>
</body>
</html>