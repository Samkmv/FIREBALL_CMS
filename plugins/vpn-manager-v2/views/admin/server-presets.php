<?php

use Fireball\VpnManagerV2\Support\AdminActionDropdown;
use Fireball\VpnManagerV2\Support\Permissions;

$t = static fn(string $key): string => htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_' . $key));
$base = base_href('/admin/plugins/vpn-manager-v2/servers/presets');
$canEdit = Permissions::allows(Permissions::MANAGE_SERVERS) && Permissions::allows(Permissions::MANAGE_PLANS);
$actions = $canEdit ? '<a class="btn btn-primary d-inline-flex align-items-center gap-2" href="' . htmlSC($base . '/create') . '">'
    . '<i class="ci-plus" aria-hidden="true"></i>' . $t('preset_add') . '</a>' : '';

$rows = $mobileCards = [];
foreach ($presets as $preset) {
    $url = $base . '/' . (int)$preset['id'];
    $name = htmlSC($preset['name']);
    $titleHtml = $canEdit ? '<a class="fw-semibold text-break" href="' . htmlSC($url) . '">' . $name . '</a>' : $name;
    $nodeCount = (int)$preset['node_count'];
    $planCount = (int)$preset['plan_count'];
    $nodesHtml = $nodeCount > 0 ? (string)$nodeCount : '<span class="badge rounded-pill text-bg-warning">' . $t('preset_no_nodes') . '</span>';
    $menu = [];
    if ($canEdit) {
        $menu[] = ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_action_edit'), 'href' => $url, 'icon' => 'ci-edit'];
        if ($planCount === 0) {
            $menu[] = [
                'label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_preset_delete'),
                'type' => 'form',
                'action' => $url . '/delete',
                'icon' => 'ci-trash',
                'class' => 'text-danger',
                'form_attributes' => [
                    'data-admin-delete-form' => true,
                    'data-delete-message' => FireballPluginVpnManagerV2::t('vpn_manager_v2_preset_confirm_delete'),
                    'data-delete-item' => $preset['name'],
                ],
            ];
        }
    }
    $rows[] = ['cells' => [
        ['html' => $titleHtml], ['html' => $nodesHtml], ['value' => (string)$planCount],
        ['html' => '<div class="text-end">' . AdminActionDropdown::render($menu) . '</div>'],
    ]];
    $mobileCards[] = [
        'title' => ['html' => $titleHtml], 'actions' => $menu,
        'extra_fields' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_tab_connections'), 'html' => $nodesHtml],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_tab_plans'), 'value' => (string)$planCount],
        ],
    ];
}
?>
<?= view()->renderPartial('admin/shell_open', ['title' => $title, 'actions' => $actions]) ?>
<?php require __DIR__ . '/partials/tabs.php'; ?>
<a class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-2 mb-3" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers')) ?>">
    <i class="ci-arrow-left" aria-hidden="true"></i><?= $t('tab_servers') ?>
</a>
<p class="small text-body-secondary"><?= $t('presets_help') ?></p>
<?= view()->renderPartial('admin/partials/table', [
    'columns' => [
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_preset_name')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_tab_connections')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_tab_plans')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_actions'), 'class' => 'text-end'],
    ],
    'rows' => $rows, 'mobile_cards' => $mobileCards,
    'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_presets_empty'),
]) ?>
<?= view()->renderPartial('admin/shell_close') ?>
