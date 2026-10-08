<?php
$currency = (string)($settings['currency'] ?? '₽');
$paymentMethods = [
    '' => FireballPluginToyCarRental::t('toy_rental_filter_all_payment_methods'),
];
foreach (FireballPluginToyCarRental::PAYMENT_METHODS as $method) {
    $paymentMethods[$method] = FireballPluginToyCarRental::paymentMethodLabel($method);
}
$paymentStatuses = [
    '' => FireballPluginToyCarRental::t('toy_rental_filter_all_payment_statuses'),
    'unpaid' => FireballPluginToyCarRental::paymentStatusLabel('unpaid'),
    'paid' => FireballPluginToyCarRental::paymentStatusLabel('paid'),
    'refunded' => FireballPluginToyCarRental::paymentStatusLabel('refunded'),
];
$billingTypes = [
    '' => FireballPluginToyCarRental::t('toy_rental_filter_all_billing_types'),
    'fixed' => FireballPluginToyCarRental::t('toy_rental_filter_fixed'),
    'metered' => FireballPluginToyCarRental::t('toy_rental_filter_metered'),
];
$rideStatuses = [
    '' => FireballPluginToyCarRental::t('toy_rental_filter_all_ride_statuses'),
    'active' => FireballPluginToyCarRental::t('toy_rental_filter_active'),
    'completed' => FireballPluginToyCarRental::t('toy_rental_filter_completed'),
    'overdue' => FireballPluginToyCarRental::t('toy_rental_filter_overdue'),
    'cancelled' => FireballPluginToyCarRental::t('toy_rental_filter_cancelled'),
];
$returnTo = current_url_with_query();
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => FireballPluginToyCarRental::t('toy_rental_history_title'),
    'subtitle' => FireballPluginToyCarRental::t('toy_rental_history_subtitle'),
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <form class="fb-card p-3 p-md-4 mb-4" method="get" action="<?= base_href('/admin/toy-rental/rides') ?>">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_filter_period')) ?></label>
                <select class="form-select" name="date_filter">
                    <?php foreach (['today' => FireballPluginToyCarRental::t('toy_rental_filter_today'), 'yesterday' => FireballPluginToyCarRental::t('toy_rental_filter_yesterday'), 'period' => FireballPluginToyCarRental::t('toy_rental_filter_custom_period'), 'all' => FireballPluginToyCarRental::t('toy_rental_filter_all')] as $key => $label): ?>
                        <option value="<?= $key ?>" <?= (string)$filters['date_filter'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_filter_from')) ?></label>
                <input class="form-control" type="date" name="date_from" value="<?= htmlSC((string)$filters['date_from']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_filter_to')) ?></label>
                <input class="form-control" type="date" name="date_to" value="<?= htmlSC((string)$filters['date_to']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_car')) ?></label>
                <select class="form-select" name="car_id">
                    <option value="0"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_filter_all_cars')) ?></option>
                    <?php foreach ($cars as $car): ?>
                        <option value="<?= (int)$car['id'] ?>" <?= (int)$filters['car_id'] === (int)$car['id'] ? 'selected' : '' ?>><?= htmlSC((string)$car['name'] . ' #' . (string)$car['number'] . (!empty($car['deleted_at']) ? ' (' . FireballPluginToyCarRental::t('toy_rental_deleted') . ')' : '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100" type="submit"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_filter_show')) ?></button>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="billing_type" aria-label="<?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_type')) ?>">
                    <?php foreach ($billingTypes as $key => $label): ?>
                        <option value="<?= $key ?>" <?= (string)$filters['billing_type'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="ride_status" aria-label="<?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_status')) ?>">
                    <?php foreach ($rideStatuses as $key => $label): ?>
                        <option value="<?= $key ?>" <?= (string)$filters['ride_status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="payment_method" aria-label="<?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_payment_method')) ?>">
                    <?php foreach ($paymentMethods as $key => $label): ?>
                        <option value="<?= $key ?>" <?= (string)$filters['payment_method'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="payment_status" aria-label="<?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_payment_status')) ?>">
                    <?php foreach ($paymentStatuses as $key => $label): ?>
                        <option value="<?= $key ?>" <?= (string)$filters['payment_status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>

    <?php $mobileCards = []; ob_start(); ?>
            <thead>
                <tr>
                    <th scope="col"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_date')) ?></th>
                    <th scope="col"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_car')) ?></th>
                    <th scope="col"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_customer')) ?></th>
                    <th scope="col"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_type')) ?></th>
                    <th scope="col"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_time')) ?></th>
                    <th scope="col"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_minute_price')) ?></th>
                    <th scope="col"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_calculated')) ?></th>
                    <th scope="col"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_paid_amount')) ?></th>
                    <th scope="col"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_payment_method')) ?></th>
                    <th scope="col"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_payment_status')) ?></th>
                    <th scope="col"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_status')) ?></th>
                    <th scope="col"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rides)): ?>
                    <tr><td colspan="12" class="text-center text-body-secondary py-5"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_history_empty')) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($rides as $ride): ?>
                    <?php
                    $duration = (int)($ride['duration_minutes'] ?? 0);
                    $pricePerMinute = (float)($ride['price_per_minute'] ?? 0);
                    $calculated = (string)($ride['billing_type'] ?? 'fixed') === 'metered'
                        ? (float)($ride['final_amount'] ?? ($duration * $pricePerMinute))
                        : (float)($ride['final_amount'] ?? $ride['payment_amount']);
                    $paidAmount = (float)($ride['payment_amount'] ?? 0);
                    $isUnpaid = (string)($ride['payment_status'] ?? '') === 'unpaid';
                    $canMarkPaid = $isUnpaid && (string)($ride['status'] ?? '') === 'completed';
                    $mobileActions = [];
                    if ($canMarkPaid) {
                        foreach (FireballPluginToyCarRental::PAYMENT_METHODS as $method) {
                            $mobileActions[] = [
                                'type' => 'form',
                                'icon' => 'ci-check',
                                'label' => FireballPluginToyCarRental::t('toy_rental_mark_paid') . ': ' . FireballPluginToyCarRental::paymentMethodLabel($method),
                                'action' => base_href('/admin/toy-rental/rides/pay'),
                                'hidden' => [
                                    'id' => (int)$ride['id'],
                                    'payment_amount' => $paidAmount > 0 ? $paidAmount : $calculated,
                                    'payment_method' => $method,
                                    'return_to' => $returnTo,
                                ],
                            ];
                        }
                    }
                    $mobileCards[] = [
                        'id' => (int)$ride['id'],
                        'title' => (string)$ride['car_name'],
                        'extra_fields' => [
                            ['label' => FireballPluginToyCarRental::t('toy_rental_field_number'), 'value' => (string)$ride['car_number']],
                            ['label' => FireballPluginToyCarRental::t('toy_rental_table_date'), 'value' => date('d.m.Y H:i', strtotime((string)$ride['started_at']))],
                            ['label' => FireballPluginToyCarRental::t('toy_rental_table_customer'), 'value' => trim((string)$ride['customer_name'] . ' ' . (string)$ride['customer_phone']) ?: '—'],
                            ['label' => FireballPluginToyCarRental::t('toy_rental_table_type'), 'value' => FireballPluginToyCarRental::billingTypeLabel((string)($ride['billing_type'] ?? 'fixed'))],
                            ['label' => FireballPluginToyCarRental::t('toy_rental_table_time'), 'value' => $duration . ' ' . FireballPluginToyCarRental::t('toy_rental_min_short')],
                            ['label' => FireballPluginToyCarRental::t('toy_rental_table_minute_price'), 'value' => number_format($pricePerMinute, 2, '.', ' ') . ' ' . $currency],
                            ['label' => FireballPluginToyCarRental::t('toy_rental_table_calculated'), 'value' => number_format($calculated, 2, '.', ' ') . ' ' . $currency],
                            ['label' => FireballPluginToyCarRental::t('toy_rental_table_paid_amount'), 'value' => number_format($paidAmount, 2, '.', ' ') . ' ' . $currency],
                            ['label' => FireballPluginToyCarRental::t('toy_rental_table_payment_method'), 'value' => FireballPluginToyCarRental::paymentMethodLabel((string)$ride['payment_method'])],
                            ['label' => FireballPluginToyCarRental::t('toy_rental_table_payment_status'), 'html' => '<span class="' . ($isUnpaid ? 'text-warning' : '') . '">' . htmlSC(FireballPluginToyCarRental::paymentStatusLabel((string)$ride['payment_status'])) . '</span>'],
                        ],
                        'status' => [['label' => FireballPluginToyCarRental::statusLabel((string)$ride['status']), 'class' => 'text-secondary bg-secondary-subtle']],
                        'status_label' => FireballPluginToyCarRental::t('toy_rental_table_status'),
                        'actions' => $mobileActions,
                    ];
                    ?>
                    <tr>
                        <td class="text-nowrap"><?= htmlSC(date('d.m.Y H:i', strtotime((string)$ride['started_at']))) ?></td>
                        <td>
                            <div class="fw-medium"><?= htmlSC((string)$ride['car_name']) ?></div>
                            <div class="small text-body-secondary">№ <?= htmlSC((string)$ride['car_number']) ?></div>
                        </td>
                        <td>
                            <div><?= htmlSC((string)$ride['customer_name']) ?></div>
                            <div class="small text-body-secondary"><?= htmlSC((string)$ride['customer_phone']) ?></div>
                        </td>
                        <td><?= htmlSC(FireballPluginToyCarRental::billingTypeLabel((string)($ride['billing_type'] ?? 'fixed'))) ?></td>
                        <td class="text-nowrap"><?= $duration ?> <?= htmlSC(FireballPluginToyCarRental::t('toy_rental_min_short')) ?></td>
                        <td class="text-nowrap"><?= number_format($pricePerMinute, 2, '.', ' ') ?> <?= htmlSC($currency) ?></td>
                        <td class="text-nowrap"><?= number_format($calculated, 2, '.', ' ') ?> <?= htmlSC($currency) ?></td>
                        <td class="text-nowrap"><?= number_format($paidAmount, 2, '.', ' ') ?> <?= htmlSC($currency) ?></td>
                        <td><?= htmlSC(FireballPluginToyCarRental::paymentMethodLabel((string)$ride['payment_method'])) ?></td>
                        <td>
                            <div class="<?= $isUnpaid ? 'text-warning fw-semibold' : '' ?>">
                                <?= htmlSC(FireballPluginToyCarRental::paymentStatusLabel((string)$ride['payment_status'])) ?>
                            </div>
                            <?php if ($isUnpaid): ?>
                                <div class="small text-body-secondary"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_unpaid_warning_history')) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge rounded-pill text-bg-light border"><?= htmlSC(FireballPluginToyCarRental::statusLabel((string)$ride['status'])) ?></span></td>
                        <td class="text-nowrap">
                            <?php if ($canMarkPaid): ?>
                                <form class="d-flex align-items-center gap-2" action="<?= base_href('/admin/toy-rental/rides/pay') ?>" method="post">
                                    <?= get_csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$ride['id'] ?>">
                                    <input type="hidden" name="payment_amount" value="<?= htmlSC((string)($paidAmount > 0 ? $paidAmount : $calculated)) ?>">
                                    <input type="hidden" name="return_to" value="<?= htmlSC($returnTo) ?>">
                                    <select class="form-select form-select-sm w-auto" name="payment_method" aria-label="<?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_payment_method')) ?>">
                                        <?php foreach ($paymentMethods as $key => $label): ?>
                                            <?php if ($key === '') { continue; } ?>
                                            <option value="<?= htmlSC((string)$key) ?>" <?= (string)$ride['payment_method'] === (string)$key ? 'selected' : '' ?>><?= htmlSC($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="btn btn-sm btn-primary" type="submit">
                                        <?= htmlSC(FireballPluginToyCarRental::t('toy_rental_mark_paid')) ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-body-tertiary">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
    <?php $tableContent = ob_get_clean(); ?>
    <?= view()->renderPartial('admin/partials/table', [
        'content' => $tableContent,
        'mobile_cards' => $mobileCards,
        'empty_text' => FireballPluginToyCarRental::t('toy_rental_history_empty'),
        'wrapper_attributes' => ['data-admin-simplebar' => true, 'data-simplebar-auto-hide' => 'false'],
    ]) ?>

<?= view()->renderPartial('admin/shell_close') ?>
