<?php

declare(strict_types=1);

require dirname(__DIR__) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

new FBL\Application();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$suffix = substr(bin2hex(random_bytes(6)), 0, 10);
$tables = [
    'notifications' => 'test_v2_notifications_' . $suffix,
    'nodes' => 'test_v2_nodes_' . $suffix,
    'subscriptions' => 'test_v2_subscriptions_' . $suffix,
    'servers' => 'test_v2_servers_' . $suffix,
];
$runner = new App\Services\SqlFileRunner();

try {
    db()->query("CREATE TABLE {$tables['servers']} (
        id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        panel_path VARCHAR(190) NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB");
    db()->query("CREATE TABLE {$tables['subscriptions']} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        traffic_limit_bytes BIGINT UNSIGNED NULL,
        created_by INT(10) UNSIGNED NOT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB");
    db()->query("CREATE TABLE {$tables['nodes']} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        subscription_id BIGINT UNSIGNED NOT NULL,
        traffic_used_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(40) NOT NULL DEFAULT 'active',
        PRIMARY KEY (id)
    ) ENGINE=InnoDB");

    $replace = static function (string $sql) use ($tables, $suffix): string {
        return str_replace(
            [
                'vpn_v2_subscription_nodes',
                'vpn_v2_subscriptions',
                'vpn_v2_notifications',
                'vpn_v2_servers',
                'fk_v2_notifications_subscription',
                'fk_v2_notifications_user',
            ],
            [
                $tables['nodes'],
                $tables['subscriptions'],
                $tables['notifications'],
                $tables['servers'],
                'fk_test_v2_notif_sub_' . $suffix,
                'fk_test_v2_notif_user_' . $suffix,
            ],
            $sql
        );
    };

    foreach ([2, 3, 4] as $number) {
        $file = glob(ROOT . '/plugins/vpn-manager-v2/migrations/00' . $number . '_*.sql')[0] ?? null;
        if (!is_string($file)) {
            throw new RuntimeException('VPN V2 migration fixture is missing.');
        }
        $sql = $replace((string)file_get_contents($file));
        $runner->executeDatabase($sql);
        $runner->executeDatabase($sql);
    }

    $assert((bool)db()->query("SHOW COLUMNS FROM {$tables['servers']} LIKE 'auth_type'")->getOne(),
        'auth_type was not added idempotently.');
    $expectedIndex = 'idx_' . $tables['servers'] . '_auth_type';
    $assert((bool)db()->query("SHOW INDEX FROM {$tables['servers']} WHERE Key_name = ?", [$expectedIndex])->getOne(),
        'auth_type index was not added idempotently.');
    $assert((bool)db()->query("SHOW COLUMNS FROM {$tables['subscriptions']} LIKE 'internal_comment'")->getOne(),
        'internal_comment was not added idempotently.');
    $assert((bool)db()->query("SHOW COLUMNS FROM {$tables['subscriptions']} LIKE 'traffic_used_bytes'")->getOne(),
        'traffic_used_bytes was not added idempotently.');
    $assert((bool)db()->query(
        'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$tables['notifications']]
    )->getOne(), 'vpn_v2_notifications was not created idempotently.');

    echo json_encode([
        'status' => 'ok',
        'migrations' => ['002', '003', '004'],
        'runs_per_migration' => 2,
    ], JSON_UNESCAPED_SLASHES), PHP_EOL;
} finally {
    foreach (['notifications', 'nodes', 'subscriptions', 'servers'] as $key) {
        db()->query("DROP TABLE IF EXISTS {$tables[$key]}");
    }
}
