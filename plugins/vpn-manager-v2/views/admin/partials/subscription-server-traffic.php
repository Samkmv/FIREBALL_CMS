<?php
$serverTraffic = \Fireball\VpnManagerV2\Support\SubscriptionUsageSummary::byServer($subscription, $nodes);
$serverTrafficRows = [];
$serverTrafficCards = [];
$serverTrafficColumns = array_map(static fn($key) => ['label' => $clientT('vpn_manager_v2_' . $key)], [
    'col_server', 'profile_traffic_used', 'client_upload', 'client_download', 'client_connections', 'client_traffic_checked',
]);
foreach ($serverTraffic as $serverUsage) {
    $serverValue = static fn(string $field, string $value): string => '<span data-vpn-v2-traffic-value="' . $field
        . '" data-server-id="' . (int)$serverUsage['id'] . '">' . htmlSC($value) . '</span>';
    $state = $clientT('vpn_manager_v2_client_traffic_saved');
    if ($serverUsage['checked_at'] !== null) $state .= ' · ' . $serverUsage['checked_at'];
    if ($serverUsage['partial']) $state .= ' · ' . $clientT('vpn_manager_v2_client_traffic_partial');
    $cells = [
        ['value' => $serverUsage['name'] . ' (#' . $serverUsage['id'] . ')'],
        ['html' => $serverValue('used', $serverUsage['known'] ? $clientBytes($serverUsage['used']) : '—')],
        ['html' => $serverValue('upload', $serverUsage['sample_known'] ? $clientBytes($serverUsage['upload']) : '—')],
        ['html' => $serverValue('download', $serverUsage['sample_known'] ? $clientBytes($serverUsage['download']) : '—')],
        ['value' => (string)$serverUsage['connections']],
        ['html' => '<span data-vpn-v2-traffic-state data-server-id="' . (int)$serverUsage['id']
            . '" data-connections="' . (int)$serverUsage['connections'] . '">' . htmlSC($state) . '</span>'],
    ];
    $serverTrafficRows[] = ['cells' => $cells];
    $card = ['title' => $cells[0], 'icon' => 'ci-server', 'extra_fields' => []];
    foreach (array_slice($cells, 1, null, true) as $index => $cell) {
        $card['extra_fields'][] = ['label' => $serverTrafficColumns[$index]['label']] + $cell;
    }
    $serverTrafficCards[] = $card;
}
?>
<?php if ($serverTrafficRows !== []): ?>
<div class="mt-4 mb-4" data-vpn-v2-server-traffic
     data-units="<?= htmlSC(json_encode(array_map(static fn($unit) => $clientT('vpn_manager_v2_traffic_unit_' . $unit), ['b', 'kb', 'mb', 'gb', 'tb', 'pb']), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) ?>"
     data-live-label="<?= htmlSC($clientT('vpn_manager_v2_client_live_data')) ?>"
     data-saved-label="<?= htmlSC($clientT('vpn_manager_v2_client_traffic_saved')) ?>"
     data-partial-label="<?= htmlSC($clientT('vpn_manager_v2_client_traffic_partial')) ?>"
     data-failed-label="<?= htmlSC($clientT('vpn_manager_v2_client_inspection_failed')) ?>">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h3 class="h6 mb-0"><?= htmlSC($clientT('vpn_manager_v2_client_server_traffic')) ?></h3>
        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill d-inline-flex align-items-center gap-2"
                data-vpn-v2-client-traffic-refresh data-loading="<?= htmlSC($clientT('vpn_manager_v2_client_loading')) ?>">
            <i class="ci-refresh-cw" aria-hidden="true"></i><span><?= htmlSC($clientT('vpn_manager_v2_client_refresh_traffic')) ?></span>
        </button>
    </div>
    <p class="small text-body-secondary"><?= htmlSC($clientT('vpn_manager_v2_client_server_traffic_help')) ?></p>
    <?= view()->renderPartial('admin/partials/table', [
        'columns' => $serverTrafficColumns, 'rows' => $serverTrafficRows, 'mobile_cards' => $serverTrafficCards,
    ]) ?>
    <div class="small mt-2 text-break" role="status" aria-live="polite" data-vpn-v2-server-traffic-result></div>
</div>
<?php endif; ?>
