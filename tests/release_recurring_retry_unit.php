<?php
declare(strict_types=1);
namespace Fireball\Subscriptions\Services {
    final class SubscriptionEligibilityService { public function assertEligible(int $id, string $reason): void {} }
    final class SubscriptionService { public function event(mixed ...$args): void { $GLOBALS['events'][] = $args; } }
}
namespace Fireball\Subscriptions\Repositories {
    final class PlanRepository { public function find(int $id): array { return ['id' => $id, 'grace_period_days' => 3]; } }
}
namespace Fireball\Subscriptions\Payments {
    final class RobokassaGateway {
        public function initiateRecurring(array $order, array $plan, array $parent): string {
            $GLOBALS['dispatches']++;
            if (\db()->inTransaction()) throw new \RuntimeException('Provider contacted inside a transaction.');
            if ($GLOBALS['paidCallback']) {
                \db()->query("UPDATE subscription_payments SET status='paid' WHERE id=501");
                \db()->query("UPDATE subscription_orders SET status='paid' WHERE id=501");
                \db()->query("UPDATE subscriptions SET status='active', ends_at='2099-01-01' WHERE id=99");
            }
            if ($GLOBALS['providerFails']) throw new \RuntimeException('Fixture provider timeout');
            return 'fixture provider accepted';
        }
    }
}
namespace {
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/plugins/subscriptions/src/Services/RecurringService.php';
final class RetryFixtureDatabase {
    public PDO $pdo;
    public function __construct() { $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
    public function query(string $sql, array $params = []): object {
        $statement = $this->pdo->prepare(preg_replace('/\s+FOR UPDATE\s*$/i', '', $sql)); $statement->execute($params);
        return new class($statement) {
            public function __construct(private PDOStatement $statement) {}
            public function getOne(): array|false { return $this->statement->fetch(PDO::FETCH_ASSOC); }
            public function getColumn(): mixed { return $this->statement->fetchColumn(); }
        };
    }
    public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
    public function commit(): bool { return $this->pdo->commit(); }
    public function rollBack(): bool { return $this->pdo->rollBack(); }
    public function inTransaction(): bool { return $this->pdo->inTransaction(); }
}
function db(): RetryFixtureDatabase { static $database; return $database ??= new RetryFixtureDatabase(); }
function log_error_details(mixed ...$args): void {}
class FireballPluginSubscriptions { public static function t(string $key): string { return $key; } }
function retryCheck(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); $GLOBALS['checks']++; }
$checks = $dispatches = 0; $events = []; $paidCallback = $providerFails = false;
$pdo = db()->pdo;
foreach ([
    'subscriptions (id INT PRIMARY KEY, user_id INT, plan_id INT, status TEXT, auto_renew INT, next_billing_at TEXT, ends_at TEXT, grace_ends_at TEXT, archived_at TEXT, updated_at TEXT)',
    'subscription_payments (id INT PRIMARY KEY, subscription_id INT, order_id INT, parent_payment_id INT, payment_type TEXT, status TEXT, failed_at TEXT, provider_transaction TEXT, error_message TEXT, updated_at TEXT)',
    'subscription_orders (id INT PRIMARY KEY, invoice_id INT, status TEXT, expires_at TEXT, updated_at TEXT)',
    'subscription_events (payment_id INT, event_key TEXT)',
] as $schema) $pdo->exec('CREATE TABLE ' . $schema);
$pdo->exec("INSERT INTO subscriptions (id,user_id,plan_id,status,auto_renew,next_billing_at,ends_at) VALUES (99,1,9,'past_due',1,'2001-01-01','2001-01-01')");
$pdo->exec("INSERT INTO subscription_orders (id,invoice_id,status) VALUES (501,501,'failed')");
$pdo->exec("INSERT INTO subscription_payments (id,subscription_id,order_id,parent_payment_id,payment_type,status,failed_at) VALUES (501,99,501,500,'recurring','failed','2001-01-01'), (500,99,500,NULL,'initial','paid',NULL)");
$service = new Fireball\Subscriptions\Services\RecurringService();
$retry = new ReflectionMethod($service, 'retryFailedAttempt');
$snapshot = ['id'=>99,'user_id'=>1,'status'=>'past_due','ends_at'=>'2001-01-01'];
retryCheck($retry->invoke($service, 501, $snapshot), 'Failed invoice can be retried');
retryCheck($dispatches === 1 && !db()->inTransaction(), 'Exactly one provider dispatch after transaction commit');
retryCheck(db()->query('SELECT provider_transaction FROM subscription_payments WHERE id=501')->getColumn() === 'fixture provider accepted', 'Provider response is saved');
retryCheck(!$retry->invoke($service, 501, $snapshot) && $dispatches === 1, 'Already pending invoice cannot be dispatched twice');
foreach ([false, true] as $fails) {
    $pdo->exec("UPDATE subscription_payments SET status='failed', provider_transaction=NULL, failed_at='2001-01-01' WHERE id=501");
    $pdo->exec("UPDATE subscription_orders SET status='failed' WHERE id=501");
    $pdo->exec("UPDATE subscriptions SET status='past_due', ends_at='2001-01-01' WHERE id=99");
    $paidCallback = true; $providerFails = $fails;
    try { $retry->invoke($service, 501, $snapshot); }
    catch (RuntimeException $error) { if (!$fails || $error->getMessage() !== 'Fixture provider timeout') throw $error; }
    retryCheck(db()->query('SELECT status FROM subscription_payments WHERE id=501')->getColumn() === 'paid', 'Callback paid state survives late success or failure');
    retryCheck(db()->query('SELECT status FROM subscriptions WHERE id=99')->getColumn() === 'active', 'Paid access survives late success or failure');
    retryCheck(!db()->inTransaction(), 'Retry leaves no open transaction');
}
$pdo->exec("UPDATE subscription_payments SET status='failed', provider_transaction=NULL, failed_at='2001-01-01' WHERE id=501");
$dispatchCount = $dispatches;
retryCheck(!$retry->invoke($service,501,$snapshot) && $dispatches===$dispatchCount, 'Paid order cannot be charged again even with a stale failed payment');
echo "Recurring retry regressions passed: $checks checks.\n";
}
