<?php

declare(strict_types=1);

// Reuse the isolated SQLite repository harness, never the CMS bootstrap/live database.
require __DIR__ . '/profile_regions_unit.php';
require_once __DIR__ . '/../src/Services/AddressSuggestionService.php';

use Fireball\Subscriptions\Services\AddressSuggestionService;

$checks = 0;
$failures = [];
FireballPluginSubscriptions::$locale = 'ru';

// SQLite's built-in LOWER only handles ASCII; MySQL's UTF-8 LOWER handles Cyrillic.
db()->pdo->sqliteCreateFunction('LOWER', static fn(?string $value): string => mb_strtolower($value ?? '', 'UTF-8'), 1);
db()->pdo->sqliteCreateFunction('CONCAT', static fn(...$values): string => implode('', $values));
db()->pdo->exec('CREATE TABLE subscription_address_catalog (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    region TEXT, city TEXT, street TEXT, house TEXT, postal_code TEXT,
    normalized_region TEXT, normalized_city TEXT, normalized_street TEXT,
    normalized_house TEXT, created_at TEXT
)');
db()->pdo->exec('CREATE TABLE subscription_address_exclusions (
    id INTEGER PRIMARY KEY, is_active INTEGER, normalized_street TEXT,
    normalized_house TEXT, normalized_apartment TEXT
)');

// The production MySQL table DDL is not relevant to query behavior on SQLite.
$schemaReady = new ReflectionProperty(AddressSuggestionService::class, 'schemaReady');
$schemaReady->setValue(null, true);
$service = new AddressSuggestionService();

check(false, $service->configured(), 'An empty local directory and empty profiles are not configured');
check(['rows' => 0, 'cities' => 0, 'streets' => 0], $service->stats(), 'Empty directory statistics');

$normalizeFixture = static function (string $value): string {
    $value = str_replace('ё', 'е', mb_strtolower(trim($value), 'UTF-8'));
    return trim((string)preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value));
};
$addCatalog = static function (string $region, string $city, string $street = '', string $house = '', string $postal = '') use ($normalizeFixture): void {
    db()->query(
        'INSERT INTO subscription_address_catalog
         (region, city, street, house, postal_code, normalized_region, normalized_city, normalized_street, normalized_house, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$region, $city, $street, $house, $postal, ...array_map($normalizeFixture, [$region, $city, $street, $house]), '2026-09-19 12:00:00']
    );
};
$addProfile = static function (int $id, string $region, string $city, string $street, string $house = '', string $postal = ''): void {
    db()->query(
        'INSERT INTO subscription_profiles (id, user_id, country, region, city, street, house, postal_code, email, apartment)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$id, $id, 'Россия', $region, $city, $street, $house, $postal, 'private@example.test', 'private-apartment']
    );
};
$values = static fn(array $items): array => array_column($items, 'value');
$context = ['country' => 'Россия', 'region' => 'Ставропольский край', 'city' => 'Железноводск', 'street' => 'Октябрьская'];

$addCatalog('Ставропольский край', 'Железноводск', 'Октябрьская', '41', '357400');
$addCatalog('Ставропольский край', 'Железноводск', 'Октябрьская', '42', '357401');
$addCatalog('Ставропольский край', 'Железноводск', 'Ёлочная', '8', '357402');
$addCatalog('Ставропольский край', 'Кисловодск', 'Октябрьская', '49', '357700');
$addCatalog('Краснодарский край', 'Железноводск', 'Октябрьская', '45', '350000');
$addCatalog('Красноярский край', 'Железногорск');

check(true, $service->configured(), 'Imported rows enable local suggestions');
check(['rows' => 6, 'cities' => 4, 'streets' => 4], $service->stats(), 'Statistics distinguish the same city in different regions');
check(['Железноводск'], $values($service->suggest('city', '  ЖЕЛЕ  ', $context)), 'City prefixes are trimmed and case normalized');
check(['Железноводск'], $values($service->suggest('city', 'Желе', array_replace($context, ['region' => 'Stavropol Krai']))), 'English region alias resolves before city lookup');
check(['Железногорск'], $values($service->suggest('city', 'Желе', array_replace($context, ['region' => 'Красноярский край']))), 'City search honors the selected region');
check(['Октябрьская'], $values($service->suggest('street', 'окТЯ', $context)), 'Street search deduplicates multiple houses');
check(['Ёлочная'], $values($service->suggest('street', 'ело', $context)), 'Imported street search normalizes yo/e');
check(['Ёлочная'], $values($service->suggest('street', 'ёлО', array_replace($context, ['city' => '  ЖЕЛЕЗНОВОДСК  ']))), 'City context uses normalized components');
check(['41', '42'], $values($service->suggest('house', '4', $context)), 'House search excludes other regions and cities');
check('357400', $service->suggest('house', '41', $context)[0]['postal_code'] ?? null, 'Selected house includes its postal code');
check([], $service->suggest('street', 'Ок', array_replace($context, ['city' => 'Неизвестный город'])), 'Unknown city has no unrelated street results');
check([], $service->suggest('house', '4', array_replace($context, ['street' => 'Ёлочная'])), 'House search honors selected street');
foreach (['country', 'region', 'postal_code', 'invalid', 'CITY'] as $type) {
    check([], $service->suggest($type, 'Желе', $context), 'Unsupported suggestion type is rejected: ' . $type);
}
foreach (['city', 'street'] as $type) {
    check([], $service->suggest($type, 'Ж', $context), 'At least two characters required for ' . $type);
    check([], $service->suggest($type, '  ', $context), 'Empty prefix returns no suggestions for ' . $type);
}
check([], $service->suggest('house', '', $context), 'House prefix requires at least one character');
check([], $service->suggest('street', 'Ок', array_replace($context, ['city' => ''])), 'Street suggestions require city context');
check([], $service->suggest('house', '4', array_replace($context, ['city' => ''])), 'House suggestions require city context');
check([], $service->suggest('house', '4', array_replace($context, ['street' => ''])), 'House suggestions require street context');
check([], $service->suggest('city', 'Же', array_replace($context, ['country' => 'Germany'])), 'A foreign country does not use Russian suggestions');
foreach (['Russia', 'RUS', 'РФ', 'Российская Федерация', ''] as $country) {
    check(['Железноводск'], $values($service->suggest('city', 'Же', array_replace($context, ['country' => $country]))), 'Recognized country alias: ' . $country);
}
check([], $service->suggest('city', "Же' OR 1=1 --", $context), 'SQL-looking input is treated as a search value');

$addCatalog('Ставропольский край', 'Железноводск', 'ул. Ленина', '1', '357400');
$addCatalog('Ставропольский край', 'Железноводск', 'пер. Ленина', '2', '357401');
check(['пер. Ленина', 'ул. Ленина'], $values($service->suggest('street', 'Лени', $context)), 'Postal street prefixes do not hide name-based suggestions');
check(['ул. Ленина'], $values($service->suggest('street', 'ул. Лени', $context)), 'Explicit street type still narrows the lookup');
check(['1'], $values($service->suggest('house', '1', array_replace($context, ['street' => 'ул. Ленина']))), 'House lookup retains the chosen street type');

$addProfile(42, 'Ставропольский край', 'Железноводск', 'Октябрьская', '41', '999999');
$addProfile(43, 'Ставропольский край', 'Железноводск', 'Профильная', '77', '357403');
$addProfile(44, 'Ставропольский край', 'Жемчужный', 'Профильная', '79', '357404');
$addProfile(45, 'Краснодарский край', 'Железноводск', 'Профильная', '78', '350001');
check(['Железноводск', 'Жемчужный'], $values($service->suggest('city', 'Же', $context)), 'Existing profiles supplement imported cities without duplicates');
check(['Профильная'], $values($service->suggest('street', 'пРОФ', $context)), 'Local profiles supplement missing streets');
check(['77'], $values($service->suggest('house', '7', array_replace($context, ['street' => 'Профильная']))), 'Profile fallback respects region, city and street');
check([['value' => '77', 'label' => '77', 'postal_code' => '357403']], $service->suggest('house', '7', array_replace($context, ['street' => 'Профильная'])), 'Suggestions expose no subscriber IDs, email or apartment');
check('357400', $service->suggest('house', '41', $context)[0]['postal_code'] ?? null, 'Imported directory wins over duplicate profile data');

$beforeProfiles = db()->query('SELECT * FROM subscription_profiles ORDER BY id')->get();
$beforeCatalog = db()->query('SELECT * FROM subscription_address_catalog ORDER BY id')->get();
foreach (['city' => 'Же', 'street' => 'Ок', 'house' => '4'] as $type => $prefix) {
    $service->suggest($type, $prefix, $context);
}
check($beforeProfiles, db()->query('SELECT * FROM subscription_profiles ORDER BY id')->get(), 'Looking up addresses never rewrites registered profiles');
check($beforeCatalog, db()->query('SELECT * FROM subscription_address_catalog ORDER BY id')->get(), 'Looking up addresses never changes the catalog');

for ($number = 1; $number <= 15; $number++) {
    $addCatalog('Ставропольский край', 'Тестовый город ' . sprintf('%02d', $number));
}
check(12, count($service->suggest('city', 'Тестовый', $context)), 'Catalog suggestions are limited to twelve');
for ($number = 1; $number <= 15; $number++) {
    $addProfile(100 + $number, 'Ставропольский край', 'Посёлок ' . sprintf('%02d', $number), 'Тестовая');
}
check(12, count($service->suggest('city', 'Пос', $context)), 'Profile fallback is limited to twelve');

// Exercise existing persistence: a local catalog is helpful, not a complete national registry.
$saved = $repository->saveProfile(41, [
    'country' => 'Россия', 'region' => 'Stavropol Krai', 'city' => 'Новый посёлок',
    'street' => 'Новая улица', 'house' => '9А', 'postal_code' => '357499',
]);
foreach (['city' => 'Новый посёлок', 'street' => 'Новая улица', 'house' => '9А', 'postal_code' => '357499'] as $key => $value) {
    check($value, $saved[$key], 'Manual address outside the catalog remains saveable: ' . $key);
}
$saved = $repository->saveProfile(41, ['first_name' => 'Обновлённое имя']);
foreach (['region' => 'Ставропольский край', 'city' => 'Новый посёлок', 'street' => 'Новая улица', 'house' => '9А', 'postal_code' => '357499'] as $key => $value) {
    check($value, $saved[$key], 'Editing another field preserves the saved address: ' . $key);
}
check(['Новый посёлок'], $values($service->suggest('city', 'Новый', $context)), 'New manual entries become available locally');

$mapHeader = new ReflectionMethod($service, 'mapHeader');
check(['region' => 0, 'city' => 1, 'street' => 2, 'house' => 3, 'postal_code' => 4], $mapHeader->invoke($service, ["\xEF\xBB\xBFregion", 'city', 'street', 'house', 'postal_code']), 'CSV header supports a UTF-8 BOM');
check(['region' => 0, 'city' => 1, 'street' => 2, 'house' => 3, 'postal_code' => 4], $mapHeader->invoke($service, ['Край', 'Населённый пункт', 'Улица', 'Дом', 'Индекс']), 'CSV header supports Russian column names');
$detectDelimiter = new ReflectionMethod($service, 'detectDelimiter');
foreach ([',', ';', "\t"] as $separator) {
    check($separator, $detectDelimiter->invoke($service, implode($separator, ['region', 'city', 'street'])), 'Supported local import delimiter ' . json_encode($separator));
}
$countBeforeInvalidImport = $service->stats()['rows'];
try {
    $service->importUploaded(['error' => UPLOAD_ERR_NO_FILE], true);
    check(true, false, 'Missing upload is rejected before replacement');
} catch (InvalidArgumentException) {
    check($countBeforeInvalidImport, $service->stats()['rows'], 'Invalid upload never clears the existing catalog');
}

$beforeClear = db()->query('SELECT * FROM subscription_profiles ORDER BY id')->get();
$service->clear();
check(0, $service->stats()['rows'], 'Clear only removes isolated catalog rows');
check($beforeClear, db()->query('SELECT * FROM subscription_profiles ORDER BY id')->get(), 'Clearing the catalog preserves all subscriber profiles');
check(true, $service->configured(), 'Profile fallback remains available without an imported catalog');
check(['Профильная'], $values($service->suggest('street', 'Проф', $context)), 'Profile suggestions still work after catalog reset');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "Local address suggestion tests passed: {$checks} checks." . PHP_EOL;
