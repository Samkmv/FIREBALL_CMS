<?php

declare(strict_types=1);

$settingsStore = [];

define('CHAT_ENCRYPTION_KEY', str_repeat('unit-test-key-', 4));

function base_url(string $path = ''): string
{
    return 'https://example.test' . $path;
}

function current_locale(): string
{
    return 'en';
}

function plugin_setting(string $slug, string $key, mixed $default = null): mixed
{
    global $settingsStore;
    return $settingsStore[$slug][$key] ?? $default;
}

function plugin_setting_set(string $slug, string $key, mixed $value): void
{
    global $settingsStore;
    $settingsStore[$slug][$key] = $value;
}

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assertTrueValue(bool $value, string $message): void
{
    if (!$value) {
        throw new RuntimeException($message);
    }
}

require_once __DIR__ . '/../src/Support/Money.php';
require_once __DIR__ . '/../src/Support/SecretCipher.php';
require_once __DIR__ . '/../src/Services/SettingsService.php';
require_once __DIR__ . '/../src/Payments/PaymentGatewayInterface.php';
require_once __DIR__ . '/../src/Payments/RobokassaGateway.php';
require_once __DIR__ . '/../../../app/Services/SqlFileRunner.php';

use App\Services\SqlFileRunner;
use Fireball\Subscriptions\Payments\RobokassaGateway;
use Fireball\Subscriptions\Services\SettingsService;
use Fireball\Subscriptions\Support\Money;

assertSameValue(0, Money::toMinor('0'), 'Zero money parsing');
assertSameValue(10050, Money::toMinor('100,50'), 'Exact money parsing');
assertSameValue('100.50', Money::decimal(10050), 'Exact money formatting');
assertSameValue('100,50 RUB', Money::display(10050), 'Display money formatting');

$invalidMoneyRejected = false;
try {
    Money::toMinor('1.001');
} catch (InvalidArgumentException) {
    $invalidMoneyRejected = true;
}
assertTrueValue($invalidMoneyRejected, 'Money with more than two decimal places must be rejected');

$settings = new SettingsService();
$settings->ensureDefaults();
$settings->save([
    'merchant_login' => 'merchant',
    'password1' => 'secret-one',
    'password2' => 'secret-two',
    'hash_algorithm' => 'sha256',
    'currency' => 'RUB',
    'payment_timeout_minutes' => 60,
    'media_token_ttl' => 300,
]);
assertTrueValue(plugin_setting('subscriptions', 'password1') !== 'secret-one', 'Password #1 must be encrypted at rest');
assertSameValue('', $settings->current()['password1'], 'Secrets must not be returned to admin views');
assertSameValue('secret-one', $settings->current(true)['password1'], 'Gateway must be able to decrypt Password #1');

$gateway = new RobokassaGateway($settings);
$url = $gateway->checkoutUrl(
    ['id' => 7, 'invoice_id' => 123, 'user_id' => 4, 'amount_minor' => 10050],
    ['name' => 'Test plan', 'is_recurring' => 0],
    ['email' => 'buyer@example.test']
);
parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
$expectedCheckoutSignature = hash('sha256', 'merchant:100.50:123:secret-one:Shp_order=7:Shp_user=4');
assertSameValue($expectedCheckoutSignature, $query['SignatureValue'] ?? '', 'Checkout signature');
assertSameValue('100.50', $query['OutSum'] ?? '', 'Checkout amount');

$settings->save([
    'merchant_login' => 'merchant',
    'hash_algorithm' => 'sha256',
    'currency' => 'RUB',
    'payment_timeout_minutes' => 60,
    'media_token_ttl' => 300,
    'receipt_enabled' => '1',
    'receipt_tax' => 'none',
    'receipt_payment_method' => 'full_payment',
    'receipt_payment_object' => 'service',
]);
$receiptUrl = $gateway->checkoutUrl(
    ['id' => 7, 'invoice_id' => 123, 'user_id' => 4, 'amount_minor' => 10050],
    ['name' => 'Test plan', 'is_recurring' => 0],
    ['email' => 'buyer@example.test']
);
parse_str((string)parse_url($receiptUrl, PHP_URL_QUERY), $receiptQuery);
$receipt = (string)($receiptQuery['Receipt'] ?? '');
$expectedReceiptSignature = hash('sha256', 'merchant:100.50:123:' . rawurlencode($receipt) . ':secret-one:Shp_order=7:Shp_user=4');
assertTrueValue($receipt !== '' && is_array(json_decode($receipt, true)), 'Receipt must be valid JSON');
assertSameValue($expectedReceiptSignature, $receiptQuery['SignatureValue'] ?? '', 'Receipt checkout signature');

$settings->save([
    'merchant_login' => 'merchant',
    'hash_algorithm' => 'sha256',
    'currency' => 'RUB',
    'payment_timeout_minutes' => 60,
    'media_token_ttl' => 300,
    'recurring_enabled' => '1',
]);
$recurringOrder = [
    'id' => 7,
    'invoice_id' => 123,
    'user_id' => 4,
    'amount_minor' => 10050,
    'consent_snapshot' => json_encode(['recurring' => true, 'auto_renew' => true]),
];
parse_str((string)parse_url($gateway->checkoutUrl($recurringOrder, ['name' => 'Test plan', 'is_recurring' => 1], ['email' => 'buyer@example.test']), PHP_URL_QUERY), $recurringQuery);
assertSameValue('true', $recurringQuery['Recurring'] ?? '', 'Recurring checkout registration requires explicit consent');
$recurringOrder['consent_snapshot'] = json_encode(['recurring' => true, 'auto_renew' => false]);
parse_str((string)parse_url($gateway->checkoutUrl($recurringOrder, ['name' => 'Test plan', 'is_recurring' => 1], ['email' => 'buyer@example.test']), PHP_URL_QUERY), $manualQuery);
assertTrueValue(!isset($manualQuery['Recurring']), 'Manual checkout must not register recurring billing');

$callback = [
    'OutSum' => '100.50',
    'InvId' => '123',
    'Shp_order' => '7',
    'Shp_user' => '4',
];
$callback['SignatureValue'] = hash('sha256', '100.50:123:secret-two:Shp_order=7:Shp_user=4');
assertTrueValue($gateway->verifyResult($callback), 'Valid ResultURL signature');
$callback['OutSum'] = '100.51';
assertTrueValue(!$gateway->verifyResult($callback), 'Tampered ResultURL amount must fail signature verification');
assertSameValue('OK123', $gateway->expectedResultResponse(123), 'Robokassa acknowledgement');

$migration = (string)file_get_contents(__DIR__ . '/../migrations/001_create_subscriptions_tables.sql');
$statements = (new SqlFileRunner())->split($migration);
assertTrueValue(count($statements) >= 12, 'Migration must contain all subscription tables');
assertTrueValue(str_contains($migration, 'uq_subscription_payment_invoice'), 'Payment invoice idempotency index');
assertTrueValue(str_contains($migration, 'uq_subscription_webhook_hash'), 'Webhook idempotency index');

$upgradeMigration = (string)file_get_contents(__DIR__ . '/../migrations/002_finalize_recurring_schema.sql');
$upgradeStatements = (new SqlFileRunner())->split($upgradeMigration);
assertTrueValue(count($upgradeStatements) >= 12, 'Upgrade migration must be split into executable statements');
assertTrueValue(str_contains($upgradeMigration, 'uq_subscription_recurring_period'), 'Recurring billing period idempotency index');

$snapshotMigration = (string)file_get_contents(__DIR__ . '/../migrations/003_add_order_plan_snapshot.sql');
$snapshotStatements = (new SqlFileRunner())->split($snapshotMigration);
assertTrueValue(count($snapshotStatements) >= 7, 'Plan snapshot migration must be split into executable statements');
assertTrueValue(str_contains($migration, 'plan_snapshot MEDIUMTEXT NOT NULL'), 'Fresh orders must store an immutable plan snapshot');
assertTrueValue(str_contains($snapshotMigration, 'JSON_OBJECT'), 'Existing orders must receive a compatible plan snapshot');

$profileMigration = (string)file_get_contents(__DIR__ . '/../migrations/004_normalize_optional_profile_fields.sql');
assertTrueValue(str_contains($profileMigration, "'apartment'"), 'Apartment must remain an optional address field');

foreach (['dashboard', 'plans', 'plan-form', 'subscribers', 'payments', 'fields', 'field-form', 'settings'] as $adminView) {
    $adminTemplate = (string)file_get_contents(__DIR__ . '/../views/admin/' . $adminView . '.php');
    assertTrueValue(
        str_contains($adminTemplate, "require __DIR__ . '/shell-open.php'")
        && str_contains($adminTemplate, "require __DIR__ . '/shell-close.php'")
        && !str_contains($adminTemplate, 'container-fluid'),
        'Admin view must render inside the standard constrained admin shell: ' . $adminView
    );
}

fwrite(STDOUT, "subscriptions_unit: ok\n");
