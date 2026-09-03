<?php

$payments = is_array($payments ?? null) ? $payments : [];
$paymentRows = [];
$paymentCards = [];
$subscriptionStatusLabels = [
    'active' => FireballPluginSubscriptions::t('subscriptions_subscription_status_active'),
    'disabled' => FireballPluginSubscriptions::t('subscriptions_subscription_status_disabled'),
    'pending' => FireballPluginSubscriptions::t('subscriptions_status_pending'),
    'cancelled' => FireballPluginSubscriptions::t('subscriptions_status_cancelled'),
    'grace_period' => FireballPluginSubscriptions::t('subscriptions_status_grace_period'),
    'past_due' => FireballPluginSubscriptions::t('subscriptions_status_past_due'),
    'expired' => FireballPluginSubscriptions::t('subscriptions_status_expired'),
];
$paymentStatusLabels = [
    'created' => FireballPluginSubscriptions::t('subscriptions_payment_status_created'),
    'pending' => FireballPluginSubscriptions::t('subscriptions_payment_status_pending'),
    'paid' => FireballPluginSubscriptions::t('subscriptions_payment_status_paid'),
    'failed' => FireballPluginSubscriptions::t('subscriptions_payment_status_failed'),
    'cancelled' => FireballPluginSubscriptions::t('subscriptions_payment_status_cancelled'),
];
$statusClasses = [
    'active' => 'text-success bg-success-subtle',
    'paid' => 'text-success bg-success-subtle',
    'created' => 'text-info bg-info-subtle',
    'pending' => 'text-warning bg-warning-subtle',
    'grace_period' => 'text-warning bg-warning-subtle',
    'past_due' => 'text-danger bg-danger-subtle',
    'failed' => 'text-danger bg-danger-subtle',
    'disabled' => 'text-secondary bg-secondary-subtle',
    'cancelled' => 'text-secondary bg-secondary-subtle',
    'expired' => 'text-secondary bg-secondary-subtle',
];
$formatDateTime = static function (mixed $value, bool $withTime = true): string {
    $timestamp = strtotime((string)$value);

    return $timestamp === false ? (string)$value : date($withTime ? 'd.m.Y H:i' : 'd.m.Y', $timestamp);
};

foreach ($payments as $payment) {
    $amount = \Fireball\Subscriptions\Support\Money::display(
        (int)$payment['amount_minor'],
        (string)$payment['currency']
    );
    $statusKey = (string)($payment['status'] ?? '');
    $status = '<span class="badge rounded-pill '
        . htmlSC($statusClasses[$statusKey] ?? 'text-secondary bg-secondary-subtle') . '">'
        . htmlSC($paymentStatusLabels[$statusKey] ?? FireballPluginSubscriptions::t('subscriptions_status_unknown'))
        . '</span>';
    $createdAt = $formatDateTime($payment['created_at'] ?? '');

    $paymentRows[] = [
        'cells' => [
            ['value' => '#' . (int)$payment['invoice_id']],
            ['value' => (string)$payment['plan_name']],
            ['value' => $amount],
            ['html' => $status],
            ['value' => $createdAt],
        ],
    ];

    $paymentCards[] = [
        'id' => '#' . (int)$payment['invoice_id'],
        'title' => (string)$payment['plan_name'],
        'icon' => 'ci-credit-card',
        'status' => [['html' => $status]],
        'extra_fields' => [
            ['label' => FireballPluginSubscriptions::t('subscriptions_field_price'), 'value' => $amount],
            ['label' => FireballPluginSubscriptions::t('subscriptions_date'), 'value' => $createdAt],
        ],
    ];
}
?>

<section class="container py-5 subscriptions-public">
    <?php get_alerts(); ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><h1 class="h3 mb-0"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_account_title')) ?></h1><a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/subscriptions/plans') ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_view_plans')) ?></a></div>
    <?php if ($subscription): ?>
        <?php
        $subscriptionStatus = (string)($subscription['status'] ?? '');
        $subscriptionStatusLabel = $subscriptionStatusLabels[$subscriptionStatus] ?? FireballPluginSubscriptions::t('subscriptions_status_unknown');
        $isUtilityManaged = !empty($subscription['utility_managed']);
        ?>
        <article class="subscriptions-account-card border rounded-5 p-4 p-lg-5 mb-5 overflow-hidden">
            <?php if ($isUtilityManaged): ?><div class="alert alert-success rounded-4 mb-4" role="status"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_address_included_in_utilities')) ?></div><?php endif; ?>
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="subscriptions-account-card__icon d-inline-flex align-items-center justify-content-center rounded-circle"><i class="ci-award"></i></span>
                    <div>
                        <div class="small text-body-secondary mb-1"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plan')) ?></div>
                        <h2 class="h3 mb-0"><?= htmlSC((string)$subscription['plan_name']) ?></h2>
                    </div>
                </div>
                <span class="badge rounded-pill px-3 py-2 <?= htmlSC($statusClasses[$subscriptionStatus] ?? 'text-secondary bg-secondary-subtle') ?>"><?= htmlSC($subscriptionStatusLabel) ?></span>
            </div>

            <div class="row g-3 my-4">
                <div class="col-sm-6">
                    <div class="subscriptions-account-card__meta h-100 rounded-4 p-3">
                        <div class="small text-body-secondary mb-1"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_ends_at')) ?></div>
                        <div class="fw-semibold fs-5"><?= htmlSC($isUtilityManaged || empty($subscription['ends_at']) ? FireballPluginSubscriptions::t('subscriptions_indefinite') : $formatDateTime($subscription['ends_at'], false)) ?></div>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="subscriptions-account-card__meta h-100 rounded-4 p-3">
                        <div class="small text-body-secondary mb-1"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_auto_renew')) ?></div>
                        <div class="fw-semibold fs-5"><?= htmlSC(FireballPluginSubscriptions::t($isUtilityManaged ? 'subscriptions_auto_renew_not_required' : (!empty($subscription['auto_renew']) ? 'subscriptions_auto_renew_enabled' : 'subscriptions_auto_renew_disabled'))) ?></div>
                    </div>
                </div>
            </div>

            <div class="border-top pt-4">
                <h3 class="h6 mb-3"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_access_included')) ?></h3>
                <ul class="subscriptions-account-card__permissions list-unstyled row g-2 mb-0">
                    <?php foreach ($permissions as $key => $enabled): ?>
                        <?php if ($enabled): ?>
                            <li class="col-md-6">
                                <div class="d-flex align-items-center gap-2 rounded-4 p-3 h-100">
                                    <i class="ci-check-circle text-success flex-shrink-0"></i>
                                    <span><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_permission_' . str_replace('.', '_', $key))) ?><?= is_int($enabled) ? ': ' . (int)$enabled : '' ?></span>
                                </div>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-4">
                <?php if (!$isUtilityManaged): ?><a class="btn btn-dark rounded-pill" href="<?= base_href('/subscriptions/checkout/' . (int)$subscription['plan_id']) ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_renew')) ?></a><?php endif; ?>
                <?php if (!$isUtilityManaged && !empty($subscription['auto_renew'])): ?><form action="<?= base_href('/account/subscription/auto-renew') ?>" method="post"><?= get_csrf_field() ?><input type="hidden" name="enabled" value="0"><button class="btn btn-outline-secondary rounded-pill" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_disable_auto_renew')) ?></button></form><?php endif; ?>
                <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/profile/subscription-details') ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_profile_title')) ?></a>
            </div>
        </article>
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
        <?= view()->renderPartial('admin/partials/table_footer', [
            'visible' => count($paymentRows),
            'total' => (int)($payments_total ?? count($paymentRows)),
            'pagination' => $payments_pagination ?? null,
        ]) ?>
    </div>
</section>
