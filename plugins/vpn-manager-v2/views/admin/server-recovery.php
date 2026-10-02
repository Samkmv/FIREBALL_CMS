<?php
use Fireball\VpnManagerV2\Support\LocalizedValue;

$id = (int)$server['id'];
$base = '/admin/plugins/vpn-manager-v2/servers/' . $id . '/recovery';
$active = array_values(array_filter($inbounds, static fn(array $row): bool => $row['status'] === 'active' && !empty($row['is_enabled'])));
$latest = [];
foreach ($recoveryOperations as $operation) {
    $payload = json_decode((string)($operation['payload_json'] ?? ''), true);
    if ((int)($payload['repair_server_id'] ?? 0) === $id
        && ($payload['repair_server_signature'] ?? '') === ($recoverySignature ?? '')) {
        $latest[(int)$operation['subscription_id']] ??= $operation;
    }
}
$pending = [];
$failed = [];
foreach ($latest as $operation) {
    if (in_array($operation['status'], ['pending', 'running'], true)) { $pending[] = $operation['operation_id']; }
    if (in_array($operation['status'], ['failed', 'retry'], true)) { $failed[] = $operation['operation_id']; }
}
$rows = [];
foreach ($subscriptions as $subscription) {
    $operation = $latest[(int)$subscription['id']] ?? null;
    $rows[] = ['cells' => [
        ['html' => '<a href="' . htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . (int)$subscription['id'])) . '">#'
            . (int)$subscription['id'] . ' · ' . htmlSC((string)$subscription['customer_name']) . '</a>'],
        ['value' => $subscription['plan_name']],
        ['html' => '<span data-vpn-recovery-status="' . (int)$subscription['id'] . '">'
            . htmlSC($operation ? LocalizedValue::operationStatus($operation['status']) : FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_not_run')) . '</span>'],
        ['html' => '<span class="text-danger" data-vpn-recovery-error="' . (int)$subscription['id'] . '">'
            . htmlSC((string)(($operation['status'] ?? '') === 'completed' ? ''
                : ($subscription['last_error'] ?: ($operation['last_error'] ?? '')))) . '</span>'],
    ]];
}
?>
<?= view()->renderPartial('admin/shell_open', ['title' => $title, 'subtitle' => $subtitle]) ?>
<?php require __DIR__ . '/partials/tabs.php'; ?>
<div data-vpn-recovery data-process-url="<?= htmlSC(base_href($base . '/process')) ?>"
     data-progress-base="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/operations/')) ?>"
     data-completed="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_finished')) ?>"
     data-failed="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_error_operation_generic')) ?>">
    <section class="border rounded-5 p-3 p-md-4 mb-3">
        <h2 class="h5">1. <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_step_check')) ?></h2>
        <p class="text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_check_help')) ?></p>
        <?php if (!empty($server['last_error'])): ?>
            <div class="alert alert-danger rounded-4"><?= htmlSC((string)$server['last_error']) ?></div>
        <?php endif; ?>
        <div class="d-flex flex-wrap gap-2">
            <form method="post" action="<?= htmlSC(base_href($base . '/inspect')) ?>">
                <?= get_csrf_field() ?>
                <button class="btn <?= $inspected ? 'btn-outline-secondary' : 'btn-dark' ?> rounded-pill" type="submit"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_check')) ?></button>
            </form>
            <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers/edit/' . $id)) ?>"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_edit')) ?></a>
        </div>
        <?php if ($inspected): ?><div class="text-success mt-2"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_inspected')) ?></div><?php endif; ?>
    </section>
    <form method="post" action="<?= htmlSC(base_href($base . '/apply')) ?>" data-vpn-recovery-apply>
        <?= get_csrf_field() ?>
        <section class="border rounded-5 p-3 p-md-4 mb-3">
            <h2 class="h5">2. <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_step_map')) ?></h2>
            <p class="text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_map_help')) ?></p>
            <?php if ($targets === []): ?>
                <div class="alert alert-info rounded-4"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_new_server')) ?></div>
                <a class="btn btn-outline-primary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/plans')) ?>"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_tab_plans')) ?></a>
            <?php endif; ?>
            <?php foreach ($targets as $target):
                $options = array_values(array_filter($active, static fn(array $row): bool => strtolower($row['protocol']) === strtolower($target['protocol'])));
                $selected = 0;
                foreach ($options as $option) { if ((int)$option['id'] === (int)$target['id']) { $selected = (int)$option['id']; } }
                $similar = array_values(array_filter($options, static fn(array $row): bool => (int)$row['port'] === (int)$target['port'] && $row['network'] === $target['network'] && $row['security'] === $target['security']));
                if (!$selected && count($similar) === 1) { $selected = (int)$similar[0]['id']; }
            ?>
                <div class="row align-items-center g-2 mb-3">
                    <label class="col-md-5 form-label mb-0" for="vpnRecoveryMap<?= (int)$target['id'] ?>">
                        <?= htmlSC((string)$target['name']) ?> · <?= htmlSC((string)$target['protocol']) ?>:<?= (int)$target['port'] ?>
                        <span class="small text-body-secondary d-block"><?= htmlSC(sprintf(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_plan_count'), (int)$target['plan_count'])) ?></span>
                    </label>
                    <div class="col-md-7">
                        <select class="form-select" id="vpnRecoveryMap<?= (int)$target['id'] ?>" name="mapping[<?= (int)$target['id'] ?>]" required <?= !$inspected ? 'disabled' : '' ?>>
                            <option value=""><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_select')) ?></option>
                            <?php foreach ($options as $option): ?>
                                <option value="<?= (int)$option['id'] ?>" <?= $selected === (int)$option['id'] ? 'selected' : '' ?>><?= htmlSC((string)$option['name'] . ' · ' . $option['protocol'] . ':' . $option['port'] . ' · #' . $option['remote_inbound_id']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
        <section class="border rounded-5 p-3 p-md-4 mb-3">
            <h2 class="h5">3. <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_step_apply')) ?></h2>
            <p class="text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_apply_help')) ?></p>
            <p><?= htmlSC(sprintf(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_subscription_count'), count($subscriptions))) ?></p>
            <button class="btn btn-dark rounded-pill" type="submit" <?= !$inspected || $targets === [] || $subscriptions === [] ? 'disabled' : '' ?>><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_apply')) ?></button>
        </section>
    </form>
    <section class="border rounded-5 p-3 p-md-4 mb-3">
        <h2 class="h5">4. <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_step_result')) ?></h2>
        <div data-vpn-recovery-progress class="mb-3" role="status" aria-live="polite"></div>
        <form data-vpn-recovery-resume data-operation-ids="<?= htmlSC(json_encode($pending)) ?>" data-retry="0" class="mb-3" method="post" <?= $pending === [] ? 'hidden' : '' ?>>
            <?= get_csrf_field() ?><button type="submit" class="btn btn-outline-primary rounded-pill" <?= $pending === [] ? 'disabled' : '' ?>><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_resume')) ?></button>
        </form>
        <form data-vpn-recovery-resume data-operation-ids="<?= htmlSC(json_encode($failed)) ?>" data-retry="1" class="mb-3" method="post" <?= $failed === [] ? 'hidden' : '' ?>>
            <?= get_csrf_field() ?><button type="submit" class="btn btn-outline-warning rounded-pill" <?= $failed === [] ? 'disabled' : '' ?>><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_retry')) ?></button>
        </form>
        <?= view()->renderPartial('admin/partials/table', ['columns' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_tab_subscriptions')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_plan')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_status')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_last_error')],
        ], 'rows' => $rows, 'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_new_server')]) ?>
    </section>
    <section class="border rounded-5 p-3 p-md-4 mb-3">
        <h2 class="h5"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_delete_title')) ?></h2>
        <p class="mb-2"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_delete_help')) ?></p>
        <div class="small text-body-secondary"><?= htmlSC(sprintf(FireballPluginVpnManagerV2::t('vpn_manager_v2_recovery_dependencies'), (int)$dependencies['plan_nodes'], (int)$dependencies['subscription_nodes'])) ?></div>
    </section>
</div>
<?= view()->renderPartial('admin/shell_close') ?>
