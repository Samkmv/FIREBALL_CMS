<?php

$payments = is_array($payments ?? null) ? $payments : [];
$rows = [];
$mobileCards = [];

foreach ($payments as $payment) {
    $amount = \Fireball\Subscriptions\Support\Money::display(
        (int)$payment['amount_minor'],
        (string)$payment['currency']
    );
    $status = '<span class="badge rounded-pill '
        . ($payment['status'] === 'paid' ? 'text-bg-success' : 'text-bg-secondary') . '">'
        . htmlSC((string)$payment['status'])
        . '</span>'
        . (!empty($payment['signature_verified'])
            ? '<div class="small text-success">' . htmlSC(FireballPluginSubscriptions::t('subscriptions_signature_verified')) . '</div>'
            : '');
    $user = htmlSC((string)$payment['user_name'])
        . '<div class="small text-body-secondary">' . htmlSC((string)$payment['user_email']) . '</div>';
    $date = htmlSC((string)$payment['created_at'])
        . (!empty($payment['paid_at']) ? '<br>' . htmlSC((string)$payment['paid_at']) : '');

    $rows[] = [
        'cells' => [
            ['html' => '#' . (int)$payment['invoice_id'] . '<div class="small text-body-secondary">' . htmlSC((string)$payment['provider']) . '</div>'],
            ['html' => $user],
            ['value' => (string)$payment['plan_name']],
            ['value' => $amount],
            ['html' => $status],
            ['html' => $date],
        ],
    ];

    $mobileCards[] = [
        'id' => '#' . (int)$payment['invoice_id'],
        'title' => (string)$payment['plan_name'],
        'icon' => 'ci-credit-card',
        'status' => [['html' => $status]],
        'extra_fields' => [
            ['label' => FireballPluginSubscriptions::t('subscriptions_user'), 'html' => $user],
            ['label' => FireballPluginSubscriptions::t('subscriptions_field_price'), 'value' => $amount],
            ['label' => FireballPluginSubscriptions::t('subscriptions_payment_gateway'), 'value' => (string)$payment['provider']],
            ['label' => FireballPluginSubscriptions::t('subscriptions_date'), 'html' => $date],
        ],
    ];
}
?>

<?php require __DIR__ . '/shell-open.php'; ?>
    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table>
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => [
                ['label' => FireballPluginSubscriptions::t('subscriptions_invoice')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_user')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_plan')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_field_price')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_field_status')],
                ['label' => FireballPluginSubscriptions::t('subscriptions_date')],
            ],
            'rows' => $rows,
            'mobile_cards' => $mobileCards,
            'empty_text' => FireballPluginSubscriptions::t('subscriptions_empty'),
        ]) ?>
    </div>
<?php require __DIR__ . '/shell-close.php'; ?>
