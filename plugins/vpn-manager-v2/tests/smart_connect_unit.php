<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';
ini_set('session.save_path', sys_get_temp_dir());
new FBL\Application(false);
require dirname(__DIR__) . '/Plugin.php';
FBL\Language::registerPluginLanguage('vpn-manager-v2', dirname(__DIR__) . '/lang');

use Fireball\VpnManagerV2\Services\SettingsService;
use Fireball\VpnManagerV2\Validators\SettingsValidator;
use Fireball\VpnManagerV2\Validators\SmartConnectValidator;
use Fireball\VpnManagerV2\Support\HappSmartConnect;
use Fireball\VpnManagerV2\Services\SingBoxSubscriptionBuilder;
use Fireball\VpnManagerV2\Services\ServerHealthMonitorService;
use Fireball\VpnManagerV2\Services\VpnSubscriptionMetadataService;
use Fireball\VpnManagerV2\Services\VpnSubscriptionCache;
use Fireball\VpnManagerV2\Services\VpnSubscriptionEndpointService;

$cases = [];
$assert = static function (bool $condition, string $case) use (&$cases): void {
    if (!$condition) { throw new RuntimeException($case); }
    $cases[] = $case;
};
$defaults = SettingsService::defaults();
$validator = new SettingsValidator();
$stored = $validator->validateStored($defaults)->toArray();
$assert($stored === $defaults, 'defaults_dto_roundtrip');
$assert(!$defaults['smart_connect_enabled'] && !$defaults['smart_connect_happ_enabled']
    && !$defaults['smart_connect_singbox_enabled'] && !$defaults['smart_connect_health_enabled'], 'off_by_default');
$smart = array_replace($defaults, ['smart_connect_enabled' => true, 'smart_connect_happ_enabled' => true,
    'smart_connect_happ_provider_id' => 'fixture-provider', 'smart_connect_singbox_enabled' => true,
    'smart_connect_server_priorities' => [2 => 5, 1 => 10]]);
$saved = $validator->validate($smart, $defaults)->toArray();
$assert($saved['smart_connect_server_priorities'] === [1 => 10, 2 => 5]
    && $validator->validateStored($saved)->toArray() === $saved, 'typed_settings_roundtrip');
foreach ([['smart_connect_mode' => 'fake'], ['smart_connect_happ_ping_type' => 'icmp'],
    ['smart_connect_happ_provider_id' => "fixture\r\nInjected: yes"], ['smart_connect_interval_seconds' => 1],
    ['smart_connect_tolerance_ms' => 0], ['smart_connect_test_url' => 'http://public.test/check'],
    ['smart_connect_test_url' => 'https://127.0.0.1/check'], ['smart_connect_test_url' => 'https://u:p@example.com/check'],
    ['smart_connect_server_priorities' => [1 => -1]], ['smart_connect_happ_provider_id' => ''],
    ['smart_connect_happ_ping_on_open' => false]] as $index => $bad) {
    try { (new SmartConnectValidator())->validate(array_replace($smart, $bad)); $rejected = false; }
    catch (\Fireball\VpnManagerV2\Exceptions\ValidationException) { $rejected = true; }
    $assert($rejected, 'reject_invalid_setting_' . $index);
}
$happ = new HappSmartConnect();
$assert($happ->headers($defaults) === [] && $happ->headers(array_replace($smart, ['smart_connect_mode' => 'manual'])) === [], 'manual_no_headers');
$headers = $happ->headers($smart);
$assert($headers === ['providerid' => 'fixture-provider', 'subscription-ping-onopen-enabled' => '1',
    'subscription-autoconnect' => '1', 'subscriptions-sort-type' => 'ping', 'ping-type' => 'proxy',
    'check-url-via-proxy' => 'https://www.gstatic.com/generate_204', 'subscription-autoconnect-type' => 'lowestdelay'], 'documented_happ_headers');
$assert($happ->headers(array_replace($smart, ['smart_connect_mode' => 'failover'])) === $headers, 'happ_no_fake_failover');
$assert($happ->headers($smart, false)['subscription-autoconnect'] === '0'
    && !isset($happ->headers($smart, false)['subscription-autoconnect-type']), 'inactive_no_autoconnect');
$assert($happ->headers(array_replace($smart, ['smart_connect_happ_provider_id' => ''])) === [], 'invalid_stored_provider_safe');
$profile = ['Name' => 'Fixture', 'DirectSites' => ['example.com']];
$routing = array_replace($smart, ['happ_routing_enabled' => true,
    'happ_routing_link' => (new \Fireball\VpnManagerV2\Support\HappRoutingProfile())->normalize(json_encode($profile))]);
$meta = (new VpnSubscriptionMetadataService())->headers([], $routing);
$assert(isset($meta['routing'], $meta['providerid']) && $meta['ping-type'] === 'proxy', 'routing_preserved');
$uuid = '81111111-1111-4111-8111-111111111111';
$uris = ["vless://$uuid@first.example:443?type=tcp&security=none#First",
    'trojan://secret%3Apassword@second.example:443?type=ws&security=tls&sni=second.example&host=cdn.example&path=%2Fproxy#Second'];
$builder = new SingBoxSubscriptionBuilder();
$json = json_decode($builder->build($uris, $smart), true, 64, JSON_THROW_ON_ERROR);
$assert($json['outbounds'][0]['type'] === 'urltest' && $json['route']['final'] === 'auto'
    && $json['outbounds'][0]['tolerance'] === 100 && $json['outbounds'][0]['interval'] === '180s'
    && !$json['outbounds'][0]['interrupt_exist_connections'], 'urltest_hysteresis_interval');
$assert($json['outbounds'][1]['uuid'] === $uuid && $json['outbounds'][2]['password'] === 'secret:password'
    && $json['outbounds'][2]['transport']['headers']['Host'] === 'cdn.example', 'credentials_tls_ws_preserved');
$failover = json_decode($builder->build($uris, array_replace($smart, ['smart_connect_mode' => 'failover'])), true);
$assert($failover['outbounds'][0]['interrupt_exist_connections'], 'live_connection_interrupt_enabled');
$manual = json_decode($builder->build($uris, array_replace($smart, ['smart_connect_mode' => 'manual'])), true);
$assert($manual['outbounds'][0]['type'] === 'selector' && !isset($manual['outbounds'][0]['interval']), 'json_manual_selector');
$prioritized = json_decode($builder->build($uris, $smart, [$uris[1] => 0]), true);
$assert($prioritized['outbounds'][1]['server'] === 'second.example' && $uris[0] === "vless://$uuid@first.example:443?type=tcp&security=none#First", 'priority_does_not_mutate_manual_order');
$vmess = 'vmess://' . base64_encode(json_encode(['id' => $uuid, 'add' => 'vmess.example', 'port' => '443',
    'aid' => '0', 'scy' => 'auto', 'net' => 'grpc', 'path' => 'service', 'tls' => 'tls', 'sni' => 'vmess.example']));
$assert($builder->outbound($vmess)['transport']['service_name'] === 'service', 'vmess_grpc');
$reality = "vless://$uuid@reality.example:443?type=tcp&security=reality&sni=cover.example&fp=chrome&pbk=J3ZzzEkAl3VzYBuHo8aW0xMoNWuLAwgRFI5rA-ZTPBQ&sid=abcd&flow=xtls-rprx-vision";
$assert($builder->outbound($reality)['tls']['reality']['short_id'] === 'abcd', 'reality_vision_preserved');
$ss = 'ss://' . rtrim(strtr(base64_encode('aes-128-gcm:external-password'), '+/', '-_'), '=') . '@ss.example:443';
$assert($builder->outbound($ss)['password'] === 'external-password', 'external_shadowsocks');
foreach (["vless://$uuid@x.example:443?type=xhttp&security=tls", $uris[0] . '&unknown=1',
    "vless://$uuid@x.example:443?type=tcp&pqv=unsupported", 'ss://broken@x.example:443?plugin=v2ray-plugin',
    "vless://$uuid@x.example:443?type=tcp&security=none&flow=xtls-rprx-vision",
    "vless://$uuid@x.example:443?type=ws&security=tls&flow=xtls-rprx-vision",
    "vless://$uuid@x.example:443?type=tcp&security=tls&fp=nonexistent",
    "vless://$uuid@x.example:443?type=tcp&security=reality&pbk=broken",
    "vless://$uuid@x.example:443?type=tcp&security=tls&allowInsecure=maybe",
    'vmess://' . base64_encode(json_encode(['id' => $uuid, 'add' => 'vmess.example', 'port' => '443oops'])),
    'vmess://' . base64_encode(json_encode(['id' => $uuid, 'add' => 'vmess.example', 'port' => 443, 'aid' => -1]))] as $index => $bad) {
    // Put query extensions before the fragment.
    if ($index === 1) { $bad = str_replace('#First', '&unknown=1#First', $uris[0]); }
    try { $builder->outbound($bad); $rejected = false; }
    catch (\Fireball\VpnManagerV2\Exceptions\VpnConfigValidationException $e) {
        $rejected = !str_contains($e->getMessage(), $uuid) && !str_contains($e->getMessage(), 'secret');
    }
    $assert($rejected, 'unsupported_export_no_silent_loss_' . $index);
}
$inactive = json_decode($builder->inactive(), true);
try { $builder->build(['trojan://password%FF@second.example:443'], $smart); $rejected = false; }
catch (\Fireball\VpnManagerV2\Exceptions\VpnConfigValidationException) { $rejected = true; }
$assert($rejected, 'invalid_utf8_export_safe_error');
$assert($inactive['outbounds'] === [] && $inactive['route']['rules'] === [['action' => 'reject']], 'inactive_json_blocks_traffic');
$cache = new VpnSubscriptionCache();
$assert($cache->key(str_repeat('a', 64), 1, 'singbox') !== $cache->key(str_repeat('a', 64), 1, 'base64'), 'cache_format_isolation');
$endpoint = new VpnSubscriptionEndpointService();
$assert($endpoint->etag(str_repeat('a', 64), 1, 'singbox') !== $endpoint->etag(str_repeat('a', 64), 1, 'plain'), 'etag_format_isolation');
$monitor = new ServerHealthMonitorService(static fn(): array => ['xray' => ['state' => 'running'], 'cpu' => 35], static fn(): bool => true);
$server = ['panel_url' => 'https://server.example'];
$nodes = [['id' => 1, 'port' => 443, 'network' => 'ws']];
$healthy = $monitor->inspect($server, $nodes);
$assert($healthy['state'] === 'healthy' && $healthy['scope'] === 'infrastructure'
    && $healthy['ports'][0]['tunnel'] === 'unverified', 'global_not_user_health');
$failed = (new ServerHealthMonitorService(static fn() => throw new RuntimeException('secret-token'), static fn(): bool => false))->inspect($server, $nodes);
$assert($failed['state'] === 'unavailable' && $failed['error'] === 'panel_probe_failed'
    && !str_contains(json_encode($failed), 'secret-token'), 'unavailable_sanitized');
$assert($monitor->inspect($server, $nodes)['state'] === 'healthy' && $monitor->retryDelay(600, 0) === 600
    && $monitor->retryDelay(600, 3) === 2400 && $monitor->retryDelay(600, 10) === 3600, 'recovery_bounded_backoff');
$assert($monitor->inspect($server, [['id' => 2, 'port' => 443, 'network' => 'kcp']])['ports'][0]['tcp'] === 'unknown', 'unsupported_transport_not_false_positive');
$assert($monitor->runDue($defaults) === ['checked' => 0, 'disabled' => true], 'disabled_health_no_io');
$languages = [];
foreach (['ru', 'en', 'de', 'zh-cn'] as $lang) {
    $languages[$lang] = require dirname(__DIR__) . '/lang/' . $lang . '.php';
    $keys = array_filter(array_keys($languages[$lang]), static fn(string $key): bool => str_starts_with($key, 'vpn_manager_v2_smart_'));
    $assert(count($keys) >= 40 && count(array_filter(array_intersect_key($languages[$lang], array_flip($keys)), static fn($v): bool => !is_string($v) || $v === '')) === 0, 'translations_' . $lang);
}
$assert(array_diff_key($languages['ru'], $languages['en']) === [] && array_diff_key($languages['ru'], $languages['de']) === []
    && array_diff_key($languages['ru'], $languages['zh-cn']) === [], 'language_key_parity');
if (isset($argv[1]) && $argv[1] === '--export-fixtures') {
    $dir = $argv[2] ?? sys_get_temp_dir();
    foreach (['smart' => $json, 'failover' => $failover, 'manual' => $manual, 'inactive' => $inactive,
        'vmess' => json_decode($builder->build([$vmess], $smart), true),
        'reality' => json_decode($builder->build([$reality], $smart), true),
        'external' => json_decode($builder->build([$ss], $smart), true)] as $name => $fixture) {
        file_put_contents($dir . '/smart-' . $name . '.json', json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
echo json_encode(['status' => 'ok', 'count' => count($cases), 'cases' => $cases], JSON_UNESCAPED_SLASHES), PHP_EOL;
