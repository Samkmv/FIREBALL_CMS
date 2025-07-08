<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">

    <base href="<?= base_url('/') ?>">

    <?= get_csrf_meta() ?>

    <title>FBL - <?= $title ?? '' ?></title>

    <link rel="icon" type="image/x-icon" href="<?= base_url('/fireball_logo.png')?>">

    <link rel="stylesheet" href="<?= base_url('/assets/default/bootstrap/css/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('/assets/default/iziModal/css/iziModal.min.css') ?>">

    <?php if (!empty($styles)): ?>
        <?php foreach ($styles as $style): ?>

            <link rel="stylesheet" href="<?= $style; ?>">

        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($header_scripts)): ?>
        <?php foreach ($header_scripts as $header_script): ?>

            <script src="<?= $header_script; ?>"></script>

        <?php endforeach; ?>
    <?php endif; ?>

</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-3">
        <div class="container-fluid">
            <a href="<?= base_href('/') ?>">
                <img src="<?= base_url('/fireball_logo.png')?>" alt="fireball_logo" width="50" height="50">
            </a>
            <a class="navbar-brand" href="<?= base_href('/') ?>">FBL Engine</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">

                <!--

                Для вкл кэширования меню, использовать метод ниже

                <?//= cache()->get('menu'); ?>

                -->

                <?php echo view()->renderPartial('incs/menu'); ?>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#"
                           id="navbarDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <?= app()->get('lang')['title']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">

                            <?php $request_uri = uri_without_lang(); ?>

                            <?php foreach (LANGS as $key => $val): ?>

                            <?php if (app()->get('lang')['code'] == $key) continue; ?>

                                <?php if ($val['base'] == 1): ?>

                                    <li><a class="dropdown-item" href="<?= base_url("{$request_uri}"); ?>"><?= $val['title']; ?></a></li>

                                <?php else: ?>

                                    <li><a class="dropdown-item" href="<?= base_url("/{$key}{$request_uri}"); ?>"><?= $val['title']; ?></a></li>

                                <?php endif; ?>

                            <?php endforeach; ?>

                        </ul>
                    </li>
                </ul>

                <?php if(check_auth()): ?>

                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#"
                               id="navbarDropdown" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false">
                                <?= get_user()['name']; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">

                                <li><a class="dropdown-item" href="<?= base_href('/dashboard') ?>"><?php _e('menu_dashboard'); ?></a></li>

                                <li><a class="dropdown-item" href="<?= base_href('/logout') ?>"><?php _e('menu_logout'); ?></a></li>

                            </ul>
                        </li>
                    </ul>

                <?php else: ?>

                    <a href="<?= base_href('/register') ?>"
                       type="button"
                       class="btn btn-outline-light me-2"><?php _e('menu_register'); ?>
                    </a>

                    <a href="<?= base_href('/login') ?>"
                       type="button"
                       class="btn btn-warning"><?php _e('menu_login'); ?>
                    </a>

                <?php endif; ?>

            </div>
        </div>
    </nav>

    <?php get_alerts(); ?>
    <?= $this->content; ?>

    <div class="iziModal-alert-success"></div>
    <div class="iziModal-alert-error"></div>

</body>

<script src="<?= base_url('/assets/default/js/jquery-3.7.1.min.js') ?>"></script>
<script src="<?= base_url('/assets/default/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('/assets/default/iziModal/js/iziModal.min.js') ?>"></script>

<?php if (!empty($footer_scripts)): ?>
    <?php foreach ($footer_scripts as $footer_script): ?>

        <script src="<?= $footer_script; ?>"></script>

    <?php endforeach; ?>
<?php endif; ?>

<script src="<?= base_url('/assets/default/js/main.js') ?>"></script>

</html>