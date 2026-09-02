<?php
$payments = is_array($payments ?? null) ? $payments : [];
$profileFields = is_array($profile_fields ?? null) ? $profile_fields : [];
$renderPayerDetails = require __DIR__ . '/payer-details.php';
$rows = [];
$mobileCards = [];
$statusLabels = [
    'created' => FireballPluginSubscriptions::t('subscriptions_payment_status_created'),
    'pending' => FireballPluginSubscriptions::t('subscriptions_payment_status_pending'),
    'paid' => FireballPluginSubscriptions::t('subscriptions_payment_status_paid'),
    'failed' => FireballPluginSubscriptions::t('subscriptions_payment_status_failed'),
    'cancelled' => FireballPluginSubscriptions::t('subscriptions_payment_status_cancelled'),
];
$webhookStatusLabels = [
    'received' => FireballPluginSubscriptions::t('subscriptions_webhook_status_received'),
    'processing' => FireballPluginSubscriptions::t('subscriptions_webhook_status_processing'),
    'processed' => FireballPluginSubscriptions::t('subscriptions_webhook_status_processed'),
    'failed' => FireballPluginSubscriptions::t('subscriptions_webhook_status_failed'),
    'rejected' => FireballPluginSubscriptions::t('subscriptions_webhook_status_rejected'),
];
foreach ($payments as $payment) {
    $amount = \Fireball\Subscriptions\Support\Money::display((int)$payment['amount_minor'], (string)$payment['currency']);
    $statusKey = (string)($payment['status'] ?? '');
    $webhookStatusKey = (string)($payment['webhook_status'] ?? '');
    $webhookReceived = trim((string)($payment['webhook_created_at'] ?? '')) !== '';
    $webhookStateClass = $webhookStatusKey === 'processed' ? 'text-success' : (in_array($webhookStatusKey, ['failed', 'rejected'], true) ? 'text-danger' : 'text-warning');
    $webhookSummary = $webhookReceived
        ? '<div class="small ' . $webhookStateClass . '">ResultURL: ' . htmlSC($webhookStatusLabels[$webhookStatusKey] ?? $webhookStatusKey) . '</div>'
        : ($statusKey === 'pending' ? '<div class="small text-warning">' . htmlSC(FireballPluginSubscriptions::t('subscriptions_webhook_not_received')) . '</div>' : '');
    $status = '<span class="badge rounded-pill ' . ($statusKey === 'paid' ? 'text-bg-success' : 'text-bg-secondary') . '">' . htmlSC($statusLabels[$statusKey] ?? $statusKey) . '</span>' . (!empty($payment['signature_verified']) ? '<div class="small text-success">' . htmlSC(FireballPluginSubscriptions::t('subscriptions_signature_verified')) . '</div>' : '') . $webhookSummary;
    $user = htmlSC((string)$payment['user_name']) . '<div class="small text-body-secondary">' . htmlSC((string)$payment['user_email']) . '</div>';
    $date = htmlSC((string)$payment['created_at']) . (!empty($payment['paid_at']) ? '<br>' . htmlSC((string)$payment['paid_at']) : '');
    $detailsAttributes = [
        'data-bs-toggle="modal"',
        'data-bs-target="#subscriptionsPaymentDetailsModal"',
        'data-subscriptions-payment-details',
        'data-payment-id="' . (int)$payment['id'] . '"',
        'data-payment-user="' . htmlSC((string)$payment['user_name']) . '"',
    ];
    $detailsButton = '<button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="button" '
        . implode(' ', $detailsAttributes)
        . ' title="' . htmlSC(FireballPluginSubscriptions::t('subscriptions_view_details')) . '"'
        . ' aria-label="' . htmlSC(FireballPluginSubscriptions::t('subscriptions_view_details')) . '"><i class="ci-eye"></i></button>';
    $rows[] = ['cells' => [
        ['html' => '#' . (int)$payment['invoice_id'] . '<div class="small text-body-secondary">' . htmlSC((string)$payment['provider']) . '</div>'],
        ['html' => $user], ['value' => (string)$payment['plan_name']], ['value' => $amount], ['html' => $status], ['html' => $date], ['html' => $detailsButton],
    ]];
    $mobileCards[] = ['id' => '#' . (int)$payment['invoice_id'], 'title' => (string)$payment['plan_name'], 'icon' => 'ci-credit-card', 'status' => [['html' => $status]], 'actions' => [[
        'label' => FireballPluginSubscriptions::t('subscriptions_view_details'),
        'icon' => 'ci-eye',
        'type' => 'button',
        'attributes' => [
            'data-bs-toggle' => 'modal',
            'data-bs-target' => '#subscriptionsPaymentDetailsModal',
            'data-subscriptions-payment-details' => true,
            'data-payment-id' => (int)$payment['id'],
            'data-payment-user' => (string)$payment['user_name'],
        ],
    ]], 'extra_fields' => [
        ['label' => FireballPluginSubscriptions::t('subscriptions_user'), 'html' => $user], ['label' => FireballPluginSubscriptions::t('subscriptions_field_price'), 'value' => $amount],
        ['label' => FireballPluginSubscriptions::t('subscriptions_payment_gateway'), 'value' => (string)$payment['provider']], ['label' => FireballPluginSubscriptions::t('subscriptions_date'), 'html' => $date],
    ]];
}
?>

<?php require __DIR__ . '/shell-open.php'; ?>
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-3 subscriptions-table-toolbar">
        <form class="d-flex gap-2 subscriptions-table-search" method="get"><input class="form-control" type="search" name="q" value="<?= htmlSC((string)($search ?? '')) ?>" placeholder="<?= htmlSC(FireballPluginSubscriptions::t('subscriptions_search')) ?>"><button class="btn btn-outline-secondary rounded-pill" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_apply')) ?></button></form>
        <?php if ($payments): ?><form method="post" action="<?= base_href('/admin/subscriptions/payments/clear') ?>" data-admin-delete-form data-delete-message="<?= htmlSC(FireballPluginSubscriptions::t('subscriptions_payments_clear_confirm')) ?>" data-delete-confirm-label="<?= htmlSC(FireballPluginSubscriptions::t('subscriptions_payments_clear')) ?>"><?= get_csrf_field() ?><button class="btn btn-outline-danger rounded-pill" type="submit"><i class="ci-trash me-2"></i><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_payments_clear')) ?></button></form><?php endif; ?>
    </div>
    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table>
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => array_map(static fn(string $label): array => ['label' => $label], [FireballPluginSubscriptions::t('subscriptions_invoice'), FireballPluginSubscriptions::t('subscriptions_user'), FireballPluginSubscriptions::t('subscriptions_plan'), FireballPluginSubscriptions::t('subscriptions_field_price'), FireballPluginSubscriptions::t('subscriptions_field_status'), FireballPluginSubscriptions::t('subscriptions_date'), FireballPluginSubscriptions::t('subscriptions_actions')]),
            'rows' => $rows, 'mobile_cards' => $mobileCards, 'empty_text' => FireballPluginSubscriptions::t('subscriptions_empty'),
        ]) ?>
        <?= view()->renderPartial('admin/partials/table_footer', ['visible' => count($payments), 'total' => (int)($total ?? 0), 'pagination' => $pagination ?? null, 'show_results_label' => false]) ?>
    </div>

    <?php foreach ($payments as $payment): ?>
        <?php
        $paymentStatusKey = (string)($payment['status'] ?? '');
        $payerSnapshot = is_array($payment['payer_snapshot'] ?? null) ? $payment['payer_snapshot'] : [];
        $consents = is_array($payment['consents'] ?? null) ? $payment['consents'] : [];
        ?>
        <template data-subscriptions-payment-details-template="<?= (int)$payment['id'] ?>">
            <section class="subscriptions-details-section">
                <h3 class="h6 mb-3"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_payment_details')) ?></h3>
                <div class="subscriptions-payer-grid">
                    <div class="subscriptions-payer-field"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_payment')) ?></span><span class="subscriptions-payer-field__value">#<?= (int)$payment['id'] ?></span></div>
                    <div class="subscriptions-payer-field"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_order')) ?></span><span class="subscriptions-payer-field__value">#<?= (int)$payment['order_id'] ?></span></div>
                    <div class="subscriptions-payer-field"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_invoice')) ?></span><span class="subscriptions-payer-field__value">#<?= (int)$payment['invoice_id'] ?></span></div>
                    <div class="subscriptions-payer-field"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_user_id')) ?></span><span class="subscriptions-payer-field__value">#<?= (int)$payment['user_id'] ?></span></div>
                    <div class="subscriptions-payer-field"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_plan')) ?></span><span class="subscriptions-payer-field__value"><?= htmlSC((string)$payment['plan_name']) ?></span></div>
                    <div class="subscriptions-payer-field"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_price')) ?></span><span class="subscriptions-payer-field__value"><?= htmlSC(\Fireball\Subscriptions\Support\Money::display((int)$payment['amount_minor'], (string)$payment['currency'])) ?></span></div>
                    <div class="subscriptions-payer-field"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_status')) ?></span><span class="subscriptions-payer-field__value"><?= htmlSC($statusLabels[$paymentStatusKey] ?? $paymentStatusKey) ?></span></div>
                    <div class="subscriptions-payer-field"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_payment_gateway')) ?></span><span class="subscriptions-payer-field__value"><?= htmlSC((string)$payment['provider']) ?></span></div>
                    <div class="subscriptions-payer-field"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_payment_type')) ?></span><span class="subscriptions-payer-field__value"><?= htmlSC((string)$payment['payment_type']) ?></span></div>
                    <div class="subscriptions-payer-field"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_created_at')) ?></span><span class="subscriptions-payer-field__value"><?= htmlSC((string)$payment['created_at']) ?></span></div>
                    <div class="subscriptions-payer-field"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_paid_at')) ?></span><span class="subscriptions-payer-field__value"><?= trim((string)($payment['paid_at'] ?? '')) !== '' ? htmlSC((string)$payment['paid_at']) : '<span class="text-body-secondary">—</span>' ?></span></div>
                    <div class="subscriptions-payer-field"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_transaction')) ?></span><span class="subscriptions-payer-field__value"><?= trim((string)($payment['provider_transaction'] ?? '')) !== '' ? htmlSC((string)$payment['provider_transaction']) : '<span class="text-body-secondary">—</span>' ?></span></div>
                    <?php if (trim((string)($payment['error_message'] ?? '')) !== ''): ?><div class="subscriptions-payer-field subscriptions-payer-field--wide"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_payment_error')) ?></span><span class="subscriptions-payer-field__value text-danger"><?= nl2br(htmlSC((string)$payment['error_message'])) ?></span></div><?php endif; ?>
                </div>
            </section>
            <?php if (trim((string)($payment['webhook_created_at'] ?? '')) !== ''): ?>
                <?php $webhookStatusKey = (string)($payment['webhook_status'] ?? ''); ?>
                <section class="subscriptions-details-section">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <h3 class="h6 mb-0"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_webhook_details')) ?></h3>
                        <?php if ($paymentStatusKey !== 'paid' && $webhookStatusKey === 'failed' && !empty($payment['webhook_signature_verified'])): ?>
                            <form method="post" action="<?= htmlSC(base_href('/admin/subscriptions/payments/retry-webhook')) ?>">
                                <?= get_csrf_field() ?>
                                <input type="hidden" name="payment_id" value="<?= (int)$payment['id'] ?>">
                                <button class="btn btn-sm btn-outline-primary rounded-pill" type="submit"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_webhook_retry')) ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="subscriptions-payer-grid">
                        <div class="subscriptions-payer-field"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_field_status')) ?></span><span class="subscriptions-payer-field__value"><?= htmlSC($webhookStatusLabels[$webhookStatusKey] ?? $webhookStatusKey) ?></span></div>
                        <div class="subscriptions-payer-field"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_signature_verified')) ?></span><span class="subscriptions-payer-field__value"><?= htmlSC(FireballPluginSubscriptions::t(!empty($payment['webhook_signature_verified']) ? 'subscriptions_value_yes' : 'subscriptions_value_no')) ?></span></div>
                        <div class="subscriptions-payer-field"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_webhook_received_at')) ?></span><span class="subscriptions-payer-field__value"><?= htmlSC((string)$payment['webhook_created_at']) ?></span></div>
                        <div class="subscriptions-payer-field"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_webhook_processed_at')) ?></span><span class="subscriptions-payer-field__value"><?= trim((string)($payment['webhook_processed_at'] ?? '')) !== '' ? htmlSC((string)$payment['webhook_processed_at']) : '<span class="text-body-secondary">—</span>' ?></span></div>
                        <?php if (trim((string)($payment['webhook_error_message'] ?? '')) !== ''): ?><div class="subscriptions-payer-field subscriptions-payer-field--wide"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_payment_error')) ?></span><span class="subscriptions-payer-field__value text-danger"><?= nl2br(htmlSC((string)$payment['webhook_error_message'])) ?></span></div><?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
            <?php if ($consents !== []): ?>
                <section class="subscriptions-details-section">
                    <h3 class="h6 mb-3"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_consent_details')) ?></h3>
                    <div class="subscriptions-payer-grid">
                        <?php foreach (['offer', 'privacy', 'recurring', 'auto_renew'] as $consentKey): ?>
                            <div class="subscriptions-payer-field"><span class="subscriptions-payer-field__label"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_consent_' . $consentKey)) ?></span><span class="subscriptions-payer-field__value"><?= htmlSC(FireballPluginSubscriptions::t(!empty($consents[$consentKey]) ? 'subscriptions_value_yes' : 'subscriptions_value_no')) ?></span></div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            <section class="subscriptions-details-section">
                <h3 class="h6 mb-1"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_payer_details')) ?></h3>
                <p class="small text-body-secondary mb-3"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_payer_snapshot_hint')) ?></p>
                <?= $renderPayerDetails($payerSnapshot, $profileFields) ?>
            </section>
        </template>
    <?php endforeach; ?>

    <div class="modal fade" id="subscriptionsPaymentDetailsModal" tabindex="-1" aria-labelledby="subscriptionsPaymentDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content rounded-5">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h2 class="modal-title h5 mb-1" id="subscriptionsPaymentDetailsModalLabel"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_details')) ?></h2>
                        <div class="small text-body-secondary" data-subscriptions-payment-details-user></div>
                    </div>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="<?= htmlSC(FireballPluginSubscriptions::t('subscriptions_cancel')) ?>"></button>
                </div>
                <div class="modal-body subscriptions-details-modal-body" data-subscriptions-payment-details-body></div>
                <div class="modal-footer border-0 pt-0">
                    <button class="btn btn-dark rounded-pill" type="button" data-bs-dismiss="modal"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_close')) ?></button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const modal = document.getElementById('subscriptionsPaymentDetailsModal');
            modal?.addEventListener('show.bs.modal', (event) => {
                const trigger = event.relatedTarget;
                if (!(trigger instanceof HTMLElement) || !trigger.matches('[data-subscriptions-payment-details]')) {
                    return;
                }
                const template = document.querySelector(`[data-subscriptions-payment-details-template="${CSS.escape(trigger.dataset.paymentId || '')}"]`);
                const body = modal.querySelector('[data-subscriptions-payment-details-body]');
                if (template instanceof HTMLTemplateElement && body) {
                    body.replaceChildren(template.content.cloneNode(true));
                }
                const user = modal.querySelector('[data-subscriptions-payment-details-user]');
                if (user) {
                    user.textContent = trigger.dataset.paymentUser || '';
                }
            });
        })();
    </script>
<?php require __DIR__ . '/shell-close.php'; ?>
