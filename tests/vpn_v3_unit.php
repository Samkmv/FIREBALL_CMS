<?php

declare(strict_types=1);

use Fireball\VpnManagerV2\Repositories\ServerRecoveryRepository;
use Fireball\VpnManagerV2\Services\SettingsService;
use Fireball\VpnManagerV2\Services\SubscriptionHwidGatewayService;
use Fireball\VpnManagerV2\Services\VpnSubscriptionEndpointService;
use Fireball\VpnManagerV2\Validators\SettingsValidator;

const VPN_PLUGIN = __DIR__ . '/../plugins/vpn-manager-v2';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Fireball\\VpnManagerV2\\';
    if (str_starts_with($class, $prefix)) {
        require VPN_PLUGIN . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

final class FireballPluginVpnManagerV2
{
    public static function t(string $key): string
    {
        static $messages;
        $messages ??= require VPN_PLUGIN . '/lang/ru.php';

        return $messages[$key] ?? $key;
    }
}

function db(): object
{
    return $GLOBALS['fixtureDb'];
}

function cache(): object
{
    static $cache;
    return $cache ??= new class {
        private array $values = [];

        public function get(string $key): mixed { return $this->values[$key] ?? null; }
        public function set(string $key, mixed $value, int $ttl): void { $this->values[$key] = $value; }
    };
}

$GLOBALS['fixtureDb'] = new class {
    public array $subscription = [];
    public array $nodes = [];
    private array $rows = [];

    public function query(string $sql, array $params = []): self
    {
        $this->rows = match (true) {
            str_contains($sql, 'WHERE subscription_token_hash') => [$this->subscription],
            str_contains($sql, 'FROM vpn_v2_servers WHERE') => [['id' => $params[0], 'panel_url' => 'https://panel.example']],
            str_contains($sql, 'INNER JOIN vpn_v2_servers s ON s.id = n.server_id') => $this->nodes,
            default => [],
        };

        return $this;
    }
    public function get(): array { return $this->rows; }
    public function getOne(): ?array { return $this->rows[0] ?? null; }
    public function getColumn(): int { return 0; }
};

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$defaults = SettingsService::defaults();
$legacy = array_replace($defaults, [
    'smart_connect_enabled' => true,
    'smart_connect_mode' => 'smart',
    'smart_connect_singbox_enabled' => true,
    'happ_server_settings_policy' => 'hide',
]);
$saved = (new SettingsValidator())->validate(
    ['service_name' => 'My VPN', 'smart_connect_happ_provider_id' => ''],
    $legacy,
    manageSmartConnect: false
)->toArray();
check($saved['service_name'] === 'My VPN' && $saved['happ_server_settings_policy'] === 'default', 'Provider ID blocked ordinary settings.');
check($saved['smart_connect_enabled'] && $saved['smart_connect_singbox_enabled'], 'A saved legacy export was disabled.');

$client = new class {
    public array $statuses = [];
    public function probeSubscriptionHwid(string $id, array $headers): int { return $this->statuses[$id]; }
};
$gateway = new SubscriptionHwidGatewayService(static fn(array $server): object => $client);
$token = str_repeat('a', 64);
$subscription = ['id' => 1, 'device_limit' => 1, 'subscription_token' => $token];
$nodes = [['server_id' => 1, 'client_sub_id' => 'a'], ['server_id' => 2, 'client_sub_id' => 'b']];
$headers = ['X-HWID' => 'fixture-device'];
$client->statuses = ['a' => 200, 'b' => 200];
check($gateway->enforce($subscription, $nodes, $headers)['allowed'], 'Healthy panel denied access.');
$client->statuses = ['a' => 503, 'b' => 503];
check($gateway->enforce($subscription, $nodes, $headers)['allowed'], 'Confirmed device lost its subscription during an outage.');
check(!$gateway->enforce($subscription, $nodes, ['X-HWID' => 'unknown-device'])['allowed'], 'Unknown device passed during an outage.');
$gateway->invalidateDevices(1, 'a');
$partial = $gateway->enforce($subscription, $nodes, $headers);
check(($partial['verified_targets'] ?? []) === ['2:b'], 'Revoked device got access to an offline target.');
$client->statuses['a'] = 404;
check($gateway->enforce($subscription, $nodes, $headers)['status'] === 404, 'Explicit denial was ignored.');

$fixtureDb = db();
$fixtureDb->subscription = $subscription + [
    'status' => 'active', 'revision' => 1, 'expires_at' => '2000-01-01 00:00:00',
];
$endpoint = new VpnSubscriptionEndpointService();
$expired = $endpoint->respond($token, 'base64');
check($expired->status === 200 && $expired->headers['X-Fireball-VPN-Status'] === 'expired', 'Expired subscription did not refresh.');
check(str_contains(rawurldecode(base64_decode($expired->body)), 'Подписка истекла'), 'Expired placeholder was not labeled.');

$fixtureDb->subscription['expires_at'] = null;
$fixtureDb->nodes = [[
    'node_id' => 42, 'server_id' => 1, 'client_sub_id' => 'a', 'maintenance_mode' => 1,
    'protocol' => 'vless', 'network' => 'tcp', 'security' => 'none', 'port' => 443,
    'stream_settings_json' => '{"network":"tcp","security":"none"}',
    'client_uuid' => '70cc48c5-b2f4-4fc7-a4dd-123456789abc',
    'panel_url' => 'https://maintenance.example',
]];
$maintenance = $endpoint->respond($token, 'plain');
check($maintenance->status === 200 && $maintenance->headers['X-Fireball-VPN-Status'] === 'maintenance', 'All-maintenance subscription did not refresh.');
check(!str_contains($maintenance->body, 'maintenance.example'), 'Maintenance connection leaked into the subscription.');
$fixtureDb->subscription['device_limit'] = 0;
$fixtureDb->nodes[] = array_replace($fixtureDb->nodes[0], [
    'node_id' => 43, 'server_id' => 2, 'maintenance_mode' => 0, 'panel_url' => 'https://healthy.example',
]);
$mixed = $endpoint->respond($token, 'plain');
check($mixed->status === 200 && isset($mixed->headers['announce']), 'Maintenance notice missing for a mixed subscription.');
check(str_contains($mixed->body, 'healthy.example') && !str_contains($mixed->body, 'maintenance.example'), 'Maintenance filtered the wrong connections.');

$topology = new ServerRecoveryRepository();
$old = ['id' => 1, 'protocol' => 'vless', 'port' => 443, 'network' => 'tcp', 'security' => 'reality'];
$new = array_replace($old, ['id' => 2, 'status' => 'active', 'is_enabled' => 1]);
check($topology->suggestMapping([$old], [$new]) === [1 => 2], 'Unique replacement target was not mapped.');
check($topology->suggestMapping([$old], [$new, array_replace($new, ['id' => 3])]) === null, 'Ambiguous replacement was applied automatically.');

echo "VPN 3.0 regression checks passed\n";
