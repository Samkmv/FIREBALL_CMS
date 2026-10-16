<?php
use Fireball\ReelPlayer\Controllers\PlayerController;

/** @var \FBL\Router $router */
$router->get('/plugins/reel-player/assets/(?P<file>player\.(?:css|js)|preload\.js|meters\.js|chapters\.js|transport\.js|quality\.js|collections\.js|catalog\.js|images\.js|(?:cover|artist)-(?:light|dark)(?:\.png|-(?:96|320|640)\.webp))', static function (): never {
    $file = (string)get_route_param('file');
    $allowed = ['player.css','player.js','preload.js','meters.js','chapters.js','transport.js','quality.js','collections.js','catalog.js','images.js'];
    foreach (['cover','artist'] as $kind) foreach (['light','dark'] as $theme) {
        $allowed[] = $kind . '-' . $theme . '.png';
        foreach ([96,320,640] as $size) $allowed[] = $kind . '-' . $theme . '-' . $size . '.webp';
    }
    if (!in_array($file, $allowed, true)) abort();
    $kind = str_ends_with($file, '.css') ? 'css' : (str_ends_with($file, '.js') ? 'js' : 'images');
    $path = ROOT . '/themes/tape-room/assets/' . $kind . '/' . $file;
    if (!is_file($path)) abort();
    if ($file === 'player.css') {
        // Relative font URLs must resolve next to the moved stylesheet.
        response()->redirect(base_url('/themes/tape-room/assets/css/player.css'));
    }
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

$router->get('/admin/reel-player', [PlayerController::class, 'index'])->middleware(['auth', 'admin']);
$router->get('/tape-room/(?P<surface>share|embed)/(?P<kind>album|track)/(?P<id>\d+)', [\Fireball\ReelPlayer\Controllers\ShareController::class, 'show']);
$router->get('/admin/reel-player/settings', [\Fireball\ReelPlayer\Controllers\AdminCatalogController::class, 'settings'])->middleware(['auth','admin']);
$router->post('/admin/reel-player/settings', [\Fireball\ReelPlayer\Controllers\AdminCatalogController::class, 'saveSettings'])->middleware(['auth','admin']);
$router->get('/tape-room/torrent-worker.js', [\Fireball\ReelPlayer\Services\TorrentWorker::class, 'script']);
$router->get('/tape-room/ui-kit', static function (): string {
    if (theme()->getActiveTheme()['slug'] !== 'tape-room') abort('Страница недоступна.',404);
    $preview = db()->query("SELECT id FROM reel_albums WHERE published=1 AND moderation_status='approved' ORDER BY id DESC LIMIT 1")->getOne();
    return theme()->render('ui-kit',['title'=>'UI-компоненты Tape Room','tr_wide_page'=>true,'tr_ui_embed_album_id'=>(int)($preview['id'] ?? 0)]);
})->middleware(['auth','admin']);
$router->get('/admin/reel-player/genres', [\Fireball\ReelPlayer\Controllers\AdminCatalogController::class, 'genres'])->middleware(['auth','admin']);
$router->post('/admin/reel-player/genres/save', [\Fireball\ReelPlayer\Controllers\AdminCatalogController::class, 'saveGenre'])->middleware(['auth','admin']);
$router->get('/admin/reel-player/albums/(?P<id>\d+)', [\Fireball\ReelPlayer\Controllers\AdminCatalogController::class, 'album'])->middleware(['auth','admin']);
$router->post('/admin/reel-player/albums/review', [\Fireball\ReelPlayer\Controllers\AdminCatalogController::class, 'review'])->middleware(['auth','admin']);
$router->get('/admin/reel-player/review-media/(?P<album>\d+)/(?P<id>\d+)', [PlayerController::class, 'reviewMedia'])->middleware(['auth','admin']);
$router->get('/admin/reel-player/review-cover/(?P<id>\d+)', [PlayerController::class, 'reviewCover'])->middleware(['auth','admin']);

// New browser requests use the front-end URLs. Keep administrator aliases for
// existing sessions and the registered OAuth callback URL for Google Drive.
foreach (['/tape-room' => ['auth'], '/admin/reel-player' => ['auth', 'admin']] as $prefix => $guard) {
    $readGuard=$prefix==='/tape-room' ? [] : $guard;
    $router->get($prefix . '/api/state', [PlayerController::class, 'state'])->middleware($readGuard);
    $router->get($prefix . '/api/quality/(?P<id>\d+)', [PlayerController::class, 'quality'])->middleware($guard);
    $router->post($prefix . '/api/action', [PlayerController::class, 'action'])->middleware($guard);
    $router->post($prefix . '/api/upload', [PlayerController::class, 'upload'])->middleware($guard);
    $router->post($prefix . '/api/prepare', [PlayerController::class, 'prepare'])->middleware($guard);
    $router->post($prefix . '/api/chapters/cue', [PlayerController::class, 'cuePreview'])->middleware($guard);
    $router->post($prefix . '/api/chapters/analyze', [PlayerController::class, 'chapterAnalysis'])->middleware($guard);
    $router->get($prefix . '/media/(?P<id>\d+)', [PlayerController::class, 'media'])->middleware($guard);
    $router->get($prefix . '/cover/(?P<id>\d+)', [PlayerController::class, 'cover'])->middleware($guard);
    $router->get($prefix . '/api/drive/status', [PlayerController::class, 'driveStatus'])->middleware($guard);
    $router->get($prefix . '/api/drive/files', [PlayerController::class, 'driveFiles'])->middleware($guard);
    $router->post($prefix . '/api/drive/metadata', [PlayerController::class, 'driveMetadata'])->middleware($guard);
    $router->get($prefix . '/drive/callback', [PlayerController::class, 'driveCallback'])->middleware(['auth']);
    $router->get($prefix . '/catalog/media/(?P<id>\d+)', [PlayerController::class, 'catalogMedia'])->middleware($readGuard);
    $router->get($prefix . '/catalog/cover/(?P<id>\d+)', [PlayerController::class, 'catalogCover'])->middleware($readGuard);
    $router->get($prefix . '/catalog/album-cover/(?P<id>\d+)', [PlayerController::class, 'albumCover'])->middleware($readGuard);
    $router->get($prefix . '/catalog/artist-image/(?P<id>\d+)', [PlayerController::class, 'artistImage'])->middleware($readGuard);
    $router->get($prefix . '/api/catalog/quality/(?P<id>\d+)', [PlayerController::class, 'catalogQuality'])->middleware($readGuard);
    $router->post($prefix . '/api/catalog/prepare', [PlayerController::class, 'catalogPrepare'])->middleware($guard);
    $router->post($prefix . '/api/catalog/listen', [PlayerController::class, 'catalogListen'])->middleware($guard);
}
