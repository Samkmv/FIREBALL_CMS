<?php
require_once __DIR__ . '/partials/helpers.php';

use Fireball\VpnManager\Support\Formatter;

$users = is_array($users ?? null) ? $users : [];
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManager::t('vpn_manager_users_title'),
    'subtitle' => $subtitle ?? '',
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <div class="border rounded-5 p-3 p-md-4">
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => [
                ['label' => 'ID'],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_user')],
                ['label' => 'Email / login'],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_active')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_expired')],
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

<?= view()->renderPartial('admin/shell_close') ?>
