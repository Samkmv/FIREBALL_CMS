<?= view()->renderPartial('admin/shell_open', [
    'title' => FireballPluginToyCarRental::t('toy_rental_active_title'),
    'subtitle' => FireballPluginToyCarRental::t('toy_rental_active_subtitle'),
]) ?>
    <?php require __DIR__ . '/tabs.php'; ?>
    <?php $operatorActiveOnly = true; require __DIR__ . '/operator-panel.php'; ?>
<?= view()->renderPartial('admin/shell_close') ?>
