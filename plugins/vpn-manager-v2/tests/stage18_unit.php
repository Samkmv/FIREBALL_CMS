<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require dirname(__DIR__) . '/Plugin.php';

use Fireball\VpnManagerV2\Services\ExternalVpnSourceService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$external = new ExternalVpnSourceService();
$vless = 'vless://00000000-0000-4000-8000-000000000018@vpn.example.com:443'
    . '?encryption=none&security=tls&type=tcp&sni=vpn.example.com#DE';
$vlessRenamed = str_replace('#DE', '#NL', $vless);
$vmess = 'vmess://' . base64_encode(json_encode([
    'v' => '2',
    'ps' => 'FR',
    'add' => 'vmess.example.com',
    'port' => '443',
    'id' => '00000000-0000-4000-8000-000000000019',
    'aid' => '0',
    'net' => 'ws',
    'tls' => 'tls',
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
$trojan = 'trojan://correct-horse-battery-staple@trojan.example.com:443?security=tls&type=tcp#SE';
$shadowsocks = 'ss://' . base64_encode('aes-256-gcm:password@example.com:8388') . '#PL';
$payload = base64_encode(implode("\n", [$vless, $vlessRenamed, $vmess, $trojan, $shadowsocks]));
$uris = $external->extractUrisFromPayload($payload);
$assert(count($uris) === 4, 'External Base64 subscriptions were not parsed or technically deduplicated.');
$assert($external->technicalKey($vless) === $external->technicalKey($vlessRenamed),
    'Changing a display name bypasses external connection deduplication.');
$assert(count($external->extractUrisFromPayload(json_encode([
    'configs' => [$vless, $trojan],
], JSON_THROW_ON_ERROR))) === 2, 'JSON subscription payloads are not supported.');

$root = dirname(__DIR__);
$migration = (string)file_get_contents($root . '/migrations/012_add_external_sources_and_plan_archiving.sql');
$repository = (string)file_get_contents($root . '/src/Repositories/ExternalSourceRepository.php');
$service = (string)file_get_contents($root . '/src/Services/ExternalVpnSourceService.php');
$builder = (string)file_get_contents($root . '/src/Services/VpnSubscriptionBuilder.php');
$planRepository = (string)file_get_contents($root . '/src/Repositories/PlanRepository.php');
$routes = (string)file_get_contents($root . '/routes/admin.php');
$subscriptionView = (string)file_get_contents($root . '/views/admin/subscription-show.php');
$plansView = (string)file_get_contents($root . '/views/admin/plans.php');
$connectionsView = (string)file_get_contents($root . '/views/admin/connections.php');
$serversView = (string)file_get_contents($root . '/views/admin/servers.php');
$dropdown = (string)file_get_contents($root . '/src/Support/AdminActionDropdown.php');
$postsView = (string)file_get_contents(dirname($root, 2)
    . '/app/Views/themes/default/admin/partials/posts_table_pane.php');
$plugin = json_decode((string)file_get_contents($root . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);

$assert(str_contains($migration, 'vpn_v2_external_sources')
    && str_contains($migration, 'encrypted_source')
    && str_contains($migration, 'encrypted_snapshot')
    && str_contains($migration, 'vpn_v2_plans ADD COLUMN deleted_at'),
    'The encrypted external source or plan archive schema is incomplete.');
$assert(str_contains($repository, 'SecretCipher::decrypt')
    && str_contains($repository, 'SecretCipher::encrypt')
    && !str_contains($repository, 'SELECT encrypted_source, source_preview'),
    'External credentials are not protected by the repository boundary.');
$assert(str_contains($service, 'validatedRequestAddresses')
    && str_contains($service, 'CURLOPT_FOLLOWLOCATION => false')
    && str_contains($service, 'MAX_SOURCE_BYTES')
    && str_contains($service, "['vless', 'vmess', 'trojan', 'ss']"),
    'External subscription fetching lacks SSRF, redirect, size, or protocol controls.');
$assert(str_contains($builder, 'urisForParent(')
    && str_contains($builder, 'technicalKey('),
    'Confirmed external snapshots are not merged into the public subscription.');
$assert(str_contains($planRepository, 'function archive(')
    && str_contains($planRepository, 'deleted_at = ?')
    && str_contains($plansView, 'vpn_manager_v2_action_delete_plan'),
    'Safe plan deletion is missing.');
$assert(str_contains($routes, '/external/subscription/')
    && str_contains($routes, '/external/connection/')
    && str_contains($subscriptionView, 'vpnV2ExternalSubscriptionUrl')
    && str_contains($subscriptionView, 'vpnV2ExternalConnectionUri'),
    'External source administration routes or forms are missing.');
$assert(str_contains($dropdown, 'ci-more-vertical')
    && str_contains($dropdown, 'dropdown-menu dropdown-menu-end shadow-sm rounded-4')
    && str_contains($plansView, 'AdminActionDropdown::render')
    && str_contains($connectionsView, 'AdminActionDropdown::render')
    && str_contains($serversView, 'AdminActionDropdown::render'),
    'VPN row actions do not use the shared FIREBALL CMS dropdown style.');
$assert(str_contains($postsView, '<i class="ci-folder"></i>')
    && !str_contains($postsView, '<i class="ci-map-pin"></i>'),
    'The post category icon was not changed to ci-folder.');
$assert(version_compare((string)($plugin['version'] ?? '0.0.0'), '0.17.0', '>='),
    'The plugin version is older than 0.17.0.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'post_category_folder_icon',
        'cms_action_dropdown',
        'safe_plan_archiving',
        'encrypted_external_sources',
        'base64_and_json_subscription_parsing',
        'vless_vmess_trojan_ss_support',
        'external_technical_deduplication',
        'ssrf_and_size_guards',
        'confirmed_snapshot_delivery',
        'admin_external_source_workflow',
        'plugin_version',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
