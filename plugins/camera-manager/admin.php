<?php

/** @var \FBL\Router $router */

$cameraManagerRedirect = static function (string $path = '/admin/camera-manager'): never {
    response()->redirect(base_href($path));
};

$router->get('/admin/camera-manager', static function (): string {
    return plugin_view('camera-manager', 'dashboard', FireballPluginCameraManager::viewData('dashboard', [
        'title' => FireballPluginCameraManager::t('camera_manager_title'),
        'stats' => FireballPluginCameraManager::stats(),
        'cameras' => FireballPluginCameraManager::cameras(),
        'sites' => FireballPluginCameraManager::sites(),
        'latest_publication' => FireballPluginCameraManager::latestPublication(),
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/camera-manager/sites', static function (): string {
    return plugin_view('camera-manager', 'sites', FireballPluginCameraManager::viewData('sites', [
        'title' => 'Объекты камер',
        'sites' => FireballPluginCameraManager::sites(),
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/camera-manager/sites/create', static function (): string {
    return plugin_view('camera-manager', 'site-form', FireballPluginCameraManager::viewData('sites', [
        'title' => 'Новый объект',
        'site' => null,
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/camera-manager/sites/edit/(?P<id>\d+)/?', static function (): string {
    $site = FireballPluginCameraManager::site((int)get_route_param('id'));
    if ($site === null) {
        abort();
    }

    return plugin_view('camera-manager', 'site-form', FireballPluginCameraManager::viewData('sites', [
        'title' => 'Редактирование объекта',
        'site' => $site,
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/camera-manager/sites/connection/(?P<id>\d+)/?', static function (): string {
    $site = FireballPluginCameraManager::site((int)get_route_param('id'));
    if ($site === null) {
        abort();
    }
    $network = [];
    $networkError = '';
    try {
        $network = FireballPluginCameraManager::networkConfiguration((int)$site['id']);
    } catch (Throwable $exception) {
        $networkError = $exception->getMessage();
    }

    return plugin_view('camera-manager', 'site-connection', FireballPluginCameraManager::viewData('sites', [
        'title' => 'Подключение объекта ' . (string)$site['code'],
        'site' => $site,
        'network' => $network,
        'network_error' => $networkError,
        'diagnostic_jobs' => FireballPluginCameraManager::diagnosticJobs((int)$site['id']),
        'cameras' => FireballPluginCameraManager::siteCameras((int)$site['id']),
        'rtsp_profiles' => \Fireball\CameraManager\RtspUrlBuilder::PROFILE_LABELS,
    ]));
})->middleware(['auth', 'admin']);

$router->post('/admin/camera-manager/sites/save', static function () use ($cameraManagerRedirect): never {
    $id = (int)request()->post('id', 0);
    try {
        $savedId = FireballPluginCameraManager::saveSite(request()->getData(), $id > 0 ? $id : null);
        session()->setFlash('success', 'Объект сохранён. Пароль RTSP в таблицах и логах не отображается.');
        $cameraManagerRedirect($id > 0
            ? '/admin/camera-manager/sites/edit/' . $savedId
            : '/admin/camera-manager/sites/connection/' . $savedId);
    } catch (Throwable $exception) {
        log_error_details('Camera Manager site save failed', ['site_id' => $id], $exception);
        session()->setFlash('error', $exception->getMessage());
        $cameraManagerRedirect($id > 0 ? '/admin/camera-manager/sites/edit/' . $id : '/admin/camera-manager/sites/create');
    }
})->middleware(['auth', 'admin']);

$router->post('/admin/camera-manager/sites/diagnostics', static function () use ($cameraManagerRedirect): never {
    $siteId = (int)request()->post('site_id', 0);
    try {
        FireballPluginCameraManager::queueSiteDiagnostics($siteId);
        session()->setFlash('success', 'Диагностика поставлена в очередь. RTSP-агент выполнит только разрешённые проверки.');
    } catch (Throwable $exception) {
        log_error_details('Camera Manager site diagnostics queue failed', ['site_id' => $siteId], $exception);
        session()->setFlash('error', $exception->getMessage());
    }
    $cameraManagerRedirect('/admin/camera-manager/sites/connection/' . $siteId);
})->middleware(['auth', 'admin']);

$router->post('/admin/camera-manager/sites/rtsp-probe', static function () use ($cameraManagerRedirect): never {
    $siteId = (int)request()->post('site_id', 0);
    try {
        FireballPluginCameraManager::queueRtspProbe(
            $siteId,
            (int)request()->post('channel', 1),
            (int)request()->post('subtype', 0)
        );
        session()->setFlash('success', 'RTSP auto-detect поставлен в очередь без выполнения LAN-запроса с SprintHost.');
    } catch (Throwable $exception) {
        log_error_details('Camera Manager RTSP probe queue failed', ['site_id' => $siteId], $exception);
        session()->setFlash('error', $exception->getMessage());
    }
    $cameraManagerRedirect('/admin/camera-manager/sites/connection/' . $siteId . '#rtsp-detect');
})->middleware(['auth', 'admin']);

$router->post('/admin/camera-manager/sites/apply-rtsp-profile', static function () use ($cameraManagerRedirect): never {
    $siteId = (int)request()->post('site_id', 0);
    try {
        $label = FireballPluginCameraManager::applyDetectedRtspProfile($siteId);
        session()->setFlash('success', 'RTSP-профиль применён после подтверждения: ' . $label . '.');
    } catch (Throwable $exception) {
        session()->setFlash('error', $exception->getMessage());
    }
    $cameraManagerRedirect('/admin/camera-manager/sites/connection/' . $siteId . '#rtsp-detect');
})->middleware(['auth', 'admin']);

$router->get('/admin/camera-manager/sites/network-script/(?P<id>\d+)/(?P<kind>up|down)/?', static function (): never {
    $siteId = (int)get_route_param('id');
    $kind = (string)get_route_param('kind');
    $network = FireballPluginCameraManager::networkConfiguration($siteId);
    $contents = (string)($network['scripts'][$kind] ?? '');
    $fileName = (string)($network['scripts'][$kind . '_name'] ?? '');
    if ($contents === '' || preg_match('/^[a-zA-Z0-9._-]+\.sh$/', $fileName) !== 1) {
        abort();
    }
    header('Content-Type: text/x-shellscript; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: no-store, private, max-age=0');
    header('X-Content-Type-Options: nosniff');
    echo $contents;
    exit;
})->middleware(['auth', 'admin']);

$router->get('/admin/camera-manager/cameras/create', static function (): string {
    return plugin_view('camera-manager', 'camera-form', FireballPluginCameraManager::viewData('dashboard', [
        'title' => 'Новая камера',
        'camera' => null,
        'sites' => FireballPluginCameraManager::sites(),
        'selected_site_id' => (int)request()->get('site_id', 0),
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/camera-manager/cameras/edit/(?P<id>\d+)/?', static function (): string {
    $camera = FireballPluginCameraManager::camera((int)get_route_param('id'));
    if ($camera === null) {
        abort();
    }

    return plugin_view('camera-manager', 'camera-form', FireballPluginCameraManager::viewData('dashboard', [
        'title' => 'Редактирование камеры',
        'camera' => $camera,
        'sites' => FireballPluginCameraManager::sites(),
        'selected_site_id' => (int)$camera['site_id'],
    ]));
})->middleware(['auth', 'admin']);

$router->post('/admin/camera-manager/cameras/save', static function () use ($cameraManagerRedirect): never {
    $id = (int)request()->post('id', 0);
    try {
        FireballPluginCameraManager::saveCamera(request()->getData(), $id > 0 ? $id : null);
        session()->setFlash('success', 'Камера сохранена. Нажмите «Опубликовать», чтобы обновить streams.pl.');
        $cameraManagerRedirect('/admin/camera-manager');
    } catch (Throwable $exception) {
        log_error_details('Camera Manager camera save failed', ['camera_id' => $id], $exception);
        session()->setFlash('error', $exception->getMessage());
        $cameraManagerRedirect($id > 0 ? '/admin/camera-manager/cameras/edit/' . $id : '/admin/camera-manager/cameras/create');
    }
})->middleware(['auth', 'admin']);

$router->post('/admin/camera-manager/cameras/toggle', static function () use ($cameraManagerRedirect): never {
    $id = (int)request()->post('id', 0);
    try {
        FireballPluginCameraManager::toggleCamera($id);
        session()->setFlash('success', 'Состояние камеры изменено. Для применения опубликуйте конфигурацию.');
    } catch (Throwable $exception) {
        session()->setFlash('error', $exception->getMessage());
    }
    $cameraManagerRedirect();
})->middleware(['auth', 'admin']);

$router->post('/admin/camera-manager/cameras/probe', static function () use ($cameraManagerRedirect): never {
    $id = (int)request()->post('id', 0);
    try {
        $result = FireballPluginCameraManager::probeCamera($id);
        session()->setFlash($result['online'] ? 'success' : 'warning', $result['message']);
    } catch (Throwable $exception) {
        log_error_details('Camera Manager health probe failed', ['camera_id' => $id], $exception);
        session()->setFlash('error', $exception->getMessage());
    }
    $cameraManagerRedirect();
})->middleware(['auth', 'admin']);

$router->post('/admin/camera-manager/publish', static function () use ($cameraManagerRedirect): never {
    try {
        $result = FireballPluginCameraManager::publish();
        if (!empty($result['queued'])) {
            session()->setFlash(
                'success',
                'Ревизия ' . (int)$result['revision'] . ' поставлена в очередь. RTSP-сервер заберёт её по HTTPS.'
            );
        } elseif (!empty(FireballPluginCameraManager::settings()['restart_on_publish']) && !$result['restarted']) {
            session()->setFlash('warning', 'streams.pl обновлён и проверен, но службу перезапустить не удалось: ' . ($result['restart_output'] ?: 'нет прав sudo')); 
        } else {
            session()->setFlash('success', 'Опубликовано потоков: ' . (int)$result['stream_count'] . '. Резервная копия создана.');
        }
    } catch (Throwable $exception) {
        log_error_details('Camera Manager publication failed', [], $exception);
        session()->setFlash('error', $exception->getMessage());
    }
    $cameraManagerRedirect();
})->middleware(['auth', 'admin']);

$router->get('/admin/camera-manager/preview', static function (): string {
    $preview = '';
    $error = '';
    try {
        $preview = FireballPluginCameraManager::preview();
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }

    return plugin_view('camera-manager', 'preview', FireballPluginCameraManager::viewData('preview', [
        'title' => 'Предпросмотр конфигурации',
        'preview' => $preview,
        'preview_rows' => FireballPluginCameraManager::publicationPreview(),
        'preview_error' => $error,
        'latest_publication' => FireballPluginCameraManager::latestPublication(),
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/camera-manager/settings', static function (): string {
    return plugin_view('camera-manager', 'settings', FireballPluginCameraManager::viewData('settings', [
        'title' => 'Настройки Camera Manager',
    ]));
})->middleware(['auth', 'admin']);

$router->post('/admin/camera-manager/settings', static function () use ($cameraManagerRedirect): never {
    try {
        FireballPluginCameraManager::saveSettings(request()->getData());
        session()->setFlash('success', 'Настройки сохранены.');
    } catch (Throwable $exception) {
        log_error_details('Camera Manager settings save failed', [], $exception);
        session()->setFlash('error', $exception->getMessage());
    }
    $cameraManagerRedirect('/admin/camera-manager/settings');
})->middleware(['auth', 'admin']);

$router->post('/admin/camera-manager/settings/token', static function () use ($cameraManagerRedirect): never {
    try {
        FireballPluginCameraManager::savePullToken((string)request()->post('pull_token', ''));
        session()->setFlash('success', 'HTTPS-токен сохранён и проверен чтением из базы данных.');
    } catch (Throwable $exception) {
        log_error_details('Camera Manager HTTPS token save failed', [], $exception);
        session()->setFlash('error', $exception->getMessage());
    }
    $cameraManagerRedirect('/admin/camera-manager/settings');
})->middleware(['auth', 'admin']);

$router->post('/admin/camera-manager/settings/test-connection', static function () use ($cameraManagerRedirect): never {
    try {
        $result = FireballPluginCameraManager::testConnection();
        $details = [];
        if (!empty($result['streams_file'])) {
            $details[] = (string)$result['streams_file'];
        }
        if (!empty($result['service'])) {
            $details[] = (string)$result['service'];
        }
        session()->setFlash('success', (string)$result['message'] . ($details ? ' ' . implode(' · ', $details) : ''));
    } catch (Throwable $exception) {
        log_error_details('Camera Manager connection test failed', [], $exception);
        session()->setFlash('error', $exception->getMessage());
    }
    $cameraManagerRedirect('/admin/camera-manager/settings');
})->middleware(['auth', 'admin']);
