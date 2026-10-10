<?php
use Fireball\ReelPlayer\Controllers\PlayerController;

/** @var \FBL\Router $router */
$router->get('/plugins/reel-player/assets/(?P<file>player\.(?:css|js)|preload\.js|meters\.js|cover\.(?:svg|png))', static function (): never {
    $file = (string)get_route_param('file');
    if (!in_array($file, ['player.css', 'player.js', 'preload.js', 'meters.js', 'cover.svg', 'cover.png'], true)) abort();
    $path = __DIR__ . '/assets/' . $file;
    header('Content-Type: ' . match (pathinfo($file, PATHINFO_EXTENSION)) {
        'css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', default => 'image/svg+xml',
    } . '; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
});

$guard = ['auth', 'admin'];
$router->get('/admin/reel-player', [PlayerController::class, 'index'])->middleware($guard);
$router->get('/admin/reel-player/api/state', [PlayerController::class, 'state'])->middleware($guard);
$router->post('/admin/reel-player/api/action', [PlayerController::class, 'action'])->middleware($guard);
$router->post('/admin/reel-player/api/upload', [PlayerController::class, 'upload'])->middleware($guard);
$router->post('/admin/reel-player/api/prepare', [PlayerController::class, 'prepare'])->middleware($guard);
$router->get('/admin/reel-player/media/(?P<id>\d+)', [PlayerController::class, 'media'])->middleware($guard);
$router->get('/admin/reel-player/cover/(?P<id>\d+)', [PlayerController::class, 'cover'])->middleware($guard);
$router->get('/admin/reel-player/api/drive/status', [PlayerController::class, 'driveStatus'])->middleware($guard);
$router->get('/admin/reel-player/api/drive/files', [PlayerController::class, 'driveFiles'])->middleware($guard);
$router->get('/admin/reel-player/drive/callback', [PlayerController::class, 'driveCallback'])->middleware($guard);
