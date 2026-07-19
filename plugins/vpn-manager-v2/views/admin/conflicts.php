<?php
$conflicts = is_array($conflicts ?? null) ? $conflicts : [];
$unmanagedClients = is_array($unmanagedClients ?? null) ? $unmanagedClients : [];
$connections = is_array($connections ?? null) ? $connections : [];
$connectionsByServer = [];
foreach ($connections as $connection) {
    $connectionsByServer[(int)$connection['server_id']][] = $connection;
}
$rows = [];
foreach ($conflicts as $conflict) {
    $rows[] = ['cells' => [
        ['value' => '#' . (int)$conflict['id']],
        ['value' => (string)$conflict['conflict_type']],
        ['value' => !empty($conflict['server_id']) ? '#' . (int)$conflict['server_id'] . ' · ' . (string)($conflict['server_name'] ?? '') : '—'],
        ['value' => !empty($conflict['subscription_id']) ? '#' . (int)$conflict['subscription_id'] : '—'],
        ['value' => !empty($conflict['connection_id']) ? '#' . (int)$conflict['connection_id'] : '—'],
        ['value' => (string)($conflict['local_value'] ?? '—')],
        ['value' => (string)($conflict['remote_value'] ?? '—')],
        ['value' => (string)($conflict['recommended_action'] ?? '—')],
        ['value' => (string)$conflict['status']],
        ['value' => (string)$conflict['detected_at']],
    ]];
}
?>
<?= view()->renderPartial('admin/shell_open', ['title' => $title ?? '', 'subtitle' => $subtitle ?? '']) ?>
<?php require __DIR__ . '/partials/tabs.php'; ?>
<div data-vpn-v2-operation-alert aria-live="polite"></div>

<?php if ($unmanagedClients !== []): ?>
    <section class="border rounded-5 p-3 p-md-4 mb-4">
        <div class="mb-3">
            <h2 class="h5 mb-1"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_unmanaged_clients_title')) ?></h2>
            <p class="small text-body-secondary mb-0"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_unmanaged_clients_help')) ?></p>
        </div>
        <div class="vstack gap-3">
            <?php foreach ($unmanagedClients as $remote): ?>
                <?php $choices = $connectionsByServer[(int)$remote['server_id']] ?? []; ?>
                <form class="row g-2 align-items-end border rounded-4 p-3" method="post"
                      action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/conflicts/link')) ?>"
                      data-vpn-v2-async-operation>
                    <?= get_csrf_field() ?>
                    <input type="hidden" name="remote_client_id" value="<?= (int)$remote['id'] ?>">
                    <div class="col-lg-5">
                        <div class="fw-semibold"><?= htmlSC((string)($remote['remote_client_name'] ?: ('#' . (int)$remote['id']))) ?></div>
                        <div class="small text-body-secondary">
                            <?= htmlSC((string)$remote['server_name']) ?> · <?= htmlSC((string)$remote['inbound_name']) ?>
                            <?php if (!empty($remote['credential_preview'])): ?> · <code><?= htmlSC((string)$remote['credential_preview']) ?></code><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label small" for="vpnV2ManualLink<?= (int)$remote['id'] ?>"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_manual_link_connection')) ?></label>
                        <select class="form-select" id="vpnV2ManualLink<?= (int)$remote['id'] ?>" name="connection_id" required>
                            <option value=""><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_manual_link_choose')) ?></option>
                            <?php foreach ($choices as $connection): ?>
                                <option value="<?= (int)$connection['id'] ?>">
                                    #<?= (int)$connection['id'] ?> · <?= htmlSC((string)$connection['user_name']) ?> · <?= htmlSC((string)$connection['plan_name']) ?> · <?= htmlSC((string)$connection['inbound_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2 d-grid">
                        <button class="btn btn-outline-primary rounded-pill" type="submit" <?= $choices === [] ? 'disabled' : '' ?>>
                            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_manual_link_action')) ?>
                        </button>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<div class="border rounded-5 p-3 p-md-4">
<?= view()->renderPartial('admin/partials/table', [
    'columns' => [
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_id')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_conflict_type')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_server')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_subscription')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_connection_id')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_local_value')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_remote_value')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_recommended_action')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_status')],
        ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_detected_at')],
    ],
    'rows' => $rows,
    'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_empty_conflicts'),
]) ?>
</div>
<?= view()->renderPartial('admin/shell_close') ?>
