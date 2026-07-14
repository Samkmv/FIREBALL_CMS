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
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Services\SubscriptionProvisioningService;
use Fireball\VpnManagerV2\Services\VpnFlowResolver;

final class Stage5FakePanel
{
    public array $clients = [];
    public int $addCount = 0;
    public int $httpCount = 0;
    public bool $transactionViolation = false;

    public function __construct(public string $behavior = 'ok')
    {
    }
}

final class Stage5FakeClient implements ThreeXuiClientInterface
{
    public function __construct(
        private readonly Stage5FakePanel $panel,
        private readonly int $remoteInboundId,
        private readonly int $nodeId,
    ) {
    }

    public function authenticate(): void
    {
        $this->touch();
    }

    public function testConnection(): ConnectionTestResult
    {
        $this->touch();

        return new ConnectionTestResult(true, 'ok', 1, 'online');
    }

    public function listInbounds(): array
    {
        $this->touch();

        return [$this->getInbound($this->remoteInboundId)];
    }

    public function getInbound(int $remoteInboundId): array
    {
        $this->touch();

        return [
            'id' => $remoteInboundId,
            'settings' => json_encode(['clients' => array_values($this->panel->clients)], JSON_UNESCAPED_SLASHES),
        ];
    }

    public function findClient(int $remoteInboundId, string $clientId = '', string $clientEmail = ''): ?array
    {
        $this->touch();
        foreach ($this->panel->clients as $client) {
            if (($clientId !== '' && (string)($client['id'] ?? $client['password'] ?? '') === $clientId)
                || ($clientEmail !== '' && (string)($client['email'] ?? '') === $clientEmail)) {
                return $client;
            }
        }

        return null;
    }

    public function addClient(int $remoteInboundId, array $client): array
    {
        $this->touch();
        $this->panel->addCount++;
        if (!isset($client['tgId']) || !is_int($client['tgId'])) {
            throw new RuntimeException('3x-ui requires a numeric tgId.');
        }
        if ($this->panel->behavior === 'unavailable') {
            throw new ThreeXuiTransportException('vpn_manager_v2_error_transport');
        }

        $key = (string)($client['id'] ?? $client['password'] ?? '');
        if ($this->panel->behavior === 'strip_flow') {
            $client['flow'] = '';
        }
        $this->panel->clients[$key] = $client;
        if ($this->panel->behavior === 'fail_after_store') {
            throw new ThreeXuiTransportException('vpn_manager_v2_error_transport');
        }

        return ['success' => true];
    }

    public function updateClient(int $remoteInboundId, string $clientId, array $client): array
    {
        throw new LogicException('Not used in stage 5.');
    }

    public function deleteClient(int $remoteInboundId, string $clientId, ?string $clientEmail = null): array
    {
        throw new LogicException('Not used in stage 5.');
    }

    private function touch(): void
    {
        $this->panel->httpCount++;
        if (db()->inTransaction()) {
            $this->panel->transactionViolation = true;
        }
        $row = db()->query(
            'SELECT status FROM vpn_v2_subscription_nodes WHERE id = ? LIMIT 1',
            [$this->nodeId]
        )->getOne();
        if (!is_array($row)) {
            throw new RuntimeException('Remote call happened before the local node was committed.');
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
$assert(is_array($admin) && is_array($subscriber), 'CMS users are required for integration tests.');
$adminId = (int)$admin['id'];
$userId = (int)$subscriber['id'];
$suffix = substr(hash('sha256', uniqid('vpn-v2-stage5-', true)), 0, 10);
$now = date('Y-m-d H:i:s');
$serverIds = [];
$inboundIds = [];
$planIds = [];
$subscriptionIds = [];
$panels = [
    'tcp' => new Stage5FakePanel('ok'),
    'xhttp' => new Stage5FakePanel('ok'),
    'down' => new Stage5FakePanel('unavailable'),
    'retry' => new Stage5FakePanel('fail_after_store'),
    'flow' => new Stage5FakePanel('strip_flow'),
];
$results = [];

try {
    foreach (array_keys($panels) as $key) {
        db()->query(
            'INSERT INTO vpn_v2_servers
                (name, code, panel_url, panel_path, auth_type, show_flag, status, is_enabled, created_at, updated_at)
             VALUES (?, ?, ?, NULL, ?, 0, ?, 1, ?, ?)',
            ['Stage 5 ' . $key, 'stage5-' . $key . '-' . $suffix, 'https://' . $key . '.stage5.invalid', 'token', 'online', $now, $now]
        );
        $serverIds[$key] = (int)db()->getInsertId();
        $network = $key === 'xhttp' ? 'xhttp' : 'tcp';
        db()->query(
            'INSERT INTO vpn_v2_inbounds
                (server_id, remote_inbound_id, name, protocol, port, network, security, default_flow,
                 settings_json, stream_settings_json, status, is_enabled, synced_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
            [
                $serverIds[$key],
                (string)(51000 + count($inboundIds)),
                'Stage 5 ' . strtoupper($key),
                'vless',
                14000 + count($inboundIds),
                $network,
                'reality',
                $network === 'tcp' ? VpnFlowResolver::VISION : null,
                '{}',
                json_encode(['network' => $network, 'security' => 'reality'], JSON_UNESCAPED_SLASHES),
                'active',
                $now,
                $now,
                $now,
            ]
        );
        $inboundIds[$key] = (int)db()->getInsertId();
    }

    $createPlan = static function (string $name, array $keys) use (&$planIds, $serverIds, $inboundIds, $now, $suffix): int {
        db()->query(
            'INSERT INTO vpn_v2_plans
                (name, description, duration_days, traffic_limit_bytes, device_limit, is_active, created_at, updated_at)
             VALUES (?, ?, 30, ?, 3, 1, ?, ?)',
            [$name . ' ' . $suffix, 'Stage 5 fixture', 50 * (1024 ** 3), $now, $now]
        );
        $planId = (int)db()->getInsertId();
        $planIds[] = $planId;
        foreach ($keys as $order => $key) {
            db()->query(
                'INSERT INTO vpn_v2_plan_nodes
                    (plan_id, server_id, inbound_id, flow_override, is_enabled, sort_order, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, ?, ?, ?)',
                [
                    $planId,
                    $serverIds[$key],
                    $inboundIds[$key],
                    $key === 'xhttp' ? null : VpnFlowResolver::VISION,
                    $order * 10,
                    $now,
                    $now,
                ]
            );
        }

        return $planId;
    };

    $plans = [
        'single' => $createPlan('Stage 5 single', ['tcp']),
        'multi' => $createPlan('Stage 5 multi', ['tcp', 'xhttp']),
        'down' => $createPlan('Stage 5 down', ['down']),
        'partial' => $createPlan('Stage 5 partial', ['tcp', 'down']),
        'retry' => $createPlan('Stage 5 retry', ['retry']),
        'flow' => $createPlan('Stage 5 flow', ['flow']),
    ];

    $factory = static function (array $server, array $inbound, array $node) use ($panels): ThreeXuiClientInterface {
        $code = (string)$server['code'];
        foreach ($panels as $key => $panel) {
            if (str_starts_with($code, 'stage5-' . $key . '-')) {
                return new Stage5FakeClient($panel, (int)$inbound['remote_inbound_id'], (int)$node['id']);
            }
        }
        throw new RuntimeException('Unknown fake server.');
    };
    $service = new SubscriptionProvisioningService(clientFactory: $factory);
    $input = static fn(int $planId): array => [
        'user_id' => $userId,
        'plan_id' => $planId,
        'starts_at' => date('Y-m-d\\TH:i'),
    ];

    $single = $service->create($input($plans['single']), $adminId);
    $subscriptionIds[] = $single->subscriptionId;
    $assert($single->successful() && $single->created === 1, 'Single TCP Reality provisioning failed.');
    $singleNode = db()->query('SELECT * FROM vpn_v2_subscription_nodes WHERE subscription_id = ?', [$single->subscriptionId])->getOne();
    $assert(is_array($singleNode) && $singleNode['status'] === 'active', 'Single node is not active.');
    $remoteSingle = reset($panels['tcp']->clients);
    $assert(($remoteSingle['flow'] ?? null) === VpnFlowResolver::VISION, 'Vision was not stored by the fake 3x-ui panel.');
    $assert($remoteSingle['enable'] === true && (int)$remoteSingle['limitIp'] === 3, 'Confirmed client fields are invalid.');
    $results['single_tcp_reality'] = true;
    $results['vision_confirmed'] = true;

    $multi = $service->create($input($plans['multi']), $adminId);
    $subscriptionIds[] = $multi->subscriptionId;
    $assert($multi->successful() && $multi->created === 2, 'Multi-node provisioning failed.');
    $multiNodes = db()->query('SELECT * FROM vpn_v2_subscription_nodes WHERE subscription_id = ? ORDER BY id', [$multi->subscriptionId])->get() ?: [];
    $assert(count($multiNodes) === 2, 'Multi-node local rows are missing.');
    $xhttpNode = array_values(array_filter($multiNodes, static fn(array $node): bool => (int)$node['inbound_id'] === $inboundIds['xhttp']))[0] ?? null;
    $assert(is_array($xhttpNode) && $xhttpNode['flow'] === null, 'XHTTP node received an incompatible Flow.');
    $results['multiple_nodes'] = true;
    $results['xhttp_without_vision'] = true;

    $down = $service->create($input($plans['down']), $adminId);
    $subscriptionIds[] = $down->subscriptionId;
    $assert($down->status === 'provisioning_failed' && $down->failed === 1, 'Unavailable server was not persisted as failed.');
    $results['unavailable_server'] = true;

    $partial = $service->create($input($plans['partial']), $adminId);
    $subscriptionIds[] = $partial->subscriptionId;
    $partialStatuses = db()->query('SELECT status FROM vpn_v2_subscription_nodes WHERE subscription_id = ? ORDER BY id', [$partial->subscriptionId])->get() ?: [];
    $assert($partial->status === 'provisioning_failed'
        && array_column($partialStatuses, 'status') === ['active', 'create_failed'], 'Partial multi-server outcome is invalid.');
    $results['partial_success'] = true;

    $retry = $service->create($input($plans['retry']), $adminId);
    $subscriptionIds[] = $retry->subscriptionId;
    $assert($retry->failed === 1 && $panels['retry']->addCount === 1, 'Retry fixture did not fail after remote storage.');
    $retryNode = db()->query('SELECT id FROM vpn_v2_subscription_nodes WHERE subscription_id = ?', [$retry->subscriptionId])->getOne();
    $panels['retry']->behavior = 'ok';
    $retried = $service->retryNode((int)$retryNode['id']);
    $assert($retried->status === 'active' && $retried->reused === 1 && $panels['retry']->addCount === 1, 'Retry created a duplicate remote client.');
    $results['retry_without_duplicate'] = true;

    $flow = $service->create($input($plans['flow']), $adminId);
    $subscriptionIds[] = $flow->subscriptionId;
    $assert($flow->status === 'sync_error' && $flow->flowError, 'Lost Flow was not classified as sync_error.');
    $flowNode = db()->query('SELECT status, last_error FROM vpn_v2_subscription_nodes WHERE subscription_id = ?', [$flow->subscriptionId])->getOne();
    $assert($flowNode['status'] === 'sync_error'
        && $flowNode['last_error'] === FireballPluginVpnManagerV2::t('vpn_manager_v2_error_client_flow_not_saved'), 'Flow error message is invalid.');
    $results['flow_sync_error'] = true;

    $tokens = db()->query(
        'SELECT subscription_token FROM vpn_v2_subscriptions WHERE id IN (' . implode(',', array_fill(0, count($subscriptionIds), '?')) . ')',
        $subscriptionIds
    )->get() ?: [];
    $assert(count(array_unique(array_column($tokens, 'subscription_token'))) === count($subscriptionIds), 'Subscription tokens are not unique.');
    foreach ($panels as $panel) {
        $assert(!$panel->transactionViolation, 'HTTP was invoked inside a database transaction.');
    }
    $results['local_first_commit'] = true;
    $results['unique_tokens'] = true;
} finally {
    if ($subscriptionIds !== []) {
        $placeholders = implode(',', array_fill(0, count($subscriptionIds), '?'));
        db()->query("DELETE FROM vpn_v2_events WHERE subscription_id IN ({$placeholders})", $subscriptionIds);
        db()->query("DELETE FROM vpn_v2_subscriptions WHERE id IN ({$placeholders})", $subscriptionIds);
    }
    foreach ($planIds as $planId) {
        db()->query('DELETE FROM vpn_v2_plans WHERE id = ?', [$planId]);
    }
    foreach ($inboundIds as $inboundId) {
        db()->query('DELETE FROM vpn_v2_inbounds WHERE id = ?', [$inboundId]);
    }
    foreach ($serverIds as $serverId) {
        db()->query('DELETE FROM vpn_v2_events WHERE server_id = ?', [$serverId]);
        db()->query('DELETE FROM vpn_v2_servers WHERE id = ?', [$serverId]);
    }
}

$remaining = (int)db()->query('SELECT COUNT(*) FROM vpn_v2_servers WHERE code LIKE ?', ['stage5-%-' . $suffix])->getColumn();
$assert($remaining === 0, 'Stage 5 fixtures were not cleaned.');

echo json_encode(['status' => 'ok', 'results' => $results, 'fixtures_cleaned' => true], JSON_UNESCAPED_SLASHES), PHP_EOL;
