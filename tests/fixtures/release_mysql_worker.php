<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$fixture = realpath($argv[1] ?? '');
if ($fixture === false || !preg_match('/^fireball-recovery-[a-f0-9]{12}$/D', basename($fixture))) throw new RuntimeException('Invalid fixture root.');
$local = require $fixture . '/config/config.local.php';
if (!preg_match('/^fbl_release_test_[a-f0-9]{12}$/D', (string)($local['DB_SETTINGS']['database'] ?? '')) || !in_array($local['DB_SETTINGS']['host'], ['localhost', '127.0.0.1', '::1'], true)) throw new RuntimeException('Only a disposable local database is allowed.');
$repository = dirname(__DIR__, 2);
$codeRoot = is_file($fixture . '/app/Models/User.php') ? $fixture : $repository;
define('ROOT', $fixture); define('APP', $codeRoot . '/app'); define('CORE', $codeRoot . '/core'); define('HELPERS', $codeRoot . '/helpers');
ini_set('session.save_path', sys_get_temp_dir());
$_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['REQUEST_URI'] = '/';
define('DB_SETTINGS', $local['DB_SETTINGS']);
define('CHAT_ENCRYPTION_KEY', $local['CHAT_ENCRYPTION_KEY']);
require $fixture . '/config/config.php'; require $codeRoot . '/vendor/autoload.php'; require HELPERS . '/helpers.php';
FBL\PerformanceProfiler::start();
$app = new FBL\Application(false); $app->db = new FBL\Database();
if (db()->query('SELECT DATABASE()')->getColumn() !== $local['DB_SETTINGS']['database']) throw new RuntimeException('Fixture database isolation failed.');
require $codeRoot . '/plugins/toy-car-rental/Plugin.php'; require $codeRoot . '/plugins/subscriptions/Plugin.php';
if (in_array($argv[2] ?? '', ['rental-start', 'rental-complete'], true)) { FireballPluginToyCarRental::car(1); FireballPluginToyCarRental::settings(); }
echo 'READY:' . db()->query('SELECT CONNECTION_ID()')->getColumn() . "\n"; flush();
fgets(STDIN);
try {
    $action = $argv[2] ?? '';
    $result = match ($action) {
        'rental-start' => (function (): bool { FireballPluginToyCarRental::startRide(['car_id' => 1, 'payment_status' => 'unpaid', 'payment_amount' => '100.00']); return true; })(),
        'rental-complete' => (function (): bool { FireballPluginToyCarRental::completeRide(1, ['final_amount' => '100.00']); return true; })(),
        'recovery-code' => (new App\Models\User())->consumeRecoveryCode(1, 'FIXTURE-RECOVERY-CODE'),
        'recurring-response' => (new ReflectionMethod(Fireball\Subscriptions\Services\RecurringService::class, 'persistAttemptResult'))->invoke(new Fireball\Subscriptions\Services\RecurringService(), ['id' => 99, 'ends_at' => '2001-01-01'], ['grace_period_days' => 3], 501, 501, 'late response'),
        default => throw new RuntimeException('Unknown fixture action.'),
    };
    echo json_encode(['result' => $result]) . "\n";
} catch (RuntimeException $exception) {
    echo json_encode(['result' => false, 'error' => $exception->getMessage()]) . "\n";
}
