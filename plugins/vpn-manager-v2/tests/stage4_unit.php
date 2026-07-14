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
use Fireball\VpnManagerV2\Services\VpnFlowResolver;
use Fireball\VpnManagerV2\Support\TrafficFormatter;
use Fireball\VpnManagerV2\Validators\PlanValidator;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$assertThrows = static function (callable $callback, string $expectedMessage) use ($assert): void {
    try {
        $callback();
    } catch (ValidationException $exception) {
        $assert($exception->getMessage() === $expectedMessage, 'Unexpected validation message: ' . $exception->getMessage());
        return;
    }

    throw new RuntimeException('Expected ValidationException was not thrown.');
};

$topology = [
    101 => [
        'id' => 101,
        'server_id' => 11,
        'protocol' => 'vless',
        'network' => 'tcp',
        'security' => 'reality',
        'status' => 'active',
        'is_enabled' => 1,
        'server_is_enabled' => 1,
    ],
    102 => [
        'id' => 102,
        'server_id' => 12,
        'protocol' => 'vless',
        'network' => 'xhttp',
        'security' => 'reality',
        'status' => 'active',
        'is_enabled' => 1,
        'server_is_enabled' => 1,
    ],
    103 => [
        'id' => 103,
        'server_id' => 13,
        'protocol' => 'vless',
        'network' => 'tcp',
        'security' => 'reality',
        'status' => 'active',
        'is_enabled' => 1,
        'server_is_enabled' => 0,
    ],
    104 => [
        'id' => 104,
        'server_id' => 14,
        'protocol' => 'vless',
        'network' => 'tcp',
        'security' => 'reality',
        'status' => 'disabled',
        'is_enabled' => 0,
        'server_is_enabled' => 1,
    ],
];

$base = [
    'name' => 'Stage 4 test plan',
    'description' => 'Validator fixture',
    'duration_days' => 30,
    'traffic_limit_value' => '100',
    'traffic_unit' => 'gb',
    'device_limit' => 3,
    'is_active' => 1,
];
$validator = new PlanValidator();

$single = $validator->validate($base + [
    'nodes' => [[
        'server_id' => 11,
        'inbound_id' => 101,
        'flow_override' => VpnFlowResolver::VISION,
        'sort_order' => 0,
    ]],
], $topology);
$assert(count($single->nodes) === 1, 'Single-server plan did not retain one link.');
$assert($single->nodes[0]->flowOverride === VpnFlowResolver::VISION, 'Vision override was not retained.');
$assert($single->trafficLimitBytes === 100 * (1024 ** 3), 'Traffic limit conversion failed.');
$assert(TrafficFormatter::limit(100) === '100 B', 'Byte traffic formatting lost trailing zeroes.');
$assert(TrafficFormatter::limit(100 * (1024 ** 3)) === '100 GB', 'Plan traffic formatting failed.');

$multi = $validator->validate($base + [
    'nodes' => [
        ['server_id' => 11, 'inbound_id' => 101, 'flow_override' => '__auto__', 'sort_order' => 10],
        ['server_id' => 12, 'inbound_id' => 102, 'flow_override' => '__none__', 'sort_order' => 20],
    ],
], $topology);
$assert(count($multi->nodes) === 2, 'Multi-server plan did not retain both links.');
$assert($multi->nodes[0]->flowOverride === null, 'Automatic Flow must be stored as NULL.');
$assert($multi->nodes[1]->flowOverride === '', 'No Flow must be stored as an empty override.');

$edited = $validator->validate(array_replace($base, [
    'name' => 'Stage 4 edited plan',
    'duration_days' => 45,
    'nodes' => [[
        'server_id' => 11,
        'inbound_id' => 101,
        'flow_override' => VpnFlowResolver::VISION,
        'sort_order' => 5,
    ]],
]), $topology);
$assert($edited->name === 'Stage 4 edited plan' && count($edited->nodes) === 1, 'Repeated edit validation failed.');

$assertThrows(static fn() => $validator->validate($base + [
    'nodes' => [[
        'server_id' => 12,
        'inbound_id' => 102,
        'flow_override' => VpnFlowResolver::VISION,
        'sort_order' => 0,
    ]],
], $topology), 'vpn_manager_v2_error_plan_flow_incompatible');

$assertThrows(static fn() => $validator->validate($base + [
    'nodes' => [
        ['server_id' => 11, 'inbound_id' => 101, 'flow_override' => '__auto__', 'sort_order' => 0],
        ['server_id' => 11, 'inbound_id' => 101, 'flow_override' => '__none__', 'sort_order' => 1],
    ],
], $topology), 'vpn_manager_v2_error_plan_node_duplicate');

$assertThrows(static fn() => $validator->validate($base + [
    'nodes' => [[
        'server_id' => 13,
        'inbound_id' => 103,
        'flow_override' => '__auto__',
        'sort_order' => 0,
    ]],
], $topology), 'vpn_manager_v2_error_plan_server_disabled');

$assertThrows(static fn() => $validator->validate($base + [
    'nodes' => [[
        'server_id' => 14,
        'inbound_id' => 104,
        'flow_override' => '__auto__',
        'sort_order' => 0,
    ]],
], $topology), 'vpn_manager_v2_error_plan_inbound_disabled');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'single_server_plan',
        'multi_server_plan',
        'tcp_reality_vision',
        'xhttp_no_flow',
        'repeated_edit_validation',
        'duplicate_link_rejected',
        'disabled_server_rejected',
        'disabled_inbound_rejected',
        'incompatible_flow_rejected',
        'traffic_limit_formatting',
    ],
], JSON_UNESCAPED_SLASHES), PHP_EOL;
