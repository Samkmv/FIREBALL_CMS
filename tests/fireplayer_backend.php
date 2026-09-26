<?php
declare(strict_types=1);
namespace App\Controllers { class BaseController {} }
namespace {
    require __DIR__ . '/../app/Controllers/StreamController.php';
    require __DIR__ . '/../app/Services/FrontendAssets.php';
    $cache = sys_get_temp_dir() . '/fireplayer-test-' . bin2hex(random_bytes(6));
    mkdir($cache, 0700); mkdir($cache . '/.locks', 0700); define('CACHE', $cache);
    $allowedBases = ['https://camera.example/rtsp'];
    function stream_config(): array { return ['allowed_hls_bases' => $GLOBALS['allowedBases']]; }
    function check(bool $condition, string $message): void {
        if (!$condition) { throw new \RuntimeException($message); }
        echo "PASS $message\n";
    }
    $controller = new \App\Controllers\StreamController();
    $call = static function (string $method, ...$args) use ($controller) {
        return (new \ReflectionMethod($controller, $method))->invoke($controller, ...$args);
    };
    try {
        $url = 'https://camera.example/rtsp/stream-33-01/index.m3u8';
        check($call('isAllowedHlsUrl', $url, '33-01'), 'configured origin/path accepted');
        foreach (['http://127.0.0.1/stream-33-01/index.m3u8',
            'https://camera.example.evil/rtsp/stream-33-01/index.m3u8',
            'https://camera.example/other/stream-33-01/index.m3u8',
            'https://user:secret@camera.example/rtsp/stream-33-01/index.m3u8',
            'https://camera.example/rtsp/%2e%2e/stream-33-01/index.m3u8',
            'https://camera.example:4430/rtsp/stream-33-01/index.m3u8'] as $bad) {
            check(!$call('isAllowedHlsUrl', $bad, '33-01'), 'untrusted HLS URL rejected');
        }
        check(!$call('isAllowedHlsUrl', $url, 'another'), 'stream ID mismatch rejected');
        $configuredBases = $allowedBases;
        $productionConfig = require __DIR__ . '/../config/streams.php';
        $allowedBases = $productionConfig['allowed_hls_bases'];
        check($call('isAllowedHlsUrl', 'https://cam.maxipapa.ru/rtsp/stream-33-01/index.m3u8', '33-01'), 'published MAXIPAPA camera works without Camera Manager class');
        check($call('isAllowedHlsUrl', 'https://rtsp.ddns.net/rtsp/stream-34-01/index.m3u8', '34-01'), 'published legacy camera host remains allowed');
        check(!$call('isAllowedHlsUrl', 'https://cam.maxipapa.ru.evil/rtsp/stream-33-01/index.m3u8', '33-01'), 'published camera allowlist does not accept lookalike host');
        $allowedBases = $configuredBases;
        $manifest = "#EXTM3U\n#EXT-X-MAP:URI=\"init.mp4\"\n#EXTINF:4,\none.ts\n#EXTINF:4,\ntwo.ts\n#EXTINF:4,\nthree.ts\n#EXTINF:4,\nfour.ts\n";
        $segments = $call('mediaSegmentUrls', $manifest, $url, 3);
        check(count($segments) === 3 && str_ends_with($segments[0], '/four.ts') && str_ends_with($segments[2], '/two.ts'), 'three newest media segments in reverse order');
        check(str_ends_with($call('resolveUrl', $url, 'four.ts?token=a/../b'), '?token=a/../b'), 'signed query preserved verbatim');
        $key = hash('sha256', '33-01|' . $url);
        $lockPath = CACHE . '/.locks/stream-' . substr($key, 0, 2);
        $lock = fopen($lockPath, 'c+'); flock($lock, LOCK_EX);
        $config = ['ready_timeout_seconds' => 1, 'ready_interval_ms' => 500, 'http_timeout_seconds' => 1, 'ready_cache_seconds' => 5];
        $result = $call('coordinateReadiness', '33-01', $url, $config);
        check($result['state'] === 'starting' && $result['retryable'], 'concurrent worker returns STARTING without upstream requests');
        fwrite($lock, json_encode(['key' => $key, 'expires' => microtime(true) + 5])); fflush($lock);
        flock($lock, LOCK_UN); fclose($lock);
        $result = $call('coordinateReadiness', '33-01', $url, $config);
        check($result['ready'] && $result['code'] === 'READY_CACHED', 'warm readiness served without upstream requests');
        if (function_exists('pcntl_fork')) {
            // A real, isolated local HTTP fixture: newest segment absent, previous ready.
            $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
            if (!$server) { throw new \RuntimeException('HTTP fixture failed to start'); }
            $address = stream_socket_get_name($server, false);
            $allowedBases[] = 'http://' . $address . '/rtsp';
            $pid = pcntl_fork();
            if ($pid === 0) {
                for ($i = 0; $i < 4; $i++) {
                    $connection = stream_socket_accept($server, 5);
                    if (!$connection) { exit(2); }
                    $request = fgets($connection);
                    while (($line = fgets($connection)) !== false && trim($line) !== '') {}
                    $manifestRequest = str_contains($request, 'index.m3u8');
                    $missing = str_contains($request, 'new.ts');
                    $body = $manifestRequest ? "#EXTM3U\n#EXTINF:4,\nold.ts\n#EXTINF:4,\nnew.ts\n" : '';
                    fwrite($connection, 'HTTP/1.1 ' . ($missing ? '404 Not Found' : '200 OK') . "\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n" . $body);
                    fclose($connection);
                }
                fclose($server); exit(0);
            }
            fclose($server);
            $result = $call('coordinateReadiness', 'local', 'http://' . $address . '/rtsp/stream-local/index.m3u8', $config);
            pcntl_waitpid($pid, $status);
            check(pcntl_wexitstatus($status) === 0 && $result['ready'], 'real HTTP: GET manifest, newest HEAD/Range 404, previous HEAD ready');
        }
        $assets = \App\Services\FrontendAssets::requirements('<video data-player-native src="hero.mp4"></video>');
        check(empty($assets['player']), 'native-only media omits FirePlayer');
        $assets = \App\Services\FrontendAssets::requirements('<audio src="song.mp3"></audio>');
        check(!empty($assets['player_audio']) && empty($assets['player_hls']), 'MP3 does not request HLS assets');
        $assets = \App\Services\FrontendAssets::requirements('<div class="fire-player" data-src="movie.mp4"></div>');
        check(!empty($assets['player']), 'class-only manual player requests assets');
    } finally {
        foreach (glob($cache . '/.locks/*') as $file) { unlink($file); }
        rmdir($cache . '/.locks'); rmdir($cache);
    }
}
