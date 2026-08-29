<?php
$editing = is_array($camera);
$value = static fn(string $key, mixed $default = ''): mixed => $editing ? ($camera[$key] ?? $default) : $default;
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => $editing ? 'Редактирование камеры' : 'Новая камера',
    'subtitle' => 'Канал регистратора автоматически получает RTSP, M3U8 и ссылку на постер.',
    'actions' => '<a class="btn btn-outline-secondary rounded-pill" href="' . base_href('/admin/camera-manager') . '">К камерам</a>',
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <?php if (empty($sites)): ?>
        <div class="alert alert-warning rounded-4">Сначала <a href="<?= base_href('/admin/camera-manager/sites/create') ?>">создайте объект с регистратором</a>.</div>
    <?php else: ?>
        <form class="border rounded-5 p-3 p-md-4" action="<?= base_href('/admin/camera-manager/cameras/save') ?>" method="post">
            <?= get_csrf_field() ?>
            <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$camera['id'] ?>"><?php endif; ?>
            <div class="row g-3">
                <div class="col-md-5"><label class="form-label" for="cameraSite">Объект</label><select class="form-select" id="cameraSite" name="site_id" required><?php foreach ($sites as $siteItem): ?><option value="<?= (int)$siteItem['id'] ?>" <?= (int)$selected_site_id === (int)$siteItem['id'] ? 'selected' : '' ?>><?= htmlSC((string)$siteItem['code']) ?> · <?= htmlSC((string)$siteItem['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-4"><label class="form-label" for="cameraName">Название</label><input class="form-control" id="cameraName" name="name" maxlength="190" placeholder="Площадь, север" value="<?= htmlSC((string)$value('name')) ?>"></div>
                <div class="col-md-3"><label class="form-label" for="cameraStreamKey">Ключ потока</label><input class="form-control font-monospace" id="cameraStreamKey" name="stream_key" maxlength="64" placeholder="33-01" value="<?= htmlSC((string)$value('stream_key')) ?>"><div class="form-text">Пусто — создастся автоматически.</div></div>
                <div class="col-md-3"><label class="form-label" for="cameraChannel">Канал</label><input class="form-control" id="cameraChannel" name="channel_number" type="number" min="1" max="4096" required value="<?= (int)$value('channel_number', 1) ?>"></div>
                <div class="col-md-3"><label class="form-label" for="cameraSubtype">Субпоток</label><input class="form-control" id="cameraSubtype" name="subtype" type="number" min="0" max="99" value="<?= (int)$value('subtype', 0) ?>"><div class="form-text">0 — основной, 1 — облегчённый.</div></div>
                <div class="col-md-6"><label class="form-label" for="cameraPathOverride">Индивидуальный RTSP-путь</label><input class="form-control font-monospace" id="cameraPathOverride" name="rtsp_path_override" maxlength="500" placeholder="/?channel={channel}&stream=1" value="<?= htmlSC((string)$value('rtsp_path_override')) ?>"><div class="form-text">Необязательно. Перекрывает шаблон регистратора только для этой камеры.</div></div>
                <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" id="cameraEnabled" name="enabled" value="1" type="checkbox" <?= (!$editing || !empty($camera['enabled'])) ? 'checked' : '' ?>><label class="form-check-label" for="cameraEnabled">Включить камеру в следующую публикацию</label></div></div>
            </div>
            <button class="btn btn-dark rounded-pill mt-4" type="submit">Сохранить камеру</button>
        </form>
    <?php endif; ?>

<?= view()->renderPartial('admin/shell_close') ?>
