<?php

/** @var \FBL\Router $router */

$router->get('/toy-car-rental', static function (): string {
    return plugin_view('toy-car-rental', 'frontend', [
        'title' => FireballPluginToyCarRental::t('toy_rental_dashboard_title'),
    ]);
});

$router->get('/profile/toy-rental', static function (): string {
    FireballPluginToyCarRental::requireOperator();
    return plugin_view('toy-car-rental', 'profile-operator', FireballPluginToyCarRental::operatorViewData([
        'cars' => FireballPluginToyCarRental::carsForOperator(),
    ]));
})->middleware(['auth']);

$router->get('/profile/toy-rental/assets/(?P<file>[a-z0-9._-]+)', static function (): never {
    FireballPluginToyCarRental::requireOperator();
    $file = (string)get_route_param('file');
    if (!in_array($file, ['toy-rental.css', 'toy-rental.js'], true)) abort();
    $base = realpath(__DIR__ . '/assets');
    $real = realpath(__DIR__ . '/assets/' . $file);
    if ($real === false || $base === false || !str_starts_with($real, $base . '/')) abort();
    header('Content-Type: ' . (str_ends_with($file, '.css') ? 'text/css' : 'application/javascript') . '; charset=utf-8');
    header('Cache-Control: private, max-age=3600');
    readfile($real);
    exit;
})->middleware(['auth']);

$router->get('/profile/toy-rental/state', static function (): void {
    FireballPluginToyCarRental::requireOperator();
    header('Cache-Control: no-store');
    response()->json(FireballPluginToyCarRental::operatorState());
})->middleware(['auth']);

foreach (['start', 'complete'] as $action) {
    $router->post('/profile/toy-rental/rides/' . $action, static function () use ($action): void {
        FireballPluginToyCarRental::requireOperator();
        try {
            $data = request()->getData();
            if ($action === 'start') {
                // Operators use the rate configured by the administrator.
                unset($data['price_per_minute']);
                FireballPluginToyCarRental::startRide($data);
            } else {
                FireballPluginToyCarRental::completeRide((int)request()->post('id'), $data);
            }
            $message = FireballPluginToyCarRental::t($action === 'start' ? 'toy_rental_flash_ride_started' : 'toy_rental_flash_ride_completed');
            if (request()->isAjax()) response()->json(array_merge(FireballPluginToyCarRental::operatorState(), ['message' => $message]));
            session()->setFlash('success', $message);
        } catch (Throwable $exception) {
            log_error_details('Toy rental operator action failed', ['Action' => $action], $exception);
            if (request()->isAjax()) response()->json(['status' => false, 'message' => $exception->getMessage()], 422);
            session()->setFlash('error', $exception->getMessage());
        }
        response()->redirect(base_href('/profile/toy-rental'));
    })->middleware(['auth']);
}

$router->post('/profile/toy-rental/rides/sync-overdue', static function (): void {
    FireballPluginToyCarRental::requireOperator();
    try {
        response()->json(['status' => true, 'updated' => FireballPluginToyCarRental::markOverdueRides()]);
    } catch (Throwable $exception) {
        log_error_details('Toy rental operator overdue sync failed', [], $exception);
        response()->json(['status' => false, 'message' => FireballPluginToyCarRental::t('toy_rental_error_overdue_sync')], 500);
    }
})->middleware(['auth']);
