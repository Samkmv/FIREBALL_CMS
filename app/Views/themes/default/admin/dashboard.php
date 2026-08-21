<?php
$currentUser = get_user() ?: [];
$displayName = trim((string)($currentUser['name'] ?? ''));
if ($displayName === '') {
    $displayName = trim((string)($currentUser['login'] ?? ''));
}
$welcomeTitle = str_replace(':name', $displayName, return_translation('admin_dashboard_welcome'));
$analytics = is_array($analytics_dashboard ?? null) ? $analytics_dashboard : [];
$analyticsCards = is_array($analytics['cards'] ?? null) ? $analytics['cards'] : [];
$analyticsPages = is_array($analytics['pages'] ?? null) ? $analytics['pages'] : [];
$analyticsLatest = is_array($analytics['latest'] ?? null) ? $analytics['latest'] : [];
$analyticsJson = json_encode($analytics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$analyticsI18nJson = json_encode([
    'visits' => return_translation('admin_analytics_chart_visits'),
    'sources' => return_translation('admin_analytics_chart_sources'),
    'devices' => return_translation('admin_analytics_chart_devices'),
    'countries' => return_translation('admin_analytics_geo_title'),
    'unavailable' => return_translation('admin_analytics_chart_unavailable'),
    'empty' => return_translation('admin_table_empty'),
    'unknown' => return_translation('admin_analytics_country_unknown'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$requestSummary = is_array($request_summary ?? null) ? $request_summary : [];
$requestItems = is_array($requestSummary['items'] ?? null) ? $requestSummary['items'] : [];
$requestStatusLabels = [
    'new' => return_translation('admin_support_status_new'),
    'in_work' => return_translation('admin_support_status_in_work'),
    'closed' => return_translation('admin_support_status_closed'),
    'spam' => return_translation('admin_support_status_spam'),
];
$releaseData = is_array($engine_release ?? null) ? $engine_release : [];
$updateData = is_array($update_center ?? null) ? $update_center : [];
$lastUpdateCheck = is_array($updateData['last_check'] ?? null) ? $updateData['last_check'] : [];
$installedVersion = (string)($releaseData['version'] ?? $updateData['local']['version'] ?? '—');
$remoteVersion = trim((string)($lastUpdateCheck['remote_version'] ?? ''));
$hasUpdate = !empty($lastUpdateCheck['update_available']);
if ($hasUpdate && $remoteVersion !== '') {
    $versionMeta = str_replace(':version', $remoteVersion, return_translation('admin_dashboard_update_available_version'));
} elseif ($lastUpdateCheck !== [] && (string)($lastUpdateCheck['status'] ?? '') === 'ok') {
    $versionMeta = return_translation('admin_dashboard_version_current');
} else {
    $versionMeta = return_translation('admin_dashboard_check_updates');
}

$statCards = [
    [
        'label' => return_translation('admin_stat_new_contacts'),
        'value' => (int)($stats['contact_requests_new'] ?? 0),
        'icon' => 'ci-bell',
        'variant' => 'is-primary',
        'href' => base_href('/admin/support/requests?status=new'),
        'meta' => return_translation('admin_dashboard_requires_attention'),
    ],
    [
        'label' => return_translation('admin_stat_contacts'),
        'value' => (int)($stats['contact_requests'] ?? 0),
        'icon' => 'ci-inbox',
        'variant' => 'is-blue',
        'href' => base_href('/admin/support/requests'),
        'meta' => return_translation('admin_dashboard_all_requests'),
    ],
    [
        'label' => return_translation('admin_stat_users'),
        'value' => (int)($stats['users'] ?? 0),
        'icon' => 'ci-user',
        'variant' => 'is-pink',
        'href' => base_href('/admin/users'),
        'meta' => return_translation('admin_dashboard_team'),
    ],
    [
        'label' => return_translation('admin_dashboard_update_version'),
        'value' => $installedVersion,
        'icon' => $hasUpdate ? 'ci-download' : 'ci-refresh-cw',
        'variant' => $hasUpdate ? 'is-primary' : 'is-green',
        'href' => check_creator() ? base_href('/admin/updates') : base_href('/admin/settings'),
        'meta' => $versionMeta,
    ],
];
$statCards = apply_filters('admin_dashboard_stat_cards', $statCards, $stats, $currentUser);
if (!is_array($statCards)) {
    $statCards = [];
}

$trafficStatCards = [
    [
        'label' => return_translation('admin_analytics_today_visits'),
        'value' => (int)($analyticsCards['today_visits'] ?? 0),
        'icon' => 'ci-eye',
        'variant' => 'is-primary',
    ],
    [
        'label' => return_translation('admin_analytics_today_unique'),
        'value' => (int)($analyticsCards['today_unique'] ?? 0),
        'icon' => 'ci-user-check',
        'variant' => 'is-blue',
    ],
    [
        'label' => return_translation('admin_analytics_visits_7'),
        'value' => (int)($analyticsCards['visits_7'] ?? 0),
        'icon' => 'ci-calendar',
        'variant' => 'is-purple',
    ],
    [
        'label' => return_translation('admin_analytics_visits_30'),
        'value' => (int)($analyticsCards['visits_30'] ?? 0),
        'icon' => 'ci-trending-up',
        'variant' => 'is-green',
    ],
    [
        'label' => return_translation('admin_analytics_mobile_percent'),
        'value' => rtrim(rtrim(number_format((float)($analyticsCards['mobile_percent'] ?? 0), 1, '.', ''), '0'), '.') . '%',
        'icon' => 'ci-smartphone',
        'variant' => 'is-pink',
    ],
    [
        'label' => return_translation('admin_analytics_desktop_percent'),
        'value' => rtrim(rtrim(number_format((float)($analyticsCards['desktop_percent'] ?? 0), 1, '.', ''), '0'), '.') . '%',
        'icon' => 'ci-monitor',
        'variant' => 'is-blue',
    ],
];
$trafficStatCards = apply_filters('admin_dashboard_traffic_stat_cards', $trafficStatCards, $analytics, $currentUser);
if (!is_array($trafficStatCards)) {
    $trafficStatCards = [];
}

$quickActions = [
    ['label' => return_translation('admin_dashboard_create_page'), 'href' => base_href('/admin/pages/create'), 'icon' => 'ci-file-plus', 'color' => 'var(--fb-color-info)', 'soft' => 'var(--fb-color-info-soft)'],
    ['label' => return_translation('admin_dashboard_create_post'), 'href' => base_href('/admin/posts/create'), 'icon' => 'ci-edit-3', 'color' => 'var(--fb-color-purple)', 'soft' => 'var(--fb-color-purple-soft)'],
    ['label' => return_translation('admin_dashboard_upload_media'), 'href' => base_href('/admin/files'), 'icon' => 'ci-image', 'color' => 'var(--fb-color-success)', 'soft' => 'var(--fb-color-success-soft)'],
    ['label' => return_translation('admin_dashboard_manage_plugins'), 'href' => base_href('/admin/plugins'), 'icon' => 'ci-box', 'color' => 'var(--fb-color-primary)', 'soft' => 'var(--fb-color-primary-soft)'],
    ['label' => return_translation('admin_dashboard_manage_theme'), 'href' => base_href('/admin/themes'), 'icon' => 'ci-monitor', 'color' => 'var(--fb-color-purple)', 'soft' => 'var(--fb-color-purple-soft)'],
    ['label' => return_translation('admin_dashboard_create_user'), 'href' => base_href('/admin/users/create'), 'icon' => 'ci-user-plus', 'color' => 'var(--fb-color-info)', 'soft' => 'var(--fb-color-info-soft)', 'creator_only' => true],
];
$quickActions = apply_filters('admin_quick_actions', $quickActions, $currentUser);
if (!is_array($quickActions)) {
    $quickActions = [];
}

$activityItems = apply_filters('admin_dashboard_activity', (array)($recent_activity ?? []), $currentUser);
if (!is_array($activityItems)) {
    $activityItems = [];
}

$pluginWidgets = apply_filters_safe('admin_dashboard_widgets', [], [
    'stats' => $stats,
    'user' => $currentUser,
    'plugins' => (array)($active_plugins ?? []),
]);
if (!is_array($pluginWidgets)) {
    $pluginWidgets = [];
}

echo view()->renderPartial('admin/shell_open', [
    'title' => $welcomeTitle,
    'subtitle' => return_translation('admin_dashboard_welcome_subtitle'),
]);
?>

<section class="fb-stat-grid" aria-label="<?= htmlSC(return_translation('admin_dashboard_overview')) ?>">
    <?php foreach ($statCards as $card): ?>
        <?php if (!is_array($card) || !isset($card['value'], $card['label'])) { continue; } ?>
        <a class="fb-card fb-stat-card <?= htmlSC((string)($card['variant'] ?? '')) ?>" href="<?= htmlSC((string)($card['href'] ?? '#')) ?>">
            <span class="fb-stat-icon"><i class="<?= htmlSC((string)($card['icon'] ?? 'ci-activity')) ?>" aria-hidden="true"></i></span>
            <span class="fb-stat-copy">
                <strong class="fb-stat-value"><?= htmlSC((string)$card['value']) ?></strong>
                <span class="fb-stat-label"><?= htmlSC((string)$card['label']) ?></span>
                <?php if (!empty($card['meta'])): ?>
                    <span class="fb-stat-meta"><i class="ci-arrow-up-right" aria-hidden="true"></i><?= htmlSC((string)$card['meta']) ?></span>
                <?php endif; ?>
            </span>
        </a>
    <?php endforeach; ?>
</section>

<header class="fb-dashboard-section-heading">
    <div>
        <h2><?= print_translation('admin_dashboard_work_summary') ?></h2>
        <p><?= print_translation('admin_dashboard_work_summary_subtitle') ?></p>
    </div>
    <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= base_href('/admin/support/requests') ?>">
        <?= print_translation('admin_dashboard_all_requests') ?>
    </a>
</header>

<section class="fb-dashboard-focus-grid" aria-label="<?= htmlSC(return_translation('admin_dashboard_work_summary')) ?>">
    <article class="fb-card fb-dashboard-widget fb-dashboard-requests-card">
        <header class="fb-card-header">
            <div>
                <h2 class="fb-card-title"><?= print_translation('admin_dashboard_recent_requests') ?></h2>
                <p class="fb-card-subtitle"><?= print_translation('admin_dashboard_recent_requests_subtitle') ?></p>
            </div>
            <span class="fb-dashboard-count-badge"><?= (int)($requestSummary['new'] ?? 0) ?> <?= print_translation('admin_support_status_new') ?></span>
        </header>
        <div class="fb-card-body pt-3">
            <div class="fb-request-status-grid" aria-label="<?= htmlSC(return_translation('admin_contacts_col_status')) ?>">
                <?php foreach (['new', 'in_work', 'closed'] as $requestStatus): ?>
                    <a class="fb-request-status fb-request-status--<?= htmlSC($requestStatus) ?>" href="<?= base_href('/admin/support/requests?status=' . $requestStatus) ?>">
                        <strong><?= (int)($requestSummary[$requestStatus] ?? 0) ?></strong>
                        <span><?= htmlSC((string)($requestStatusLabels[$requestStatus] ?? $requestStatus)) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($requestItems !== []): ?>
                <ul class="fb-request-list">
                    <?php foreach ($requestItems as $requestItem): ?>
                        <?php
                        if (!is_array($requestItem)) { continue; }
                        $requestId = (int)($requestItem['id'] ?? 0);
                        $requestStatus = (string)($requestItem['status'] ?? 'new');
                        if (!isset($requestStatusLabels[$requestStatus])) { $requestStatus = 'new'; }
                        $requestDate = strtotime((string)($requestItem['created_at'] ?? '')) ?: time();
                        ?>
                        <li class="fb-request-item">
                            <span class="fb-request-avatar" aria-hidden="true"><?= htmlSC(mb_strtoupper(mb_substr(trim((string)($requestItem['name'] ?? '?')), 0, 1))) ?></span>
                            <div class="fb-request-copy">
                                <a class="fb-request-title" href="<?= base_href('/admin/support/requests/reply/' . $requestId) ?>"><?= htmlSC((string)($requestItem['subject'] ?? '')) ?></a>
                                <span class="fb-request-meta"><?= htmlSC((string)($requestItem['name'] ?? '')) ?> · <?= htmlSC(date('d.m.Y H:i', $requestDate)) ?></span>
                            </div>
                            <span class="fb-request-badge is-<?= htmlSC($requestStatus) ?>"><?= htmlSC((string)$requestStatusLabels[$requestStatus]) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="fb-empty-state fb-empty-state--compact">
                    <div><span class="fb-empty-state-icon"><i class="ci-inbox" aria-hidden="true"></i></span><p class="mb-0"><?= print_translation('admin_contacts_empty') ?></p></div>
                </div>
            <?php endif; ?>
        </div>
    </article>

    <article class="fb-card fb-dashboard-widget fb-dashboard-content-card">
        <header class="fb-card-header">
            <div>
                <h2 class="fb-card-title"><?= print_translation('admin_dashboard_content_summary') ?></h2>
                <p class="fb-card-subtitle"><?= print_translation('admin_dashboard_content_summary_subtitle') ?></p>
            </div>
        </header>
        <div class="fb-card-body">
            <div class="fb-content-summary-list">
                <?php foreach ([
                    [return_translation('admin_stat_posts'), (int)($stats['posts'] ?? 0), 'ci-file-text', '/admin/posts'],
                    [return_translation('admin_stat_pages'), (int)($stats['pages'] ?? 0), 'ci-file', '/admin/pages'],
                    [return_translation('admin_stat_categories'), (int)($stats['categories'] ?? 0), 'ci-folder', '/admin/categories'],
                    [return_translation('admin_stat_support_kb_articles'), (int)($stats['support_kb_articles'] ?? 0), 'ci-book-open', '/admin/support/knowledge-base'],
                    [return_translation('admin_stat_support_faq'), (int)($stats['support_faq'] ?? 0), 'ci-help-circle', '/admin/support/faq'],
                ] as $contentItem): ?>
                    <a class="fb-content-summary-item" href="<?= base_href((string)$contentItem[3]) ?>">
                        <span class="fb-content-summary-icon"><i class="<?= htmlSC((string)$contentItem[2]) ?>" aria-hidden="true"></i></span>
                        <span><?= htmlSC((string)$contentItem[0]) ?></span>
                        <strong><?= (int)$contentItem[1] ?></strong>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </article>
</section>

<?php if ($pluginWidgets !== []): ?>
    <header class="fb-dashboard-section-heading">
        <div>
            <h2><?= print_translation('admin_dashboard_plugin_widgets') ?></h2>
            <p><?= print_translation('admin_dashboard_plugin_widgets_subtitle') ?></p>
        </div>
        <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= base_href('/admin/plugins') ?>"><?= print_translation('admin_dashboard_manage_plugins') ?></a>
    </header>

    <section class="fb-dashboard-plugin-grid" aria-label="<?= htmlSC(return_translation('admin_dashboard_plugin_widgets')) ?>">
        <?php foreach ($pluginWidgets as $pluginWidget): ?>
            <?php
            if (!is_array($pluginWidget)) { continue; }
            $pluginMetrics = is_array($pluginWidget['metrics'] ?? null) ? $pluginWidget['metrics'] : [];
            $pluginContent = (string)($pluginWidget['content'] ?? '');
            if ($pluginMetrics === [] && $pluginContent === '') { continue; }
            $span = max(1, min(3, (int)($pluginWidget['span'] ?? 1)));
            ?>
            <article class="fb-card fb-dashboard-widget fb-plugin-widget<?= $span > 1 ? ' fb-dashboard-span-' . $span : '' ?>">
                <header class="fb-card-header">
                    <div class="fb-plugin-widget-heading">
                        <span class="fb-plugin-widget-icon"><i class="<?= htmlSC((string)($pluginWidget['icon'] ?? 'ci-box')) ?>" aria-hidden="true"></i></span>
                        <div>
                            <?php if (!empty($pluginWidget['title'])): ?><h2 class="fb-card-title"><?= htmlSC((string)$pluginWidget['title']) ?></h2><?php endif; ?>
                            <?php if (!empty($pluginWidget['subtitle'])): ?><p class="fb-card-subtitle"><?= htmlSC((string)$pluginWidget['subtitle']) ?></p><?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($pluginWidget['href'])): ?>
                        <a class="btn btn-sm btn-link" href="<?= htmlSC((string)$pluginWidget['href']) ?>" aria-label="<?= htmlSC(return_translation('admin_dashboard_open_widget')) ?>"><i class="ci-arrow-up-right" aria-hidden="true"></i></a>
                    <?php endif; ?>
                </header>
                <div class="fb-card-body">
                    <?php if ($pluginMetrics !== []): ?>
                        <div class="fb-plugin-metric-grid">
                            <?php foreach ($pluginMetrics as $pluginMetric): ?>
                                <?php if (!is_array($pluginMetric) || !array_key_exists('value', $pluginMetric)) { continue; } ?>
                                <?php $metricTone = in_array((string)($pluginMetric['tone'] ?? ''), ['success', 'warning', 'danger', 'info'], true) ? (string)$pluginMetric['tone'] : 'neutral'; ?>
                                <div class="fb-plugin-metric is-<?= htmlSC($metricTone) ?>">
                                    <strong><?= htmlSC((string)$pluginMetric['value']) ?></strong>
                                    <span><?= htmlSC((string)($pluginMetric['label'] ?? '')) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($pluginContent !== ''): ?><?= $pluginContent ?><?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<header class="fb-dashboard-section-heading">
    <div>
        <h2><?= print_translation('admin_analytics_title') ?></h2>
        <p><?= print_translation('admin_analytics_subtitle') ?></p>
    </div>
    <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= base_href('/admin/analytics') ?>">
        <?= print_translation('admin_analytics_view_all') ?>
    </a>
</header>

<section class="fb-traffic-stat-grid" aria-label="<?= htmlSC(return_translation('admin_analytics_title')) ?>">
    <?php foreach ($trafficStatCards as $card): ?>
        <?php if (!is_array($card) || !isset($card['value'], $card['label'])) { continue; } ?>
        <a class="fb-card fb-traffic-stat-card <?= htmlSC((string)($card['variant'] ?? '')) ?>" href="<?= base_href('/admin/analytics') ?>">
            <span class="fb-traffic-stat-icon"><i class="<?= htmlSC((string)($card['icon'] ?? 'ci-activity')) ?>" aria-hidden="true"></i></span>
            <span class="fb-traffic-stat-copy">
                <strong><?= htmlSC((string)$card['value']) ?></strong>
                <span><?= htmlSC((string)$card['label']) ?></span>
            </span>
        </a>
    <?php endforeach; ?>
</section>

<section
    class="fb-dashboard-grid"
    data-admin-analytics
    data-admin-analytics-payload="<?= htmlSC($analyticsJson ?: '{}') ?>"
    data-admin-analytics-i18n="<?= htmlSC($analyticsI18nJson ?: '{}') ?>"
>
    <article class="fb-card fb-dashboard-widget fb-dashboard-span-2 fb-dashboard-traffic-card">
        <header class="fb-card-header">
            <div>
                <h2 class="fb-card-title"><?= print_translation('admin_analytics_traffic_title') ?></h2>
                <p class="fb-card-subtitle"><?= print_translation('admin_dashboard_traffic_subtitle') ?></p>
            </div>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-analytics-range-toggle>
                    <span data-analytics-range-label><?= print_translation('admin_analytics_range_7') ?></span>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php foreach (['7', '30', '90'] as $range): ?>
                        <button
                            class="dropdown-item<?= $range === '7' ? ' active' : '' ?>"
                            type="button"
                            data-analytics-range="<?= $range ?>"
                        ><?= print_translation('admin_analytics_range_' . $range) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </header>
        <div class="fb-card-body fb-dashboard-chart">
            <div class="fb-dashboard-chart-summary" aria-live="polite">
                <strong data-analytics-range-total>0</strong>
                <span><?= print_translation('admin_analytics_chart_visits') ?></span>
            </div>
            <div data-analytics-chart="traffic" aria-label="<?= htmlSC(return_translation('admin_analytics_traffic_title')) ?>"></div>
        </div>
    </article>

    <article class="fb-card fb-dashboard-widget fb-dashboard-chart-card">
        <header class="fb-card-header">
            <div>
                <h2 class="fb-card-title"><?= print_translation('admin_analytics_sources_title') ?></h2>
                <p class="fb-card-subtitle"><?= print_translation('admin_analytics_sources_subtitle') ?></p>
            </div>
        </header>
        <div class="fb-card-body fb-dashboard-chart fb-dashboard-chart--compact">
            <div data-analytics-chart="sources" aria-label="<?= htmlSC(return_translation('admin_analytics_sources_title')) ?>"></div>
        </div>
    </article>

    <article class="fb-card fb-dashboard-widget fb-dashboard-chart-card">
        <header class="fb-card-header">
            <div>
                <h2 class="fb-card-title"><?= print_translation('admin_analytics_devices_title') ?></h2>
                <p class="fb-card-subtitle"><?= print_translation('admin_analytics_devices_subtitle') ?></p>
            </div>
        </header>
        <div class="fb-card-body fb-dashboard-chart fb-dashboard-chart--compact">
            <div data-analytics-chart="devices" aria-label="<?= htmlSC(return_translation('admin_analytics_devices_title')) ?>"></div>
        </div>
    </article>

    <article class="fb-card fb-dashboard-widget fb-dashboard-chart-card">
        <header class="fb-card-header">
            <div>
                <h2 class="fb-card-title"><?= print_translation('admin_analytics_geo_title') ?></h2>
                <p class="fb-card-subtitle"><?= print_translation('admin_analytics_full_subtitle') ?></p>
            </div>
        </header>
        <div class="fb-card-body fb-dashboard-chart fb-dashboard-chart--compact">
            <div data-analytics-chart="countries" aria-label="<?= htmlSC(return_translation('admin_analytics_geo_title')) ?>"></div>
        </div>
    </article>

    <article class="fb-card fb-dashboard-widget">
        <header class="fb-card-header">
            <h2 class="fb-card-title"><?= print_translation('admin_analytics_pages_title') ?></h2>
            <a class="btn btn-sm btn-link" href="<?= base_href('/admin/analytics') ?>"><?= print_translation('admin_dashboard_view_all') ?></a>
        </header>
        <div class="fb-card-body pt-3">
            <?php if ($analyticsPages !== []): ?>
                <div class="table-responsive fb-dashboard-analytics-table">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th><?= print_translation('admin_analytics_col_page') ?></th><th class="text-end"><?= print_translation('admin_analytics_col_views') ?></th></tr></thead>
                        <tbody>
                            <?php foreach (array_slice($analyticsPages, 0, 15) as $page): ?>
                                <tr><td class="text-break"><?= htmlSC((string)($page['label'] ?? '/')) ?></td><td class="text-end fw-semibold"><?= (int)($page['views'] ?? $page['total'] ?? 0) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="fb-empty-state"><div><span class="fb-empty-state-icon"><i class="ci-bar-chart" aria-hidden="true"></i></span><p class="mb-0"><?= print_translation('admin_analytics_empty') ?></p></div></div>
            <?php endif; ?>
        </div>
    </article>

    <article class="fb-card fb-dashboard-widget fb-dashboard-span-2 fb-dashboard-latest-visits">
        <header class="fb-card-header">
            <h2 class="fb-card-title"><?= print_translation('admin_analytics_latest_title') ?></h2>
            <a class="btn btn-sm btn-link" href="<?= base_href('/admin/analytics') ?>"><?= print_translation('admin_dashboard_view_all') ?></a>
        </header>
        <div class="fb-card-body pt-3">
            <?php if ($analyticsLatest !== []): ?>
                <div class="table-responsive fb-dashboard-analytics-table">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th><?= print_translation('admin_analytics_col_time') ?></th><th><?= print_translation('admin_analytics_col_country') ?></th><th><?= print_translation('admin_analytics_col_device') ?></th><th><?= print_translation('admin_analytics_col_browser') ?></th><th><?= print_translation('admin_analytics_col_page') ?></th></tr></thead>
                        <tbody>
                            <?php foreach (array_slice($analyticsLatest, 0, 15) as $visit): ?>
                                <?php $visitTime = strtotime((string)($visit['created_at'] ?? '')) ?: time(); ?>
                                <tr>
                                    <td class="text-nowrap"><?= htmlSC(date('d.m H:i', $visitTime)) ?></td>
                                    <td><?= htmlSC((string)($visit['country'] ?? return_translation('admin_analytics_country_unknown'))) ?></td>
                                    <td><?= htmlSC((string)($visit['device_type'] ?? '')) ?></td>
                                    <td><?= htmlSC((string)($visit['browser'] ?? '')) ?></td>
                                    <td class="text-break"><?= htmlSC((string)($visit['current_page'] ?? '/')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="fb-empty-state"><div><span class="fb-empty-state-icon"><i class="ci-globe" aria-hidden="true"></i></span><p class="mb-0"><?= print_translation('admin_analytics_empty') ?></p></div></div>
            <?php endif; ?>
        </div>
    </article>

    <article class="fb-card fb-dashboard-widget fb-dashboard-quick-actions-card">
        <header class="fb-card-header">
            <h2 class="fb-card-title"><?= print_translation('admin_dashboard_quick_actions') ?></h2>
        </header>
        <div class="fb-card-body">
            <div class="fb-quick-actions">
                <?php foreach ($quickActions as $action): ?>
                    <?php
                    if (!is_array($action) || empty($action['label']) || empty($action['href'])) {
                        continue;
                    }
                    if (!empty($action['creator_only']) && !check_creator()) {
                        continue;
                    }
                    ?>
                    <a
                        class="fb-quick-action"
                        href="<?= htmlSC((string)$action['href']) ?>"
                        style="--fb-action-color: <?= htmlSC((string)($action['color'] ?? 'var(--fb-color-info)')) ?>; --fb-action-soft: <?= htmlSC((string)($action['soft'] ?? 'var(--fb-color-info-soft)')) ?>;"
                        data-fb-quick-action
                        data-fb-quick-action-label="<?= htmlSC((string)$action['label']) ?>"
                        data-fb-quick-action-category="<?= htmlSC(return_translation('admin_dashboard_quick_actions')) ?>"
                        data-fb-quick-action-icon="<?= htmlSC((string)($action['icon'] ?? 'ci-arrow-right')) ?>"
                        data-fb-command-kind="action"
                    >
                        <span class="fb-quick-action-icon"><i class="<?= htmlSC((string)($action['icon'] ?? 'ci-arrow-right')) ?>" aria-hidden="true"></i></span>
                        <span><?= htmlSC((string)$action['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </article>

    <div class="fb-dashboard-status-pages-row">
    <article class="fb-card fb-dashboard-widget fb-dashboard-system-card">
        <header class="fb-card-header">
            <h2 class="fb-card-title"><?= print_translation('admin_dashboard_system_status') ?></h2>
        </header>
        <div class="fb-card-body">
            <ul class="fb-system-list">
                <?php foreach ((array)($system_status ?? []) as $statusItem): ?>
                    <?php if (!is_array($statusItem)) { continue; } ?>
                    <li class="fb-system-row">
                        <i class="ci-check-circle fb-system-status" aria-hidden="true"></i>
                        <span class="fb-system-label"><?= htmlSC((string)($statusItem['label'] ?? '')) ?></span>
                        <strong class="fb-system-value"><?= htmlSC((string)($statusItem['value'] ?? '—')) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </article>

    <article class="fb-card fb-dashboard-widget fb-dashboard-recent-pages">
        <header class="fb-card-header">
            <h2 class="fb-card-title"><?= print_translation('admin_dashboard_recent_pages') ?></h2>
            <a class="btn btn-sm btn-link" href="<?= base_href('/admin/pages') ?>"><?= print_translation('admin_dashboard_view_all') ?></a>
        </header>
        <div class="fb-card-body pt-3">
            <?php if (!empty($latest_pages)): ?>
                <ul class="fb-simple-list">
                    <?php foreach ((array)$latest_pages as $page): ?>
                        <li class="fb-simple-list-item">
                            <span class="fb-simple-list-leading"><i class="ci-file" aria-hidden="true"></i></span>
                            <div class="fb-simple-list-copy">
                                <a class="fb-simple-list-title d-block text-decoration-none" href="<?= base_href('/admin/pages/edit/' . (int)($page['id'] ?? 0)) ?>">
                                    <?= htmlSC((string)($page['title'] ?? '')) ?>
                                </a>
                                <div class="fb-simple-list-meta">/<?= htmlSC((string)($page['slug'] ?? '')) ?> · <?= htmlSC(date('d.m.Y', strtotime((string)($page['updated_at'] ?? 'now')) ?: time())) ?></div>
                            </div>
                            <span class="fb-list-status"><?= htmlSC(return_translation(!empty($page['is_published']) ? 'admin_dashboard_published' : 'admin_dashboard_draft')) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="fb-empty-state">
                    <div>
                        <span class="fb-empty-state-icon"><i class="ci-file" aria-hidden="true"></i></span>
                        <p class="mb-0"><?= print_translation('admin_dashboard_no_pages') ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </article>
    </div>

    <article class="fb-card fb-dashboard-widget">
        <header class="fb-card-header">
            <h2 class="fb-card-title"><?= print_translation('admin_dashboard_installed_plugins') ?></h2>
            <a class="btn btn-sm btn-link" href="<?= base_href('/admin/plugins') ?>"><?= print_translation('admin_dashboard_view_all') ?></a>
        </header>
        <div class="fb-card-body pt-3">
            <?php if (!empty($active_plugins)): ?>
                <ul class="fb-simple-list">
                    <?php foreach (array_slice((array)$active_plugins, 0, 5) as $plugin): ?>
                        <li class="fb-simple-list-item">
                            <span class="fb-simple-list-leading"><i class="ci-box" aria-hidden="true"></i></span>
                            <div class="fb-simple-list-copy">
                                <div class="fb-simple-list-title"><?= htmlSC((string)($plugin['name'] ?? $plugin['slug'] ?? '')) ?></div>
                                <div class="fb-simple-list-meta"><?= htmlSC((string)($plugin['version'] ?? '')) ?></div>
                            </div>
                            <span class="fb-list-status"><?= print_translation('admin_plugins_status_active') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="fb-empty-state">
                    <div>
                        <span class="fb-empty-state-icon"><i class="ci-box" aria-hidden="true"></i></span>
                        <p class="mb-0"><?= print_translation('admin_dashboard_no_plugins') ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </article>

    <article class="fb-card fb-dashboard-widget">
        <header class="fb-card-header">
            <h2 class="fb-card-title"><?= print_translation('admin_dashboard_recent_activity') ?></h2>
            <a class="btn btn-sm btn-link" href="<?= base_href('/admin/security/logs') ?>"><?= print_translation('admin_dashboard_view_all') ?></a>
        </header>
        <div class="fb-card-body pt-3">
            <?php if ($activityItems !== []): ?>
                <ul class="fb-activity-list">
                    <?php foreach (array_slice($activityItems, 0, 6) as $activityItem): ?>
                        <?php
                        if (!is_array($activityItem) || empty($activityItem['event'])) {
                            continue;
                        }
                        $event = trim(str_replace(['.', '_', '-'], ' ', (string)$activityItem['event']));
                        $actor = trim((string)($activityItem['actor_login'] ?? ''));
                        $createdAt = strtotime((string)($activityItem['created_at'] ?? '')) ?: time();
                        ?>
                        <li class="fb-activity-item">
                            <span class="fb-activity-icon"><i class="ci-activity" aria-hidden="true"></i></span>
                            <div class="fb-activity-copy">
                                <div class="fb-activity-title"><?= htmlSC(ucfirst($event)) ?></div>
                                <div class="fb-activity-meta">
                                    <?= $actor !== '' ? '@' . htmlSC($actor) . ' · ' : '' ?><?= htmlSC(date('d.m.Y H:i', $createdAt)) ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="fb-empty-state">
                    <div>
                        <span class="fb-empty-state-icon"><i class="ci-activity" aria-hidden="true"></i></span>
                        <p class="mb-0"><?= print_translation('admin_dashboard_no_activity') ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </article>

</section>

<?= view()->renderPartial('admin/shell_close') ?>
