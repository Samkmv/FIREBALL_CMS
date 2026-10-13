<?php
use Fireball\ReelPlayer\Controllers\PlayerController;

/** @var \FBL\Router $router */
$router->get('/plugins/reel-player/assets/(?P<file>[a-z0-9.-]+)', static function (): never {
    $file = (string)get_route_param('file');
    $allowed = ['player.css','player.js','preload.js','meters.js','chapters.js','transport.js','quality.js','collections.js','catalog.js','images.js','reel.css','public.css','public.js','navigation.js','platform-admin.js','media-session.js','playback-core.js','preferences.js','compact.js','public-playback.js','embed.js'];
    foreach (['cover','artist'] as $kind) foreach (['light','dark'] as $theme) {
        $allowed[] = $kind . '-' . $theme . '.png';
        foreach ([96,320,640] as $size) $allowed[] = $kind . '-' . $theme . '-' . $size . '.webp';
    }
    if (!in_array($file, $allowed, true)) abort();
    $path = __DIR__ . '/assets/' . $file;
    if (!is_file($path)) abort();
    $hash = hash_file('sha256', $path);
    $etag = '"' . $hash . '"';
    $version = (string)request()->get('v', '');
    $maxAge = hash_equals(substr($hash, 0, 16), $version) ? 31536000 : 3600;
    // CMS bootstrap starts a session even on public routes. Do not retain its
    // no-cache headers or publish a user's session cookie in a shared cache.
    session()->close();
    header_remove('Pragma');
    header_remove('Expires');
    header_remove('Set-Cookie');
    header('Content-Type: ' . match (pathinfo($file, PATHINFO_EXTENSION)) {
        'css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'webp' => 'image/webp', default => 'image/svg+xml',
    } . '; charset=utf-8');
    // Content-versioned assets can stay in the disk cache until their bytes change.
    header('Cache-Control: public, max-age=' . $maxAge . ($maxAge === 31536000 ? ', immutable' : ''));
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $maxAge) . ' GMT');
    header('ETag: ' . $etag);
    header('X-Content-Type-Options: nosniff');
    $matches = array_map(static fn(string $value): string => preg_replace('/^W\//', '', trim($value)), explode(',', (string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')));
    if (in_array($etag, $matches, true) || in_array('*', $matches, true)) { http_response_code(304); exit; }
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
});

$guard = ['auth', 'admin'];
$router->get('/admin/reel-player', [PlayerController::class, 'index'])->middleware($guard);
$router->get('/admin/reel-player/api/state', [PlayerController::class, 'state'])->middleware($guard);
$router->get('/admin/reel-player/api/quality/(?P<id>\d+)', [PlayerController::class, 'quality'])->middleware($guard);
$router->post('/admin/reel-player/api/action', [PlayerController::class, 'action'])->middleware($guard);
$router->post('/admin/reel-player/api/upload', [PlayerController::class, 'upload'])->middleware($guard);
$router->post('/admin/reel-player/api/prepare', [PlayerController::class, 'prepare'])->middleware($guard);
$router->post('/admin/reel-player/api/chapters/cue', [PlayerController::class, 'cuePreview'])->middleware($guard);
$router->post('/admin/reel-player/api/chapters/analyze', [PlayerController::class, 'chapterAnalysis'])->middleware($guard);
$router->get('/admin/reel-player/media/(?P<id>\d+)', [PlayerController::class, 'media'])->middleware($guard);
$router->get('/admin/reel-player/cover/(?P<id>\d+)', [PlayerController::class, 'cover'])->middleware($guard);
$router->get('/admin/reel-player/api/drive/status', [PlayerController::class, 'driveStatus'])->middleware($guard);
$router->get('/admin/reel-player/api/drive/files', [PlayerController::class, 'driveFiles'])->middleware($guard);
$router->get('/admin/reel-player/drive/callback', [PlayerController::class, 'driveCallback'])->middleware($guard);
$router->get('/admin/reel-player/catalog/media/(?P<id>\d+)', [PlayerController::class, 'catalogMedia'])->middleware($guard);
$router->get('/admin/reel-player/catalog/cover/(?P<id>\d+)', [PlayerController::class, 'catalogCover'])->middleware($guard);
$router->get('/admin/reel-player/catalog/album-cover/(?P<id>\d+)', [PlayerController::class, 'albumCover'])->middleware($guard);
$router->get('/admin/reel-player/catalog/artist-image/(?P<id>\d+)', [PlayerController::class, 'artistImage'])->middleware($guard);
$router->get('/admin/reel-player/api/catalog/quality/(?P<id>\d+)', [PlayerController::class, 'catalogQuality'])->middleware($guard);
$router->post('/admin/reel-player/api/catalog/prepare', [PlayerController::class, 'catalogPrepare'])->middleware($guard);
$router->post('/admin/reel-player/api/catalog/listen', [PlayerController::class, 'catalogListen'])->middleware($guard);

require __DIR__ . '/routes-public.php';
require __DIR__ . '/routes-discovery.php';
require __DIR__ . '/routes-preferences.php';
