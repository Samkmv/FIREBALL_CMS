<?php
use Fireball\ReelPlayer\Controllers\DistributionController;
use Fireball\ReelPlayer\Controllers\PublicController;

/** @var \FBL\Router $router */
// Keep an existing CMS page named "music" reachable while this feature is off.
if (\Fireball\ReelPlayer\Services\PlatformFeatures::enabled('public_catalog')) {
$router->get('/music', [PublicController::class, 'index']);
$router->get('/music/(?P<section>albums|artists|tracks|new|popular|genres)', [PublicController::class, 'index']);
$router->get('/music/(?P<section>collection)', [PublicController::class, 'index'])->middleware(['auth']);
$router->get('/music/(?P<type>album|track)/(?P<id>\d+)(?:-(?P<slug>[a-z0-9-]+))?', [PublicController::class, 'detail']);
$router->get('/music/api/catalog', [PublicController::class, 'catalog']);
$router->get('/music/api/albums/(?P<id>\d+)', [PublicController::class, 'album']);
$router->get('/music/api/tracks/(?P<id>\d+)', [PublicController::class, 'track']);
$router->get('/music/api/artists', [PublicController::class, 'artists']);
$router->get('/music/api/quality/(?P<id>\d+)', [PublicController::class, 'quality']);
$router->post('/music/api/prepare', [PublicController::class, 'prepare']);
$router->post('/music/api/collection', [PublicController::class, 'collection'])->middleware(['auth']);
$router->post('/music/api/listen', [PublicController::class, 'listen'])->middleware(['auth']);
$router->get('/music/media/(?P<id>\d+)', [PublicController::class, 'media']);
$router->get('/music/cover/(?P<id>\d+)', [PublicController::class, 'cover']);
$router->get('/music/album-cover/(?P<id>\d+)', [PublicController::class, 'albumCover']);
$router->get('/music/artist-image/(?P<id>\d+)', [PublicController::class, 'artistImage']);
$router->get('/music/download/(?P<id>\d+)', [PublicController::class, 'download']);
$router->get('/music/embed/track/(?P<id>\d+)', [PublicController::class, 'embed']);
$router->get('/music/embed/media/(?P<id>\d+)', [PublicController::class, 'embedMedia']);
}
$router->get('/admin/reel-player/platform', [DistributionController::class, 'index'])->middleware(['auth','admin']);
$router->post('/admin/reel-player/platform/distribution', [DistributionController::class, 'save'])->middleware(['auth','admin']);
