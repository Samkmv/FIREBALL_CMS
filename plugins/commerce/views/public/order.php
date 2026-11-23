<?php require __DIR__ . '/open.php'; ?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="border rounded-4 p-3 p-lg-4 mb-4">
            <h1 class="h2"><?= htmlSC($title) ?></h1>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge text-bg-dark"><?= $t('status_' . $order['status']) ?></span>
                <span class="badge text-bg-secondary"><?= $t('payment_' . $order['payment_status']) ?></span>
            </div>
            <p class="mb-1"><strong><?= $t($order['fulfillment']) ?></strong></p>
            <p class="text-body-secondary mb-0 commerce-copy"><?= htmlSC($order['fulfillment_address'] ?: FireballPluginCommerce::t('pickup_address_pending')) ?></p>
        </div>
        <div class="border rounded-4 p-3 p-lg-4 mb-4">
            <h2 class="h5"><?= $t('summary') ?></h2>
            <?php require __DIR__ . '/../order-items.php'; ?>
        </div>
        <a class="btn btn-outline-secondary" href="<?= $url('/shop') ?>"><?= $t('continue_shopping') ?></a>
    </div>
</div>
</div>
