<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

new FBL\Application();
require dirname(__DIR__) . '/Plugin.php';
FBL\Language::registerPluginLanguage('vpn-manager-v2', dirname(__DIR__) . '/lang');

use App\Services\NotificationService;
use Fireball\VpnManagerV2\Clients\ThreeXuiClientInterface;
use Fireball\VpnManagerV2\DTO\ConnectionTestResult;
use Fireball\VpnManagerV2\Exceptions\ThreeXuiTransportException;
use Fireball\VpnManagerV2\Repositories\AutomationRepository;
use Fireball\VpnManagerV2\Repositories\NotificationRepository;
use Fireball\VpnManagerV2\Repositories\SettingsRepository;
use Fireball\VpnManagerV2\Repositories\SubscriptionRepository;
use Fireball\VpnManagerV2\Services\SettingsService;
use Fireball\VpnManagerV2\Services\SubscriptionAutomationService;
use Fireball\VpnManagerV2\Services\TrafficSyncService;
use Fireball\VpnManagerV2\Services\VpnNotificationService;
use Fireball\VpnManagerV2\Support\SubscriptionToken;
use Fireball\VpnManagerV2\Support\Uuid;

final class Stage12Client implements ThreeXuiClientInterface
{
    public function __construct(private array &$traffic)
    {
    }

    public function authenticate(): void {}

    public function testConnection(): ConnectionTestResult
    {
        return new ConnectionTestResult(true, 'ok', 0, 'online');
    }

    public function listInbounds(): array { return []; }

    public function getInbound(int $remoteInboundId): array { return ['id' => $remoteInboundId]; }

    public function getClientTraffic(string $clientIdentifier): array
    {
        $value = $this->traffic[$clientIdentifier] ?? null;
        if ($value instanceof Throwable) {
            throw $value;
        }
        if (!is_array($value)) {
            throw new ThreeXuiTransportException('missing fixture traffic');
        }

        return ['success' => true, 'obj' => [
            'email' => $clientIdentifier,
            'up' => (int)($value['up'] ?? 0),
            'down' => (int)($value['down'] ?? 0),
        ]];
    }

    public function findClient(int $remoteInboundId, string $clientId = '', string $clientEmail = ''): ?array { return null; }

    public function addClient(int $remoteInboundId, array $client): array { throw new LogicException('Not used.'); }

    public function updateClient(int $remoteInboundId, string $clientId, array $client): array { throw new LogicException('Not used.'); }

    public function deleteClient(int $remoteInboundId, string $clientId, ?string $clientEmail = null): array { throw new LogicException('Not used.'); }
}

final class Stage12NotificationService extends NotificationService
{
    public int $created = 0;
    public bool $failNext = false;

    public function createNotification(array $payload): array
    {
        if ($this->failNext) {
            $this->failNext = false;
            throw new RuntimeException('fixture delivery failure');
        }
        $this->created++;

        return [
            'notification' => ['id' => $this->created] + $payload,
            'push' => ['sent' => 0, 'failed' => 0, 'total' => 0],
        ];
    }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$admin = db()->query("SELECT id FROM users WHERE role IN ('creator', 'admin') ORDER BY id LIMIT 1")->getOne();
$user = db()->query("SELECT id FROM users WHERE role = 'user' ORDER BY id LIMIT 1")->getOne()
    ?: db()->query('SELECT id FROM users ORDER BY id LIMIT 1')->getOne();
$assert(is_array($admin) && is_array($user), 'CMS users are required for Stage 12 integration tests.');

$suffix = substr(hash('sha256', uniqid('stage12-', true)), 0, 12);
$now = date('Y-m-d H:i:s');
$serverId = 0;
$inboundId = 0;
$planId = 0;
$subscriptionIds = [];
$nodeIds = [];
$settingsRepository = new SettingsRepository();
$settingsBefore = $settingsRepository->stored();
$settingKeys = [
    'sync_enabled',
    'retry_failed_operations',
    'notifications_profile_enabled',
    'notifications_email_enabled',
    'notify_expiration_3_days',
    'notify_expiration_day',
    'notify_traffic_80',
    'notify_traffic_100',
    'notify_provisioned',
    'notify_critical_errors',
];
$restoreSettings = array_intersect_key($settingsBefore, array_flip($settingKeys));
$results = [];

try {
    $settingsRepository->write([
        'sync_enabled' => true,
        'retry_failed_operations' => true,
        'notifications_profile_enabled' => true,
        'notifications_email_enabled' => false,
        'notify_expiration_3_days' => true,
        'notify_expiration_day' => true,
        'notify_traffic_80' => true,
        'notify_traffic_100' => true,
        'notify_provisioned' => true,
        'notify_critical_errors' => true,
    ]);
    $settingsRepository->invalidateCache();

    db()->query(
        'INSERT INTO vpn_v2_servers
            (name, code, panel_url, panel_path, auth_type, show_flag, status, is_enabled, created_at, updated_at)
         VALUES (?, ?, ?, NULL, ?, 0, ?, 1, ?, ?)',
        ['Stage 12', 'stage12-' . $suffix, 'https://stage12.invalid', 'token', 'online', $now, $now]
    );
    $serverId = (int)db()->getInsertId();
    db()->query(
        'INSERT INTO vpn_v2_inbounds
            (server_id, remote_inbound_id, name, protocol, port, network, security, default_flow,
             settings_json, stream_settings_json, status, is_enabled, synced_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, 443, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
        [$serverId, '120012', 'Stage 12 inbound', 'vless', 'tcp', 'reality', 'xtls-rprx-vision',
            '{}', '{"network":"tcp","security":"reality"}', 'active', $now, $now, $now]
    );
    $inboundId = (int)db()->getInsertId();
    db()->query(
        'INSERT INTO vpn_v2_plans
            (name, description, duration_days, traffic_limit_bytes, device_limit, is_active, created_at, updated_at)
         VALUES (?, ?, 30, ?, 1, 1, ?, ?)',
        ['Stage 12 ' . $suffix, 'Automation fixture', 10 * (1024 ** 3), $now, $now]
    );
    $planId = (int)db()->getInsertId();

    $createSubscription = static function (
        string $status,
        ?string $expiresAt,
        int $limit,
        int $used,
        string $nodeStatus = 'active'
    ) use (&$subscriptionIds, &$nodeIds, $user, $admin, $planId, $serverId, $inboundId, $now): array {
        db()->query(
            'INSERT INTO vpn_v2_subscriptions
                (user_id, plan_id, status, starts_at, expires_at, traffic_limit_bytes, traffic_used_bytes,
                 device_limit, subscription_token, revision, config_updated_at, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, 1, ?, ?, ?, ?)',
            [(int)$user['id'], $planId, $status, date('Y-m-d H:i:s', time() - 86400), $expiresAt,
                $limit > 0 ? $limit : null, $used, SubscriptionToken::generate(), $now, (int)$admin['id'], $now, $now]
        );
        $subscriptionId = (int)db()->getInsertId();
        $subscriptionIds[] = $subscriptionId;
        $uuid = Uuid::v4();
        $email = 'stage12-' . $subscriptionId;
        db()->query(
            'INSERT INTO vpn_v2_subscription_nodes
                (subscription_id, server_id, inbound_id, remote_client_id, client_uuid, client_email,
                 client_sub_id, protocol, network, security, flow, status, traffic_limit_bytes,
                 traffic_used_bytes, last_sync_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$subscriptionId, $serverId, $inboundId, $uuid, $uuid, $email, bin2hex(random_bytes(8)),
                'vless', 'tcp', 'reality', 'xtls-rprx-vision', $nodeStatus, $limit > 0 ? $limit : null,
                $used, $now, $now, $now]
        );
        $nodeId = (int)db()->getInsertId();
        $nodeIds[] = $nodeId;

        return ['subscription_id' => $subscriptionId, 'node_id' => $nodeId, 'email' => $email];
    };

    $trafficFixture = $createSubscription('active', date('Y-m-d H:i:s', time() + 86400 * 20), 10 * (1024 ** 3), 512 * (1024 ** 2));
    $fakeNotifications = new Stage12NotificationService();
    $notificationService = new VpnNotificationService(
        new NotificationRepository(),
        new AutomationRepository(),
        new SettingsService(),
        $fakeNotifications,
        expirationCandidates: static function () use (&$subscriptionIds): array {
            return array_values(array_filter(
                (new AutomationRepository())->expirationNotificationCandidates(),
                static fn(array $row): bool => in_array((int)$row['id'], $subscriptionIds, true)
            ));
        },
        trafficCandidates: static function () use (&$subscriptionIds): array {
            return array_values(array_filter(
                (new AutomationRepository())->trafficNotificationCandidates(),
                static fn(array $row): bool => in_array((int)$row['id'], $subscriptionIds, true)
            ));
        }
    );
    $traffic = [$trafficFixture['email'] => ['up' => 200 * (1024 ** 2), 'down' => 200 * (1024 ** 2)]];
    $trafficClient = new Stage12Client($traffic);
    $trafficService = new TrafficSyncService(
        clientFactory: static fn(array $server, array $node): ThreeXuiClientInterface => $trafficClient,
        notificationService: $notificationService,
        nodeProvider: static fn(): array => array_values(array_filter(
            (new AutomationRepository())->activeNodesForTrafficSync(),
            static fn(array $node): bool => (int)$node['id'] === (int)$trafficFixture['node_id']
        ))
    );
    $trafficService->syncActiveNodes();
    $stored = (int)db()->query('SELECT traffic_used_bytes FROM vpn_v2_subscription_nodes WHERE id = ?', [$trafficFixture['node_id']])->getColumn();
    $assert($stored === 512 * (1024 ** 2), 'A smaller remote counter reset local traffic.');

    $traffic[$trafficFixture['email']] = ['up' => 300 * (1024 ** 2), 'down' => 300 * (1024 ** 2)];
    $trafficService->syncActiveNodes();
    $stored = (int)db()->query('SELECT traffic_used_bytes FROM vpn_v2_subscription_nodes WHERE id = ?', [$trafficFixture['node_id']])->getColumn();
    $aggregate = (int)db()->query('SELECT traffic_used_bytes FROM vpn_v2_subscriptions WHERE id = ?', [$trafficFixture['subscription_id']])->getColumn();
    $assert($stored === 600 * (1024 ** 2) && $aggregate === $stored, 'Traffic or subscription aggregate was not updated.');

    $traffic[$trafficFixture['email']] = new ThreeXuiTransportException('temporary stage12 outage');
    $trafficService->syncActiveNodes();
    $afterFailure = db()->query('SELECT traffic_used_bytes, last_error FROM vpn_v2_subscription_nodes WHERE id = ?', [$trafficFixture['node_id']])->getOne();
    $assert((int)$afterFailure['traffic_used_bytes'] === $stored && trim((string)$afterFailure['last_error']) !== '',
        'Temporary traffic failure overwrote the confirmed counter or was not recorded.');
    $results['traffic_monotonic'] = true;
    $results['temporary_error_preserved'] = true;

    $expiryFixture = $createSubscription('active', date('Y-m-d H:i:s', time() - 60), 10 * (1024 ** 3), 1);
    $remoteStates = [];
    $automation = new SubscriptionAutomationService(
        notificationService: $notificationService,
        remotePush: static function (array $node, array $subscription, array $overrides) use (&$remoteStates): array {
            $remoteStates[(int)$node['id']] = (string)$subscription['status'];
            return ['remote_updated' => true, 'traffic_used_bytes' => (int)$node['traffic_used_bytes']];
        },
        expirationProvider: static function () use (&$subscriptionIds): array {
            return array_values(array_filter(
                (new AutomationRepository())->dueSubscriptions(),
                static fn(array $row): bool => in_array((int)$row['id'], $subscriptionIds, true)
            ));
        },
        trafficLimitProvider: static function () use (&$subscriptionIds): array {
            return array_values(array_filter(
                (new AutomationRepository())->subscriptionsForTrafficLimit(),
                static fn(array $row): bool => in_array((int)$row['id'], $subscriptionIds, true)
            ));
        }
    );
    $expirationResult = $automation->checkExpirations();
    $expired = db()->query('SELECT status, revision FROM vpn_v2_subscriptions WHERE id = ?', [$expiryFixture['subscription_id']])->getOne();
    $assert((string)$expired['status'] === 'expired' && (int)$expired['revision'] === 2
        && ($remoteStates[$expiryFixture['node_id']] ?? '') === 'expired'
        && $expirationResult['failed'] === 0, 'Expiration was not confirmed remotely and persisted locally.');
    $results['expiration'] = true;

    $limitFixture = $createSubscription('active', date('Y-m-d H:i:s', time() + 86400 * 20), 10 * (1024 ** 3), 10 * (1024 ** 3));
    $beforeLimitNotifications = $fakeNotifications->created;
    $limitResult = $automation->checkTrafficLimits();
    $limitStatus = db()->query('SELECT status, revision FROM vpn_v2_subscriptions WHERE id = ?', [$limitFixture['subscription_id']])->getOne();
    $firstLimitNotifications = $fakeNotifications->created;
    $automation->checkTrafficLimits();
    $assert((string)$limitStatus['status'] === 'traffic_exceeded' && (int)$limitStatus['revision'] === 2
        && ($remoteStates[$limitFixture['node_id']] ?? '') === 'traffic_exceeded'
        && $limitResult['failed'] === 0, 'Traffic limit enforcement was not confirmed.');
    $assert($firstLimitNotifications === $beforeLimitNotifications + 2
        && $fakeNotifications->created === $firstLimitNotifications,
        'Repeated traffic-limit job sent duplicate 80/100 notifications.');
    $results['traffic_limit'] = true;
    $results['traffic_notification_dedupe'] = true;

    $futureFixture = $createSubscription('active', date('Y-m-d 12:00:00', strtotime('+3 days')), 0, 0);
    $notificationService->queueExpirationNotifications();
    $notificationService->dispatch([VpnNotificationService::EXPIRES_TODAY]);
    $beforeExpiryNotifications = $fakeNotifications->created;
    $notificationService->queueExpirationNotifications();
    $notificationService->dispatch([VpnNotificationService::EXPIRES_3_DAYS]);
    $notificationService->queueExpirationNotifications();
    $notificationService->dispatch([VpnNotificationService::EXPIRES_3_DAYS]);
    $assert($fakeNotifications->created === $beforeExpiryNotifications + 1,
        'Repeated expiration job sent a duplicate notification.');

    $beforeProvisioned = $fakeNotifications->created;
    $notificationService->notifyProvisioned($futureFixture['subscription_id']);
    $notificationService->notifyProvisioned($futureFixture['subscription_id']);
    $assert($fakeNotifications->created === $beforeProvisioned + 1,
        'Provisioning notification was not deduplicated.');

    $beforeCritical = $fakeNotifications->created;
    $notificationService->notifyCritical($futureFixture['subscription_id'], 'stage12-critical');
    $notificationService->notifyCritical($futureFixture['subscription_id'], 'stage12-critical');
    $assert($fakeNotifications->created === $beforeCritical + 1,
        'Critical notification was not deduplicated.');
    $results['expiration_notification_dedupe'] = true;
    $results['provisioned_notification'] = true;
    $results['critical_notification'] = true;

    $retryFixture = $createSubscription('active', date('Y-m-d H:i:s', time() + 86400 * 20), 0, 42, 'sync_error');
    $retryAutomation = new SubscriptionAutomationService(
        notificationService: $notificationService,
        remotePush: static fn(array $node, array $subscription, array $overrides): array => [
            'remote_updated' => true,
            'traffic_used_bytes' => 42,
        ]
    );
    $assert($retryAutomation->retryNode($retryFixture['node_id']), 'Failed node retry did not recover.');
    $retriedNode = db()->query('SELECT status, traffic_used_bytes FROM vpn_v2_subscription_nodes WHERE id = ?', [$retryFixture['node_id']])->getOne();
    $assert((string)$retriedNode['status'] === 'active' && (int)$retriedNode['traffic_used_bytes'] === 42,
        'Retry reset traffic or did not restore node status.');
    $results['failed_operation_retry'] = true;

    $fakeNotifications->failNext = true;
    $failedDeliveryBefore = $fakeNotifications->created;
    $notificationService->notifyCritical($retryFixture['subscription_id'], 'delivery-retry');
    $failedOutbox = (int)db()->query(
        "SELECT COUNT(*) FROM vpn_v2_notifications
         WHERE subscription_id = ? AND notification_type = 'critical_error' AND status = 'failed'",
        [$retryFixture['subscription_id']]
    )->getColumn();
    $retryDelivery = $notificationService->retryFailed();
    $assert($failedOutbox === 1 && $retryDelivery['sent'] === 1
        && $fakeNotifications->created === $failedDeliveryBefore + 1,
        'Failed notification was not retried exactly once.');
    $results['notification_retry'] = true;
} finally {
    foreach (array_reverse($subscriptionIds) as $subscriptionId) {
        db()->query('DELETE FROM vpn_v2_notifications WHERE subscription_id = ?', [$subscriptionId]);
        db()->query('DELETE FROM vpn_v2_events WHERE subscription_id = ?', [$subscriptionId]);
        db()->query('DELETE FROM vpn_v2_subscription_nodes WHERE subscription_id = ?', [$subscriptionId]);
        db()->query('DELETE FROM vpn_v2_subscriptions WHERE id = ?', [$subscriptionId]);
    }
    if ($planId > 0) {
        db()->query('DELETE FROM vpn_v2_plans WHERE id = ?', [$planId]);
    }
    if ($inboundId > 0) {
        db()->query('DELETE FROM vpn_v2_events WHERE server_id = ?', [$serverId]);
        db()->query('DELETE FROM vpn_v2_inbounds WHERE id = ?', [$inboundId]);
    }
    if ($serverId > 0) {
        db()->query('DELETE FROM vpn_v2_servers WHERE id = ?', [$serverId]);
    }
    if ($restoreSettings !== []) {
        $settingsRepository->write($restoreSettings);
    }
    $settingsRepository->invalidateCache();
}

echo json_encode([
    'status' => 'ok',
    'results' => $results,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
