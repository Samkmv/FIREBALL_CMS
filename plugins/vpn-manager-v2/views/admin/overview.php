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
$summaryCards = [
    'servers' => ['vpn_manager_v2_overview_servers', 'ci-server', '/admin/plugins/vpn-manager-v2/servers'],
    'subscriptions' => ['vpn_manager_v2_overview_subscriptions', 'ci-link', '/admin/plugins/vpn-manager-v2/subscriptions'],
    'connections' => ['vpn_manager_v2_overview_connections', 'ci-share-2', '/admin/plugins/vpn-manager-v2/connections'],
    'plans' => ['vpn_manager_v2_overview_plans', 'ci-package', '/admin/plugins/vpn-manager-v2/plans'],
];
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_title'),
    'subtitle' => $subtitle ?? '',
]) ?>

<?php require __DIR__ . '/partials/tabs.php'; ?>

<div class="alert <?= $isReady ? 'alert-success' : 'alert-warning' ?> rounded-4 mb-4" role="alert">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-start gap-3">
            <i class="<?= $isReady ? 'ci-check-circle' : 'ci-alert-triangle' ?> fs-4" aria-hidden="true"></i>
            <div>
                <div class="fw-semibold"><?= htmlSC(FireballPluginVpnManagerV2::t($isReady ? 'vpn_manager_v2_overview_everything_ready' : 'vpn_manager_v2_overview_attention')) ?></div>
                <div class="small mt-1"><?= htmlSC(FireballPluginVpnManagerV2::t($isReady ? 'vpn_manager_v2_overview_ready_help' : 'vpn_manager_v2_schema_incomplete')) ?></div>
            </div>
        </div>
        <a class="btn btn-sm <?= $isReady ? 'btn-success' : 'btn-warning' ?> rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/sync-logs')) ?>">
            <i class="ci-list me-1" aria-hidden="true"></i><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_tab_sync_logs')) ?>
        </a>
    </div>
</div>

<section class="border rounded-5 p-3 p-md-4 mb-4" aria-labelledby="vpnV2QuickActionsTitle">
    <h2 class="h5 mb-3" id="vpnV2QuickActionsTitle"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_quick_actions')) ?></h2>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-dark rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/create')) ?>"><i class="ci-plus me-1" aria-hidden="true"></i><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_add_subscription')) ?></a>
        <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers/create')) ?>"><i class="ci-server me-1" aria-hidden="true"></i><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_add_server')) ?></a>
        <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/plans/create')) ?>"><i class="ci-package me-1" aria-hidden="true"></i><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_add_plan')) ?></a>
        <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/operations')) ?>"><i class="ci-refresh-cw me-1" aria-hidden="true"></i><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_tab_operations')) ?></a>
    </div>
</section>

<section class="mb-4" aria-labelledby="vpnV2MainStateTitle">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h2 class="h5 mb-0" id="vpnV2MainStateTitle"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_primary_title')) ?></h2>
        <span class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_primary_help')) ?></span>
    </div>
    <div class="row g-3">
        <?php foreach ($summaryCards as $key => [$labelKey, $icon, $href]): ?>
            <?php
            $item = is_array($overviewData[$key] ?? null) ? $overviewData[$key] : [];
            $errors = $item['errors'] ?? null;
            $hasErrors = $errors !== null && (int)$errors > 0;
            ?>
            <div class="col-12 col-sm-6 col-xl-3">
                <a class="border rounded-5 p-3 p-md-4 h-100 d-block text-reset text-decoration-none"
                   href="<?= htmlSC(base_href($href)) ?>">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t($labelKey)) ?></div>
                        <span class="rounded-circle <?= $hasErrors ? 'text-bg-warning' : 'bg-body-tertiary' ?> d-inline-flex align-items-center justify-content-center" style="width:2.25rem;height:2.25rem">
                            <i class="<?= htmlSC($icon) ?>" aria-hidden="true"></i>
                        </span>
                    </div>
                    <div class="d-flex align-items-end gap-2 mt-2">
                        <span class="display-6 fw-semibold lh-1"><?= htmlSC($metric($item['active'] ?? null)) ?></span>
                        <span class="text-body-secondary mb-1">/ <?= htmlSC($metric($item['total'] ?? null)) ?></span>
                    </div>
                    <div class="small mt-2 <?= $hasErrors ? 'text-warning' : 'text-body-secondary' ?>">
                        <?php if ($errors !== null): ?>
                            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_errors')) ?>: <?= htmlSC($metric($errors)) ?>
                        <?php else: ?>
                            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_active')) ?>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="border rounded-5 p-3 p-md-4 mb-4" aria-labelledby="vpnV2ServerLoadTitle"
         data-vpn-v2-server-metrics
         data-loading-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_loading_metrics')) ?>"
         data-error-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_metrics_unavailable')) ?>"
         data-disabled-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_server_disabled_metrics')) ?>"
         data-days-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_days_short')) ?>"
         data-hours-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_hours_short')) ?>"
         data-minutes-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_minutes_short')) ?>"
         data-cores-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_cores')) ?>">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1" id="vpnV2ServerLoadTitle"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_servers_load_title')) ?></h2>
            <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_servers_load_help')) ?></div>
        </div>
        <?php if ($servers !== []): ?>
            <button class="btn btn-sm btn-outline-secondary rounded-pill" type="button" data-vpn-v2-refresh-metrics>
                <i class="ci-refresh-cw me-1" aria-hidden="true"></i><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_refresh_metrics')) ?>
            </button>
        <?php endif; ?>
    </div>

    <?php if ($servers === []): ?>
        <div class="text-center text-body-secondary py-4">
            <i class="ci-server fs-3 d-block mb-2" aria-hidden="true"></i>
            <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_no_servers')) ?>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($servers as $server): ?>
                <?php
                $serverId = (int)($server['id'] ?? 0);
                $enabled = !empty($server['is_enabled']);
                $serverStatus = $enabled ? (string)($server['status'] ?? 'unchecked') : 'disabled';
                $badgeClass = match ($serverStatus) {
                    'online' => 'text-bg-success',
                    'offline' => 'text-bg-danger',
                    'error' => 'text-bg-warning',
                    'disabled' => 'text-bg-secondary',
                    default => 'text-bg-light border text-body-secondary',
                };
                ?>
                <div class="col-12 col-xl-6">
                    <article class="border rounded-4 p-3 h-100"
                             data-vpn-v2-server-metric-card
                             data-enabled="<?= $enabled ? '1' : '0' ?>"
                             data-url="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers/' . $serverId . '/metrics')) ?>">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                            <div>
                                <a class="fw-semibold text-reset text-decoration-none" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers/edit/' . $serverId)) ?>">
                                    <?= htmlSC((string)($server['name'] ?? '')) ?><i class="ci-edit-2 ms-1 small text-body-secondary" aria-hidden="true"></i>
                                    <span class="visually-hidden"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_action_edit')) ?></span>
                                </a>
                                <div class="small text-body-secondary"><?= htmlSC(trim(implode(' · ', array_filter([(string)($server['country_name'] ?? ''), (string)($server['city'] ?? '')])))) ?></div>
                            </div>
                            <span class="badge rounded-pill <?= $badgeClass ?>"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_status_' . $serverStatus)) ?></span>
                        </div>

                        <div class="small text-body-secondary mb-3" data-vpn-v2-metric-state aria-live="polite">
                            <?= htmlSC(FireballPluginVpnManagerV2::t($enabled ? 'vpn_manager_v2_overview_loading_metrics' : 'vpn_manager_v2_overview_server_disabled_metrics')) ?>
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
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<details class="border rounded-5 p-3 p-md-4" <?= !$isReady ? 'open' : '' ?>>
    <summary class="d-flex align-items-center justify-content-between gap-3 list-unstyled" style="cursor:pointer">
        <span><span class="h5 d-block mb-1"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_technical_title')) ?></span><span class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_technical_help')) ?></span></span>
        <span class="badge rounded-pill <?= $isReady ? 'text-bg-success' : 'text-bg-warning' ?>"><?= htmlSC(FireballPluginVpnManagerV2::t($isReady ? 'vpn_manager_v2_overview_ready' : 'vpn_manager_v2_overview_attention')) ?></span>
    </summary>

    <div class="pt-4">
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
