<?php

declare(strict_types=1);

namespace Fireball\Subscriptions\Services {
    // Activation is tested separately; here we verify that retries call it once, only for valid results.
    final class SubscriptionService
    {
        public static int $activations = 0;
        public function activatePaidOrder(array $order, int $paymentId): array
        {
            self::$activations++;
            return ['id' => 500 + $paymentId];
        }
    }
}

namespace {
require __DIR__ . '/profile_checkout_unit.php';
require_once __DIR__ . '/../src/Support/Money.php';
require_once __DIR__ . '/../src/Support/SecretCipher.php';
require_once __DIR__ . '/../src/Services/SettingsService.php';
require_once __DIR__ . '/../src/Payments/PaymentGatewayInterface.php';
require_once __DIR__ . '/../src/Payments/RobokassaGateway.php';
require_once __DIR__ . '/../src/Services/PaymentService.php';

function notification_create(array $data): void {}
function log_error_details(string $message, array $context = [], ?Throwable $exception = null): void {}
function base_url(string $path = ''): string { return 'https://site.test' . $path; }
function plugin_setting_set(string $slug, string $key, mixed $value): void { $GLOBALS['profileTestSettings'][$key] = $value; }
define('CHAT_ENCRYPTION_KEY', str_repeat('test-only-key-', 4));

$checks = 0;
$failures = [];
FireballPluginSubscriptions::$locale = 'ru';
db()->pdo->exec('CREATE TABLE pages (id INTEGER PRIMARY KEY, title TEXT, slug TEXT, is_published INTEGER)');
db()->query('INSERT INTO pages VALUES (?, ?, ?, ?)', [1, 'Оферта СКФ', 'offer', 1]);
db()->query('INSERT INTO pages VALUES (?, ?, ?, ?)', [2, 'Черновик', 'draft', 0]);
db()->pdo->exec('CREATE TABLE plugin_settings (id INTEGER PRIMARY KEY, plugin_slug TEXT, setting_key TEXT, setting_value TEXT, updated_at TEXT)');
$offer = new Fireball\Subscriptions\Services\PublicOfferService();
check(1, count($offer->pages()), 'Only published pages can be selected');
foreach (['https://example.test/offer.pdf', 'http://example.test/offer', '/offer?lang=ru'] as $url) {
    check(true, $offer->validUrl($url), 'Allowed offer URL: ' . $url);
}
foreach (['javascript:alert(1)', 'data:text/html,test', '//attacker.test', '/\\attacker.test', 'https://user:pass@example.test/offer', "https://example.test/\n", ''] as $url) {
    check(false, $offer->validUrl($url), 'Unsafe offer URL rejected');
}
$GLOBALS['profileTestSettings'] = ['public_offer_page_id' => 1, 'public_offer_url' => 'https://example.test/offer.pdf'];
check('/offer', $offer->url(), 'Selected page takes precedence');
$GLOBALS['profileTestSettings']['public_offer_page_id'] = 2;
check('https://example.test/offer.pdf', $offer->url(), 'Draft page falls back to configured URL');
$GLOBALS['profileTestSettings']['public_offer_page_id'] = 0;
$GLOBALS['profileTestSettings']['public_offer_url'] = '/offer.pdf';
check('/offer.pdf', $offer->url(), 'Site-relative PDF URL is supported');

$settings = new Fireball\Subscriptions\Services\SettingsService();
$settingsData = [
    'merchant_login' => 'test-shop', 'password1' => 'test-one', 'password2' => 'test-two',
    'public_offer_page_id' => 1, 'public_offer_url' => 'https://example.test/terms.pdf',
];
$settings->save($settingsData);
check(1, $settings->current()['public_offer_page_id'], 'Offer page setting persists');
check('https://example.test/terms.pdf', $settings->current()['public_offer_url'], 'Offer URL setting persists');
foreach ([['public_offer_page_id' => 2], ['public_offer_page_id' => 999], ['public_offer_url' => 'javascript:alert(1)']] as $invalid) {
    $before = $GLOBALS['profileTestSettings'];
    try {
        $settings->save(array_replace($settingsData, $invalid));
        check(true, false, 'Invalid offer setting must throw');
    } catch (InvalidArgumentException) {
        check($before, $GLOBALS['profileTestSettings'], 'Invalid settings do not partially overwrite stored values');
    }
}
$xpath = renderCheckout(false);
check('/offer', $xpath->query('//div[contains(@class,"subscriptions-checkout-consents")]//a')->item(0)->getAttribute('href'), 'Checkout uses configured offer page');
check('публичной оферты', $xpath->query('//div[contains(@class,"subscriptions-checkout-consents")]//a')->item(0)->textContent, 'Requested inline offer caption');

db()->pdo->exec('CREATE TABLE subscription_orders (id INTEGER PRIMARY KEY, invoice_id INTEGER, user_id INTEGER, plan_id INTEGER, amount_minor INTEGER, currency TEXT, status TEXT, expires_at TEXT, updated_at TEXT)');
db()->pdo->exec('CREATE TABLE subscription_payments (id INTEGER PRIMARY KEY, order_id INTEGER, invoice_id INTEGER, user_id INTEGER, plan_id INTEGER, provider TEXT, amount_minor INTEGER, currency TEXT, status TEXT, signature_verified INTEGER DEFAULT 0, error_message TEXT, failed_at TEXT, paid_at TEXT, updated_at TEXT, provider_payload TEXT, provider_transaction TEXT, cleared_at TEXT)');
db()->pdo->exec('CREATE TABLE subscription_webhook_events (id INTEGER PRIMARY KEY, provider TEXT, invoice_id INTEGER, event_hash TEXT, signature_verified INTEGER, processing_status TEXT, payload TEXT, error_message TEXT, created_at TEXT, processed_at TEXT)');
function seedPayment(int $id, string $status = 'pending', ?string $expiry = '2000-01-01 00:00:00', int $user = 41, ?string $orderStatus = null): void
{
    db()->query('INSERT INTO subscription_orders VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', [$id, 1000 + $id, $user, 9, 15000, 'RUB', $orderStatus ?? $status, $expiry, 'unchanged']);
    db()->query('INSERT INTO subscription_payments (id, order_id, invoice_id, user_id, plan_id, provider, amount_minor, currency, status, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [$id, $id, 1000 + $id, $user, 9, 'robokassa', 15000, 'RUB', $status, 'unchanged']);
}
function seedWebhook(int $id, bool $verified, string $status, string $amount = '150.000000'): void
{
    db()->query('INSERT INTO subscription_webhook_events (id, provider, invoice_id, event_hash, signature_verified, processing_status, payload) VALUES (?, ?, ?, ?, ?, ?, ?)', [$id, 'robokassa', 1000 + $id, 'event-' . $id, $verified ? 1 : 0, $status, json_encode(['InvId' => 1000 + $id, 'OutSum' => $amount])]);
}
function payment(int $id): array { return db()->query('SELECT * FROM subscription_payments WHERE id = ?', [$id])->getOne(); }
seedPayment(1);
seedPayment(2, expiry: '2099-01-01 00:00:00');
seedPayment(3, 'paid');
seedPayment(4); seedWebhook(4, true, 'failed');
seedPayment(5); seedWebhook(5, false, 'rejected');
seedPayment(6); seedWebhook(6, true, 'processing');
seedPayment(7, expiry: null);
seedPayment(8, 'cancelled');
seedPayment(9, user: 42);
seedPayment(10, orderStatus: 'paid');
seedPayment(11, 'failed'); seedWebhook(11, true, 'failed');
db()->query('UPDATE subscription_payments SET error_message = ?, failed_at = ? WHERE id = ?', ['Payment timeout', '2000-01-01 00:00:00', 11]);
seedPayment(12); seedWebhook(12, true, 'failed', '1.00');
$payments = new Fireball\Subscriptions\Services\PaymentService();
check(2, $payments->expirePending(41), 'Only expired, unconfirmed payments for this user time out');
check('failed', payment(1)['status'], 'Expired pending payment is updated');
check('Payment timeout', payment(1)['error_message'], 'Timeout is distinguishable from provider rejection');
foreach ([2 => 'pending', 3 => 'paid', 4 => 'pending', 6 => 'pending', 7 => 'pending', 8 => 'cancelled', 9 => 'pending', 10 => 'pending', 12 => 'pending'] as $id => $status) {
    check($status, payment($id)['status'], 'Protected state is retained: payment ' . $id);
}
check('unchanged', payment(3)['updated_at'], 'Paid payment is not touched');
check(0, $payments->expirePending(41), 'Expiry is idempotent');
check(1, $payments->expirePending(), 'Admin maintenance can expire other users safely');
check(['processed' => 2, 'failed' => 1], $payments->retryFailedWebhooks(), 'Only stored, verified notifications are replayed');
check('paid', payment(4)['status'], 'Failed processing is recovered');
check('paid', payment(11)['status'], 'Late verified callback recovers a timed-out payment');
check(null, payment(11)['failed_at'], 'Successful recovery clears stale failure timestamp');
check(null, payment(11)['error_message'], 'Successful recovery clears stale error');
check('Payment amount mismatch.', payment(12)['error_message'], 'Mismatched amounts are never activated');
check(2, Fireball\Subscriptions\Services\SubscriptionService::$activations, 'Exactly two valid payments activate');
check(['processed' => 0, 'failed' => 1], $payments->retryFailedWebhooks(), 'Refresh does not activate twice');
check(2, Fireball\Subscriptions\Services\SubscriptionService::$activations, 'Activation remains idempotent');
check(12, count(db()->query('SELECT id FROM subscription_payments')->get()), 'Financial history is not deleted');
check(12, $payments->clearHistory(), 'History clear includes every payment status');
db()->query('UPDATE subscription_webhook_events SET payload = ? WHERE id = ?', [json_encode(['InvId' => 1012, 'OutSum' => '150.00']), 12]);
check(['processed' => 1, 'failed' => 0], $payments->retryFailedWebhooks(), 'Cleared payment can still recover from a verified callback');
check('paid', payment(12)['status'], 'Clearing history does not block later payment activation');
check(true, payment(12)['cleared_at'] !== null, 'Late callback does not unhide cleared history');
check(['paid_total_minor' => 0, 'failed' => 0], $payments->visibleHistoryStats(), 'Late callback does not restore cleared overview counters');

function adminFixture(string $screen): DOMXPath
{
    $html = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/payment-admin.php') . ' ' . escapeshellarg($screen));
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return new DOMXPath($document);
}
$xpath = adminFixture('payments');
check(4, $xpath->query('//table//button[@data-subscriptions-payment-details]')->length, 'All payment rows have detail actions');
check(0, $xpath->query('//table//button[@data-subscriptions-payment-details and contains(@class,"rounded-circle")]')->length, 'Detail actions are not circular');
check(4, $xpath->query('//table//button[@data-subscriptions-payment-details]/span[text()="Посмотреть данные"]')->length, 'Actions have visible captions');
check(2, $xpath->query('//table//span[contains(@class,"badge") and text()="ResultURL не получен"]')->length, 'Missing callbacks appear in badges, including timeouts');
check(1, $xpath->query('//table//span[contains(@class,"badge") and text()="Срок ожидания оплаты истёк"]')->length, 'Timeout has a truthful display status');
check(1, $xpath->query('//table//span[contains(@class,"badge") and text()="Требует проверки"]')->length, 'A verified but unprocessed result is not presented as unpaid');
check(1, $xpath->query('//form[@action="/admin/subscriptions/payments/refresh" and @method="post"]/input[@name="csrf"]')->length, 'Refresh action uses CSRF-protected POST');
$xpath = adminFixture('settings');
check('1', $xpath->query('//select[@name="public_offer_page_id"]/option[@selected]')->item(0)->getAttribute('value'), 'Settings render selected offer page');
check('https://example.test/offer.pdf', $xpath->query('//input[@name="public_offer_url"]')->item(0)->getAttribute('value'), 'Settings render saved offer URL');

if ($failures !== []) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
echo "Payment status and offer tests passed: {$checks} checks." . PHP_EOL;
}
