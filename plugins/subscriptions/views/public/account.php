<?php

$payments = is_array($payments ?? null) ? $payments : [];
$paymentRows = [];
$paymentCards = [];

foreach ($payments as $payment) {
    $amount = \Fireball\Subscriptions\Support\Money::display(
        (int)$payment['amount_minor'],
        (string)$payment['currency']
    );
    $status = '<span class="badge rounded-pill '
        . ($payment['status'] === 'paid' ? 'text-bg-success' : 'text-bg-secondary') . '">'
        . htmlSC((string)$payment['status'])
        . '</span>';

    $paymentRows[] = [
        'cells' => [
            ['value' => '#' . (int)$payment['invoice_id']],
            ['value' => (string)$payment['plan_name']],
            ['value' => $amount],
            ['html' => $status],
            ['value' => (string)$payment['created_at']],
        ],
    ];

    $paymentCards[] = [
        'id' => '#' . (int)$payment['invoice_id'],
        'title' => (string)$payment['plan_name'],
        'icon' => 'ci-credit-card',
        'status' => [['html' => $status]],
        'extra_fields' => [
            ['label' => FireballPluginSubscriptions::t('subscriptions_field_price'), 'value' => $amount],
            ['label' => FireballPluginSubscriptions::t('subscriptions_date'), 'value' => (string)$payment['created_at']],
        ],
    ];
}
?>

<section class="container py-5 subscriptions-public">
    <?php get_alerts(); ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><h1 class="h3 mb-0"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_account_title')) ?></h1><a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/subscriptions/plans') ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_view_plans')) ?></a></div>
    <?php if ($subscription): ?>
        <div class="border rounded-5 p-4 p-lg-5 mb-4">
            <div class="row g-4"><div class="col-md-6"><div class="text-body-secondary"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plan')) ?></div><div class="h3"><?= htmlSC((string)$subscription['plan_name']) ?></div></div><div class="col-md-3"><div class="text-body-secondary"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_status')) ?></div><strong><?= htmlSC((string)$subscription['status']) ?></strong></div><div class="col-md-3"><div class="text-body-secondary"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_ends_at')) ?></div><strong><?= htmlSC((string)$subscription['ends_at']) ?></strong></div></div>
            <hr>
            <ul class="list-unstyled row g-2"><?php foreach ($permissions as $key => $enabled): ?><?php if ($enabled): ?><li class="col-md-6"><i class="ci-check-circle text-success me-2"></i><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_permission_' . str_replace('.', '_', $key))) ?><?= is_int($enabled) ? ': ' . (int)$enabled : '' ?></li><?php endif; ?><?php endforeach; ?></ul>
            <div class="d-flex flex-wrap gap-2 mt-3"><a class="btn btn-dark rounded-pill" href="<?= base_href('/subscriptions/checkout/' . (int)$subscription['plan_id']) ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_renew')) ?></a><?php if (!empty($subscription['auto_renew'])): ?><form action="<?= base_href('/account/subscription/auto-renew') ?>" method="post"><?= get_csrf_field() ?><input type="hidden" name="enabled" value="0"><button class="btn btn-outline-secondary rounded-pill" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_disable_auto_renew')) ?></button></form><?php endif; ?><a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/profile/subscription-details') ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_profile_title')) ?></a></div>
        </div>
    <?php else: ?><div class="alert alert-info"><h2 class="h5"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_no_subscription_title')) ?></h2><p><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_no_subscription_message')) ?></p><a class="btn btn-dark rounded-pill" href="<?= base_href('/subscriptions/plans') ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_view_plans')) ?></a></div><?php endif; ?>

    <h2 class="h5 mt-5 mb-3"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_payment_history')) ?></h2>
    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table>
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => [
                ['label' => FireballPluginSubscriptions::t('subscriptions_invoice')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_plan')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_field_price')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_field_status')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_date')],
            ],
            'rows' => $paymentRows,
            'mobile_cards' => $paymentCards,
            'empty_text' => FireballPluginSubscriptions::t('subscriptions_empty'),
        ]) ?>
    </div>
</section>
