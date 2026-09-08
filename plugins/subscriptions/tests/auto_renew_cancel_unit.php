<?php

declare(strict_types=1);

namespace Fireball\Subscriptions\Payments {
    function curl_init(string $url): never { $GLOBALS['providerCalls']++; throw new \RuntimeException('Network access is forbidden in this test.'); }
    function file_get_contents(string $url, mixed ...$args): never { $GLOBALS['providerCalls']++; throw new \RuntimeException('Network access is forbidden in this test.'); }
}

namespace {
spl_autoload_register(static function (string $class): void {
    $prefix = 'Fireball\\Subscriptions\\';
    if (str_starts_with($class, $prefix)) require_once __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
});
final class FireballPluginSubscriptions {
    public static function t(string $key): string { return (require __DIR__ . '/../lang/ru.php')[$key] ?? $key; }
}
final class RenewalTestDb {
    public readonly PDO $pdo;
    public mixed $afterCommit = null;
    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'));
    }
    public function query(string $sql, array $params = []): object {
        $stmt = $this->pdo->prepare(preg_replace('/\s+FOR UPDATE\s*$/i', '', $sql));
        $stmt->execute($params);
        return new class($stmt) {
            public function __construct(private PDOStatement $stmt) {}
            public function get(): array { return $this->stmt->fetchAll(PDO::FETCH_ASSOC); }
            public function getOne(): array|false { return $this->stmt->fetch(PDO::FETCH_ASSOC); }
            public function getColumn(): mixed { return $this->stmt->fetchColumn(); }
            public function rowCount(): int { return $this->stmt->rowCount(); }
        };
    }
    public function getInsertId(): int { return (int)$this->pdo->lastInsertId(); }
    public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
    public function inTransaction(): bool { return $this->pdo->inTransaction(); }
    public function rollBack(): bool { return $this->pdo->rollBack(); }
    public function commit(): bool {
        $result = $this->pdo->commit();
        if ($this->afterCommit) { $callback = $this->afterCommit; $this->afterCommit = null; $callback(); }
        return $result;
    }
}
function db(): RenewalTestDb { static $db; return $db ??= new RenewalTestDb(); }
function plugin_setting(string $slug, string $key, mixed $default = null): mixed { return $default; }
function base_href(string $path): string { return $path; }
function notification_create(array $data): void {}
function log_error_details(string $message, array $context = [], ?Throwable $exception = null): void {}
function current_locale(): string { return 'ru'; }
$checks = 0;
$GLOBALS['providerCalls'] = 0;
function renewalCheck(mixed $expected, mixed $actual, string $message): void {
    global $checks; $checks++;
    if ($expected !== $actual) throw new RuntimeException($message . ': ' . var_export($actual, true));
}

db()->pdo->exec('CREATE TABLE subscription_plans (id INTEGER PRIMARY KEY, name TEXT, slug TEXT, description TEXT, price_minor INTEGER, currency TEXT, duration_value INTEGER, duration_unit TEXT, grace_period_days INTEGER, is_recurring INTEGER, auto_renew_enabled INTEGER, is_active INTEGER, is_public INTEGER, is_popular INTEGER, sort_order INTEGER)');
db()->pdo->exec('CREATE TABLE subscription_plan_permissions (id INTEGER PRIMARY KEY, plan_id INTEGER, permission_key TEXT, permission_value TEXT)');
db()->pdo->exec('CREATE TABLE subscription_plan_resources (id INTEGER PRIMARY KEY, plan_id INTEGER, resource_type TEXT, resource_id TEXT)');
db()->pdo->exec('CREATE TABLE subscriptions (id INTEGER PRIMARY KEY, user_id INTEGER, plan_id INTEGER, status TEXT, starts_at TEXT, ends_at TEXT, grace_ends_at TEXT, archived_at TEXT, auto_renew INTEGER, next_billing_at TEXT, cancelled_at TEXT, parent_payment_id INTEGER, source TEXT, utility_managed INTEGER DEFAULT 0, created_at TEXT, updated_at TEXT)');
db()->pdo->exec('CREATE TABLE subscription_profiles (id INTEGER PRIMARY KEY, user_id INTEGER)');
db()->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
db()->pdo->exec('CREATE TABLE subscription_orders (id INTEGER PRIMARY KEY, invoice_id INTEGER, user_id INTEGER, plan_id INTEGER, subscription_id INTEGER, amount_minor INTEGER, currency TEXT, status TEXT, plan_snapshot TEXT, customer_snapshot TEXT, consent_snapshot TEXT, expires_at TEXT, created_at TEXT, updated_at TEXT)');
db()->pdo->exec('CREATE TABLE subscription_payments (id INTEGER PRIMARY KEY, order_id INTEGER, user_id INTEGER, plan_id INTEGER, subscription_id INTEGER, provider TEXT, invoice_id INTEGER, parent_payment_id INTEGER, parent_invoice_id INTEGER, status TEXT, amount_minor INTEGER, currency TEXT, payment_type TEXT, billing_period_start TEXT, signature_verified INTEGER, provider_payload TEXT, provider_transaction TEXT, error_message TEXT, paid_at TEXT, failed_at TEXT, cleared_at TEXT, created_at TEXT, updated_at TEXT)');
db()->pdo->exec('CREATE TABLE subscription_events (id INTEGER PRIMARY KEY, subscription_id INTEGER, payment_id INTEGER, user_id INTEGER, actor_user_id INTEGER, event_key TEXT, old_status TEXT, new_status TEXT, metadata TEXT, created_at TEXT)');
db()->pdo->exec('CREATE TABLE subscription_webhook_events (id INTEGER PRIMARY KEY, provider TEXT, invoice_id INTEGER, event_hash TEXT, signature_verified INTEGER, processing_status TEXT, payload TEXT, processed_at TEXT, error_message TEXT)');
db()->query('INSERT INTO subscription_plans VALUES (9, ?, ?, ?, 15000, ?, 30, ?, 7, 1, 1, 1, 1, 0, 0)', ['Тариф', 'plan', '', 'RUB', 'days']);
db()->query('INSERT INTO subscription_plan_permissions VALUES (1, 9, ?, ?)', ['posts.view_paid', 'true']);
$future = date('Y-m-d H:i:s', time() + 10 * 86400);
$past = '2000-01-01 00:00:00';
function seedSubscription(int $id, string $status = 'active', bool $utility = false): void {
    global $future, $past;
    db()->query('INSERT INTO subscriptions (id, user_id, plan_id, status, starts_at, ends_at, grace_ends_at, auto_renew, next_billing_at, parent_payment_id, source, utility_managed, updated_at) VALUES (?, ?, 9, ?, ?, ?, ?, 1, ?, 100, ?, ?, ?)', [$id, $id, $status, $past, $status === 'grace_period' ? $past : $future, $status === 'grace_period' ? $future : null, $status === 'grace_period' ? $past : $future, 'robokassa', $utility ? 1 : 0, 'unchanged']);
}
function subscription(int $id): array { return db()->query('SELECT * FROM subscriptions WHERE id = ?', [$id])->getOne(); }
function seedPayment(int $id, int $userId, string $type = 'recurring', string $status = 'pending'): void {
    $terms = ['id' => 9, 'price_minor' => 15000, 'currency' => 'RUB', 'duration_unit' => 'days', 'duration_value' => 30, 'auto_renew_enabled' => true];
    db()->query('INSERT INTO subscription_orders (id, invoice_id, user_id, plan_id, subscription_id, amount_minor, currency, status, plan_snapshot, consent_snapshot, customer_snapshot) VALUES (?, ?, ?, 9, ?, 15000, ?, ?, ?, ?, ?)', [$id, 10000 + $id, $userId, $userId, 'RUB', $status, json_encode($terms), json_encode(['recurring' => true, 'auto_renew' => true]), '{}']);
    db()->query('INSERT INTO subscription_payments (id, order_id, user_id, plan_id, subscription_id, provider, invoice_id, status, amount_minor, currency, payment_type) VALUES (?, ?, ?, 9, ?, ?, ?, ?, 15000, ?, ?)', [$id, $id, $userId, $userId, 'robokassa', 10000 + $id, $status, 'RUB', $type]);
    if ($status !== 'paid') db()->query('INSERT INTO subscription_webhook_events (id, provider, invoice_id, event_hash, signature_verified, processing_status, payload) VALUES (?, ?, ?, ?, 1, ?, ?)', [$id, 'robokassa', 10000 + $id, 'event-' . $id, 'failed', json_encode(['InvId' => 10000 + $id, 'OutSum' => '150.00'])]);
}

use Fireball\Subscriptions\Services\SubscriptionService;
use Fireball\Subscriptions\Services\AccessService;
use Fireball\Subscriptions\Services\RecurringService;
use Fireball\Subscriptions\Services\PaymentService;
use Fireball\Subscriptions\Payments\RobokassaGateway;
use Fireball\Subscriptions\Support\RecurringCancelledException;

seedSubscription(1); seedSubscription(2); seedSubscription(3, 'grace_period'); seedSubscription(4, utility: true);
seedPayment(100, 1, 'initial', 'paid');
$financialBefore = db()->query('SELECT * FROM subscription_payments')->get();
$orderBefore = db()->query('SELECT * FROM subscription_orders')->get();
$service = new SubscriptionService();
$before = subscription(1);
$service->setAutoRenew(1, false);
$after = subscription(1);
renewalCheck('active', $after['status'], 'Opt-out retains active status');
renewalCheck($before['ends_at'], $after['ends_at'], 'Paid end date is not shortened');
renewalCheck($before['starts_at'], $after['starts_at'], 'Start date is not changed');
renewalCheck(0, $after['auto_renew'], 'Recurring flag disabled');
renewalCheck(null, $after['next_billing_at'], 'Future billing schedule cleared');
renewalCheck(true, !empty($after['cancelled_at']), 'Opt-out timestamp recorded');
renewalCheck(1, (new AccessService())->activeSubscription(1)['id'], 'Access remains valid');
renewalCheck(true, (new AccessService())->permissions(9)['posts.view_paid'], 'Plan permissions are retained');
renewalCheck(1, subscription(2)['auto_renew'], 'Another user is not affected');
renewalCheck($financialBefore, db()->query('SELECT * FROM subscription_payments')->get(), 'Payment history not touched by opt-out');
renewalCheck($orderBefore, db()->query('SELECT * FROM subscription_orders')->get(), 'Purchase snapshots not modified');
$service->setAutoRenew(1, false);
renewalCheck($after, subscription(1), 'Repeated opt-out is idempotent');
renewalCheck(1, (int)db()->query("SELECT COUNT(*) FROM subscription_events WHERE user_id = 1 AND event_key = 'subscription.auto_renew_disabled'")->getColumn(), 'Opt-out audit not duplicated');
renewalCheck(false, (new RecurringService())->initiate(1), 'Direct recurring entry point stops for opted-out subscriber');
renewalCheck(['initiated' => 0, 'failed' => 0], (function () { db()->query('UPDATE subscriptions SET next_billing_at = ? WHERE id = 3', ['2099-01-01 00:00:00']); return (new RecurringService())->processDue(); })(), 'Cron ignores opted-out subscriptions');
try { $service->setAutoRenew(999, false); throw new RuntimeException('Missing user must not succeed'); } catch (RuntimeException $error) { renewalCheck(FireballPluginSubscriptions::t('subscriptions_error_no_active_subscription'), $error->getMessage(), 'Unknown user cannot cancel someone else'); }
try { $service->setAutoRenew(4, false); throw new RuntimeException('Utility access must not be modified'); } catch (DomainException $error) { renewalCheck(FireballPluginSubscriptions::t('subscriptions_error_utility_managed'), $error->getMessage(), 'Utility subscription protected'); }
$graceBefore = subscription(3);
$service->setAutoRenew(3, false);
renewalCheck('grace_period', subscription(3)['status'], 'Grace status is preserved');
renewalCheck($graceBefore['grace_ends_at'], subscription(3)['grace_ends_at'], 'Existing grace access is not truncated');
renewalCheck(3, (new AccessService())->activeSubscription(3)['id'], 'Grace access remains valid');

$gateway = new RobokassaGateway();
foreach ([[1, 1], [2, 999]] as [$subscriptionId, $userId]) {
    try { $gateway->initiateRecurring(['subscription_id' => $subscriptionId, 'user_id' => $userId, 'plan_id' => 9], [], []); throw new RuntimeException('Provider must not be reached'); }
    catch (RecurringCancelledException) { renewalCheck(false, db()->inTransaction(), 'Adapter refuses disabled or foreign subscription before network I/O'); }
}
$service->setAutoRenew(1, true);
renewalCheck(1, subscription(1)['auto_renew'], 'Existing API can re-enable only with stored mandate');
renewalCheck($future, subscription(1)['next_billing_at'], 'Re-enabling restores billing to paid end date');
renewalCheck(null, subscription(1)['cancelled_at'], 'Re-enable clears opt-out marker');
$service->setAutoRenew(1, false);

// Simulate opt-out after cron has created an order, but before final provider dispatch.
seedSubscription(7, 'grace_period');
db()->afterCommit = static fn() => (new SubscriptionService())->setAutoRenew(7, false);
renewalCheck(false, (new RecurringService())->initiate(7), 'Cancellation between order creation and dispatch prevents charging');
renewalCheck('cancelled', db()->query("SELECT status FROM subscription_payments WHERE user_id = 7 AND payment_type = 'recurring'")->getColumn(), 'Unsent recurring attempt is cancelled, not reported as a bank failure');
renewalCheck('cancelled', db()->query('SELECT status FROM subscription_orders WHERE user_id = 7')->getColumn(), 'Unsent order is cancelled');
renewalCheck('grace_period', subscription(7)['status'], 'Cancelled dispatch does not revoke remaining access');
renewalCheck(0, $GLOBALS['providerCalls'], 'Payment provider has not been called');

// A signed result already in flight may extend the paid term, but must not re-enable renewal.
seedSubscription(8); seedPayment(108, 8);
$service->setAutoRenew(8, false);
$cancelledAt = subscription(8)['cancelled_at'];
renewalCheck('OK10108', (new PaymentService())->retryVerifiedWebhook(108), 'Late verified payment can finish normally');
renewalCheck('paid', db()->query('SELECT status FROM subscription_payments WHERE id = 108')->getColumn(), 'Late payment remains in financial history');
renewalCheck('active', subscription(8)['status'], 'Late paid period grants active access');
renewalCheck(0, subscription(8)['auto_renew'], 'Late result does not restore recurring billing');
renewalCheck(null, subscription(8)['next_billing_at'], 'Late result does not restore a charge schedule');
renewalCheck($cancelledAt, subscription(8)['cancelled_at'], 'Cancellation marker survives the late result');
renewalCheck(true, subscription(8)['ends_at'] > $future, 'Paid renewal period is still credited');
$paidEnd = subscription(8)['ends_at'];
(new PaymentService())->retryVerifiedWebhook(108);
renewalCheck($paidEnd, subscription(8)['ends_at'], 'Duplicate result does not extend twice');

db()->query('UPDATE subscriptions SET ends_at = ? WHERE id = 1', [$past]);
renewalCheck(null, (new AccessService())->activeSubscription(1), 'Access ends after the paid term');
$maintenance = file_get_contents(__DIR__ . '/../src/Services/MaintenanceService.php');
preg_match('/"(UPDATE subscriptions SET status = \'expired\'[^"\n]+)"/', $maintenance, $expirySql);
db()->query($expirySql[1], [date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
renewalCheck('expired', subscription(1)['status'], 'Maintenance expires the opted-out subscription normally');
renewalCheck('active', subscription(8)['status'], 'Maintenance preserves another valid paid period');
renewalCheck(false, db()->inTransaction(), 'No transaction leaks');
echo "Auto-renew cancellation tests passed: {$checks} checks." . PHP_EOL;
}
