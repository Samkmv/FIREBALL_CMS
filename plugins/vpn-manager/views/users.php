<?php
require_once __DIR__ . '/partials/helpers.php';

use Fireball\VpnManager\Support\Formatter;

$users = is_array($users ?? null) ? $users : [];
$remoteClients = is_array($remote_clients ?? null) ? $remote_clients : [];
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManager::t('vpn_manager_users_title'),
    'subtitle' => $subtitle ?? '',
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <div class="border rounded-5 p-3 p-md-4">
        <h2 class="h5 mb-3"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_cms_users_title')) ?></h2>
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => [
                ['label' => 'ID'],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_user')],
                ['label' => 'Email / login'],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_active')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_expired')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_connections')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_traffic_used')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_last_activity')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_actions')],
            ],
            'rows' => array_map(static function (array $user): array {
                $label = trim((string)($user['name'] ?? '')) ?: ('#' . (int)$user['id']);
                $login = trim((string)($user['email'] ?? ''));
                $loginAlt = trim((string)($user['login'] ?? ''));
                $loginText = $login !== '' ? $login : ($loginAlt !== '' ? $loginAlt : '-');
                return [
                    'cells' => [
                        ['value' => '#' . (int)$user['id']],
                        ['html' => '<span class="fw-medium">' . htmlSC($label) . '</span><div class="small text-body-secondary">' . htmlSC((string)(int)$user['subscription_count']) . ' ' . htmlSC(FireballPluginVpnManager::t('vpn_manager_col_subscriptions')) . '</div>'],
                        ['value' => $loginText],
                        ['value' => (string)(int)($user['active_count'] ?? 0)],
                        ['value' => (string)(int)($user['expired_count'] ?? 0)],
                        ['value' => (string)(int)($user['connection_count'] ?? 0)],
                        ['value' => Formatter::bytes((int)($user['traffic_used_bytes'] ?? 0))],
                        ['value' => Formatter::dateTime((string)($user['last_seen_at'] ?? $user['latest_expires_at'] ?? ''))],
                        ['html' => vpnm_actions_dropdown([
                            ['label' => FireballPluginVpnManager::t('vpn_manager_action_create_subscription'), 'href' => base_href('/admin/plugins/vpn-manager/subscriptions/create'), 'icon' => 'ci-plus'],
                        ])],
                    ],
                ];
            }, $users),
            'empty_text' => FireballPluginVpnManager::t('vpn_manager_empty_users'),
        ]) ?>
    </div>

    <div class="border rounded-5 p-3 p-md-4 mt-4">
        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h5 mb-1"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_remote_users_title')) ?></h2>
                <p class="text-body-secondary mb-0"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_remote_users_subtitle')) ?></p>
            </div>
        </div>
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => [
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_client_email')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_servers')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_active')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_connections')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_traffic_used')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_last_sync')],
            ],
            'rows' => array_map(static function (array $client): array {
                return [
                    'cells' => [
                        ['html' => '<span class="fw-medium">' . htmlSC((string)($client['client_email'] ?? '-')) . '</span>' . (!empty($client['client_remark']) ? '<div class="small text-body-secondary">' . htmlSC((string)$client['client_remark']) . '</div>' : '')],
                        ['value' => trim((string)($client['server_names'] ?? '')) !== '' ? (string)$client['server_names'] : '-'],
                        ['value' => (string)(int)($client['active_count'] ?? 0)],
                        ['value' => (string)(int)($client['connection_count'] ?? 0)],
                        ['value' => Formatter::bytes((int)($client['traffic_used_bytes'] ?? 0))],
                        ['value' => Formatter::dateTime((string)($client['last_seen_at'] ?? ''))],
                    ],
                ];
            }, $remoteClients),
            'empty_text' => FireballPluginVpnManager::t('vpn_manager_empty_remote_users'),
        ]) ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
