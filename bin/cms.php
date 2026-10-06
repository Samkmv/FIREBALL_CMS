<?php

/** Explicit maintenance entry point; never route this file through HTTP. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$command = $argv[1] ?? 'help';
$cmsStorage = dirname(__DIR__) . '/storage';
if (is_file($cmsStorage . '/update.maintenance')) { fwrite(STDERR, "Update maintenance is active. Use offline recovery.\n"); exit(1); }
$GLOBALS['fireball_runtime_lock'] = @fopen($cmsStorage . '/update.lock', 'c+');
if (!is_resource($GLOBALS['fireball_runtime_lock']) || !flock($GLOBALS['fireball_runtime_lock'], LOCK_SH | LOCK_NB)
    || is_file($cmsStorage . '/update.maintenance')) { fwrite(STDERR, "Update in progress.\n"); exit(1); }
define('FIREBALL_CLI', true);
require dirname(__DIR__) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require HELPERS . '/helpers.php';
\FBL\PerformanceProfiler::start();
$app = new \FBL\Application(false);
try {
    if ($command === 'diagnose') {
        $status = $app->inspectInstallation(true);
        $status['upload_limits'] = \App\Services\UploadPolicy::limits();
        echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit($status['state'] === 'installed' ? 0 : 1);
    }
    if ($command === 'migrate') {
        $app->db = new \FBL\Database();
        $executed = (new \App\Services\MigrationRunner())->run();
        $app->plugins = new \FBL\Plugins\PluginManager();
        $app->plugins->migrateInstalledPlugins();
        $app->bootInstalledServices();
        require CONFIG . '/routes.php';
        \App\Services\SearchMaintenance::rebuild(null, search_registry());
        \FBL\AssetManifest::rebuild();
        echo 'Applied migrations: ' . count($executed) . PHP_EOL;
        foreach ($executed as $name) {
            echo $name . PHP_EOL;
        }
        exit;
    }
    if ($command === 'scheduler:run') {
        $app->bootInstalledServices();

        // Loading routes boots active plugins, so their scheduled jobs
        // are registered through fireball_scheduled_jobs.
        require CONFIG . '/routes.php';

        $result = (new \App\Services\SchedulerService())->run();
        $failed = (int)($result['failed'] ?? 0);

        echo json_encode(
            [
                'status' => $failed > 0 ? 'partial' : 'ok',
                'scheduler' => $result,
            ],
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PARTIAL_OUTPUT_ON_ERROR
        ) . PHP_EOL;

        exit($failed > 0 ? 1 : 0);
    }

    if ($command === 'assets:rebuild') {
        echo 'Versioned assets: ' . \FBL\AssetManifest::rebuild() . PHP_EOL;
        exit;
    }
    if ($command === 'cache:clear') {
        echo 'Removed cache files: ' . $app->cache->clear() . PHP_EOL;
        exit;
    }
    if ($command === 'search:reindex') {
        $app->bootInstalledServices();
        require CONFIG . '/routes.php';
        \App\Services\SearchMaintenance::rebuild(null, search_registry(), in_array('--allow-empty', $argv, true));
        echo "Search index refreshed.\n";
        exit;
    }
    echo "Usage: php bin/cms.php diagnose|migrate|scheduler:run|cache:clear|assets:rebuild|search:reindex [--allow-empty]\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
