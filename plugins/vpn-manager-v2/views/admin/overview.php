<?php

$migrationStatus = is_array($migrationStatus ?? null) ? $migrationStatus : [];
$expectedTables = is_array($migrationStatus['expected_tables'] ?? null) ? $migrationStatus['expected_tables'] : [];
$presentTables = is_array($migrationStatus['present_tables'] ?? null) ? $migrationStatus['present_tables'] : [];
$missingTables = is_array($migrationStatus['missing_tables'] ?? null) ? $migrationStatus['missing_tables'] : [];
$migrations = is_array($migrationStatus['migrations'] ?? null) ? $migrationStatus['migrations'] : [];
$permissions = is_array($permissions ?? null) ? $permissions : [];
$isReady = !empty($migrationStatus['is_ready']);
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
