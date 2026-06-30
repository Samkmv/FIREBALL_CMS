<?php
$currency = (string)($settings['currency'] ?? '₽');
$durations = [5, 10, 15, 30];
$paymentMethods = ['cash' => 'Наличные', 'card' => 'Карта', 'transfer' => 'Перевод', 'other' => 'Другое'];
$paymentStatuses = ['unpaid' => 'Не оплачено', 'paid' => 'Оплачено', 'refunded' => 'Возврат'];
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
    'title' => 'Прокат машинок',
    'subtitle' => 'Панель оператора: старт, таймеры, просрочка и завершение поездок.',
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="border rounded-5 p-4">
                <div class="text-body-secondary small mb-1">Поездок сегодня</div>
                <div class="toy-rental-stat-value fw-bold"><?= (int)$stats['rides_total'] ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded-5 p-4">
                <div class="text-body-secondary small mb-1">Активных</div>
                <div class="toy-rental-stat-value fw-bold"><?= (int)$stats['active'] + (int)$stats['overdue'] ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="border rounded-5 p-4">
                <div class="text-body-secondary small mb-1">Выручка</div>
                <div class="toy-rental-stat-value fw-bold"><?= number_format((float)$stats['revenue_total'], 0, '.', ' ') ?> <?= htmlSC($currency) ?></div>
            </div>
        </div>
    </div>

    <?php if (empty($cars)): ?>
        <div class="border rounded-5 p-4 p-md-5 text-center">
            <i class="ci-settings fs-1 text-body-tertiary d-block mb-3"></i>
            <h2 class="h5 mb-2">Машинки ещё не добавлены</h2>
            <p class="text-body-secondary mb-3">Добавьте первую машинку, чтобы начать прокат.</p>
            <a class="btn btn-dark rounded-pill" href="<?= base_href('/admin/toy-rental/cars/create') ?>">Добавить машинку</a>
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
                                    <?= htmlSC($isOverdue ? 'Просрочена' : FireballPluginToyCarRental::statusLabel((string)$car['status'])) ?>
                                </span>
                            </div>

                            <div class="d-flex flex-wrap gap-3 small text-body-secondary mb-3">
                                <?php if ((string)$car['color'] !== ''): ?>
                                    <span class="d-inline-flex align-items-center gap-2"><span class="toy-rental-color-dot" style="<?= FireballPluginToyCarRental::cssColorStyle((string)$car['color']) ?>"></span><?= htmlSC((string)$car['color']) ?></span>
                                <?php endif; ?>
                                <span><?= number_format($fixedPrice, 0, '.', ' ') ?> <?= htmlSC($currency) ?> / поездка</span>
                                <span><?= number_format($minutePrice, 2, '.', ' ') ?> <?= htmlSC($currency) ?> / мин</span>
                            </div>

                            <?php if ($ride): ?>
                                <?php
                                $started = strtotime((string)$ride['started_at']) ?: time();
                                $duration = max(1, (int)ceil((time() - $started) / 60));
                                $calculated = $isMetered ? $duration * (float)$ride['price_per_minute'] : (float)$ride['payment_amount'];
                                ?>
                                <div class="border rounded-4 p-3 mb-3">
                                    <div class="d-flex align-items-center justify-content-between gap-3">
                                        <span class="text-body-secondary small"><?= $isMetered ? 'Время катания' : 'Осталось' ?></span>
                                        <span class="h4 mb-0 toy-rental-timer"
                                              data-toy-rental-timer
                                              data-billing-type="<?= htmlSC((string)($ride['billing_type'] ?? 'fixed')) ?>"
                                              data-start="<?= htmlSC(date('c', $started)) ?>"
                                              data-end="<?= htmlSC(date('c', strtotime((string)$ride['planned_end_at']))) ?>"
                                              data-price-per-minute="<?= htmlSC((string)$ride['price_per_minute']) ?>"
                                              data-estimated-minutes="<?= (int)($ride['estimated_minutes'] ?? 0) ?>">--:--</span>
                                    </div>
                                    <div class="small text-body-secondary mt-2">
                                        <?= htmlSC($billingType) ?> · старт <?= htmlSC(date('H:i', $started)) ?>
                                        <?php if (!$isMetered): ?>
                                            · план <?= htmlSC(date('H:i', strtotime((string)$ride['planned_end_at']))) ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($isMetered): ?>
                                        <div class="small mt-2">
                                            Примерно: <span data-toy-rental-live-cost><?= number_format($calculated, 2, '.', ' ') ?></span> <?= htmlSC($currency) ?>
                                            <span class="badge rounded-pill text-bg-warning ms-2 d-none" data-toy-rental-estimate-warning>Ориентир превышен</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ((string)$ride['customer_name'] !== '' || (string)$ride['customer_phone'] !== ''): ?>
                                        <div class="small mt-2"><?= htmlSC(trim((string)$ride['customer_name'] . ' ' . (string)$ride['customer_phone'])) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($isMetered): ?>
                                    <button class="btn btn-dark rounded-pill w-100" type="button" data-bs-toggle="modal" data-bs-target="#toyCompleteRide<?= (int)$ride['id'] ?>">Завершить поездку</button>
                                <?php else: ?>
                                    <form action="<?= base_href('/admin/toy-rental/rides/complete') ?>" method="post">
                                        <?= get_csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$ride['id'] ?>">
                                        <input type="hidden" name="final_amount" value="<?= htmlSC((string)$ride['payment_amount']) ?>">
                                        <input type="hidden" name="payment_method" value="<?= htmlSC((string)$ride['payment_method']) ?>">
                                        <input type="hidden" name="payment_status" value="<?= htmlSC((string)$ride['payment_status']) ?>">
                                        <button class="btn btn-dark rounded-pill w-100" type="submit">Завершить поездку</button>
                                    </form>
                                <?php endif; ?>
                            <?php elseif ((string)$car['status'] === 'available'): ?>
                                <details class="toy-rental-start-details">
                                    <summary class="btn btn-outline-dark rounded-pill w-100">Начать поездку</summary>
                                    <form class="mt-3 d-grid gap-3" action="<?= base_href('/admin/toy-rental/rides/start') ?>" method="post" data-toy-rental-start-form>
                                        <?= get_csrf_field() ?>
                                        <input type="hidden" name="car_id" value="<?= (int)$car['id'] ?>">
                                        <div class="row g-2">
                                            <div class="col-sm-6">
                                                <input class="form-control" type="text" name="customer_name" placeholder="Имя клиента">
                                            </div>
                                            <div class="col-sm-6">
                                                <input class="form-control" type="text" name="customer_phone" placeholder="Телефон">
                                            </div>
                                        </div>
                                        <?php $toyHint('toy_rental_hint_customer'); ?>
                                        <select class="form-select" name="billing_type" data-toy-rental-billing-type>
                                            <option value="fixed">Фиксированная поездка</option>
                                            <option value="metered">Поминутная, оплата после катания</option>
                                        </select>
                                        <?php $toyHint('toy_rental_hint_billing_type'); ?>
                                        <div class="d-grid gap-3" data-toy-rental-fixed-fields>
                                            <select class="form-select" name="duration_minutes">
                                                <?php foreach ($durations as $duration): ?>
                                                    <option value="<?= $duration ?>" <?= (int)$settings['default_duration'] === $duration ? 'selected' : '' ?>><?= $duration ?> минут</option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php $toyHint('toy_rental_hint_fixed_duration'); ?>
                                            <div class="row g-2">
                                                <div class="col-sm-5">
                                                    <input class="form-control" type="number" name="payment_amount" min="0" step="0.01" value="<?= htmlSC((string)$fixedPrice) ?>" placeholder="Сумма">
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
                                                        <option value="paid">Оплачено</option>
                                                        <option value="unpaid">Не оплачено</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <?php $toyHint('toy_rental_hint_fixed_payment'); ?>
                                        </div>
                                        <div class="d-grid gap-3 d-none" data-toy-rental-metered-fields>
                                            <div class="row g-2">
                                                <div class="col-sm-6">
                                                    <input class="form-control" type="number" name="price_per_minute" min="0" step="0.01" value="<?= htmlSC((string)$minutePrice) ?>" placeholder="Цена минуты">
                                                </div>
                                                <div class="col-sm-6">
                                                    <input class="form-control" type="number" name="estimated_minutes" min="1" step="1" value="<?= (int)$settings['default_duration'] ?>" placeholder="Ориентир, мин">
                                                </div>
                                            </div>
                                            <?php $toyHint('toy_rental_hint_metered_price'); ?>
                                            <select class="form-select" name="payment_status" disabled>
                                                <option value="unpaid">Оплата после завершения</option>
                                            </select>
                                            <?php $toyHint('toy_rental_hint_metered_payment'); ?>
                                        </div>
                                        <button class="btn btn-dark rounded-pill" type="submit">Старт</button>
                                    </form>
                                </details>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary rounded-pill w-100" type="button" disabled>Недоступна</button>
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
                        <h2 class="modal-title h5" id="toyCompleteRideLabel<?= (int)$ride['id'] ?>">Завершить поминутную поездку</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <div class="modal-body">
                        <div class="small text-body-secondary mb-3"><?= htmlSC((string)$ride['car_name'] . ' #' . (string)$ride['car_number']) ?></div>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label">Фактическое время</label>
                                <input class="form-control" type="text" value="<?= $duration ?> мин" readonly data-toy-rental-modal-duration>
                                <?php $toyHint('toy_rental_hint_complete_duration'); ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Цена минуты</label>
                                <input class="form-control" type="text" value="<?= number_format((float)$ride['price_per_minute'], 2, '.', ' ') ?> <?= htmlSC($currency) ?>" readonly>
                                <?php $toyHint('toy_rental_hint_complete_minute_price'); ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Расчёт</label>
                                <input class="form-control" type="text" value="<?= number_format($calculated, 2, '.', ' ') ?> <?= htmlSC($currency) ?>" readonly data-toy-rental-modal-calculated>
                                <?php $toyHint('toy_rental_hint_complete_calculated'); ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Итоговая сумма</label>
                                <input class="form-control" type="number" name="final_amount" min="0" step="0.01" value="<?= htmlSC((string)$calculated) ?>" data-toy-rental-final-amount>
                                <?php $toyHint('toy_rental_hint_complete_final_amount'); ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Способ оплаты</label>
                                <select class="form-select" name="payment_method">
                                    <?php foreach ($paymentMethods as $key => $methodLabel): ?>
                                        <option value="<?= $key ?>" <?= (string)$ride['payment_method'] === $key ? 'selected' : '' ?>><?= htmlSC($methodLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php $toyHint('toy_rental_hint_complete_payment_method'); ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Статус оплаты</label>
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
                        <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Отмена</button>
                        <button type="submit" class="btn btn-dark rounded-pill">Завершить</button>
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
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>

<?= view()->renderPartial('admin/shell_close') ?>
