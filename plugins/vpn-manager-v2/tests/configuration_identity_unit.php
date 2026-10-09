<?php
// No database or external requests: verify factual client identity selection.
require dirname(__DIR__) . '/src/Services/RemoteClientCredentialService.php';
require dirname(__DIR__) . '/src/Services/ConfigurationSyncService.php';
$service = new Fireball\VpnManagerV2\Services\ConfigurationSyncService();
$match = new ReflectionMethod($service, 'match');
$match->setAccessible(true);
$node = ['client_uuid' => 'local-uuid', 'client_email' => 'local-owner', 'remote_client_name' => '',
    'remote_client_id' => '123', 'client_sub_id' => 'shared-subscription', 'inbound_id' => 1];
$remote = static fn(string $uuid, string $name, string $id = '123'): array => [
    'inbound' => ['id' => 1], 'identity' => ['uuid' => $uuid, 'name' => $name,
        'remote_client_id' => $id, 'sub_id' => 'shared-subscription']];
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } };
$unrelated = $remote('other-uuid', 'other-owner');
$result = $match->invoke($service, $node, [$unrelated]);
$assert($result['remote'] === null && !$result['conflict'], 'Reused numeric id or shared subscription id bound a different client');
$actual = $remote('local-uuid', 'local-owner', '456');
$result = $match->invoke($service, $node, [$unrelated, $actual]);
$assert($result['remote'] === $actual && !$result['conflict'], 'Actual credential and email lost to a reused REST id');
$result = $match->invoke($service, $node, [$actual, $actual]);
$assert($result['remote'] === null && $result['conflict'], 'Duplicate identities were not flagged');
$changedCredential = $remote('new-uuid', 'local-owner', '456');
$result = $match->invoke($service, $node, [$unrelated, $changedCredential]);
$assert($result['remote'] === $changedCredential, 'A stable email cannot reconcile an explicitly changed panel credential');
echo "PASS configuration client identities: shared subId, reused REST id, stable email and ambiguity\n";
