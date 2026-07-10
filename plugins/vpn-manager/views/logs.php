<?php
require_once __DIR__ . '/partials/helpers.php';

use Fireball\VpnManager\Support\Formatter;

$events = is_array($events ?? null) ? $events : [];
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManager::t('vpn_manager_logs_title'),
    'subtitle' => $subtitle ?? '',
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <div class="border rounded-5 p-3 p-md-4">
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => [
                ['label' => 'ID'],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_event')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_message')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_admin')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_user')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_created_at')],
            ],
            'rows' => array_map(static function (array $event): array {
                return [
                    'cells' => [
                        ['value' => '#' . (int)$event['id']],
                        ['value' => (string)$event['event_type']],
                        ['html' => '<span class="text-break">' . htmlSC((string)$event['message']) . '</span>'],
                        ['value' => (string)($event['admin_name'] ?? '-')],
                        ['value' => (string)($event['user_name'] ?? '-')],
                        ['value' => Formatter::dateTime((string)($event['created_at'] ?? ''))],
                    ],
                ];
            }, $events),
            'empty_text' => FireballPluginVpnManager::t('vpn_manager_empty_logs'),
        ]) ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
