<?php

$start_framework = microtime(true);

/* I recommend using the phpstorm editor. */

if (PHP_VERSION_ID < 80200) {

    echo "Requires PHP version 8.2 or higher!";
    exit;
}

require_once __DIR__ . '/../config/config.php';

if (!is_dir(dirname(ERROR_LOGS))) {
    @mkdir(dirname(ERROR_LOGS), 0755, true);
}

ini_set('log_errors', '1');
ini_set('error_log', ERROR_LOGS);
error_reporting(E_ALL);

if (!file_exists(ROOT . '/vendor/autoload.php')) {
    error_log('[' . date('Y-m-d H:i:s') . '] Bootstrap error: vendor/autoload.php not found' . PHP_EOL, 3, ERROR_LOGS);
    http_response_code(500);
    exit('Autoloader not found.');
}

require_once ROOT . '/vendor/autoload.php';
require_once HELPERS . '/helpers.php';

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

register_shutdown_function('log_last_php_error');

$whoops = new \Whoops\Run();

if (DEBUG) {
    $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler());
} else {
    $whoops->pushHandler(new \Whoops\Handler\CallbackHandler(function (Throwable $e) {
        log_error_details('Unhandled application exception', [], $e);
        abort('Sorry! An error has occurred!', 500);
    }));
}

$whoops->register();

$app = new \FBL\Application();
require_once CONFIG . '/routes.php';
$app->run();
