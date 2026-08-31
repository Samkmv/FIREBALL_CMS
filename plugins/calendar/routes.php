<?php

use Fireball\Calendar\Controllers\CalendarController;

/** @var \FBL\Router $router */

$router->get('/plugins/calendar/assets/(?P<file>calendar\.(?:css|js))', static function (): never {
    $file = (string)get_route_param('file');
    if (!in_array($file, ['calendar.css', 'calendar.js'], true)) {
        abort();
    }
    $path = __DIR__ . '/assets/' . $file;
    if (!is_file($path)) {
        abort();
    }
    header('Content-Type: ' . (str_ends_with($file, '.css') ? 'text/css' : 'application/javascript') . '; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    readfile($path);
    exit;
});

$router->get('/calendar', [CalendarController::class, 'index'])->middleware(['auth']);
$router->get('/calendar/events', [CalendarController::class, 'events'])->middleware(['auth']);
$router->post('/calendar/events', [CalendarController::class, 'create'])->middleware(['auth']);
$router->post('/calendar/events/(?P<id>\d+)/update', [CalendarController::class, 'update'])->middleware(['auth']);
$router->post('/calendar/events/(?P<id>\d+)/delete', [CalendarController::class, 'delete'])->middleware(['auth']);
$router->post('/calendar/events/(?P<id>\d+)/duplicate', [CalendarController::class, 'duplicate'])->middleware(['auth']);
$router->post('/calendar/events/(?P<id>\d+)/status', [CalendarController::class, 'status'])->middleware(['auth']);
