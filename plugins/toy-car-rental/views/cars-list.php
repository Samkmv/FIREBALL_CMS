<?= view()->renderPartial('admin/shell_open', [
    'title' => FireballPluginToyCarRental::t('toy_rental_cars_title'),
    'subtitle' => FireballPluginToyCarRental::t('toy_rental_cars_subtitle'),
    'actions' => '<a class="btn btn-primary d-inline-flex align-items-center gap-2" href="' . base_href('/admin/toy-rental/cars/create') . '"><i class="ci-plus"></i>' . htmlSC(FireballPluginToyCarRental::t('toy_rental_add_car')) . '</a>',
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <?php $mobileCards = []; ob_start(); ?>
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_car')) ?></th>
                    <th scope="col"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_field_color')) ?></th>
                    <th scope="col"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_status')) ?></th>
                    <th scope="col"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_price')) ?></th>
                    <th scope="col" class="text-end"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_actions')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cars)): ?>
                    <tr><td colspan="6" class="text-center text-body-secondary py-5"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_cars_empty')) ?></td></tr>
                <?php endif; ?>
                <?php foreach ($cars as $car): ?>
                    <?php
                    $actionsHtml = plugin_view('toy-car-rental', 'car-actions', ['car' => $car], false);
                    $price = number_format(FireballPluginToyCarRental::minutePrice($car, $settings), 2, '.', ' ') . ' ' . (string)$settings['currency'] . ' ' . FireballPluginToyCarRental::t('toy_rental_price_per_minute_suffix');
                    $mobileCards[] = [
                        'id' => (int)$car['id'],
                        'title' => (string)$car['name'],
                        'extra_fields' => [
                            ['label' => FireballPluginToyCarRental::t('toy_rental_field_number'), 'value' => (string)$car['number']],
                            ['label' => FireballPluginToyCarRental::t('toy_rental_field_color'), 'value' => (string)$car['color']],
                            ['label' => FireballPluginToyCarRental::t('toy_rental_table_price'), 'value' => $price],
                        ],
                        'status' => [['label' => FireballPluginToyCarRental::statusLabel((string)$car['status']), 'class' => 'text-secondary bg-secondary-subtle']],
                        'status_label' => FireballPluginToyCarRental::t('toy_rental_table_status'),
                        'actions' => $actionsHtml,
                    ];
                    ?>
                    <tr>
                        <th scope="row"><?= (int)$car['id'] ?></th>
                        <td>
                            <div class="fw-medium"><?= htmlSC((string)$car['name']) ?></div>
                            <div class="small text-body-secondary">№ <?= htmlSC((string)$car['number']) ?></div>
                        </td>
                        <td><?= htmlSC((string)$car['color']) ?></td>
                        <td><span class="badge rounded-pill text-bg-light border"><?= htmlSC(FireballPluginToyCarRental::statusLabel((string)$car['status'])) ?></span></td>
                        <td>
                            <div><?= htmlSC($price) ?></div>
                        </td>
                        <td class="text-end">
                            <?= $actionsHtml ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
    <?php $tableContent = ob_get_clean(); ?>
    <?= view()->renderPartial('admin/partials/table', [
        'content' => $tableContent,
        'mobile_cards' => $mobileCards,
        'empty_text' => FireballPluginToyCarRental::t('toy_rental_cars_empty'),
        'wrapper_attributes' => ['data-admin-simplebar' => true, 'data-simplebar-auto-hide' => 'false'],
    ]) ?>

<?= view()->renderPartial('admin/shell_close') ?>
