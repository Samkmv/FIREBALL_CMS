<?= view()->renderPartial('admin/shell_open', [
    'title' => 'Настройки проката',
    'subtitle' => 'Значения по умолчанию, звуковой сигнал и автообновление.',
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= base_href('/admin/toy-rental/settings') ?>" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Длительность по умолчанию, мин</label>
                <input class="form-control" type="number" name="default_duration" min="1" step="1" value="<?= (int)$settings['default_duration'] ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Цена фиксированной поездки</label>
                <input class="form-control" type="number" name="default_price" min="0" step="0.01" value="<?= htmlSC((string)$settings['default_price']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Цена минуты по умолчанию</label>
                <input class="form-control" type="number" name="default_minute_price" min="0" step="0.01" value="<?= htmlSC((string)$settings['default_minute_price']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Валюта</label>
                <input class="form-control" type="text" name="currency" value="<?= htmlSC((string)$settings['currency']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Автообновление, сек</label>
                <input class="form-control" type="number" name="auto_refresh_seconds" min="0" step="1" value="<?= (int)$settings['auto_refresh_seconds'] ?>">
                <div class="form-text">0 — не обновлять страницу автоматически.</div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="sound_enabled" value="1" id="toySound" <?= !empty($settings['sound_enabled']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="toySound">Звуковое уведомление</label>
                </div>
            </div>
        </div>
        <button class="btn btn-dark rounded-pill mt-4" type="submit">Сохранить настройки</button>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
