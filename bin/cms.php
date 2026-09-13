<?php

/** Explicit maintenance entry point; never route this file through HTTP. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require HELPERS . '/helpers.php';
\FBL\PerformanceProfiler::start();
$app = new \FBL\Application(false);
$command = $argv[1] ?? 'help';
try {
    if ($command === 'diagnose') {
        $status = $app->inspectInstallation(true);
        echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit($status['state'] === 'installed' ? 0 : 1);
    }
    if ($command === 'migrate') {
        $app->db = new \FBL\Database();
        $executed = (new \App\Services\MigrationRunner())->run();
        $app->plugins = new \FBL\Plugins\PluginManager();
        $app->plugins->migrateInstalledPlugins();
        echo 'Applied migrations: ' . count($executed) . PHP_EOL;
        foreach ($executed as $name) {
            echo $name . PHP_EOL;
        }
        exit;
    }
    if ($command === 'cache:clear') {
        echo 'Removed cache files: ' . $app->cache->clear() . PHP_EOL;
        exit;
    }
    if ($command === 'search:reindex') {
        $app->bootInstalledServices();
        require CONFIG . '/routes.php';
        foreach (search_registry()->names() as $name) {
            search_indexer()->reindexProvider($name);
        }
        echo "Search index refreshed.\n";
        exit;
    }
    echo "Usage: php bin/cms.php diagnose|migrate|cache:clear|search:reindex\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
