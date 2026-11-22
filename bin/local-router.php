<?php

// Router for the loopback-only PHP development server; mirrors public/.htaccess.
$public = realpath(dirname(__DIR__) . '/public');
$path = rawurldecode(explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0]);
if (str_contains($path, "\0") || str_contains($path, '\\')
    || preg_match('~(?:^|/)\.(?:[^/]*)(?:/|$)~', $path)
    || preg_match('~\.(?:php[0-9]?|phtml|phar|ini|sql|log|bak)(?:/|$)~i', $path)
        && !in_array($path, ['/index.php', '/theme-asset.php'], true)) {
    http_response_code(404);
    exit;
}

if (str_starts_with($path, '/themes/')) {
    $_GET['path'] = ltrim($path, '/');
    require $public . '/theme-asset.php';
    return;
}

$file = realpath($public . $path);
$dynamic = in_array($path, ['/manifest.webmanifest', '/service-worker.js', '/sw.js'], true);
if (!$dynamic && $file !== false && str_starts_with($file, $public . '/') && is_file($file)) {
    return false;
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $public . '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
require $public . '/index.php';
