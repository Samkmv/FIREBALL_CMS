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

final class SubscriptionsUnitDbResult
{
    public function getOne(): mixed
    {
        return null;
    }

    public function get(): array
    {
        return [];
    }

    public function getColumn(): mixed
    {
        return null;
    }
}

final class SubscriptionsUnitDb
{
    public function query(string $sql, array $params = []): SubscriptionsUnitDbResult
    {
        return new SubscriptionsUnitDbResult();
    }
}

function db(): SubscriptionsUnitDb
{
    static $database;

    return $database ??= new SubscriptionsUnitDb();
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
require_once __DIR__ . '/../src/Support/ProtectedContent.php';
require_once __DIR__ . '/../src/Support/SecretCipher.php';
require_once __DIR__ . '/../src/Repositories/ContentRuleRepository.php';
require_once __DIR__ . '/../src/Services/AccessService.php';
require_once __DIR__ . '/../src/Services/SettingsService.php';
require_once __DIR__ . '/../src/Payments/PaymentGatewayInterface.php';
require_once __DIR__ . '/../src/Payments/RobokassaGateway.php';
require_once __DIR__ . '/../../../app/Services/SqlFileRunner.php';

use App\Services\SqlFileRunner;
use Fireball\Subscriptions\Payments\RobokassaGateway;
use Fireball\Subscriptions\Services\AccessService;
use Fireball\Subscriptions\Services\SettingsService;
use Fireball\Subscriptions\Support\Money;
use Fireball\Subscriptions\Support\ProtectedContent;

$manifest = json_decode((string)file_get_contents(__DIR__ . '/../plugin.json'), true, 512, JSON_THROW_ON_ERROR);
assertSameValue('1.2.1', $manifest['version'] ?? '', 'Plugin release version');
assertSameValue('github_directory', $manifest['update']['provider'] ?? '', 'Independent update provider');
assertSameValue('Samkmv/FIREBALL_CMS', $manifest['update']['repository'] ?? '', 'Independent update repository');
assertSameValue('main', $manifest['update']['branch'] ?? '', 'Independent update branch');
assertSameValue('plugins/subscriptions', $manifest['update']['path'] ?? '', 'Independent update directory');

$supportedLocales = ['ru', 'en', 'de', 'zh-cn'];
foreach (['name_i18n', 'description_i18n', 'release_notes_i18n'] as $localizedManifestField) {
    assertSameValue(
        $supportedLocales,
        array_keys((array)($manifest[$localizedManifestField] ?? [])),
        'Manifest translations must cover every CMS language: ' . $localizedManifestField
    );
    foreach ($supportedLocales as $locale) {
        $localizedValue = $manifest[$localizedManifestField][$locale] ?? '';
        assertTrueValue(
            is_array($localizedValue)
                ? $localizedValue !== [] && count(array_filter($localizedValue, static fn(mixed $item): bool => trim((string)$item) !== '')) === count($localizedValue)
                : trim((string)$localizedValue) !== '',
            'Manifest translation must not be empty: ' . $localizedManifestField . '.' . $locale
        );
    }
}

$englishTranslations = require __DIR__ . '/../lang/en.php';
foreach ($supportedLocales as $locale) {
    $translations = require __DIR__ . '/../lang/' . $locale . '.php';
    assertSameValue(
        array_keys($englishTranslations),
        array_keys($translations),
        'Plugin interface translation keys must match English: ' . $locale
    );
    assertTrueValue(
        count(array_filter($translations, static fn(mixed $value): bool => trim((string)$value) === '')) === 0,
        'Plugin interface translations must not contain empty values: ' . $locale
    );
}

assertSameValue(0, Money::toMinor('0'), 'Zero money parsing');
assertSameValue(10050, Money::toMinor('100,50'), 'Exact money parsing');
assertSameValue('100.50', Money::decimal(10050), 'Exact money formatting');
assertSameValue('100,50 RUB', Money::display(10050), 'Display money formatting');

$anonymousPostDecision = (new AccessService())->contentDecision(0, 'post', 42);
assertTrueValue($anonymousPostDecision['allowed'], 'Posts without an explicit rule must remain public');
assertSameValue('public', $anonymousPostDecision['reason'], 'Anonymous post access reason');
assertSameValue('public', $anonymousPostDecision['rule']['access_mode'] ?? '', 'Default post access mode');

$protectedHtml = ProtectedContent::replaceVideos(
    '<p>Before</p><video src="/paid.mp4"></video><iframe src="https://www.youtube.com/embed/one"></iframe><iframe src="https://maps.google.com/map"></iframe>',
    '<div>locked</div>'
);
assertTrueValue(!str_contains($protectedHtml, '/paid.mp4'), 'Protected video source must be removed from HTML');
assertTrueValue(!str_contains($protectedHtml, 'youtube.com'), 'Protected video embed must be removed from HTML');
assertTrueValue(str_contains($protectedHtml, 'maps.google.com'), 'Non-video embeds must remain available');
assertSameValue(2, substr_count($protectedHtml, '<div>locked</div>'), 'Each protected video must get a replacement');

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

$accessMigration = (string)file_get_contents(__DIR__ . '/../migrations/005_default_post_access_to_subscribers.sql');
assertTrueValue(
    str_contains($accessMigration, "DEFAULT 'subscribers'"),
    'Legacy subscriber-default migration must remain available before the public-default correction'
);

$publicAccessMigration = (string)file_get_contents(__DIR__ . '/../migrations/006_default_post_access_to_public.sql');
assertTrueValue(str_contains($publicAccessMigration, "DEFAULT 'public'"), 'Content rules must default to public access unless explicitly protected');

$openExistingContentMigration = (string)file_get_contents(__DIR__ . '/../migrations/007_open_existing_posts_and_videos.sql');
assertTrueValue(
    str_contains($openExistingContentMigration, "SET access_mode = 'public'")
    && str_contains($openExistingContentMigration, 'hide_video = 0'),
    'Existing posts and videos must be opened when production installs the update'
);

$pluginSource = (string)file_get_contents(__DIR__ . '/../Plugin.php');
assertTrueValue(
    str_contains($pluginSource, "add_filter('public_posts_before_render'")
    && str_contains($pluginSource, "add_filter('public_page_before_render'")
    && str_contains($pluginSource, "add_filter('public_video_access_allowed'"),
    'Plugin must integrate with public post, page and direct video rendering'
);
assertTrueValue(
    str_contains($pluginSource, 'public static function filterPublicVideoAccess')
    && str_contains($pluginSource, 'return $allowed;'),
    'The subscription plugin must not globally close public video by default'
);
assertTrueValue(
    str_contains($pluginSource, 'subscriptions-access-message__actions')
    && str_contains((string)file_get_contents(__DIR__ . '/../../../themes/default/templates/posts.php'), 'subscriptions-lock-badge'),
    'Protected content must use compact, explanatory subscription notices'
);

foreach (['dashboard', 'plans', 'plan-form', 'subscribers', 'payments', 'fields', 'field-form', 'settings'] as $adminView) {
    $adminTemplate = (string)file_get_contents(__DIR__ . '/../views/admin/' . $adminView . '.php');
    assertTrueValue(
        str_contains($adminTemplate, "require __DIR__ . '/shell-open.php'")
        && str_contains($adminTemplate, "require __DIR__ . '/shell-close.php'")
        && !str_contains($adminTemplate, 'container-fluid'),
        'Admin view must render inside the standard constrained admin shell: ' . $adminView
    );
}

$settingsTemplate = (string)file_get_contents(__DIR__ . '/../views/admin/settings.php');
$adminControllerSource = (string)file_get_contents(__DIR__ . '/../src/Controllers/AdminController.php');
$routesSource = (string)file_get_contents(__DIR__ . '/../routes.php');
assertTrueValue(
    str_contains($settingsTemplate, "/admin/subscriptions/settings/save")
    && str_contains($routesSource, "/admin/subscriptions/settings/save")
    && str_contains($adminControllerSource, 'public function saveSettings(): never'),
    'Robokassa settings must use an explicit save endpoint'
);
assertTrueValue(
    str_contains($adminControllerSource, 'beginTransaction()')
    && str_contains($adminControllerSource, 'Robokassa settings save failed'),
    'Robokassa settings writes must be atomic and safely logged'
);

foreach (['plans', 'subscribers', 'payments', 'fields'] as $tableView) {
    $tableTemplate = (string)file_get_contents(__DIR__ . '/../views/admin/' . $tableView . '.php');
    assertTrueValue(
        str_contains($tableTemplate, "renderPartial('admin/partials/table'")
        && str_contains($tableTemplate, "'mobile_cards' => \$mobileCards")
        && str_contains($tableTemplate, 'admin-table-card'),
        'Admin data list must use the template mobile-card table component: ' . $tableView
    );
}

$accountTemplate = (string)file_get_contents(__DIR__ . '/../views/public/account.php');
assertTrueValue(
    str_contains($accountTemplate, "renderPartial('admin/partials/table'")
    && str_contains($accountTemplate, "'mobile_cards' => \$paymentCards")
    && !str_contains($accountTemplate, '<table'),
    'Public payment history must use the template mobile-card table component'
);

fwrite(STDOUT, "subscriptions_unit: ok\n");
