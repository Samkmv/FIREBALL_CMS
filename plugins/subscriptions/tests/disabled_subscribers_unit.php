<?php

declare(strict_types=1);

require __DIR__ . '/profile_checkout_unit.php';
require_once __DIR__ . '/../src/Services/SubscriptionService.php';
$checks = 0;
$failures = [];
FireballPluginSubscriptions::$locale = 'ru';
db()->pdo->exec('CREATE TABLE subscriptions (id INTEGER PRIMARY KEY, user_id INTEGER, status TEXT, source TEXT, utility_managed INTEGER, archived_at TEXT, auto_renew INTEGER, next_billing_at TEXT, starts_at TEXT, ends_at TEXT, updated_at TEXT)');
db()->pdo->exec('CREATE TABLE subscription_events (id INTEGER PRIMARY KEY, subscription_id INTEGER, payment_id INTEGER, user_id INTEGER, actor_user_id INTEGER, event_key TEXT, old_status TEXT, new_status TEXT, metadata TEXT, created_at TEXT)');
db()->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
db()->pdo->exec('CREATE TABLE subscription_orders (id INTEGER PRIMARY KEY, subscription_id INTEGER, status TEXT, consent_snapshot TEXT)');
db()->pdo->exec('CREATE TABLE subscription_payments (id INTEGER PRIMARY KEY, order_id INTEGER, subscription_id INTEGER, status TEXT, parent_payment_id INTEGER, amount_minor INTEGER)');
db()->pdo->exec('CREATE TABLE subscription_webhook_events (id INTEGER PRIMARY KEY, signature_verified INTEGER, payload TEXT)');
db()->query('INSERT INTO users VALUES (41, ?)', ['Test subscriber']);
$service = new Fireball\Subscriptions\Services\SubscriptionService();
foreach ([1 => 'active', 2 => 'grace_period', 3 => 'cancelled', 4 => 'pending', 5 => 'past_due', 6 => 'expired'] as $id => $status) {
    db()->query('INSERT INTO subscriptions VALUES (?, 41, ?, ?, 0, NULL, 1, ?, ?, ?, ?)', [$id, $status, 'robokassa', '2099-01-01 00:00:00', '2000-01-01 00:00:00', '2099-01-01 00:00:00', 'unchanged']);
}
foreach ([10 => ['external', 0], 11 => ['manual', 0], 12 => ['robokassa', 0], 13 => ['external', 1]] as $id => [$source, $managed]) {
    db()->query('INSERT INTO subscriptions VALUES (?, 41, ?, ?, ?, NULL, 1, ?, ?, ?, ?)', [$id, 'disabled', $source, $managed, '2099-01-01 00:00:00', '2000-01-01 00:00:00', '2099-01-01 00:00:00', 'unchanged']);
    db()->query('INSERT INTO subscription_orders VALUES (?, ?, ?, ?)', [$id, $id, 'paid', 'original-consents']);
    db()->query('INSERT INTO subscription_payments VALUES (?, ?, ?, ?, ?, ?)', [$id, $id, $id, 'paid', 12, 15000]);
}
db()->query('INSERT INTO subscription_webhook_events VALUES (1, 1, ?)', ['verified-result']);
$retained = [];
foreach (['users', 'subscription_orders', 'subscription_payments', 'subscription_webhook_events'] as $table) $retained[$table] = db()->query('SELECT * FROM ' . $table)->get();
$otherSubscriptions = db()->query('SELECT * FROM subscriptions WHERE id < 10 ORDER BY id')->get();
foreach ([10, 11, 12, 13] as $id) {
    $before = db()->query('SELECT * FROM subscriptions WHERE id = ?', [$id])->getOne();
    check(true, $service->archiveDisabledSubscription($id, 99), 'Disabled source can be removed: ' . $before['source']);
    $after = db()->query('SELECT * FROM subscriptions WHERE id = ?', [$id])->getOne();
    check(true, !empty($after['archived_at']), 'Selected row is soft-deleted');
    check('disabled', $after['status'], 'Original status retained for audit');
    check($before['ends_at'], $after['ends_at'], 'Historic end date is unchanged');
    check($before['source'], $after['source'], 'Historic source is unchanged');
    check(0, $after['auto_renew'], 'Archived row cannot auto-renew');
    check(null, $after['next_billing_at'], 'Billing schedule is cleared');
    check($otherSubscriptions, db()->query('SELECT * FROM subscriptions WHERE id < 10 ORDER BY id')->get(), 'Other subscriptions, including active ones, remain intact');
    check(false, $service->archiveDisabledSubscription($id, 99), 'Repeated removal is idempotent');
    check($after, db()->query('SELECT * FROM subscriptions WHERE id = ?', [$id])->getOne(), 'Repeated removal changes no fields');
}
foreach ($retained as $table => $rows) check($rows, db()->query('SELECT * FROM ' . $table)->get(), 'Retain ' . $table);
check(4, (int)db()->query("SELECT COUNT(*) FROM subscription_events WHERE event_key = 'subscriber.disabled_archived_by_admin' AND actor_user_id = 99 AND user_id = 41 AND subscription_id BETWEEN 10 AND 13")->getColumn(), 'Each removal is audited once');
check(0, (int)db()->query("SELECT COUNT(*) FROM subscriptions WHERE status = 'disabled' AND archived_at IS NULL")->getColumn(), 'Disabled rows disappear from the subscriber list');
foreach ([1, 2, 3, 4, 5, 6] as $id) {
    try { $service->archiveDisabledSubscription($id, 99); check(true, false, 'Wrong state must be rejected'); }
    catch (DomainException $exception) { check(FireballPluginSubscriptions::t('subscriptions_subscriber_delete_disabled_only'), $exception->getMessage(), 'Server rejects a non-disabled record'); }
    check(false, db()->inTransaction(), 'Rejection rolls back its transaction');
}
// The administrator loaded a disabled row, but it was reactivated before the POST arrived.
db()->query("INSERT INTO subscriptions (id, user_id, status, source) VALUES (20, 41, 'disabled', 'manual')");
db()->query("UPDATE subscriptions SET status = 'active' WHERE id = 20");
try { $service->archiveDisabledSubscription(20, 99); check(true, false, 'Stale form must not delete reactivated access'); }
catch (DomainException) { check(null, db()->query('SELECT archived_at FROM subscriptions WHERE id = 20')->getColumn(), 'Fresh backend status wins over stale UI'); }
foreach ([0, -1, 999] as $id) {
    try { $service->archiveDisabledSubscription($id, 99); check(true, false, 'Invalid target must not succeed'); }
    catch (InvalidArgumentException|RuntimeException $exception) { check(FireballPluginSubscriptions::t('subscriptions_error_subscription_not_found'), $exception->getMessage(), 'Invalid/missing target rejected'); }
}
$routes = file_get_contents(__DIR__ . '/../routes.php');
check(true, str_contains($routes, "\$router->post('/admin/subscriptions/subscribers/delete-disabled', [SubscriptionsAdminController::class, 'deleteDisabledSubscriber'])->middleware(['auth', 'admin']);"), 'Deletion uses an authenticated admin-only POST with normal CSRF middleware');
check(4, (int)db()->query("SELECT COUNT(*) FROM subscription_events WHERE event_key = 'subscriber.disabled_archived_by_admin'")->getColumn(), 'Rejected requests create no removal audit events');
if ($failures !== []) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
echo "Disabled subscriber tests passed: {$checks} checks." . PHP_EOL;
