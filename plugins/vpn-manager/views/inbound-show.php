<?php
require_once __DIR__ . '/partials/helpers.php';

use Fireball\VpnManager\Support\Formatter;

$inbound = is_array($inbound ?? null) ? $inbound : [];
$formatJson = static function (?string $json): string {
    $json = trim((string)$json);
    if ($json === '') {
        return '{}';
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return $json;
    }

    return (string)json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => ($title ?? FireballPluginVpnManager::t('vpn_manager_inbound_json_title')) . ' #' . (int)($inbound['id'] ?? 0),
    'subtitle' => $subtitle ?? '',
    'actions' => '<a class="btn btn-outline-secondary rounded-pill" href="' . htmlSC(base_href('/admin/plugins/vpn-manager/inbounds')) . '">' . htmlSC(FireballPluginVpnManager::t('vpn_manager_back_to_list')) . '</a>',
]) ?>

    <?php require __DIR__ . '/partials/tabs.php'; ?>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="border rounded-5 p-3 p-md-4 h-100">
                <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                    <div>
                        <h2 class="h5 mb-1"><?= htmlSC((string)($inbound['name'] ?? 'Inbound')) ?></h2>
                        <div class="text-body-secondary"><?= htmlSC((string)($inbound['server_name'] ?? '-')) ?></div>
                    </div>
                    <?= vpnm_status_badge((string)($inbound['status'] ?? 'active')) ?>
                </div>
                <dl class="row mb-0">
                    <dt class="col-5 text-body-secondary fw-normal">ID</dt>
                    <dd class="col-7">#<?= (int)($inbound['id'] ?? 0) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_remote_id')) ?></dt>
                    <dd class="col-7">#<?= htmlSC((string)($inbound['remote_inbound_id'] ?? '-')) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_protocol')) ?></dt>
                    <dd class="col-7"><?= htmlSC((string)($inbound['protocol'] ?? '-')) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_port')) ?></dt>
                    <dd class="col-7"><?= htmlSC((string)($inbound['port'] ?? '-')) ?></dd>
                    <dt class="col-5 text-body-secondary fw-normal"><?= htmlSC(FireballPluginVpnManager::t('vpn_manager_col_updated_at')) ?></dt>
                    <dd class="col-7"><?= htmlSC(Formatter::dateTime((string)($inbound['updated_at'] ?? ''))) ?></dd>
                </dl>
            </div>
        </div>
        <div class="col-xl-8">
            <div class="border rounded-5 p-3 p-md-4 mb-4">
                <h2 class="h6 mb-3">settings_json</h2>
                <pre class="bg-body-tertiary border rounded-4 p-3 mb-0 small text-break" style="white-space: pre-wrap;"><?= htmlSC($formatJson($inbound['settings_json'] ?? null)) ?></pre>
            </div>
            <div class="border rounded-5 p-3 p-md-4">
                <h2 class="h6 mb-3">stream_settings_json</h2>
                <pre class="bg-body-tertiary border rounded-4 p-3 mb-0 small text-break" style="white-space: pre-wrap;"><?= htmlSC($formatJson($inbound['stream_settings_json'] ?? null)) ?></pre>
            </div>
        </div>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
