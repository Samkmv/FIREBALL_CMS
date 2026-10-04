<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/plugins/subscriptions/Plugin.php';
final class ReleaseFixtureDb {
    public PDO $pdo;
    public function __construct() { $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
    public function query(string $sql, array $params = []): object {
        $statement = $this->pdo->prepare(preg_replace('/\s+FOR UPDATE\s*$/i', '', $sql));
        $statement->execute($params);
        return new class($statement) {
            public function __construct(private PDOStatement $statement) {}
            public function get(): array { return $this->statement->fetchAll(PDO::FETCH_ASSOC); }
            public function getOne(): array|false { return $this->statement->fetch(PDO::FETCH_ASSOC); }
            public function getColumn(): mixed { return $this->statement->fetchColumn(); }
            public function rowCount(): int { return $this->statement->rowCount(); }
        };
    }
    public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
    public function commit(): bool { return $this->pdo->commit(); }
    public function rollBack(): bool { return $this->pdo->rollBack(); }
    public function inTransaction(): bool { return $this->pdo->inTransaction(); }
}
function db(): ReleaseFixtureDb { static $db; return $db ??= new ReleaseFixtureDb(); }
function apply_filters(string $hook, mixed $value, mixed ...$args): mixed { return $hook === 'search_document_access' ? FireballPluginSubscriptions::filterSearchDocumentAccess($value, ...$args) : $value; }
function current_user(): array { return $GLOBALS['viewer']; }
function current_locale(): string { return 'en'; }
function return_translation(string $key): string { return $key; }
function base_href(string $path): string { return $path; }
function base_url(string $path): string { return 'https://fixture.test' . $path; }
$checks = 0;
function financialCheck(bool $value, string $message): void { $GLOBALS['checks']++; if (!$value) throw new RuntimeException($message); }
$pdo = db()->pdo;
foreach ([
    'users (id INTEGER PRIMARY KEY, role TEXT)',
    'subscription_plans (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, grace_period_days INTEGER)',
    'subscriptions (id INTEGER PRIMARY KEY, user_id INTEGER, plan_id INTEGER, status TEXT, starts_at TEXT, ends_at TEXT, grace_ends_at TEXT, archived_at TEXT, auto_renew INTEGER, next_billing_at TEXT, updated_at TEXT)',
    'subscription_plan_permissions (plan_id INTEGER, permission_key TEXT, permission_value TEXT)',
    'subscription_content_rules (id INTEGER PRIMARY KEY, content_type TEXT, content_id TEXT, access_mode TEXT, show_title INTEGER, show_excerpt INTEGER, show_image INTEGER, required_permission TEXT)',
    'subscription_content_plans (content_rule_id INTEGER, plan_id INTEGER)',
    'subscription_orders (id INTEGER PRIMARY KEY, status TEXT, updated_at TEXT)',
    'subscription_payments (id INTEGER PRIMARY KEY, status TEXT, provider_transaction TEXT, error_message TEXT, failed_at TEXT, updated_at TEXT)',
    'search_index (id INTEGER PRIMARY KEY, provider TEXT, entity_type TEXT, entity_id TEXT, module TEXT, locale TEXT, title TEXT, subtitle TEXT, search_text TEXT, normalized_title TEXT, normalized_text TEXT, keywords TEXT, url TEXT, icon TEXT, priority INTEGER, status TEXT, published_at TEXT, metadata TEXT)',
    'search_index_tokens (search_index_id INTEGER, token TEXT)',
] as $schema) $pdo->exec('CREATE TABLE ' . $schema);
$pdo->exec("INSERT INTO users VALUES (1,'user'), (2,'user'), (3,'admin')");
$pdo->exec("INSERT INTO subscription_plans VALUES (9,'Fixture plan','fixture',3)");
$pdo->exec("INSERT INTO subscriptions (id,user_id,plan_id,status,starts_at,ends_at,auto_renew) VALUES (2,2,9,'active','2000-01-01','2099-01-01',1), (99,1,9,'active','2000-01-01','2099-01-01',1)");
$pdo->exec("INSERT INTO subscription_plan_permissions VALUES (9,'posts.view_paid','true')");
$pdo->exec("INSERT INTO subscription_content_rules VALUES (1,'post','99','subscribers',0,0,0,'posts.view_paid')");
$pdo->exec("INSERT INTO search_index VALUES (1,'posts','post','99','posts','en','Classified title','','classified private body','classified title','classified private body','[]','/posts/secret','newspaper',0,'published',NULL,'{\"image\":\"private.png\"}')");
$pdo->exec("INSERT INTO search_index_tokens VALUES (1,'classified')");
$registry = new App\Search\SearchRegistry();
$registry->registerProvider('posts', new App\Search\Providers\PostSearchProvider());
$engine = new App\Search\SearchEngine($registry, new App\Search\SearchIndexer($registry), new App\Search\SearchConfig(['search_partial_matching' => '0']));
$GLOBALS['viewer'] = [];
financialCheck($engine->search('classified')['total'] === 0, 'Guest cannot discover restricted indexed title, body or metadata');
$GLOBALS['viewer'] = ['id' => 1];
$pdo->exec("UPDATE subscriptions SET ends_at='2001-01-01' WHERE id=99");
financialCheck($engine->search('classified')['total'] === 0, 'Expired/non-subscriber has no restricted search result');
$GLOBALS['viewer'] = ['id' => 2];
financialCheck($engine->search('classified')['total'] === 1, 'Authorized subscriber can search the publication');
$GLOBALS['viewer'] = ['id' => 3];
financialCheck($engine->search('classified')['total'] === 1, 'Administrator retains access');
$pdo->exec("UPDATE subscriptions SET ends_at='2001-01-01' WHERE id=2");
(new ReflectionProperty(FireballPluginSubscriptions::class, 'accessService'))->setValue(null, null);
$GLOBALS['viewer'] = ['id' => 2];
financialCheck($engine->search('classified')['total'] === 0, 'Expired access is re-evaluated on the next request');
$pdo->exec("UPDATE subscription_content_rules SET access_mode='public'");
$GLOBALS['viewer'] = [];
financialCheck($engine->search('classified')['total'] === 1, 'Public content remains searchable');

$service = new Fireball\Subscriptions\Services\RecurringService();
$persist = new ReflectionMethod($service, 'persistAttemptResult');
$subscription = ['id' => 99, 'ends_at' => '2001-01-01'];
$plan = ['grace_period_days' => 3];
$pdo->exec("UPDATE subscriptions SET status='active', ends_at='2099-01-01' WHERE id=99");
$pdo->exec("INSERT INTO subscription_orders VALUES (501,'paid',NULL), (502,'pending',NULL)");
$pdo->exec("INSERT INTO subscription_payments VALUES (501,'paid',NULL,NULL,NULL,NULL), (502,'pending',NULL,NULL,NULL,NULL)");
financialCheck(!$persist->invoke($service, $subscription, $plan, 501, 501, 'late response'), 'Late success cannot overwrite a paid callback');
financialCheck(!$persist->invoke($service, $subscription, $plan, 501, 501, null, new RuntimeException('timeout')), 'Late failure cannot overwrite a paid callback');
financialCheck(db()->query('SELECT status FROM subscriptions WHERE id=99')->getColumn() === 'active', 'Paid subscription stays active');
financialCheck(db()->query('SELECT status FROM subscription_payments WHERE id=501')->getColumn() === 'paid', 'Paid payment stays paid');
financialCheck($persist->invoke($service, $subscription, $plan, 502, 502, 'pending response'), 'Pending attempt response is recorded');
financialCheck(db()->query('SELECT status FROM subscriptions WHERE id=99')->getColumn() === 'active', 'Old invoice cannot downgrade a newer paid period');
$pdo->exec("UPDATE subscription_payments SET status='cancelled' WHERE id=502");
financialCheck(!$persist->invoke($service, $subscription, $plan, 502, 502, 'late response'), 'Cancelled payment cannot be re-opened');
$pdo->exec("UPDATE subscription_payments SET status='pending' WHERE id=502");
$pdo->exec("UPDATE subscriptions SET ends_at='2001-01-01', auto_renew=0 WHERE id=99");
financialCheck($persist->invoke($service, $subscription, $plan, 502, 502, null, new RuntimeException('failed')), 'Unpaid failure is recorded');
financialCheck(db()->query('SELECT status FROM subscriptions WHERE id=99')->getColumn() === 'active', 'Opted-out subscription is not downgraded');
financialCheck(db()->query('SELECT status FROM subscription_orders WHERE id=502')->getColumn() === 'failed', 'Unpaid order marked failed');
echo "Payment/search regressions passed: $checks checks.\n";
