<?php

declare(strict_types=1);

require __DIR__ . '/profile_checkout_unit.php';
require_once __DIR__ . '/../src/Support/AddressNormalizer.php';
require_once __DIR__ . '/../src/Support/AddressMatcher.php';
require_once __DIR__ . '/../src/Repositories/AddressExclusionRepository.php';
require_once __DIR__ . '/../src/Services/SubscriptionEligibilityService.php';
require_once __DIR__ . '/../src/Services/SubscriptionService.php';

use Fireball\Subscriptions\Support\RussianRegionCatalog;

$checks = 0;
$failures = [];
FireballPluginSubscriptions::$locale = 'ru';
$catalog = new RussianRegionCatalog();
foreach ($catalog->entries() as $entry) {
    foreach ([$entry['name'], ...$entry['aliases']] as $alias) {
        check($entry['name'], $catalog->resolve($alias), 'Directory alias: ' . $alias);
    }
}
foreach ([
    '  STAVROPOL   KRAI  ' => 'Ставропольский край',
    'Stavropolskiy kray' => 'Ставропольский край',
    'Stavropol Territory' => 'Ставропольский край',
    'обл. Московская' => 'Московская область',
    'Moscow Region' => 'Московская область',
    'Moscow' => 'Москва',
    'Leningrad Oblast' => 'Ленинградская область',
    'St. Petersburg' => 'Санкт-Петербург',
    'Krasnodar Krai' => 'Краснодарский край',
    'Krasnoyarsk Krai' => 'Красноярский край',
    'Орёловская' => null,
    'Орёл область' => 'Орловская область',
    'Altai' => null,
    'Mosc' => null,
    'Not a real region' => null,
    '<script>alert(1)</script>' => null,
] as $input => $expected) {
    check($expected, $catalog->resolve($input), 'Region normalization: ' . $input);
}
foreach (['', 'Россия', ' РФ ', 'Russia', 'Russian Federation', 'ru', 'RUS', 'Российская   Федерация'] as $country) {
    check(true, $catalog->appliesToCountry($country), 'Russian country alias: ' . $country);
}
check(false, $catalog->appliesToCountry('Belarus'), 'Other country does not use Russian directory');
check('Bavaria', $catalog->normalize(' Bavaria ', 'Germany'), 'Foreign regions are preserved');
check('', $catalog->normalize('', 'Russia'), 'Optional empty region stays empty');

// Real persistence path, including post-save eligibility checks, with no provider/network calls.
db()->query('DELETE FROM subscription_profile_fields');
$regionFieldId = $repository->saveField([
    'field_key' => 'region', 'label' => 'Region', 'field_type' => 'text',
    'is_required' => 1, 'is_active' => 1, 'is_editable' => 1,
]);
db()->query('UPDATE subscription_profile_fields SET is_system = 1 WHERE id = ?', [$regionFieldId]);
db()->pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY); INSERT INTO users (id) VALUES (41)');
db()->pdo->exec('CREATE TABLE subscriptions (id INTEGER, user_id INTEGER, utility_managed INTEGER, archived_at TEXT)');
$columns = implode(', ', array_map(static fn(string $key): string => $key . " TEXT DEFAULT ''", $repository::SYSTEM_FIELDS));
db()->pdo->exec('CREATE TABLE subscription_profiles (id INTEGER PRIMARY KEY, user_id INTEGER, ' . $columns . ', updated_at TEXT, data_completed_at TEXT, address_excluded INTEGER, matched_address_exclusion_id INTEGER, address_checked_at TEXT)');
db()->pdo->exec('CREATE TABLE subscription_profile_values (profile_id INTEGER, field_id INTEGER, field_value TEXT)');
db()->query('INSERT INTO subscription_profiles (id, user_id, country, region) VALUES (?, ?, ?, ?)', [11, 41, 'Russia', 'Stavropol Krai']);
check('Stavropol Krai', $repository->profileForUser(41, false)['region'], 'Reading a legacy profile does not rewrite it');
check('Ставропольский край', $repository->snapshot(41)['address']['region'], 'New order snapshot gets Russian region');
check('Stavropol Krai', $repository->profileForUser(41, false)['region'], 'Snapshot does not mutate the profile');
foreach (['Stavropol Krai' => 'Ставропольский край', 'Moscow Region' => 'Московская область'] as $input => $expected) {
    $saved = $repository->saveProfile(41, ['region' => $input]);
    check($expected, $saved['region'], 'English region is saved as Russian');
    check($expected, $repository->profileForUser(41, false)['region'], 'Russian region survives a fresh database read');
}
$before = $repository->profileForUser(41, false);
try {
    $repository->saveProfile(41, ['region' => 'Typo region', 'first_name' => 'Should not be saved']);
    check(true, false, 'Invalid direct POST must be rejected');
} catch (InvalidArgumentException $exception) {
    check(FireballPluginSubscriptions::t('subscriptions_error_region_select'), $exception->getMessage(), 'Invalid region has a useful error');
}
check($before, $repository->profileForUser(41, false), 'Rejected input cannot partially overwrite the profile');
db()->query('UPDATE subscription_profiles SET region = ? WHERE id = ?', ['Misspelled region', 11]);
check(false, $repository->completion($repository->profileForUser(41, false))['complete'], 'Invalid legacy region requires correction before checkout');
try {
    $repository->snapshot(41);
    check(true, false, 'Invalid legacy region must not enter a new order');
} catch (RuntimeException $exception) {
    check(FireballPluginSubscriptions::t('subscriptions_error_profile_incomplete'), $exception->getMessage(), 'Snapshot guards direct checkout');
}
$saved = $repository->saveProfile(41, ['country' => 'Germany', 'region' => 'Bavaria']);
check('Bavaria', $saved['region'], 'Existing non-Russian address workflow is retained');

function regionMarkup(string $country, string $value): string
{
    $old = static fn(string $key): string => $key === 'country' ? $country : '';
    $fieldValue = $value;
    $field = ['label' => 'Область / край', 'placeholder' => '', 'is_required' => true];
    ob_start();
    require __DIR__ . '/../views/public/region-field.php';
    return (string)ob_get_clean();
}

foreach (['ru', 'en', 'de', 'zh-cn'] as $locale) {
    FireballPluginSubscriptions::$locale = $locale;
    foreach (['Russia' => 'Stavropol Krai', 'Germany' => 'Bavaria'] as $country => $value) {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . regionMarkup($country, $value));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $isRussian = $country === 'Russia';
        check(1, $xpath->query('//*[@name="region" and not(@disabled)]')->length, $locale . ': exactly one submitted region field');
        check($isRussian ? 1 : 0, $xpath->query('//select[@name="region" and not(@disabled)]')->length, $locale . ': country-aware selection');
        if ($isRussian) {
            check('Ставропольский край', $xpath->query('//option[@selected]')->item(0)?->getAttribute('value'), $locale . ': legacy English name shown in Russian');
        }
        $select = $xpath->query('//select')->item(0);
        $config = json_decode($select->getAttribute('data-select'), true, flags: JSON_THROW_ON_ERROR);
        check(true, $config['searchEnabled'], $locale . ': search is enabled');
        check(['label', 'customProperties.aliases'], $config['searchFields'], $locale . ': searches both Russian and English');
        $option = $xpath->query('//option[@value="Ставропольский край"]')->item(0);
        check(true, str_contains($option->getAttribute('data-custom-properties'), 'Stavropol Krai'), $locale . ': English aliases reach the UI');
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}
echo "Profile region tests passed: {$checks} checks." . PHP_EOL;
