<?php
$t = static fn(string $key): string => FireballPluginVpnManagerV2::t('vpn_manager_v2_online_' . $key);
$snapshot = $snapshot ?? null;
$summary = $snapshot['summary'] ?? [];
$connections = $snapshot['connections'] ?? [];
$noData = $snapshot === null || ((int)($summary['total_servers'] ?? 0) > 0 && (int)($summary['checked'] ?? 0) === 0);
$cards = [
    'users' => ['users', 'ci-user', 'blue'],
    'connections' => ['connections', 'ci-share-2', 'green'],
    'subscriptions' => ['subscriptions', 'ci-link', 'purple'],
    'servers' => ['servers', 'ci-server', 'primary'],
];
$rows = $mobileCards = [];
$columns = [];
foreach (['user', 'subscription', 'server', 'inbound', 'presence', 'last_seen'] as $key) {
    $columns[] = ['html' => '<button type="button" class="btn btn-link p-0 text-reset text-decoration-none fw-semibold d-inline-flex align-items-center gap-2" data-vpn-online-sort="'
        . htmlSC($key) . '"><span>' . htmlSC($t($key)) . '</span><i class="ci-chevron-down" data-vpn-online-sort-icon aria-hidden="true"></i></button>'];
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
    $userHtml = '<a class="fw-medium text-decoration-none" href="' . htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . $subscriptionId)) . '">' . htmlSC($user) . '</a>';
    if ($clientName !== '' && $clientName !== $user) {
        $userHtml .= '<div class="small text-body-secondary">' . htmlSC($clientName) . '</div>';
    }
    if ($connection['user_login'] !== '') {
        $userHtml .= '<div class="small text-body-secondary">' . htmlSC($connection['user_login']) . '</div>';
    }
    $subscriptionHtml = '<span class="text-body-secondary">#' . $subscriptionId . '</span><div>' . htmlSC($connection['plan_name']) . '</div>';
    $serverHtml = '<a class="text-decoration-none" href="' . htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers/edit/' . (int)$connection['server_id'])) . '">' . htmlSC($connection['server_name']) . '</a>';
    $inboundHtml = '<a class="text-decoration-none" href="' . htmlSC(base_href('/admin/plugins/vpn-manager-v2/connections/' . $id)) . '">' . htmlSC($connection['inbound_name']) . '</a>'
        . '<div class="small text-body-secondary">' . htmlSC($connection['protocol']) . ' · ' . (int)$connection['port'] . '</div>';
    $lastSeenHtml = $lastSeen !== '' ? htmlSC($lastSeen) : '<span class="text-body-secondary">' . htmlSC($t('never_seen')) . '</span>';
    $rows[] = ['attributes' => $attributes, 'cells' => array_map(static fn(string $html): array => ['html' => $html],
        [$userHtml, $subscriptionHtml, $serverHtml, $inboundHtml, $badge, $lastSeenHtml])];
    $mobileCards[] = ['attributes' => $attributes, 'id' => '#' . $subscriptionId, 'title' => ['html' => $userHtml],
        'icon' => 'ci-user', 'status' => [['html' => $badge]], 'extra_fields' => [
            ['label' => $t('subscription'), 'html' => $subscriptionHtml], ['label' => $t('server'), 'html' => $serverHtml],
            ['label' => $t('inbound'), 'html' => $inboundHtml], ['label' => $t('last_seen'), 'html' => $lastSeenHtml],
        ]];
}
?>
<div class="row g-3 mb-3" data-vpn-online-summary>
    <?php foreach ($cards as $key => [$label, $icon, $tone]): ?>
        <div class="col-6 col-xl-3">
            <div class="fb-card fb-stat-card fb-vpn-stat-card is-<?= htmlSC($tone) ?> rounded-5 p-3 p-md-4 h-100">
                <div class="d-flex align-items-start justify-content-between gap-2">
                    <span class="small text-body-secondary"><?= htmlSC($t($label)) ?></span>
                    <span class="fb-stat-icon rounded-circle flex-shrink-0"><i class="<?= htmlSC($icon) ?>" aria-hidden="true"></i></span>
                </div>
                <div class="display-6 fw-semibold lh-1 mt-3"><?= $noData ? '—' : (int)$summary[$key] ?></div>
                <div class="small text-body-secondary mt-2"><?= htmlSC($t('stat_' . $key)) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php if ($snapshot === null): ?>
    <p class="text-body-secondary d-flex align-items-center gap-2"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><?= htmlSC($t('loading')) ?></p>
<?php else: ?>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 small text-body-secondary mb-3">
        <span><?= htmlSC(sprintf($t('checked'), (int)$summary['checked'], (int)$summary['total_servers'])) ?></span>
        <span><?= htmlSC($t('updated')) ?> <time><?= htmlSC($snapshot['updated_at']) ?></time></span>
    </div>
    <?php if ($summary['unavailable'] > 0): ?>
        <div class="alert alert-warning rounded-4" role="status"><?= htmlSC(sprintf($t('partial'), (int)$summary['unavailable'])) ?></div>
    <?php endif; ?>
    <div class="d-flex flex-wrap gap-2 mb-3">
        <?php foreach ($snapshot['servers'] as $server): ?>
            <span class="badge rounded-pill <?= !$server['available'] ? 'text-bg-warning' : ($server['online'] > 0 ? 'text-bg-success' : 'text-bg-secondary') ?>"
                  title="<?= htmlSC((string)($server['checked_at'] ?? $t('unknown'))) ?>">
                <?= htmlSC($server['name']) ?> · <?= $server['available'] ? (int)$server['online'] : htmlSC($t('unknown')) ?>
            </span>
        <?php endforeach; ?>
    </div>
    <div class="border rounded-5 p-3 p-md-4" data-vpn-online-table <?= $summary['connections'] === 0 ? 'hidden' : '' ?>>
        <?= view()->renderPartial('admin/partials/table', ['columns' => $columns, 'rows' => $rows, 'mobile_cards' => $mobileCards,
            'empty_text' => $t('empty'), 'table_class' => 'text-nowrap', 'caption' => $t('table_title')]) ?>
    </div>
    <p class="border rounded-5 p-4 text-center text-body-secondary" data-vpn-online-empty <?= $summary['connections'] > 0 ? 'hidden' : '' ?>><?= htmlSC($noData ? $t('unknown') : $t('empty')) ?></p>
    <p class="small text-body-secondary mt-3 mb-0"><?= htmlSC($t('last_seen_hint')) ?></p>
<?php endif; ?>
