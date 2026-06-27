<?php
$currency = (string)($settings['currency'] ?? '₽');
$paymentMethods = ['cash' => 'Наличные', 'card' => 'Карта', 'transfer' => 'Перевод', 'other' => 'Другое'];
$paymentStatuses = ['unpaid' => 'Не оплачено', 'paid' => 'Оплачено', 'refunded' => 'Возврат'];
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => 'Активные поездки',
    'subtitle' => 'Все текущие и просроченные поездки.',
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <div class="row g-4">
        <?php if (empty($rides)): ?>
            <div class="col-12"><div class="border rounded-5 p-4 p-md-5 text-center text-body-secondary">Активных поездок нет</div></div>
        <?php endif; ?>
        <?php foreach ($rides as $ride): ?>
            <?php
            $isMetered = (string)($ride['billing_type'] ?? 'fixed') === 'metered';
            $isOverdue = (string)$ride['status'] === 'overdue';
            $started = strtotime((string)$ride['started_at']) ?: time();
            $duration = max(1, (int)ceil((time() - $started) / 60));
            $calculated = $isMetered ? $duration * (float)$ride['price_per_minute'] : (float)$ride['payment_amount'];
            ?>
            <div class="col-md-6 col-xl-4">
                <article class="card h-100 rounded-5 toy-rental-car-card <?= $isOverdue ? 'is-overdue' : 'is-rented' ?>" data-toy-rental-card data-ride-id="<?= (int)$ride['id'] ?>" data-car-label="<?= htmlSC((string)$ride['car_name']) ?>">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between gap-3 mb-3">
                            <div>
                                <h2 class="h5 mb-1"><?= htmlSC((string)$ride['car_name']) ?></h2>
                                <div class="small text-body-secondary">№ <?= htmlSC((string)$ride['car_number']) ?></div>
                            </div>
                            <span class="badge rounded-pill <?= $isOverdue ? 'text-bg-danger' : 'text-bg-warning' ?>" data-toy-rental-status><?= $isOverdue ? 'Просрочена' : 'Активна' ?></span>
                        </div>
                        <div class="border rounded-4 p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-center gap-3">
                                <span class="small text-body-secondary"><?= $isMetered ? 'Время катания' : 'Таймер' ?></span>
                                <span class="h4 mb-0 toy-rental-timer"
                                      data-toy-rental-timer
                                      data-billing-type="<?= htmlSC((string)($ride['billing_type'] ?? 'fixed')) ?>"
                                      data-start="<?= htmlSC(date('c', $started)) ?>"
                                      data-end="<?= htmlSC(date('c', strtotime((string)$ride['planned_end_at']))) ?>"
                                      data-price-per-minute="<?= htmlSC((string)$ride['price_per_minute']) ?>"
                                      data-estimated-minutes="<?= (int)($ride['estimated_minutes'] ?? 0) ?>">--:--</span>
                            </div>
                            <div class="small text-body-secondary mt-2"><?= htmlSC(FireballPluginToyCarRental::billingTypeLabel((string)($ride['billing_type'] ?? 'fixed'))) ?></div>
                            <?php if ($isMetered): ?>
                                <div class="small mt-2">
                                    Примерно: <span data-toy-rental-live-cost><?= number_format($calculated, 2, '.', ' ') ?></span> <?= htmlSC($currency) ?>
                                    <span class="badge rounded-pill text-bg-warning ms-2 d-none" data-toy-rental-estimate-warning>Ориентир превышен</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($isMetered): ?>
                            <button class="btn btn-dark rounded-pill w-100" type="button" data-bs-toggle="modal" data-bs-target="#toyCompleteRide<?= (int)$ride['id'] ?>">Завершить</button>
                        <?php else: ?>
                            <form action="<?= base_href('/admin/toy-rental/rides/complete') ?>" method="post">
                                <?= get_csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$ride['id'] ?>">
                                <button class="btn btn-dark rounded-pill w-100" type="submit">Завершить</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>

    <?php foreach ($rides as $ride): ?>
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
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Цена минуты</label>
                                <input class="form-control" type="text" value="<?= number_format((float)$ride['price_per_minute'], 2, '.', ' ') ?> <?= htmlSC($currency) ?>" readonly>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Расчёт</label>
                                <input class="form-control" type="text" value="<?= number_format($calculated, 2, '.', ' ') ?> <?= htmlSC($currency) ?>" readonly data-toy-rental-modal-calculated>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Итоговая сумма</label>
                                <input class="form-control" type="number" name="final_amount" min="0" step="0.01" value="<?= htmlSC((string)$calculated) ?>" data-toy-rental-final-amount>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Способ оплаты</label>
                                <select class="form-select" name="payment_method">
                                    <?php foreach ($paymentMethods as $key => $methodLabel): ?>
                                        <option value="<?= $key ?>" <?= (string)$ride['payment_method'] === $key ? 'selected' : '' ?>><?= htmlSC($methodLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label">Статус оплаты</label>
                                <select class="form-select" name="payment_status">
                                    <?php foreach ($paymentStatuses as $key => $statusLabel): ?>
                                        <option value="<?= $key ?>" <?= (string)$ride['payment_status'] === $key ? 'selected' : '' ?>><?= htmlSC($statusLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
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
            'currency' => $currency,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>

<?= view()->renderPartial('admin/shell_close') ?>
