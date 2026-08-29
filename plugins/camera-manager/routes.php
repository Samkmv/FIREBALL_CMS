<?php

use Fireball\CameraManager\PullSyncService;

/** @var \FBL\Router $router */

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
