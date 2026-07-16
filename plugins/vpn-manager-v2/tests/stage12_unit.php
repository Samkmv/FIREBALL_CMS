<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';

if (!function_exists('return_translation')) {
    function return_translation(string $key): string
    {
        return [
            'vpn_manager_v2_unlimited' => 'Безлимит',
            'vpn_manager_v2_traffic_unit_b' => 'Б',
            'vpn_manager_v2_traffic_unit_kb' => 'КБ',
            'vpn_manager_v2_traffic_unit_mb' => 'МБ',
            'vpn_manager_v2_traffic_unit_gb' => 'ГБ',
            'vpn_manager_v2_traffic_unit_tb' => 'ТБ',
            'vpn_manager_v2_traffic_unit_pb' => 'ПБ',
        ][$key] ?? $key;
    }
}

require dirname(__DIR__) . '/Plugin.php';

use Fireball\VpnManagerV2\Exceptions\ThreeXuiResponseException;
use Fireball\VpnManagerV2\Jobs\VpnV2CheckExpirationsJob;
use Fireball\VpnManagerV2\Jobs\VpnV2CheckTrafficLimitsJob;
use Fireball\VpnManagerV2\Jobs\VpnV2RetryFailedOperationsJob;
use Fireball\VpnManagerV2\Jobs\VpnV2SendExpirationNotificationsJob;
use Fireball\VpnManagerV2\Jobs\VpnV2SyncTrafficJob;
use Fireball\VpnManagerV2\Services\ClientPayloadFactory;
use Fireball\VpnManagerV2\Services\SettingsService;
use Fireball\VpnManagerV2\Services\TrafficSyncService;
use Fireball\VpnManagerV2\Support\TrafficFormatter;
use Fireball\VpnManagerV2\Validators\SettingsValidator;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$used = TrafficSyncService::trafficUsedFromResponse([
    'success' => true,
    'obj' => ['email' => 'client-a', 'up' => 512, 'down' => 1024, 'total' => 999999999],
], 'client-a');
$assert($used === 1536, 'Traffic parser treated quota/total as used traffic.');

$listed = TrafficSyncService::trafficUsedFromResponse([
    'obj' => [
        ['email' => 'other', 'up' => 100, 'down' => 100],
        ['email' => 'wanted', 'up' => 200, 'down' => 300],
    ],
], 'wanted');
$assert($listed === 500, 'Traffic parser mixed clients from a list response.');

$invalidRejected = false;
try {
    TrafficSyncService::trafficUsedFromResponse(['obj' => ['email' => 'wanted', 'total' => 100]], 'wanted');
} catch (ThreeXuiResponseException) {
    $invalidRejected = true;
}
$assert($invalidRejected, 'Missing factual up/down counters were accepted.');

$tenGb = 10 * (1024 ** 3);
$assert(TrafficFormatter::usage(0, $tenGb) === '0 Б / 10 ГБ', 'Zero/limited formatter is incorrect.');
$assert(TrafficFormatter::usage(512 * (1024 ** 2), $tenGb) === '512 МБ / 10 ГБ', 'MB/GB formatter is incorrect.');
$assert(TrafficFormatter::usage(0, null) === '0 Б / Безлимит', 'Unlimited formatter is incorrect.');
$assert(TrafficFormatter::usage(0, null) !== '0 Б / Б', 'Formatter produced an ambiguous byte/byte label.');

$payload = (new ClientPayloadFactory())->build([
    'status' => 'traffic_exceeded',
    'expires_at' => null,
    'device_limit' => 1,
    'traffic_limit_bytes' => $tenGb,
], [
    'protocol' => 'vless',
    'client_uuid' => '00000000-0000-4000-8000-000000000012',
    'client_email' => 'stage12-unit',
    'client_sub_id' => 'stage12',
]);
$assert($payload['enable'] === false && $payload['reset'] === 0, 'Traffic limit payload enables or resets the client.');

$defaults = SettingsService::defaults();
$validated = (new SettingsValidator())->validate(array_replace($defaults, [
    'notifications_email_enabled' => '0',
    'notify_critical_errors' => '1',
    'retry_failed_operations' => '1',
]), $defaults)->toArray();
$assert($validated['notifications_email_enabled'] === false
    && $validated['notify_critical_errors'] === true
    && $validated['retry_failed_operations'] === true,
    'Stage 12 settings were not normalized.');

$jobs = FireballPluginVpnManagerV2::jobs();
$classes = array_column($jobs, 'class');
foreach ([
    VpnV2SyncTrafficJob::class,
    VpnV2CheckExpirationsJob::class,
    VpnV2CheckTrafficLimitsJob::class,
    VpnV2SendExpirationNotificationsJob::class,
    VpnV2RetryFailedOperationsJob::class,
] as $class) {
    $assert(in_array($class, $classes, true) && method_exists($class, 'handle'), 'Job is not registered: ' . $class);
}

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'traffic_up_plus_down_only',
        'client_isolation',
        'invalid_traffic_rejected',
        'localized_byte_formatter',
        'traffic_limit_disables_without_reset',
        'automation_settings',
        'five_jobs_registered',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
