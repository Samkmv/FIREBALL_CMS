<?php

use Fireball\VpnManagerV2\Support\ProvisioningStatus;
use Fireball\VpnManagerV2\Support\TrafficFormatter;

$connection = is_array($connection ?? null) ? $connection : [];
$id = (int)($connection['id'] ?? 0);
$flow = trim((string)($connection['flow'] ?? '')) ?: FireballPluginVpnManagerV2::t('vpn_manager_v2_flow_none');
?>

<?= view()->renderPartial('admin/shell_open', ['title' => $title ?? '', 'subtitle' => $subtitle ?? '']) ?>
<?php require __DIR__ . '/partials/tabs.php'; ?>

<div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/connections')) ?>">
        <i class="ci-arrow-left" aria-hidden="true"></i> <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_back_to_connections')) ?>
    </a>
    <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . (int)$connection['subscription_id'])) ?>">
        <i class="ci-link" aria-hidden="true"></i> <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_open_subscription')) ?> #<?= (int)$connection['subscription_id'] ?>
    </a>
    <a class="btn btn-dark rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/connections/' . $id . '/edit')) ?>">
        <i class="ci-edit-2" aria-hidden="true"></i> <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_edit')) ?>
    </a>
    <?php if (ProvisioningStatus::canRetry((string)($connection['status'] ?? ''))): ?>
        <form method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/connections/' . $id . '/retry')) ?>">
            <?= get_csrf_field() ?>
            <button class="btn btn-warning rounded-pill" type="submit">
                <i class="ci-refresh-cw" aria-hidden="true"></i> <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_retry_creation')) ?>
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="border rounded-5 p-3 p-md-4 mb-4">
    <h2 class="h5 mb-2"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_sync_title')) ?></h2>
    <p class="text-body-secondary mb-3"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_sync_modes_help')) ?></p>
    <div class="d-flex flex-wrap gap-2">
        <form method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/connections/' . $id . '/sync')) ?>">
            <?= get_csrf_field() ?>
            <input type="hidden" name="mode" value="pull">
            <button class="btn btn-outline-secondary rounded-pill" type="submit">
                <i class="ci-download" aria-hidden="true"></i> <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_sync_pull')) ?>
            </button>
        </form>
        <form method="post" action="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/connections/' . $id . '/sync')) ?>">
            <?= get_csrf_field() ?>
            <input type="hidden" name="mode" value="push">
            <button class="btn btn-outline-primary rounded-pill" type="submit">
                <i class="ci-upload" aria-hidden="true"></i> <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_sync_push')) ?>
            </button>
        </form>
    </div>
</div>

<div class="border rounded-5 p-3 p-md-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div><span class="text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_connection_id')) ?></span> <strong>#<?= $id ?></strong></div>
        <?= ProvisioningStatus::badge((string)($connection['status'] ?? '')) ?>
    </div>
    <dl class="row mb-0 g-3">
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_user')) ?></dt>
        <dd class="col-sm-9 mb-0">#<?= (int)$connection['user_id'] ?> · <?= htmlSC((string)$connection['user_name']) ?> · <?= htmlSC((string)$connection['user_email']) ?></dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_plan')) ?></dt>
        <dd class="col-sm-9 mb-0">#<?= (int)$connection['plan_id'] ?> · <?= htmlSC((string)$connection['plan_name']) ?></dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_server')) ?></dt>
        <dd class="col-sm-9 mb-0">#<?= (int)$connection['server_id'] ?> · <?= htmlSC((string)$connection['server_name']) ?> (<?= htmlSC((string)$connection['server_code']) ?>)</dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_inbound')) ?></dt>
        <dd class="col-sm-9 mb-0">#<?= (int)$connection['inbound_id'] ?> · <?= htmlSC((string)$connection['inbound_name']) ?> · remote #<?= htmlSC((string)$connection['remote_inbound_id']) ?></dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_protocol')) ?></dt>
        <dd class="col-sm-9 mb-0"><?= htmlSC(strtoupper((string)$connection['protocol'])) ?></dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_transport_security')) ?></dt>
        <dd class="col-sm-9 mb-0"><?= htmlSC(strtoupper((string)($connection['network'] ?: '—'))) ?> / <?= htmlSC(strtoupper((string)($connection['security'] ?: 'none'))) ?></dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_flow')) ?></dt>
        <dd class="col-sm-9 mb-0"><?= htmlSC($flow) ?></dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_client_email')) ?></dt>
        <dd class="col-sm-9 mb-0"><code><?= htmlSC((string)$connection['client_email']) ?></code></dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_remote_client')) ?></dt>
        <dd class="col-sm-9 mb-0"><code><?= htmlSC((string)($connection['remote_client_preview'] ?: '—')) ?></code></dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_limits')) ?></dt>
        <dd class="col-sm-9 mb-0"><?= htmlSC(TrafficFormatter::limit(isset($connection['traffic_limit_bytes']) ? (int)$connection['traffic_limit_bytes'] : null)) ?> · <?= (int)$connection['device_limit'] ?> IP</dd>
        <dt class="col-sm-3 text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_last_sync')) ?></dt>
        <dd class="col-sm-9 mb-0"><?= htmlSC((string)($connection['last_sync_at'] ?: '—')) ?></dd>
        <?php if (!empty($connection['last_error'])): ?>
            <dt class="col-sm-3 text-danger"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_last_error')) ?></dt>
            <dd class="col-sm-9 mb-0 text-danger"><?= htmlSC((string)$connection['last_error']) ?></dd>
        <?php endif; ?>
    </dl>
</div>

<?= view()->renderPartial('admin/shell_close') ?>
