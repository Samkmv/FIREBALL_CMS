<?php

declare(strict_types=1);

use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Services\XrayAutoConfigService;
use Fireball\VpnManagerV2\Support\XrayAutoSettings;

spl_autoload_register(static function (string $class): void {
    $prefix = 'Fireball\\VpnManagerV2\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__) . '/plugins/vpn-manager-v2/src/'
            . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});
final class FireballPluginVpnManagerV2
{
    public static function t(string $key): string { return $key; }
}

$check = static function (bool $ok, string $message): void {
    if (!$ok) {
        throw new RuntimeException($message);
    }
};
$settings = XrayAutoSettings::defaults();
$builder = new XrayAutoConfigService();
$uris = [];
for ($i = 1; $i <= 3; $i++) {
    $key = rtrim(strtr(base64_encode(str_repeat(chr($i), 32)), '+/', '-_'), '=');
    $uris[] = sprintf('vless://00000000-0000-4000-8000-%012d@node%d.example:%d?security=reality&type=raw'
        . '&encryption=none&flow=xtls-rprx-vision&fp=chrome&sni=cover%d.example&pbk=%s&sid=0%d&spx=%%2F#Fixture',
        $i, $i, 443 + $i, $i, $key, $i);
}
$export = static function (string $strategy) use ($builder, $settings, $uris): array {
    return $builder->build($uris, array_replace($settings, ['strategy' => $strategy]), '⚡ Автовыбор');
};
if (in_array($argv[1] ?? '', ['leastPing', 'leastLoad', 'blocked'], true)) {
    $fixture = $argv[1] === 'blocked' ? $builder->build([], $settings, 'Unavailable') : $export($argv[1]);
    echo json_encode($fixture['profile'], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
    return;
}
$result = $export('leastPing');
$profile = $result['profile'];
$check($result['count'] === 3 && $result['skipped'] === 0 && $profile['observatory']['probeInterval'] === '30s', 'Default export');
$check(!isset($profile['routing']['observatory']) && $profile['routing']['balancers'][0]['fallbackTag'] === 'vpn-block', 'Fail-closed balance');
$check(array_column($profile['outbounds'], 'protocol') === ['blackhole', 'vless', 'vless', 'vless', 'dns'], 'No direct fallback');
$check($profile['dns']['servers'] === ['https://1.1.1.1/dns-query'] && $profile['routing']['rules'][0]['balancerTag'] === 'vpn-auto', 'DoH is routed into VPN');
$check($profile['inbounds'][0]['protocol'] === 'socks' && $profile['inbounds'][0]['listen'] === '127.0.0.1'
    && $profile['inbounds'][0]['settings']['udp'], 'Local SOCKS with UDP');
for ($i = 1; $i <= 3; $i++) {
    $node = $profile['outbounds'][$i];
    $remote = $node['settings']['vnext'][0];
    $reality = $node['streamSettings']['realitySettings'];
    $check($remote['address'] === "node{$i}.example" && $remote['port'] === 443 + $i
        && $remote['users'][0]['id'] === sprintf('00000000-0000-4000-8000-%012d', $i)
        && $remote['users'][0]['flow'] === 'xtls-rprx-vision'
        && $reality['shortId'] === '0' . $i && $reality['serverName'] === "cover{$i}.example"
        && $reality['publicKey'] === rtrim(strtr(base64_encode(str_repeat(chr($i), 32)), '+/', '-_'), '=')
        && $reality['spiderX'] === '/', 'Each outbound retains its own parameters');
}
$load = $export('leastLoad')['profile'];
$check(!isset($load['observatory']) && $load['burstObservatory']['pingConfig']['timeout'] === '5s'
    && $load['burstObservatory']['pingConfig']['sampling'] === 3, 'leastLoad uses only burstObservatory');
$mixed = $builder->build([$uris[0], str_replace('type=raw', 'type=xhttp', $uris[1]), 'ss://invalid',
    str_replace('fp=chrome', 'fp[]=chrome', $uris[2])], array_replace($settings, ['fallback' => 'first']), 'fixture');
$check($mixed['count'] === 1 && $mixed['skipped'] === 3
    && $mixed['profile']['routing']['balancers'][0]['fallbackTag'] === 'vpn-node-1', 'One node and unsupported transports');
$empty = $builder->build([], $settings, 'Unavailable');
$check($empty['count'] === 0 && array_column($empty['profile']['outbounds'], 'protocol') === ['blackhole'], 'Empty profile has no credentials');
$more = array_merge($uris, [str_replace(':444?', ':1444?', $uris[0]), str_replace(':445?', ':1445?', $uris[1])]);
$check($builder->build($more, $settings, 'Fixture')['count'] === 5, 'The export must not cap the number of peers at three.');
$check(!XrayAutoSettings::normalize(['enabled' => true, 'servers' => ['invalid']])['enabled'], 'Invalid stored allowlist must not broaden access');
try {
    XrayAutoSettings::normalize(['probe_url' => 'http://example.com/probe'], true);
    throw new RuntimeException('Unsafe probe URL accepted');
} catch (ValidationException) {
}

function db(): object { return $GLOBALS['xrayTestDb']; }
function cache(): object
{
    static $cache;
    return $cache ??= new class {
        private array $values = [];
        public function get(string $key): mixed { return $this->values[$key] ?? null; }
        public function set(string $key, mixed $value, int $ttl): void { $this->values[$key] = $value; }
    };
}
$fixtureDb = $GLOBALS['xrayTestDb'] = new class {
    public array $subscription = [];
    public array $nodes = [];
    private array $rows = [];
    public function query(string $sql, array $params = []): self
    {
        $this->rows = match (true) {
            str_contains($sql, 'WHERE subscription_token_hash') => [$this->subscription],
            str_contains($sql, 'INNER JOIN vpn_v2_servers s ON s.id = n.server_id') => $this->nodes,
            default => [],
        };
        return $this;
    }
    public function get(): array { return $this->rows; }
    public function getOne(): ?array { return $this->rows[0] ?? null; }
    public function getColumn(): mixed { return $this->rows[0] ?? null; }
};
$token = str_repeat('a', 64);
$fixtureDb->subscription = ['id' => 1, 'status' => 'active', 'subscription_token' => $token,
    'expires_at' => null, 'device_limit' => 0, 'revision' => 1];
$key = rtrim(strtr(base64_encode(str_repeat('A', 32)), '+/', '-_'), '=');
$fixtureDb->nodes = [[
    'node_id' => 1, 'server_id' => 2, 'panel_url' => 'https://healthy.example', 'protocol' => 'vless',
    'network' => 'tcp', 'security' => 'reality', 'port' => 443,
    'client_uuid' => '70cc48c5-b2f4-4fc7-a4dd-123456789abc',
    'stream_settings_json' => json_encode(['network' => 'tcp', 'security' => 'reality',
        'realitySettings' => ['serverNames' => ['cover.example'], 'shortIds' => ['01'], 'settings' => ['publicKey' => $key]]]),
]];
$endpoint = new \Fireball\VpnManagerV2\Services\VpnSubscriptionEndpointService();
$legacySettings = \Fireball\VpnManagerV2\Services\SettingsService::defaults();
$validator = new \Fireball\VpnManagerV2\Validators\SettingsValidator();
$custom = $validator->validate(['xray_auto' => ['enabled' => '1', 'interval' => '45', 'servers' => ['8']]], $legacySettings)->toArray();
$check($custom['xray_auto']['enabled'] && $custom['xray_auto']['interval'] === 45
    && $custom['xray_auto']['servers'] === [8], 'Xray form values are persisted as typed settings.');
$check($validator->validate([], $custom)->toArray()['xray_auto'] === $custom['xray_auto'],
    'An older settings form must preserve Xray settings.');
$check($endpoint->respond($token, 'xray')->status === 404, 'Xray export is disabled by default.');
$xraySettings = array_replace($legacySettings, ['xray_auto' => ['enabled' => true]]);
cache()->set('vpn-v2:settings:v1', $xraySettings, 300);
$xray = $endpoint->respond($token, 'xray', '*');
$xrayProfile = json_decode($xray->body, true, 512, JSON_THROW_ON_ERROR)[0];
$check($xray->status === 200 && $xray->configCount === 1 && !isset($xray->headers['ETag'])
    && str_contains($xray->headers['Cache-Control'], 'no-store') && !isset($xray->headers['routing']) && !isset($xray->headers['providerid'])
    && $xrayProfile['outbounds'][1]['settings']['vnext'][0]['address'] === 'healthy.example',
    'Separate export must use the authorized node without cache or legacy Happ headers.');
$plain = $endpoint->respond($token, 'plain');
$check(str_starts_with($plain->body, 'vless://') && $plain->headers['Content-Type'] === 'text/plain; charset=utf-8',
    'Enabling Xray changed the legacy response format.');
$fixtureDb->nodes[] = array_replace($fixtureDb->nodes[0], ['server_id' => 8, 'panel_url' => 'https://added.example']);
$check($endpoint->respond($token, 'xray')->configCount === 2, 'A newly assigned server is absent from Xray.');
cache()->set('vpn-v2:settings:v1', array_replace($xraySettings, ['xray_auto' => ['enabled' => true, 'servers' => [8]]]), 300);
$limited = $endpoint->respond($token, 'xray');
$check($limited->configCount === 1 && !str_contains($limited->body, 'healthy.example'), 'Xray ignored its server allowlist.');
cache()->set('vpn-v2:settings:v1', $xraySettings, 300);
$fixtureDb->nodes = [$fixtureDb->nodes[1]];
$removed = $endpoint->respond($token, 'xray');
$check($removed->configCount === 1 && !str_contains($removed->body, 'healthy.example'), 'Removed server was retained.');
$fixtureDb->subscription['expires_at'] = '2000-01-01 00:00:00';
$xrayExpired = $endpoint->respond($token, 'xray');
$check($xrayExpired->configCount === 0 && $xrayExpired->headers['X-Fireball-VPN-Status'] === 'expired'
    && !str_contains($xrayExpired->body, $key), 'Expired Xray export revealed credentials.');
$check($endpoint->respond(str_repeat('b', 64), 'xray')->status === 404, 'Another token must not expose this profile.');
$fixtureDb->subscription['expires_at'] = null;
$fixtureDb->subscription['device_limit'] = 1;
$check($endpoint->respond($token, 'xray')->status === 404, 'Xray export must not bypass the device requirement.');
function request_is_secure(): bool { return false; }
function request(): object
{
    return new class {
        public function get(string $name, mixed $default = null): mixed { return $name === 'format' ? ' XRay ' : $default; }
    };
}
$insecure = (new \Fireball\VpnManagerV2\Controllers\Public\SubscriptionController())->show();
$check($insecure === '' && http_response_code() === 403, 'The public controller must reject Xray over HTTP before looking up the token.');

echo "PASS Xray generation, settings, access, compatibility, DNS routing, removal, expiry and HTTPS\n";
