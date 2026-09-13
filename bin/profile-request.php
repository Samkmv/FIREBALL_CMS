<?php

// CLI-only aggregate regression probe. Does not enable public production diagnostics.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$_SERVER['REQUEST_URI'] = $argv[1] ?? '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = getenv('FIREBALL_PROFILE_HOST') ?: 'localhost:8888';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '8888';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/public/index.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '', $_GET);
putenv('FIREBALL_PERFORMANCE_DEBUG=1');
ob_start();
register_shutdown_function(function () {
    $html = ob_get_contents();
    ob_end_clean();
    $metrics = class_exists('FBL\\PerformanceProfiler') ? \FBL\PerformanceProfiler::snapshot() : [];
    $metrics['status'] = http_response_code() ?: 200;
    $metrics['html_bytes'] = strlen($html);
    preg_match_all('/<(?:script|link)\b[^>]*(?:src|href)=["\']([^"\']+)["\']/i', $html, $matches);
    $metrics['assets'] = array_values(array_filter($matches[1], fn($url) => preg_match('/\.(css|js)(\?|$)/', $url)));
    echo json_encode($metrics, JSON_UNESCAPED_SLASHES) . PHP_EOL;
});
require $root . '/public/index.php';
