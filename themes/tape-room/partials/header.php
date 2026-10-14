<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="<?= htmlSC(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f7f5f0">
    <?= get_csrf_meta() ?>
    <title><?= htmlSC($title ?? 'Tape Room') ?> · Tape Room</title>
    <?= $this->partial('pwa-head') ?>
    <link rel="stylesheet" href="<?= htmlSC(theme_asset_versioned('css/player.css')) ?>">
    <link rel="stylesheet" href="<?= htmlSC(theme_asset_versioned('css/shell.css')) ?>">
</head>
<body class="rp-body">
<div class="tr-shell">
    <header class="tr-header"><a class="rp-brand" href="<?= htmlSC(base_href('/')) ?>">TAPE ROOM<span>ЛИЧНАЯ МУЗЫКАЛЬНАЯ КОМНАТА</span></a>
        <?php if (!check_auth()): ?><nav class="tr-auth-nav" aria-label="Вход и регистрация">
            <a href="<?= htmlSC(base_href('/login')) ?>" aria-label="Войти" title="Войти"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3h6v18h-6M3 12h12m-5-5 5 5-5 5"/></svg><span>Войти</span></a>
            <a href="<?= htmlSC(base_href('/register')) ?>" aria-label="Зарегистрироваться" title="Зарегистрироваться"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="7" r="4"/><path d="M2 22v-4a7 7 0 0 1 14 0v4m4-13v8m-4-4h8"/></svg><span>Регистрация</span></a>
        </nav><?php endif; ?>
    </header>
    <?php require __DIR__ . '/menu.php'; ?>
    <main class="tr-main">
        <?php foreach (['error', 'success', 'warning', 'info'] as $tone): ?>
            <?php if ($message = session()->getFlash($tone)): ?>
                <p class="tr-notice tr-notice--<?= $tone ?>" role="status"><?= htmlSC((string)$message) ?></p>
            <?php endif; ?>
        <?php endforeach; ?>
