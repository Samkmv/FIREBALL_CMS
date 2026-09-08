<?php

$payments = is_array($payments ?? null) ? $payments : [];
$permissions = is_array($permissions ?? null) ? $permissions : [];
$includedPermissions = array_filter($permissions);
$permissionIcons = [
    'posts.view_paid' => 'ci-file-text',
    'videos.view_paid' => 'ci-play-circle',
    'camera_archive.view' => 'ci-camera',
    'camera_archive.download' => 'ci-download',
    'camera_archive.max_days' => 'ci-calendar',
    'camera_archive.max_fragment_minutes' => 'ci-clock',
];
$isUtilityManaged = !empty($subscription['utility_managed']);
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
        . htmlSC($statusKey === 'failed' && ($payment['error_message'] ?? '') === \Fireball\Subscriptions\Services\PaymentService::TIMEOUT_ERROR
            ? FireballPluginSubscriptions::t('subscriptions_payment_status_timeout')
            : ($paymentStatusLabels[$statusKey] ?? FireballPluginSubscriptions::t('subscriptions_status_unknown')))
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

<section class="container py-5 subscriptions-public subscriptions-account-page">
    <?php get_alerts(); ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><h1 class="h3 mb-0"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_account_title')) ?></h1><?php if (!$isUtilityManaged): ?><a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/subscriptions/plans') ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_view_plans')) ?></a><?php endif; ?></div>
    <?php if ($subscription): ?>
        <?php
        $subscriptionStatus = (string)($subscription['status'] ?? '');
        $subscriptionStatusLabel = $subscriptionStatusLabels[$subscriptionStatus] ?? FireballPluginSubscriptions::t('subscriptions_status_unknown');
        $isGracePeriod = $subscriptionStatus === 'grace_period';
        $accessEndsAt = $isGracePeriod ? ($subscription['grace_ends_at'] ?? $subscription['ends_at']) : $subscription['ends_at'];
        $autoRenew = !$isUtilityManaged && !empty($subscription['auto_renew']);
        ?>
        <article class="subscriptions-account-card <?= $isGracePeriod ? 'subscriptions-account-card--grace' : '' ?> mb-5" aria-labelledby="subscription-plan-title">
            <header class="subscriptions-account-card__header">
                <div class="subscriptions-account-card__identity">
                    <span class="subscriptions-account-card__icon"><i class="<?= $isUtilityManaged ? 'ci-home' : 'ci-award' ?>" aria-hidden="true"></i></span>
                    <div class="subscriptions-account-card__title">
                        <div class="subscriptions-account-card__eyebrow"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plan')) ?></div>
                        <h2 id="subscription-plan-title"><?= htmlSC((string)$subscription['plan_name']) ?></h2>
                    </div>
                </div>
                <span class="subscriptions-account-card__status badge rounded-pill <?= htmlSC($statusClasses[$subscriptionStatus] ?? 'text-secondary bg-secondary-subtle') ?>"><i class="<?= $isGracePeriod ? 'ci-clock' : 'ci-check-circle' ?>" aria-hidden="true"></i><?= htmlSC($subscriptionStatusLabel) ?></span>
                <p class="subscriptions-account-card__intro mb-0"><?= htmlSC(FireballPluginSubscriptions::t($isUtilityManaged ? 'subscriptions_address_included_in_utilities' : 'subscriptions_account_access_ready')) ?></p>
            </header>

            <div class="subscriptions-account-card__layout <?= $includedPermissions === [] ? 'subscriptions-account-card__layout--single' : '' ?>">
                <div class="subscriptions-account-card__summary">
                    <dl class="subscriptions-account-card__facts mb-0">
                        <div class="subscriptions-account-card__meta">
                            <dt><i class="ci-calendar" aria-hidden="true"></i><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_ends_at')) ?></dt>
                            <dd class="subscriptions-account-card__date"><?= htmlSC($isUtilityManaged || empty($accessEndsAt) ? FireballPluginSubscriptions::t('subscriptions_indefinite') : $formatDateTime($accessEndsAt, false)) ?></dd>
                        </div>
                        <div class="subscriptions-account-card__meta">
                            <dt><i class="ci-repeat" aria-hidden="true"></i><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_auto_renew')) ?></dt>
                            <dd><?= htmlSC(FireballPluginSubscriptions::t($isUtilityManaged ? 'subscriptions_auto_renew_not_required' : ($autoRenew ? 'subscriptions_auto_renew_enabled' : 'subscriptions_auto_renew_disabled'))) ?></dd>
                        </div>
                    </dl>
                    <?php if (!$isUtilityManaged): ?>
                        <p class="subscriptions-account-card__billing-note mb-0"><i class="<?= $autoRenew ? 'ci-check-shield' : 'ci-clock' ?>" aria-hidden="true"></i><span><?= htmlSC($autoRenew
                            ? (!empty($subscription['next_billing_at']) ? str_replace(':date', $formatDateTime($subscription['next_billing_at'], false), FireballPluginSubscriptions::t('subscriptions_account_next_payment')) : FireballPluginSubscriptions::t('subscriptions_account_renewal_scheduled'))
                            : (!empty($subscription['cancelled_at'])
                                ? (!empty($accessEndsAt) ? str_replace(':date', $formatDateTime($accessEndsAt, false), FireballPluginSubscriptions::t('subscriptions_auto_renew_cancelled_until')) : FireballPluginSubscriptions::t('subscriptions_auto_renew_cancelled_access_retained'))
                                : FireballPluginSubscriptions::t('subscriptions_account_no_auto_charge'))) ?></span></p>
                        <?php if ($autoRenew): ?><p class="small text-body-secondary mt-2 mb-0"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_auto_renew_cancel_hint')) ?></p><?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ($includedPermissions !== []): ?>
                    <section class="subscriptions-account-card__features" aria-labelledby="subscription-features-title">
                        <h3 class="h6 mb-3" id="subscription-features-title"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_access_included')) ?></h3>
                        <ul class="subscriptions-account-card__permissions list-unstyled mb-0">
                            <?php foreach ($includedPermissions as $key => $enabled): ?>
                            <li>
                                <span class="subscriptions-account-card__feature-icon"><i class="<?= htmlSC($permissionIcons[$key] ?? 'ci-check-circle') ?>" aria-hidden="true"></i></span>
                                <span class="subscriptions-account-card__feature-label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_permission_' . str_replace('.', '_', $key))) ?><?= is_int($enabled) ? ': ' . (int)$enabled : '' ?></span>
                                <i class="ci-check subscriptions-account-card__feature-check" aria-hidden="true"></i>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>
            </div>

            <footer class="subscriptions-account-card__actions">
                <?php if (!$isUtilityManaged): ?><a class="btn btn-dark rounded-pill subscriptions-account-card__renew" href="<?= base_href('/subscriptions/checkout/' . (int)$subscription['plan_id']) ?>"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_renew')) ?><i class="ci-arrow-right ms-2" aria-hidden="true"></i></a><?php endif; ?>
                <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/profile/subscription-details') ?>"><i class="ci-user me-2" aria-hidden="true"></i><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_profile_title')) ?></a>
                <?php if ($autoRenew): ?><form action="<?= base_href('/account/subscription/auto-renew') ?>" method="post"><?= get_csrf_field() ?><input type="hidden" name="enabled" value="0"><button class="btn btn-outline-secondary rounded-pill" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_disable_auto_renew')) ?></button></form><?php endif; ?>
            </footer>
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
