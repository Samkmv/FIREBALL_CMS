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
    <header class="tr-header"><a class="rp-brand" href="<?= htmlSC(base_href('/')) ?>">TAPE ROOM<span>ЛИЧНАЯ МУЗЫКАЛЬНАЯ КОМНАТА</span></a></header>
    <?php require __DIR__ . '/menu.php'; ?>
    <main class="tr-main">
        <?php foreach (['error', 'success', 'warning', 'info'] as $tone): ?>
            <?php if ($message = session()->getFlash($tone)): ?>
                <p class="tr-notice tr-notice--<?= $tone ?>" role="status"><?= htmlSC((string)$message) ?></p>
            <?php endif; ?>
        <?php endforeach; ?>
