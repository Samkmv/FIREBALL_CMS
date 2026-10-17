<div class="tr-bootstrap tr-page-content" data-tr-page data-tr-shell data-bs-theme="light">
    <main class="tr-main <?= !empty($tr_wide_page)?'tr-main--wide':'' ?>" id="tr-main">
        <?php foreach (['error','success','warning','info'] as $tone): ?>
            <?php if ($message=session()->getFlash($tone)): ?><div class="alert alert-<?= $tone==='error'?'danger':$tone ?> tr-notice" role="status"><?= htmlSC((string)$message) ?></div><?php endif; ?>
        <?php endforeach; ?>
