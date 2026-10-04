<?php
declare(strict_types=1);

require __DIR__ . '/../app/Services/Maintenance/DatabaseBackupService.php';
function return_translation(string $key): string {
    static $translations;
    $translations ??= require __DIR__ . '/../app/Languages/ru.php';
    return $translations[$key] ?? $key;
}
$checks = 0;
function backupCheck(bool $condition, string $message): void {
    $GLOBALS['checks']++;
    if (!$condition) throw new RuntimeException($message);
}
$method = new ReflectionMethod(App\Services\Maintenance\DatabaseBackupService::class, 'assertMetadataVisibility');
$allowed = [
    ['GRANT ALL PRIVILEGES ON *.* TO `fixture`@`localhost`'],
    ['GRANT ALL PRIVILEGES ON `cms`.* TO `fixture`@`localhost`'],
    ['GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, RELOAD, PROCESS, FILE, REFERENCES, INDEX, ALTER, SHOW DATABASES, SUPER, CREATE TEMPORARY TABLES, LOCK TABLES, EXECUTE, CREATE VIEW, SHOW VIEW, CREATE ROUTINE, ALTER ROUTINE, EVENT, TRIGGER ON *.* TO `fixture`@`localhost`'],
    ['GRANT SELECT, SHOW VIEW, TRIGGER, EVENT, EXECUTE ON `cms`.* TO `fixture`@`localhost`'],
    ['GRANT SELECT, SHOW VIEW ON `cms`.* TO `fixture`@`localhost`', 'GRANT TRIGGER, EVENT, CREATE ROUTINE ON `cms`.* TO `fixture`@`localhost`'],
    ['GRANT SELECT, SHOW VIEW, TRIGGER, EVENT ON `cms`.* TO `fixture`@`localhost`', 'GRANT SHOW_ROUTINE ON *.* TO `fixture`@`localhost`'],
];
foreach ($allowed as $grants) {
    $method->invoke(new App\Services\Maintenance\DatabaseBackupService(), $grants, 'cms');
    backupCheck(true, 'Complete schema/global grants accepted');
}
$method->invoke(new App\Services\Maintenance\DatabaseBackupService(), ['GRANT ALL PRIVILEGES ON `cms\_live`.* TO fixture'], 'cms_live');
backupCheck(true, 'Escaped underscore schema matches');
$method->invoke(new App\Services\Maintenance\DatabaseBackupService(), ['GRANT ALL PRIVILEGES ON `cms``live`.* TO fixture'], 'cms`live');
backupCheck(true, 'Escaped backtick schema matches');
$denied = [
    ['GRANT SELECT, INSERT, UPDATE, DELETE ON `cms`.* TO fixture'],
    ['GRANT ALL PRIVILEGES ON `other`.* TO fixture'],
    ['GRANT ALL PRIVILEGES ON `cms`.`users` TO fixture'],
    ['GRANT `owner`@`%` TO fixture'],
    ['GRANT ALL PRIVILEGES ON `*`.* TO fixture'],
    ['GRANT SELECT, SHOW VIEW, TRIGGER, EVENT ON `cms`.* TO fixture'],
    ['GRANT SELECT, SHOW VIEW, TRIGGER, EXECUTE ON `cms`.* TO fixture'],
    ['GRANT ALL PRIVILEGES ON *.* TO fixture', 'REVOKE SELECT ON `cms`.* FROM fixture'],
];
foreach ($denied as $grants) {
    $service = new App\Services\Maintenance\DatabaseBackupService();
    try {
        $method->invoke($service, $grants, 'cms');
        throw new LogicException('Unverifiable backup accepted');
    } catch (RuntimeException $error) {
        backupCheck($service->lastFailure() === return_translation('admin_update_backup_permissions_failed'), 'Hidden metadata refuses backup with a safe translated reason');
    }
}
$pipes = [];
$process = proc_open([PHP_BINARY, __DIR__ . '/plugin_updates_fixture.php', 'ru', 'installfailed'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
$html = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
foreach ($pipes as $pipe) fclose($pipe);
backupCheck(proc_close($process) === 0 && $error === '', 'Real plugin template renders');
backupCheck(str_contains($html, return_translation('admin_plugin_updates_install_failed')), 'Installation failure is distinguished from checking failure');
backupCheck(str_contains($html, return_translation('admin_update_backup_permissions_failed')) && str_contains($html, '&lt;fixture&gt;') && !str_contains($html, '<fixture>'), 'Safe reason is visible and HTML-escaped');
echo "Database backup policy and plugin failure UI: $checks checks passed.\n";
