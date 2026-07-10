<?php
require_once __DIR__ . '/partials/helpers.php';

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
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_subscriptions')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_active')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_latest_expire')],
            ],
            'rows' => array_map(static function (array $user): array {
                return [
                    'cells' => [
                        ['value' => '#' . (int)$user['id']],
                        ['html' => '<span class="fw-medium">' . htmlSC((string)$user['name']) . '</span><div class="small text-body-secondary">' . htmlSC((string)$user['email']) . '</div>'],
                        ['value' => (string)(int)$user['subscription_count']],
                        ['value' => (string)(int)$user['active_count']],
                        ['value' => (string)($user['latest_expires_at'] ?? '-')],
                    ],
                ];
            }, $users),
            'empty_text' => FireballPluginVpnManager::t('vpn_manager_empty_users'),
        ]) ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
