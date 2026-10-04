<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$repository = dirname(__DIR__);
if (getenv('FIREBALL_TEST_MYSQL_HOST') !== false) {
    $local = ['DB_SETTINGS' => ['driver' => 'mysql', 'host' => getenv('FIREBALL_TEST_MYSQL_HOST'), 'port' => (int)(getenv('FIREBALL_TEST_MYSQL_PORT') ?: 3306), 'username' => getenv('FIREBALL_TEST_MYSQL_USER') ?: 'root', 'password' => getenv('FIREBALL_TEST_MYSQL_PASSWORD') ?: '', 'database' => '', 'charset' => 'utf8mb4', 'options' => [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]]];
} else {
    $local = require $repository . '/config/config.local.php';
}
$settings = $local['DB_SETTINGS'] ?? [];
if (!in_array((string)($settings['host'] ?? ''), ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Integration fixture requires local MySQL.');
$databaseName = 'fbl_release_test_' . bin2hex(random_bytes(6));
$fixture = sys_get_temp_dir() . '/fireball-recovery-' . bin2hex(random_bytes(6));
foreach (['config', 'storage', 'public/uploads', 'tmp/cache', 'plugins', 'vendor', 'app', 'core', 'bin', 'database/migrations'] as $dir) mkdir($fixture . '/' . $dir, 0700, true);
$artifact = getenv('FIREBALL_RELEASE_ZIP') ?: '';
if ($artifact !== '') {
    $artifact = realpath($artifact);
    if ($artifact === false || dirname($artifact) !== $repository . '/dist' || !preg_match('/^fireball-cms-[0-9.]+\.zip$/D', basename($artifact))) throw new RuntimeException('Only a locally built release artifact is allowed.');
    $zip = new ZipArchive();
    if ($zip->open($artifact) !== true) throw new RuntimeException('Cannot open release artifact.');
    try {
        for ($i=0; $i<$zip->numFiles; $i++) {
            $name=$zip->getNameIndex($i); $attributes=0; $system=0; $zip->getExternalAttributesIndex($i,$system,$attributes);
            if (str_starts_with($name,'/') || str_contains($name,'\\') || array_intersect(explode('/',$name),['..','.']) || (($attributes>>16)&0170000)===0120000) throw new RuntimeException('Unsafe release artifact.');
        }
        if (!$zip->extractTo($fixture)) throw new RuntimeException('Cannot extract release artifact.');
    } finally { $zip->close(); }
}
$codeRoot = $artifact !== '' ? $fixture : $repository;
foreach (['schema.sql', 'seed.sql', 'demo.sql'] as $file) copy($repository . '/database/' . $file, $fixture . '/database/' . $file);
foreach (glob($repository . '/database/migrations/*') ?: [] as $file) if (is_file($file) && !str_contains(basename($file), '.bak')) copy($file, $fixture . '/database/migrations/' . basename($file));
copy($repository . '/config/config.php', $fixture . '/config/config.php');
copy($repository . '/config/version.php', $fixture . '/config/version.php');
copy($repository . '/update.json', $fixture . '/update.json');
copy($repository . '/public/runtime-gate.php', $fixture . '/public/runtime-gate.php');
if ($artifact === '') file_put_contents($fixture . '/vendor/autoload.php', '<?php');
file_put_contents($fixture . '/app/before.php', '<?php /* original */');
define('ROOT', $fixture);
define('CORE', $codeRoot . '/core');
define('APP', $codeRoot . '/app');
define('HELPERS', $codeRoot . '/helpers');
define('VIEWS', $codeRoot . '/app/Views');
$settings['database'] = $databaseName;
define('DB_SETTINGS', $settings);
define('CHAT_ENCRYPTION_KEY', bin2hex(random_bytes(32)));
define('PATH', 'https://fixture.example.test');
$_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['REQUEST_URI'] = '/';
ini_set('session.save_path', sys_get_temp_dir());
require $fixture . '/config/config.php';
require $codeRoot . '/vendor/autoload.php';
require HELPERS . '/helpers.php';
\FBL\PerformanceProfiler::start();
$dsn = 'mysql:host=' . $settings['host'] . ';port=' . (int)($settings['port'] ?? 3306) . ';charset=utf8mb4';
$server = new PDO($dsn, $settings['username'], $settings['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$created = false;
$checks = 0;
function mysqlCheck(bool $condition, string $message): void { $GLOBALS['checks']++; if (!$condition) throw new RuntimeException($message); }
function removeMysqlFixture(string $path): void { if (is_file($path) || is_link($path)) { unlink($path); return; } foreach (scandir($path) ?: [] as $name) if ($name !== '.' && $name !== '..') removeMysqlFixture($path . '/' . $name); rmdir($path); }
class HiddenMetadataFixtureStatement extends PDOStatement {
    public function __construct() {}
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return ['GRANT SELECT, INSERT ON `fixture`.* TO fixture']; }
}
class HiddenMetadataFixturePdo extends PDO {
    public function __construct(private PDO $actual) {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false {
        return $query === 'SHOW GRANTS' ? new HiddenMetadataFixtureStatement() : $this->actual->query($query);
    }
    public function inTransaction(): bool { return $this->actual->inTransaction(); }
}
class FailingReleaseUpdater extends App\Services\UpdateCenter {
    protected function performUpdate(): array {
        $this->createPreUpdateBackups();
        $this->installationMutated = true;
        file_put_contents(ROOT . '/app/before.php', '<?php /* mixed version */');
        file_put_contents(ROOT . '/app/introduced.php', '<?php /* introduced */');
        db()->query('CREATE VIEW introduced_view AS SELECT id FROM release_backup_fixture');
        db()->query('CREATE TABLE introduced_by_failed_update (id INT PRIMARY KEY) ENGINE=InnoDB');
        db()->query("UPDATE release_backup_fixture SET label='mutated', amount=99.99 WHERE id=42");
        throw new RuntimeException('Injected migration failure');
    }
    protected function writeUpdateLog(array $user, string $fromVersion, ?string $toVersion, string $result, ?string $error = null): void {}
}
try {
    $server->exec('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    $app = new FBL\Application(false);
    $installer = new App\Services\InstallService();
    $result = $installer->install([
        'db' => $settings,
        'site' => ['name' => 'Release fixture', 'url' => PATH, 'timezone' => 'Europe/Moscow'],
        'admin' => ['login' => 'fixture', 'email' => 'fixture@example.test', 'password' => 'Strong-Fixture-2026', 'password_confirmation' => 'Strong-Fixture-2026'],
        'locale' => 'ru', 'demo' => false,
    ]);
    mysqlCheck(!empty($result['ok']), 'Fresh installation failed: ' . ($result['message'] ?? 'unknown'));
    mysqlCheck(is_file(INSTALLED_LOCK), 'Installed marker created');
    // This process loaded its test encryption key before the installer generated a new one.
    // Keep all worker processes on the same disposable fixture key.
    $installedConfig = require CONFIG . '/config.local.php';
    $installedConfig['CHAT_ENCRYPTION_KEY'] = CHAT_ENCRYPTION_KEY;
    file_put_contents(CONFIG . '/config.local.php', '<?php return ' . var_export($installedConfig, true) . ';');
    clearstatcache(true, CONFIG . '/config.local.php');
    mysqlCheck((fileperms(CONFIG . '/config.local.php') & 0777) === 0600, 'Secret config is private');
    $app->db = new FBL\Database();
    mysqlCheck(count(db()->query('SHOW TABLES')->get()) === 45, 'Fresh schema has 45 core tables');
    mysqlCheck(count((new App\Services\MigrationRunner())->run()) === 0, 'Migration rerun is idempotent');
    mysqlCheck((int)db()->query("SELECT COUNT(*) FROM users WHERE role='creator'")->getColumn() === 1, 'One Creator created');
    db()->query('CREATE TABLE release_backup_fixture (id INT PRIMARY KEY, label TEXT, amount DECIMAL(10,2), payload BLOB) ENGINE=InnoDB');
    $originalLabel = "Emoji 😀; quote ' and new\nline";
    $originalBinary = "\x00\xff\x01binary";
    db()->query('INSERT INTO release_backup_fixture VALUES (?,?,?,?)', [42, $originalLabel, '12.34', $originalBinary]);
    try { (new FailingReleaseUpdater())->runUpdate(); throw new RuntimeException('Injected failure did not fail.'); }
    catch (RuntimeException $error) { mysqlCheck($error->getMessage() === 'Injected migration failure', 'Actual update reached the injected failure'); }
    mysqlCheck(is_file(STORAGE . '/update.maintenance'), 'Failed update retains maintenance');
    $descriptor = json_decode(file_get_contents(STORAGE . '/update-recovery.json'), true, 512, JSON_THROW_ON_ERROR);
    mysqlCheck(is_file($descriptor['database']) && is_file($descriptor['files']), 'Both recovery snapshots exist');
    mysqlCheck((fileperms($descriptor['database']) & 0777) === 0600 && (fileperms($descriptor['files']) & 0777) === 0600, 'Both snapshots are private');
    $process = proc_open([PHP_BINARY, '-r', 'require ' . var_export(ROOT . '/public/runtime-gate.php', true) . '; echo "BOOTED";'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $output = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]); foreach ($pipes as $pipe) fclose($pipe);
    mysqlCheck(proc_close($process) === 0 && !str_contains($output, 'BOOTED') && str_contains($output, '503') && $errors === '', 'Maintenance stops bootstrap before application code');
    $pdo = new PDO($dsn . ';dbname=' . $databaseName, $settings['username'], $settings['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $broken = $descriptor; $broken['files_sha256'] = str_repeat('0', 64);
    file_put_contents(STORAGE . '/update-recovery.json', json_encode($broken));
    try { (new App\Services\UpdateRecoveryService(ROOT))->restore($pdo); throw new RuntimeException('Corrupt snapshot accepted'); }
    catch (RuntimeException $error) { mysqlCheck(str_contains($error->getMessage(), 'checksum'), 'Checksum mismatch blocks restoration'); }
    mysqlCheck(is_file(STORAGE . '/update.maintenance'), 'Rejected recovery retains maintenance');
    file_put_contents(STORAGE . '/update-recovery.json', json_encode($descriptor));
    (new App\Services\UpdateRecoveryService(ROOT))->restore($pdo);
    mysqlCheck(!is_file(STORAGE . '/update.maintenance'), 'Successful recovery releases maintenance');
    mysqlCheck(file_get_contents(ROOT . '/app/before.php') === '<?php /* original */' && !is_file(ROOT . '/app/introduced.php'), 'Previous code restored and introduced runtime file removed');
    $row = $pdo->query('SELECT * FROM release_backup_fixture WHERE id=42')->fetch(PDO::FETCH_ASSOC);
    mysqlCheck($row['label'] === $originalLabel && $row['amount'] === '12.34' && $row['payload'] === $originalBinary, 'Text, numeric and binary data restored exactly');
    mysqlCheck($pdo->query("SHOW TABLES LIKE 'introduced_by_failed_update'")->fetchColumn() === false, 'Post-snapshot DDL removed during recovery');
    mysqlCheck((fileperms(CONFIG . '/config.local.php') & 0777) === 0600, 'Restored config remains private');
    mysqlCheck($pdo->query("SHOW FULL TABLES WHERE Table_type='VIEW'")->fetchColumn() === false, 'Introduced view removed during recovery');
    mysqlCheck((new App\Services\Maintenance\DatabaseBackupService(new HiddenMetadataFixturePdo($pdo)))->createBackup() === '', 'Limited metadata visibility blocks an unverifiable full backup');
    // Engine refuses to promise a snapshot for unsupported engines.
    $pdo->exec('CREATE TABLE unsupported_backup_engine (id INT PRIMARY KEY) ENGINE=MyISAM');
    mysqlCheck((new App\Services\Maintenance\DatabaseBackupService($pdo))->createBackup() === '', 'Nontransactional table blocks an inconsistent backup');
    $pdo->exec('DROP TABLE unsupported_backup_engine');
    $pdo->exec('CREATE VIEW unsupported_backup_view AS SELECT id FROM release_backup_fixture');
    mysqlCheck((new App\Services\Maintenance\DatabaseBackupService($pdo))->createBackup() === '', 'Unsupported objects block an incomplete backup');
    $pdo->exec('DROP VIEW unsupported_backup_view');
    require $codeRoot . '/plugins/toy-car-rental/Plugin.php';
    require $codeRoot . '/plugins/subscriptions/Plugin.php';
    foreach (glob($repository . '/plugins/toy-car-rental/migrations/*.sql') as $migration) (new App\Services\SqlFileRunner())->executePdo($pdo, file_get_contents($migration));
    $pdo->exec("INSERT INTO toy_rental_cars (id,name,number,status,created_at,updated_at) VALUES (1,'Fixture','TEST-1','available',NOW(),NOW())");
    $spawn = static function (string $action) use ($repository): array {
        $process = proc_open([PHP_BINARY, $repository . '/tests/fixtures/release_mysql_worker.php', ROOT, $action], [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Unable to start concurrency worker.');
        $ready = trim((string)fgets($pipes[1]));
        if (!preg_match('/^READY:([0-9]+)$/D', $ready, $match)) throw new RuntimeException('Concurrency worker failed to initialize.');
        return [$process, $pipes, (int)$match[1]];
    };
    $collect = static function (array $worker): array {
        [$process, $pipes] = $worker;
        fclose($pipes[0]); $text = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0 || $errors !== '') throw new RuntimeException('Concurrency worker failed.');
        return json_decode(trim($text), true, 512, JSON_THROW_ON_ERROR);
    };
    // Observe processes rather than INNODB_TRX: newly restored MySQL tables can
    // block in the optimizer's statistics phase before exposing a transaction row.
    $waitForLocks = static function (array $workers): bool {
        usleep(250000);
        $read = array_map(static fn(array $worker) => $worker[1][1], $workers);
        $write = $except = [];
        if (stream_select($read, $write, $except, 0, 0) !== 0) return false;
        foreach ($workers as $worker) if (!proc_get_status($worker[0])['running']) return false;
        return true;
    };
    $workers = [$spawn('rental-start'), $spawn('rental-start')];
    $pdo->beginTransaction(); $pdo->exec('UPDATE toy_rental_cars SET updated_at=NOW() WHERE id=1');
    foreach ($workers as $worker) { fwrite($worker[1][0], "go\n"); fflush($worker[1][0]); }
    $waited = $waitForLocks($workers); $pdo->commit();
    $answers = array_map($collect, $workers);
    mysqlCheck($waited && count(array_filter($answers, static fn(array $answer): bool => !empty($answer['result']))) === 1, 'Two starts overlap under real MySQL row locks; only one succeeds: ' . json_encode(['waited' => $waited, 'answers' => $answers]));
    mysqlCheck((int)$pdo->query("SELECT COUNT(*) FROM toy_rental_rides WHERE car_id=1 AND status='active'")->fetchColumn() === 1, 'One active rental per car');
    $rideId = (int)$pdo->query('SELECT id FROM toy_rental_rides LIMIT 1')->fetchColumn();
    $pdo->exec('CREATE TABLE fixture_completion_audit (id INT AUTO_INCREMENT PRIMARY KEY, ride_id INT) ENGINE=InnoDB');
    $pdo->exec('CREATE TRIGGER fixture_completion_update AFTER UPDATE ON toy_rental_rides FOR EACH ROW INSERT INTO fixture_completion_audit (ride_id) VALUES (NEW.id)');
    $workers = [$spawn('rental-complete'), $spawn('rental-complete')];
    $pdo->beginTransaction(); $pdo->exec('UPDATE toy_rental_cars SET updated_at=NOW() WHERE id=1');
    foreach ($workers as $worker) { fwrite($worker[1][0], "go\n"); fflush($worker[1][0]); }
    $waited = $waitForLocks($workers); $pdo->commit(); $answers = array_map($collect, $workers);
    mysqlCheck($waited && count(array_filter($answers, static fn(array $answer): bool => !empty($answer['result']))) === 2, 'Overlapping completion requests finish without a second mutation');
    mysqlCheck((int)$pdo->query('SELECT COUNT(*) FROM fixture_completion_audit')->fetchColumn() === 1, 'Rental completion writes the ride only once');
    FireballPluginToyCarRental::markRidePaid($rideId, ['payment_amount' => '100.00']);
    FireballPluginToyCarRental::markRidePaid($rideId, ['payment_amount' => '999.00']);
    mysqlCheck($pdo->query('SELECT payment_amount FROM toy_rental_rides LIMIT 1')->fetchColumn() === '100.00', 'Repeated payment cannot replace a paid amount');

    $model = new App\Models\User(); $model->enableTwoFactor(1, 'JBSWY3DPEHPK3PXP', ['FIXTURE-RECOVERY-CODE']);
    $workers = [$spawn('recovery-code'), $spawn('recovery-code')];
    $pdo->beginTransaction(); $pdo->exec('UPDATE users SET id=id WHERE id=1');
    foreach ($workers as $worker) { fwrite($worker[1][0], "go\n"); fflush($worker[1][0]); }
    $waited = $waitForLocks($workers); $pdo->commit();
    $answers = array_map($collect, $workers);
    mysqlCheck($waited && count(array_filter($answers, static fn(array $answer): bool => !empty($answer['result']))) === 1, 'Overlapping sessions can consume a recovery code only once: ' . json_encode(['waited' => $waited, 'answers' => $answers]));

    foreach ([
        'subscriptions (id INT PRIMARY KEY, ends_at DATETIME, status VARCHAR(30), auto_renew INT, archived_at DATETIME, grace_ends_at DATETIME, updated_at DATETIME, next_billing_at DATETIME)',
        'subscription_orders (id INT PRIMARY KEY, status VARCHAR(30), updated_at DATETIME)',
        'subscription_payments (id INT PRIMARY KEY, status VARCHAR(30), provider_transaction TEXT, error_message TEXT, failed_at DATETIME, updated_at DATETIME)',
    ] as $schema) $pdo->exec('CREATE TABLE ' . $schema . ' ENGINE=InnoDB');
    $pdo->exec("INSERT INTO subscriptions (id,ends_at,status,auto_renew) VALUES (99,'2099-01-01','active',1)");
    $pdo->exec("INSERT INTO subscription_orders (id,status) VALUES (501,'paid')");
    $pdo->exec("INSERT INTO subscription_payments (id,status) VALUES (501,'pending')");
    $pdo->beginTransaction(); $pdo->exec("UPDATE subscription_payments SET status='paid' WHERE id=501");
    $worker = $spawn('recurring-response'); fwrite($worker[1][0], "go\n"); fflush($worker[1][0]); $waited = $waitForLocks([$worker]); $pdo->commit(); $answer = $collect($worker);
    mysqlCheck($waited && $answer['result'] === false, 'Late recurring worker waits for callback transaction and sees the committed paid state');
    mysqlCheck($pdo->query('SELECT status FROM subscriptions WHERE id=99')->fetchColumn() === 'active', 'Concurrent callback preserves active access');

    echo "MySQL installation/recovery regressions passed: $checks checks. Working database unchanged.\n";
} finally {
    if ($created && preg_match('/^fbl_release_test_[a-f0-9]{12}$/D', $databaseName)) $server->exec('DROP DATABASE `' . $databaseName . '`');
    removeMysqlFixture($fixture);
}
