<?php
// Offline only. Use after a failed update, before allowing public traffic again.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('--confirm', $argv, true)) {
    fwrite(STDERR, "Usage: php bin/recover-update.php --confirm\nRestores the pre-update code AND database. Keep the site in maintenance.\n");
    exit(1);
}
try {
    require dirname(__DIR__) . '/config/config.php';
    require ROOT . '/app/Services/SqlFileRunner.php';
    require ROOT . '/app/Services/UpdateRecoveryService.php';
    $dsn = 'mysql:host=' . DB_SETTINGS['host'] . ';port=' . (int)(DB_SETTINGS['port'] ?? 3306) . ';dbname=' . DB_SETTINGS['database'] . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_SETTINGS['username'], DB_SETTINGS['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    (new App\Services\UpdateRecoveryService(ROOT))->restore($pdo);
    echo "Pre-update code and database restored. Maintenance is disabled.\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\nMaintenance remains enabled.\n");
    exit(1);
}
