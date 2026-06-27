<?= view()->renderPartial('admin/shell_open', [
    'title' => 'Машинки',
    'subtitle' => 'Парк машинок, статусы, цены и порядок вывода.',
    'actions' => '<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="' . base_href('/admin/toy-rental/cars/create') . '"><i class="ci-plus"></i>Добавить машинку</a>',
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <div class="table-responsive border rounded-5">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Машинка</th>
                    <th scope="col">Цвет</th>
                    <th scope="col">Статус</th>
                    <th scope="col">Цена</th>
                    <th scope="col" class="text-end">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($cars)): ?>
                    <tr><td colspan="6" class="text-center text-body-secondary py-5">Машинки не найдены</td></tr>
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
                            <div><?= number_format((float)$car['price_per_ride'], 2, '.', ' ') ?> / поездка</div>
                            <div class="small text-body-secondary"><?= number_format((float)$car['price_per_minute'], 2, '.', ' ') ?> / мин</div>
                        </td>
                        <td class="text-end">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="button" data-bs-toggle="dropdown" aria-label="Действия">
                                    <i class="ci-more-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">
                                    <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/admin/toy-rental/cars/edit/' . (int)$car['id']) ?>">
                                        <i class="ci-edit"></i><span>Редактировать</span>
                                    </a>
                                    <?php if ((string)$car['status'] !== 'hidden'): ?>
                                        <form action="<?= base_href('/admin/toy-rental/cars/hide') ?>" method="post" data-admin-delete-form data-delete-message="Скрыть машинку?" data-delete-item="<?= htmlSC((string)$car['name']) ?>">
                                            <?= get_csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= (int)$car['id'] ?>">
                                            <button class="dropdown-item d-flex align-items-center gap-2 text-danger" type="submit"><i class="ci-eye-off"></i><span>Скрыть</span></button>
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
