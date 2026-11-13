<?php
$base = '/admin/plugins/vpn-manager-v2/servers/' . (int)$server['id'] . '/xray';
?>
<?= view()->renderPartial('admin/shell_open', ['title' => $title, 'subtitle' => 'Проверка переноса клиентов в самостоятельный Xray-core']) ?>
<?php require __DIR__ . '/partials/tabs.php'; ?>
<section class="border rounded-5 p-3 p-md-4 mb-3">
    <h2 class="h5">1. Подготовить сервер</h2>
    <p>Установите Xray и FIREBALL Agent по инструкции <code>plugins/vpn-manager-v2/docs/native-xray.md</code>.
        Сохраните адрес, порт, UUID, пароли и ключи REALITY из прежней конфигурации.
        Входящим подключениям нужны прежние числовые идентификаторы в тегах <code>inbound-24</code>.</p>
    <p>В настройках сервера выберите Xray-core · FIREBALL Agent и укажите отдельный HTTPS API URL агента.
        Основной адрес сервера должен соответствовать адресу подключения клиентов.</p>
    <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers/edit/' . (int)$server['id'])) ?>">Настройки сервера</a>
</section>
<section class="border rounded-5 p-3 p-md-4 mb-3">
    <h2 class="h5">2. Сверить подключения</h2>
    <p>Проверка сравнивает существующие ключи, протоколы и порты. Посторонние пользователи сохраняются.
        Совпадающие подключения перейдут под управление CMS. Недостающие можно восстановить через обычную синхронизацию.</p>
    <?php if ($error !== ''): ?><div class="alert alert-danger rounded-4" role="alert"><?= htmlSC($error) ?></div><?php endif; ?>
    <form method="post" action="<?= htmlSC(base_href($base . '/preview')) ?>">
        <?= get_csrf_field() ?>
        <button class="btn btn-dark rounded-pill" type="submit">Проверить перенос</button>
    </form>
    <?php if ($result !== []): ?>
        <dl class="row mt-3 mb-0">
            <dt class="col-8">Подтверждены в Xray</dt><dd class="col-4"><?= (int)$result['matched'] ?></dd>
            <dt class="col-8">Нужно восстановить</dt><dd class="col-4"><?= (int)$result['missing'] ?></dd>
        </dl>
        <?php if (!empty($result['adopted'])): ?>
            <div class="alert alert-success rounded-4 mt-3">Сервер передан под управление агента.
                Запустите восстановление, чтобы досоздать недостающие подключения, и проверьте клиентское приложение.</div>
            <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers/' . (int)$server['id'] . '/recovery')) ?>">Восстановление подключений</a>
        <?php else: ?>
            <form method="post" action="<?= htmlSC(base_href($base . '/adopt')) ?>" class="mt-3">
                <?= get_csrf_field() ?>
                <input type="hidden" name="fingerprint" value="<?= htmlSC((string)$result['fingerprint']) ?>">
                <p class="small text-body-secondary">После передачи агент применяет сохранённые сроки, блокировки и квоты.
                    Ограничение IP требует отдельной реализации и блокирует перенос, если оно включено.</p>
                <button class="btn btn-dark rounded-pill" type="submit">Передать управление агенту</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php if (($server['auth_type'] ?? '') === 'xray'): ?>
<section class="border rounded-5 p-3 p-md-4 mb-3">
    <h2 class="h5">3. Управлять входящими подключениями</h2>
    <p>После передачи сервера под управление можно добавить или изменить входящее подключение в формате Xray JSON.
        Пользователи сохраняются автоматически. Удаление разрешено после освобождения подключения от клиентов.
        Изменение активного подключения может прервать его текущие соединения.</p>
    <form method="post" action="<?= htmlSC(base_href($base . '/inbound-preview')) ?>" autocomplete="off">
        <?= get_csrf_field() ?>
        <label class="form-label" for="nativeInboundJson">Конфигурация входящего подключения</label>
        <textarea id="nativeInboundJson" name="inbound_json" class="form-control font-monospace mb-3" rows="8" required maxlength="131072" spellcheck="false" placeholder='{"tag":"inbound-25","listen":"0.0.0.0","port":8443,"protocol":"vless","settings":{"decryption":"none"},"streamSettings":{"network":"tcp","security":"reality"}}'></textarea>
        <div class="form-text mb-3">Для REALITY при создании укажите privateKey, serverNames, shortIds и целевой адрес.
            Существующие privateKey и TLS-сертификаты сохранятся, если вы их не передаёте. Поле settings.clients передавать не нужно.
            Для удаления достаточно тега.</div>
        <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="delete" value="1"><span class="form-check-label">Удалить указанное входящее подключение</span></label>
        <button class="btn btn-outline-secondary rounded-pill" type="submit">Проверить изменение</button>
    </form>
    <?php if ($inboundResult !== []): ?>
        <div class="alert alert-<?= !empty($inboundResult['applied']) ? 'success' : 'info' ?> rounded-4 mt-3">
            <?= !empty($inboundResult['applied']) ? 'Xray подтвердил изменение.' : 'Конфигурация прошла проверку Xray.' ?>
            <?= htmlSC((string)$inboundResult['tag']) ?> · Сохранено клиентов <?= (int)$inboundResult['clients_preserved'] ?>
        </div>
        <?php if (empty($inboundResult['applied'])): ?>
        <form method="post" action="<?= htmlSC(base_href($base . '/inbound-apply')) ?>">
            <?= get_csrf_field() ?>
            <input type="hidden" name="ticket" value="<?= htmlSC((string)$inboundResult['ticket']) ?>">
            <button class="btn btn-dark rounded-pill" type="submit"><?= !empty($inboundResult['delete']) ? 'Подтвердить удаление' : 'Применить к Xray' ?></button>
        </form>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; ?>
<?= view()->renderPartial('admin/shell_close') ?>
