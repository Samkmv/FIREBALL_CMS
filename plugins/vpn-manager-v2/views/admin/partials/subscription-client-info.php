<?php

use Fireball\VpnManagerV2\Support\SubscriptionUsageSummary;

$usage = SubscriptionUsageSummary::from($subscription, $nodes);
$clientT = static fn(string $key): string => FireballPluginVpnManagerV2::t($key);
$clientBytes = static fn(mixed $value): string => \Fireball\VpnManagerV2\Support\TrafficFormatter::bytes(max(0, (int)$value));
$clientLimit = static fn(mixed $value): string => \Fireball\VpnManagerV2\Support\TrafficFormatter::localizedLimit($value === null ? null : (int)$value);
$summaryCards = [
    ['icon' => 'ci-bar-chart', 'label' => 'vpn_manager_v2_profile_traffic_used', 'value' => $usage['known'] ? $clientBytes($usage['used']) : '—',
        'help' => $clientT('vpn_manager_v2_profile_traffic_limit') . ': ' . $clientLimit($usage['limit'])],
    ['icon' => 'ci-hard-drive', 'label' => 'vpn_manager_v2_profile_traffic_remaining',
        'value' => $usage['remaining'] === null ? $clientT('vpn_manager_v2_unlimited') : ($usage['known'] ? $clientBytes($usage['remaining']) : '—'),
        'help' => $clientT('vpn_manager_v2_client_accounting_cms')],
    ['icon' => 'ci-calendar', 'label' => 'vpn_manager_v2_client_term_remaining',
        'value' => $usage['lifetime'] ? $clientT('vpn_manager_v2_lifetime_short') : ($usage['days_remaining'] === null ? '—' : $usage['days_remaining'] . ' ' . $clientT('vpn_manager_v2_days')),
        'help' => (string)($subscription['expires_at'] ?? '')],
    ['icon' => 'ci-server', 'label' => 'vpn_manager_v2_client_connections',
        'value' => $usage['active_connections'] . ' / ' . $usage['connections'], 'help' => $clientT('vpn_manager_v2_client_connections_help')],
];
?>
<section class="border rounded-5 p-3 p-md-4 mb-4" data-vpn-v2-client-information>
    <h2 class="h5 mb-1"><?= htmlSC($clientT('vpn_manager_v2_client_information')) ?></h2>
    <p class="small text-body-secondary mb-4"><?= htmlSC($clientT('vpn_manager_v2_client_information_help')) ?></p>
    <div class="row g-3 mb-3">
        <?php foreach ($summaryCards as $card): ?>
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="border bg-body-tertiary rounded-4 p-3 h-100">
                    <div class="small text-body-secondary d-flex align-items-center gap-2 mb-2"><i class="<?= htmlSC($card['icon']) ?>" aria-hidden="true"></i><?= htmlSC($clientT($card['label'])) ?></div>
                    <div class="h5 mb-1 text-break"><?= htmlSC($card['value']) ?></div>
                    <div class="small text-body-secondary text-break"><?= htmlSC($card['help']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if ($usage['percent'] !== null): ?>
        <div class="progress mb-2" role="progressbar" aria-label="<?= htmlSC($clientT('vpn_manager_v2_profile_traffic_title')) ?>" aria-valuenow="<?= $usage['percent'] ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar <?= $usage['percent'] >= 100 ? 'bg-danger' : ($usage['percent'] >= 80 ? 'bg-warning' : 'bg-primary') ?>" style="width: <?= $usage['percent'] ?>%"></div>
        </div>
    <?php endif; ?>
    <div class="small text-body-secondary mb-3">
        <?= htmlSC($usage['checked_at'] !== null ? sprintf($clientT('vpn_manager_v2_profile_traffic_updated'), $usage['checked_at']) : $clientT('vpn_manager_v2_client_not_checked')) ?>
        <?php if ($usage['partial']): ?><div class="text-warning mt-1"><?= htmlSC($clientT('vpn_manager_v2_client_partial_traffic')) ?></div><?php endif; ?>
    </div>
    <?php require __DIR__ . '/subscription-server-traffic.php'; ?>
    <?php foreach ($nodes as $clientNode): ?>
        <?php
        if ((int)($clientNode['subscription_id'] ?? 0) !== $subscriptionId || (string)$clientNode['status'] === 'deleted') { continue; }
        $clientNodeId = (int)$clientNode['id'];
        $trafficSampleKnown = strtotime((string)($clientNode['traffic_synced_at'] ?? '')) !== false;
        $trafficKnown = $trafficSampleKnown || (int)($clientNode['traffic_used_bytes'] ?? 0) > 0;
        $trafficSyncStatus = (string)($clientNode['traffic_sync_status'] ?? 'pending');
        $clientFields = [
            'vpn_manager_v2_col_client_email' => (string)($clientNode['client_email'] ?? '—'),
            'vpn_manager_v2_col_remote_client' => (string)($clientNode['remote_client_preview'] ?? '—'),
            'vpn_manager_v2_profile_traffic_used' => $trafficKnown ? $clientBytes($clientNode['traffic_used_bytes'] ?? 0) : '—',
            'vpn_manager_v2_client_upload' => $trafficSampleKnown ? $clientBytes($clientNode['upload_bytes'] ?? 0) : '—',
            'vpn_manager_v2_client_download' => $trafficSampleKnown ? $clientBytes($clientNode['download_bytes'] ?? 0) : '—',
            'vpn_manager_v2_profile_traffic_limit' => $clientLimit($clientNode['traffic_limit_bytes'] ?? null),
            'vpn_manager_v2_col_transport_security' => strtoupper((string)($clientNode['protocol'] ?? '')) . ' / ' . strtoupper((string)($clientNode['network'] ?? '')) . ' / ' . strtoupper((string)($clientNode['security'] ?? 'none')),
            'vpn_manager_v2_col_flow' => (string)(($clientNode['flow'] ?? '') ?: $clientT('vpn_manager_v2_flow_none')),
            'vpn_manager_v2_client_desired_access' => $clientT(!empty($clientNode['desired_enabled']) ? 'vpn_manager_v2_overview_enabled' : 'vpn_manager_v2_provisioning_status_disabled'),
            'vpn_manager_v2_sync_status' => \Fireball\VpnManagerV2\Support\LocalizedValue::syncStatus($clientNode['sync_status'] ?? 'pending'),
            'vpn_manager_v2_client_traffic_sync' => \Fireball\VpnManagerV2\Support\LocalizedValue::syncStatus($trafficSyncStatus === 'failed' ? 'sync_error' : $trafficSyncStatus),
            'vpn_manager_v2_client_traffic_checked' => (string)(($clientNode['traffic_synced_at'] ?? '') ?: '—'),
            'vpn_manager_v2_col_last_sync' => (string)(($clientNode['last_seen_remote_at'] ?? '') ?: '—'),
        ];
        ?>
        <details class="border rounded-4 p-3 mt-3" data-vpn-v2-client-card>
            <summary class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span class="fw-semibold text-break">#<?= $clientNodeId ?> · <?= htmlSC((string)$clientNode['server_name']) ?> → <?= htmlSC((string)$clientNode['inbound_name']) ?></span>
                <span class="d-inline-flex align-items-center gap-2"><?= \Fireball\VpnManagerV2\Support\ProvisioningStatus::badge((string)$clientNode['status']) ?><i class="ci-chevron-down" aria-hidden="true"></i></span>
            </summary>
            <div class="mt-3">
                <dl class="row g-2 mb-3">
                    <?php foreach ($clientFields as $label => $value): ?>
                        <dt class="col-sm-5 text-body-secondary small"><?= htmlSC($clientT($label)) ?></dt><dd class="col-sm-7 mb-0 text-break small"><?= htmlSC($value) ?></dd>
                    <?php endforeach; ?>
                </dl>
                <?php if (!empty($clientNode['last_error'])): ?><div class="small text-danger text-break mb-3"><?= htmlSC((string)$clientNode['last_error']) ?></div><?php endif; ?>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill d-inline-flex align-items-center gap-2 text-wrap text-start mw-100"
                            data-vpn-v2-client-inspect="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . $subscriptionId . '/connections/' . $clientNodeId . '/client-info')) ?>"
                            data-server-id="<?= (int)$clientNode['server_id'] ?>" data-connection-id="<?= $clientNodeId ?>"
                            data-loading="<?= htmlSC($clientT('vpn_manager_v2_client_loading')) ?>"
                            data-failed="<?= htmlSC($clientT('vpn_manager_v2_client_inspection_failed')) ?>"
                            data-live-label="<?= htmlSC($clientT('vpn_manager_v2_client_live_data')) ?>"
                            <?= in_array((string)$clientNode['status'], ['deleting', 'pending_remote_delete', 'delete_failed'], true) ? 'disabled' : '' ?>>
                        <i class="ci-refresh-cw flex-shrink-0" aria-hidden="true"></i><span data-vpn-v2-client-button-label><?= htmlSC($clientT('vpn_manager_v2_client_inspect')) ?></span>
                    </button>
                    <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/connections/' . $clientNodeId)) ?>"><?= htmlSC($clientT('vpn_manager_v2_action_view')) ?></a>
                </div>
                <div class="small text-body-secondary mt-2" data-vpn-v2-client-readonly><?= htmlSC($clientT('vpn_manager_v2_client_readonly')) ?></div>
                <div class="small mt-2 text-break" role="status" aria-live="polite" data-vpn-v2-client-result></div>
                <dl class="row g-2 mb-0 mt-3" data-vpn-v2-client-live hidden></dl>
            </div>
        </details>
    <?php endforeach; ?>
    <?php if ($usage['connections'] === 0): ?><div class="alert alert-info mb-0"><?= htmlSC($clientT('vpn_manager_v2_client_no_connections')) ?></div><?php endif; ?>
</section>
