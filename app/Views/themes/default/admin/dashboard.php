<?php
$updateCenter = $update_center ?? [];
$updateLocal = $updateCenter['local'] ?? [];
$installedVersionLabel = (string)($updateLocal['version'] ?? ($engine_release['version'] ?? '0.0.0'));
$analytics = $analytics_dashboard ?? [];
$analyticsCards = $analytics['cards'] ?? [];
$unknownCountryLabel = return_translation('admin_analytics_country_unknown');
$analyticsJson = json_encode($analytics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$analyticsI18nJson = json_encode([
    'visits' => return_translation('admin_analytics_chart_visits'),
    'sources' => return_translation('admin_analytics_chart_sources'),
    'devices' => return_translation('admin_analytics_chart_devices'),
    'unavailable' => return_translation('admin_analytics_chart_unavailable'),
    'unknown' => return_translation('admin_analytics_country_unknown'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

echo view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_dashboard_heading'),
    'subtitle' => return_translation('admin_dashboard_subtitle'),
    'actions' => '',
]);
?>

    <?php
    $overviewCards = [
        ['label' => return_translation('admin_stat_posts'), 'value' => (int)($stats['posts'] ?? 0), 'icon' => 'ci-file-text'],
        ['label' => return_translation('admin_stat_pages'), 'value' => (int)($stats['pages'] ?? 0), 'icon' => 'ci-file'],
        ['label' => return_translation('admin_stat_contacts'), 'value' => (int)($stats['contact_requests'] ?? 0), 'icon' => 'ci-mail'],
        ['label' => return_translation('admin_stat_new_contacts'), 'value' => (int)($stats['contact_requests_new'] ?? 0), 'icon' => 'ci-inbox'],
        ['label' => return_translation('admin_stat_categories'), 'value' => (int)($stats['categories'] ?? 0), 'icon' => 'ci-folder'],
        ['label' => return_translation('admin_stat_users'), 'value' => (int)($stats['users'] ?? 0), 'icon' => 'ci-user'],
        ['label' => return_translation('admin_stat_visits'), 'value' => (int)($stats['site_visits'] ?? 0), 'icon' => 'ci-activity'],
        ['label' => return_translation('admin_stat_support_faq'), 'value' => (int)($stats['support_faq'] ?? 0), 'icon' => 'ci-message-square'],
        ['label' => return_translation('admin_stat_support_kb_articles'), 'value' => (int)($stats['support_kb_articles'] ?? 0), 'icon' => 'ci-book-open'],
        ['label' => return_translation('admin_stat_support_kb_categories'), 'value' => (int)($stats['support_kb_categories'] ?? 0), 'icon' => 'ci-folder'],
        ['label' => return_translation('admin_update_current_version'), 'value' => $installedVersionLabel, 'icon' => 'ci-refresh-cw'],
    ];
    ?>
    <div class="row g-3">
        <?php foreach ($overviewCards as $card): ?>
            <div class="col-md-6 col-xl-3">
                <div class="border rounded-5 p-4 h-100 admin-shell-profile-card">
                    <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2">
                        <i class="<?= htmlSC($card['icon']) ?>"></i><?= htmlSC((string)$card['label']) ?>
                    </div>
                    <div class="display-6 mb-0"><?= htmlSC((string)$card['value']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <section class="mt-4" data-admin-analytics data-admin-analytics-payload="<?= htmlSC($analyticsJson ?: '{}') ?>" data-admin-analytics-i18n="<?= htmlSC($analyticsI18nJson ?: '{}') ?>">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h2 class="h5 mb-1"><?= print_translation('admin_analytics_title') ?></h2>
                <p class="text-body-secondary mb-0"><?= print_translation('admin_analytics_subtitle') ?></p>
            </div>
            <a class="btn btn-sm btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin/analytics') ?>">
                <?= print_translation('admin_analytics_view_all') ?><i class="ci-arrow-right"></i>
            </a>
        </div>

        <div class="row g-3">
            <?php
            $analyticsMetricCards = [
                ['label' => return_translation('admin_analytics_today_visits'), 'value' => (int)($analyticsCards['today_visits'] ?? 0), 'icon' => 'ci-eye'],
                ['label' => return_translation('admin_analytics_today_unique'), 'value' => (int)($analyticsCards['today_unique'] ?? 0), 'icon' => 'ci-user'],
                ['label' => return_translation('admin_analytics_visits_7'), 'value' => (int)($analyticsCards['visits_7'] ?? 0), 'icon' => 'ci-activity'],
                ['label' => return_translation('admin_analytics_visits_30'), 'value' => (int)($analyticsCards['visits_30'] ?? 0), 'icon' => 'ci-calendar'],
                ['label' => return_translation('admin_analytics_mobile_percent'), 'value' => (float)($analyticsCards['mobile_percent'] ?? 0) . '%', 'icon' => 'ci-smartphone'],
                ['label' => return_translation('admin_analytics_desktop_percent'), 'value' => (float)($analyticsCards['desktop_percent'] ?? 0) . '%', 'icon' => 'ci-monitor'],
            ];
            ?>
            <?php foreach ($analyticsMetricCards as $card): ?>
                <div class="col-md-6 col-xl-2">
                    <div class="border rounded-5 p-3 h-100 admin-shell-profile-card admin-analytics-card d-flex flex-column">
                        <div class="d-flex align-items-start gap-2 text-body-secondary fs-sm mb-3 admin-analytics-card__label">
                            <i class="<?= htmlSC($card['icon']) ?>"></i><?= htmlSC($card['label']) ?>
                        </div>
                        <div class="h3 mb-0 lh-1 admin-analytics-card__value"><?= htmlSC((string)$card['value']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-xl-8">
                <div class="border rounded-5 p-3 p-md-4 h-100 admin-shell-profile-card admin-table-card">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h3 class="h5 mb-1"><?= print_translation('admin_analytics_traffic_title') ?></h3>
                            <p class="text-body-secondary mb-0"><?= print_translation('admin_analytics_traffic_subtitle') ?></p>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="<?= htmlSC(return_translation('admin_analytics_range_label')) ?>">
                            <button class="btn btn-outline-secondary active" type="button" data-analytics-range="7"><?= print_translation('admin_analytics_range_7') ?></button>
                            <button class="btn btn-outline-secondary" type="button" data-analytics-range="30"><?= print_translation('admin_analytics_range_30') ?></button>
                            <button class="btn btn-outline-secondary" type="button" data-analytics-range="90"><?= print_translation('admin_analytics_range_90') ?></button>
                        </div>
                    </div>
                    <div style="min-height: 320px" data-analytics-chart="traffic"></div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="border rounded-5 p-3 p-md-4 h-100 admin-shell-profile-card admin-table-card">
                    <h3 class="h5 mb-1"><?= print_translation('admin_analytics_sources_title') ?></h3>
                    <p class="text-body-secondary mb-3"><?= print_translation('admin_analytics_sources_subtitle') ?></p>
                    <div style="min-height: 320px" data-analytics-chart="sources"></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-lg-6">
                <div class="border rounded-5 p-3 p-md-4 h-100 admin-shell-profile-card admin-table-card" data-admin-table>
                    <h3 class="h5 mb-3"><?= print_translation('admin_analytics_geo_title') ?></h3>
                    <div class="table-responsive overflow-auto admin-table-scroll">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr><th><?= print_translation('admin_analytics_col_country') ?></th><th class="text-end"><?= print_translation('admin_analytics_col_visits') ?></th></tr>
                            </thead>
                            <tbody>
                            <?php foreach (($analytics['countries'] ?? []) as $row): ?>
                                <tr>
                                    <td><?= htmlSC((string)($row['label'] ?? $unknownCountryLabel)) ?></td>
                                    <td class="text-end"><?= (int)($row['total'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($analytics['countries'])): ?>
                                <tr><td colspan="2" class="text-center text-body-secondary py-5"><?= print_translation('admin_table_empty') ?></td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-body-tertiary mt-3">
                        <a class="text-body-tertiary" href="https://db-ip.com" target="_blank" rel="noopener noreferrer">IP Geolocation by DB-IP</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="border rounded-5 p-3 p-md-4 h-100 admin-shell-profile-card admin-table-card">
                    <h3 class="h5 mb-1"><?= print_translation('admin_analytics_devices_title') ?></h3>
                    <p class="text-body-secondary mb-3"><?= print_translation('admin_analytics_devices_subtitle') ?></p>
                    <div style="min-height: 300px" data-analytics-chart="devices"></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-xl-6">
                <div class="border rounded-5 p-3 p-md-4 h-100 admin-shell-profile-card admin-table-card" data-admin-table>
                    <h3 class="h5 mb-3"><?= print_translation('admin_analytics_pages_title') ?></h3>
                    <div class="analytics-scroll-container table-responsive overflow-auto admin-table-scroll">
                        <table class="table align-middle mb-0 admin-analytics-table admin-analytics-table--pages">
                            <thead>
                            <tr><th><?= print_translation('admin_analytics_col_page') ?></th><th class="text-end"><?= print_translation('admin_analytics_col_views') ?></th></tr>
                            </thead>
                            <tbody>
                            <?php foreach (($analytics['pages'] ?? []) as $row): ?>
                                <tr>
                                    <td class="text-break"><?= htmlSC((string)($row['label'] ?? '/')) ?></td>
                                    <td class="text-end"><?= (int)($row['total'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($analytics['pages'])): ?>
                                <tr><td colspan="2" class="text-center text-body-secondary py-5"><?= print_translation('admin_table_empty') ?></td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="border rounded-5 p-3 p-md-4 h-100 admin-shell-profile-card admin-table-card" data-admin-table>
                    <h3 class="h5 mb-3"><?= print_translation('admin_analytics_latest_title') ?></h3>
                    <div class="analytics-scroll-container table-responsive overflow-auto admin-table-scroll">
                        <table class="table align-middle mb-0 admin-analytics-table admin-analytics-table--latest">
                            <thead>
                            <tr>
                                <th><?= print_translation('admin_analytics_col_time') ?></th>
                                <th><?= print_translation('admin_analytics_col_country') ?></th>
                                <th><?= print_translation('admin_analytics_col_device') ?></th>
                                <th><?= print_translation('admin_analytics_col_browser') ?></th>
                                <th><?= print_translation('admin_analytics_col_source') ?></th>
                                <th><?= print_translation('admin_analytics_col_page') ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach (($analytics['latest'] ?? []) as $row): ?>
                                <tr>
                                    <td class="text-nowrap"><?= htmlSC(date('d.m H:i', strtotime((string)($row['created_at'] ?? 'now')))) ?></td>
                                    <td><?= htmlSC((string)($row['country'] ?? $unknownCountryLabel)) ?></td>
                                    <td><?= htmlSC((string)($row['device_type'] ?? '')) ?> / <?= htmlSC((string)($row['os'] ?? '')) ?></td>
                                    <td><?= htmlSC((string)($row['browser'] ?? '')) ?></td>
                                    <td><?= htmlSC((string)($row['source'] ?? '')) ?></td>
                                    <td class="text-break"><?= htmlSC((string)($row['current_page'] ?? '/')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($analytics['latest'])): ?>
                                <tr><td colspan="6" class="text-center text-body-secondary py-5"><?= print_translation('admin_table_empty') ?></td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-3 mt-1">
        <div class="col-md-6 col-xl-4">
            <a class="border rounded-5 p-4 h-100 d-flex align-items-start gap-3 text-decoration-none text-reset admin-shell-profile-card admin-shell-action-card" href="<?= base_href('/admin/posts/create') ?>">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-dark text-white flex-shrink-0 admin-shell-action-card__icon" style="width: 3rem; height: 3rem;"><i class="ci-plus"></i></span>
                <span>
                    <span class="d-block fw-semibold mb-1"><?= print_translation('admin_posts_create') ?></span>
                    <span class="d-block text-body-secondary small"><?= print_translation('admin_posts_subtitle') ?></span>
                </span>
            </a>
        </div>
        <div class="col-md-6 col-xl-4">
            <a class="border rounded-5 p-4 h-100 d-flex align-items-start gap-3 text-decoration-none text-reset admin-shell-profile-card admin-shell-action-card" href="<?= base_href('/admin/settings') ?>">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-body-tertiary flex-shrink-0 admin-shell-action-card__icon" style="width: 3rem; height: 3rem;"><i class="ci-settings"></i></span>
                <span>
                    <span class="d-block fw-semibold mb-1"><?= print_translation('admin_nav_settings') ?></span>
                    <span class="d-block text-body-secondary small"><?= print_translation('admin_settings_subtitle') ?></span>
                </span>
            </a>
        </div>
        <?php if (check_creator()): ?>
        <div class="col-md-6 col-xl-4">
            <a class="border rounded-5 p-4 h-100 d-flex align-items-start gap-3 text-decoration-none text-reset admin-shell-profile-card admin-shell-action-card" href="<?= base_href('/admin/updates') ?>">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-body-tertiary flex-shrink-0 admin-shell-action-card__icon" style="width: 3rem; height: 3rem;"><i class="ci-refresh-cw"></i></span>
                <span>
                    <span class="d-block fw-semibold mb-1"><?= print_translation('admin_nav_updates') ?></span>
                    <span class="d-block text-body-secondary small"><?= print_translation('admin_update_center_subtitle') ?></span>
                </span>
            </a>
        </div>
        <?php endif; ?>
    </div>
    <?= view()->renderPartial('admin/shell_close') ?>
