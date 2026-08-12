<?php
$payments = is_array($payments ?? null) ? $payments : [];
$rows = [];
$mobileCards = [];
$statusLabels = [
    'created' => FireballPluginSubscriptions::t('subscriptions_payment_status_created'),
    'pending' => FireballPluginSubscriptions::t('subscriptions_payment_status_pending'),
    'paid' => FireballPluginSubscriptions::t('subscriptions_payment_status_paid'),
    'failed' => FireballPluginSubscriptions::t('subscriptions_payment_status_failed'),
    'cancelled' => FireballPluginSubscriptions::t('subscriptions_payment_status_cancelled'),
];
foreach ($payments as $payment) {
    $amount = \Fireball\Subscriptions\Support\Money::display((int)$payment['amount_minor'], (string)$payment['currency']);
    $statusKey = (string)($payment['status'] ?? '');
    $status = '<span class="badge rounded-pill ' . ($statusKey === 'paid' ? 'text-bg-success' : 'text-bg-secondary') . '">' . htmlSC($statusLabels[$statusKey] ?? $statusKey) . '</span>' . (!empty($payment['signature_verified']) ? '<div class="small text-success">' . htmlSC(FireballPluginSubscriptions::t('subscriptions_signature_verified')) . '</div>' : '');
    $user = htmlSC((string)$payment['user_name']) . '<div class="small text-body-secondary">' . htmlSC((string)$payment['user_email']) . '</div>';
    $date = htmlSC((string)$payment['created_at']) . (!empty($payment['paid_at']) ? '<br>' . htmlSC((string)$payment['paid_at']) : '');
    $rows[] = ['cells' => [
        ['html' => '#' . (int)$payment['invoice_id'] . '<div class="small text-body-secondary">' . htmlSC((string)$payment['provider']) . '</div>'],
        ['html' => $user], ['value' => (string)$payment['plan_name']], ['value' => $amount], ['html' => $status], ['html' => $date],
    ]];
    $mobileCards[] = ['id' => '#' . (int)$payment['invoice_id'], 'title' => (string)$payment['plan_name'], 'icon' => 'ci-credit-card', 'status' => [['html' => $status]], 'extra_fields' => [
        ['label' => FireballPluginSubscriptions::t('subscriptions_user'), 'html' => $user], ['label' => FireballPluginSubscriptions::t('subscriptions_field_price'), 'value' => $amount],
        ['label' => FireballPluginSubscriptions::t('subscriptions_payment_gateway'), 'value' => (string)$payment['provider']], ['label' => FireballPluginSubscriptions::t('subscriptions_date'), 'html' => $date],
    ]];
}
?>

<?php require __DIR__ . '/shell-open.php'; ?>
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
        <form class="d-flex gap-2" method="get"><input class="form-control" type="search" name="q" value="<?= htmlSC((string)($search ?? '')) ?>" placeholder="<?= htmlSC(FireballPluginSubscriptions::t('subscriptions_search')) ?>"><button class="btn btn-outline-secondary rounded-pill" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_apply')) ?></button></form>
        <?php if ($payments): ?><form method="post" action="<?= base_href('/admin/subscriptions/payments/clear') ?>" data-admin-delete-form data-delete-message="<?= htmlSC(FireballPluginSubscriptions::t('subscriptions_payments_clear_confirm')) ?>" data-delete-confirm-label="<?= htmlSC(FireballPluginSubscriptions::t('subscriptions_payments_clear')) ?>"><?= get_csrf_field() ?><button class="btn btn-outline-danger rounded-pill" type="submit"><i class="ci-trash me-2"></i><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_payments_clear')) ?></button></form><?php endif; ?>
    </div>
    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table>
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => array_map(static fn(string $label): array => ['label' => $label], [FireballPluginSubscriptions::t('subscriptions_invoice'), FireballPluginSubscriptions::t('subscriptions_user'), FireballPluginSubscriptions::t('subscriptions_plan'), FireballPluginSubscriptions::t('subscriptions_field_price'), FireballPluginSubscriptions::t('subscriptions_field_status'), FireballPluginSubscriptions::t('subscriptions_date')]),
            'rows' => $rows, 'mobile_cards' => $mobileCards, 'empty_text' => FireballPluginSubscriptions::t('subscriptions_empty'),
        ]) ?>
        <?= view()->renderPartial('admin/partials/table_footer', ['visible' => count($payments), 'total' => (int)($total ?? 0), 'pagination' => $pagination ?? null, 'show_results_label' => false]) ?>
    </div>
<?php require __DIR__ . '/shell-close.php'; ?>
