<?php
declare(strict_types=1);

// Real SQL in a private in-memory database; no live rental data or notifications.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('ROOT', dirname(__DIR__));
define('PATH', 'https://cms.example.test');
define('FIREBALL_CLI', true);
require ROOT . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';
require ROOT . '/plugins/toy-car-rental/Plugin.php';

final class RentalHistoryDatabase extends FBL\Database
{
    public bool $failRecalculation = false;
    public function __construct()
    {
        $this->connection = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    public function query(string $query, array $params = []): static
    {
        if ($this->failRecalculation && str_contains($query, 'SET duration_minutes = ?')) {
            $this->failRecalculation = false;
            throw new RuntimeException('Isolated write failure');
        }
        // SQLite executes the same predicates and transactions, without MySQL lock syntax.
        return parent::query(str_replace(' FOR UPDATE', '', $query), $params);
    }
}
final class RentalHistoryResponseStop extends RuntimeException
{
    public function __construct(public string $target) { parent::__construct('Response stopped'); }
}
final class RentalHistoryResponse extends FBL\Response
{
    public function redirect($url = '') { throw new RentalHistoryResponseStop((string)$url); }
}

$app = (new ReflectionClass(FBL\Application::class))->newInstanceWithoutConstructor();
FBL\Application::$app = $app;
$app->db = $database = new RentalHistoryDatabase();
$app->session = (new ReflectionClass(FBL\Session::class))->newInstanceWithoutConstructor();
$app->plugins = new FBL\Plugins\PluginManager();
$app->hooks = new FBL\Plugins\HookManager();
$app->response = new RentalHistoryResponse();
$app->set('lang', LANGS['ru']);
FBL\Language::$lang_data = require ROOT . '/plugins/toy-car-rental/lang/ru.php';
(new ReflectionProperty(App\Models\SiteSetting::class, 'cache'))->setValue(null, []);
$cache = [];
foreach (FireballPluginToyCarRental::defaultSettings() as $key => $value) $cache[$key] = ['setting_value' => json_encode($value)];
(new ReflectionProperty(FBL\Plugins\PluginManager::class, 'settingsCache'))->setValue($app->plugins, ['toy-car-rental' => $cache]);
$_GET = $_POST = $_FILES = [];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SESSION = ['needCSRFToken' => 'rental-history-test'];
$app->request = new FBL\Request('/admin/toy-rental/rides');
$app->router = new FBL\Router($app->request, $app->response);

db()->query('CREATE TABLE toy_rental_cars (id INTEGER PRIMARY KEY, name TEXT, number TEXT, color TEXT, status TEXT, price_per_minute NUMERIC, deleted_at TEXT, updated_at TEXT)');
db()->query('CREATE TABLE toy_rental_rides (
    id INTEGER PRIMARY KEY, car_id INTEGER, billing_type TEXT, price_per_minute NUMERIC,
    estimated_minutes INTEGER, customer_name TEXT, customer_phone TEXT,
    started_at TEXT, planned_end_at TEXT, ended_at TEXT, duration_minutes INTEGER,
    payment_amount NUMERIC, final_amount NUMERIC, payment_method TEXT,
    payment_status TEXT, status TEXT, notes TEXT, created_at TEXT, updated_at TEXT
)');
$checks = 0;
function rentalHistoryCheck(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function rentalHistoryReject(callable $action, string $message): void
{
    try { $action(); } catch (RuntimeException $exception) { rentalHistoryCheck(true, $message); return; }
    throw new LogicException($message);
}
function rentalHistoryRide(int $id, array $overrides = []): void
{
    $now = time();
    $ride = array_replace([
        'id' => $id, 'car_id' => $id, 'billing_type' => 'metered', 'price_per_minute' => 20,
        'estimated_minutes' => 10, 'customer_name' => '', 'customer_phone' => '',
        'started_at' => date('Y-m-d H:i:s', $now - 552), 'planned_end_at' => date('Y-m-d H:i:s', $now + 60),
        'ended_at' => date('Y-m-d H:i:s', $now), 'duration_minutes' => 9,
        'payment_amount' => 15, 'final_amount' => 15, 'payment_method' => 'transfer',
        'payment_status' => 'paid', 'status' => 'completed', 'notes' => '',
        'created_at' => date('Y-m-d H:i:s', $now), 'updated_at' => date('Y-m-d H:i:s', $now),
    ], $overrides);
    db()->query('INSERT INTO toy_rental_cars (id, name, number, color, status, price_per_minute) VALUES (?, ?, ?, ?, ?, ?)',
        [$id, 'Test car ' . $id, (string)$id, '', in_array($ride['status'], ['active', 'overdue'], true) ? 'rented' : 'available', 999]);
    db()->query('INSERT INTO toy_rental_rides (' . implode(', ', array_keys($ride)) . ') VALUES (' . implode(', ', array_fill(0, count($ride), '?')) . ')', array_values($ride));
}
function rentalHistoryRow(int $id): array|false
{
    return db()->query('SELECT * FROM toy_rental_rides WHERE id = ?', [$id])->getOne();
}

foreach ([0 => 1, 1 => 1, 59 => 1, 60 => 1, 61 => 2, 540 => 9, 541 => 10, 552 => 10, 599 => 10, 600 => 10, 601 => 11] as $seconds => $minutes) {
    rentalHistoryCheck(FireballPluginToyCarRental::billableMinutes($seconds) === $minutes, 'Started-minute billing boundary ' . $seconds);
}
rentalHistoryRide(1);
FireballPluginToyCarRental::recalculateRide(1);
$ride = rentalHistoryRow(1);
rentalHistoryCheck((int)$ride['duration_minutes'] === 10 && (float)$ride['final_amount'] === 200.0 && (float)$ride['payment_amount'] === 200.0, '9:12 must become 10 billable minutes and replace a manual amount.');
rentalHistoryCheck($ride['payment_status'] === 'paid' && $ride['payment_method'] === 'transfer', 'Recalculation must preserve payment status and method.');
rentalHistoryCheck((float)FireballPluginToyCarRental::todayStats()['revenue_total'] === 200.0, 'Statistics must use the recalculated paid amount.');
rentalHistoryRide(2, ['payment_status' => 'unpaid']);
FireballPluginToyCarRental::recalculateRide(2);
rentalHistoryCheck(rentalHistoryRow(2)['payment_status'] === 'unpaid' && (float)rentalHistoryRow(2)['payment_amount'] === 200.0, 'Recalculate debt without falsely marking it paid.');
rentalHistoryCheck((float)FireballPluginToyCarRental::todayStats()['revenue_total'] === 200.0, 'Unpaid recalculated rides must not count as paid revenue.');
rentalHistoryRide(3, ['billing_type' => 'fixed', 'estimated_minutes' => 5]);
FireballPluginToyCarRental::recalculateRide(3);
rentalHistoryCheck((float)rentalHistoryRow(3)['final_amount'] === 100.0 && (int)rentalHistoryRow(3)['duration_minutes'] === 10, 'Fixed rides use purchased duration and the saved rate, while retaining actual rounded time.');
rentalHistoryRide(4, ['billing_type' => 'fixed', 'estimated_minutes' => null, 'planned_end_at' => date('Y-m-d H:i:s', time() - 252)]);
FireballPluginToyCarRental::recalculateRide(4);
rentalHistoryCheck((float)rentalHistoryRow(4)['final_amount'] === 100.0, 'Legacy fixed rides derive purchased duration from the saved plan.');
rentalHistoryRide(5, ['status' => 'active', 'ended_at' => null]);
rentalHistoryRide(6, ['status' => 'overdue', 'ended_at' => null, 'billing_type' => 'metered']);
rentalHistoryRide(7, ['status' => 'cancelled']);
foreach ([5, 6, 7, 999] as $id) {
    rentalHistoryReject(static fn() => FireballPluginToyCarRental::recalculateRide($id), 'Only completed rides may be recalculated.');
    rentalHistoryCheck(!db()->inTransaction(), 'Rejected recalculation must release its transaction.');
}
foreach ([5, 6] as $id) {
    rentalHistoryReject(static fn() => FireballPluginToyCarRental::deleteRideFromHistory($id), 'Active and overdue rides cannot be removed from history.');
    rentalHistoryCheck(rentalHistoryRow($id) !== false, 'A rejected history deletion must retain the running ride.');
}
rentalHistoryRide(8, ['ended_at' => null]);
rentalHistoryReject(static fn() => FireballPluginToyCarRental::recalculateRide(8), 'Invalid end timestamps must not alter a stored charge.');
rentalHistoryCheck((float)rentalHistoryRow(8)['payment_amount'] === 15.0, 'Invalid recalculation leaves the recorded amount intact.');
$before = rentalHistoryRow(2);
$database->failRecalculation = true;
rentalHistoryReject(static fn() => FireballPluginToyCarRental::recalculateRide(2), 'Write failures must be propagated.');
rentalHistoryCheck(rentalHistoryRow(2) === $before && !db()->inTransaction(), 'Recalculation write failures must roll back.');
FireballPluginToyCarRental::deleteRideFromHistory(1);
rentalHistoryCheck(rentalHistoryRow(1) === false, 'Remove an individual paid historical ride.');
rentalHistoryCheck((float)FireballPluginToyCarRental::todayStats()['revenue_total'] === 260.0, 'Deletion must remove that ride from revenue immediately.');
FireballPluginToyCarRental::deleteRideFromHistory(7);
rentalHistoryCheck(rentalHistoryRow(7) === false, 'Cancelled historical rides can be deleted.');
rentalHistoryReject(static fn() => FireballPluginToyCarRental::deleteRideFromHistory(999), 'Missing historical IDs must fail without modifying other rides.');

$router = $app->router;
require ROOT . '/plugins/toy-car-rental/admin.php';
$paths = ['/admin/toy-rental/rides/delete', '/admin/toy-rental/rides/recalculate', '/admin/toy-rental/rides/clear-history'];
$actions = array_values(array_filter($router->getRoutes(), static fn(array $route): bool => in_array($route['path'], $paths, true)));
rentalHistoryCheck(count($actions) === 3, 'Register all three history operations.');
foreach ($actions as $route) {
    rentalHistoryCheck($route['method'] === ['POST'] && $route['middleware'] === ['auth', 'admin'] && $route['needCSRFToken'], 'Every history mutation must be admin-only POST with CSRF protection.');
    foreach (['user', 'moderator', 'rental-operator'] as $role) {
        $_SESSION['user'] = ['id' => 1, 'role' => $role];
        try { (new FBL\Middleware\Admin())->handle(); throw new LogicException('Operator gained history access.'); }
        catch (RentalHistoryResponseStop $stop) { rentalHistoryCheck($stop->target === base_href('/profile'), 'Operators must be rejected by the native admin guard.'); }
    }
}
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['needCSRFToken' => 'wrong'];
$app->request = new FBL\Request($paths[0]);
rentalHistoryCheck(!$router->checkCSRFToken(), 'Reject forged history action tokens.');
$_POST = ['needCSRFToken' => 'rental-history-test'];
$app->request = new FBL\Request($paths[0]);
rentalHistoryCheck($router->checkCSRFToken(), 'Accept the current native CSRF token.');

rentalHistoryRide(9, ['started_at' => date('Y-m-d H:i:s', strtotime('-1 day')), 'ended_at' => date('Y-m-d H:i:s', strtotime('-1 day +552 seconds'))]);
$runningCars = db()->query("SELECT id, status FROM toy_rental_cars WHERE status = 'rented' ORDER BY id")->get();
$count = FireballPluginToyCarRental::clearRideHistory();
rentalHistoryCheck($count === 5, 'Clear all closed history, including previous dates and unpaid records.');
rentalHistoryCheck(array_map('intval', array_column(db()->query('SELECT id FROM toy_rental_rides ORDER BY id')->get(), 'id')) === [5, 6], 'Full history clear must retain every active or overdue ride.');
rentalHistoryCheck(db()->query("SELECT id, status FROM toy_rental_cars WHERE status = 'rented' ORDER BY id")->get() === $runningCars, 'Clearing history must not change running car status.');
rentalHistoryCheck((float)FireballPluginToyCarRental::todayStats()['revenue_total'] === 30.0, 'Statistics must contain only the remaining rides after clearing.');
rentalHistoryCheck(FireballPluginToyCarRental::clearRideHistory() === 0, 'Clearing an empty closed history is idempotent.');

$endedAt = time();
rentalHistoryRide(10, ['status' => 'active', 'ended_at' => null, 'started_at' => date('Y-m-d H:i:s', $endedAt - 552), 'payment_amount' => 0, 'final_amount' => null]);
FireballPluginToyCarRental::completeRide(10, ['payment_status' => 'paid'], $endedAt);
$ride = rentalHistoryRow(10);
rentalHistoryCheck($ride['status'] === 'completed' && (int)$ride['duration_minutes'] === 10 && (float)$ride['payment_amount'] === 200.0, 'Normal ride completion must bill 9:12 as 10 minutes with the saved rate.');
rentalHistoryCheck(db()->query('SELECT status FROM toy_rental_cars WHERE id = 10')->getOne()['status'] === 'available', 'Completion must free the car after saving the rounded charge.');
rentalHistoryRide(11, ['billing_type' => 'fixed', 'status' => 'active', 'ended_at' => null, 'estimated_minutes' => 5, 'payment_amount' => 100]);
FireballPluginToyCarRental::completeRide(11, [], $endedAt);
rentalHistoryCheck((float)rentalHistoryRow(11)['payment_amount'] === 100.0, 'Fixed completion must retain the purchased fixed charge.');
rentalHistoryRide(12, ['status' => 'active', 'ended_at' => null, 'started_at' => date('Y-m-d H:i:s', $endedAt - 121 * 60)]);
FireballPluginToyCarRental::completeRide(12, [], $endedAt);
rentalHistoryCheck((int)rentalHistoryRow(12)['duration_minutes'] === 120 && (float)rentalHistoryRow(12)['payment_amount'] === 2400.0, 'Started-minute rounding must not exceed the maximum ride duration.');
echo "$checks isolated rental history and billing checks passed; no live data or push.\n";
