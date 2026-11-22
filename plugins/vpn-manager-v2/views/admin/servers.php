<?php

use Fireball\VpnManagerV2\Support\CountryFlag;
use Fireball\VpnManagerV2\Support\AdminActionDropdown;

$servers = is_array($servers ?? null) ? $servers : [];
$addUrl = base_href('/admin/plugins/vpn-manager-v2/servers/create');
$actions = '<a class="btn btn-primary d-inline-flex align-items-center gap-2" href="'
    . htmlSC($addUrl) . '"><i class="ci-plus" aria-hidden="true"></i><span>'
    . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_add_server')) . '</span></a>';
$actions = '<a class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" href="'
    . htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers/presets')) . '"><i class="ci-layers" aria-hidden="true"></i>'
    . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_presets_title')) . '</a>' . $actions;

$statusBadge = static function (array $server): string {
    $status = empty($server['is_enabled']) ? 'disabled' : (string)($server['status'] ?? 'unchecked');
    if ($status !== 'disabled' && !empty($server['maintenance_mode'])) {
        return '<span class="badge rounded-pill text-bg-warning">'
            . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_subscription_notice_maintenance')) . '</span>';
    }
    $classes = [
        'online' => 'text-bg-success',
        'offline' => 'text-bg-danger',
        'error' => 'text-bg-warning',
        'disabled' => 'text-bg-secondary',
        'unchecked' => 'text-bg-light border text-body-secondary',
    ];
    $key = array_key_exists($status, $classes) ? $status : 'unchecked';

    return '<span class="badge rounded-pill ' . $classes[$key] . '">'
        . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_status_' . $key)) . '</span>';
};

$serverActions = static function (array $server): array {
    $id = (int)$server['id'];

    return [
        [
            'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_action'),
            'href' => base_href('/admin/plugins/vpn-manager-v2/servers/' . $id . '/recovery'),
            'icon' => 'ci-refresh-cw',
        ],
        [
            'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_edit'),
            'href' => base_href('/admin/plugins/vpn-manager-v2/servers/edit/' . $id),
            'icon' => 'ci-edit',
        ],
        [
            'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_test'),
            'type' => 'form',
            'action' => base_href('/admin/plugins/vpn-manager-v2/servers/test'),
            'hidden' => ['id' => $id],
            'icon' => 'ci-activity',
        ],
        [
            'label' => FireballPluginVpnManagerV2::t(!empty($server['is_enabled'])
                ? 'vpn_manager_v2_action_disable'
                : 'vpn_manager_v2_action_enable'),
            'type' => 'form',
            'action' => base_href('/admin/plugins/vpn-manager-v2/servers/toggle'),
            'hidden' => ['id' => $id],
            'icon' => 'ci-power',
        ],
        [
            'label' => 'Xray-core · перенос',
            'href' => base_href('/admin/plugins/vpn-manager-v2/servers/' . $id . '/xray'),
            'icon' => 'ci-server',
        ],
        ['type' => 'divider'],
        [
            'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_delete_server'),
            'href' => base_href('/admin/plugins/vpn-manager-v2/servers/' . $id . '/delete'),
            'icon' => 'ci-trash',
            'class' => 'text-danger',
        ],
    ];
};

$rows = [];
$mobileCards = [];
foreach ($servers as $server) {
    $id = (int)($server['id'] ?? 0);
    $flag = !empty($server['show_flag']) ? CountryFlag::emoji($server['country_code'] ?? null) : '';
    $locationParts = array_filter([
        trim($flag . ' ' . (string)($server['country_name'] ?? '')),
        trim((string)($server['city'] ?? '')),
    ], static fn(string $value): bool => $value !== '');
    $location = implode(', ', $locationParts) ?: '-';
    $panel = rtrim((string)($server['panel_url'] ?? ''), '/') . (string)($server['panel_path'] ?? '');
    $authType = (string)($server['auth_type'] ?? 'token');
    $authLabel = $authType === 'xray' ? 'Xray-core · FIREBALL Agent' : FireballPluginVpnManagerV2::t(
        $authType === 'password' ? 'vpn_manager_v2_auth_password' : 'vpn_manager_v2_auth_token'
    );
    $lastCheck = trim((string)($server['last_check_at'] ?? ''));
    $lastCheckHtml = htmlSC($lastCheck !== '' ? $lastCheck : FireballPluginVpnManagerV2::t('vpn_manager_v2_never'));
    if (!empty($server['last_error'])) {
        $lastCheckHtml .= '<div class="small text-danger text-break mt-1">'
            . htmlSC((string)$server['last_error']) . '</div>';
    }
    $badge = $statusBadge($server);
    $counts = (int)($server['inbound_count'] ?? 0) . ' / ' . (int)($server['client_count'] ?? 0);
    $serverLink = '<a class="fw-semibold text-break" href="'
        . htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers/' . $id . '/recovery')) . '">'
        . htmlSC((string)$server['name']) . '</a>';
    $lastSync = trim((string)($server['last_sync_at'] ?? '')) ?: FireballPluginVpnManagerV2::t('vpn_manager_v2_never');

    $technical = '<details><summary class="small text-body-secondary">'
        . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_technical_title')) . '</summary>'
        . '<div class="small text-break">' . htmlSC($panel) . '</div>'
        . '<div class="small text-body-secondary">' . htmlSC($authLabel) . '</div>'
        . '<div class="small text-body-secondary">' . htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_last_sync'))
        . ' · ' . htmlSC($lastSync) . '</div></details>';

    $rows[] = [
        'cells' => [
            ['html' => $serverLink . '<div class="small text-body-secondary text-break">#' . $id . ' · ' . htmlSC($location) . '</div>'
                . $technical],
            ['html' => $badge],
            ['value' => $counts],
            ['html' => $lastCheckHtml],
            ['html' => '<div class="text-end">' . AdminActionDropdown::render($serverActions($server)) . '</div>'],
        ],
    ];

    $mobileCards[] = [
        'id' => (string)$id,
        'title' => ['html' => $serverLink],
        'status' => [['html' => $badge]],
        'actions' => $serverActions($server),
        'extra_fields' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_location'), 'value' => $location],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_inbounds_clients'), 'value' => $counts],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_last_check'), 'html' => $lastCheckHtml],
        ],
    ];
}
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManagerV2::t('vpn_manager_v2_servers_title'),
    'actions' => $actions,
]) ?>

<?php require __DIR__ . '/partials/tabs.php'; ?>

<?php require __DIR__ . '/partials/operation-alert.php'; ?>

<?= view()->renderPartial('admin/partials/table', [
    'columns' => [
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_name')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_status')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_inbounds_clients')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_last_check')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_actions'), 'class' => 'text-end'],
    ],
    'rows' => $rows,
    'mobile_cards' => $mobileCards,
    'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_empty_servers'),
]) ?>

<?= view()->renderPartial('admin/shell_close') ?>
