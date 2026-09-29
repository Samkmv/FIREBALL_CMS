<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require ROOT . '/helpers/helpers.php';

new FBL\Application();
require dirname(__DIR__) . '/Plugin.php';

use Fireball\VpnManagerV2\Repositories\ServerRepository;
use Fireball\VpnManagerV2\Services\ServerManagerService;
use Fireball\VpnManagerV2\Services\VpnV2SchemaUpgradeService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

(new VpnV2SchemaUpgradeService())->ensureCurrent();
$database = db();
$database->beginTransaction();

try {
    $suffix = substr(bin2hex(random_bytes(8)), 0, 12);
    $baseCode = 'nl-test-' . $suffix;
    $service = new ServerManagerService();
    foreach ([1, 2] as $index) {
        $service->create([
            'name' => 'Netherlands test ' . $index,
            'code' => $baseCode,
            'panel_url' => 'https://1.1.1.1:' . (21000 + $index),
            'auth_type' => 'token',
            'token' => 'test-token-' . $suffix . '-' . $index,
            'country_code' => 'NL',
            'country_name' => 'Netherlands',
            'is_enabled' => 1,
            'show_flag' => 1,
            'allow_new_connections' => 1,
            'verify_ssl' => 1,
        ]);
    }

    $repository = new ServerRepository();
    $assert($repository->availableCode($baseCode) === $baseCode . '-3',
        'Repeated server-form submissions were not numbered.');

    $sameCountryCount = (int)$database->query(
        'SELECT COUNT(*) FROM vpn_v2_servers WHERE code IN (?, ?) AND country_code = ?',
        [$baseCode, $baseCode . '-2', 'NL']
    )->getColumn();
    $assert($sameCountryCount === 2,
        'Two servers could not share the NL country code.');

    echo json_encode([
        'status' => 'ok',
        'cases' => ['same_country_code', 'automatic_internal_code_suffix'],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} finally {
    if ($database->inTransaction()) {
        $database->rollBack();
    }
}
