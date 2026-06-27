<?php
$isEdit = is_array($car);
$action = $isEdit ? base_href('/admin/toy-rental/cars/edit/' . (int)$car['id']) : base_href('/admin/toy-rental/cars/create');
$value = static fn(string $key, mixed $default = ''): string => htmlSC((string)($car[$key] ?? $default));
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => $isEdit ? 'Редактирование машинки' : 'Новая машинка',
    'subtitle' => 'Название, номер, цвет, статус и цена проката.',
    'actions' => '<a class="btn btn-outline-secondary rounded-pill" href="' . base_href('/admin/toy-rental/cars') . '">К списку</a>',
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= $action ?>" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Название</label>
                <input class="form-control" type="text" name="name" value="<?= $value('name') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Номер</label>
                <input class="form-control" type="text" name="number" value="<?= $value('number') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Цвет</label>
                <input class="form-control" type="text" name="color" value="<?= $value('color') ?>" placeholder="red / #ff0000">
            </div>
            <div class="col-md-4">
                <label class="form-label">Статус</label>
                <select class="form-select" name="status">
                    <?php foreach (['available' => 'Свободна', 'maintenance' => 'Обслуживание', 'hidden' => 'Скрыта'] as $status => $label): ?>
                        <option value="<?= $status ?>" <?= (string)($car['status'] ?? 'available') === $status ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Цена за минуту</label>
                <input class="form-control" type="number" name="price_per_minute" value="<?= $value('price_per_minute', '0') ?>" min="0" step="0.01">
            </div>
            <div class="col-md-4">
                <label class="form-label">Цена за поездку</label>
                <input class="form-control" type="number" name="price_per_ride" value="<?= $value('price_per_ride', $settings['default_price'] ?? '0') ?>" min="0" step="0.01">
            </div>
            <div class="col-md-8">
                <label class="form-label">Изображение</label>
                <input class="form-control" type="text" name="image" value="<?= $value('image') ?>" placeholder="/uploads/toy-rental/car-01.jpg">
            </div>
            <div class="col-md-4">
                <label class="form-label">Порядок</label>
                <input class="form-control" type="number" name="sort_order" value="<?= $value('sort_order', '0') ?>" min="0" step="1">
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-4">
            <button class="btn btn-dark rounded-pill" type="submit">Сохранить</button>
            <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/toy-rental/cars') ?>">Отмена</a>
        </div>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
