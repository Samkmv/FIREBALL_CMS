<?php
$paymentMethods = ['' => 'Все способы', 'cash' => 'Наличные', 'card' => 'Карта', 'transfer' => 'Перевод', 'other' => 'Другое'];
$paymentStatuses = ['' => 'Все оплаты', 'unpaid' => 'Не оплачено', 'paid' => 'Оплачено', 'refunded' => 'Возврат'];
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => 'История поездок',
    'subtitle' => 'Фильтры по датам, машинкам, способу и статусу оплаты.',
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <form class="border rounded-5 p-3 p-md-4 mb-4" method="get" action="<?= base_href('/admin/toy-rental/rides') ?>">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Период</label>
                <select class="form-select" name="date_filter">
                    <?php foreach (['today' => 'Сегодня', 'yesterday' => 'Вчера', 'period' => 'Период', 'all' => 'Все'] as $key => $label): ?>
                        <option value="<?= $key ?>" <?= (string)$filters['date_filter'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">С</label>
                <input class="form-control" type="date" name="date_from" value="<?= htmlSC((string)$filters['date_from']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">По</label>
                <input class="form-control" type="date" name="date_to" value="<?= htmlSC((string)$filters['date_to']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Машинка</label>
                <select class="form-select" name="car_id">
                    <option value="0">Все машинки</option>
                    <?php foreach ($cars as $car): ?>
                        <option value="<?= (int)$car['id'] ?>" <?= (int)$filters['car_id'] === (int)$car['id'] ? 'selected' : '' ?>><?= htmlSC((string)$car['name'] . ' #' . (string)$car['number']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-dark rounded-pill w-100" type="submit">Показать</button>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="payment_method" aria-label="Способ оплаты">
                    <?php foreach ($paymentMethods as $key => $label): ?>
                        <option value="<?= $key ?>" <?= (string)$filters['payment_method'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="payment_status" aria-label="Статус оплаты">
                    <?php foreach ($paymentStatuses as $key => $label): ?>
                        <option value="<?= $key ?>" <?= (string)$filters['payment_status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>

    <div class="table-responsive border rounded-5">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Дата</th>
                    <th scope="col">Машинка</th>
                    <th scope="col">Клиент</th>
                    <th scope="col">Время</th>
                    <th scope="col">Оплата</th>
                    <th scope="col">Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rides)): ?>
                    <tr><td colspan="6" class="text-center text-body-secondary py-5">Поездки не найдены</td></tr>
                <?php endif; ?>
                <?php foreach ($rides as $ride): ?>
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
                        <td><?= (int)($ride['duration_minutes'] ?? 0) ?> мин</td>
                        <td>
                            <div><?= number_format((float)$ride['payment_amount'], 2, '.', ' ') ?></div>
                            <div class="small text-body-secondary"><?= htmlSC((string)$ride['payment_method']) ?> / <?= htmlSC((string)$ride['payment_status']) ?></div>
                        </td>
                        <td><span class="badge rounded-pill text-bg-light border"><?= htmlSC(FireballPluginToyCarRental::statusLabel((string)$ride['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
