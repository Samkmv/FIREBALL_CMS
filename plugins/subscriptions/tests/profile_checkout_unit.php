<?php

declare(strict_types=1);

// Execute the repository's real INSERT/UPDATE/SELECT queries against an isolated database.
final class ProfileTestDb
{
    public readonly PDO $pdo;

    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE subscription_profile_fields (
            id INTEGER PRIMARY KEY AUTOINCREMENT, field_key TEXT UNIQUE, label TEXT,
            description TEXT, placeholder TEXT, field_type TEXT, is_required INTEGER,
            is_active INTEGER, is_system INTEGER, is_editable INTEGER,
            show_during_checkout INTEGER, use_in_receipt INTEGER, validation_rules TEXT,
            options_json TEXT, plan_ids_json TEXT, sort_order INTEGER,
            created_at TEXT, updated_at TEXT
        )');
    }

    public function query(string $sql, array $params = []): object
    {
        // SQLite has no row locks; production's FOR UPDATE is irrelevant in this isolated test DB.
        $sql = preg_replace('/\s+FOR UPDATE\s*$/i', '', $sql);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return new class($statement) {
            public function __construct(private readonly PDOStatement $statement) {}
            public function get(): array { return $this->statement->fetchAll(PDO::FETCH_ASSOC); }
            public function getOne(): array|false { return $this->statement->fetch(PDO::FETCH_ASSOC); }
        };
    }

    public function getInsertId(): int
    {
        return (int)$this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool { return $this->pdo->beginTransaction(); }
    public function commit(): bool { return $this->pdo->commit(); }
    public function rollBack(): bool { return $this->pdo->rollBack(); }
    public function inTransaction(): bool { return $this->pdo->inTransaction(); }
}

function db(): ProfileTestDb
{
    static $db = new ProfileTestDb();
    return $db;
}

final class FireballPluginSubscriptions
{
    public static string $locale = 'ru';

    public static function t(string $key): string
    {
        $translations = require __DIR__ . '/../lang/' . self::$locale . '.php';
        return $translations[$key] ?? $key;
    }
}

require_once __DIR__ . '/../src/Repositories/ProfileRepository.php';
require_once __DIR__ . '/../src/Support/RussianRegionCatalog.php';

$checks = 0;
$failures = [];
function check(mixed $expected, mixed $actual, string $message): void
{
    global $checks, $failures;
    $checks++;
    if ($expected !== $actual) {
        $failures[] = $message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true);
    }
}

$repository = new Fireball\Subscriptions\Repositories\ProfileRepository();
$data = [
    'field_key' => 'email', 'label' => 'Email', 'field_type' => 'email',
    'is_required' => '1', 'is_active' => '1', 'is_editable' => '1',
    'show_during_checkout' => '1', 'use_in_receipt' => '1', 'sort_order' => 40,
];
$id = $repository->saveField($data);
db()->query('UPDATE subscription_profile_fields SET is_system = 1 WHERE id = ?', [$id]);
check(FireballPluginSubscriptions::t('subscriptions_profile_field_email'), $repository->fields()[0]['label'], 'Seeded system labels remain localized');
$repository->saveField(array_replace($data, ['label' => $repository->fields()[0]['label']]), $id);
FireballPluginSubscriptions::$locale = 'de';
check(FireballPluginSubscriptions::t('subscriptions_profile_field_email'), $repository->fields()[0]['label'], 'Saving a default label preserves localization');
FireballPluginSubscriptions::$locale = 'ru';

$edited = array_replace($data, [
    'label' => 'Электронная почта для квитанций', 'description' => 'Куда отправить чек',
    'placeholder' => 'mail@example.test', 'sort_order' => 7,
]);
$repository->saveField($edited, $id);
$saved = $repository->fields()[0];
foreach (['label', 'description', 'placeholder', 'sort_order'] as $key) {
    check($edited[$key], $saved[$key], 'Saved system field ' . $key . ' survives reload');
}
FireballPluginSubscriptions::$locale = 'en';
check($edited['label'], $repository->fields()[0]['label'], 'Custom label is not replaced on locale switch');
FireballPluginSubscriptions::$locale = 'ru';

$flags = ['is_required', 'is_active', 'is_editable', 'show_during_checkout', 'use_in_receipt'];
$unchecked = array_diff_key($edited, array_flip($flags));
$repository->saveField($unchecked, $id);
$saved = $repository->fields()[0];
foreach ($flags as $flag) {
    check(false, $saved[$flag], 'Unchecked system field flag stays off: ' . $flag);
}
check([], $repository->fields(true), 'Inactive field is absent from the public form');
check(true, $repository->completion([])['complete'], 'No active required fields means complete');

$repository->saveField($edited, $id);
foreach ($flags as $flag) {
    check(true, $repository->fields()[0][$flag], 'System field flag can be re-enabled: ' . $flag);
}
check(false, $repository->completion(['email' => ''])['complete'], 'Active required field is still validated');
check([$edited['label']], $repository->completion([])['missing'], 'Missing-field message uses the saved label');
check(true, $repository->completion(['email' => 'user@example.test'])['complete'], 'Filled required field completes the profile');
$repository->saveField(array_replace($edited, ['is_required' => 0]), $id);
check(true, $repository->completion([])['complete'], 'Active optional field does not block checkout');

$repository->saveField(array_replace($edited, ['field_key' => 'changed', 'field_type' => 'text']), $id);
check('email', $repository->fields()[0]['field_key'], 'System key cannot be changed by a forged request');
check('email', $repository->fields()[0]['field_type'], 'System type cannot be changed by a forged request');

$custom = [
    'field_key' => 'entrance', 'label' => 'Подъезд', 'field_type' => 'select',
    'is_active' => 1, 'is_editable' => 1, 'options' => "Первый\nВторой",
    'plan_ids' => ['4', '9', '4'], 'validation_rules' => 'max:20', 'sort_order' => 80,
];
$customId = $repository->saveField($custom);
$repository->saveField(array_replace($custom, ['options' => "Первый\nТретий", 'plan_ids' => ['9']]), $customId);
$saved = $repository->fields()[1];
check(['Первый', 'Третий'], $saved['options'], 'Custom options survive editing and reload');
check([9], $saved['plan_ids'], 'Plan selection survives editing and reload');
check('max:20', $saved['validation_rules'], 'Validation rules survive editing');
check(false, $saved['is_system'], 'New fields cannot become system fields');

function htmlSC(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function base_href(string $path): string { return $path; }
function get_alerts(): void {}
function get_csrf_field(): string { return '<input type="hidden" name="csrf_token" value="test-token">'; }

function renderCheckout(bool $recurring): DOMXPath
{
    $plan = [
        'id' => 9, 'name' => 'Тариф', 'description' => '', 'price_display' => '990 RUB',
        'duration_value' => 1, 'duration_unit' => 'months', 'permissions' => [],
        'auto_renew_enabled' => $recurring,
    ];
    $profile = array_fill_keys(Fireball\Subscriptions\Repositories\ProfileRepository::SYSTEM_FIELDS, '');
    ob_start();
    require __DIR__ . '/../views/public/checkout.php';
    $html = (string)ob_get_clean();
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return new DOMXPath($document);
}

foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    FireballPluginSubscriptions::$locale = $locale;
    foreach ([false, true] as $recurring) {
        $xpath = renderCheckout($recurring);
        $context = $locale . ($recurring ? ' recurring' : ' one-off');
        $links = $xpath->query('//form[@action="/subscriptions/payment/create"]//div[contains(@class,"subscriptions-checkout-consents")]//a');
        check(1, $links->length, $context . ': offer link is beside payment consents');
        $link = $links->item(0);
        check('https://docs.robokassa.ru/media/1550/%D0%BE%D1%84%D0%B5%D1%80%D1%82%D0%B0-itv.pdf', $link?->getAttribute('href'), $context . ': official payer offer URL');
        check(FireballPluginSubscriptions::t('subscriptions_robokassa_public_offer'), $link?->textContent, $context . ': localized offer label');
        check(1, $xpath->query('//label[input[@name="consent_offer"]]/following-sibling::*[1][self::p]/a')->length, $context . ': offer link is directly below its consent checkbox');
        check(true, str_ends_with($link?->textContent ?? '', $locale === 'zh-cn' ? '（PDF）' : '(PDF)'), $context . ': link suffix contains only PDF');
        check('_blank', $link?->getAttribute('target'), $context . ': payment form stays open');
        check('noopener noreferrer', $link?->getAttribute('rel'), $context . ': safe new tab');
        foreach (['consent_offer', 'consent_privacy', ...($recurring ? ['consent_recurring'] : [])] as $name) {
            check(1, $xpath->query('//input[@name="' . $name . '" and @type="checkbox" and @required and not(@checked)]')->length, $context . ': explicit consent preserved for ' . $name);
        }
        check($recurring ? 1 : 0, $xpath->query('//input[@name="consent_recurring"]')->length, $context . ': recurring mandate only for recurring tariffs');
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "Profile/checkout tests passed: {$checks} checks." . PHP_EOL;
