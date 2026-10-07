<?php

use Fireball\VpnManagerV2\Support\AdminActionDropdown;
use Fireball\VpnManagerV2\Support\LocalizedValue;

$externalBase = '/admin/plugins/vpn-manager-v2/plans/' . (int)$externalOwnerId . '/external';
$externalSourceRows = [];
$externalSourceCards = [];
$externalT = static fn(string $key): string => FireballPluginVpnManagerV2::t($key);
foreach ($externalSourceItems as $sourceItem) {
    $sourceId = (int)$sourceItem['id'];
    $sourceActive = !empty($sourceItem['is_enabled']) && (string)$sourceItem['sync_status'] === 'synced';
    $sourceBadge = '<span class="badge rounded-pill text-bg-' . ($sourceActive ? 'success' : (!empty($sourceItem['is_enabled']) ? 'warning' : 'secondary')) . '">'
        . htmlSC(LocalizedValue::syncStatus($sourceItem['sync_status'])) . '</span>';
    $sourceActions = $externalCanManage ? [
        ['label' => $externalT('vpn_manager_v2_dependency_sync'), 'type' => 'form',
            'action' => base_href($externalBase . '/' . $sourceId . '/sync'), 'icon' => 'ci-refresh-cw'],
        ['label' => $externalT(!empty($sourceItem['is_enabled']) ? 'vpn_manager_v2_action_disable' : 'vpn_manager_v2_action_enable'),
            'type' => 'form', 'action' => base_href($externalBase . '/' . $sourceId . '/toggle'),
            'hidden' => ['is_enabled' => !empty($sourceItem['is_enabled']) ? '0' : '1'],
            'icon' => !empty($sourceItem['is_enabled']) ? 'ci-pause-circle' : 'ci-play-circle'],
        ['type' => 'divider'],
        ['label' => $externalT('vpn_manager_v2_dependency_detach'), 'type' => 'form',
            'action' => base_href($externalBase . '/' . $sourceId . '/detach'), 'icon' => 'ci-trash', 'class' => 'text-danger'],
    ] : [];
    $sourceLastSync = (string)(($sourceItem['last_sync_at'] ?? '') ?: '—');
    $sourceError = !empty($sourceItem['last_error']) ? '<div class="small text-danger mt-1 text-break">' . htmlSC((string)$sourceItem['last_error']) . '</div>' : '';
    $externalSourceRows[] = ['cells' => [
        ['html' => '<div class="fw-medium text-break">' . htmlSC((string)$sourceItem['name']) . '</div><div class="small text-body-secondary">' . htmlSC(LocalizedValue::externalSourceType($sourceItem['source_type'])) . '</div>'],
        ['html' => '<code class="small text-break">' . htmlSC((string)$sourceItem['source_preview']) . '</code>'],
        ['html' => $sourceBadge . $sourceError], ['value' => (string)(int)$sourceItem['config_count']],
        ['value' => $sourceLastSync], ['html' => AdminActionDropdown::render($sourceActions), 'class' => 'text-end'],
    ]];
    $externalSourceCards[] = [
        'title' => (string)$sourceItem['name'],
        'actions' => $sourceActions,
        'extra_fields' => [
            ['label' => $externalT('vpn_manager_v2_dependency_type'), 'value' => LocalizedValue::externalSourceType($sourceItem['source_type'])],
            ['label' => $externalT('vpn_manager_v2_external_source'), 'value' => (string)$sourceItem['source_preview']],
            ['label' => $externalT('vpn_manager_v2_col_status'), 'html' => $sourceBadge . $sourceError],
            ['label' => $externalT('vpn_manager_v2_external_config_count'), 'value' => (string)(int)$sourceItem['config_count']],
            ['label' => $externalT('vpn_manager_v2_col_last_sync'), 'value' => $sourceLastSync],
        ],
    ];
}
?>
<section class="border rounded-5 p-3 p-md-4 mt-4 mb-4" id="external-sources" data-vpn-v2-plan-external>
    <h2 class="h6 mb-1"><?= htmlSC($externalHeading) ?></h2>
    <p class="small text-body-secondary mb-4"><?= htmlSC($externalHelp) ?></p>
    <?php if ($externalCanManage): ?>
        <div class="row g-3 mb-4">
            <?php foreach (['subscription', 'connection'] as $externalType): ?>
                <?php $isExternalSubscription = $externalType === 'subscription'; $externalInputId = 'vpnV2PlanExternal' . ucfirst($externalType) . (int)$externalOwnerId; ?>
                <div class="col-xl-6">
                    <form class="border rounded-4 p-3 h-100" method="post" action="<?= htmlSC(base_href($externalBase . '/' . $externalType)) ?>">
                        <?= get_csrf_field() ?>
                        <label class="form-label fw-semibold" for="<?= $externalInputId ?>"><?= htmlSC($externalT($isExternalSubscription ? 'vpn_manager_v2_add_external_subscription' : 'vpn_manager_v2_add_external_connection')) ?></label>
                        <label class="visually-hidden" for="<?= $externalInputId ?>Name"><?= htmlSC($externalT('vpn_manager_v2_col_name')) ?></label>
                        <input class="form-control mb-2" id="<?= $externalInputId ?>Name" name="name" maxlength="160" placeholder="<?= htmlSC($externalT('vpn_manager_v2_external_name_placeholder')) ?>">
                        <?php if ($isExternalSubscription): ?>
                            <input class="form-control font-monospace" id="<?= $externalInputId ?>" name="source_url" type="url" inputmode="url" maxlength="2048" required placeholder="https://example.com/subscription/token">
                        <?php else: ?>
                            <textarea class="form-control font-monospace" id="<?= $externalInputId ?>" name="connection_uri" rows="3" maxlength="16384" required placeholder="vless://…  vmess://…  trojan://…  ss://…"></textarea>
                        <?php endif; ?>
                        <div class="form-text mb-3"><?= htmlSC($externalT($isExternalSubscription ? 'vpn_manager_v2_external_subscription_help' : 'vpn_manager_v2_external_connection_help')) ?></div>
                        <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="<?= $isExternalSubscription ? 'ci-link' : 'ci-plus' ?>" aria-hidden="true"></i><?= htmlSC($externalT('vpn_manager_v2_add')) ?></button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($externalSourceItems) > 1): ?>
            <form class="border rounded-4 p-3 mb-4" method="post" action="<?= htmlSC(base_href($externalBase . '/order')) ?>" data-vpn-v2-connection-order>
                <?= get_csrf_field() ?>
                <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
                    <div><h3 class="h6 mb-1"><?= htmlSC($externalT('vpn_manager_v2_external_order_title')) ?></h3><div class="small text-body-secondary"><?= htmlSC($externalT('vpn_manager_v2_external_order_help')) ?></div></div>
                    <button class="btn btn-dark rounded-pill text-wrap" type="submit"><?= htmlSC($externalT('vpn_manager_v2_save_external_order')) ?></button>
                </div>
                <div class="list-group list-group-flush border rounded-4 overflow-hidden" data-vpn-v2-connection-order-list>
                    <?php foreach ($externalSourceItems as $sourceItem): ?>
                        <div class="list-group-item d-flex align-items-center gap-2 py-3" draggable="true" data-vpn-v2-connection-order-item>
                            <input type="hidden" name="external_source_order[]" value="<?= (int)$sourceItem['id'] ?>">
                            <i class="ci-menu text-body-tertiary" aria-hidden="true"></i>
                            <div class="flex-grow-1 text-break" style="min-width:0"><?= htmlSC((string)$sourceItem['name']) ?></div>
                            <div class="btn-group flex-shrink-0" role="group" aria-label="<?= htmlSC($externalT('vpn_manager_v2_external_order_actions')) ?>">
                                <?php foreach (['up', 'down'] as $direction): ?>
                                    <button class="btn btn-sm btn-outline-secondary btn-icon" type="button" data-vpn-v2-order-move="<?= $direction ?>" aria-label="<?= htmlSC($externalT('vpn_manager_v2_move_external_' . $direction)) ?>"><i class="ci-chevron-<?= $direction ?>" aria-hidden="true"></i></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </form>
        <?php endif; ?>
    <?php elseif ((int)$externalOwnerId > 0 && \Fireball\VpnManagerV2\Support\Permissions::allows(\Fireball\VpnManagerV2\Support\Permissions::MANAGE_PLANS)): ?>
        <a class="btn btn-sm btn-outline-secondary rounded-pill mb-3" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/plans/edit/' . (int)$externalOwnerId . '#external-sources')) ?>"><?= htmlSC($externalT('vpn_manager_v2_plan_edit_title')) ?></a>
    <?php endif; ?>
    <?= view()->renderPartial('admin/partials/table', [
        'columns' => [
            ['label' => $externalT('vpn_manager_v2_col_name')], ['label' => $externalT('vpn_manager_v2_external_source')],
            ['label' => $externalT('vpn_manager_v2_col_status')], ['label' => $externalT('vpn_manager_v2_external_config_count')],
            ['label' => $externalT('vpn_manager_v2_col_last_sync')], ['label' => $externalT('vpn_manager_v2_col_actions'), 'class' => 'text-end'],
        ],
        'rows' => $externalSourceRows, 'mobile_cards' => $externalSourceCards,
        'empty_text' => $externalT('vpn_manager_v2_external_sources_empty'),
    ]) ?>
</section>
