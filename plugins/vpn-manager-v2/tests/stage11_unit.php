<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';

if (!function_exists('return_translation')) {
    function return_translation(string $key): string
    {
        return $key;
    }
}

require dirname(__DIR__) . '/Plugin.php';

use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Services\CountryFlagService;
use Fireball\VpnManagerV2\Services\SettingsService;
use Fireball\VpnManagerV2\Services\VpnServerNameRenderer;
use Fireball\VpnManagerV2\Validators\SettingsValidator;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$flags = new CountryFlagService();
$assert($flags->normalize('de') === 'DE' && $flags->emoji('DE') === '🇩🇪', 'ISO alpha-2 flag failed.');
$assert($flags->emoji('ZZ') === '' && $flags->emoji('DEU') === '', 'Non-ISO flag was accepted.');
$assert($flags->forServer('DE', true, true) === '🇩🇪'
    && $flags->forServer('DE', false, true) === ''
    && $flags->forServer('DE', true, false) === '', 'Global/server flag policy failed.');

$defaults = SettingsService::defaults();
$validator = new SettingsValidator();
$data = $validator->validate(array_replace($defaults, [
    'service_name' => 'Fireball Secure',
    'server_name_template' => '{flag} {service} {country} {country_code} {city} {server} {protocol}',
    'global_show_flags' => '0',
]), $defaults)->toArray();
$assert($data['service_name'] === 'Fireball Secure' && $data['global_show_flags'] === false,
    'Service name or checkbox=false normalization failed.');

$renderer = new VpnServerNameRenderer($flags);
$node = [
    'country_code' => 'DE',
    'country_name' => 'Germany',
    'city' => 'Berlin',
    'server_name' => 'Node One',
    'server_code' => 'node-one',
    'protocol' => 'vless',
    'show_flag' => 1,
    'client_uuid' => '11111111-2222-4333-8444-555555555555',
    'client_email' => 'technical@example.invalid',
    'client_sub_id' => 'technical-sub-id',
];
$withFlag = $renderer->render($node, array_replace($data, ['global_show_flags' => true]));
$withoutGlobalFlag = $renderer->render($node, array_replace($data, ['global_show_flags' => false]));
$withoutServerFlag = $renderer->render(array_replace($node, ['show_flag' => 0]), array_replace($data, ['global_show_flags' => true]));
$assert(str_contains($withFlag, '🇩🇪') && str_contains($withFlag, 'Fireball Secure')
    && str_contains($withFlag, 'Germany') && str_contains($withFlag, 'DE')
    && str_contains($withFlag, 'Berlin') && str_contains($withFlag, 'Node One')
    && str_contains($withFlag, 'VLESS'), 'Template variables were not rendered.');
$assert(!str_contains($withoutGlobalFlag, '🇩🇪') && !str_contains($withoutServerFlag, '🇩🇪'),
    'Flag policy leaked into a disabled display name.');
foreach (['client_uuid', 'client_email', 'client_sub_id'] as $identity) {
    $assert(!str_contains($withFlag, (string)$node[$identity]), 'Technical identity leaked into the display name.');
}

$invalidTemplate = false;
try {
    $validator->validate(array_replace($defaults, ['server_name_template' => '{unknown}']), $defaults);
} catch (ValidationException) {
    $invalidTemplate = true;
}
$assert($invalidTemplate, 'Unsupported template variable was accepted.');

$invalidCache = false;
try {
    $validator->validate(array_replace($defaults, ['subscription_cache_ttl_seconds' => 5]), $defaults);
} catch (ValidationException) {
    $invalidCache = true;
}
$assert($invalidCache, 'Unsafe cache TTL was accepted.');

$safeFields = SettingsService::safeFieldNames([
    'service_name' => 'Safe',
    'future_secret' => '',
    'api_token' => '',
    'password' => '',
]);
$assert($safeFields === ['service_name'], 'Secret fields were included in safe logging metadata.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'iso_alpha_2_flag',
        'global_and_server_flags',
        'all_template_variables',
        'checkbox_false',
        'identities_excluded',
        'invalid_template_rejected',
        'cache_bounds',
        'secret_field_redaction',
    ],
], JSON_UNESCAPED_SLASHES), PHP_EOL;
