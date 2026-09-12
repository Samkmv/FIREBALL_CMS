<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/ProcessRunner.php';
require_once dirname(__DIR__) . '/src/InputValidator.php';
require_once dirname(__DIR__) . '/src/RtspUrlBuilder.php';
require_once dirname(__DIR__) . '/src/NetworkConfigGenerator.php';
require_once dirname(__DIR__) . '/src/StreamKeyGenerator.php';
require_once dirname(__DIR__, 3) . '/app/Services/SqlFileRunner.php';

use Fireball\CameraManager\InputValidator;
use Fireball\CameraManager\NetworkConfigGenerator;
use Fireball\CameraManager\ProcessRunner;
use Fireball\CameraManager\RtspUrlBuilder;
use Fireball\CameraManager\StreamKeyGenerator;
use App\Services\SqlFileRunner;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$throws = static function (callable $callback, string $message) use ($assert): void {
    $thrown = false;
    try {
        $callback();
    } catch (RuntimeException) {
        $thrown = true;
    }
    $assert($thrown, $message);
};

$assert(InputValidator::nullableIpv4Cidr('192.168.34.0/24') === '192.168.34.0/24', 'Valid CIDR was rejected.');
$throws(static fn() => InputValidator::nullableIpv4Cidr('192.168.34.10/24'), 'Host address was accepted as LAN CIDR.');
$throws(static fn() => InputValidator::nullablePort('65536', 'port'), 'Out-of-range port was accepted.');
$throws(static fn() => InputValidator::linuxInterface('wg0;id', 'interface'), 'Shell fragment was accepted as a network interface.');
$assert(InputValidator::nullablePort('', 'port') === null, 'Optional port is not nullable.');
$publicKey = base64_encode(str_repeat("\x01", 32));
$assert(InputValidator::wireGuardPublicKey($publicKey) === $publicKey, 'Valid WireGuard PublicKey was rejected.');
$throws(static fn() => InputValidator::wireGuardPublicKey('PrivateKey = secret'), 'Invalid WireGuard key was accepted.');

$builder = new RtspUrlBuilder();
$assert(
    $builder->path('dahua', 2, 1, '/unused/{channel}') === '/cam/realmonitor?channel=2&subtype=1',
    'Dahua RTSP path is incorrect.'
);
$assert(
    $builder->path('dahua_legacy', 2, 1, '/unused/{channel}') === '/cam/realmonitor?channel=2_subtype=1',
    'Dahua legacy RTSP path is incorrect.'
);
$assert($builder->path('hikvision_isapi', 1, 0, '/unused/{channel}') === '/ISAPI/Streaming/Channels/101', 'Hikvision 101 calculation failed.');
$assert($builder->path('hikvision_isapi', 1, 1, '/unused/{channel}') === '/ISAPI/Streaming/Channels/102', 'Hikvision 102 calculation failed.');
$assert($builder->path('hikvision_isapi', 2, 0, '/unused/{channel}') === '/ISAPI/Streaming/Channels/201', 'Hikvision 201 calculation failed.');
$assert($builder->path('hikvision_isapi', 2, 1, '/unused/{channel}') === '/ISAPI/Streaming/Channels/202', 'Hikvision 202 calculation failed.');
$assert(
    $builder->path('custom', 3, 1, '/custom/{channel}?sub={subtype}&stream={stream}') === '/custom/3?sub=1&stream=1',
    'Custom RTSP template expansion failed.'
);
$masked = $builder->maskedUrl('192.168.34.100', 554, 'operator', '/live');
$assert(str_contains($masked, '••••••') && !str_contains($masked, 'secret'), 'Masked RTSP URL is unsafe.');

$keyGenerator = new StreamKeyGenerator();
$assert($keyGenerator->generate('34', 1) === '34-01', 'Default stream key generation failed.');
$assert($keyGenerator->generate('SITE_A', 12) === 'site_a-12', 'Named site stream key generation failed.');
$throws(static fn() => $keyGenerator->validate('bad key;id'), 'Unsafe stream key was accepted.');

$site = [
    'code' => '34',
    'router_ip' => '192.168.34.1',
    'vpn_ip' => '10.10.0.34',
    'lan_cidr' => '192.168.34.0/24',
    'recorder_ip' => '192.168.34.100',
    'wireguard_public_key' => $publicKey,
    'rtsp_port' => 554,
    'external_rtsp_port' => 55434,
    'management_port' => null,
    'external_management_port' => null,
];
$settings = [
    'wireguard_interface' => 'wg0',
    'wireguard_server_ip' => '10.10.0.254',
    'wireguard_endpoint' => 'vpn.example.test:51820',
    'wireguard_server_public_key' => base64_encode(str_repeat("\x02", 32)),
    'external_interface' => 'ens3',
    'public_ip' => '193.0.2.10',
];
$network = (new NetworkConfigGenerator())->generate($site, $settings);
$up = (string)$network['scripts']['up'];
$down = (string)$network['scripts']['down'];
$wireGuardCommands = (string)$network['wireguard_commands'];
$assert($network['route_command'] === 'ip route replace 192.168.34.0/24 dev wg0', 'Route generator failed.');
$assert(str_contains((string)$network['router_config'], 'IPv4: 10.10.0.34/24'), 'Router WireGuard address is incorrect.');
$assert(str_contains((string)$network['router_config'], 'AllowedIPs: 10.10.0.0/24'), 'Router WireGuard AllowedIPs are incorrect.');
$assert(str_contains($wireGuardCommands, 'cp -a /etc/wireguard/wg0.conf'), 'WireGuard backup command is missing.');
$assert(str_contains($wireGuardCommands, "wg set 'wg0' peer '" . $publicKey . "'"), 'Safe wg set command is missing.');
$assert(str_contains($wireGuardCommands, "wg syncconf 'wg0' <(wg-quick strip 'wg0')"), 'Safe wg syncconf command is missing.');
$assert(!str_contains($wireGuardCommands, 'PrivateKey ='), 'WireGuard commands contain a PrivateKey field.');
$wireGuardSyntax = (new ProcessRunner())->run(['/bin/bash', '-n'], 10, $wireGuardCommands);
$assert($wireGuardSyntax['exit_code'] === 0, 'Generated WireGuard commands failed bash -n.');
$assert(str_contains($up, 'iptables -t nat -C PREROUTING') && str_contains($up, 'iptables -t nat -A PREROUTING'), 'Up script is not idempotent.');
$assert(str_contains($up, '-C FORWARD') && str_contains($up, '-A FORWARD'), 'FORWARD rules are not idempotent.');
$assert(str_contains($down, 'iptables -t nat -D OUTPUT') && str_contains($down, '2>/dev/null || true'), 'Down script does not safely invert rules.');
$assert(substr_count($up, '--dport 55434') >= 2, 'RTSP external port is missing from NAT rules.');
$assert(!str_contains($up, '37777') && !str_contains($up, '35773'), 'Optional management rules were generated unexpectedly.');
$assert(!str_contains($up . $down, 'PrivateKey') && !str_contains($up . $down, 'password'), 'Generated scripts leaked a secret field.');
$assert(str_contains((string)$network['verification_commands'], '</dev/tcp/193.0.2.10/55434'), 'External RTSP verification is missing.');
$upSyntax = (new ProcessRunner())->run(['/bin/bash', '-n'], 10, $up);
$downSyntax = (new ProcessRunner())->run(['/bin/bash', '-n'], 10, $down);
$assert($upSyntax['exit_code'] === 0 && $downSyntax['exit_code'] === 0, 'Generated iptables scripts failed bash -n.');

$site['management_port'] = 8000;
$site['external_management_port'] = 38000;
$networkWithManagement = (new NetworkConfigGenerator())->generate($site, $settings);
$assert(
    str_contains((string)$networkWithManagement['scripts']['up'], '--dport 38000')
        && str_contains((string)$networkWithManagement['scripts']['up'], ':8000'),
    'Optional management NAT mapping was not generated.'
);
$assert(
    str_contains((string)$networkWithManagement['verification_commands'], '</dev/tcp/193.0.2.10/38000'),
    'External management verification is missing.'
);

$migration = (string)file_get_contents(dirname(__DIR__) . '/migrations/002_add_connection_wizard_and_diagnostics.sql');
foreach (['lan_cidr', 'external_rtsp_port', 'external_management_port', 'rtsp_profile', 'rtsp_stream_mode', 'network_setup_status', 'network_notes', 'camera_manager_diagnostic_jobs'] as $needle) {
    $assert(str_contains($migration, $needle), 'Migration 002 is missing ' . $needle . '.');
}
$assert(str_contains($migration, 'management_port SMALLINT(5) UNSIGNED NULL DEFAULT NULL'), 'Management port was not made nullable.');
$assert(count((new SqlFileRunner())->split($migration)) >= 20, 'Migration 002 cannot be parsed by the CMS migration runner.');

$helper = dirname(__DIR__) . '/server/fireball-camera-diagnostics';
$injectionTarget = sys_get_temp_dir() . '/camera-manager-injection-' . bin2hex(random_bytes(5));
$payload = json_encode([
    'operation' => 'shell',
    'parameters' => ['command' => 'touch ' . $injectionTarget],
], JSON_THROW_ON_ERROR);
$rejection = (new ProcessRunner())->run([$helper], 10, $payload);
$decoded = json_decode($rejection['stdout'], true);
$assert($rejection['exit_code'] === 0 && is_array($decoded) && $decoded['status'] === 'failed', 'Arbitrary diagnostic operation was not rejected.');
$assert(!file_exists($injectionTarget), 'Arbitrary diagnostic command was executed.');
$pullSource = (string)file_get_contents(dirname(__DIR__) . '/server/fireball-camera-pull');
$assert(
    str_contains($pullSource, "-x \$DIAGNOSTICS_BINARY ? ['diagnostics_v1'] : []")
        && !str_contains($pullSource, "-x \$DIAGNOSTICS_BINARY or fail"),
    'Updated pull agent would break publication when the optional diagnostic helper is not installed.'
);
$assert(
    str_contains($pullSource, 'sub restore_original')
        && str_contains($pullSource, 'original cameras restored')
        && str_contains($pullSource, "run_quiet(\$PERL_BINARY, '-c', \$candidate)"),
    'HTTPS pull publication no longer guarantees Perl validation and rollback.'
);

echo "Camera Manager connection wizard checks passed.\n";
