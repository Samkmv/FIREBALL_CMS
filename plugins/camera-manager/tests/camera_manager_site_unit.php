<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/Plugins/PluginInterface.php';
require_once dirname(__DIR__, 3) . '/app/Services/SqlFileRunner.php';

if (!defined('CHAT_ENCRYPTION_KEY')) {
    define('CHAT_ENCRYPTION_KEY', 'camera-manager-site-test-key');
}

final class CameraManagerSiteFakeDatabase
{
    public ?array $insert = null;
    private mixed $result = null;

    public function query(string $sql, array $params = []): self
    {
        if (str_starts_with($sql, 'SELECT id FROM camera_manager_sites')) {
            $this->result = null;
            return $this;
        }
        if (str_starts_with($sql, 'INSERT INTO camera_manager_sites')) {
            $this->insert = ['sql' => $sql, 'params' => $params];
            $this->result = null;
            return $this;
        }
        throw new RuntimeException('Unexpected SQL in site test: ' . $sql);
    }

    public function getOne(): mixed
    {
        return $this->result;
    }

    public function getInsertId(): int
    {
        return 73;
    }
}

$GLOBALS['camera_manager_site_db'] = new CameraManagerSiteFakeDatabase();
$GLOBALS['camera_manager_site_settings'] = [];

function db(): CameraManagerSiteFakeDatabase
{
    return $GLOBALS['camera_manager_site_db'];
}

function plugin_setting(string $slug, string $key, mixed $default = null): mixed
{
    return $GLOBALS['camera_manager_site_settings'][$key] ?? $default;
}

function plugin_setting_set(string $slug, string $key, mixed $value): void
{
    $GLOBALS['camera_manager_site_settings'][$key] = $value;
}

require_once dirname(__DIR__) . '/Plugin.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$throws = static function (callable $callback, string $message) use ($assert): void {
    $thrown = false;
    try {
        $callback();
    } catch (RuntimeException) {
        $thrown = true;
    }
    $assert($thrown, $message);
};

$id = FireballPluginCameraManager::saveSite([
    'code' => 'legacy-34',
    'name' => 'Старый объект',
    'recorder_ip' => '192.168.34.100',
    'rtsp_port' => 554,
    'rtsp_username' => 'operator',
    'rtsp_password' => 'secret-for-encryption-test',
    'rtsp_path_template' => '/cam/realmonitor?channel={channel}&subtype={subtype}',
    'enabled' => 1,
]);
$assert($id === 73 && is_array(db()->insert), 'Backward-compatible site input was not inserted.');
$params = db()->insert['params'];
$assert(count($params) === 22, 'Site insert does not match migration 002 columns.');
$assert($params[6] === null && $params[9] === null && $params[10] === null && $params[11] === null, 'New optional network fields are not default-safe.');
$assert($params[15] === 'custom' && $params[16] === 'camera' && $params[17] === 'not_configured', 'Legacy RTSP/template behavior was not preserved.');
$assert(!str_contains((string)$params[13], 'secret-for-encryption-test'), 'RTSP password was stored in plaintext.');

$throws(static fn() => FireballPluginCameraManager::saveSite([
    'code' => '34', 'name' => 'Bad CIDR', 'recorder_ip' => '192.168.34.100',
    'rtsp_username' => 'u', 'rtsp_password' => 'p',
    'rtsp_path_template' => '/live/{channel}', 'lan_cidr' => '192.168.34.10/24',
]), 'Invalid site CIDR was accepted.');
$throws(static fn() => FireballPluginCameraManager::saveSite([
    'code' => '34', 'name' => 'Bad port', 'recorder_ip' => '192.168.34.100',
    'rtsp_username' => 'u', 'rtsp_password' => 'p',
    'rtsp_path_template' => '/live/{channel}', 'external_rtsp_port' => 70000,
]), 'Invalid external RTSP port was accepted.');
$throws(static fn() => FireballPluginCameraManager::saveSite([
    'code' => '34', 'name' => 'Bad key', 'recorder_ip' => '192.168.34.100',
    'rtsp_username' => 'u', 'rtsp_password' => 'p',
    'rtsp_path_template' => '/live/{channel}', 'wireguard_public_key' => 'not-a-key',
]), 'Invalid WireGuard PublicKey was accepted.');

echo "Camera Manager site compatibility checks passed.\n";
