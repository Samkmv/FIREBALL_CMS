<?php
use Fireball\VpnManagerV2\Support\ProvisioningStatus;


$migrationStatus = is_array($migrationStatus ?? null) ? $migrationStatus : [];
$expectedTables = is_array($migrationStatus['expected_tables'] ?? null) ? $migrationStatus['expected_tables'] : [];
$presentTables = is_array($migrationStatus['present_tables'] ?? null) ? $migrationStatus['present_tables'] : [];
$missingTables = is_array($migrationStatus['missing_tables'] ?? null) ? $migrationStatus['missing_tables'] : [];
$migrations = is_array($migrationStatus['migrations'] ?? null) ? $migrationStatus['migrations'] : [];
$permissions = is_array($permissions ?? null) ? $permissions : [];
$overview = is_array($overview ?? null) ? $overview : [];
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
$dataCards = [
    'servers' => ['vpn_manager_v2_overview_servers', '/admin/plugins/vpn-manager-v2/servers', 'ci-server'],
    'inbounds' => ['vpn_manager_v2_overview_inbounds', '/admin/plugins/vpn-manager-v2/inbounds', 'ci-log-in'],
    'plans' => ['vpn_manager_v2_overview_plans', '/admin/plugins/vpn-manager-v2/plans', 'ci-package'],
    'subscriptions' => ['vpn_manager_v2_overview_subscriptions', '/admin/plugins/vpn-manager-v2/subscriptions', 'ci-link'],
    'connections' => ['vpn_manager_v2_overview_connections', '/admin/plugins/vpn-manager-v2/connections', 'ci-share-2'],
];
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $title ?? FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_title'),
    'subtitle' => $subtitle ?? '',
]) ?>

<?php require __DIR__ . '/partials/tabs.php'; ?>

<div class="alert <?= $isReady ? 'alert-success' : 'alert-warning' ?> rounded-4" role="alert">
    <div class="d-flex align-items-start gap-3">
        <i class="<?= $isReady ? 'ci-check-circle' : 'ci-alert-triangle' ?> fs-4" aria-hidden="true"></i>
        <div>
            <div class="fw-semibold"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_plugin_loaded')) ?></div>
            <div class="small mt-1">
                <?= htmlSC(FireballPluginVpnManagerV2::t($isReady ? 'vpn_manager_v2_schema_ready' : 'vpn_manager_v2_schema_incomplete')) ?>
            </div>
        </div>
    </div>
</div>

<div class="border rounded-5 p-3 p-md-4 mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h2 class="h5 mb-0"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_system_title')) ?></h2>
        <span class="badge rounded-pill <?= $isReady ? 'text-bg-success' : 'text-bg-warning' ?>">
            <?= htmlSC(FireballPluginVpnManagerV2::t($isReady ? 'vpn_manager_v2_overview_ready' : 'vpn_manager_v2_overview_attention')) ?>
        </span>
    </div>
    <div class="row g-3">
        <div class="col-6 col-xl-3">
            <div class="border rounded-4 p-3 h-100">
                <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_version')) ?></div>
                <div class="h4 mb-0 mt-2"><?= htmlSC((string)($overview['version'] ?? '—')) ?></div>
                <?php if (($overview['installed_version'] ?? null) !== ($overview['version'] ?? null)): ?>
                    <div class="small text-warning mt-1">
                        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_installed_version')) ?>:
                        <?= htmlSC((string)($overview['installed_version'] ?? '—')) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="border rounded-4 p-3 h-100">
                <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_migrations')) ?></div>
                <div class="h4 mb-0 mt-2">
                    <?= (int)($overviewMigrations['applied_count'] ?? 0) ?> / <?= (int)($overviewMigrations['files_count'] ?? 0) ?>
                </div>
                <div class="small text-body-secondary mt-1">
                    <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_pending')) ?>: <?= count($pendingMigrations) ?>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="border rounded-4 p-3 h-100">
                <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_required_columns')) ?></div>
                <div class="h4 mb-0 mt-2"><?= count($presentColumns) ?> / <?= count($requiredColumns) ?></div>
                <div class="small text-body-secondary mt-1">
                    <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_missing')) ?>: <?= count($missingColumns) ?>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="border rounded-4 p-3 h-100">
                <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_jobs')) ?></div>
                <div class="h4 mb-0 mt-2"><?= (int)($overview['jobs_count'] ?? 0) ?></div>
                <div class="small text-body-secondary mt-1">
                    <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_plugin_status')) ?>:
                    <?= htmlSC(ProvisioningStatus::label((string)($overview['plugin_status'] ?? ''))) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="small text-body-secondary mt-3">
        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_last_migration')) ?>:
        <?= htmlSC((string)($overviewMigrations['last_executed_at'] ?? FireballPluginVpnManagerV2::t('vpn_manager_v2_never'))) ?>
    </div>
</div>

<?php if (!$diagnosticsAvailable || $pendingMigrations !== [] || $missingColumns !== []): ?>
    <div class="alert alert-warning rounded-4" role="alert">
        <div class="fw-semibold"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_diagnostics_warning')) ?></div>
        <div class="small mt-1"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_diagnostics_help')) ?></div>
        <?php if ($pendingMigrations !== []): ?>
            <div class="small mt-2">
                <strong><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_pending')) ?>:</strong>
                <?= htmlSC(implode(', ', $pendingMigrations)) ?>
            </div>
        <?php endif; ?>
        <?php if ($missingColumns !== []): ?>
            <div class="small mt-1">
                <strong><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_missing_columns')) ?>:</strong>
                <?= htmlSC(implode(', ', $missingColumns)) ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="border rounded-5 p-3 p-md-4 mb-4">
    <h2 class="h5 mb-3"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_data_title')) ?></h2>
    <div class="row g-3">
        <?php foreach ($dataCards as $key => [$labelKey, $href, $icon]): ?>
            <?php $item = is_array($overviewData[$key] ?? null) ? $overviewData[$key] : []; ?>
            <div class="col-12 col-sm-6 col-xl">
                <a class="border rounded-4 p-3 h-100 d-block text-reset text-decoration-none" href="<?= htmlSC(base_href($href)) ?>">
                    <div class="d-flex align-items-center justify-content-between gap-2">
                        <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t($labelKey)) ?></div>
                        <i class="<?= htmlSC($icon) ?>" aria-hidden="true"></i>
                    </div>
                    <div class="h3 mb-1 mt-2"><?= htmlSC($metric($item['total'] ?? null)) ?></div>
                    <div class="small text-body-secondary">
                        <?= htmlSC(FireballPluginVpnManagerV2::t($key === 'servers' ? 'vpn_manager_v2_overview_online' : 'vpn_manager_v2_overview_active')) ?>:
                        <?= htmlSC($metric($item['active'] ?? null)) ?>
                        <?php if (($item['enabled'] ?? null) !== null): ?>
                            · <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_enabled')) ?>:
                            <?= htmlSC($metric($item['enabled'])) ?>
                        <?php endif; ?>
                        <?php if (($item['errors'] ?? null) !== null): ?>
                            · <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_errors')) ?>:
                            <?= htmlSC($metric($item['errors'])) ?>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="border rounded-5 p-3 p-md-4 mb-4">
    <h2 class="h5 mb-3"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_overview_quick_actions')) ?></h2>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-primary" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers/create')) ?>">
            <i class="ci-plus me-1" aria-hidden="true"></i><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_add_server')) ?>
        </a>
        <a class="btn btn-outline-secondary" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/plans/create')) ?>">
            <i class="ci-package me-1" aria-hidden="true"></i><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_add_plan')) ?>
        </a>
        <a class="btn btn-outline-secondary" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/create')) ?>">
            <i class="ci-link me-1" aria-hidden="true"></i><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_add_subscription')) ?>
        </a>
        <a class="btn btn-outline-secondary" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/settings')) ?>">
            <i class="ci-settings me-1" aria-hidden="true"></i><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_tab_settings')) ?>
        </a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="border rounded-5 p-4 h-100">
            <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_expected_tables')) ?></div>
            <div class="h3 mb-0 mt-2"><?= count($expectedTables) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="border rounded-5 p-4 h-100">
            <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_present_tables')) ?></div>
            <div class="h3 mb-0 mt-2"><?= count($presentTables) ?></div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="border rounded-5 p-4 h-100">
            <div class="small text-body-secondary"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_missing_tables')) ?></div>
            <div class="h3 mb-0 mt-2"><?= count($missingTables) ?></div>
        </div>
    </div>
</div>

<div class="border rounded-5 p-3 p-md-4 mb-4">
    <h2 class="h5 mb-3"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_tables_title')) ?></h2>
    <?= view()->renderPartial('admin/partials/table', [
        'columns' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_table')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_status')],
        ],
        'rows' => array_map(static function (string $table) use ($presentTables): array {
            $present = in_array($table, $presentTables, true);

            return [
                'cells' => [
                    ['value' => $table],
                    ['html' => '<span class="badge rounded-pill ' . ($present ? 'text-bg-success' : 'text-bg-warning') . '">'
                        . htmlSC(FireballPluginVpnManagerV2::t($present ? 'vpn_manager_v2_status_present' : 'vpn_manager_v2_status_missing'))
                        . '</span>'],
                ],
            ];
        }, $expectedTables),
        'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_tables_empty'),
    ]) ?>
</div>

<div class="border rounded-5 p-3 p-md-4 mb-4">
    <h2 class="h5 mb-3"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_migrations_title')) ?></h2>
    <?= view()->renderPartial('admin/partials/table', [
        'columns' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_id')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_migration')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_executed_at')],
        ],
        'rows' => array_map(static fn(array $migration): array => [
            'cells' => [
                ['value' => '#' . (int)($migration['id'] ?? 0)],
                ['value' => (string)($migration['migration'] ?? '')],
                ['value' => (string)($migration['executed_at'] ?? '')],
            ],
        ], $migrations),
        'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_migrations_empty'),
    ]) ?>
</div>

<div class="border rounded-5 p-3 p-md-4">
    <h2 class="h5 mb-2"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_permissions_title')) ?></h2>
    <p class="small text-body-secondary mb-3"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_permissions_note')) ?></p>
    <?= view()->renderPartial('admin/partials/table', [
        'columns' => [
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_permission')],
            ['label' => FireballPluginVpnManagerV2::t('vpn_manager_v2_col_description')],
        ],
        'rows' => array_map(static fn(string $permission, string $translationKey): array => [
            'cells' => [
                ['value' => $permission],
                ['value' => FireballPluginVpnManagerV2::t($translationKey)],
            ],
        ], array_keys($permissions), array_values($permissions)),
        'empty_text' => FireballPluginVpnManagerV2::t('vpn_manager_v2_permissions_empty'),
    ]) ?>
</div>

<?= view()->renderPartial('admin/shell_close') ?>
