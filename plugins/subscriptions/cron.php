<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once ROOT . '/vendor/autoload.php';
require_once HELPERS . '/helpers.php';

$app = new \FBL\Application();
require_once __DIR__ . '/Plugin.php';

try {
    // CLI maintenance bypasses the plugin manager's language registration.
    \FBL\Language::registerPluginLanguage(FireballPluginSubscriptions::SLUG, __DIR__ . '/lang');
    $result = (new \Fireball\Subscriptions\Services\MaintenanceService())->run();
    fwrite(STDOUT, json_encode(['status' => 'ok', 'result' => $result], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
} catch (Throwable $exception) {
    log_error_details('Subscriptions maintenance failed', [], $exception);
    fwrite(STDERR, json_encode(['status' => 'error', 'message' => $exception->getMessage()], JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}
