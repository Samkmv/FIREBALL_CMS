<?= view()->renderPartial('admin/shell_open', [
    'title' => FireballPluginCameraManager::t('camera_manager_title'),
    'subtitle' => FireballPluginCameraManager::t('camera_manager_dashboard_subtitle'),
    'actions' => '<div class="d-flex flex-wrap gap-2">'
        . '<a class="btn btn-outline-secondary rounded-pill" href="' . base_href('/admin/camera-manager/cameras/create') . '"><i class="ci-plus me-1"></i>Добавить камеру</a>'
        . '<form action="' . base_href('/admin/camera-manager/publish') . '" method="post" data-admin-delete-form data-delete-message="Проверить синтаксис, создать резервную копию и обновить streams.pl?">'
        . get_csrf_field() . '<button class="btn btn-dark rounded-pill" type="submit"><i class="ci-upload me-1"></i>Опубликовать</button></form></div>',
]) ?>

    <?php require __DIR__ . '/tabs.php'; ?>

    <div class="row g-3 mb-4">
        <?php foreach ([['Объекты', $stats['sites'], 'ci-map-pin'], ['Активные камеры', $stats['cameras'], 'ci-video'], ['Недоступны', $stats['offline'], 'ci-alert-circle']] as $metric): ?>
            <div class="col-12 col-md-4">
                <div class="border rounded-5 p-4 h-100">
                    <div class="d-flex align-items-center justify-content-between">
                        <span class="text-body-secondary"><?= htmlSC($metric[0]) ?></span><i class="<?= htmlSC($metric[2]) ?>"></i>
                    </div>
                    <div class="display-6 fw-semibold mt-2"><?= (int)$metric[1] ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($latest_publication): ?>
        <?php $publicationTone = $latest_publication['status'] === 'success' ? 'success' : ($latest_publication['status'] === 'warning' ? 'warning' : 'danger'); ?>
        <div class="alert alert-<?= $publicationTone ?> rounded-4 d-flex flex-wrap justify-content-between gap-2" role="status">
            <span><strong>Последняя публикация:</strong> <?= htmlSC((string)$latest_publication['message']) ?></span>
            <span><?= htmlSC((string)$latest_publication['created_at']) ?> · <?= (int)$latest_publication['stream_count'] ?> потоков</span>
        </div>
    <?php endif; ?>

    <div class="table-responsive border rounded-5" data-admin-simplebar data-simplebar-auto-hide="false">
        <table class="table align-middle mb-0">
            <thead><tr><th>Камера</th><th>Объект / регистратор</th><th>Поток</th><th>Проверка</th><th class="text-end">Действия</th></tr></thead>
            <tbody>
            <?php if (empty($cameras)): ?>
                <tr><td colspan="5" class="text-center text-body-secondary py-5">Камер пока нет. Сначала создайте объект, затем добавьте его каналы.</td></tr>
            <?php endif; ?>
            <?php foreach ($cameras as $camera): ?>
                <?php
                    $isEnabled = !empty($camera['enabled']) && !empty($camera['site_enabled']);
                    $health = (string)$camera['last_health_status'];
                    $healthClass = $health === 'online' ? 'success' : ($health === 'offline' ? 'danger' : 'secondary');
                ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= htmlSC((string)$camera['name']) ?></div>
                        <div class="small text-body-secondary">Канал <?= (int)$camera['channel_number'] ?> · <?= $isEnabled ? 'активна' : 'отключена' ?></div>
                    </td>
                    <td>
                        <div><?= htmlSC((string)$camera['site_code']) ?> · <?= htmlSC((string)$camera['site_name']) ?></div>
                        <code class="small"><?= htmlSC((string)$camera['recorder_ip']) ?>:<?= (int)$camera['rtsp_port'] ?></code>
                    </td>
                    <td>
                        <code><?= htmlSC((string)$camera['stream_key']) ?></code>
                        <div class="small mt-1"><a href="<?= htmlSC(FireballPluginCameraManager::hlsUrl((string)$camera['stream_key'])) ?>" target="_blank" rel="noopener">M3U8</a> · <a href="<?= htmlSC(FireballPluginCameraManager::posterUrl((string)$camera['stream_key'])) ?>" target="_blank" rel="noopener">постер</a></div>
                    </td>
                    <td>
                        <span class="badge rounded-pill text-bg-<?= $healthClass ?>"><?= $health === 'online' ? 'Онлайн' : ($health === 'offline' ? 'Недоступна' : 'Не проверялась') ?></span>
                        <?php if (!empty($camera['last_checked_at'])): ?><div class="small text-body-secondary mt-1"><?= htmlSC((string)$camera['last_checked_at']) ?></div><?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                            <button
                                class="btn btn-sm btn-dark rounded-pill"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#cameraManagerPlayerModal"
                                data-camera-player-open
                                data-player-src="<?= htmlSC(FireballPluginCameraManager::hlsUrl((string)$camera['stream_key'])) ?>"
                                data-player-poster="<?= htmlSC(FireballPluginCameraManager::posterUrl((string)$camera['stream_key'])) ?>"
                                data-player-stream-id="<?= htmlSC((string)$camera['stream_key']) ?>"
                                data-player-title="<?= htmlSC((string)$camera['name']) ?>"
                                <?= !$isEnabled ? 'disabled' : '' ?>
                            ><i class="ci-play me-1" aria-hidden="true"></i>Смотреть LIVE</button>
                            <form action="<?= base_href('/admin/camera-manager/cameras/probe') ?>" method="post">
                                <?= get_csrf_field() ?><input type="hidden" name="id" value="<?= (int)$camera['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary rounded-pill" type="submit">Проверить</button>
                            </form>
                            <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= base_href('/admin/camera-manager/cameras/edit/' . (int)$camera['id']) ?>">Изменить</a>
                            <form action="<?= base_href('/admin/camera-manager/cameras/toggle') ?>" method="post">
                                <?= get_csrf_field() ?><input type="hidden" name="id" value="<?= (int)$camera['id'] ?>">
                                <button class="btn btn-sm <?= !empty($camera['enabled']) ? 'btn-outline-danger' : 'btn-outline-success' ?> rounded-pill" type="submit"><?= !empty($camera['enabled']) ? 'Отключить' : 'Включить' ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="cameraManagerPlayerModal" tabindex="-1" aria-labelledby="cameraManagerPlayerTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content border-0 rounded-5 overflow-hidden">
                <div class="modal-header border-0 px-3 px-md-4 pt-3 pt-md-4">
                    <div>
                        <div class="small text-body-secondary mb-1">Camera Manager · LIVE</div>
                        <h2 class="modal-title h5 mb-0" id="cameraManagerPlayerTitle" data-camera-player-title>Камера</h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                </div>
                <div class="modal-body p-3 p-md-4 pt-2">
                    <div class="fire-player" data-fire-player-manual data-camera-player aria-label="LIVE-поток камеры"></div>
                    <p class="small text-body-secondary mt-3 mb-0">Тип источника, HLS-движок и режим LIVE определяются автоматически. При зависании поток переподключится.</p>
                </div>
            </div>
        </div>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
