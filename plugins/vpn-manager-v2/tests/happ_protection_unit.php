<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// No DB, remote panel, Happ service or device is contacted.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Fireball\\VpnManagerV2\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
final class FireballPluginVpnManagerV2 {
    public static function t(string $key): string { return $key; }
}
use Fireball\VpnManagerV2\Services\SettingsService;
use Fireball\VpnManagerV2\Services\VpnSubscriptionMetadataService;
use Fireball\VpnManagerV2\Validators\SettingsValidator;
use Fireball\VpnManagerV2\Exceptions\ValidationException;
$cases = [];
$assert = static function (bool $condition, string $case) use (&$cases): void {
    if (!$condition) { throw new RuntimeException($case); }
    $cases[] = $case;
};
$defaults = SettingsService::defaults();
$validator = new SettingsValidator();
$metadata = new VpnSubscriptionMetadataService();
$assert($defaults['happ_server_settings_policy'] === 'default'
    && !isset($metadata->headers([], $defaults)['hide-settings']), 'existing_clients_unchanged');
foreach (['default' => null, 'hide' => '1', 'show' => '0'] as $policy => $header) {
    $settings = $validator->validate(array_replace($defaults, ['happ_server_settings_policy' => $policy]), $defaults)->toArray();
    $assert($settings['happ_server_settings_policy'] === $policy
        && $validator->validateStored($settings)->toArray() === $settings, 'dto_roundtrip_' . $policy);
    $headers = $metadata->headers([], $settings);
    $assert(($headers['hide-settings'] ?? null) === $header && !isset($headers['providerid']), 'documented_header_without_provider_' . $policy);
    $assert(!$settings['smart_connect_enabled'] && !isset($headers['subscription-autoconnect']), 'independent_of_smart_connect_' . $policy);
}
foreach (['1', 'fake', "hide\r\nInjected: yes", [], true] as $index => $bad) {
    try { $validator->validate(array_replace($defaults, ['happ_server_settings_policy' => $bad]), $defaults); $rejected = false; }
    catch (ValidationException) { $rejected = true; }
    $assert($rejected, 'reject_policy_' . $index);
    $stored = $validator->validateStored(array_replace($defaults, ['happ_server_settings_policy' => $bad]))->toArray();
    $assert($stored['happ_server_settings_policy'] === 'default' && !isset($metadata->headers([], $stored)['hide-settings']), 'bad_stored_policy_safe_' . $index);
}
$hidden = array_replace($defaults, ['happ_server_settings_policy' => 'hide', 'happ_routing_enabled' => true,
    'happ_routing_link' => (new \Fireball\VpnManagerV2\Support\HappRoutingProfile())->normalize('{"Name":"Fixture","DirectSites":["example.com"]}')]);
$assert(isset($metadata->headers([], $hidden)['routing']) && $metadata->headers([], $hidden)['hide-settings'] === '1', 'routing_preserved');
foreach (['ru', 'en', 'de', 'zh-cn'] as $language) {
    $strings = require dirname(__DIR__) . '/lang/' . $language . '.php';
    $keys = array_filter(array_keys($strings), static fn(string $key): bool => str_starts_with($key, 'vpn_manager_v2_happ_protection_'));
    $assert(count($keys) === 8 && count(array_filter(array_intersect_key($strings, array_flip($keys)), static fn($value): bool => !is_string($value) || $value === '')) === 0,
        'translations_' . $language);
}
echo json_encode(['status' => 'ok', 'count' => count($cases), 'cases' => $cases]), PHP_EOL;
