<?php

use Fireball\CameraManager\PullSyncService;

/** @var \FBL\Router $router */

$router->get('/plugins/camera-manager/assets/(?P<file>[a-z0-9._-]+)', static function (): never {
    $file = (string)get_route_param('file');
    if ($file !== 'camera-manager-player.js') {
        abort();
    }

    $path = __DIR__ . '/assets/' . $file;
    $real = realpath($path);
    $base = realpath(__DIR__ . '/assets');
    if ($real === false || $base === false || !str_starts_with($real, rtrim($base, '/') . '/')) {
        abort();
    }

    header('Content-Type: application/javascript; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    readfile($real);
    exit;
});

$router->post('/api/camera-manager/pull', static function () {
    header('Cache-Control: no-store, private, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) {
        response()->json(['success' => false, 'message' => 'Request body is too large.'], 413);
    }

    $service = new PullSyncService();
    $token = strtolower(trim((string)request()->header('X-Fireball-Camera-Token', '')));
    if (!$service->authenticate($token)) {
        response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
    }

    try {
        response()->json($service->handle(request()->json()));
    } catch (Throwable $exception) {
        response()->json([
            'success' => false,
            'message' => mb_substr($exception->getMessage(), 0, 500),
        ], 400);
    }
})->withoutCSRFToken();
