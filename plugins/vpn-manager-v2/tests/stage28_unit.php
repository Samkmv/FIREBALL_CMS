<?php

declare(strict_types=1);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$root = dirname(__DIR__);
$servers = (string)file_get_contents($root . '/views/admin/servers.php');
$logs = (string)file_get_contents($root . '/views/admin/sync-logs.php');
$connection = (string)file_get_contents($root . '/views/admin/connection-show.php');
$plans = (string)file_get_contents($root . '/views/admin/plans.php');
$subscriptions = (string)file_get_contents($root . '/views/admin/subscriptions.php');
$script = (string)file_get_contents($root . '/assets/vpn-manager-v2.js');

$assert(str_contains($servers, "'data-admin-delete-form' => true")
    && str_contains($servers, "'data-delete-item'")
    && str_contains($servers, "'data-delete-confirm-label'"),
    'Server deletion does not use the standard administrator modal.');
$assert(str_contains($logs, 'data-admin-delete-form')
    && str_contains($logs, 'data-confirm-item-label')
    && str_contains($logs, 'data-delete-confirm-label'),
    'Log clearing does not use the standard administrator modal.');
$assert(str_contains($connection, 'data-vpn-v2-async-operation')
    && str_contains($connection, 'data-admin-delete-form')
    && str_contains($connection, 'data-confirm-title')
    && str_contains($connection, 'vpn_manager_v2_modal_client_label')
    && str_contains($connection, 'data-confirm-variant="warning"')
    && str_contains($connection, 'data-confirm-icon="ci-rotate-ccw"'),
    'Traffic reset does not use the administrator modal or warning presentation.');
$assert(str_contains($plans, "'data-admin-delete-form' => true")
    && str_contains($subscriptions, "'data-admin-delete-form' => true"),
    'Existing plan or subscription removals no longer use the administrator modal.');
$assert(!str_contains($script, 'window.confirm(')
    && !str_contains($script, 'data-vpn-v2-confirm-submit')
    && str_contains($script, "form.dataset.deleteConfirmed !== '1'")
    && str_contains($script, 'Modal.getOrCreateInstance(modalElement).hide()'),
    'Legacy browser confirmations remain or asynchronous modal confirmation is incomplete.');

$manifest = json_decode((string)file_get_contents($root . '/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
$assert(version_compare((string)($manifest['version'] ?? '0.0.0'), '0.24.1', '>='),
    'The administrator modal release version was not published.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'server_delete_modal',
        'log_clear_modal',
        'traffic_reset_modal',
        'existing_delete_modals_preserved',
        'native_confirms_removed',
        'async_confirmation_bridge',
        'plugin_version',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
