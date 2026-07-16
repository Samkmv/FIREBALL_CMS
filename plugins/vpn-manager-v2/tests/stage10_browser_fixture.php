<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

new FBL\Application();

$action = (string)($argv[1] ?? 'setup');
$prefix = 'stage10-browser-';

$cleanup = static function () use ($prefix): void {
    $users = db()->query('SELECT id FROM users WHERE login LIKE ?', [$prefix . '%'])->get() ?: [];
    foreach ($users as $user) {
        $userId = (int)$user['id'];
        $subscriptions = db()->query('SELECT id FROM vpn_v2_subscriptions WHERE user_id = ?', [$userId])->get() ?: [];
        foreach ($subscriptions as $subscription) {
            db()->query('DELETE FROM vpn_v2_events WHERE subscription_id = ?', [(int)$subscription['id']]);
        }
        db()->query('DELETE FROM vpn_v2_subscriptions WHERE user_id = ?', [$userId]);
    }
    $plans = db()->query('SELECT id FROM vpn_v2_plans WHERE name LIKE ?', ['Stage 10 Browser %'])->get() ?: [];
    foreach ($plans as $plan) {
        db()->query('DELETE FROM vpn_v2_plan_nodes WHERE plan_id = ?', [(int)$plan['id']]);
        db()->query('DELETE FROM vpn_v2_plans WHERE id = ?', [(int)$plan['id']]);
    }
    $servers = db()->query('SELECT id FROM vpn_v2_servers WHERE code LIKE ?', [$prefix . '%'])->get() ?: [];
    foreach ($servers as $server) {
        $serverId = (int)$server['id'];
        db()->query('DELETE FROM vpn_v2_events WHERE server_id = ?', [$serverId]);
        db()->query('DELETE FROM vpn_v2_inbounds WHERE server_id = ?', [$serverId]);
        db()->query('DELETE FROM vpn_v2_servers WHERE id = ?', [$serverId]);
    }
    foreach ($users as $user) {
        db()->query('DELETE FROM users WHERE id = ?', [(int)$user['id']]);
    }
};

$cleanup();
if ($action === 'cleanup') {
    echo json_encode([
        'status' => 'ok',
        'cleaned' => true,
        'remaining' => [
            'users' => (int)db()->query('SELECT COUNT(*) FROM users WHERE login LIKE ?', [$prefix . '%'])->getColumn(),
            'plans' => (int)db()->query('SELECT COUNT(*) FROM vpn_v2_plans WHERE name LIKE ?', ['Stage 10 Browser %'])->getColumn(),
            'servers' => (int)db()->query('SELECT COUNT(*) FROM vpn_v2_servers WHERE code LIKE ?', [$prefix . '%'])->getColumn(),
        ],
    ]), PHP_EOL;
    exit;
}

$suffix = substr(hash('sha256', uniqid('', true)), 0, 8);
$login = $prefix . $suffix;
$password = 'S10!' . bin2hex(random_bytes(12));
$now = date('Y-m-d H:i:s');
db()->query(
    'INSERT INTO users (name, login, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?)',
    ['Stage 10 Browser', $login, $login . '@example.invalid', password_hash($password, PASSWORD_DEFAULT), 'user', $now]
);
$userId = (int)db()->getInsertId();
db()->query(
    'INSERT INTO vpn_v2_servers
        (name, code, panel_url, auth_type, country_code, country_name, city, show_flag,
         status, is_enabled, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, 1, ?, ?)',
    ['Germany Browser', $prefix . $suffix, 'https://browser-fixture.invalid', 'token', 'DE', 'Германия', 'Берлин', 'online', $now, $now]
);
$serverId = (int)db()->getInsertId();
db()->query(
    'INSERT INTO vpn_v2_inbounds
        (server_id, remote_inbound_id, name, protocol, port, network, security,
         settings_json, stream_settings_json, status, is_enabled, synced_at, created_at, updated_at)
     VALUES (?, ?, ?, ?, 443, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
    [$serverId, '991011', 'Browser inbound', 'vless', 'tcp', 'reality', '{}', '{}', 'active', $now, $now, $now]
);
$inboundId = (int)db()->getInsertId();
db()->query(
    'INSERT INTO vpn_v2_plans
        (name, description, duration_days, traffic_limit_bytes, device_limit, is_active, created_at, updated_at)
     VALUES (?, ?, 30, ?, 2, 1, ?, ?)',
    ['Stage 10 Browser ' . $suffix, 'Mobile profile test', 10 * (1024 ** 3), $now, $now]
);
$planId = (int)db()->getInsertId();
$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', time() + 30 * 86400);
db()->query(
    'INSERT INTO vpn_v2_subscriptions
        (user_id, plan_id, status, starts_at, expires_at, traffic_limit_bytes, device_limit,
         subscription_token, revision, config_updated_at, created_by, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, 2, ?, 1, ?, ?, ?, ?)',
    [$userId, $planId, 'active', $now, $expires, 10 * (1024 ** 3), $token, $now, $userId, $now, $now]
);
$subscriptionId = (int)db()->getInsertId();
$uuid = sprintf('b0000000-0000-4000-8000-%012d', $subscriptionId);
db()->query(
    'INSERT INTO vpn_v2_subscription_nodes
        (subscription_id, server_id, inbound_id, remote_client_id, client_uuid, client_email,
         client_sub_id, protocol, network, security, status, traffic_limit_bytes,
         traffic_used_bytes, last_sync_at, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [$subscriptionId, $serverId, $inboundId, $uuid, $uuid, $login, 'browser-' . $suffix,
        'vless', 'tcp', 'reality', 'active', 10 * (1024 ** 3), 2 * (1024 ** 3), $now, $now, $now]
);

echo json_encode(['status' => 'ok', 'login' => $login, 'password' => $password], JSON_UNESCAPED_SLASHES), PHP_EOL;
