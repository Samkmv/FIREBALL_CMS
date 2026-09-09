<?php

declare(strict_types=1);

require __DIR__ . '/profile_checkout_unit.php';
require_once __DIR__ . '/../src/Services/PaymentService.php';
require_once __DIR__ . '/../src/Services/SubscriptionService.php';

$checks = 0;
$failures = [];
FireballPluginSubscriptions::$locale = 'ru';
db()->pdo->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'));
db()->pdo->exec('CREATE TABLE subscriptions (id INTEGER PRIMARY KEY, user_id INTEGER, status TEXT, starts_at TEXT, ends_at TEXT, grace_ends_at TEXT, archived_at TEXT, auto_renew INTEGER DEFAULT 1, next_billing_at TEXT, updated_at TEXT)');
db()->pdo->exec('CREATE TABLE subscription_events (id INTEGER PRIMARY KEY, subscription_id INTEGER, payment_id INTEGER, user_id INTEGER, actor_user_id INTEGER, event_key TEXT, old_status TEXT, new_status TEXT, metadata TEXT, created_at TEXT)');
db()->pdo->exec('CREATE TABLE subscription_payments (id INTEGER PRIMARY KEY, user_id INTEGER, status TEXT, amount_minor INTEGER, cleared_at TEXT, updated_at TEXT)');
db()->pdo->exec('CREATE TABLE subscription_orders (id INTEGER PRIMARY KEY, user_id INTEGER, subscription_id INTEGER, status TEXT)');
db()->pdo->exec('CREATE TABLE subscription_webhook_events (id INTEGER PRIMARY KEY, signature_verified INTEGER, payload TEXT)');

foreach ([1 => 'disabled', 2 => 'disabled', 3 => 'active', 4 => 'expired', 5 => 'cancelled', 6 => 'grace_period', 7 => 'active'] as $id => $status) {
    db()->query('INSERT INTO subscriptions (id, user_id, status, starts_at, ends_at, grace_ends_at, next_billing_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [
        $id, $id === 3 ? 2 : $id, $status, '2000-01-01 00:00:00', $id === 7 ? null : '2099-01-01 00:00:00', $id === 6 ? '2099-01-02 00:00:00' : null, '2099-01-01 00:00:00',
    ]);
}
foreach (['paid', 'failed', 'pending', 'created', 'cancelled'] as $index => $status) {
    db()->query('INSERT INTO subscription_payments VALUES (?, ?, ?, ?, ?, ?)', [$index + 1, 1, $status, 15000, null, 'unchanged']);
}
db()->query('INSERT INTO subscription_orders VALUES (1, 1, 1, ?)', ['paid']);
db()->query('INSERT INTO subscription_webhook_events VALUES (1, 1, ?)', ['stored-confirmation']);
$financialRows = db()->query('SELECT * FROM subscription_payments ORDER BY id')->get();
$orders = db()->query('SELECT * FROM subscription_orders')->get();
$webhooks = db()->query('SELECT * FROM subscription_webhook_events')->get();

// Execute the actual list eligibility expression, so the button agrees with the server guard.
$controller = file_get_contents(__DIR__ . '/../src/Controllers/AdminController.php');
preg_match('/NOT EXISTS \([\s\S]+?\) AS can_archive_subscriber/', $controller, $match);
if (empty($match[0])) throw new RuntimeException('Subscriber eligibility SQL not found');
$eligibility = db()->query('SELECT s.id, ' . $match[0] . ' FROM subscriptions s ORDER BY s.id')->get();
foreach ($eligibility as $row) {
    check(in_array((int)$row['id'], [1, 4], true), (bool)$row['can_archive_subscriber'], 'List eligibility for subscription ' . $row['id']);
}

$subscriptions = new Fireball\Subscriptions\Services\SubscriptionService();
foreach ([2, 5, 6, 7] as $userId) {
    $before = db()->query('SELECT * FROM subscriptions WHERE user_id = ?', [$userId])->get();
    try {
        $subscriptions->archiveInactiveSubscriber($userId, 99);
        check(true, false, 'Active access must block deletion for user ' . $userId);
    } catch (DomainException $exception) {
        check(FireballPluginSubscriptions::t('subscriptions_subscriber_delete_active_error'), $exception->getMessage(), 'Server rechecks active access');
    }
    check($before, db()->query('SELECT * FROM subscriptions WHERE user_id = ?', [$userId])->get(), 'Blocked deletion changes no subscriptions');
    check(false, db()->inTransaction(), 'Blocked deletion rolls back');
}
check(1, $subscriptions->archiveInactiveSubscriber(1, 99), 'Disabled subscriber can be deleted even with a future end date');
$archived = db()->query('SELECT * FROM subscriptions WHERE id = 1')->getOne();
check(true, $archived['archived_at'] !== null, 'Subscriber is archived rather than physically deleted');
check(0, $archived['auto_renew'], 'Archived subscription cannot auto-renew');
check(null, $archived['next_billing_at'], 'Archived subscription is not scheduled for billing');
check(1, $subscriptions->archiveInactiveSubscriber(4, 99), 'Expired subscriber can be deleted');
check(2, (int)db()->query("SELECT COUNT(*) FROM subscription_events WHERE event_key = 'subscriber.archived_by_admin' AND actor_user_id = 99")->getColumn(), 'Both removals are audited');
check($financialRows, db()->query('SELECT * FROM subscription_payments ORDER BY id')->get(), 'Subscriber deletion preserves financial records unchanged');
check($orders, db()->query('SELECT * FROM subscription_orders')->get(), 'Subscriber deletion preserves linked orders');
check($webhooks, db()->query('SELECT * FROM subscription_webhook_events')->get(), 'Subscriber deletion preserves payment confirmations');

$payments = new Fireball\Subscriptions\Services\PaymentService();
check(['paid_total_minor' => 15000, 'failed' => 1], $payments->visibleHistoryStats(), 'Overview counts visible paid and failed records');
$activeBefore = db()->query('SELECT * FROM subscriptions')->get();
check(5, $payments->clearHistory(), 'History clear includes failures and all other statuses');
check(['paid_total_minor' => 0, 'failed' => 0], $payments->visibleHistoryStats(), 'Overview counters reset after clearing');
check(0, (int)db()->query('SELECT COUNT(*) FROM subscription_payments WHERE cleared_at IS NULL')->getColumn(), 'Payment list is empty after clearing');
check(5, (int)db()->query('SELECT COUNT(*) FROM subscription_payments')->getColumn(), 'All financial rows are retained');
check($activeBefore, db()->query('SELECT * FROM subscriptions')->get(), 'History clear does not change active or archived subscriptions');
check($orders, db()->query('SELECT * FROM subscription_orders')->get(), 'History clear retains orders');
check($webhooks, db()->query('SELECT * FROM subscription_webhook_events')->get(), 'History clear retains callbacks');
check(0, $payments->clearHistory(), 'Repeated clearing is idempotent');
db()->query('INSERT INTO subscription_payments VALUES (6, 1, ?, 50000, NULL, ?)', ['failed', 'new']);
check(['paid_total_minor' => 0, 'failed' => 1], $payments->visibleHistoryStats(), 'New failure increments the overview after clearing');
check(true, str_contains($controller, '(new PaymentService())->visibleHistoryStats()'), 'Overview uses filtered statistics');
check(true, str_contains($controller, '(new PaymentService())->clearHistory()'), 'Clear action uses the same service');

$html = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/payment-admin.php') . ' subscribers');
$document = new DOMDocument();
$previous = libxml_use_internal_errors(true);
$document->loadHTML('<?xml encoding="UTF-8">' . $html);
libxml_clear_errors();
libxml_use_internal_errors($previous);
$xpath = new DOMXPath($document);
$deleteForms = '//table//form[@action="/admin/subscriptions/subscribers/delete" or @action="/admin/subscriptions/subscribers/delete-disabled"]';
$disabledForms = '//table//form[@action="/admin/subscriptions/subscribers/delete-disabled"]';
check(5, $xpath->query($deleteForms)->length, 'Disabled records of every source and expired subscribers show delete actions');
check(5, $xpath->query($deleteForms . '[ancestor::div[contains(@class,"dropdown-menu")]]')->length, 'Deletion stays inside the existing Actions dropdown');
check(0, $xpath->query($deleteForms . '[not(ancestor::div[contains(@class,"dropdown-menu")])]')->length, 'No standalone subscriber delete button is rendered');
check(5, $xpath->query($deleteForms . '[@data-admin-delete-form and @data-delete-message]/input[@name="csrf"]')->length, 'Every delete action has confirmation and CSRF');
check(4, $xpath->query($disabledForms . '/input[@name="subscription_id"]')->length, 'Disabled records are targeted by subscription ID');
check(1, $xpath->query($disabledForms . '/input[@name="subscription_id" and @value="2"]')->length, 'A different active subscription does not hide deletion of the disabled row');
check(0, $xpath->query($deleteForms . '/input[(@name="subscription_id" or @name="user_id") and @value="3"]')->length, 'The active subscription itself cannot be deleted');
check(5, $xpath->query($deleteForms . '/button/span[text()="Удалить подписчика"]')->length, 'Delete buttons have clear captions');
check(4, $xpath->query($disabledForms . '[@data-delete-message="' . FireballPluginSubscriptions::t('subscriptions_subscriber_delete_disabled_confirm') . '"]')->length, 'Confirmation explains that other subscriptions will remain');
check(4, $xpath->query('//form[@action="/admin/subscriptions/subscribers/delete-disabled" and not(ancestor::table)]/input[@name="subscription_id"]')->length, 'Mobile action menus also include every disabled source');

if ($failures !== []) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
echo "Admin history and subscriber tests passed: {$checks} checks." . PHP_EOL;
