<?php
$t = static fn(string $key): string => FireballPluginVpnManagerV2::t('vpn_manager_v2_online_' . $key);
$summary = $snapshot['summary'];
$connections = $snapshot['connections'];
$noData = $summary['total_servers'] > 0 && $summary['checked'] === 0;
$rows = $mobileCards = [];
$columns = [];
foreach (['user', 'server', 'inbound', 'presence', 'last_seen', 'subscription'] as $key) {
    $columns[] = ['html' => '<button type="button" class="btn btn-link p-0 text-start text-reset text-decoration-none fw-semibold d-inline-flex align-items-center gap-2" data-vpn-online-sort="'
        . htmlSC($key) . '"><span>' . htmlSC($t($key)) . '</span><i class="ci-chevron-down" data-vpn-online-sort-icon aria-hidden="true"' . ($key !== 'last_seen' ? ' hidden' : '') . '></i></button>'];
}
foreach ($connections as $connection) {
    $id = (int)$connection['connection_id'];
    $subscriptionId = (int)$connection['subscription_id'];
    $user = trim($connection['user_name']) ?: trim($connection['client_name']) ?: '#' . $subscriptionId;
    $clientName = trim($connection['client_name']);
    $presence = $connection['presence'];
    $badgeClass = ['online' => 'text-bg-success', 'offline' => 'text-bg-secondary', 'unknown' => 'text-bg-warning'][$presence];
    $badge = '<span class="badge rounded-pill ' . $badgeClass . '">' . htmlSC($t($presence)) . '</span>';
    $lastSeen = $connection['last_seen_at'];
    $search = implode(' ', [$user, $clientName, $connection['user_login'], '#' . $subscriptionId,
        $connection['server_name'], $connection['inbound_name'], $connection['plan_name'], $connection['protocol'], $connection['port']]);
    $attributes = [
        'data-vpn-online-row' => (string)$id, 'data-presence' => $presence, 'data-search' => $search,
        'data-sort-user' => $user, 'data-sort-subscription' => (string)$subscriptionId,
        'data-sort-server' => $connection['server_name'], 'data-sort-inbound' => $connection['inbound_name'],
        'data-sort-presence' => $presence, 'data-sort-last_seen' => $lastSeen,
        'hidden' => $presence !== 'online',
    ];
    $userHtml = '<a class="fw-semibold text-break" href="' . htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . $subscriptionId)) . '">' . htmlSC($user) . '</a>';
    $userMeta = implode(' · ', array_filter([$clientName !== $user ? $clientName : '', $connection['user_login']], static fn(string $value): bool => $value !== ''));
    $userMetaHtml = $userMeta === '' ? '' : '<span class="d-block small fw-normal text-body-secondary text-break">' . htmlSC($userMeta) . '</span>';
    $subscriptionHtml = htmlSC($connection['plan_name']) . ' <span class="small text-body-secondary text-nowrap">#' . $subscriptionId . '</span>';
    $serverHtml = '<a class="text-break" href="' . htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers/edit/' . (int)$connection['server_id'])) . '">' . htmlSC($connection['server_name']) . '</a>';
    $inboundHtml = '<a class="text-break" href="' . htmlSC(base_href('/admin/plugins/vpn-manager-v2/connections/' . $id)) . '">' . htmlSC($connection['inbound_name']) . '</a>'
        . '<span class="d-block small text-body-secondary">' . htmlSC($connection['protocol']) . ' · ' . (int)$connection['port'] . '</span>';
    $lastSeenHtml = $lastSeen !== '' ? '<time datetime="' . htmlSC(str_replace(' ', 'T', $lastSeen)) . '">' . htmlSC($lastSeen) . '</time>' : '<span class="text-body-secondary">' . htmlSC($t('never_seen')) . '</span>';
    $rows[] = ['attributes' => $attributes, 'cells' => array_map(static fn(string $html): array => ['html' => $html],
        [$userHtml . $userMetaHtml, $serverHtml, $inboundHtml, $badge, $lastSeenHtml, $subscriptionHtml])];
    $mobileCards[] = [
        'attributes' => $attributes,
        'title' => ['html' => '<span class="d-flex flex-wrap align-items-center gap-2">' . $userHtml . $badge . '</span>' . $userMetaHtml],
        'extra_fields' => [
            ['label' => $t('server'), 'html' => $serverHtml . '<span class="d-block small">' . $inboundHtml . '</span>'],
            ['label' => $t('last_seen'), 'html' => $lastSeenHtml],
            ['label' => $t('subscription'), 'html' => $subscriptionHtml],
        ],
    ];
}
?>
<?php require __DIR__ . '/online-summary.php'; ?>
<div data-vpn-online-table <?= $summary['connections'] === 0 ? 'hidden' : '' ?>>
    <?= view()->renderPartial('admin/partials/table', [
        'columns' => $columns,
        'rows' => $rows,
        'mobile_cards' => $mobileCards,
        'mobile_breakpoint' => 'lg',
        'empty_text' => $t('empty'),
        'table_class' => 'table-sm',
        'caption' => $t('table_title'),
    ]) ?>
</div>
<div class="fb-card fb-empty-state" data-vpn-online-empty <?= $summary['connections'] > 0 ? 'hidden' : '' ?>>
    <p class="mb-0"><?= htmlSC($noData ? $t('unknown') : $t('empty')) ?></p>
</div>
<p class="small text-body-secondary mt-2 mb-0"><?= htmlSC($t('last_seen_hint')) ?></p>
