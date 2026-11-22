<?php

use Fireball\VpnManagerV2\Support\ProvisioningStatus;

$migrationStatus = is_array($migrationStatus ?? null) ? $migrationStatus : [];
$expectedTables = is_array($migrationStatus['expected_tables'] ?? null) ? $migrationStatus['expected_tables'] : [];
$presentTables = is_array($migrationStatus['present_tables'] ?? null) ? $migrationStatus['present_tables'] : [];
$missingTables = is_array($migrationStatus['missing_tables'] ?? null) ? $migrationStatus['missing_tables'] : [];
$migrations = is_array($migrationStatus['migrations'] ?? null) ? $migrationStatus['migrations'] : [];
$permissions = is_array($permissions ?? null) ? $permissions : [];
$overview = is_array($overview ?? null) ? $overview : [];
$servers = is_array($servers ?? null) ? $servers : [];
$overviewSchema = is_array($overview['schema'] ?? null) ? $overview['schema'] : [];
$overviewMigrations = is_array($overview['migrations'] ?? null) ? $overview['migrations'] : [];
$overviewData = is_array($overview['data'] ?? null) ? $overview['data'] : [];
$requiredColumns = is_array($overviewSchema['required_columns'] ?? null) ? $overviewSchema['required_columns'] : [];
$presentColumns = is_array($overviewSchema['present_columns'] ?? null) ? $overviewSchema['present_columns'] : [];
$missingColumns = is_array($overviewSchema['missing_columns'] ?? null) ? $overviewSchema['missing_columns'] : [];
$pendingMigrations = is_array($overviewMigrations['pending'] ?? null) ? $overviewMigrations['pending'] : [];
$diagnosticsAvailable = !empty($overview['available']);
$isReady = !empty($migrationStatus['is_ready']) && !empty($overview['is_ready']);
$metric = static fn(mixed $value): string => $value === null ? '—' : (string)(int)$value;
$t = static fn(string $key): string => htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_' . $key));
$summary = [
    'servers' => 'ci-server',
    'subscriptions' => 'ci-link',
    'connections' => 'ci-share-2',
    'plans' => 'ci-package',
];
$actions = '<a class="btn btn-primary d-inline-flex align-items-center gap-2" href="'
    . htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/create')) . '"><i class="ci-plus" aria-hidden="true"></i>'
    . $t('add_subscription') . '</a>';
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_title'),
    'actions' => $actions,
]) ?>

<?php require __DIR__ . '/partials/tabs.php'; ?>

<?php if (!$isReady): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3" role="alert">
        <span><strong><?= $t('overview_attention') ?></strong> · <?= $t('schema_incomplete') ?></span>
        <a class="alert-link" href="#vpnV2Technical"><?= $t('overview_technical_title') ?></a>
    </div>
<?php endif; ?>

<section class="alert alert-warning mb-3" data-vpn-v2-metrics-attention hidden aria-labelledby="vpnV2AttentionTitle">
    <h2 class="h6 mb-1" id="vpnV2AttentionTitle"><?= $t('overview_attention') ?></h2>
    <ul class="list-unstyled mb-0">
        <?php foreach ($servers as $server): ?>
            <?php $serverId = (int)$server['id']; ?>
            <li id="vpnV2PanelIssue<?= $serverId ?>" class="py-1" hidden>
                <strong><?= htmlSC((string)$server['name']) ?></strong>
                <span class="small" data-vpn-v2-panel-issue-message></span>
                <a class="alert-link ms-1" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers/' . $serverId . '/recovery')) ?>"><?= $t('recovery_action') ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="row g-2 mb-3" aria-label="<?= $t('overview_primary_title') ?>">
    <?php foreach ($summary as $key => $icon): ?>
        <?php $item = $overviewData[$key] ?? []; ?>
        <div class="col-6 col-xl-3">
            <a class="fb-card d-flex flex-column gap-1 h-100 px-3 py-2 text-reset text-decoration-none"
               href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/' . $key)) ?>">
                <span class="small text-body-secondary"><i class="<?= htmlSC($icon) ?> me-1" aria-hidden="true"></i><?= $t('overview_' . $key) ?></span>
                <span>
                    <strong class="fs-5"><?= htmlSC($metric($item['active'] ?? null)) ?></strong>
                    <span class="small text-body-secondary">/ <?= htmlSC($metric($item['total'] ?? null)) ?></span>
                    <span class="visually-hidden"><?= $t(in_array($key, ['subscriptions', 'connections'], true) ? 'overview_available_current' : 'overview_active_total') ?></span>
                </span>
                <?php if ((int)($item['errors'] ?? 0) > 0): ?>
                    <span class="small text-warning-emphasis"><?= $t('overview_errors') ?> <?= (int)$item['errors'] ?></span>
                <?php endif; ?>
            </a>
        </div>
    <?php endforeach; ?>
</section>

<section class="mb-3" aria-labelledby="vpnV2ServerLoadTitle"
         data-vpn-v2-server-metrics
         data-loading-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_loading_metrics')) ?>"
         data-error-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_metrics_unavailable')) ?>"
         data-disabled-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_server_disabled_metrics')) ?>"
         data-online-label="<?= $t('overview_panel_available') ?>"
         data-offline-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_status_offline')) ?>"
         data-unchecked-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_status_unchecked')) ?>"
         data-error-status-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_status_error')) ?>"
         data-disabled-status-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_status_disabled')) ?>"
         data-days-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_days_short')) ?>"
         data-hours-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_hours_short')) ?>"
         data-minutes-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_minutes_short')) ?>"
         data-cores-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_cores')) ?>">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <div>
            <h2 class="h5 mb-1" id="vpnV2ServerLoadTitle"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_servers_load_title')) ?></h2>
        </div>
        <?php if ($servers !== []): ?>
            <button class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center gap-1" type="button" data-vpn-v2-refresh-metrics>
                <i class="ci-refresh-cw" aria-hidden="true"></i><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_refresh_metrics')) ?>
            </button>
        <?php endif; ?>
    </div>

    <?php if ($servers === []): ?>
        <div class="text-center text-body-secondary py-4">
            <i class="ci-server fs-3 d-block mb-2" aria-hidden="true"></i>
            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_no_servers')) ?>
        </div>
    <?php else: ?>
        <div class="fb-card px-3">
            <?php foreach (array_values($servers) as $index => $server): ?>
                <?php
                $serverId = (int)($server['id'] ?? 0);
                $enabled = !empty($server['is_enabled']);
                // A saved status is not evidence that the current metrics request succeeded.
                $serverStatus = $enabled ? 'unchecked' : 'disabled';
                $badgeClass = $enabled ? 'text-bg-light border text-body-secondary' : 'text-bg-secondary';
                ?>
                <details class="<?= $index > 0 ? 'border-top' : '' ?>"
                         data-vpn-v2-server-metric-card
                         data-enabled="<?= $enabled ? '1' : '0' ?>"
                         data-issue-id="vpnV2PanelIssue<?= $serverId ?>"
                         data-url="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers/' . $serverId . '/metrics')) ?>">
                    <summary class="fb-vpn-metrics-summary py-2">
                        <span class="d-inline-flex flex-wrap align-items-center column-gap-3 row-gap-1 ms-1">
                            <strong class="fb-vpn-metrics-name"><?= htmlSC((string)$server['name']) ?></strong>
                            <span class="badge rounded-pill <?= $badgeClass ?>" data-vpn-v2-server-status aria-live="polite"><?= $t('status_' . $serverStatus) ?></span>
                            <?php if ($enabled && !empty($server['maintenance_mode'])): ?>
                                <span class="badge rounded-pill text-bg-warning"><?= $t('subscription_notice_maintenance') ?></span>
                            <?php endif; ?>
                            <span class="small text-body-secondary"><?= $t('overview_cpu') ?> <strong data-vpn-v2-metric="cpu-value">—</strong></span>
                            <span class="small text-body-secondary"><?= $t('overview_memory') ?> <strong data-vpn-v2-metric="memory-value">—</strong></span>
                        </span>
                    </summary>
                    <div class="pb-3 pt-1">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                            <span class="small text-body-secondary" data-vpn-v2-metric-state aria-live="polite"><?= $t($enabled ? 'overview_loading_metrics' : 'overview_server_disabled_metrics') ?></span>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers/edit/' . $serverId)) ?>">
                                <?= $t('action_edit') ?>
                            </a>
                        </div>

                        <div class="row g-3">
                            <?php foreach (['cpu' => 'vpn_manager_v2_overview_cpu', 'memory' => 'vpn_manager_v2_overview_memory', 'swap' => 'vpn_manager_v2_overview_swap', 'disk' => 'vpn_manager_v2_overview_disk'] as $metricKey => $labelKey): ?>
                                <div class="col-6">
                                    <div class="d-flex justify-content-between gap-2 small mb-1">
                                        <span class="text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t($labelKey)) ?></span>
                                        <span class="fw-medium" data-vpn-v2-metric="<?= htmlSC($metricKey) ?>-value">—</span>
                                    </div>
                                    <div class="progress" style="height:.4rem" role="progressbar" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar" data-vpn-v2-metric="<?= htmlSC($metricKey) ?>-bar" style="width:0"></div>
                                    </div>
                                    <div class="text-body-secondary mt-1" style="font-size:.72rem" data-vpn-v2-metric="<?= htmlSC($metricKey) ?>-details">—</div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="border-top mt-3 pt-3 row g-2 small">
                            <div class="col-6"><span class="text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_uptime')) ?>:</span> <span data-vpn-v2-metric="uptime">—</span></div>
                            <div class="col-6"><span class="text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_load_average')) ?>:</span> <span data-vpn-v2-metric="load">—</span></div>
                            <div class="col-6"><span class="text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_network')) ?>:</span> <span data-vpn-v2-metric="network">—</span></div>
                            <div class="col-6"><span class="text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_tcp_udp')) ?>:</span> <span data-vpn-v2-metric="connections">—</span></div>
                            <div class="col-12"><span class="text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_xray')) ?>:</span> <span data-vpn-v2-metric="xray">—</span></div>
                        </div>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<details id="vpnV2Technical" class="fb-card p-3" <?= !$isReady ? 'open' : '' ?>>
    <summary class="fb-vpn-metrics-summary d-flex align-items-center gap-2">
        <i class="ci-chevron-down" aria-hidden="true"></i>
        <span class="flex-grow-1"><span class="h5 d-block mb-1"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_technical_title')) ?></span><span class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_technical_help')) ?></span></span>
        <span class="badge rounded-pill <?= $isReady ? 'text-bg-success' : 'text-bg-warning' ?>"><?= htmlSC(FireballPluginVpnManagerV2::t($isReady ? 'vpn_manager_v2_overview_ready' : 'vpn_manager_v2_overview_attention')) ?></span>
    </summary>

    <div class="pt-3">
        <a class="btn btn-sm btn-outline-secondary mb-3" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/sync-logs')) ?>"><?= $t('tab_sync_logs') ?></a>
        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3"><div class="bg-body-tertiary rounded-4 p-3 h-100"><div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_version')) ?></div><div class="h4 mb-0 mt-2"><?= htmlSC((string)($overview['version'] ?? '—')) ?></div></div></div>
            <div class="col-6 col-xl-3"><div class="bg-body-tertiary rounded-4 p-3 h-100"><div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_migrations')) ?></div><div class="h4 mb-0 mt-2"><?= (int)($overviewMigrations['applied_count'] ?? 0) ?> / <?= (int)($overviewMigrations['files_count'] ?? 0) ?></div></div></div>
            <div class="col-6 col-xl-3"><div class="bg-body-tertiary rounded-4 p-3 h-100"><div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_required_columns')) ?></div><div class="h4 mb-0 mt-2"><?= count($presentColumns) ?> / <?= count($requiredColumns) ?></div></div></div>
            <div class="col-6 col-xl-3"><div class="bg-body-tertiary rounded-4 p-3 h-100"><div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_jobs')) ?></div><div class="h4 mb-0 mt-2"><?= (int)($overview['jobs_count'] ?? 0) ?></div><div class="small text-body-secondary mt-1"><?= htmlSC(ProvisioningStatus::label((string)($overview['plugin_status'] ?? ''))) ?></div></div></div>
        </div>

        <?php if (!$diagnosticsAvailable || $pendingMigrations !== [] || $missingColumns !== [] || $missingTables !== []): ?>
            <div class="alert alert-warning rounded-4">
                <div class="fw-semibold"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_diagnostics_warning')) ?></div>
                <?php if ($pendingMigrations !== []): ?><div class="small mt-2"><strong><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_pending')) ?>:</strong> <?= htmlSC(implode(', ', $pendingMigrations)) ?></div><?php endif; ?>
                <?php if ($missingColumns !== []): ?><div class="small mt-1"><strong><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_missing_columns')) ?>:</strong> <?= htmlSC(implode(', ', $missingColumns)) ?></div><?php endif; ?>
                <?php if ($missingTables !== []): ?><div class="small mt-1"><strong><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_missing_tables')) ?>:</strong> <?= htmlSC(implode(', ', $missingTables)) ?></div><?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-6">
                <h3 class="h6 mb-3"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_tables_title')) ?></h3>
                <?= view()->renderPartial('admin/partials/table', [
                    'columns' => [['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_table')], ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_status')]],
                    'rows' => array_map(static function (string $table) use ($presentTables): array {
                        $present = in_array($table, $presentTables, true);
                        return ['cells' => [['value' => $table], ['html' => '<span class="badge rounded-pill ' . ($present ? 'text-bg-success' : 'text-bg-warning') . '">' . htmlSC(FireballPluginVpnManagerV2::t($present ? 'vpn_manager_v2_status_present' : 'vpn_manager_v2_status_missing')) . '</span>']]];
                    }, $expectedTables),
                    'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_tables_empty'),
                ]) ?>
            </div>
            <div class="col-12 col-lg-6">
                <h3 class="h6 mb-3"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_migrations_title')) ?></h3>
                <?= view()->renderPartial('admin/partials/table', [
                    'columns' => [['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_migration')], ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_executed_at')]],
                    'rows' => array_map(static fn(array $migration): array => ['cells' => [['value' => (string)($migration['migration'] ?? '')], ['value' => (string)($migration['executed_at'] ?? '')]]], $migrations),
                    'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_migrations_empty'),
                ]) ?>
            </div>
        </div>

        <div class="small text-body-secondary">
            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_installed_version')) ?>: <?= htmlSC((string)($overview['installed_version'] ?? '—')) ?> ·
            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_last_migration')) ?>: <?= htmlSC((string)($overviewMigrations['last_executed_at'] ?? FireballPluginVpnManagerV2::t('vpn_manager_v2_never'))) ?> ·
            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_permissions_title')) ?>: <?= count($permissions) ?>
        </div>
    </div>
</details>

<?= view()->renderPartial('admin/shell_close') ?>
