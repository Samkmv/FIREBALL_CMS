<?php
declare(strict_types=1);

// Dedicated rental expiry runner; never expose it as an HTTP endpoint.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$cmsRoot = dirname(__DIR__, 2);
if (is_file($cmsRoot . '/storage/update.maintenance')) {
    fwrite(STDERR, "CMS update is in progress.\n");
    exit(1);
}
$runtimeLock = @fopen($cmsRoot . '/storage/update.lock', 'c+');
if (!is_resource($runtimeLock) || !flock($runtimeLock, LOCK_SH | LOCK_NB)
    || is_file($cmsRoot . '/storage/update.maintenance')) {
    fwrite(STDERR, "CMS update is in progress.\n");
    exit(1);
}

define('FIREBALL_CLI', true);
require $cmsRoot . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require HELPERS . '/helpers.php';
\FBL\PerformanceProfiler::start();

try {
    $app = new \FBL\Application(false);
    $app->db = new \FBL\Database();
    $app->plugins = new \FBL\Plugins\PluginManager();
    $active = db()->query('SELECT status FROM plugins WHERE slug = ? LIMIT 1', ['toy-car-rental'])->getColumn() === 'active';
    if (!$active) {
        if (in_array('--check', $argv, true)) echo "Rental background check: plugin is inactive.\n";
        exit(0);
    }
    require_once __DIR__ . '/Plugin.php';
    (new FireballPluginToyCarRental())->boot();
    // Keep unrelated CMS/plugin jobs outside this dedicated runner.
    add_filter('fireball_scheduled_jobs', static fn(array $jobs): array => array_intersect_key($jobs, ['toy_rental_expiry' => true]), PHP_INT_MAX);

    if (in_array('--check', $argv, true)) {
        $jobs = apply_filters_safe('fireball_scheduled_jobs', []);
        if (!isset($jobs['toy_rental_expiry'])) throw new RuntimeException('Rental expiry job is missing.');
        echo "Rental background check: ready; schedule: " . $jobs['toy_rental_expiry']['schedule'] . ".\n";
        exit(0);
    }
    $result = (new \App\Services\SchedulerService())->run();
    if ((int)$result['failed'] > 0) {
        fwrite(STDERR, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        exit(1);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "Rental background check failed: " . $exception->getMessage() . "\n");
    exit(1);
}
