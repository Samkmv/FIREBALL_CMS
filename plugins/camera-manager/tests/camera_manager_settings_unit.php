<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/Plugins/PluginInterface.php';
require_once dirname(__DIR__, 3) . '/app/Services/SqlFileRunner.php';

$GLOBALS['camera_manager_settings_store'] = [];

function plugin_setting(string $slug, string $key, mixed $default = null): mixed
{
    return $GLOBALS['camera_manager_settings_store'][$key] ?? $default;
}

function plugin_setting_set(string $slug, string $key, mixed $value): void
{
    $GLOBALS['camera_manager_settings_store'][$key] = $value;
}

require_once dirname(__DIR__) . '/Plugin.php';

foreach (FireballPluginCameraManager::defaultSettings() as $key => $value) {
    plugin_setting_set(FireballPluginCameraManager::SLUG, $key, $value);
}

$token = str_repeat('ab', 32);
FireballPluginCameraManager::saveSettings([
    'connection_mode' => 'pull',
    'hls_base_url' => 'https://rtsp.ddns.net/rtsp',
    'service_name' => 'rtsp-streams.service',
    'pull_token' => $token,
    'streams_file_path' => '/var/www/html/rtsp/streams.pl',
    'perl_binary' => '/usr/bin/perl',
    'ssh_binary' => '/usr/bin/ssh',
    'ssh_host' => 'rtsp.ddns.net',
    'ssh_port' => 22,
    'ssh_user' => 'camera-sync',
    'ssh_identity_file' => '/var/www/.ssh/fireball-camera-manager',
    'ssh_known_hosts_file' => '/var/www/.ssh/known_hosts',
]);

$storedHash = (string)plugin_setting(FireballPluginCameraManager::SLUG, 'pull_token_hash', '');
if (!hash_equals(hash('sha256', $token), $storedHash)) {
    throw new RuntimeException('Camera Manager did not store the HTTPS token hash.');
}

$settings = FireballPluginCameraManager::settings();
if (empty($settings['pull_token_configured']) || array_key_exists('pull_token_hash', $settings)) {
    throw new RuntimeException('Camera Manager token status or redaction is incorrect.');
}

echo "Camera Manager settings save check passed.\n";
