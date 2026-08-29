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

$router->post('/admin/camera-manager/sites/save', static function () use ($cameraManagerRedirect): never {
    $id = (int)request()->post('id', 0);
    try {
        $savedId = FireballPluginCameraManager::saveSite(request()->getData(), $id > 0 ? $id : null);
        session()->setFlash('success', 'Объект сохранён. Пароль RTSP в таблицах и логах не отображается.');
        $cameraManagerRedirect('/admin/camera-manager/sites/edit/' . $savedId);
    } catch (Throwable $exception) {
        log_error_details('Camera Manager site save failed', ['site_id' => $id], $exception);
        session()->setFlash('error', $exception->getMessage());
        $cameraManagerRedirect($id > 0 ? '/admin/camera-manager/sites/edit/' . $id : '/admin/camera-manager/sites/create');
    }
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
