<?php

$start_framework = microtime(true);

/* I recommend using the phpstorm editor. */

if (PHP_MAJOR_VERSION < 8) {

    echo "Requires PHP version 8.0 or higher!";
    exit;
}

require_once __DIR__ . '/../config/config.php';

require_once ROOT . '/vendor/autoload.php';
require_once HELPERS . '/helpers.php';

$whoops = new \Whoops\Run();

if (DEBUG) {
    $whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler());
} else {
    $whoops->pushHandler(new \Whoops\Handler\CallbackHandler(function (Throwable $e) {
        error_log("[" . date('Y-m-d H:i:s') . "] Error: {$e->getMessage()}" . PHP_EOL . "File: {$e->getFile()}" . PHP_EOL . "Line: {$e->getLine()}" . PHP_EOL . '========================================================================' . PHP_EOL, 3, ERROR_LOGS);
        abort('Sorry! An error has occurred!', 500);
    }));
}

$whoops->register();

$app = new \FBL\Application();
require_once CONFIG . '/routes.php';
$app->run();