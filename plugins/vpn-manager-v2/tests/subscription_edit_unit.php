<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
if (!function_exists('return_translation')) {
    function return_translation(string $key): string { return $key; }
}
require dirname(__DIR__) . '/Plugin.php';

use Fireball\VpnManagerV2\Services\SubscriptionRenewalPolicy;
use Fireball\VpnManagerV2\Support\ProvisioningStatus;
use Fireball\VpnManagerV2\Validators\SubscriptionEditValidator;
use Fireball\VpnManagerV2\Validators\SubscriptionValidator;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
};
$now = time();
$past = date('Y-m-d H:i:s', $now - 3600);
$future = date('Y-m-d H:i:s', $now + 86400);
$validator = new SubscriptionEditValidator();
$input = ['status' => 'active', 'expires_at' => $past, 'traffic_limit_value' => 10, 'traffic_unit' => 'gb'];
$lifetime = $validator->validate($input + ['lifetime' => '1']);
$assert($lifetime->expiresAt === null && $lifetime->status === 'active'
    && $lifetime->trafficLimitBytes === 10 * (1024 ** 3), 'Lifetime edit retained the previous date or changed the traffic limit.');
$expired = $validator->validate($input + ['lifetime' => '0']);
$assert($expired->expiresAt === $past && $expired->status === 'expired', 'Unchecked lifetime ignored the expiration.');
$dated = $validator->validate(array_replace($input, ['expires_at' => $future, 'lifetime' => '0']));
$assert($dated->expiresAt === $future && $dated->status === 'active', 'Lifetime could not be changed back to a finite term.');
$renewed = (new SubscriptionRenewalPolicy())->normalize(['status' => 'expired', 'expires_at' => $past],
    $validator->validate(array_replace($input, ['lifetime' => '1', 'status' => 'expired'])), $now);
$assert($renewed->expiresAt === null && $renewed->status === 'active', 'Expired subscription was not renewed as lifetime.');
$suspended = (new SubscriptionRenewalPolicy())->normalize(['status' => 'suspended', 'expires_at' => $past],
    $validator->validate(array_replace($input, ['lifetime' => '1', 'status' => 'suspended'])), $now);
$assert($suspended->expiresAt === null && $suspended->status === 'suspended', 'Lifetime unexpectedly removed suspension.');

$createValidator = new SubscriptionValidator();
$createInput = ['user_id' => 1, 'plan_id' => 2, 'starts_at' => date('Y-m-d\\TH:i', $now),
    'expires_at' => date('Y-m-d\\TH:i', $now + 90 * 86400)];
$manual = $createValidator->validate($createInput + ['expiry_mode' => 'manual']);
$assert($manual->planId === 2 && $manual->expiresAt !== null && !$manual->lifetime,
    'Creation lost the selected tariff or the manual expiration.');
$byPlan = $createValidator->validate($createInput + ['expiry_mode' => 'plan', 'lifetime' => '1']);
$assert($byPlan->expiresAt === null && !$byPlan->lifetime,
    'Plan-based term accepted a stale manual date or lifetime flag.');
$forever = $createValidator->validate($createInput + ['expiry_mode' => 'lifetime']);
$assert($forever->lifetime && $forever->expiresAt === null, 'Explicit lifetime term was not accepted.');
foreach ([['expiry_mode' => 'manual', 'expires_at' => ''],
    ['expiry_mode' => 'manual', 'expires_at' => date('Y-m-d\\TH:i', $now - 86400)],
    ['expiry_mode' => 'manual', 'expires_at' => '2026-02-30T12:00'],
    ['expiry_mode' => 'invalid'], ['plan_id' => 0]] as $invalid) {
    try {
        $createValidator->validate(array_replace($createInput, $invalid));
        throw new LogicException('Invalid creation tariff/date accepted');
    } catch (\Fireball\VpnManagerV2\Exceptions\ValidationException) {}
}

foreach (['active', 'partial_sync', 'sync_error', 'suspended', 'provisioning', 'provisioning_failed'] as $status) {
    $assert(ProvisioningStatus::subscriptionStatus(['status' => $status, 'expires_at' => $past], $now) === 'expired',
        'Past subscription still displayed its stored status: ' . $status);
    $assert(ProvisioningStatus::subscriptionStatus(['status' => $status, 'expires_at' => $future], $now) === $status,
        'Future subscription lost its current state.');
}
$assert(ProvisioningStatus::subscriptionStatus(['status' => 'active', 'expires_at' => date('Y-m-d H:i:s', $now)], $now) === 'expired',
    'Expiration boundary remained active.');
$assert(ProvisioningStatus::subscriptionStatus(['status' => 'active', 'expires_at' => null], $now) === 'active',
    'Lifetime subscription was displayed as expired.');
foreach (['deleted', 'deleting', 'delete_failed', 'pending_remote_delete', 'cancelled'] as $status) {
    $assert(ProvisioningStatus::subscriptionStatus(['status' => $status, 'expires_at' => $past], $now) === $status,
        'Expiration hid a deletion/cancellation state.');
}

echo json_encode(['status' => 'ok', 'cases' => ['lifetime_edit', 'finite_term_restored',
    'expired_to_lifetime', 'suspension_preserved', 'display_without_worker', 'expiration_boundary',
    'lifetime_display', 'deletion_state_preserved', 'creation_plan_term', 'creation_manual_date',
    'creation_lifetime', 'creation_invalid_plan_or_date']]), PHP_EOL;
