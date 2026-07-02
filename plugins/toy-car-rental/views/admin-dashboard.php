<?php
$currency = (string)($settings['currency'] ?? '₽');
$durations = array_values(array_unique(array_filter([5, 10, 15, 30, (int)($settings['default_duration'] ?? 10)])));
sort($durations);
$defaultFixedPrice = (float)($settings['default_price'] ?? 0);
$defaultMinutePrice = (float)($settings['default_minute_price'] ?? 0);
$t = static fn(string $key, array $replace = []): string => htmlSC(FireballPluginToyCarRental::t($key, $replace));
$paymentMethods = [
    'cash' => FireballPluginToyCarRental::paymentMethodLabel('cash'),
    'card' => FireballPluginToyCarRental::paymentMethodLabel('card'),
    'transfer' => FireballPluginToyCarRental::paymentMethodLabel('transfer'),
    'other' => FireballPluginToyCarRental::paymentMethodLabel('other'),
];
$paymentStatuses = [
    'unpaid' => FireballPluginToyCarRental::paymentStatusLabel('unpaid'),
    'paid' => FireballPluginToyCarRental::paymentStatusLabel('paid'),
    'refunded' => FireballPluginToyCarRental::paymentStatusLabel('refunded'),
];
$toyHint = static function (string $key): void {
    $text = FireballPluginToyCarRental::t($key);
    ?>
    <div class="toy-rental-field-hint form-text d-flex align-items-start gap-2">
        <i class="ci-info text-info flex-shrink-0 mt-1" data-bs-toggle="tooltip" data-bs-title="<?= htmlSC($text) ?>" aria-label="<?= htmlSC($text) ?>"></i>
        <span><?= htmlSC($text) ?></span>
    </div>
    <?php
};
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => FireballPluginToyCarRental::t('toy_rental_dashboard_title'),
    'subtitle' => FireballPluginToyCarRental::t('toy_rental_dashboard_subtitle'),
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="border rounded-5 p-4">
                <div class="text-body-secondary small mb-1"><?= $t('toy_rental_stat_rides_today') ?></div>
                <div class="toy-rental-stat-value fw-bold"><?= (int)$stats['rides_total'] ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded-5 p-4">
                <div class="text-body-secondary small mb-1"><?= $t('toy_rental_stat_active') ?></div>
                <div class="toy-rental-stat-value fw-bold"><?= (int)$stats['active'] + (int)$stats['overdue'] ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded-5 p-4">
                <div class="text-body-secondary small mb-1"><?= $t('toy_rental_stat_revenue') ?></div>
                <div class="toy-rental-stat-value fw-bold"><?= number_format((float)$stats['revenue_total'], 0, '.', ' ') ?> <?= htmlSC($currency) ?></div>
            </div>
        </div>
    </div>

    <?php if (empty($cars)): ?>
        <div class="border rounded-5 p-4 p-md-5 text-center">
            <i class="ci-settings fs-1 text-body-tertiary d-block mb-3"></i>
            <h2 class="h5 mb-2"><?= $t('toy_rental_empty_cars_title') ?></h2>
            <p class="text-body-secondary mb-3"><?= $t('toy_rental_empty_cars_text') ?></p>
            <a class="btn btn-dark rounded-pill" href="<?= base_href('/admin/toy-rental/cars/create') ?>"><?= $t('toy_rental_add_car') ?></a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($cars as $car): ?>
                <?php
                $ride = $car['active_ride'] ?? null;
                $billingType = FireballPluginToyCarRental::billingTypeLabel((string)($ride['billing_type'] ?? 'fixed'));
                $isMetered = $ride && (string)($ride['billing_type'] ?? 'fixed') === 'metered';
                $isOverdue = $ride && (string)$ride['status'] === 'overdue';
                $isRented = $ride !== null || (string)$car['status'] === 'rented';
                $cardClass = $isOverdue ? 'is-overdue' : ($isRented ? 'is-rented' : '');
                $statusClass = $isOverdue ? 'text-bg-danger' : ($isRented ? 'text-bg-warning' : ((string)$car['status'] === 'available' ? 'text-bg-success' : 'text-bg-secondary'));
                $label = trim((string)$car['name'] . ' #' . (string)$car['number']);
                $minutePrice = (float)($car['price_per_minute'] ?: $settings['default_minute_price']);
                $fixedPrice = (float)($car['price_per_ride'] ?: $settings['default_price']);
                ?>
                <div class="col-md-6 col-xl-4">
                    <article class="card h-100 rounded-5 toy-rental-car-card <?= htmlSC($cardClass) ?>" data-toy-rental-card data-ride-id="<?= (int)($ride['id'] ?? 0) ?>" data-car-label="<?= htmlSC($label) ?>">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                                <div>
                                    <h2 class="h5 mb-1"><?= htmlSC((string)$car['name']) ?></h2>
                                    <div class="text-body-secondary small">№ <?= htmlSC((string)$car['number']) ?></div>
                                </div>
                                <span class="badge rounded-pill toy-rental-status-badge <?= htmlSC($statusClass) ?>" data-toy-rental-status>
                                    <?= htmlSC($isOverdue ? FireballPluginToyCarRental::t('toy_rental_status_overdue') : FireballPluginToyCarRental::statusLabel((string)$car['status'])) ?>
                                </span>
                            </div>

                            <div class="d-flex flex-wrap gap-3 small text-body-secondary mb-3">
                                <?php if ((string)$car['color'] !== ''): ?>
                                    <span class="d-inline-flex align-items-center gap-2"><span class="toy-rental-color-dot" style="<?= FireballPluginToyCarRental::cssColorStyle((string)$car['color']) ?>"></span><?= htmlSC((string)$car['color']) ?></span>
                                <?php endif; ?>
                                <span><?= number_format($fixedPrice, 0, '.', ' ') ?> <?= htmlSC($currency) ?> <?= $t('toy_rental_price_per_ride_suffix') ?></span>
                                <span><?= number_format($minutePrice, 2, '.', ' ') ?> <?= htmlSC($currency) ?> <?= $t('toy_rental_price_per_minute_suffix') ?></span>
                            </div>

                            <?php if ($ride): ?>
                                <?php
                                $started = strtotime((string)$ride['started_at']) ?: time();
                                $duration = max(1, (int)ceil((time() - $started) / 60));
                                $calculated = $isMetered ? $duration * (float)$ride['price_per_minute'] : (float)$ride['payment_amount'];
                                ?>
                                <div class="border rounded-4 p-3 mb-3">
                                    <div class="d-flex align-items-center justify-content-between gap-3">
                                        <span class="text-body-secondary small"><?= $isMetered ? $t('toy_rental_timer_ride_time') : $t('toy_rental_timer_remaining') ?></span>
                                        <span class="h4 mb-0 toy-rental-timer"
                                              data-toy-rental-timer
                                              data-billing-type="<?= htmlSC((string)($ride['billing_type'] ?? 'fixed')) ?>"
                                              data-start="<?= htmlSC(date('c', $started)) ?>"
                                              data-end="<?= htmlSC(date('c', strtotime((string)$ride['planned_end_at']))) ?>"
                                              data-price-per-minute="<?= htmlSC((string)$ride['price_per_minute']) ?>"
                                              data-estimated-minutes="<?= (int)($ride['estimated_minutes'] ?? 0) ?>">--:--</span>
                                    </div>
                                    <div class="small text-body-secondary mt-2">
                                        <?= htmlSC($billingType) ?> · <?= $t('toy_rental_timer_start') ?> <?= htmlSC(date('H:i', $started)) ?>
                                        <?php if (!$isMetered): ?>
                                            · <?= $t('toy_rental_timer_plan') ?> <?= htmlSC(date('H:i', strtotime((string)$ride['planned_end_at']))) ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($isMetered): ?>
                                        <div class="small mt-2">
                                            <?= $t('toy_rental_timer_estimated_amount') ?>: <span data-toy-rental-live-cost><?= number_format($calculated, 2, '.', ' ') ?></span> <?= htmlSC($currency) ?>
                                            <span class="badge rounded-pill text-bg-warning ms-2 d-none" data-toy-rental-estimate-warning><?= $t('toy_rental_timer_estimate_exceeded') ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ((string)$ride['customer_name'] !== '' || (string)$ride['customer_phone'] !== ''): ?>
                                        <div class="small mt-2"><?= htmlSC(trim((string)$ride['customer_name'] . ' ' . (string)$ride['customer_phone'])) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($isMetered): ?>
                                    <button class="btn btn-dark rounded-pill w-100" type="button" data-bs-toggle="modal" data-bs-target="#toyCompleteRide<?= (int)$ride['id'] ?>"><?= $t('toy_rental_complete_ride') ?></button>
                                <?php else: ?>
                                    <form action="<?= base_href('/admin/toy-rental/rides/complete') ?>" method="post">
                                        <?= get_csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$ride['id'] ?>">
                                        <input type="hidden" name="payment_amount" value="<?= htmlSC((string)$ride['payment_amount']) ?>">
                                        <input type="hidden" name="payment_method" value="<?= htmlSC((string)$ride['payment_method']) ?>">
                                        <input type="hidden" name="payment_status" value="<?= htmlSC((string)$ride['payment_status']) ?>">
                                        <button class="btn btn-dark rounded-pill w-100" type="submit"><?= $t('toy_rental_complete_ride') ?></button>
                                    </form>
                                <?php endif; ?>
                            <?php elseif ((string)$car['status'] === 'available'): ?>
                                <details class="toy-rental-start-details">
                                    <summary class="btn btn-outline-dark rounded-pill w-100"><?= $t('toy_rental_start_ride') ?></summary>
                                    <form class="mt-3 d-grid gap-3" action="<?= base_href('/admin/toy-rental/rides/start') ?>" method="post" data-toy-rental-start-form>
                                        <?= get_csrf_field() ?>
                                        <input type="hidden" name="car_id" value="<?= (int)$car['id'] ?>">
                                        <div class="row g-2">
                                            <div class="col-sm-6">
                                                <input class="form-control" type="text" name="customer_name" placeholder="<?= $t('toy_rental_customer_name_placeholder') ?>">
                                            </div>
                                            <div class="col-sm-6">
                                                <input class="form-control" type="text" name="customer_phone" placeholder="<?= $t('toy_rental_customer_phone_placeholder') ?>">
                                            </div>
                                        </div>
                                        <?php $toyHint('toy_rental_hint_customer'); ?>
                                        <select class="form-select" name="billing_type" data-toy-rental-billing-type>
                                            <option value="fixed"><?= $t('toy_rental_billing_fixed_option') ?></option>
                                            <option value="metered"><?= $t('toy_rental_billing_metered_option') ?></option>
                                        </select>
                                        <?php $toyHint('toy_rental_hint_billing_type'); ?>
                                        <div class="d-grid gap-3" data-toy-rental-fixed-fields>
                                            <select class="form-select" name="duration_minutes">
                                                <?php foreach ($durations as $duration): ?>
                                                    <option value="<?= $duration ?>" <?= (int)$settings['default_duration'] === $duration ? 'selected' : '' ?>><?= $t('toy_rental_duration_minutes_option', ['minutes' => $duration]) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php $toyHint('toy_rental_hint_fixed_duration'); ?>
                                            <div class="row g-2">
                                                <div class="col-sm-5">
                                                    <input class="form-control" type="number" name="payment_amount" min="0" step="0.01" value="<?= htmlSC((string)$defaultFixedPrice) ?>" placeholder="<?= $t('toy_rental_amount_placeholder') ?>">
                                                </div>
                                                <div class="col-sm-4">
                                                    <select class="form-select" name="payment_method">
                                                        <?php foreach ($paymentMethods as $key => $methodLabel): ?>
                                                            <option value="<?= $key ?>"><?= htmlSC($methodLabel) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-sm-3">
                                                    <select class="form-select" name="payment_status">
                                                        <option value="paid"><?= $t('toy_rental_paid_option') ?></option>
                                                        <option value="unpaid"><?= $t('toy_rental_unpaid_option') ?></option>
                                                    </select>
                                                </div>
                                            </div>
                                            <?php $toyHint('toy_rental_hint_fixed_payment'); ?>
                                        </div>
                                        <div class="d-grid gap-3 d-none" data-toy-rental-metered-fields>
                                            <div class="row g-2">
                                                <div class="col-sm-6">
                                                    <input class="form-control" type="number" name="price_per_minute" min="0" step="0.01" value="<?= htmlSC((string)$defaultMinutePrice) ?>" placeholder="<?= $t('toy_rental_price_minute_placeholder') ?>">
                                                </div>
                                                <div class="col-sm-6">
                                                    <input class="form-control" type="number" name="estimated_minutes" min="1" step="1" value="<?= (int)$settings['default_duration'] ?>" placeholder="<?= $t('toy_rental_estimated_minutes_placeholder') ?>">
                                                </div>
                                            </div>
                                            <?php $toyHint('toy_rental_hint_metered_price'); ?>
                                            <select class="form-select" name="payment_status" disabled>
                                                <option value="unpaid"><?= $t('toy_rental_payment_after_completion_option') ?></option>
                                            </select>
                                            <?php $toyHint('toy_rental_hint_metered_payment'); ?>
                                        </div>
                                        <button class="btn btn-dark rounded-pill" type="submit"><?= $t('toy_rental_start') ?></button>
                                    </form>
                                </details>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary rounded-pill w-100" type="button" disabled><?= $t('toy_rental_unavailable') ?></button>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php foreach ($active_rides as $ride): ?>
        <?php if ((string)($ride['billing_type'] ?? 'fixed') !== 'metered') { continue; } ?>
        <?php
        $started = strtotime((string)$ride['started_at']) ?: time();
        $duration = max(1, (int)ceil((time() - $started) / 60));
        $calculated = $duration * (float)$ride['price_per_minute'];
        ?>
        <div class="modal fade" id="toyCompleteRide<?= (int)$ride['id'] ?>" tabindex="-1" aria-labelledby="toyCompleteRideLabel<?= (int)$ride['id'] ?>" aria-hidden="true" data-toy-rental-complete-modal data-start="<?= htmlSC(date('c', $started)) ?>" data-price-per-minute="<?= htmlSC((string)$ride['price_per_minute']) ?>" data-currency="<?= htmlSC($currency) ?>">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content rounded-5" action="<?= base_href('/admin/toy-rental/rides/complete') ?>" method="post">
                    <?= get_csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$ride['id'] ?>">
                    <div class="modal-header border-0 pb-0">
                        <h2 class="modal-title h5" id="toyCompleteRideLabel<?= (int)$ride['id'] ?>"><?= $t('toy_rental_complete_metered_title') ?></h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= $t('toy_rental_close') ?>"></button>
                    </div>
                    <div class="modal-body">
                        <div class="small text-body-secondary mb-3"><?= htmlSC((string)$ride['car_name'] . ' #' . (string)$ride['car_number']) ?></div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label"><?= $t('toy_rental_actual_time') ?></label>
                                <input class="form-control" type="text" value="<?= $duration ?> <?= $t('toy_rental_min_short') ?>" readonly data-toy-rental-modal-duration>
                                <?php $toyHint('toy_rental_hint_complete_duration'); ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label"><?= $t('toy_rental_field_price_per_minute') ?></label>
                                <input class="form-control" type="text" value="<?= number_format((float)$ride['price_per_minute'], 2, '.', ' ') ?> <?= htmlSC($currency) ?>" readonly>
                                <?php $toyHint('toy_rental_hint_complete_minute_price'); ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label"><?= $t('toy_rental_calculated_amount') ?></label>
                                <input class="form-control" type="text" value="<?= number_format($calculated, 2, '.', ' ') ?> <?= htmlSC($currency) ?>" readonly data-toy-rental-modal-calculated>
                                <?php $toyHint('toy_rental_hint_complete_calculated'); ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label"><?= $t('toy_rental_final_amount') ?></label>
                                <input class="form-control" type="number" name="payment_amount" min="0" step="0.01" value="<?= htmlSC((string)$calculated) ?>" data-toy-rental-final-amount>
                                <?php $toyHint('toy_rental_hint_complete_final_amount'); ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label"><?= $t('toy_rental_table_payment_method') ?></label>
                                <select class="form-select" name="payment_method">
                                    <?php foreach ($paymentMethods as $key => $methodLabel): ?>
                                        <option value="<?= $key ?>" <?= (string)$ride['payment_method'] === $key ? 'selected' : '' ?>><?= htmlSC($methodLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php $toyHint('toy_rental_hint_complete_payment_method'); ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label"><?= $t('toy_rental_table_payment_status') ?></label>
                                <select class="form-select" name="payment_status">
                                    <?php foreach ($paymentStatuses as $key => $statusLabel): ?>
                                        <option value="<?= $key ?>" <?= (string)$ride['payment_status'] === $key ? 'selected' : '' ?>><?= htmlSC($statusLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php $toyHint('toy_rental_hint_complete_payment_status'); ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal"><?= $t('toy_rental_cancel') ?></button>
                        <button type="submit" class="btn btn-dark rounded-pill"><?= $t('toy_rental_complete') ?></button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
        window.toyRentalSettings = <?= json_encode([
            'soundEnabled' => (bool)$settings['sound_enabled'],
            'autoRefreshSeconds' => (int)$settings['auto_refresh_seconds'],
            'dashboardUrl' => base_href('/admin/toy-rental'),
            'currency' => $currency,
            'labels' => [
                'overdue' => FireballPluginToyCarRental::t('toy_rental_status_overdue'),
                'minutes' => FireballPluginToyCarRental::t('toy_rental_min_short'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>

<?= view()->renderPartial('admin/shell_close') ?>
