<?php
$currentUser = get_user() ?: [];
$displayName = trim((string)($currentUser['name'] ?? ''));
if ($displayName === '') {
    $displayName = trim((string)($currentUser['login'] ?? ''));
}
$welcomeTitle = str_replace(':name', $displayName, return_translation('admin_dashboard_welcome'));
$analytics = is_array($analytics_dashboard ?? null) ? $analytics_dashboard : [];
$analyticsCards = is_array($analytics['cards'] ?? null) ? $analytics['cards'] : [];
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

$statCards = [
    [
        'label' => return_translation('admin_stat_pages'),
        'value' => (int)($stats['pages'] ?? 0),
        'icon' => 'ci-file',
        'variant' => 'is-blue',
        'href' => base_href('/admin/pages'),
        'meta' => return_translation('admin_dashboard_manage_content'),
    ],
    [
        'label' => return_translation('admin_stat_posts'),
        'value' => (int)($stats['posts'] ?? 0),
        'icon' => 'ci-file-text',
        'variant' => 'is-purple',
        'href' => base_href('/admin/posts'),
        'meta' => return_translation('admin_dashboard_manage_content'),
    ],
    [
        'label' => return_translation('admin_dashboard_active_plugins'),
        'value' => (int)($stats['active_plugins'] ?? 0),
        'icon' => 'ci-box',
        'variant' => 'is-pink',
        'href' => base_href('/admin/plugins'),
        'meta' => return_translation('admin_dashboard_extensions'),
    ],
    [
        'label' => return_translation('admin_stat_users'),
        'value' => (int)($stats['users'] ?? 0),
        'icon' => 'ci-user',
        'variant' => 'is-blue',
        'href' => base_href('/admin/users'),
        'meta' => return_translation('admin_dashboard_team'),
    ],
    [
        'label' => return_translation('admin_dashboard_system_health'),
        'value' => return_translation('admin_dashboard_system_ok'),
        'icon' => 'ci-check-circle',
        'variant' => 'is-green',
        'href' => check_creator() ? base_href('/admin/updates') : base_href('/admin/settings'),
        'meta' => return_translation('admin_dashboard_all_operational'),
    ],
];
$statCards = apply_filters('admin_dashboard_stat_cards', $statCards, $stats, $currentUser);
if (!is_array($statCards)) {
    $statCards = [];
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

$pluginWidgets = apply_filters('admin_dashboard_widgets', [], [
    'stats' => $stats,
    'user' => $currentUser,
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

<section
    class="fb-dashboard-grid"
    data-admin-analytics
    data-admin-analytics-payload="<?= htmlSC($analyticsJson ?: '{}') ?>"
    data-admin-analytics-i18n="<?= htmlSC($analyticsI18nJson ?: '{}') ?>"
>
    <article class="fb-card fb-dashboard-widget">
        <header class="fb-card-header">
            <div>
                <h2 class="fb-card-title"><?= print_translation('admin_analytics_traffic_title') ?></h2>
                <p class="fb-card-subtitle"><?= print_translation('admin_dashboard_traffic_subtitle') ?></p>
            </div>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <?= print_translation('admin_analytics_range_7') ?>
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
            <div data-analytics-chart="traffic" aria-label="<?= htmlSC(return_translation('admin_analytics_traffic_title')) ?>"></div>
        </div>
    </article>

    <article class="fb-card fb-dashboard-widget">
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
                    >
                        <span class="fb-quick-action-icon"><i class="<?= htmlSC((string)($action['icon'] ?? 'ci-arrow-right')) ?>" aria-hidden="true"></i></span>
                        <span><?= htmlSC((string)$action['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </article>

    <article class="fb-card fb-dashboard-widget">
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

    <article class="fb-card fb-dashboard-widget">
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

    <?php foreach ($pluginWidgets as $pluginWidget): ?>
        <?php
        if (!is_array($pluginWidget) || empty($pluginWidget['content'])) {
            continue;
        }
        $span = max(1, min(3, (int)($pluginWidget['span'] ?? 1)));
        ?>
        <article class="fb-card fb-dashboard-widget<?= $span > 1 ? ' fb-dashboard-span-' . $span : '' ?>">
            <?php if (!empty($pluginWidget['title'])): ?>
                <header class="fb-card-header"><h2 class="fb-card-title"><?= htmlSC((string)$pluginWidget['title']) ?></h2></header>
            <?php endif; ?>
            <div class="fb-card-body"><?= (string)$pluginWidget['content'] ?></div>
        </article>
    <?php endforeach; ?>
</section>

<?= view()->renderPartial('admin/shell_close') ?>
