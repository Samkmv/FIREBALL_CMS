<?php

return [
    // Camera Manager's configured HLS base is trusted automatically. Add other
    // administrator-controlled origins AND path prefixes here (never client input).
    'allowed_hls_bases' => [
        // Existing published camera blocks use both the current host and its
        // legacy address, independently of Camera Manager's selected base.
        'https://cam.maxipapa.ru/rtsp',
        'https://rtsp.ddns.net/rtsp',
    ],
    'ready_timeout_seconds' => 30,
    'ready_interval_ms' => 1500,
    'http_timeout_seconds' => 5,
    'ready_cache_seconds' => 5,
    'ready_segment_probe_count' => 3,
];
