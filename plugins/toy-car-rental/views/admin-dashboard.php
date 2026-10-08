<?php
$currency = (string)$settings['currency'];
$t = static fn(string $key): string => htmlSC(FireballPluginToyCarRental::t($key));
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => FireballPluginToyCarRental::t('toy_rental_dashboard_title'),
    'subtitle' => FireballPluginToyCarRental::t('toy_rental_dashboard_subtitle'),
]) ?>
    <?php require __DIR__ . '/tabs.php'; ?>
    <div class="toy-rental-stats mb-3">
        <div><span class="text-body-secondary"><?= $t('toy_rental_stat_rides_today') ?></span> <strong data-toy-rental-stat="rides_total"><?= (int)$stats['rides_total'] ?></strong></div>
        <div><span class="text-body-secondary"><?= $t('toy_rental_stat_active') ?></span> <strong data-toy-rental-stat="active"><?= (int)$stats['active'] + (int)$stats['overdue'] ?></strong></div>
        <div><span class="text-body-secondary"><?= $t('toy_rental_stat_revenue') ?></span> <strong><span data-toy-rental-stat="revenue_total"><?= number_format((float)$stats['revenue_total'], 2, '.', ' ') ?></span> <?= htmlSC($currency) ?></strong></div>
    </div>
    <?php require __DIR__ . '/operator-panel.php'; ?>
<?= view()->renderPartial('admin/shell_close') ?>
