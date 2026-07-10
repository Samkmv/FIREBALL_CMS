<?php
require_once __DIR__ . '/partials/helpers.php';

use Fireball\VpnManager\Support\Formatter;

$inbounds = is_array($inbounds ?? null) ? $inbounds : [];
$servers = is_array($servers ?? null) ? $servers : [];
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManager::t('vpn_manager_inbounds_title'),
    'subtitle' => $subtitle ?? '',
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <div class="border rounded-5 p-3 p-md-4 mb-4">
        <h2 class="h5 mb-3"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_sync_inbounds_title')) ?></h2>
        <p class="text-body-secondary mb-3"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_sync_inbounds_text')) ?></p>
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($servers as $server): ?>
                <form action="<?= base_href('/admin/plugins/vpn-manager/servers/sync-inbounds') ?>" method="post">
                    <?= get_csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$server['id'] ?>">
                    <button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                        <i class="ci-refresh-cw"></i><?= htmlSC((string)$server['name']) ?>
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="border rounded-5 p-3 p-md-4">
        <?= view()->renderPartial('admin/partials/table', [
            'columns' => [
                ['label' => 'ID'],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_server')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_remote_id')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_name')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_protocol')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_port')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_status')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_col_updated_at')],
                ['label' => FireballPluginVpnManager::t('vpn_manager_actions')],
            ],
            'rows' => array_map(static function (array $item): array {
                $status = (string)($item['status'] ?? (!empty($item['is_enabled']) ? 'active' : 'disabled'));
                if (empty($item['is_enabled']) && $status === 'active') {
                    $status = 'disabled';
                }
                return [
                    'cells' => [
                        ['value' => '#' . (int)$item['id']],
                        ['value' => (string)($item['server_name'] ?? '-')],
                        ['value' => '#' . (string)($item['remote_inbound_id'] ?? '-')],
                        ['html' => '<span class="fw-medium">' . htmlSC((string)$item['name']) . '</span><div class="small text-body-secondary">' . htmlSC((string)($item['remark'] ?? '')) . '</div>'],
                        ['value' => (string)($item['protocol'] ?? '-')],
                        ['value' => (string)($item['port'] ?? '-')],
                        ['html' => vpnm_status_badge($status)],
                        ['value' => Formatter::dateTime((string)($item['updated_at'] ?? ''))],
                        ['html' => vpnm_actions_dropdown([
                            ['label' => FireballPluginVpnManager::t('vpn_manager_action_open'), 'href' => base_href('/admin/plugins/vpn-manager/inbounds/' . (int)$item['id']), 'icon' => 'ci-eye'],
                            ['label' => FireballPluginVpnManager::t('vpn_manager_action_sync_inbounds'), 'type' => 'form', 'action' => base_href('/admin/plugins/vpn-manager/servers/sync-inbounds'), 'hidden' => ['id' => (int)$item['server_id']], 'icon' => 'ci-refresh-cw'],
                            ['label' => !empty($item['is_enabled']) ? FireballPluginVpnManager::t('vpn_manager_action_disable') : FireballPluginVpnManager::t('vpn_manager_action_enable'), 'type' => 'form', 'action' => base_href('/admin/plugins/vpn-manager/inbounds/toggle'), 'hidden' => ['id' => (int)$item['id']], 'icon' => 'ci-power'],
                            ['label' => FireballPluginVpnManager::t('vpn_manager_action_view_json'), 'href' => base_href('/admin/plugins/vpn-manager/inbounds/' . (int)$item['id']), 'icon' => 'ci-code'],
                        ])],
                    ],
                ];
            }, $inbounds),
            'empty_text' => FireballPluginVpnManager::t('vpn_manager_empty_inbounds'),
        ]) ?>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
