<main class="container d-flex align-items-center justify-content-center py-5" style="min-height: 60vh;">
    <div class="text-center" style="max-width: 520px;">
        <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-body-tertiary mb-4" style="width: 72px; height: 72px;">
            <i class="ci-wifi-off fs-2 text-body-secondary"></i>
        </div>
        <h1 class="h3 mb-3"><?= print_translation('pwa_offline_title') ?></h1>
        <p class="text-body-secondary mb-4"><?= print_translation('pwa_offline_text') ?></p>
        <a class="btn btn-dark rounded-pill" href="<?= base_href('/') ?>">
            <i class="ci-refresh-cw me-2"></i><?= print_translation('pwa_offline_retry') ?>
        </a>
    </div>
</main>
