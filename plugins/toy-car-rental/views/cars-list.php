<?= view()->renderPartial('admin/shell_open', [
    'title' => FireballPluginToyCarRental::t('toy_rental_cars_title'),
    'subtitle' => FireballPluginToyCarRental::t('toy_rental_cars_subtitle'),
    'actions' => '<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="' . base_href('/admin/toy-rental/cars/create') . '"><i class="ci-plus"></i>' . htmlSC(FireballPluginToyCarRental::t('toy_rental_add_car')) . '</a>',
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <div class="table-responsive border rounded-5" data-admin-simplebar data-simplebar-auto-hide="false">
        <table class="table align-middle mb-0">
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
                    <tr>
                        <th scope="row"><?= (int)$car['id'] ?></th>
                        <td>
                            <div class="fw-medium"><?= htmlSC((string)$car['name']) ?></div>
                            <div class="small text-body-secondary">№ <?= htmlSC((string)$car['number']) ?></div>
                        </td>
                        <td><?= htmlSC((string)$car['color']) ?></td>
                        <td><span class="badge rounded-pill text-bg-light border"><?= htmlSC(FireballPluginToyCarRental::statusLabel((string)$car['status'])) ?></span></td>
                        <td>
                            <div><?= number_format((float)$car['price_per_ride'], 2, '.', ' ') ?> <?= htmlSC(FireballPluginToyCarRental::t('toy_rental_price_per_ride_suffix')) ?></div>
                            <div class="small text-body-secondary"><?= number_format((float)$car['price_per_minute'], 2, '.', ' ') ?> <?= htmlSC(FireballPluginToyCarRental::t('toy_rental_price_per_minute_suffix')) ?></div>
                        </td>
                        <td class="text-end">
                            <div class="dropdown admin-post-actions-dropdown" data-admin-post-actions-dropdown>
                                <button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-display="static" data-bs-boundary="viewport" aria-label="<?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_actions')) ?>">
                                    <i class="ci-more-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">
                                    <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/admin/toy-rental/cars/edit/' . (int)$car['id']) ?>">
                                        <i class="ci-edit"></i><span><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_edit')) ?></span>
                                    </a>
                                    <?php if ((string)$car['status'] !== 'hidden'): ?>
                                        <form action="<?= base_href('/admin/toy-rental/cars/hide') ?>" method="post" data-admin-delete-form data-delete-message="<?= htmlSC(FireballPluginToyCarRental::t('toy_rental_hide_confirm')) ?>" data-delete-item="<?= htmlSC((string)$car['name']) ?>">
                                            <?= get_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$car['id'] ?>">
                                            <button class="dropdown-item d-flex align-items-center gap-2 text-danger" type="submit"><i class="ci-eye-off"></i><span><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_hide')) ?></span></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
