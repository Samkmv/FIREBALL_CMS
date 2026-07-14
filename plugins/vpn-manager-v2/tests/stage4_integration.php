<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

new FBL\Application();
require dirname(__DIR__) . '/Plugin.php';
FBL\Language::registerPluginLanguage('vpn-manager-v2', dirname(__DIR__) . '/lang');

use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Repositories\PlanRepository;
use Fireball\VpnManagerV2\Services\PlanManagerService;
use Fireball\VpnManagerV2\Services\VpnFlowResolver;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$expectValidation = static function (callable $callback) use ($assert): string {
    try {
        $callback();
    } catch (ValidationException $exception) {
        $assert(trim($exception->getMessage()) !== '', 'Validation exception must have a safe message.');
        return $exception->getMessage();
    }

    throw new RuntimeException('Expected ValidationException was not thrown.');
};

$suffix = substr(hash('sha256', uniqid('vpn-v2-stage4-', true)), 0, 12);
$serverIds = [];
$inboundIds = [];
$planIds = [];
$results = [];
$now = date('Y-m-d H:i:s');

try {
    foreach (['a', 'b'] as $letter) {
        db()->query(
            'INSERT INTO vpn_v2_servers
                (name, code, panel_url, panel_path, auth_type, show_flag, status, is_enabled, created_at, updated_at)
             VALUES (?, ?, ?, NULL, ?, 0, ?, 1, ?, ?)',
            [
                'Stage 4 fixture ' . strtoupper($letter),
                'stage4-' . $letter . '-' . $suffix,
                'https://stage4-' . $letter . '.invalid',
                'token',
                'online',
                $now,
                $now,
            ]
        );
        $serverIds[$letter] = (int)db()->getInsertId();
    }

    $fixtureInbounds = [
        'tcp' => [
            'server_id' => $serverIds['a'],
            'remote_id' => '400001',
            'name' => 'Stage 4 TCP Reality',
            'port' => 14443,
            'network' => 'tcp',
            'security' => 'reality',
            'default_flow' => VpnFlowResolver::VISION,
            'stream' => ['network' => 'tcp', 'security' => 'reality', 'tcpSettings' => [], 'realitySettings' => []],
        ],
        'xhttp' => [
            'server_id' => $serverIds['b'],
            'remote_id' => '400002',
            'name' => 'Stage 4 XHTTP Reality',
            'port' => 24443,
            'network' => 'xhttp',
            'security' => 'reality',
            'default_flow' => null,
            'stream' => ['network' => 'xhttp', 'security' => 'reality', 'xhttpSettings' => [], 'realitySettings' => []],
        ],
    ];
    foreach ($fixtureInbounds as $key => $fixture) {
        db()->query(
            'INSERT INTO vpn_v2_inbounds
                (server_id, remote_inbound_id, name, protocol, port, network, security, default_flow,
                 settings_json, stream_settings_json, status, is_enabled, synced_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
            [
                $fixture['server_id'],
                $fixture['remote_id'],
                $fixture['name'],
                'vless',
                $fixture['port'],
                $fixture['network'],
                $fixture['security'],
                $fixture['default_flow'],
                '{}',
                json_encode($fixture['stream'], JSON_UNESCAPED_SLASHES),
                'active',
                $now,
                $now,
                $now,
            ]
        );
        $inboundIds[$key] = (int)db()->getInsertId();
    }

    $formHtml = (new Fireball\VpnManagerV2\Controllers\Admin\PlanController())->create();
    $optionFor = static function (int $inboundId) use ($formHtml): string {
        $pattern = '#<option value="' . preg_quote((string)$inboundId, '#') . '".*?</option>#s';
        if (!preg_match($pattern, $formHtml, $matches)) {
            throw new RuntimeException('Inbound option was not rendered.');
        }

        return (string)$matches[0];
    };
    $tcpOption = $optionFor($inboundIds['tcp']);
    $xhttpOption = $optionFor($inboundIds['xhttp']);
    $assert(str_contains($tcpOption, VpnFlowResolver::VISION), 'TCP Reality UI does not offer Vision.');
    $assert(!str_contains($xhttpOption, VpnFlowResolver::VISION), 'XHTTP UI unexpectedly offers Vision.');
    $results['flow_options_from_resolver'] = true;

    $manager = new PlanManagerService();
    $repository = new PlanRepository();
    $base = [
        'description' => 'Stage 4 integration fixture',
        'duration_days' => 30,
        'traffic_limit_value' => 100,
        'traffic_unit' => 'gb',
        'device_limit' => 3,
        'is_active' => 1,
    ];

    $singleId = $manager->create($base + [
        'name' => 'Stage 4 single ' . $suffix,
        'nodes' => [[
            'server_id' => $serverIds['a'],
            'inbound_id' => $inboundIds['tcp'],
            'flow_override' => VpnFlowResolver::VISION,
            'sort_order' => 0,
        ]],
    ]);
    $planIds[] = $singleId;
    $singleNodes = $repository->nodes($singleId);
    $assert(count($singleNodes) === 1, 'Single-server plan must contain one link.');
    $assert((string)$singleNodes[0]['flow_override'] === VpnFlowResolver::VISION, 'TCP Reality Vision override was not stored.');
    $results['single_server'] = true;
    $results['tcp_reality_vision'] = true;

    $multiId = $manager->create($base + [
        'name' => 'Stage 4 multi ' . $suffix,
        'nodes' => [
            [
                'server_id' => $serverIds['a'],
                'inbound_id' => $inboundIds['tcp'],
                'flow_override' => '__auto__',
                'sort_order' => 10,
            ],
            [
                'server_id' => $serverIds['b'],
                'inbound_id' => $inboundIds['xhttp'],
                'flow_override' => '__none__',
                'sort_order' => 20,
            ],
        ],
    ]);
    $planIds[] = $multiId;
    $multiNodes = $repository->nodes($multiId);
    $assert(count($multiNodes) === 2, 'Multi-server plan must contain two links.');
    $assert(count(array_unique(array_column($multiNodes, 'server_id'))) === 2, 'Multi-server plan lost one server.');
    $xhttpNode = array_values(array_filter($multiNodes, static fn(array $node): bool => (int)$node['inbound_id'] === $inboundIds['xhttp']))[0] ?? null;
    $assert(is_array($xhttpNode) && (string)$xhttpNode['flow_override'] === '', 'XHTTP No Flow override was not stored distinctly.');
    $results['multiple_servers'] = true;
    $results['xhttp_without_vision'] = true;

    $incompatibleMessage = $expectValidation(static fn() => $manager->create($base + [
        'name' => 'Stage 4 incompatible ' . $suffix,
        'nodes' => [[
            'server_id' => $serverIds['b'],
            'inbound_id' => $inboundIds['xhttp'],
            'flow_override' => VpnFlowResolver::VISION,
            'sort_order' => 0,
        ]],
    ]));
    $assert($incompatibleMessage === FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_flow_incompatible'), 'Wrong incompatible Flow message.');
    $results['incompatible_flow_rejected'] = true;

    $duplicateMessage = $expectValidation(static fn() => $manager->create($base + [
        'name' => 'Stage 4 duplicate ' . $suffix,
        'nodes' => [
            ['server_id' => $serverIds['a'], 'inbound_id' => $inboundIds['tcp'], 'flow_override' => '__auto__', 'sort_order' => 0],
            ['server_id' => $serverIds['a'], 'inbound_id' => $inboundIds['tcp'], 'flow_override' => '__none__', 'sort_order' => 1],
        ],
    ]));
    $assert($duplicateMessage === FireballPluginVpnManagerV2::t('vpn_manager_v2_error_plan_node_duplicate'), 'Wrong duplicate link message.');
    $results['duplicate_rejected'] = true;

    db()->query('UPDATE vpn_v2_servers SET is_enabled = 0 WHERE id = ?', [$serverIds['b']]);
    $expectValidation(static fn() => $manager->create($base + [
        'name' => 'Stage 4 disabled server ' . $suffix,
        'nodes' => [[
            'server_id' => $serverIds['b'],
            'inbound_id' => $inboundIds['xhttp'],
            'flow_override' => '__none__',
            'sort_order' => 0,
        ]],
    ]));
    db()->query('UPDATE vpn_v2_servers SET is_enabled = 1 WHERE id = ?', [$serverIds['b']]);
    $results['disabled_server_rejected'] = true;

    db()->query("UPDATE vpn_v2_inbounds SET is_enabled = 0, status = 'disabled' WHERE id = ?", [$inboundIds['xhttp']]);
    $expectValidation(static fn() => $manager->create($base + [
        'name' => 'Stage 4 disabled inbound ' . $suffix,
        'nodes' => [[
            'server_id' => $serverIds['b'],
            'inbound_id' => $inboundIds['xhttp'],
            'flow_override' => '__none__',
            'sort_order' => 0,
        ]],
    ]));
    db()->query("UPDATE vpn_v2_inbounds SET is_enabled = 1, status = 'active' WHERE id = ?", [$inboundIds['xhttp']]);
    $results['disabled_inbound_rejected'] = true;

    $editedInput = $base + [
        'name' => 'Stage 4 edited ' . $suffix,
        'nodes' => [[
            'server_id' => $serverIds['a'],
            'inbound_id' => $inboundIds['tcp'],
            'flow_override' => VpnFlowResolver::VISION,
            'sort_order' => 5,
        ]],
    ];
    $editedInput['duration_days'] = 45;
    $manager->update($singleId, $editedInput);
    $manager->update($singleId, $editedInput);
    $edited = $repository->find($singleId);
    $assert((int)$edited['duration_days'] === 45 && count($repository->nodes($singleId)) === 1, 'Repeated edit did not remain idempotent.');
    $results['repeated_edit'] = true;

    $manager->update($multiId, $base + [
        'name' => 'Stage 4 multi reduced ' . $suffix,
        'nodes' => [[
            'server_id' => $serverIds['a'],
            'inbound_id' => $inboundIds['tcp'],
            'flow_override' => '__auto__',
            'sort_order' => 10,
        ]],
    ]);
    $assert(count($repository->nodes($multiId)) === 1, 'Removing one plan link failed.');
    $serverCount = (int)db()->query(
        'SELECT COUNT(*) FROM vpn_v2_servers WHERE id IN (?, ?)',
        [$serverIds['a'], $serverIds['b']]
    )->getColumn();
    $inboundCount = (int)db()->query(
        'SELECT COUNT(*) FROM vpn_v2_inbounds WHERE id IN (?, ?)',
        [$inboundIds['tcp'], $inboundIds['xhttp']]
    )->getColumn();
    $assert($serverCount === 2 && $inboundCount === 2, 'Removing a plan link deleted a server or inbound.');
    $results['link_removed_without_target_delete'] = true;

    $disabled = $manager->toggle($singleId);
    db()->query('UPDATE vpn_v2_servers SET is_enabled = 0 WHERE id = ?', [$serverIds['a']]);
    $expectValidation(static fn() => $manager->toggle($singleId));
    db()->query('UPDATE vpn_v2_servers SET is_enabled = 1 WHERE id = ?', [$serverIds['a']]);
    db()->query("UPDATE vpn_v2_inbounds SET is_enabled = 0, status = 'disabled' WHERE id = ?", [$inboundIds['tcp']]);
    $expectValidation(static fn() => $manager->toggle($singleId));
    db()->query("UPDATE vpn_v2_inbounds SET is_enabled = 1, status = 'active' WHERE id = ?", [$inboundIds['tcp']]);
    $assert(empty($repository->find($singleId)['is_active']), 'Rejected enable unexpectedly activated the plan.');
    $enabled = $manager->toggle($singleId);
    $assert($disabled === false && $enabled === true, 'Plan enable/disable toggle failed.');
    $results['toggle'] = true;
    $results['enable_with_disabled_topology_rejected'] = true;

    $index = db()->query(
        'SHOW INDEX FROM vpn_v2_plan_nodes WHERE Key_name = ?',
        ['uq_vpn_v2_plan_nodes_target']
    )->get() ?: [];
    $assert(array_column($index, 'Column_name') === ['plan_id', 'server_id', 'inbound_id'], 'Plan node unique index is invalid.');
    $results['unique_index'] = true;
} finally {
    foreach ($planIds as $planId) {
        db()->query('DELETE FROM vpn_v2_plans WHERE id = ?', [$planId]);
        db()->query(
            "DELETE FROM vpn_v2_events WHERE event_type LIKE 'plan.%' AND context_json LIKE ?",
            ['%"plan_id":' . $planId . ',%']
        );
    }
    foreach ($inboundIds as $inboundId) {
        db()->query('DELETE FROM vpn_v2_inbounds WHERE id = ?', [$inboundId]);
    }
    foreach ($serverIds as $serverId) {
        db()->query('DELETE FROM vpn_v2_servers WHERE id = ?', [$serverId]);
    }
}

$remainingFixtures = (int)db()->query(
    'SELECT COUNT(*) FROM vpn_v2_servers WHERE code LIKE ?',
    ['stage4-%-' . $suffix]
)->getColumn();
$remainingPlans = (int)db()->query(
    'SELECT COUNT(*) FROM vpn_v2_plans WHERE name LIKE ?',
    ['Stage 4 %' . $suffix]
)->getColumn();
$remainingEvents = 0;
foreach ($planIds as $planId) {
    $remainingEvents += (int)db()->query(
        "SELECT COUNT(*) FROM vpn_v2_events WHERE event_type LIKE 'plan.%' AND context_json LIKE ?",
        ['%"plan_id":' . $planId . ',%']
    )->getColumn();
}
$assert($remainingFixtures === 0 && $remainingPlans === 0 && $remainingEvents === 0, 'Stage 4 fixtures were not fully cleaned.');

echo json_encode([
    'status' => 'ok',
    'results' => $results,
    'fixtures_cleaned' => true,
], JSON_UNESCAPED_SLASHES), PHP_EOL;
