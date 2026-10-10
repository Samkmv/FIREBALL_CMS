<?php $settings = $rentalSettings; ?>
<section class="container py-4 py-md-5 toy-rental-profile">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-2"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_dashboard_title')) ?></h1>
            <p class="text-body-secondary mb-0"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_dashboard_subtitle')) ?></p>
        </div>
        <a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="<?= base_href('/profile') ?>">
            <i class="ci-user" aria-hidden="true"></i><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_back_profile')) ?>
        </a>
    </div>
    <?php require __DIR__ . '/operator-panel.php'; ?>
</section>
