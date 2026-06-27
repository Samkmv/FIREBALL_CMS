<?php

/** @var \FBL\Router $router */

$toyRentalRedirect = static function (string $path = '/admin/toy-rental'): void {
    response()->redirect(base_href($path));
};

$router->get('/admin/toy-rental/assets/(?P<file>[a-z0-9._-]+)', static function (): never {
    $file = (string)get_route_param('file');
    if (!in_array($file, ['toy-rental.css', 'toy-rental.js'], true)) {
        abort();
    }

    $path = __DIR__ . '/assets/' . $file;
    $real = realpath($path);
    $base = realpath(__DIR__ . '/assets');
    if ($real === false || $base === false || !str_starts_with($real, rtrim($base, '/') . '/')) {
        abort();
    }

    header('Content-Type: ' . (str_ends_with($file, '.css') ? 'text/css' : 'application/javascript') . '; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    readfile($real);
    exit;
})->middleware(['auth', 'admin']);

$router->get('/admin/toy-rental', static function (): string {
    $settings = FireballPluginToyCarRental::settings();

    return plugin_view('toy-car-rental', 'admin-dashboard', FireballPluginToyCarRental::viewData('dashboard', [
        'title' => 'Прокат машинок',
        'cars' => FireballPluginToyCarRental::carsForOperator(),
        'active_rides' => FireballPluginToyCarRental::activeRides(),
        'stats' => FireballPluginToyCarRental::todayStats(),
        'settings' => $settings,
    ]));
})->middleware(['auth', 'admin']);

$router->post('/admin/toy-rental/rides/start', static function () use ($toyRentalRedirect): void {
    try {
        FireballPluginToyCarRental::startRide(request()->getData());
        session()->setFlash('success', 'Поездка начата.');
    } catch (Throwable $exception) {
        log_error_details('Toy rental start ride failed', [], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $toyRentalRedirect('/admin/toy-rental');
})->middleware(['auth', 'admin']);

$router->post('/admin/toy-rental/rides/complete', static function () use ($toyRentalRedirect): void {
    try {
        FireballPluginToyCarRental::completeRide((int)request()->post('id'), request()->getData());
        session()->setFlash('success', 'Поездка завершена.');
    } catch (Throwable $exception) {
        log_error_details('Toy rental complete ride failed', ['Ride' => request()->post('id')], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $toyRentalRedirect('/admin/toy-rental');
})->middleware(['auth', 'admin']);

$router->get('/admin/toy-rental/cars', static function (): string {
    return plugin_view('toy-car-rental', 'cars-list', FireballPluginToyCarRental::viewData('cars', [
        'title' => 'Машинки',
        'cars' => FireballPluginToyCarRental::cars(true),
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/toy-rental/cars/create', static function (): string {
    return plugin_view('toy-car-rental', 'car-form', FireballPluginToyCarRental::viewData('cars', [
        'title' => 'Новая машинка',
        'car' => null,
    ]));
})->middleware(['auth', 'admin']);

$router->post('/admin/toy-rental/cars/create', static function () use ($toyRentalRedirect): void {
    try {
        FireballPluginToyCarRental::saveCar(request()->getData());
        session()->setFlash('success', 'Машинка добавлена.');
        $toyRentalRedirect('/admin/toy-rental/cars');
    } catch (Throwable $exception) {
        log_error_details('Toy rental car create failed', [], $exception);
        session()->setFlash('error', $exception->getMessage());
        $toyRentalRedirect('/admin/toy-rental/cars/create');
    }
})->middleware(['auth', 'admin']);

$router->get('/admin/toy-rental/cars/edit/(?P<id>\d+)/?', static function (): string {
    $car = FireballPluginToyCarRental::car((int)get_route_param('id'));
    if (!$car) {
        abort();
    }

    return plugin_view('toy-car-rental', 'car-form', FireballPluginToyCarRental::viewData('cars', [
        'title' => 'Редактирование машинки',
        'car' => $car,
    ]));
})->middleware(['auth', 'admin']);

$router->post('/admin/toy-rental/cars/edit/(?P<id>\d+)/?', static function () use ($toyRentalRedirect): void {
    $id = (int)get_route_param('id');
    try {
        FireballPluginToyCarRental::saveCar(request()->getData(), $id);
        session()->setFlash('success', 'Машинка сохранена.');
        $toyRentalRedirect('/admin/toy-rental/cars');
    } catch (Throwable $exception) {
        log_error_details('Toy rental car update failed', ['Car' => $id], $exception);
        session()->setFlash('error', $exception->getMessage());
        $toyRentalRedirect('/admin/toy-rental/cars/edit/' . $id);
    }
})->middleware(['auth', 'admin']);

$router->post('/admin/toy-rental/cars/hide', static function () use ($toyRentalRedirect): void {
    try {
        FireballPluginToyCarRental::hideCar((int)request()->post('id'));
        session()->setFlash('success', 'Машинка скрыта.');
    } catch (Throwable $exception) {
        log_error_details('Toy rental car hide failed', ['Car' => request()->post('id')], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $toyRentalRedirect('/admin/toy-rental/cars');
})->middleware(['auth', 'admin']);

$router->get('/admin/toy-rental/active', static function (): string {
    return plugin_view('toy-car-rental', 'rides-active', FireballPluginToyCarRental::viewData('active', [
        'title' => 'Активные поездки',
        'rides' => FireballPluginToyCarRental::activeRides(),
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/toy-rental/rides', static function (): string {
    $filters = [
        'date_filter' => (string)request()->get('date_filter', 'today'),
        'date_from' => (string)request()->get('date_from', ''),
        'date_to' => (string)request()->get('date_to', ''),
        'car_id' => (int)request()->get('car_id', 0),
        'payment_method' => (string)request()->get('payment_method', ''),
        'payment_status' => (string)request()->get('payment_status', ''),
    ];

    return plugin_view('toy-car-rental', 'rides-history', FireballPluginToyCarRental::viewData('history', [
        'title' => 'История поездок',
        'rides' => FireballPluginToyCarRental::history($filters),
        'cars' => FireballPluginToyCarRental::cars(true),
        'filters' => $filters,
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/toy-rental/stats', static function (): string {
    return plugin_view('toy-car-rental', 'stats', FireballPluginToyCarRental::viewData('stats', [
        'title' => 'Статистика проката',
        'stats' => FireballPluginToyCarRental::todayStats(),
    ]));
})->middleware(['auth', 'admin']);

$router->get('/admin/toy-rental/settings', static function (): string {
    return plugin_view('toy-car-rental', 'settings', FireballPluginToyCarRental::viewData('settings', [
        'title' => 'Настройки проката',
    ]));
})->middleware(['auth', 'admin']);

$router->post('/admin/toy-rental/settings', static function () use ($toyRentalRedirect): void {
    try {
        FireballPluginToyCarRental::saveSettings(request()->getData());
        session()->setFlash('success', 'Настройки проката сохранены.');
    } catch (Throwable $exception) {
        log_error_details('Toy rental settings failed', [], $exception);
        session()->setFlash('error', $exception->getMessage());
    }

    $toyRentalRedirect('/admin/toy-rental/settings');
})->middleware(['auth', 'admin']);
