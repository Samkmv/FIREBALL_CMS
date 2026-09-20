<?php

// CLI-only, isolated profile preview. No CMS bootstrap, session storage or database.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../src/Support/RussianRegionCatalog.php';

final class FireballPluginSubscriptions
{
    public static string $locale = 'ru';

    public static function t(string $key): string
    {
        static $translations = [];
        $translations[self::$locale] ??= require __DIR__ . '/../../lang/' . self::$locale . '.php';
        return $translations[self::$locale][$key] ?? $key;
    }
}

function htmlSC(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function base_href(string $path = ''): string { return $path; }
function get_alerts(): void {}
function get_csrf_field(): string { return '<input type="hidden" name="csrf" value="profile-address-test-only">'; }
function session(): object
{
    return new class {
        public function get(string $key, mixed $fallback = null): mixed { return $fallback; }
        public function remove(string $key): void {}
    };
}

$scenario = $argv[1] ?? 'saved';
FireballPluginSubscriptions::$locale = in_array($argv[2] ?? '', ['ru', 'en', 'de', 'zh-cn'], true) ? $argv[2] : 'ru';
$theme = ($argv[3] ?? '') === 'light' ? 'light' : 'dark';
$profile = [
    'first_name' => 'Тестовый пользователь', 'last_name' => 'Пример', 'email' => 'profile@example.test',
    'phone' => '+70000000000', 'country' => 'Россия', 'region' => 'Ставропольский край',
    'city' => 'Железноводск', 'street' => 'Октябрьская', 'house' => '41', 'apartment' => '12',
    'postal_code' => '357400', 'custom_values' => [],
];
if ($scenario === 'empty') {
    foreach (['city', 'street', 'house', 'postal_code'] as $key) $profile[$key] = '';
} elseif ($scenario === 'foreign') {
    $profile = array_replace($profile, [
        'country' => 'Germany', 'region' => 'Bavaria', 'city' => 'Munich', 'street' => 'Hauptstraße',
        'house' => '5', 'postal_code' => '80331',
    ]);
} elseif ($scenario === 'unsafe') {
    foreach (['city', 'street', 'house'] as $key) $profile[$key] = '"><img src=x onerror=alert(1)>';
} elseif ($scenario === 'old') {
    $profile['city'] = 'Ранее сохранённый город';
    $profile['street'] = 'Редкая улица';
    $profile['house'] = '17А/2';
}
$form_data = [];
if ($scenario === 'validation') {
    $form_data = ['city' => 'Введённый город', 'street' => 'Введённая улица', 'house' => '9', 'postal_code' => '123456'];
}
$fields = [];
foreach (['first_name', 'last_name', 'email', 'phone', 'country', 'region', 'city', 'street', 'house', 'apartment', 'postal_code'] as $key) {
    $fields[] = [
        'field_key' => $key, 'field_type' => $key === 'email' ? 'email' : 'text',
        'is_system' => true, 'is_editable' => true, 'is_required' => !in_array($key, ['apartment', 'postal_code'], true),
        'label' => FireballPluginSubscriptions::t('subscriptions_profile_field_' . $key),
        'placeholder' => '', 'description' => '', 'options' => [],
    ];
}
$completion = ['complete' => true, 'percent' => 100, 'missing' => []];
$address_suggest_url = '/profile/subscription-address/suggest';
?>
<!doctype html>
<html lang="<?= htmlSC(FireballPluginSubscriptions::$locale) ?>" data-bs-theme="<?= htmlSC($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscription address preview — isolated fixture</title>
    <link rel="stylesheet" href="/assets/default/vendor/choices.js/choices.min.css">
    <link rel="stylesheet" href="/assets/default/css/theme.min.css">
    <link rel="stylesheet" href="/assets/default/css/style.css">
</head>
<body>
<?php require __DIR__ . '/../../views/public/profile.php'; ?>
<script src="/assets/default/vendor/choices.js/choices.min.js"></script>
<script src="/plugins/subscriptions/assets/profile-region.js"></script>
<script src="/assets/default/js/select-init.js"></script>
</body>
</html>
