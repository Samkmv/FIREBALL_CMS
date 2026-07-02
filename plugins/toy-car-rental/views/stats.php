<?php $currency = (string)($settings['currency'] ?? '₽'); ?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => FireballPluginToyCarRental::t('toy_rental_stats_title'),
    'subtitle' => FireballPluginToyCarRental::t('toy_rental_stats_subtitle'),
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <div class="row g-4">
        <?php
        $cards = [
            [FireballPluginToyCarRental::t('toy_rental_stat_rides_today'), (int)$stats['rides_total'], 'ci-activity'],
            [FireballPluginToyCarRental::t('toy_rental_stats_fixed'), (int)$stats['fixed'], 'ci-clock'],
            [FireballPluginToyCarRental::t('toy_rental_stats_metered'), (int)$stats['metered'], 'ci-activity'],
            [FireballPluginToyCarRental::t('toy_rental_stat_active'), (int)$stats['active'], 'ci-clock'],
            [FireballPluginToyCarRental::t('toy_rental_stats_completed'), (int)$stats['completed'], 'ci-check'],
            [FireballPluginToyCarRental::t('toy_rental_stats_overdue'), (int)$stats['overdue'], 'ci-alert-triangle'],
            [FireballPluginToyCarRental::t('toy_rental_stats_paid'), (int)$stats['paid'], 'ci-check'],
            [FireballPluginToyCarRental::t('toy_rental_stats_unpaid'), (int)$stats['unpaid'], 'ci-x'],
            [FireballPluginToyCarRental::t('toy_rental_stat_revenue'), number_format((float)$stats['revenue_total'], 0, '.', ' ') . ' ' . $currency, 'ci-wallet'],
            [FireballPluginToyCarRental::t('toy_rental_stats_cash'), number_format((float)$stats['revenue_cash'], 0, '.', ' ') . ' ' . $currency, 'ci-banknote'],
            [FireballPluginToyCarRental::t('toy_rental_stats_card'), number_format((float)$stats['revenue_card'], 0, '.', ' ') . ' ' . $currency, 'ci-credit-card'],
            [FireballPluginToyCarRental::t('toy_rental_stats_avg_duration'), (int)$stats['avg_duration'] . ' ' . FireballPluginToyCarRental::t('toy_rental_min_short'), 'ci-calendar'],
            [FireballPluginToyCarRental::t('toy_rental_stats_popular_car'), (string)$stats['popular_car'], 'ci-star'],
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
