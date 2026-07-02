<?php
$currency = (string)($settings['currency'] ?? '₽');
$paymentMethods = [
    '' => FireballPluginToyCarRental::t('toy_rental_filter_all_payment_methods'),
    'cash' => FireballPluginToyCarRental::paymentMethodLabel('cash'),
    'card' => FireballPluginToyCarRental::paymentMethodLabel('card'),
    'transfer' => FireballPluginToyCarRental::paymentMethodLabel('transfer'),
    'other' => FireballPluginToyCarRental::paymentMethodLabel('other'),
];
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
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => FireballPluginToyCarRental::t('toy_rental_history_title'),
    'subtitle' => FireballPluginToyCarRental::t('toy_rental_history_subtitle'),
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <form class="border rounded-5 p-3 p-md-4 mb-4" method="get" action="<?= base_href('/admin/toy-rental/rides') ?>">
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
                        <option value="<?= (int)$car['id'] ?>" <?= (int)$filters['car_id'] === (int)$car['id'] ? 'selected' : '' ?>><?= htmlSC((string)$car['name'] . ' #' . (string)$car['number']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-dark rounded-pill w-100" type="submit"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_filter_show')) ?></button>
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

    <div class="table-responsive border rounded-5" data-admin-simplebar data-simplebar-auto-hide="false">
        <table class="table align-middle mb-0">
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
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rides)): ?>
                    <tr><td colspan="11" class="text-center text-body-secondary py-5"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_history_empty')) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($rides as $ride): ?>
                    <?php
                    $duration = (int)($ride['duration_minutes'] ?? 0);
                    $pricePerMinute = (float)($ride['price_per_minute'] ?? 0);
                    $calculated = (string)($ride['billing_type'] ?? 'fixed') === 'metered'
                        ? (float)($ride['final_amount'] ?? ($duration * $pricePerMinute))
                        : (float)($ride['final_amount'] ?? $ride['payment_amount']);
                    $paidAmount = (float)($ride['payment_amount'] ?? 0);
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
                        <td><?= htmlSC(FireballPluginToyCarRental::paymentStatusLabel((string)$ride['payment_status'])) ?></td>
                        <td><span class="badge rounded-pill text-bg-light border"><?= htmlSC(FireballPluginToyCarRental::statusLabel((string)$ride['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
