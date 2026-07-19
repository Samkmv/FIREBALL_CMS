<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

new FBL\Application();
require dirname(__DIR__) . '/Plugin.php';
FBL\Language::registerPluginLanguage('vpn-manager-v2', dirname(__DIR__) . '/lang');

use Fireball\VpnManagerV2\Clients\ThreeXuiClientInterface;
use Fireball\VpnManagerV2\DTO\ConnectionTestResult;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiTransportException;
use Fireball\VpnManagerV2\Services\QrCodeService;
use Fireball\VpnManagerV2\Services\RemoteClientDeletionService;
use Fireball\VpnManagerV2\Services\RemoteClientSyncService;
use Fireball\VpnManagerV2\Services\SubscriptionDeletionService;
use Fireball\VpnManagerV2\Services\VpnSubscriptionCache;
use Fireball\VpnManagerV2\Services\VpnSubscriptionEndpointService;

final class Stage8Panel
{
    public array $clients = [];
    public int $deleteCount = 0;
    public int $updateCount = 0;
    public int $readCount = 0;
    public bool $unavailable = false;
    public bool $transactionViolation = false;
    public array $remoteInboundIds = [];
}

final class Stage8Client implements ThreeXuiClientInterface
{
    public function __construct(private readonly Stage8Panel $panel, private readonly int $remoteInboundId)
    {
    }

    public function authenticate(): void
    {
    }

    public function testConnection(): ConnectionTestResult
    {
        return new ConnectionTestResult(true, 'ok', 1, 'online');
    }

    public function listInbounds(): array
    {
        return [$this->getInbound($this->remoteInboundId)];
    }

    public function getInbound(int $remoteInboundId): array
    {
        $this->touch($remoteInboundId);
        $this->panel->readCount++;
        if ($this->panel->unavailable) {
            throw new ThreeXuiTransportException('stage8 unavailable');
        }

        return [
            'id' => $remoteInboundId,
            'settings' => json_encode(['clients' => array_values($this->panel->clients)], JSON_UNESCAPED_SLASHES),
        ];
    }

    public function getClientTraffic(string $clientIdentifier): array
    {
        return ['success' => true, 'obj' => ['email' => $clientIdentifier, 'up' => 0, 'down' => 0]];
    }

    public function findClient(int $remoteInboundId, string $clientId = '', string $clientEmail = ''): ?array
    {
        foreach ($this->panel->clients as $client) {
            if (($clientId !== '' && (string)$client['id'] === $clientId)
                || ($clientEmail !== '' && (string)$client['email'] === $clientEmail)) {
                return $client;
            }
        }

        return null;
    }

    public function addClient(int $remoteInboundId, array $client): array
    {
        throw new LogicException('Stage 8 must never add a client.');
    }

    public function updateClient(int $remoteInboundId, string $clientId, array $client): array
    {
        $this->touch($remoteInboundId);
        if ($this->panel->unavailable) {
            throw new ThreeXuiTransportException('stage8 unavailable');
        }
        if (!isset($this->panel->clients[$clientId])) {
            throw new RuntimeException('Unknown Stage 8 client update.');
        }
        $this->panel->updateCount++;
        $this->panel->clients[$clientId] = $client;

        return ['success' => true];
    }

    public function deleteClient(int $remoteInboundId, string $clientId, ?string $clientEmail = null): array
    {
        $this->touch($remoteInboundId);
        if ($this->panel->unavailable) {
            throw new ThreeXuiTransportException('stage8 unavailable');
        }
        $this->panel->deleteCount++;
        if (isset($this->panel->clients[$clientId])) {
            unset($this->panel->clients[$clientId]);
        } else {
            foreach ($this->panel->clients as $key => $client) {
                if ($clientEmail !== null && (string)$client['email'] === $clientEmail) {
                    unset($this->panel->clients[$key]);
                }
            }
        }

        return ['success' => true];
    }

    public function resetClientTraffic(int $remoteInboundId, string $clientEmail): array
    {
        throw new LogicException('Not used in stage 8.');
    }

    private function touch(int $remoteInboundId): void
    {
        $this->panel->remoteInboundIds[] = $remoteInboundId;
        if (db()->inTransaction()) {
            $this->panel->transactionViolation = true;
        }
    }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$admin = db()->query("SELECT id FROM users WHERE role IN ('creator', 'admin') ORDER BY id LIMIT 1")->getOne();
$subscriber = db()->query("SELECT id FROM users WHERE role = 'user' ORDER BY id LIMIT 1")->getOne()
    ?: db()->query('SELECT id FROM users ORDER BY id LIMIT 1')->getOne();
$assert(is_array($admin) && is_array($subscriber), 'CMS users are required.');
$adminId = (int)$admin['id'];
$userId = (int)$subscriber['id'];
$suffix = substr(hash('sha256', uniqid('stage8-', true)), 0, 10);
$now = date('Y-m-d H:i:s');
$panels = [];
$serverIds = [];
$inboundIds = [];
$planIds = [];
$subscriptionIds = [];
$tokens = [];
$results = [];
$keys = ['single', 'multi-a', 'multi-b', 'partial-a', 'partial-b', 'absent'];

try {
    foreach ($keys as $index => $key) {
        $panels[$key] = new Stage8Panel();
        db()->query(
            'INSERT INTO vpn_v2_servers
                (name, code, panel_url, panel_path, auth_type, show_flag, status, is_enabled, created_at, updated_at)
             VALUES (?, ?, ?, NULL, ?, 0, ?, 1, ?, ?)',
            ['Stage 8 ' . $key, 'stage8-' . $key . '-' . $suffix, 'https://' . $key . '.stage8.invalid', 'token', 'online', $now, $now]
        );
        $serverIds[$key] = (int)db()->getInsertId();
        $stream = [
            'network' => 'tcp',
            'security' => 'reality',
            'realitySettings' => [
                'shortIds' => ['abcd1234'],
                'settings' => [
                    'serverName' => $key . '.stage8.invalid',
                    'fingerprint' => 'chrome',
                    'publicKey' => 'stage8-public-' . $index,
                ],
            ],
        ];
        db()->query(
            'INSERT INTO vpn_v2_inbounds
                (server_id, remote_inbound_id, name, protocol, port, network, security, default_flow,
                 settings_json, stream_settings_json, status, is_enabled, synced_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, 1, ?, ?, ?)',
            [$serverIds[$key], (string)(8800 + $index), 'Stage 8 ' . $key, 'vless', 18800 + $index,
                'tcp', 'reality', '{}', json_encode($stream, JSON_UNESCAPED_SLASHES), 'active', $now, $now, $now]
        );
        $inboundIds[$key] = (int)db()->getInsertId();
    }

    $createSubscription = static function (string $name, array $nodeKeys, array $missingKeys = []) use (
        &$planIds, &$subscriptionIds, &$tokens, $serverIds, $inboundIds, $panels, $userId, $adminId, $now, $suffix
    ): array {
        db()->query(
            'INSERT INTO vpn_v2_plans
                (name, description, duration_days, traffic_limit_bytes, device_limit, is_active, created_at, updated_at)
             VALUES (?, ?, 30, ?, 2, 1, ?, ?)',
            ['Stage 8 ' . $name . ' ' . $suffix, 'Stage 8 fixture', 10 * (1024 ** 3), $now, $now]
        );
        $planId = (int)db()->getInsertId();
        $planIds[] = $planId;
        foreach ($nodeKeys as $order => $key) {
            db()->query(
                'INSERT INTO vpn_v2_plan_nodes
                    (plan_id, server_id, inbound_id, flow_override, is_enabled, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, NULL, 1, ?, ?, ?)',
                [$planId, $serverIds[$key], $inboundIds[$key], $order * 10, $now, $now]
            );
        }
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 30 * 86400);
        db()->query(
            'INSERT INTO vpn_v2_subscriptions
                (user_id, plan_id, status, starts_at, expires_at, traffic_limit_bytes, device_limit,
                 subscription_token, revision, config_updated_at, created_by, internal_comment, last_error, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 2, ?, 1, ?, ?, NULL, NULL, ?, ?)',
            [$userId, $planId, 'active', $now, $expires, 10 * (1024 ** 3), $token, $now, $adminId, $now, $now]
        );
        $subscriptionId = (int)db()->getInsertId();
        $subscriptionIds[] = $subscriptionId;
        $tokens[$subscriptionId] = $token;
        $nodes = [];
        foreach ($nodeKeys as $order => $key) {
            $uuid = sprintf('80000000-0000-4000-8000-%012d', $subscriptionId * 10 + $order + 1);
            $email = 'stage8-' . $subscriptionId . '-' . $order . '-' . $suffix;
            $subId = 'stage8-sub-' . $subscriptionId . '-' . $order;
            db()->query(
                'INSERT INTO vpn_v2_subscription_nodes
                    (subscription_id, server_id, inbound_id, remote_client_id, client_uuid, client_email,
                     client_sub_id, protocol, network, security, flow, status, traffic_limit_bytes,
                     traffic_used_bytes, last_sync_at, last_error, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, 0, ?, NULL, ?, ?)',
                [$subscriptionId, $serverIds[$key], $inboundIds[$key], $uuid, $uuid, $email, $subId,
                    'vless', 'tcp', 'reality', 'active', 10 * (1024 ** 3), $now, $now, $now]
            );
            $nodes[$key] = (int)db()->getInsertId();
            if (!in_array($key, $missingKeys, true)) {
                $panels[$key]->clients[$uuid] = [
                    'id' => $uuid,
                    'email' => $email,
                    'subId' => $subId,
                    'flow' => '',
                    'security' => 'auto',
                    'limitIp' => 2,
                    'totalGB' => 10 * (1024 ** 3),
                    'expiryTime' => strtotime($expires) * 1000,
                    'enable' => true,
                    'tgId' => 0,
                    'group' => '',
                    'comment' => '',
                    'reset' => 0,
                ];
            }
        }

        return ['id' => $subscriptionId, 'token' => $token, 'nodes' => $nodes, 'plan_id' => $planId];
    };

    $factory = static function (array $server, array $inbound, array $node) use ($serverIds, $panels): ThreeXuiClientInterface {
        $key = array_search((int)$server['id'], $serverIds, true);
        if ($key === false) {
            throw new RuntimeException('Unknown Stage 8 server.');
        }

        return new Stage8Client($panels[$key], (int)$inbound['remote_inbound_id']);
    };
    $remoteDelete = new RemoteClientDeletionService(clientFactory: $factory);
    $remoteSync = new RemoteClientSyncService(clientFactory: $factory);
    $service = new SubscriptionDeletionService(remoteDeletion: $remoteDelete, remoteSync: $remoteSync);

    $single = $createSubscription('single', ['single']);
    $userBefore = db()->query('SELECT id, email FROM users WHERE id = ?', [$userId])->getOne();
    $initialRevision = (int)db()->query('SELECT revision FROM vpn_v2_subscriptions WHERE id = ?', [$single['id']])->getColumn();
    $suspended = $service->suspend($single['id'], $adminId);
    $singleRemote = reset($panels['single']->clients);
    $singleLocal = db()->query('SELECT status, subscription_token, revision FROM vpn_v2_subscriptions WHERE id = ?', [$single['id']])->getOne();
    $assert($suspended->successful() && $singleRemote['enable'] === false && $singleLocal['status'] === 'suspended'
        && $panels['single']->updateCount === 1 && $panels['single']->readCount >= 2,
        'Single subscription was not safely suspended.');
    $assert(hash_equals($single['token'], (string)$singleLocal['subscription_token'])
        && (int)$singleLocal['revision'] > $initialRevision, 'Suspend changed token or did not update revision.');
    $results['suspend_keeps_local_and_token'] = true;

    $cache = new VpnSubscriptionCache();
    $cache->set($single['token'], (int)$singleLocal['revision'], 'base64', 'safe-stage8-cache', 1);
    $qr = new QrCodeService();
    $qr->renderForToken($single['token']);
    $assert(cache()->get($qr->cacheKey($single['token'])) !== null, 'QR cache was not prepared.');
    $singleDelete = $service->deleteForever($single['id'], $adminId);
    $assert($singleDelete->successful() && $panels['single']->clients === [], 'Single subscription deletion failed.');
    $assert(!(bool)db()->query('SELECT id FROM vpn_v2_subscriptions WHERE id = ?', [$single['id']])->getOne()
        && !(bool)db()->query('SELECT id FROM vpn_v2_subscription_nodes WHERE subscription_id = ?', [$single['id']])->getOne(),
        'Single local subscription rows remain.');
    $assert($cache->get($single['token'], (int)$singleLocal['revision'], 'base64') === null
        && cache()->get($qr->cacheKey($single['token'])) === null, 'Subscription or QR cache was not cleared.');
    $assert((new VpnSubscriptionEndpointService())->respond($single['token'])->status === 404, 'Old token did not return 404.');
    $assert(db()->query('SELECT id, email FROM users WHERE id = ?', [$userId])->getOne() === $userBefore,
        'CMS user changed during deletion.');
    $assert((bool)db()->query('SELECT id FROM vpn_v2_plans WHERE id = ?', [$single['plan_id']])->getOne()
        && (bool)db()->query('SELECT id FROM vpn_v2_servers WHERE id = ?', [$serverIds['single']])->getOne()
        && (bool)db()->query('SELECT id FROM vpn_v2_inbounds WHERE id = ?', [$inboundIds['single']])->getOne(),
        'Plan, server, or inbound was deleted.');
    $results['single_delete'] = true;
    $results['old_token_404'] = true;
    $results['related_records_preserved'] = true;
    $results['caches_cleared'] = true;

    $multi = $createSubscription('multi', ['multi-a', 'multi-b']);
    $multiDelete = $service->deleteForever($multi['id'], $adminId);
    $assert($multiDelete->successful() && $multiDelete->deletedNodes === 2
        && $panels['multi-a']->clients === [] && $panels['multi-b']->clients === [], 'Multi-server deletion failed.');
    $results['multi_server_delete'] = true;

    $partial = $createSubscription('partial', ['partial-a', 'partial-b']);
    $panels['partial-b']->unavailable = true;
    $firstAttempt = $service->deleteForever($partial['id'], $adminId);
    $partialState = db()->query('SELECT status, subscription_token FROM vpn_v2_subscriptions WHERE id = ?', [$partial['id']])->getOne();
    $partialNodes = db()->query('SELECT server_id, status FROM vpn_v2_subscription_nodes WHERE subscription_id = ? ORDER BY id', [$partial['id']])->get() ?: [];
    $assert(!$firstAttempt->successful() && $partialState['status'] === 'delete_failed'
        && hash_equals($partial['token'], (string)$partialState['subscription_token'])
        && $partialNodes[0]['status'] === 'deleted' && $partialNodes[1]['status'] === 'delete_failed',
        'Partial deletion state was not retained.');
    $goodDeleteCount = $panels['partial-a']->deleteCount;
    $panels['partial-b']->unavailable = false;
    $retry = $service->deleteForever($partial['id'], $adminId);
    $assert($retry->successful() && $panels['partial-a']->deleteCount === $goodDeleteCount
        && !(bool)db()->query('SELECT id FROM vpn_v2_subscriptions WHERE id = ?', [$partial['id']])->getOne(),
        'Retry was not idempotent after server recovery.');
    $results['unavailable_server'] = true;
    $results['retry_after_recovery'] = true;

    $absent = $createSubscription('absent', ['absent'], ['absent']);
    $absentDelete = $service->deleteForever($absent['id'], $adminId);
    $repeatDelete = $service->deleteForever($absent['id'], $adminId);
    $assert($absentDelete->successful() && $absentDelete->alreadyAbsentNodes === 1
        && $panels['absent']->deleteCount === 0 && $repeatDelete->successful() && $repeatDelete->alreadyDeleted,
        'Already-absent or repeated deletion was not idempotent.');
    $results['already_absent'] = true;
    $results['repeat_after_delete'] = true;

    foreach ($panels as $key => $panel) {
        $assert(!$panel->transactionViolation, 'HTTP ran inside a DB transaction for ' . $key . '.');
        foreach ($panel->remoteInboundIds as $remoteId) {
            $assert((string)$remoteId === (string)(8800 + array_search($key, $keys, true)),
                'A client used the wrong remote inbound.');
        }
    }
    $results['correct_server_inbound'] = true;
    $results['http_outside_transaction'] = true;

    echo json_encode(['status' => 'ok', 'results' => $results, 'fixtures_cleaned' => true], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} finally {
    foreach ($subscriptionIds as $subscriptionId) {
        db()->query('DELETE FROM vpn_v2_events WHERE subscription_id = ?', [$subscriptionId]);
        db()->query('DELETE FROM vpn_v2_subscriptions WHERE id = ?', [$subscriptionId]);
    }
    foreach ($planIds as $planId) {
        db()->query('DELETE FROM vpn_v2_plan_nodes WHERE plan_id = ?', [$planId]);
        db()->query('DELETE FROM vpn_v2_plans WHERE id = ?', [$planId]);
    }
    foreach ($inboundIds as $id) {
        db()->query('DELETE FROM vpn_v2_inbounds WHERE id = ?', [$id]);
    }
    foreach ($serverIds as $id) {
        db()->query('DELETE FROM vpn_v2_events WHERE server_id = ?', [$id]);
        db()->query('DELETE FROM vpn_v2_servers WHERE id = ?', [$id]);
    }
}
