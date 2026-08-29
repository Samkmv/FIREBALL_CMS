<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function fireplayer_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$assets = [
    'core' => $root . '/public/assets/default/js/fireplayer.js',
    'video' => $root . '/public/assets/default/js/fireplayer-video.js',
    'audio' => $root . '/public/assets/default/js/fireplayer-audio.js',
    'hls' => $root . '/public/assets/default/js/fireplayer-hls.js',
    'live' => $root . '/public/assets/default/js/fireplayer-live.js',
    'init' => $root . '/public/assets/default/js/fireplayer-init.js',
    'css' => $root . '/public/assets/default/css/fireplayer.css',
];

foreach ($assets as $name => $path) {
    fireplayer_assert(is_file($path) && filesize($path) > 100, sprintf('FirePlayer %s asset is missing or empty.', $name));
}

$core = (string)file_get_contents($assets['core']);
foreach (['static detect(', 'static mount(', 'async load(', 'async reconnect(', 'goLive()', 'pictureInPicture()', 'destroy()'] as $api) {
    fireplayer_assert(str_contains($core, $api), sprintf('FirePlayer API is missing %s.', $api));
}
fireplayer_assert(str_contains($core, "extension === 'm3u8'"), 'HLS URL auto detection is missing.');
fireplayer_assert(str_contains($core, "extension === 'mpd'"), 'DASH URL recognition is missing.');
fireplayer_assert(str_contains($core, '#EXT-X-ENDLIST'), 'HLS VOD/LIVE manifest detection is missing.');
fireplayer_assert(str_contains($core, 'application/vnd.apple.mpegurl'), 'HLS Content-Type detection is missing.');

$hls = (string)file_get_contents($assets['hls']);
fireplayer_assert(str_contains($hls, "registerAdapter('hls'"), 'The HLS adapter is not registered.');
fireplayer_assert(str_contains($hls, 'canPlayNatively'), 'Native Apple HLS support is missing.');
fireplayer_assert(str_contains($hls, 'new Hls('), 'The hls.js playback path is missing.');
fireplayer_assert(str_contains($hls, "'/api/streams/wake'"), 'Lazy camera stream wake-up was not preserved.');
fireplayer_assert(str_contains($hls, 'recoverMediaError'), 'HLS media error recovery is missing.');

$live = (string)file_get_contents($assets['live']);
fireplayer_assert(str_contains($live, "player.reconnect('stall')"), 'LIVE stall recovery is missing.');
fireplayer_assert(str_contains($live, 'visibilitychange'), 'LIVE visibility recovery is missing.');
fireplayer_assert(str_contains($live, 'posterRefreshInterval'), 'LIVE poster refresh is missing.');

foreach ([$root . '/app/Views/layouts/default.php', $root . '/themes/default/templates/layout.php'] as $layoutPath) {
    $layout = (string)file_get_contents($layoutPath);
    fireplayer_assert(str_contains($layout, 'fireplayer.css'), sprintf('FirePlayer CSS is not loaded by %s.', $layoutPath));
    fireplayer_assert(str_contains($layout, 'fireplayer.js'), sprintf('FirePlayer core is not loaded by %s.', $layoutPath));
    fireplayer_assert(str_contains($layout, 'fireplayer-hls.js'), sprintf('FirePlayer HLS module is not loaded by %s.', $layoutPath));
    fireplayer_assert(str_contains($layout, 'fireplayer-init.js'), sprintf('FirePlayer bootstrap is not loaded by %s.', $layoutPath));
}

foreach ([$root . '/public/assets/default/js/plyr-init.js', $root . '/themes/default/assets/js/plyr-init.js'] as $plyrPath) {
    $plyr = (string)file_get_contents($plyrPath);
    fireplayer_assert(
        str_contains($plyr, "element.closest('.fireplayer, .fire-player, [data-fire-player]')"),
        sprintf('Plyr can still double-initialize FirePlayer media in %s.', $plyrPath)
    );
}

$cameraDashboard = (string)file_get_contents($root . '/plugins/camera-manager/views/dashboard.php');
fireplayer_assert(str_contains($cameraDashboard, 'data-camera-player-open'), 'Camera Manager has no LIVE player action.');
fireplayer_assert(str_contains($cameraDashboard, 'data-fire-player-manual'), 'Camera Manager player modal is missing.');

$cameraPlugin = (string)file_get_contents($root . '/plugins/camera-manager/Plugin.php');
fireplayer_assert(str_contains($cameraPlugin, 'camera-manager-player.js'), 'Camera Manager does not publish its FirePlayer controller.');

echo json_encode([
    'status' => 'ok',
    'assets_checked' => count($assets),
    'auto_detect' => true,
    'hls_live' => true,
    'camera_manager' => true,
    'plyr_compatibility' => true,
], JSON_UNESCAPED_SLASHES), PHP_EOL;
