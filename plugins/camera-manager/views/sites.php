<?= view()->renderPartial('admin/shell_open', [
    'title' => 'Объекты камер',
    'subtitle' => 'Роутеры WireGuard, регистраторы и общие реквизиты RTSP.',
    'actions' => '<a class="btn btn-dark rounded-pill" href="' . base_href('/admin/camera-manager/sites/create') . '"><i class="ci-plus me-1"></i>Добавить объект</a>',
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <div class="table-responsive border rounded-5" data-admin-simplebar data-simplebar-auto-hide="false">
        <table class="table align-middle mb-0">
            <thead><tr><th>Объект</th><th>Сеть</th><th>Регистратор</th><th>Камеры</th><th>Состояние</th><th class="text-end">Действия</th></tr></thead>
            <tbody>
            <?php if (empty($sites)): ?>
                <tr><td colspan="6" class="text-center text-body-secondary py-5">Объектов пока нет.</td></tr>
            <?php endif; ?>
            <?php foreach ($sites as $site): ?>
                <tr>
                    <td><div class="fw-semibold"><?= htmlSC((string)$site['code']) ?> · <?= htmlSC((string)$site['name']) ?></div><div class="small text-body-secondary"><?= htmlSC((string)($site['address'] ?: 'Адрес не указан')) ?></div></td>
                    <td><div>Роутер: <code><?= htmlSC((string)($site['router_ip'] ?: '—')) ?></code></div><div class="small">VPN: <code><?= htmlSC((string)($site['vpn_ip'] ?: '—')) ?></code></div></td>
                    <td><code><?= htmlSC((string)$site['recorder_ip']) ?>:<?= (int)$site['rtsp_port'] ?></code><div class="small text-body-secondary">управление: <?= (int)$site['management_port'] ?></div></td>
                    <td><?= (int)$site['enabled_camera_count'] ?> / <?= (int)$site['camera_count'] ?></td>
                    <td><span class="badge rounded-pill <?= !empty($site['enabled']) ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= !empty($site['enabled']) ? 'Активен' : 'Отключён' ?></span></td>
                    <td class="text-end"><div class="d-inline-flex gap-1"><a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= base_href('/admin/camera-manager/cameras/create?site_id=' . (int)$site['id']) ?>">Добавить канал</a><a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= base_href('/admin/camera-manager/sites/edit/' . (int)$site['id']) ?>">Изменить</a></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
