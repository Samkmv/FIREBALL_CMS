<?php $currency = (string)($settings['currency'] ?? '₽'); ?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => 'Статистика проката',
    'subtitle' => 'Показатели за сегодня.',
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <div class="row g-4">
        <?php
        $cards = [
            ['Поездок сегодня', (int)$stats['rides_total'], 'ci-activity'],
            ['Активные', (int)$stats['active'], 'ci-clock'],
            ['Завершённые', (int)$stats['completed'], 'ci-check'],
            ['Просроченные', (int)$stats['overdue'], 'ci-alert-triangle'],
            ['Выручка', number_format((float)$stats['revenue_total'], 0, '.', ' ') . ' ' . $currency, 'ci-wallet'],
            ['Наличными', number_format((float)$stats['revenue_cash'], 0, '.', ' ') . ' ' . $currency, 'ci-banknote'],
            ['Картой', number_format((float)$stats['revenue_card'], 0, '.', ' ') . ' ' . $currency, 'ci-credit-card'],
            ['Средняя длительность', (int)$stats['avg_duration'] . ' мин', 'ci-calendar'],
            ['Популярная машинка', (string)$stats['popular_car'], 'ci-star'],
        ];
        ?>
        <?php foreach ($cards as $card): ?>
            <div class="col-md-6 col-xl-4">
                <div class="border rounded-5 p-4 h-100">
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                        <span class="text-body-secondary"><?= htmlSC((string)$card[0]) ?></span>
                        <i class="<?= htmlSC((string)$card[2]) ?> text-body-tertiary"></i>
                    </div>
                    <div class="h3 mb-0"><?= htmlSC((string)$card[1]) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
