<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$repository = (string)file_get_contents($root . '/src/Repositories/ExternalSourceRepository.php');
$service = (string)file_get_contents($root . '/src/Services/ExternalVpnSourceService.php');
$builder = (string)file_get_contents($root . '/src/Services/VpnSubscriptionBuilder.php');
$controller = (string)file_get_contents($root . '/src/Controllers/Admin/SubscriptionController.php');
$routes = (string)file_get_contents($root . '/routes/admin.php');
$view = (string)file_get_contents($root . '/views/admin/subscription-show.php');
$plugin = json_decode((string)file_get_contents($root . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);

$languages = [];
foreach (glob($root . '/lang/*.php') ?: [] as $file) {
    $languages[basename($file, '.php')] = require $file;
}
$assert(count($languages) === 4, 'All four plugin languages must be present.');
$baseKeys = array_keys($languages['ru'] ?? []);
foreach ($languages as $name => $dictionary) {
    $assert(array_keys($dictionary) === $baseKeys, 'Translation parity differs for ' . $name . '.');
    foreach ([
        'vpn_manager_v2_external_order_title',
        'vpn_manager_v2_external_order_help',
        'vpn_manager_v2_external_order_actions',
        'vpn_manager_v2_move_external_up',
        'vpn_manager_v2_move_external_down',
        'vpn_manager_v2_save_external_order',
        'vpn_manager_v2_flash_external_order_saved',
        'vpn_manager_v2_error_external_order_invalid',
    ] as $key) {
        $assert(isset($dictionary[$key]) && trim((string)$dictionary[$key]) !== '',
            'Missing external-order translation ' . $key . ' in ' . $name . '.');
    }
}

$assert(str_contains($builder, 'array_push($uris, ...$external->urisForParent($subscriptionId))'),
    'External configurations are not appended after the existing collection.');
$assert(str_contains($builder, 'if (!array_key_exists($technicalKey, $unique))'),
    'Final technical deduplication does not preserve the first existing configuration.');
$assert(str_contains($repository, 'public function reorder(')
    && str_contains($repository, 'ORDER BY sort_order ASC, id ASC FOR UPDATE')
    && str_contains($repository, 'external_source_order_invalid'),
    'Transactional external-source ordering is incomplete.');
$assert(str_contains($service, 'subscription.external_source_order_updated')
    && str_contains($service, '$this->touch($parentId)'),
    'External-source ordering does not revise and invalidate subscription output.');
$assert(str_contains($controller, 'updateExternalSourceOrder')
    && str_contains($routes, '/external/order/')
    && str_contains($view, 'name="external_source_order[]"')
    && str_contains($view, 'data-vpn-v2-connection-order'),
    'The administrative external-source ordering workflow is incomplete.');
$assert(version_compare((string)($plugin['version'] ?? '0.0.0'), '0.19.0', '>='),
    'The plugin version is older than 0.19.0.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'additive_external_delivery',
        'first_party_duplicate_precedence',
        'transactional_external_order',
        'revision_and_cache_invalidation',
        'admin_external_order_workflow',
        'translation_parity',
        'plugin_version',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
