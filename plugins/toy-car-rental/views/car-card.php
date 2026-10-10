<?php
$currency = (string)$settings['currency'];
$t = static fn(string $key, array $replace = []): string => htmlSC(FireballPluginToyCarRental::t($key, $replace));
$ride = $car['active_ride'] ?? null;
$isMetered = $ride && (string)$ride['billing_type'] === 'metered';
$isOverdue = $ride && (string)$ride['status'] === 'overdue';
$isRented = $ride !== null || (string)$car['status'] === 'rented';
$status = (string)($ride['status'] ?? $car['status']);
$statusClass = $isOverdue ? 'toy-rental-status-badge--danger' : ($isRented ? 'toy-rental-status-badge--warning' : ($status === 'available' ? 'toy-rental-status-badge--success' : ''));
$minutePrice = $ride ? (float)$ride['price_per_minute'] : FireballPluginToyCarRental::minutePrice($car, $settings);
$fixedPrice = $ride ? (float)$ride['payment_amount'] : FireballPluginToyCarRental::money($minutePrice * (int)$settings['default_duration']);
$label = trim((string)$car['name'] . ' #' . (string)$car['number']);
$started = $ride ? (strtotime((string)$ride['started_at']) ?: time()) : 0;
$end = $ride ? (strtotime((string)$ride['planned_end_at']) ?: time()) : 0;
$seconds = $isMetered ? max(0, time() - $started) : $end - time();
$timerText = ($seconds < 0 ? '+' : '') . sprintf('%02d:%02d', intdiv(abs($seconds), 60), abs($seconds) % 60);
$operatorBase = FireballPluginToyCarRental::operatorBasePath();
?>
<article class="card fb-card toy-rental-car-card <?= $isOverdue ? 'is-overdue' : ($isRented ? 'is-rented' : '') ?>"
         data-toy-rental-card data-car-id="<?= (int)$car['id'] ?>" data-ride-id="<?= (int)($ride['id'] ?? 0) ?>"
         data-status="<?= htmlSC($status) ?>" data-car-label="<?= htmlSC($label) ?>">
    <div class="toy-rental-card-heading">
        <h2 class="fb-card-title toy-rental-car-name" title="<?= htmlSC($label) ?>"><?= htmlSC((string)$car['name']) ?></h2>
        <span class="fb-badge toy-rental-status-badge <?= $statusClass ?>" data-toy-rental-status>
            <?= $isOverdue ? $t('toy_rental_status_overdue') : ($ride ? $t('toy_rental_status_active') : htmlSC(FireballPluginToyCarRental::statusLabel($status))) ?>
        </span>
    </div>
    <div class="toy-rental-prices text-body-secondary">
        <?php if (!$isMetered): ?>
            <span><span data-toy-rental-fixed-price data-price-per-minute="<?= htmlSC((string)$minutePrice) ?>"><?= number_format($fixedPrice, 2, '.', ' ') ?></span> <?= htmlSC($currency) ?> <?= $t('toy_rental_price_per_ride_suffix') ?></span>
        <?php endif; ?>
        <span><?= number_format($minutePrice, 2, '.', ' ') ?> <?= htmlSC($currency) ?> <?= $t('toy_rental_price_per_minute_suffix') ?></span>
    </div>
    <?php if ($ride): ?>
        <div class="toy-rental-ride-summary">
            <div>
                <div class="small text-body-secondary"><?= $isMetered ? $t('toy_rental_timer_ride_time') : $t('toy_rental_timer_remaining') ?></div>
                <span class="toy-rental-timer <?= $isOverdue ? 'text-danger' : '' ?>" data-toy-rental-timer
                      data-billing-type="<?= htmlSC((string)$ride['billing_type']) ?>" data-start-ms="<?= $started * 1000 ?>"
                      data-end-ms="<?= $end * 1000 ?>" data-server-now-ms="<?= time() * 1000 ?>"
                      data-price-per-minute="<?= htmlSC((string)$ride['price_per_minute']) ?>"
                      data-estimated-minutes="<?= (int)($ride['estimated_minutes'] ?? 0) ?>"><?= $timerText ?></span>
            </div>
            <form action="<?= base_href($operatorBase . '/rides/complete') ?>" method="post" data-toy-rental-complete-form>
                <?= get_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$ride['id'] ?>">
                <div class="toy-rental-action">
                    <button class="btn btn-danger toy-rental-round-button" type="submit" title="<?= $t('toy_rental_complete_ride') ?>" aria-label="<?= $t('toy_rental_complete_ride') ?>">
                        <i class="ci-stop-circle" aria-hidden="true"></i>
                    </button>
                    <span><?= $t('toy_rental_complete') ?></span>
                </div>
            </form>
        </div>
        <div class="toy-rental-ride-note small text-body-secondary">
            <?php if ($isMetered): ?>
                <span data-toy-rental-live-cost><?= number_format(max(1, (int)ceil((time() - $started) / 60)) * (float)$ride['price_per_minute'], 2, '.', ' ') ?></span> <?= htmlSC($currency) ?> · <?= $t('toy_rental_payment_after_completion_option') ?>
            <?php else: ?>
                <?= number_format((float)$ride['payment_amount'], 2, '.', ' ') ?> <?= htmlSC($currency) ?> · <?= htmlSC(FireballPluginToyCarRental::paymentStatusLabel((string)$ride['payment_status'])) ?>
            <?php endif; ?>
        </div>
        <div class="toy-rental-time-up small text-danger <?= $isOverdue ? '' : 'd-none' ?>" data-toy-rental-time-up><?= $t('toy_rental_time_up') ?></div>
    <?php elseif ($status === 'available'): ?>
        <div class="toy-rental-start-actions">
            <form action="<?= base_href($operatorBase . '/rides/start') ?>" method="post" data-toy-rental-start-form>
                <?= get_csrf_field() ?>
                <input type="hidden" name="car_id" value="<?= (int)$car['id'] ?>">
                <input type="hidden" name="billing_type" value="fixed">
                <div class="toy-rental-action">
                    <button class="btn btn-primary toy-rental-round-button" type="submit" title="<?= $t('toy_rental_start_fixed') ?>" aria-label="<?= $t('toy_rental_start_fixed') ?>">
                        <i class="ci-play" aria-hidden="true"></i>
                    </button>
                    <span><?= $t('toy_rental_billing_fixed') ?></span>
                </div>
                <select class="form-select form-select-sm toy-rental-duration" name="duration_minutes" aria-label="<?= $t('toy_rental_settings_default_duration') ?>">
                    <?php foreach ($settings['fixed_durations'] as $minutes): ?>
                        <option value="<?= $minutes ?>" <?= (int)$settings['default_duration'] === $minutes ? 'selected' : '' ?>><?= $minutes ?> <?= $t('toy_rental_min_short') ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <form action="<?= base_href($operatorBase . '/rides/start') ?>" method="post" data-toy-rental-start-form>
                <?= get_csrf_field() ?>
                <input type="hidden" name="car_id" value="<?= (int)$car['id'] ?>">
                <input type="hidden" name="billing_type" value="metered">
                <div class="toy-rental-action">
                    <button class="btn btn-outline-secondary toy-rental-round-button" type="submit" title="<?= $t('toy_rental_start_metered') ?>" aria-label="<?= $t('toy_rental_start_metered') ?>">
                        <i class="ci-clock" aria-hidden="true"></i>
                    </button>
                    <span><?= $t('toy_rental_billing_metered') ?></span>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="toy-rental-unavailable small text-body-secondary"><?= $t('toy_rental_unavailable') ?></div>
    <?php endif; ?>
</article>
