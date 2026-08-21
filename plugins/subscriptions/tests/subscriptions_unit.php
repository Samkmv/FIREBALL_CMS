<?php

declare(strict_types=1);

$settingsStore = [];
$contentRule = null;
$currentTestUser = [];

define('CHAT_ENCRYPTION_KEY', str_repeat('unit-test-key-', 4));

function base_url(string $path = ''): string
{
    return 'https://example.test' . $path;
}

function current_locale(): string
{
    return 'en';
}

function base_href(string $path = ''): string
{
    return 'https://example.test' . $path;
}

function htmlSC(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function return_translation(string $key): string
{
    return $key;
}

function get_user(): array
{
    global $currentTestUser;

    return $currentTestUser;
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
    public function __construct(private readonly mixed $one = null)
    {
    }

    public function getOne(): mixed
    {
        return $this->one;
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
        global $settingsStore, $contentRule;
        if (str_starts_with($sql, 'SELECT * FROM subscription_content_rules')) {
            return new SubscriptionsUnitDbResult($contentRule);
        }
        if (str_starts_with($sql, 'SELECT id FROM plugin_settings')) {
            $exists = array_key_exists((string)($params[1] ?? ''), $settingsStore[(string)($params[0] ?? '')] ?? []);

            return new SubscriptionsUnitDbResult($exists ? ['id' => 1] : null);
        }
        if (str_starts_with($sql, 'UPDATE plugin_settings SET setting_value')) {
            $decoded = json_decode((string)($params[0] ?? ''), true);
            $settingsStore[(string)($params[2] ?? '')][(string)($params[3] ?? '')] = json_last_error() === JSON_ERROR_NONE
                ? $decoded
                : (string)($params[0] ?? '');
        }

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
require_once __DIR__ . '/../../../core/Plugins/PluginInterface.php';
require_once __DIR__ . '/../Plugin.php';

use App\Services\SqlFileRunner;
use Fireball\Subscriptions\Payments\RobokassaGateway;
use Fireball\Subscriptions\Services\AccessService;
use Fireball\Subscriptions\Services\SettingsService;
use Fireball\Subscriptions\Support\Money;
use Fireball\Subscriptions\Support\ProtectedContent;

$manifest = json_decode((string)file_get_contents(__DIR__ . '/../plugin.json'), true, 512, JSON_THROW_ON_ERROR);
assertSameValue('1.2.26', $manifest['version'] ?? '', 'Plugin release version');
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

$publicVideo = FireballPluginSubscriptions::filterEditorVideoBlock('<video src="/public.mp4"></video>', [
    'id' => 'public-video',
    'type' => 'video',
    'data' => ['subscriptionAccessMode' => 'public'],
]);
assertSameValue('<video src="/public.mp4"></video>', $publicVideo, 'A public video block must remain visible to every visitor');

$subscriberVideo = FireballPluginSubscriptions::filterEditorVideoBlock('<video src="/private.mp4"></video>', [
    'id' => 'subscriber-video',
    'type' => 'video',
    'data' => ['subscriptionAccessMode' => 'subscribers'],
]);
assertTrueValue(
    is_string($subscriberVideo)
    && !str_contains($subscriberVideo, '/private.mp4')
    && str_contains($subscriberVideo, 'subscriptions_access_video_title'),
    'A protected video block must be replaced by its own subscription notice'
);

$contentRule = null;
$publicPost = FireballPluginSubscriptions::filterPublicPost([
    'id' => 42,
    'title' => 'Public post',
    'content' => '<p>Public text</p>' . $subscriberVideo,
], []);
assertTrueValue(
    str_contains((string)$publicPost['content'], 'Public text')
    && str_contains((string)$publicPost['content'], 'subscriptions_access_video_title'),
    'A public post must keep its text and only the protected video notice'
);

$editorState = [
    'version' => 2,
    'blocks' => [
        [
            'id' => 'open-video-block',
            'type' => 'video',
            'data' => ['src' => '/open-video.mp4', 'subscriptionAccessMode' => 'public'],
        ],
        [
            'id' => 'paid-video-block',
            'type' => 'video',
            'data' => ['src' => '/paid-video.mp4', 'subscriptionAccessMode' => 'subscribers'],
        ],
    ],
];
$editorSnapshot = base64_encode((string)json_encode($editorState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$currentEditorContent = '<p>Visible article text</p>'
    . '<div data-fb-block="video" data-fb-block-id="open-video-block"><video src="/open-video.mp4"></video></div>'
    . '<div data-fb-block="video" data-fb-block-id="paid-video-block"><video src="/paid-video.mp4"></video></div>'
    . '<template data-fb-editor-state="2">' . $editorSnapshot . '</template>';
$filteredEditorPost = FireballPluginSubscriptions::filterPublicPost([
    'id' => 43,
    'title' => 'Mixed videos',
    'content' => $currentEditorContent,
], []);
assertTrueValue(
    str_contains((string)$filteredEditorPost['content'], 'Visible article text')
    && str_contains((string)$filteredEditorPost['content'], '/open-video.mp4')
    && !str_contains((string)$filteredEditorPost['content'], '/paid-video.mp4')
    && str_contains((string)$filteredEditorPost['content'], 'subscriptions_access_video_title')
    && !str_contains((string)$filteredEditorPost['content'], 'data-fb-editor-state'),
    'Current HTML-plus-snapshot editor content must protect only the selected video and must not leak its source in public HTML'
);
$creatorEditorPost = FireballPluginSubscriptions::filterPublicPost([
    'id' => 43,
    'title' => 'Mixed videos',
    'content' => $currentEditorContent,
], ['id' => 1, 'role' => 'creator']);
assertTrueValue(
    str_contains((string)$creatorEditorPost['content'], '/open-video.mp4')
    && str_contains((string)$creatorEditorPost['content'], '/paid-video.mp4'),
    'Creators must retain access to protected video blocks'
);

$contentRule = [
    'id' => 7,
    'content_type' => 'post',
    'content_id' => '42',
    'access_mode' => 'subscribers',
    'show_title' => 1,
    'show_excerpt' => 1,
    'show_image' => 1,
    'hide_video' => 0,
    'required_permission' => 'posts.view_paid',
];
$closedPost = FireballPluginSubscriptions::filterPublicPost([
    'id' => 42,
    'title' => 'Closed post',
    'content' => '<p>Secret publication body</p>',
], []);
assertTrueValue(
    !str_contains((string)$closedPost['content'], 'Secret publication body')
    && str_contains((string)$closedPost['content'], 'subscriptions-access-message')
    && str_contains((string)$closedPost['content'], 'subscriptions_access_login_title'),
    'Only an explicitly protected post must replace the full publication body'
);
$contentRule = null;

$invalidMoneyRejected = false;
try {
    Money::toMinor('1.001');
} catch (InvalidArgumentException) {
    $invalidMoneyRejected = true;
}
assertTrueValue($invalidMoneyRejected, 'Money with more than two decimal places must be rejected');

$settings = new SettingsService();
$settings->ensureDefaults();
$incompleteCredentialsRejected = false;
try {
    $settings->save([
        'merchant_login' => 'merchant',
        'hash_algorithm' => 'sha256',
        'currency' => 'RUB',
    ]);
} catch (InvalidArgumentException $exception) {
    $incompleteCredentialsRejected = $exception->getMessage() === SettingsService::CREDENTIALS_NOT_CONFIGURED;
}
assertTrueValue($incompleteCredentialsRejected, 'Settings save must reject incomplete Robokassa credentials');
assertSameValue('', plugin_setting('subscriptions', 'merchant_login'), 'Rejected settings must not be written');
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
$expectedCheckoutSignature = hash('sha256', 'merchant:100.50:123:secret-one');
assertSameValue($expectedCheckoutSignature, $query['SignatureValue'] ?? '', 'Checkout signature');
assertSameValue('100.50', $query['OutSum'] ?? '', 'Checkout amount');
assertSameValue('0', $query['IsTest'] ?? '', 'Live checkout mode must be explicit');
assertTrueValue(!isset($query['Shp_order']) && !isset($query['Shp_user']), 'Checkout must use the minimal Robokassa signature');

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
$expectedReceiptSignature = hash('sha256', 'merchant:100.50:123:' . $receipt . ':secret-one');
assertTrueValue($receipt !== '' && is_array(json_decode(rawurldecode($receipt), true)), 'Receipt must be URL-encoded valid JSON');
assertTrueValue(str_contains((string)parse_url($receiptUrl, PHP_URL_QUERY), 'Receipt=%25'), 'Encoded Receipt must remain encoded after Robokassa parses the query');
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
assertTrueValue(str_contains($migration, 'is_popular TINYINT(1)'), 'Fresh installs must support a manually selected popular plan');

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

$popularPlanMigration = (string)file_get_contents(__DIR__ . '/../migrations/009_add_popular_plan_flag.sql');
$popularPlanStatements = (new SqlFileRunner())->split($popularPlanMigration);
assertTrueValue(
    count($popularPlanStatements) >= 5
    && str_contains($popularPlanMigration, "COLUMN_NAME = 'is_popular'")
    && str_contains($popularPlanMigration, 'ADD COLUMN `is_popular`'),
    'Popular plan flag migration must be idempotent'
);

$pluginSource = (string)file_get_contents(__DIR__ . '/../Plugin.php');
assertTrueValue(
    substr_count($pluginSource, "'icon' => 'ci-award'") >= 3
    && !str_contains($pluginSource, "'icon' => 'ci-repeat'"),
    'Subscription navigation and dashboard surfaces must use the award identity icon'
);
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
assertTrueValue(
    str_contains($pluginSource, "array_key_exists('subscriptionAccessMode', \$blockData)")
    && str_contains($pluginSource, 'private static function canViewEmbeddedVideo')
    && str_contains($pluginSource, 'private static function filterEmbeddedVideosInContent')
    && str_contains($pluginSource, 'private static function editorBlocksFromContent')
    && str_contains($pluginSource, "data-fb-editor-state")
    && str_contains($pluginSource, "return \$access->can(\$userId, 'videos.view_paid')")
    && str_contains($pluginSource, "in_array((int)\$subscription['plan_id'], \$allowedPlans, true)")
    && !str_contains($pluginSource, '$protectVideo'),
    'Each embedded video must enforce its own subscription rule independently from the public post rule'
);
assertTrueValue(
    str_contains($pluginSource, "\$post['content'] = self::postAccessMessage")
    && str_contains($pluginSource, "if (\$decision['allowed'])")
    && str_contains($pluginSource, "\$post['subscription_access'] = \$decision;"),
    'Only an explicitly protected post may replace the whole publication with an access notice'
);

$postSettingsTemplate = (string)file_get_contents(__DIR__ . '/../views/admin/post-settings.php');
assertTrueValue(
    str_contains($postSettingsTemplate, 'subscriptions_video_access_independent_hint')
    && !str_contains($postSettingsTemplate, "'subscriptions_hide_video'")
    && !str_contains($postSettingsTemplate, "['subscription_hide_video'")
    && str_contains($postSettingsTemplate, 'subscriptions-plan-choice')
    && str_contains($postSettingsTemplate, 'type="checkbox" name="subscription_plan_ids[]"')
    && str_contains($postSettingsTemplate, 'data-subscriptions-post-plans')
    && str_contains($postSettingsTemplate, 'data-subscriptions-post-permission')
    && !str_contains($postSettingsTemplate, 'name="subscription_plan_ids[]" multiple'),
    'Post settings must explain block-level video access and must not offer a blanket hide-all-videos switch'
);

foreach (['dashboard', 'plans', 'plan-form', 'subscribers', 'payments', 'content', 'fields', 'field-form', 'settings'] as $adminView) {
    $adminTemplate = (string)file_get_contents(__DIR__ . '/../views/admin/' . $adminView . '.php');
    assertTrueValue(
        str_contains($adminTemplate, "require __DIR__ . '/shell-open.php'")
        && str_contains($adminTemplate, "require __DIR__ . '/shell-close.php'")
        && !str_contains($adminTemplate, 'container-fluid'),
        'Admin view must render inside the standard constrained admin shell: ' . $adminView
    );
}

$tabsTemplate = (string)file_get_contents(__DIR__ . '/../views/admin/tabs.php');
$subscriptionStyles = (string)file_get_contents(__DIR__ . '/../assets/subscriptions.css');
assertTrueValue(
    str_contains($tabsTemplate, 'subscriptions-admin-tabs')
    && str_contains($tabsTemplate, 'aria-current="page"')
    && str_contains($subscriptionStyles, 'scroll-snap-type: x proximity')
    && str_contains($subscriptionStyles, 'overflow-x: auto')
    && str_contains($subscriptionStyles, '.subscriptions-admin-tabs::-webkit-scrollbar-thumb')
    && !preg_match('/\.subscriptions-admin-tabs\s*\{[^}]*grid-template-columns/s', $subscriptionStyles),
    'Subscription admin tabs must remain in one horizontally scrollable mobile row with a slim scrollbar'
);

$planFormTemplate = (string)file_get_contents(__DIR__ . '/../views/admin/plan-form.php');
$planRepositorySource = (string)file_get_contents(__DIR__ . '/../src/Repositories/PlanRepository.php');
assertTrueValue(
    str_contains($planFormTemplate, 'data-slug-source="#subscription_plan_slug"')
    && str_contains($planFormTemplate, 'id="subscription_plan_slug"')
    && str_contains($planFormTemplate, 'data-slug-input')
    && str_contains($planFormTemplate, 'pattern="[a-z0-9-]+"'),
    'Subscription plan names must automatically generate a validated Latin slug without replacing manual edits'
);
assertTrueValue(
    str_contains($planFormTemplate, 'name="is_popular"')
    && str_contains($planFormTemplate, 'subscriptions_plan_popular_hint'),
    'Subscription plans must expose a manual popular-plan switch in the admin editor'
);
assertTrueValue(
    str_contains($planRepositorySource, 'is_popular = 0, updated_at = ? WHERE id <> ? AND is_popular = 1')
    && str_contains($planRepositorySource, "\$copy['is_popular'] = 0")
    && str_contains($planRepositorySource, "'is_recurring', 'is_active', 'is_public', 'is_popular'"),
    'Only one manually selected popular plan may remain active and cloned plans must not inherit the flag'
);

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
    && str_contains($adminControllerSource, 'Robokassa settings save failed')
    && str_contains($settingsTemplate, 'subscriptions_settings_credentials_ready')
    && str_contains($settingsTemplate, 'subscriptions_payment_mode_live')
    && str_contains($settingsTemplate, 'IsTest='),
    'Robokassa settings writes must be atomic and safely logged'
);
assertTrueValue(
    str_contains((string)file_get_contents(__DIR__ . '/../src/Services/SettingsService.php'), 'UPDATE plugin_settings SET setting_value = ?')
    && str_contains($settingsTemplate, 'subscriptions_secret_required_hint'),
    'Robokassa settings must repair stale duplicate rows and visibly require missing secrets'
);
assertTrueValue(
    str_contains((string)file_get_contents(__DIR__ . '/../src/Services/CheckoutService.php'), 'assertGatewayReady()')
    && str_contains((string)file_get_contents(__DIR__ . '/../src/Controllers/PublicController.php'), 'subscriptions_payment_configuration_error'),
    'Checkout must validate Robokassa before creating an order and hide internal configuration errors from customers'
);
$publicControllerSource = (string)file_get_contents(__DIR__ . '/../src/Controllers/PublicController.php');
assertTrueValue(
    str_contains($publicControllerSource, '$this->redirectToRobokassa')
    && str_contains($publicControllerSource, "'auth.robokassa.ru'")
    && str_contains($publicControllerSource, "header('Location: ' . \$url, true, 303)")
    && !str_contains($publicControllerSource, "response()->redirect((string)\$checkout['url'])"),
    'Checkout must use a narrowly allowlisted external redirect to the official Robokassa host'
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

$subscribersTemplate = (string)file_get_contents(__DIR__ . '/../views/admin/subscribers.php');
assertTrueValue(
    str_contains($subscribersTemplate, "'actions' => \$mobileActions")
    && str_contains($subscribersTemplate, 'ci-more-vertical')
    && str_contains($subscribersTemplate, 'data-subscriptions-subscriber-edit')
    && str_contains($subscribersTemplate, 'id="subscriptionsSubscriberEditModal"')
    && str_contains($subscribersTemplate, "modal.addEventListener('show.bs.modal'")
    && !str_contains($subscribersTemplate, 'subscriptions-inline-editor'),
    'Subscriber actions must use the standard ellipsis menu and edit in a modal without resizing mobile cards'
);

assertTrueValue(
    str_contains($subscribersTemplate, 'subscriptions-grant-panel')
    && str_contains($subscribersTemplate, 'subscriptions-grant-summary')
    && str_contains($subscribersTemplate, 'subscriptions-grant-form')
    && str_contains($subscribersTemplate, "t('subscriptions_grant_hint')")
    && !str_contains($subscribersTemplate, 'class="row g-3 mt-2"'),
    'Manual subscription grants must use the responsive disclosure panel instead of a negative-margin Bootstrap row'
);
assertTrueValue(
    str_contains($subscriptionStyles, '.subscriptions-grant-panel')
    && str_contains($subscriptionStyles, '.subscriptions-grant-form')
    && str_contains($subscriptionStyles, '@keyframes subscriptionsGrantReveal')
    && preg_match('/@media \(max-width: 767\.98px\).*?\.subscriptions-admin\s*\{[^}]*overflow-x:\s*hidden/s', $subscriptionStyles),
    'Manual subscription grants must stay within the mobile viewport while retaining their responsive reveal treatment'
);

$contentTableTemplate = (string)file_get_contents(__DIR__ . '/../views/admin/content.php');
assertTrueValue(
    str_contains($contentTableTemplate, 'subscriptions-content-cards d-md-none')
    && str_contains($contentTableTemplate, 'table-responsive d-none d-md-block')
    && str_contains($contentTableTemplate, 'subscriptions-content-access-form--mobile')
    && str_contains($contentTableTemplate, 'data-subscriptions-content-batch-form')
    && str_contains($contentTableTemplate, 'data-subscriptions-content-layout')
    && str_contains($contentTableTemplate, 'data-subscriptions-content-mode')
    && str_contains($contentTableTemplate, 'data-subscriptions-content-plans')
    && str_contains($contentTableTemplate, 'subscriptions-plan-choice')
    && str_contains($contentTableTemplate, '][subscription_plan_ids][]')
    && str_contains($contentTableTemplate, "plans.hidden = mode.value !== 'plans'")
    && str_contains($contentTableTemplate, "window.matchMedia('(max-width: 767.98px)')")
    && !str_contains($contentTableTemplate, 'name="subscription_plan_ids[]" multiple')
    && substr_count($contentTableTemplate, '$renderAccessFields(') >= 2
    && substr_count($contentTableTemplate, "t('subscriptions_save')") === 1,
    'Content access must use compact cards, contextual plan checkboxes, and one shared save action on desktop and mobile'
);
assertTrueValue(
    str_contains($adminControllerSource, "request()->post('content', null)")
    && str_contains($adminControllerSource, 'foreach ($entries as $postId => $entry)')
    && str_contains($adminControllerSource, '$database->beginTransaction()')
    && str_contains($adminControllerSource, '$database->commit()')
    && str_contains($adminControllerSource, '$database->rollBack()'),
    'Content access batch updates must save every visible entry atomically'
);

$accountTemplate = (string)file_get_contents(__DIR__ . '/../views/public/account.php');
assertTrueValue(
    str_contains($accountTemplate, "renderPartial('admin/partials/table'")
    && str_contains($accountTemplate, "renderPartial('admin/partials/table_footer'")
    && str_contains($accountTemplate, "'mobile_cards' => \$paymentCards")
    && !str_contains($accountTemplate, '<table'),
    'Public payment history must use the template mobile-card table component'
);
assertTrueValue(
    str_contains($publicControllerSource, 'new Pagination($paymentsTotal, $paymentsPerPage)')
    && str_contains($publicControllerSource, 'LIMIT {$paymentsOffset}, {$paymentsPerPage}')
    && str_contains($publicControllerSource, "'payments_pagination' => \$paymentsPagination")
    && !str_contains($publicControllerSource, 'LIMIT 100'),
    'Public payment history must use the standard CMS server-side pagination'
);
assertTrueValue(
    str_contains($accountTemplate, 'subscriptions-account-card')
    && str_contains($accountTemplate, 'subscriptions_payment_status_')
    && str_contains($accountTemplate, 'subscriptions_subscription_status_active')
    && !str_contains($accountTemplate, "htmlSC((string)\$payment['status'])"),
    'Public subscription cards and payment statuses must be localized and use the redesigned account card'
);

$plansTemplate = (string)file_get_contents(__DIR__ . '/../views/public/plans.php');
$checkoutTemplate = (string)file_get_contents(__DIR__ . '/../views/public/checkout.php');
$publicStyles = (string)file_get_contents(__DIR__ . '/../assets/subscriptions.css');
$editorAsset = (string)file_get_contents(__DIR__ . '/../assets/editor.js');
assertTrueValue(
    str_contains($plansTemplate, '$planCount === 1')
    && str_contains($plansTemplate, '$planCount === 2')
    && str_contains($plansTemplate, "!empty(\$plan['is_popular'])")
    && !str_contains($plansTemplate, '$recommendedIndex')
    && str_contains($plansTemplate, 'subscriptions-plan-card--recommended')
    && str_contains($plansTemplate, 'subscriptions_plan_popular')
    && str_contains($plansTemplate, 'subscriptions-plan-card__accent')
    && str_contains($plansTemplate, 'subscriptions_plan_features')
    && str_contains($plansTemplate, "\$planIcons = ['ci-repeat', 'ci-star-filled', 'ci-briefcase']")
    && !str_contains($plansTemplate, 'ci-send')
    && str_contains($plansTemplate, 'ci-arrow-right'),
    'Public plan selection must adapt its card grid to one, two, or many plans'
);
assertTrueValue(
    str_contains($checkoutTemplate, 'subscriptions-checkout-summary')
    && str_contains($checkoutTemplate, 'subscriptions-checkout-panel')
    && str_contains($checkoutTemplate, 'name="consent_offer"')
    && str_contains($checkoutTemplate, 'name="consent_privacy"')
    && str_contains($checkoutTemplate, '/subscriptions/payment/create'),
    'Checkout redesign must preserve payment submission and required consents'
);
assertTrueValue(
    str_contains($publicStyles, '.subscriptions-plan-card__accent')
    && str_contains($publicStyles, '.subscriptions-plans-hero .subscriptions-plans-hero__eyebrow')
    && str_contains($publicStyles, '--cz-badge-color: #c92542')
    && str_contains($publicStyles, '[data-bs-theme="dark"] .subscriptions-plans-hero')
    && str_contains($publicStyles, '.subscriptions-plan-card__popular')
    && str_contains($publicStyles, '.subscriptions-plan-card--recommended')
    && str_contains($publicStyles, '.subscriptions-checkout-summary')
    && str_contains($publicStyles, '@media (max-width: 767.98px)')
    && str_contains($publicStyles, '.subscriptions-content-access-form--mobile')
    && str_contains($publicStyles, '.subscriptions-content-table')
    && str_contains($publicStyles, '.subscriptions-plan-picker__options--compact')
    && str_contains($publicStyles, '.subscriptions-table-search'),
    'Public subscription cards must include responsive styling'
);
assertTrueValue(
    str_contains($pluginSource, "add_filter('fireball_editor_style_assets'")
    && str_contains($publicStyles, '.fb-editor2-inspector-field > .fb-editor2-inspector-check input')
    && str_contains($publicStyles, 'overflow-wrap: anywhere'),
    'Video access plan checkboxes must stay aligned and wrap safely in the editor inspector'
);
assertTrueValue(
    str_contains($pluginSource, "add_filter('fireball_editor_script_assets'")
    && str_contains($editorAsset, "target.hasAttribute('data-editor-video-plan')")
    && str_contains($editorAsset, 'block.data.subscriptionPlanIds = Array.from(values)')
    && str_contains($editorAsset, "plans.hidden = select.value !== 'plans'")
    && str_contains($editorAsset, 'syncPostAccessVisibility')
    && str_contains($editorAsset, "permission.hidden = select.value !== 'permission'"),
    'The plugin update must save selected video plans and hide them for non-plan access without requiring a CMS core update'
);

fwrite(STDOUT, "subscriptions_unit: ok\n");
