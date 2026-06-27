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
            <?php $isOverdue = (string)$ride['status'] === 'overdue'; ?>
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
                        <div class="d-flex justify-content-between align-items-center border rounded-4 p-3 mb-3">
                            <span class="small text-body-secondary">Таймер</span>
                            <span class="h4 mb-0 toy-rental-timer" data-toy-rental-timer data-end="<?= htmlSC(date('c', strtotime((string)$ride['planned_end_at']))) ?>">--:--</span>
                        </div>
                        <form action="<?= base_href('/admin/toy-rental/rides/complete') ?>" method="post">
                            <?= get_csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$ride['id'] ?>">
                            <button class="btn btn-dark rounded-pill w-100" type="submit">Завершить</button>
                        </form>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
