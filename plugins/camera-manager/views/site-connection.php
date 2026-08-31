<?php
$siteId = (int)$site['id'];
$latestByKey = [];
foreach ($diagnostic_jobs as $job) {
    $label = (string)($job['parameters']['label'] ?? '');
    $key = (string)$job['operation'] . ':' . $label;
    if (!isset($latestByKey[$key])) {
        $latestByKey[$key] = $job;
    }
}
$findJob = static function (string $operation, string $label = '') use ($diagnostic_jobs): ?array {
    foreach ($diagnostic_jobs as $job) {
        if ((string)$job['operation'] !== $operation) {
            continue;
        }
        if ($label !== '' && (string)($job['parameters']['label'] ?? '') !== $label) {
            continue;
        }
        return $job;
    }
    return null;
};
$statusBadge = static function (?array $job): string {
    if ($job === null) {
        return '<span class="badge rounded-pill text-bg-secondary">не проверено</span>';
    }
    $status = (string)$job['status'];
    $tone = match ($status) {
        'success' => 'success',
        'warning', 'pending', 'running' => 'warning',
        'failed' => 'danger',
        default => 'secondary',
    };
    $labels = ['success' => 'успешно', 'warning' => 'предупреждение', 'pending' => 'в очереди', 'running' => 'выполняется', 'failed' => 'ошибка'];
    return '<span class="badge rounded-pill text-bg-' . $tone . '">' . htmlSC($labels[$status] ?? $status) . '</span>';
};
$wgJob = $findJob('wg_peer');
$routeJob = $findJob('route_check');
$recorderJob = $findJob('tcp_check', 'RTSP TCP');
$rtspJob = $findJob('rtsp_probe');
$hlsJob = $findJob('hls_check');
$portsJob = $findJob('port_discovery');
$activeCameraCount = count(array_filter($cameras, static fn(array $camera): bool => !empty($camera['enabled'])));
$copyBlock = static function (string $id, string $contents): string {
    if ($contents === '') {
        return '<div class="text-body-secondary">Не настроено.</div>';
    }
    return '<div class="position-relative"><button class="btn btn-sm btn-outline-secondary rounded-pill mb-2" type="button" data-camera-copy="' . htmlSC($id) . '">Copy</button>'
        . '<pre id="' . htmlSC($id) . '" class="border rounded-4 p-3 bg-body-tertiary overflow-auto mb-0"><code>' . htmlSC($contents) . '</code></pre></div>';
};
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => 'Подключение · ' . (string)$site['code'],
    'subtitle' => 'Пошаговая настройка WireGuard, регистратора, RTSP, публикации и HLS.',
    'actions' => '<div class="d-flex flex-wrap gap-2">'
        . '<a class="btn btn-outline-secondary rounded-pill" href="' . base_href('/admin/camera-manager/sites/edit/' . $siteId) . '">Параметры объекта</a>'
        . '<form action="' . base_href('/admin/camera-manager/sites/diagnostics') . '" method="post">' . get_csrf_field()
        . '<input type="hidden" name="site_id" value="' . $siteId . '"><button class="btn btn-dark rounded-pill" type="submit"><i class="ci-activity me-1"></i>Проверить подключение</button></form></div>',
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <?php if ($network_error !== ''): ?>
        <div class="alert alert-danger rounded-4"><?= htmlSC($network_error) ?></div>
    <?php endif; ?>
    <?php if (!empty($network['missing'])): ?>
        <div class="alert alert-warning rounded-4"><strong>Для полного генератора заполните:</strong> <?= htmlSC(implode(', ', $network['missing'])) ?>.</div>
    <?php endif; ?>
    <?php if (!in_array('diagnostics_v1', $settings['pull_agent_capabilities'] ?? [], true)): ?><div class="alert alert-info rounded-4">Публикация совместима с текущим агентом, но удалённая диагностика начнёт выполняться после установки <code>fireball-camera-diagnostics</code> и обновлённого pull-agent на RTSP-сервере.</div><?php endif; ?>
    <?php foreach (($network['warnings'] ?? []) as $warning): ?><div class="alert alert-warning rounded-4"><?= htmlSC((string)$warning) ?></div><?php endforeach; ?>
    <div class="alert alert-warning rounded-4"><strong>Безопасный режим:</strong> Camera Manager не хранит PrivateKey, не изменяет <code>/etc/wireguard/wg0.conf</code>, не запускает iptables и не перезапускает WireGuard. Root-команды применяются только вручную.</div>

    <div class="row g-3 mb-4">
        <?php foreach ([
            ['WireGuard', $statusBadge($wgJob)],
            ['Маршрут', $statusBadge($routeJob)],
            ['Регистратор', $statusBadge($recorderJob)],
            ['RTSP', $statusBadge($rtspJob)],
            ['Публикация', '<span class="badge rounded-pill text-bg-' . ((int)$settings['pull_last_revision'] === (int)$settings['pull_revision'] ? 'success' : 'warning') . '">' . (int)$settings['pull_last_revision'] . ' из ' . (int)$settings['pull_revision'] . '</span>'],
            ['Камеры', '<span class="badge rounded-pill text-bg-info">' . $activeCameraCount . ' активных</span>'],
        ] as $metric): ?>
            <div class="col-6 col-lg-2"><div class="border rounded-4 p-3 h-100"><div class="small text-body-secondary mb-2"><?= htmlSC($metric[0]) ?></div><?= $metric[1] ?></div></div>
        <?php endforeach; ?>
    </div>

    <div class="vstack gap-3">
        <section class="border rounded-5 p-3 p-md-4" id="connection-parameters">
            <div class="d-flex justify-content-between gap-3"><div><div class="text-body-secondary small">Шаг 1 из 10</div><h2 class="h5">Параметры объекта</h2></div><a class="btn btn-sm btn-outline-secondary rounded-pill align-self-start" href="<?= base_href('/admin/camera-manager/sites/edit/' . $siteId) ?>">Изменить</a></div>
            <div class="row g-3 small"><div class="col-md-3">LAN<br><code><?= htmlSC((string)($site['lan_cidr'] ?? 'не настроено')) ?></code></div><div class="col-md-3">Роутер<br><code><?= htmlSC((string)($site['router_ip'] ?? 'не настроено')) ?></code></div><div class="col-md-3">VPN<br><code><?= htmlSC((string)($site['vpn_ip'] ?? 'не настроено')) ?></code></div><div class="col-md-3">Регистратор<br><code><?= htmlSC((string)$site['recorder_ip']) ?>:<?= (int)$site['rtsp_port'] ?></code></div></div>
        </section>

        <section class="border rounded-5 p-3 p-md-4" id="wireguard">
            <div class="text-body-secondary small">Шаг 2 из 10</div><h2 class="h5">WireGuard: роутер и peer сервера</h2>
            <p>Создайте WireGuard-подключение на Keenetic/Netcraze вручную, затем добавьте peer объекта на сервере.</p>
            <div class="row g-3"><div class="col-lg-6"><h3 class="h6">Параметры роутера</h3><?= $copyBlock('camera-router-config', (string)($network['router_config'] ?? '')) ?></div><div class="col-lg-6"><h3 class="h6">Peer для сервера</h3><?= $copyBlock('camera-peer-block', (string)($network['peer_block'] ?? '')) ?></div></div>
            <?php if ($wgJob !== null && !empty($wgJob['result'])): ?><dl class="row small mt-3 mb-0"><dt class="col-md-3">Peer</dt><dd class="col-md-9"><?= !empty($wgJob['result']['found']) ? 'найден' : 'не найден' ?></dd><dt class="col-md-3">Allowed IPs</dt><dd class="col-md-9"><code><?= htmlSC(implode(', ', $wgJob['result']['allowed_ips'] ?? [])) ?></code></dd><dt class="col-md-3">Latest handshake age</dt><dd class="col-md-9"><?= isset($wgJob['result']['latest_handshake_age']) ? (int)$wgJob['result']['latest_handshake_age'] . ' сек.' : 'нет данных' ?></dd><dt class="col-md-3">Transfer RX / TX</dt><dd class="col-md-9"><?= (int)($wgJob['result']['rx_bytes'] ?? 0) ?> / <?= (int)($wgJob['result']['tx_bytes'] ?? 0) ?> bytes</dd></dl><?php endif; ?>
            <div class="alert alert-danger rounded-4 mt-3 mb-0">Никогда не вставляйте PrivateKey роутера или сервера в FIREBALL CMS.</div>
        </section>

        <section class="border rounded-5 p-3 p-md-4" id="keenetic-firewall">
            <div class="text-body-secondary small">Шаг 3 из 10</div><h2 class="h5">Keenetic / Netcraze firewall</h2>
            <p>Handshake WireGuard не гарантирует доступ к LAN регистратора. Для текущей рабочей схемы создайте разрешающие правила на роутере:</p>
            <ul class="mb-2"><?php foreach (($network['firewall_rules'] ?? []) as $rule): ?><li><code><?= htmlSC((string)$rule) ?></code></li><?php endforeach; ?></ul>
            <div class="alert alert-info rounded-4 mb-0">Это совместимая рабочая схема MAXIPAPA. После запуска доступ можно ограничить более строгими source/destination. Плагин не подключается к Keenetic API.</div>
        </section>

        <section class="border rounded-5 p-3 p-md-4" id="server-route">
            <div class="text-body-secondary small">Шаг 4 из 10</div><h2 class="h5">Маршрут на RTSP-сервере</h2>
            <div class="row g-3"><div class="col-lg-6"><h3 class="h6">Применить вручную от root</h3><?= $copyBlock('camera-route-command', (string)($network['route_command'] ?? '')) ?></div><div class="col-lg-6"><h3 class="h6">Проверить</h3><?= $copyBlock('camera-route-check', (string)($network['route_check'] ?? '')) ?><div class="small text-body-secondary mt-2">Ожидается: <code><?= htmlSC((string)($network['route_expected'] ?? '')) ?></code></div></div></div>
            <?php if ($routeJob !== null && !empty($routeJob['result'])): ?><div class="small mt-3">Получено: <code><?= htmlSC((string)($routeJob['result']['route'] ?? '')) ?></code> · interface <code><?= htmlSC((string)($routeJob['result']['device'] ?? '')) ?></code> · source <code><?= htmlSC((string)($routeJob['result']['source'] ?? '')) ?></code></div><?php endif; ?>
        </section>

        <section class="border rounded-5 p-3 p-md-4" id="nat-scripts">
            <div class="text-body-secondary small">Шаг 5 из 10</div><h2 class="h5">Идемпотентные NAT / FORWARD скрипты</h2>
            <?php if (empty($network['scripts']['up'])): ?>
                <div class="alert alert-warning rounded-4 mb-0">Заполните LAN CIDR, внешний RTSP-порт и общие параметры RTSP-сервера в настройках.</div>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-2 mb-3"><a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= base_href('/admin/camera-manager/sites/network-script/' . $siteId . '/up') ?>">Download <?= htmlSC((string)$network['scripts']['up_name']) ?></a><a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= base_href('/admin/camera-manager/sites/network-script/' . $siteId . '/down') ?>">Download <?= htmlSC((string)$network['scripts']['down_name']) ?></a></div>
                <div class="row g-3"><div class="col-xl-6"><h3 class="h6"><?= htmlSC((string)$network['scripts']['up_name']) ?></h3><?= $copyBlock('camera-iptables-up', (string)$network['scripts']['up']) ?><div class="small text-body-secondary mt-2">SHA-256: <code><?= htmlSC((string)$network['scripts']['up_sha256']) ?></code></div></div><div class="col-xl-6"><h3 class="h6"><?= htmlSC((string)$network['scripts']['down_name']) ?></h3><?= $copyBlock('camera-iptables-down', (string)$network['scripts']['down']) ?><div class="small text-body-secondary mt-2">SHA-256: <code><?= htmlSC((string)$network['scripts']['down_sha256']) ?></code></div></div></div>
                <?php $installCommands = 'chown root:root /usr/local/sbin/' . $network['scripts']['up_name'] . ' /usr/local/sbin/' . $network['scripts']['down_name'] . "\n" . 'chmod 700 /usr/local/sbin/' . $network['scripts']['up_name'] . ' /usr/local/sbin/' . $network['scripts']['down_name'] . "\n" . 'bash -n /usr/local/sbin/' . $network['scripts']['up_name'] . "\n" . 'bash /usr/local/sbin/' . $network['scripts']['up_name']; ?>
                <h3 class="h6 mt-3">Проверка и ручное применение</h3><?= $copyBlock('camera-install-commands', $installCommands) ?>
                <h3 class="h6 mt-3">Диагностика правил</h3><?= $copyBlock('camera-network-verification', (string)($network['verification_commands'] ?? '')) ?>
            <?php endif; ?>
        </section>

        <section class="border rounded-5 p-3 p-md-4" id="rtsp-detect">
            <div class="text-body-secondary small">Шаг 6 из 10</div><h2 class="h5">Определить RTSP</h2>
            <p>SprintHost не обращается в LAN. Задание забирает RTSP-сервер и запускает только whitelist-проверку <code>rtsp_probe</code>.</p>
            <?php if ($portsJob !== null && !empty($portsJob['result'])): ?><div class="alert alert-info rounded-4">Открытые типовые порты: <code><?= htmlSC(implode(', ', $portsJob['result']['open_ports'] ?? [])) ?></code><?= !empty($portsJob['result']['heuristic']) ? ' · ' . htmlSC((string)$portsJob['result']['heuristic']) : '' ?></div><?php endif; ?>
            <form class="row g-2 align-items-end" action="<?= base_href('/admin/camera-manager/sites/rtsp-probe') ?>" method="post">
                <?= get_csrf_field() ?><input type="hidden" name="site_id" value="<?= $siteId ?>">
                <div class="col-sm-3"><label class="form-label" for="cameraProbeChannel">Канал</label><input class="form-control" id="cameraProbeChannel" name="channel" type="number" min="1" max="4096" value="1"></div>
                <div class="col-sm-3"><label class="form-label" for="cameraProbeSubtype">Субпоток</label><input class="form-control" id="cameraProbeSubtype" name="subtype" type="number" min="0" max="99" value="0"></div>
                <div class="col-sm-6"><button class="btn btn-dark rounded-pill" type="submit">Определить RTSP</button></div>
            </form>
            <?php if ($rtspJob !== null): ?>
                <div class="border rounded-4 p-3 mt-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2"><strong>Последний результат</strong><?= $statusBadge($rtspJob) ?></div>
                    <?php if (!empty($rtspJob['result']['success'])): ?>
                        <?php $result = $rtspJob['result']; ?><dl class="row mt-3 mb-0"><dt class="col-md-3">Профиль</dt><dd class="col-md-9"><?= htmlSC((string)($rtsp_profiles[$result['profile']] ?? $result['profile'])) ?></dd><dt class="col-md-3">RTSP path</dt><dd class="col-md-9"><code><?= htmlSC((string)$result['path']) ?></code></dd><dt class="col-md-3">Codec</dt><dd class="col-md-9"><?= htmlSC((string)$result['codec_name']) ?> · <?= (int)$result['width'] ?>×<?= (int)$result['height'] ?> · <?= htmlSC((string)$result['fps']) ?> FPS</dd></dl>
                        <form action="<?= base_href('/admin/camera-manager/sites/apply-rtsp-profile') ?>" method="post" class="mt-3"><?= get_csrf_field() ?><input type="hidden" name="site_id" value="<?= $siteId ?>"><button class="btn btn-outline-secondary rounded-pill" type="submit">Использовать этот профиль</button></form>
                    <?php else: ?>
                        <div class="alert alert-warning rounded-4 mt-3 mb-0"><?= htmlSC((string)($rtspJob['message'] ?: match ((string)$rtspJob['error_code']) { '401_unauthorized' => 'RTSP endpoint отвечает, но авторизация не принята. Проверьте логин/пароль или права пользователя.', '404_not_found' => 'Регистратор доступен, но этот RTSP путь отсутствует.', 'network_unreachable' => 'Нет маршрута до локальной сети объекта через WireGuard.', default => 'Поток пока не определён.' })) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="border rounded-5 p-3 p-md-4" id="camera-channels">
            <div class="text-body-secondary small">Шаг 7 из 10</div><div class="d-flex flex-wrap justify-content-between gap-2"><h2 class="h5">Камеры и каналы</h2><a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= base_href('/admin/camera-manager/cameras/create?site_id=' . $siteId) ?>">Добавить канал</a></div>
            <p class="text-body-secondary">Камера наследует IP, credentials и RTSP profile объекта. Пустой ключ станет <code><?= htmlSC(strtolower((string)$site['code'])) ?>-01</code>.</p>
            <?php if (empty($cameras)): ?><div class="text-body-secondary">Каналов пока нет.</div><?php else: ?><div class="d-flex flex-wrap gap-2"><?php foreach ($cameras as $camera): ?><a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= base_href('/admin/camera-manager/cameras/edit/' . (int)$camera['id']) ?>"><?= htmlSC((string)$camera['stream_key']) ?> · <?= htmlSC((string)$camera['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
        </section>

        <section class="border rounded-5 p-3 p-md-4" id="publication">
            <div class="text-body-secondary small">Шаг 8 из 10</div><h2 class="h5">Предпросмотр и публикация</h2>
            <p>Проверьте masked RTSP URL, HLS/poster и конфликты ключей. Публикация по-прежнему меняет только managed-блок и выполняет <code>perl -c</code>.</p>
            <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/camera-manager/preview') ?>">Открыть предпросмотр</a><form action="<?= base_href('/admin/camera-manager/publish') ?>" method="post" data-admin-delete-form data-delete-message="Проверить и опубликовать managed-блок камер?"><?= get_csrf_field() ?><button class="btn btn-dark rounded-pill" type="submit">Опубликовать</button></form></div>
        </section>

        <section class="border rounded-5 p-3 p-md-4" id="hls-check">
            <div class="text-body-secondary small">Шаг 9 из 10</div><h2 class="h5">HLS</h2>
            <div class="alert alert-info rounded-4">Первый запрос будит FFmpeg. Первоначальный 404 может означать <em>HLS pending</em>, а не поломку; без запросов поток засыпает примерно через 120 секунд.</div>
            <?php foreach ($cameras as $camera): ?><div class="mb-2"><code><?= htmlSC((string)$camera['stream_key']) ?></code> · <a href="<?= htmlSC(FireballPluginCameraManager::hlsUrl((string)$camera['stream_key'])) ?>" target="_blank" rel="noopener">открыть M3U8</a></div><?php endforeach; ?>
            <?php if ($hlsJob !== null): ?><div class="small text-body-secondary mt-2">Последняя проверка агента: HLS HTTP <?= (int)($hlsJob['result']['hls_status'] ?? 0) ?> · <?= $statusBadge($hlsJob) ?></div><?php endif; ?>
        </section>

        <section class="border rounded-5 p-3 p-md-4" id="poster-check">
            <div class="text-body-secondary small">Шаг 10 из 10</div><h2 class="h5">Poster</h2>
            <?php if (empty($cameras)): ?><div class="text-body-secondary">Сначала добавьте камеру.</div><?php endif; ?>
            <?php foreach ($cameras as $camera): ?><div class="mb-2"><code><?= htmlSC((string)$camera['stream_key']) ?></code> · <a href="<?= htmlSC(FireballPluginCameraManager::posterUrl((string)$camera['stream_key'])) ?>" target="_blank" rel="noopener">открыть постер</a></div><?php endforeach; ?>
            <?php if ($hlsJob !== null): ?><div class="small text-body-secondary mt-2">Последняя проверка агента: poster HTTP <?= (int)($hlsJob['result']['poster_status'] ?? 0) ?></div><?php endif; ?>
        </section>
    </div>

    <?php if (!empty($diagnostic_jobs)): ?>
        <div class="table-responsive border rounded-5 mt-4" data-admin-simplebar data-simplebar-auto-hide="false">
            <table class="table align-middle mb-0"><thead><tr><th>Проверка</th><th>Статус</th><th>Результат</th><th>Время</th></tr></thead><tbody><?php foreach ($diagnostic_jobs as $job): ?><tr><td><code><?= htmlSC((string)$job['operation']) ?></code><div class="small text-body-secondary"><?= htmlSC((string)($job['parameters']['label'] ?? '')) ?></div></td><td><?= $statusBadge($job) ?></td><td><?= htmlSC((string)($job['message'] ?: ($job['error_code'] ?: '—'))) ?></td><td class="small text-body-secondary"><?= htmlSC((string)($job['completed_at'] ?: $job['created_at'])) ?></td></tr><?php endforeach; ?></tbody></table>
        </div>
    <?php endif; ?>

    <script>
    document.querySelectorAll('[data-camera-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var target = document.getElementById(button.getAttribute('data-camera-copy'));
            if (!target) return;
            var value = target.innerText;
            var done = function () { button.textContent = 'Скопировано'; setTimeout(function () { button.textContent = 'Copy'; }, 1500); };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(done);
                return;
            }
            var area = document.createElement('textarea'); area.value = value; area.style.position = 'fixed'; area.style.opacity = '0';
            document.body.appendChild(area); area.select(); document.execCommand('copy'); area.remove(); done();
        });
    });
    </script>

<?= view()->renderPartial('admin/shell_close') ?>
