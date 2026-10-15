<?php declare(strict_types=1); ?>
<!doctype html>
<html lang="<?= htmlSC(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f7f5f0">
    <?= get_csrf_meta() ?>
    <title><?= htmlSC($title ?? 'Tape Room') ?> · Tape Room · Бета</title>
    <?= $this->partial('pwa-head') ?>
    <?= $this->partial('shell-assets') ?>
</head>
<body class="tr-body">
<div class="tr-bootstrap tr-page-shell" data-tr-shell data-bs-theme="light">
    <div class="container-fluid tr-site-container">
        <?= $this->partial('navbar') ?>
        <main class="tr-main <?= !empty($tr_wide_page)?'tr-main--wide':'' ?>" id="tr-main">
            <?php foreach (['error','success','warning','info'] as $tone): ?>
                <?php if ($message=session()->getFlash($tone)): ?><div class="alert alert-<?= $tone==='error'?'danger':$tone ?> tr-notice" role="status"><?= htmlSC((string)$message) ?></div><?php endif; ?>
            <?php endforeach; ?>
