<?php
$isEdit = is_array($car);
$action = $isEdit ? base_href('/admin/toy-rental/cars/edit/' . (int)$car['id']) : base_href('/admin/toy-rental/cars/create');
$value = static fn(string $key, mixed $default = ''): string => htmlSC((string)($car[$key] ?? $default));
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => $isEdit ? FireballPluginToyCarRental::t('toy_rental_car_edit_title') : FireballPluginToyCarRental::t('toy_rental_car_create_title'),
    'subtitle' => FireballPluginToyCarRental::t('toy_rental_car_form_subtitle'),
    'actions' => '<a class="btn btn-outline-secondary rounded-pill" href="' . base_href('/admin/toy-rental/cars') . '">' . htmlSC(FireballPluginToyCarRental::t('toy_rental_back_to_list')) . '</a>',
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= $action ?>" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_field_name')) ?></label>
                <input class="form-control" type="text" name="name" value="<?= $value('name') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_field_number')) ?></label>
                <input class="form-control" type="text" name="number" value="<?= $value('number') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_field_color')) ?></label>
                <input class="form-control" type="text" name="color" value="<?= $value('color') ?>" placeholder="<?= htmlSC(FireballPluginToyCarRental::t('toy_rental_color_placeholder')) ?>">
                <div class="form-text"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_color_hint')) ?></div>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_field_status')) ?></label>
                <select class="form-select" name="status">
                    <?php foreach (['available' => FireballPluginToyCarRental::statusLabel('available'), 'maintenance' => FireballPluginToyCarRental::statusLabel('maintenance'), 'hidden' => FireballPluginToyCarRental::statusLabel('hidden')] as $status => $label): ?>
                        <option value="<?= $status ?>" <?= (string)($car['status'] ?? 'available') === $status ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_field_price_per_minute')) ?></label>
                <input class="form-control" type="number" name="price_per_minute" value="<?= $value('price_per_minute', '0') ?>" min="0" step="0.01">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_field_price_per_ride')) ?></label>
                <input class="form-control" type="number" name="price_per_ride" value="<?= $value('price_per_ride', $settings['default_price'] ?? '0') ?>" min="0" step="0.01">
            </div>
            <div class="col-md-8">
                <label class="form-label"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_field_image')) ?></label>
                <input class="form-control" type="text" name="image" value="<?= $value('image') ?>" placeholder="/uploads/toy-rental/car-01.jpg">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_field_sort_order')) ?></label>
                <input class="form-control" type="number" name="sort_order" value="<?= $value('sort_order', '0') ?>" min="0" step="1">
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-4">
            <button class="btn btn-dark rounded-pill" type="submit"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_save')) ?></button>
            <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/toy-rental/cars') ?>"><?= htmlSC(FireballPluginToyCarRental::t('toy_rental_cancel')) ?></a>
        </div>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
