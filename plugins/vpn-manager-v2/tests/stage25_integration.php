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
use Fireball\VpnManagerV2\Exceptions\ValidationException;
use Fireball\VpnManagerV2\Services\ServerManagerService;
use Fireball\VpnManagerV2\Services\SubscriptionProvisioningService;
use Fireball\VpnManagerV2\Services\VpnAccessRequestService;
use Fireball\VpnManagerV2\Services\VpnV2SchemaUpgradeService;

final class Stage25Panel
{
    /** @var array<string, array<string, mixed>> */
    public array $globalClients = [];
    /** @var array<int, array<string, array<string, mixed>>> */
    public array $inboundClients = [];
}

final class Stage25Client implements ThreeXuiClientInterface
{
    public function __construct(private readonly Stage25Panel $panel)
    {
    }

    public function authenticate(): void {}

    public function testConnection(): ConnectionTestResult
    {
        return new ConnectionTestResult(true, 'ok', count($this->panel->inboundClients), 'online');
    }

    public function listInbounds(): array
    {
        return array_map(
            fn(int $id): array => $this->getInbound($id),
            array_keys($this->panel->inboundClients)
        );
    }

    public function getInbound(int $remoteInboundId): array
    {
        return [
            'id' => $remoteInboundId,
            'settings' => ['clients' => array_values($this->panel->inboundClients[$remoteInboundId] ?? [])],
        ];
    }

    public function getClientTraffic(string $clientIdentifier): array
    {
        return ['success' => true, 'obj' => ['email' => $clientIdentifier, 'up' => 0, 'down' => 0]];
    }

    public function findClient(int $remoteInboundId, string $clientId = '', string $clientEmail = ''): ?array
    {
        foreach ($this->panel->inboundClients[$remoteInboundId] ?? [] as $client) {
            if (($clientId !== '' && hash_equals((string)($client['id'] ?? ''), $clientId))
                || ($clientEmail !== '' && hash_equals((string)($client['email'] ?? ''), $clientEmail))) {
                return $client;
            }
        }

        return null;
    }

    public function addClient(int $remoteInboundId, array $client): array
    {
        $email = (string)($client['email'] ?? '');
        $subId = (string)($client['subId'] ?? '');
        if (isset($this->panel->globalClients[$email])
            && !hash_equals((string)$this->panel->globalClients[$email]['subId'], $subId)) {
            throw new RuntimeException('Modern 3x-ui rejected a duplicate global email.');
        }
        $this->panel->globalClients[$email] = $client;
        $this->panel->inboundClients[$remoteInboundId][$email] = $client;

        return ['success' => true];
    }

    public function updateClient(int $remoteInboundId, string $clientId, array $client): array
    {
        $email = (string)($client['email'] ?? '');
        $this->panel->globalClients[$email] = $client;
        $this->panel->inboundClients[$remoteInboundId][$email] = $client;

        return ['success' => true];
    }

    public function deleteClient(int $remoteInboundId, string $clientId, ?string $clientEmail = null): array
    {
        unset($this->panel->inboundClients[$remoteInboundId][(string)$clientEmail]);

        return ['success' => true];
    }

    public function resetClientTraffic(int $remoteInboundId, string $clientEmail): array
    {
        return ['success' => true];
    }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$suffix = bin2hex(random_bytes(5));
$now = date('Y-m-d H:i:s');
$admin = db()->query("SELECT id FROM users WHERE role IN ('creator', 'admin') ORDER BY id LIMIT 1")->getOne();
$assert(is_array($admin), 'An administrator fixture is required.');
$userId = 0;
$serverId = 0;
$inboundIds = [];
$planId = 0;
$subscriptionId = 0;

(new VpnV2SchemaUpgradeService())->ensureCurrent();

try {
    db()->query(
        'INSERT INTO users (name, login, email, password, role, created_at)
         VALUES (?, ?, ?, ?, ?, ?)',
        ['Stage 25 User', 'stage25-' . $suffix, 'stage25-' . $suffix . '@example.invalid',
            password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), 'user', $now]
    );
    $userId = (int)db()->getInsertId();
    $accessNotifications = [];
    $accessEmails = [];
    $accessService = new VpnAccessRequestService(
        notificationDispatcher: static function (array $payload) use (&$accessNotifications): void {
            $accessNotifications[] = $payload;
        },
        mailDispatcher: static function (array $recipients, string $subject, string $html, string $text) use (&$accessEmails): void {
            $accessEmails[] = compact('recipients', 'subject');
        }
    );
    $accessRequest = $accessService->requestForUser($userId);
    $duplicateAccessRequest = $accessService->requestForUser($userId);
    $assert(!empty($accessRequest['created'])
        && empty($duplicateAccessRequest['created'])
        && count($accessNotifications) === 1
        && count($accessEmails) === 1,
        'VPN access requests are not persisted, deduplicated, or delivered to administrators.');
    db()->query(
        'INSERT INTO vpn_v2_servers
            (name, code, panel_url, panel_path, auth_type, country_code, country_name, city,
             show_flag, status, is_enabled, created_at, updated_at)
         VALUES (?, ?, ?, NULL, ?, ?, ?, ?, 1, ?, 1, ?, ?)',
        ['Stage 25 server', 'stage25-' . $suffix, 'https://stage25.invalid', 'token',
            'DE', 'Germany', 'Berlin', 'online', $now, $now]
    );
    $serverId = (int)db()->getInsertId();
    foreach ([251, 252] as $remoteId) {
        db()->query(
            'INSERT INTO vpn_v2_inbounds
                (server_id, remote_inbound_id, name, protocol, port, network, security,
                 settings_json, stream_settings_json, status, is_enabled, synced_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
            [$serverId, (string)$remoteId, 'Stage 25 ' . $remoteId, 'vless', 12000 + $remoteId,
                'tcp', 'none', '{}', '{"network":"tcp","security":"none"}',
                'active', $now, $now, $now]
        );
        $inboundIds[$remoteId] = (int)db()->getInsertId();
    }
    db()->query(
        'INSERT INTO vpn_v2_plans
            (name, description, duration_days, traffic_limit_bytes, device_limit, is_active, created_at, updated_at)
         VALUES (?, ?, 30, ?, 2, 1, ?, ?)',
        ['Stage 25 ' . $suffix, 'One current panel with two inbounds', 1024 * 1024 * 1024, $now, $now]
    );
    $planId = (int)db()->getInsertId();
    foreach ($inboundIds as $order => $inboundId) {
        db()->query(
            'INSERT INTO vpn_v2_plan_nodes
                (plan_id, server_id, inbound_id, flow_override, is_enabled, sort_order, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, ?, ?, ?)',
            [$planId, $serverId, $inboundId, '', $order, $now, $now]
        );
    }

    $panel = new Stage25Panel();
    $service = new SubscriptionProvisioningService(
        clientFactory: static fn(): ThreeXuiClientInterface => new Stage25Client($panel),
        notificationCallback: static function (): void {}
    );
    $result = $service->create([
        'user_id' => $userId,
        'plan_id' => $planId,
        'starts_at' => date('Y-m-d\TH:i'),
    ], (int)$admin['id']);
    $subscriptionId = $result->subscriptionId;
    $fulfilledRequest = db()->query(
        'SELECT status, subscription_id FROM vpn_v2_access_requests WHERE user_id = ? ORDER BY id DESC LIMIT 1',
        [$userId]
    )->getOne();
    $emails = db()->query(
        'SELECT client_email FROM vpn_v2_subscription_nodes WHERE subscription_id = ? ORDER BY id',
        [$subscriptionId]
    )->get() ?: [];
    $names = array_column($emails, 'client_email');
    $assert($result->successful() && $result->created === 2
        && count($names) === 2 && count(array_unique($names)) === 2
        && count($panel->globalClients) === 2,
        'Two inbounds on one current 3x-ui panel did not receive independent clients.');
    $assert(is_array($fulfilledRequest)
        && (string)$fulfilledRequest['status'] === 'fulfilled'
        && (int)$fulfilledRequest['subscription_id'] === $subscriptionId,
        'Creating a subscription did not fulfill the pending VPN access request.');
    $serverDeletionBlocked = false;
    try {
        (new ServerManagerService())->delete($serverId);
    } catch (ValidationException) {
        $serverDeletionBlocked = true;
    }
    $assert($serverDeletionBlocked
        && is_array(db()->query('SELECT id FROM vpn_v2_servers WHERE id = ?', [$serverId])->getOne()),
        'A server linked to a plan or subscription was deleted.');

    echo json_encode([
        'status' => 'ok',
        'cases' => [
            'same_panel_multi_inbound_provisioning',
            'global_email_uniqueness',
            'access_request_deduplication',
            'access_request_administrator_delivery',
            'access_request_fulfillment',
            'server_deletion_dependency_guard',
        ],
        'fixtures_cleaned' => true,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    if ($subscriptionId > 0) {
        db()->query('DELETE FROM vpn_v2_events WHERE subscription_id = ?', [$subscriptionId]);
        db()->query('DELETE FROM vpn_v2_subscriptions WHERE id = ?', [$subscriptionId]);
    }
    if ($planId > 0) {
        db()->query('DELETE FROM vpn_v2_plan_nodes WHERE plan_id = ?', [$planId]);
        db()->query('DELETE FROM vpn_v2_plans WHERE id = ?', [$planId]);
    }
    foreach ($inboundIds as $inboundId) {
        db()->query('DELETE FROM vpn_v2_inbounds WHERE id = ?', [$inboundId]);
    }
    if ($serverId > 0) {
        db()->query('DELETE FROM vpn_v2_events WHERE server_id = ?', [$serverId]);
        db()->query('DELETE FROM vpn_v2_servers WHERE id = ?', [$serverId]);
    }
    if ($userId > 0) {
        db()->query('DELETE FROM vpn_v2_access_requests WHERE user_id = ?', [$userId]);
        db()->query('DELETE FROM vpn_v2_profiles WHERE cms_user_id = ?', [$userId]);
        db()->query('DELETE FROM users WHERE id = ?', [$userId]);
    }
}
