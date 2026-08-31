<?php
$editing = is_array($site);
$value = static fn(string $key, mixed $default = ''): mixed => $editing ? ($site[$key] ?? $default) : $default;
$profileLabels = \Fireball\CameraManager\RtspUrlBuilder::PROFILE_LABELS;
$formActions = '<a class="btn btn-outline-secondary rounded-pill" href="' . base_href('/admin/camera-manager/sites') . '">К объектам</a>';
if ($editing) {
    $formActions .= '<a class="btn btn-dark rounded-pill" href="' . base_href('/admin/camera-manager/sites/connection/' . (int)$site['id']) . '"><i class="ci-activity me-1"></i>Подключение</a>';
}
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => $editing ? 'Редактирование объекта' : 'Новый объект',
    'subtitle' => 'Сетевые параметры и единые реквизиты регистратора. Частный ключ WireGuard сюда не вводится.',
    'actions' => '<div class="d-flex flex-wrap gap-2">' . $formActions . '</div>',
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
            <div class="col-md-4"><label class="form-label" for="cameraLanCidr">LAN CIDR</label><input class="form-control font-monospace" id="cameraLanCidr" name="lan_cidr" placeholder="192.168.34.0/24" value="<?= htmlSC((string)$value('lan_cidr')) ?>"><div class="form-text">Адрес сети, а не отдельного устройства.</div></div>
            <div class="col-md-8"><label class="form-label" for="cameraWgKey">Публичный ключ WireGuard</label><input class="form-control font-monospace" id="cameraWgKey" name="wireguard_public_key" maxlength="44" value="<?= htmlSC((string)$value('wireguard_public_key')) ?>"><div class="form-text">Только PublicKey. Никогда не вставляйте сюда PrivateKey.</div></div>
        </div>

        <hr class="my-4">
        <h2 class="h5 mb-3">Регистратор и RTSP</h2>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label" for="cameraRecorderIp">IP регистратора</label><input class="form-control" id="cameraRecorderIp" name="recorder_ip" required inputmode="decimal" placeholder="192.168.33.201" value="<?= htmlSC((string)$value('recorder_ip')) ?>"></div>
            <div class="col-md-2"><label class="form-label" for="cameraRtspPort">RTSP-порт</label><input class="form-control" id="cameraRtspPort" name="rtsp_port" type="number" min="1" max="65535" required value="<?= (int)$value('rtsp_port', 554) ?>"></div>
            <div class="col-md-2"><label class="form-label" for="cameraExternalRtspPort">Внешний RTSP</label><input class="form-control" id="cameraExternalRtspPort" name="external_rtsp_port" type="number" min="1" max="65535" placeholder="55434" value="<?= htmlSC((string)$value('external_rtsp_port')) ?>"></div>
            <div class="col-md-2"><label class="form-label" for="cameraManagementPort">Порт управления</label><input class="form-control" id="cameraManagementPort" name="management_port" type="number" min="1" max="65535" placeholder="8000 или 37777" value="<?= htmlSC((string)$value('management_port')) ?>"></div>
            <div class="col-md-2"><label class="form-label" for="cameraExternalManagementPort">Внешний management</label><input class="form-control" id="cameraExternalManagementPort" name="external_management_port" type="number" min="1" max="65535" placeholder="35773" value="<?= htmlSC((string)$value('external_management_port')) ?>"></div>
            <div class="col-md-4"><label class="form-label" for="cameraRtspUser">RTSP-логин</label><input class="form-control" id="cameraRtspUser" name="rtsp_username" maxlength="190" required autocomplete="off" value="<?= htmlSC((string)$value('rtsp_username')) ?>"></div>
            <div class="col-md-4"><label class="form-label" for="cameraRtspPassword">RTSP-пароль</label><input class="form-control" id="cameraRtspPassword" name="rtsp_password" type="password" autocomplete="new-password" <?= $editing ? '' : 'required' ?>><div class="form-text"><?= $editing ? 'Оставьте пустым, чтобы сохранить текущий пароль.' : 'Хранится в базе в зашифрованном виде.' ?></div></div>
            <div class="col-md-4"><label class="form-label" for="cameraRtspProfile">RTSP profile</label><select class="form-select" id="cameraRtspProfile" name="rtsp_profile"><?php foreach ($profileLabels as $profileKey => $profileLabel): ?><option value="<?= htmlSC($profileKey) ?>" <?= (string)$value('rtsp_profile', 'custom') === $profileKey ? 'selected' : '' ?>><?= htmlSC($profileLabel) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label" for="cameraRtspStreamMode">Режим потока</label><select class="form-select" id="cameraRtspStreamMode" name="rtsp_stream_mode"><option value="camera" <?= (string)$value('rtsp_stream_mode', 'camera') === 'camera' ? 'selected' : '' ?>>По настройке камеры</option><option value="main" <?= (string)$value('rtsp_stream_mode') === 'main' ? 'selected' : '' ?>>Всегда основной</option><option value="sub" <?= (string)$value('rtsp_stream_mode') === 'sub' ? 'selected' : '' ?>>Всегда дополнительный</option></select></div>
            <div class="col-md-8"><label class="form-label" for="cameraRtspTemplate">Custom RTSP-шаблон</label><input class="form-control font-monospace" id="cameraRtspTemplate" name="rtsp_path_template" maxlength="500" required value="<?= htmlSC((string)$value('rtsp_path_template', '/cam/realmonitor?channel={channel}&subtype={subtype}')) ?>"><div class="form-text">Старые объекты продолжают использовать этот шаблон. Обязателен <code>{channel}</code>; доступны <code>{subtype}</code> и <code>{stream}</code>.</div></div>
            <div class="col-md-4"><label class="form-label" for="cameraNetworkStatus">Статус сетевой настройки</label><select class="form-select" id="cameraNetworkStatus" name="network_setup_status"><option value="not_configured" <?= (string)$value('network_setup_status', 'not_configured') === 'not_configured' ? 'selected' : '' ?>>Не настроено</option><option value="instructions_ready" <?= (string)$value('network_setup_status') === 'instructions_ready' ? 'selected' : '' ?>>Инструкции готовы</option><option value="configured" <?= (string)$value('network_setup_status') === 'configured' ? 'selected' : '' ?>>Настроено вручную</option><option value="verified" <?= (string)$value('network_setup_status') === 'verified' ? 'selected' : '' ?>>Проверено</option></select></div>
            <div class="col-md-8"><label class="form-label" for="cameraNetworkNotes">Заметки по подключению</label><textarea class="form-control" id="cameraNetworkNotes" name="network_notes" rows="2" maxlength="4000"><?= htmlSC((string)$value('network_notes')) ?></textarea></div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" id="cameraSiteEnabled" name="enabled" value="1" type="checkbox" <?= (!$editing || !empty($site['enabled'])) ? 'checked' : '' ?>><label class="form-check-label" for="cameraSiteEnabled">Публиковать активные камеры этого объекта</label></div></div>
        </div>

        <button class="btn btn-dark rounded-pill mt-4" type="submit">Сохранить объект</button>
    </form>

<?= view()->renderPartial('admin/shell_close') ?>
