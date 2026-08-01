<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function hls_player_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function hls_player_section(string $source, string $start, string $end): string
{
    $startOffset = strpos($source, $start);
    hls_player_assert($startOffset !== false, sprintf('Missing JavaScript section: %s', $start));

    $endOffset = strpos($source, $end, $startOffset + strlen($start));
    hls_player_assert($endOffset !== false, sprintf('Missing JavaScript section terminator: %s', $end));

    return substr($source, $startOffset, $endOffset - $startOffset);
}

$playerFiles = [
    $root . '/public/assets/default/js/plyr-init.js',
    $root . '/themes/default/assets/js/plyr-init.js',
];

foreach ($playerFiles as $playerFile) {
    $source = (string)file_get_contents($playerFile);
    hls_player_assert($source !== '', sprintf('Player source is empty: %s', $playerFile));

    $backendWake = hls_player_section($source, 'const wakeBackendStream', 'const isCrossOriginUrl');
    hls_player_assert(
        str_contains($backendWake, 'getBackendWakeTimeoutMs()'),
        sprintf('Backend wake does not use the configured readiness timeout: %s', $playerFile)
    );
    hls_player_assert(
        str_contains($backendWake, 'data.ready === true'),
        sprintf('Backend wake does not require ready=true: %s', $playerFile)
    );
    hls_player_assert(
        str_contains($backendWake, 'return wakeSucceeded;'),
        sprintf('Backend wake still reports every request as successful: %s', $playerFile)
    );
    hls_player_assert(
        !str_contains($backendWake, '}, 5000);'),
        sprintf('Backend wake still has the old five-second abort race: %s', $playerFile)
    );

    $sourceWake = hls_player_section($source, 'const ensureHlsSourceAwake', 'const prewarmHlsSource');
    hls_player_assert(
        str_contains($sourceWake, 'element.hlsBackendReady === true'),
        sprintf('Cross-origin lazy HLS is marked ready without backend confirmation: %s', $playerFile)
    );

    $wakeAndPlay = hls_player_section($source, 'const wakeAndPlay', 'const bootstrapPlayIntent');
    $backendReadyOffset = strpos($wakeAndPlay, 'const backendReady = await wakeBackendStream(element);');
    $nativePrepareOffset = strpos($wakeAndPlay, 'prepareNativeHlsPlayback(element)');
    hls_player_assert(
        $backendReadyOffset !== false && $nativePrepareOffset !== false && $backendReadyOffset < $nativePrepareOffset,
        sprintf('Playback starts before backend readiness is confirmed: %s', $playerFile)
    );

    $restart = hls_player_section($source, 'const restartHlsPlayback', 'const fallbackNativeHlsOrRestart');
    hls_player_assert(
        str_contains($restart, 'wakeBackendStream(element).then'),
        sprintf('Reconnect does not wake a sleeping lazy stream: %s', $playerFile)
    );
}

$layout = (string)file_get_contents($root . '/app/Views/layouts/default.php');
hls_player_assert(
    str_contains($layout, "'readyTimeoutMs' => (int)\$streamConfig['ready_timeout_seconds'] * 1000"),
    'The backend readiness timeout is not exposed to the published player.'
);

echo json_encode([
    'status' => 'ok',
    'players_checked' => count($playerFiles),
    'backend_ready_gate' => true,
    'configured_timeout' => true,
    'cross_origin_lazy_hls' => true,
    'reconnect_wake' => true,
], JSON_UNESCAPED_SLASHES), PHP_EOL;
