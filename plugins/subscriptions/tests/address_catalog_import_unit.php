<?php
declare(strict_types=1);

// Isolated in-memory SQL; no CMS bootstrap or working database.
require_once __DIR__ . '/../src/Support/RussianRegionCatalog.php';
require_once __DIR__ . '/../src/Services/AddressSuggestionService.php';
require_once __DIR__ . '/../src/Services/AddressCatalogImportService.php';
require_once __DIR__ . '/../../../core/Request.php';
require_once __DIR__ . '/../../../core/Router.php';

use Fireball\Subscriptions\Services\AddressSuggestionService as Directory;
use Fireball\Subscriptions\Services\AddressCatalogImportService as Import;

final class ImportTestDb
{
    public PDO $pdo;
    public array $queries = [];
    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->create(Directory::TABLE);
    }
    private function create(string $table): void
    {
        $this->pdo->exec("CREATE TABLE `{$table}` (id INTEGER PRIMARY KEY, region TEXT, city TEXT, street TEXT, house TEXT, postal_code TEXT,
            normalized_region TEXT, normalized_city TEXT, normalized_street TEXT, normalized_house TEXT, created_at TEXT,
            UNIQUE(normalized_region, normalized_city, normalized_street, normalized_house, postal_code))");
    }
    public function query(string $sql, array $params = []): object
    {
        $this->queries[] = $sql;
        if (preg_match('/^CREATE TABLE `([a-z0-9_]+)` LIKE /', $sql, $matches)) {
            $this->create($matches[1]); $sql = 'SELECT 1';
        } elseif (preg_match('/^RENAME TABLE (\w+) TO `(\w+)`, `(\w+)` TO (\w+)$/', $sql, $matches)) {
            // SQLite emulation of MySQL's one atomic RENAME TABLE statement.
            $this->pdo->beginTransaction();
            $this->pdo->exec("ALTER TABLE {$matches[1]} RENAME TO {$matches[2]}");
            $this->pdo->exec("ALTER TABLE {$matches[3]} RENAME TO {$matches[4]}");
            $this->pdo->commit(); $sql = 'SELECT 1';
        }
        $statement = $this->pdo->prepare(str_replace('INSERT IGNORE', 'INSERT OR IGNORE', $sql));
        $statement->execute($params);
        return new class($statement) {
            public function __construct(private PDOStatement $statement) {}
            public function getColumn(): mixed { return $this->statement->fetchColumn(); }
            public function get(): array { return $this->statement->fetchAll(PDO::FETCH_ASSOC); }
        };
    }
    public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
    public function commit(): bool { return $this->pdo->commit(); }
    public function rollBack(): bool { return $this->pdo->rollBack(); }
    public function inTransaction(): bool { return $this->pdo->inTransaction(); }
}
function db(): ImportTestDb { static $db; return $db ??= new ImportTestDb(); }
function session(): object
{
    static $session;
    return $session ??= new class {
        private array $values = [];
        public function set(string $key, mixed $value): void { $this->values[$key] = $value; }
        public function get(string $key, mixed $default = null): mixed { return $this->values[$key] ?? $default; }
        public function remove(string $key): void { unset($this->values[$key]); }
    };
}
function request(): FBL\Request { return $GLOBALS['test_request']; }
function log_error_details(...$args): void { throw new RuntimeException('Unexpected error log'); }
$checks = 0;
function check(mixed $actual, mixed $expected, string $message): void
{
    global $checks; $checks++;
    if ($actual !== $expected) throw new RuntimeException($message . ': ' . var_export($actual, true));
}
function rejects(callable $action, string $message): void
{
    try { $action(); } catch (InvalidArgumentException) { check(true, true, $message); return; }
    throw new RuntimeException('Expected rejection: ' . $message);
}
(new ReflectionProperty(Directory::class, 'schemaReady'))->setValue(null, true);
$service = new Import();
$header = ['region', 'city', 'street', 'house', 'postal_code'];
$row = ['Ставропольский край', 'Железноводск', 'ул. Октябрьская', '41', '357400'];
$count = static fn() => (int)db()->query('SELECT COUNT(*) FROM ' . Directory::TABLE)->getColumn();
$start = static fn(bool $replace = false) => $service->handle(['action' => 'start', 'header' => $header, 'replace' => $replace]);
$batch = static fn(array $job, int $sequence, array $rows) => $service->handle(['action' => 'batch', 'id' => $job['id'], 'sequence' => $sequence, 'rows' => $rows]);
$finish = static fn(array $job) => $service->handle(['action' => 'finish', 'id' => $job['id'], 'sequence' => $job['next']]);

rejects(fn() => $service->handle(['action' => 'start', 'header' => ['index', 'address', 'houses']]), 'Raw streets file requires joining');
$job = $start();
check(isset($job['table']), false, 'Internal SQL table never sent to browser');
check(strlen($job['id']), 32, 'Opaque import identifier');
$job = $batch($job, 0, [$row]);
check($count(), 1, 'First portion imported');
check($job['processed'], 1, 'Processed count');
check($batch($job, 0, [$row]), $job, 'Lost response retry is idempotent');
check($count(), 1, 'Retry does not duplicate addresses');
rejects(fn() => $batch($job, 0, [array_replace($row, [3 => '42'])]), 'Same sequence with different content rejected');
rejects(fn() => $batch($job, 9, [$row]), 'Out-of-order portion rejected');
rejects(fn() => $batch(['id' => str_repeat('0', 32)], 1, [$row]), 'Other import/session cannot be modified');
rejects(fn() => $batch($job, 1, array_fill(0, 1501, $row)), 'Row count bounded');
$job = $batch($job, 1, [$row, ['', '', '', '', ''], array_replace($row, [2 => str_repeat('Я', 256)])]);
check($job['processed'], 2, 'Duplicates are processed safely');
check($job['skipped'], 2, 'Empty and oversized address fields reported, not truncated');
check($count(), 1, 'Duplicate skipped by unique key');
$done = $finish($job);
check($done['completed'], true, 'Append finished');
check($finish($job), $done, 'Finish retry is safe');
rejects(fn() => $batch($done, 2, [$row]), 'No writes after finish');

$replacement = $start(true);
check($count(), 1, 'Starting replacement preserves current directory');
$replacement = $batch($replacement, 0, [array_replace($row, [3 => '42'])]);
check(db()->query('SELECT house FROM ' . Directory::TABLE)->getColumn(), '41', 'Incomplete replacement is not visible');
$finish($replacement);
check(db()->query('SELECT house FROM ' . Directory::TABLE)->getColumn(), '42', 'Complete replacement published');
check(count(array_filter(db()->queries, static fn($sql) => str_starts_with($sql, 'RENAME TABLE '))), 1, 'Publish uses one atomic rename');
check(count(array_filter(db()->queries, static fn($sql) => str_starts_with($sql, 'DELETE FROM '))), 0, 'No early deletion');
$cancel = $start(true);
$cancel = $batch($cancel, 0, [array_replace($row, [3 => '43'])]);
$service->handle(['action' => 'cancel', 'id' => $cancel['id']]);
check(db()->query('SELECT house FROM ' . Directory::TABLE)->getColumn(), '42', 'Cancel preserves old directory');
$empty = $start(true);
$empty = $batch($empty, 0, [['', '', '', '', '']]);
rejects(fn() => $finish($empty), 'Empty replacement cannot wipe directory');
check($count(), 1, 'Empty replacement retained original row');
$stale = $start(true);
$current = $start();
rejects(fn() => $batch($stale, 0, [$row]), 'Stale tab cannot use another active import');
check((int)db()->query("SELECT COUNT(*) FROM sqlite_master WHERE name LIKE 'subscription_address_import_%'")->getColumn(), 0, 'Starting next import cleans up previous staging table');
$records = [];
for ($i = 0; $i < 301; $i++) $records[] = array_replace($row, [3 => (string)(100 + $i)]);
$records[300][2] = "\xff";
rejects(fn() => $batch($current, 0, $records), 'Invalid encoding rejects entire portion');
$records[300][2] = "Улица\0";
rejects(fn() => $batch($current, 0, $records), 'Invalid last row rolls back prior INSERT batches');
check($count(), 1, 'Previously inserted rows in failed portion rolled back');
$current = $batch($current, 0, [array_replace($row, [0 => 'Stavropol Krai'])]);
check(db()->query("SELECT region FROM " . Directory::TABLE . " WHERE house='41'")->getColumn(), $row[0], 'Region alias canonicalized');
rejects(fn() => (new Directory())->prepareImportTable('users'), 'Arbitrary SQL table rejected');
rejects(fn() => (new Directory())->discardImportTable('subscription_address_import_bad;DROP TABLE users'), 'Identifier injection rejected');
check(Import::requestLimit() <= 262144, true, 'POST size bounded independently of total file');

// Exercise the real Router's CSRF method with an empty POST body (oversized uploads case).
$_GET = $_POST = $_FILES = [];
$_SERVER['REQUEST_METHOD'] = 'POST';
session()->set('needCSRFToken', 'test-secret');
$GLOBALS['test_request'] = new FBL\Request('/admin/subscriptions/address-catalog/batch');
$router = (new ReflectionClass(FBL\Router::class))->newInstanceWithoutConstructor();
check($router->checkCSRFToken(), false, 'Missing CSRF token rejected');
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'incorrect';
check($router->checkCSRFToken(), false, 'Wrong CSRF token rejected');
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'test-secret';
check($router->checkCSRFToken(), true, 'Header token accepted by existing CMS security');
$routes = file_get_contents(__DIR__ . '/../routes.php');
check((bool)preg_match("~address-catalog/batch'.*middleware\(\['auth', 'admin'\]\)~", $routes), true, 'Batch route requires admin authentication');
echo "Address catalog import tests passed: {$checks} checks.\n";
