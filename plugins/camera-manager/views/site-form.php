<?php
$editing = is_array($site);
$value = static fn(string $key, mixed $default = ''): mixed => $editing ? ($site[$key] ?? $default) : $default;
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => $editing ? 'Редактирование объекта' : 'Новый объект',
    'subtitle' => 'Сетевые параметры и единые реквизиты регистратора. Частный ключ WireGuard сюда не вводится.',
    'actions' => '<a class="btn btn-outline-secondary rounded-pill" href="' . base_href('/admin/camera-manager/sites') . '">К объектам</a>',
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= base_href('/admin/camera-manager/sites/save') ?>" method="post" autocomplete="off">
        <?= get_csrf_field() ?>
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$site['id'] ?>"><?php endif; ?>

        <h2 class="h5 mb-3">Объект</h2>
        <div class="row g-3">
            <div class="col-md-3"><label class="form-label" for="cameraSiteCode">Код</label><input class="form-control" id="cameraSiteCode" name="code" maxlength="32" required placeholder="33" value="<?= htmlSC((string)$value('code')) ?>"><div class="form-text">Используется в ключах 33-01, 33-02.</div></div>
            <div class="col-md-5"><label class="form-label" for="cameraSiteName">Название</label><input class="form-control" id="cameraSiteName" name="name" maxlength="190" required value="<?= htmlSC((string)$value('name')) ?>"></div>
            <div class="col-md-4"><label class="form-label" for="cameraSiteAddress">Адрес</label><input class="form-control" id="cameraSiteAddress" name="address" maxlength="255" value="<?= htmlSC((string)$value('address')) ?>"></div>
            <div class="col-md-4"><label class="form-label" for="cameraRouterIp">IP роутера</label><input class="form-control" id="cameraRouterIp" name="router_ip" inputmode="decimal" placeholder="192.168.33.1" value="<?= htmlSC((string)$value('router_ip')) ?>"></div>
            <div class="col-md-4"><label class="form-label" for="cameraVpnIp">VPN IP роутера</label><input class="form-control" id="cameraVpnIp" name="vpn_ip" inputmode="decimal" placeholder="10.10.0.33" value="<?= htmlSC((string)$value('vpn_ip')) ?>"></div>
            <div class="col-md-4"><label class="form-label" for="cameraWgKey">Публичный ключ WireGuard</label><input class="form-control font-monospace" id="cameraWgKey" name="wireguard_public_key" maxlength="190" value="<?= htmlSC((string)$value('wireguard_public_key')) ?>"><div class="form-text">Только PublicKey; PrivateKey не хранится в CMS.</div></div>
        </div>

        <hr class="my-4">
        <h2 class="h5 mb-3">Регистратор и RTSP</h2>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label" for="cameraRecorderIp">IP регистратора</label><input class="form-control" id="cameraRecorderIp" name="recorder_ip" required inputmode="decimal" placeholder="192.168.33.201" value="<?= htmlSC((string)$value('recorder_ip')) ?>"></div>
            <div class="col-md-2"><label class="form-label" for="cameraRtspPort">RTSP-порт</label><input class="form-control" id="cameraRtspPort" name="rtsp_port" type="number" min="1" max="65535" required value="<?= (int)$value('rtsp_port', 554) ?>"></div>
            <div class="col-md-2"><label class="form-label" for="cameraManagementPort">Порт управления</label><input class="form-control" id="cameraManagementPort" name="management_port" type="number" min="1" max="65535" value="<?= (int)$value('management_port', 37777) ?>"></div>
            <div class="col-md-4"><label class="form-label" for="cameraRtspUser">RTSP-логин</label><input class="form-control" id="cameraRtspUser" name="rtsp_username" maxlength="190" required autocomplete="off" value="<?= htmlSC((string)$value('rtsp_username')) ?>"></div>
            <div class="col-md-4"><label class="form-label" for="cameraRtspPassword">RTSP-пароль</label><input class="form-control" id="cameraRtspPassword" name="rtsp_password" type="password" autocomplete="new-password" <?= $editing ? '' : 'required' ?>><div class="form-text"><?= $editing ? 'Оставьте пустым, чтобы сохранить текущий пароль.' : 'Хранится в базе в зашифрованном виде.' ?></div></div>
            <div class="col-md-8"><label class="form-label" for="cameraRtspTemplate">Шаблон RTSP-пути</label><input class="form-control font-monospace" id="cameraRtspTemplate" name="rtsp_path_template" maxlength="500" required value="<?= htmlSC((string)$value('rtsp_path_template', '/cam/realmonitor?channel={channel}&subtype={subtype}')) ?>"><div class="form-text">Обязателен <code>{channel}</code>; доступен <code>{subtype}</code>. Амперсанд будет экранирован при публикации.</div></div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" id="cameraSiteEnabled" name="enabled" value="1" type="checkbox" <?= (!$editing || !empty($site['enabled'])) ? 'checked' : '' ?>><label class="form-check-label" for="cameraSiteEnabled">Публиковать активные камеры этого объекта</label></div></div>
        </div>

        <button class="btn btn-dark rounded-pill mt-4" type="submit">Сохранить объект</button>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
