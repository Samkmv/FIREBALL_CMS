<?= view()->renderPartial('admin/shell_open', [
    'title' => 'Настройки Camera Manager',
    'subtitle' => 'Безопасная синхронизация с отдельным RTSP-сервером.',
    'actions' => '<form action="' . base_href('/admin/camera-manager/settings/test-connection') . '" method="post">'
        . get_csrf_field() . '<button class="btn btn-outline-secondary rounded-pill" type="submit"><i class="ci-activity me-1"></i>Проверить подключение</button></form>',
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <form class="border rounded-5 p-3 p-md-4" action="<?= base_href('/admin/camera-manager/settings') ?>" method="post">
        <?= get_csrf_field() ?>
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label" for="cameraConnectionMode">Режим подключения</label><select class="form-select" id="cameraConnectionMode" name="connection_mode"><option value="pull" <?= $settings['connection_mode'] === 'pull' ? 'selected' : '' ?>>HTTPS: RTSP-сервер забирает настройки</option><option value="ssh" <?= $settings['connection_mode'] === 'ssh' ? 'selected' : '' ?>>Отдельный сервер по SSH</option><option value="local" <?= $settings['connection_mode'] === 'local' ? 'selected' : '' ?>>Локальный файл</option></select></div>
            <div class="col-md-8"><label class="form-label" for="cameraHlsBase">Базовый HLS URL</label><input class="form-control font-monospace" id="cameraHlsBase" name="hls_base_url" type="url" required value="<?= htmlSC((string)$settings['hls_base_url']) ?>"></div>
        </div>

        <div class="border rounded-4 p-3 mt-4">
            <h2 class="h5">HTTPS pull — для SprintHost и FTP</h2>
            <p class="text-body-secondary">RTSP-сервер сам обращается к CMS. Входящий SSH-доступ к хостингу и выполнение команд PHP не требуются.</p>
            <div class="row g-3">
                <div class="col-md-7"><label class="form-label" for="cameraPullEndpoint">Endpoint CMS</label><input class="form-control font-monospace" id="cameraPullEndpoint" readonly value="<?= htmlSC(base_url('/api/camera-manager/pull')) ?>"><div class="form-text">Этот HTTPS-адрес указывается в конфигурации агента на RTSP-сервере.</div></div>
                <div class="col-md-5"><label class="form-label" for="cameraPullToken">Секретный токен</label><input class="form-control font-monospace" id="cameraPullToken" name="pull_token" type="password" autocomplete="new-password" minlength="64" maxlength="64" pattern="[A-Fa-f0-9]{64}" title="Ровно 64 символа: цифры 0–9 и буквы a–f" placeholder="<?= !empty($settings['pull_token_configured']) ? 'Уже настроен — оставьте пустым' : '64 шестнадцатеричных символа' ?>"><div class="form-text">Создайте командой <code>openssl rand -hex 32</code>. CMS хранит только SHA-256 хеш.</div></div>
                <div class="col-12 d-flex flex-wrap align-items-center gap-3"><button class="btn btn-dark rounded-pill" type="submit" formaction="<?= base_href('/admin/camera-manager/settings/token') ?>" formmethod="post">Сохранить только токен</button><span class="badge rounded-pill text-bg-<?= !empty($settings['pull_token_configured']) ? 'success' : 'warning' ?>"><?= !empty($settings['pull_token_configured']) ? 'Токен настроен' : 'Токен ещё не сохранён' ?></span><span class="small text-body-secondary">После записи плагин сразу проверит токен чтением из базы.</span></div>
            </div>
            <dl class="row small mt-3 mb-0">
                <dt class="col-md-3">Последнее обращение агента</dt><dd class="col-md-9 mb-1"><?= htmlSC((string)($settings['pull_last_seen_at'] ?: 'ещё не было')) ?></dd>
                <dt class="col-md-3">Применённая ревизия</dt><dd class="col-md-9 mb-1"><?= (int)$settings['pull_last_revision'] ?> из <?= (int)$settings['pull_revision'] ?></dd>
                <dt class="col-md-3">Последний результат</dt><dd class="col-md-9 mb-0"><?= htmlSC((string)($settings['pull_last_status'] ?: 'нет данных')) ?><?= !empty($settings['pull_last_message']) ? ' · ' . htmlSC((string)$settings['pull_last_message']) : '' ?></dd>
            </dl>
        </div>

        <div class="border rounded-4 p-3 mt-4">
            <h2 class="h5">SSH-сервер — альтернативный режим</h2>
            <div class="row g-3">
                <div class="col-md-5"><label class="form-label" for="cameraSshHost">Адрес</label><input class="form-control font-monospace" id="cameraSshHost" name="ssh_host" value="<?= htmlSC((string)$settings['ssh_host']) ?>" placeholder="rtsp.ddns.net"></div>
                <div class="col-md-2"><label class="form-label" for="cameraSshPort">Порт</label><input class="form-control" id="cameraSshPort" name="ssh_port" type="number" min="1" max="65535" value="<?= (int)$settings['ssh_port'] ?>"></div>
                <div class="col-md-5"><label class="form-label" for="cameraSshUser">Пользователь</label><input class="form-control font-monospace" id="cameraSshUser" name="ssh_user" value="<?= htmlSC((string)$settings['ssh_user']) ?>" placeholder="camera-sync"></div>
                <div class="col-md-4"><label class="form-label" for="cameraSshBinary">SSH-клиент на сервере CMS</label><input class="form-control font-monospace" id="cameraSshBinary" name="ssh_binary" value="<?= htmlSC((string)$settings['ssh_binary']) ?>"></div>
                <div class="col-md-4"><label class="form-label" for="cameraIdentityFile">Приватный SSH-ключ</label><input class="form-control font-monospace" id="cameraIdentityFile" name="ssh_identity_file" value="<?= htmlSC((string)$settings['ssh_identity_file']) ?>"><div class="form-text">В базе хранится только путь, не содержимое ключа.</div></div>
                <div class="col-md-4"><label class="form-label" for="cameraKnownHosts">known_hosts</label><input class="form-control font-monospace" id="cameraKnownHosts" name="ssh_known_hosts_file" value="<?= htmlSC((string)$settings['ssh_known_hosts_file']) ?>"><div class="form-text">StrictHostKeyChecking всегда включён.</div></div>
            </div>
        </div>

        <div class="border rounded-4 p-3 mt-4">
            <h2 class="h5">Локальный режим</h2>
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label" for="cameraStreamsPath">Путь к streams.pl</label><input class="form-control font-monospace" id="cameraStreamsPath" name="streams_file_path" value="<?= htmlSC((string)$settings['streams_file_path']) ?>"></div>
                <div class="col-md-4"><label class="form-label" for="cameraPerlBinary">Perl</label><input class="form-control font-monospace" id="cameraPerlBinary" name="perl_binary" value="<?= htmlSC((string)$settings['perl_binary']) ?>"></div>
                <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" id="cameraSudo" name="use_sudo" value="1" type="checkbox" <?= !empty($settings['use_sudo']) ? 'checked' : '' ?>><label class="form-check-label" for="cameraSudo">Использовать <code>sudo -n</code> для локального systemctl</label></div></div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-6"><label class="form-label" for="cameraService">systemd-служба</label><input class="form-control font-monospace" id="cameraService" name="service_name" required value="<?= htmlSC((string)$settings['service_name']) ?>"><div class="form-text">В удалённых режимах имя дополнительно фиксируется внутри агента.</div></div>
            <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch mb-2"><input class="form-check-input" id="cameraRestart" name="restart_on_publish" value="1" type="checkbox" <?= !empty($settings['restart_on_publish']) ? 'checked' : '' ?>><label class="form-check-label" for="cameraRestart">Перезапускать службу после публикации</label></div></div>
        </div>
        <button class="btn btn-dark rounded-pill mt-4" type="submit">Сохранить настройки</button>
    </form>

    <div class="border rounded-5 p-4 mt-4">
        <h2 class="h5">Агент RTSP-сервера</h2>
        <p class="mb-2">Для SprintHost используется <code>server/fireball-camera-pull</code> и systemd timer. Агент принимает только управляемый блок, проверяет неизменность остального файла, выполняет <code>perl -c</code>, создаёт резервную копию и делает атомарную замену.</p>
        <p class="mb-0 text-body-secondary">SSH-агент сохранён как альтернативный вариант для серверов с полноценным SSH-доступом.</p>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
