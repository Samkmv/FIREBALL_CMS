<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$read = static function (string $path) use ($root): string {
    $v = @file_get_contents($root . '/' . $path);
    if (!is_string($v)) throw new RuntimeException('Cannot read ' . $path);
    return $v;
};

$plugin = json_decode($read('plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(($plugin['version'] ?? '') === '1.4.0', 'Version is not 1.4.0');
$assert(str_contains($read('src/Services/ClientPayloadFactory.php'), "'limitHwid' =>"), 'limitHwid missing');
$assert(str_contains($read('src/Services/ClientPayloadFactory.php'), "'limitIp' =>"), 'limitIp missing');
$assert(str_contains($read('src/Services/PlanManagerService.php'), '$parametersChanged'), 'auto reconciliation missing');
$assert(str_contains($read('routes/admin.php'), 'SubscriptionDeviceController'), 'device routes missing');
$assert(is_file($root . '/views/admin/subscription-devices.php'), 'device view missing');
$plan = $read('views/admin/plan-form.php');
$assert(str_contains($plan, 'name="ip_limit"'), 'ip_limit field missing');
$assert(!str_contains($plan, 'name="reconcile_existing"'), 'obsolete reconcile checkbox still present');
$remote = $read('src/Services/RemoteClientSyncService.php');
$assert(str_contains($remote, "'device_limit' => max(0, (int)(\$expected['limitHwid'] ?? 0))"), 'device snapshot missing');

echo json_encode(['status' => 'ok'], JSON_UNESCAPED_SLASHES), PHP_EOL;
