<?php
$analytics = (array)($analytics ?? []);
$filters = (array)($analytics['filters'] ?? []);
$filterOptions = (array)($analytics['filter_options'] ?? []);
$pages = (array)($analytics['pages'] ?? []);
$visits = (array)($analytics['visits'] ?? []);
$query = $_GET ?? [];
$canResetAnalytics = !empty($can_reset_analytics);
$geoIpStatus = (array)($geoip_status ?? []);
$unknownCountryLabel = return_translation('admin_analytics_country_unknown');

$urlFor = static function (array $overrides = []) use ($query): string {
    $params = array_merge($query, $overrides);
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        }
    }

    return base_href('/admin/analytics') . (!empty($params) ? '?' . http_build_query($params) : '');
};

$sortIndicator = static function (string $currentSort, string $currentDirection, string $column): string {
    if ($currentSort !== $column) {
        return '';
    }

    return strtolower($currentDirection) === 'asc' ? ' ↑' : ' ↓';
};

$pagesSortUrl = static function (string $column) use ($pages, $urlFor): string {
    $currentSort = (string)($pages['sort'] ?? 'views');
    $currentDirection = (string)($pages['direction'] ?? 'desc');
    $nextDirection = $currentSort === $column && $currentDirection === 'asc' ? 'desc' : 'asc';

    return $urlFor([
        'pages_sort' => $column,
        'pages_direction' => $nextDirection,
        'pages_page' => 1,
    ]);
};

$visitsSortUrl = static function (string $column) use ($visits, $urlFor): string {
    $currentSort = (string)($visits['sort'] ?? 'created_at');
    $currentDirection = (string)($visits['direction'] ?? 'desc');
    $nextDirection = $currentSort === $column && $currentDirection === 'asc' ? 'desc' : 'asc';

    return $urlFor([
        'visits_sort' => $column,
        'visits_direction' => $nextDirection,
        'visits_page' => 1,
    ]);
};
?>
<?php ob_start(); ?>
<a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin') ?>">
    <i class="ci-arrow-left"></i><?= print_translation('admin_analytics_back_to_dashboard') ?>
</a>
<form method="post" action="<?= base_href('/admin/analytics/refresh') ?>">
    <?= get_csrf_field() ?>
    <button class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="submit">
        <i class="ci-refresh-cw"></i><?= print_translation('admin_analytics_refresh_button') ?>
    </button>
</form>
<?php if ($canResetAnalytics): ?>
    <form
        method="post"
        action="<?= base_href('/admin/analytics/reset') ?>"
        data-admin-delete-form
        data-delete-message="<?= htmlSC(return_translation('admin_analytics_reset_confirm')) ?>"
        data-delete-confirm-label="<?= htmlSC(return_translation('admin_analytics_reset_confirm_button')) ?>"
    >
        <?= get_csrf_field() ?>
        <button class="btn btn-outline-danger rounded-pill d-inline-flex align-items-center gap-2" type="submit">
            <i class="ci-trash"></i><?= print_translation('admin_analytics_reset_button') ?>
        </button>
    </form>
<?php endif; ?>
<?php $adminPageActions = ob_get_clean(); ?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_analytics_full_heading'),
    'subtitle' => return_translation('admin_analytics_full_subtitle'),
    'actions' => $adminPageActions,
]) ?>

    <div class="alert <?= !empty($geoIpStatus['connected']) ? 'alert-success' : 'alert-warning' ?> rounded-4 mb-3" role="status">
        <?= print_translation(!empty($geoIpStatus['connected']) ? 'admin_geoip_connected' : 'admin_geoip_missing') ?>
        <?php if (!empty($geoIpStatus['connected'])): ?>
            <span class="ms-2">
                <a class="alert-link" href="https://db-ip.com" target="_blank" rel="noopener noreferrer">IP Geolocation by DB-IP</a>
            </span>
        <?php endif; ?>
    </div>

    <form class="border rounded-5 p-3 p-md-4 mb-3" method="get" data-admin-table-form>
        <input type="hidden" name="pages_sort" value="<?= htmlSC((string)($pages['sort'] ?? 'views')) ?>">
        <input type="hidden" name="pages_direction" value="<?= htmlSC((string)($pages['direction'] ?? 'desc')) ?>">
        <input type="hidden" name="visits_sort" value="<?= htmlSC((string)($visits['sort'] ?? 'created_at')) ?>">
        <input type="hidden" name="visits_direction" value="<?= htmlSC((string)($visits['direction'] ?? 'desc')) ?>">
        <input type="hidden" name="pages_page" value="1">
        <input type="hidden" name="visits_page" value="1">

        <div class="row g-3 align-items-end">
            <div class="col-md-6 col-xl-3">
                <label class="form-label" for="analytics-search"><?= print_translation('admin_analytics_filter_search') ?></label>
                <input id="analytics-search" class="form-control" type="search" name="search" value="<?= htmlSC((string)($filters['search'] ?? '')) ?>" placeholder="<?= print_translation('admin_table_search_placeholder') ?>" autocomplete="off" data-admin-table-search>
            </div>
            <div class="col-sm-6 col-xl-2">
                <label class="form-label" for="analytics-period"><?= print_translation('admin_analytics_filter_period') ?></label>
                <select id="analytics-period" class="form-select" name="period">
                    <?php foreach (['7' => 'admin_analytics_range_7', '30' => 'admin_analytics_range_30', '90' => 'admin_analytics_range_90', 'all' => 'admin_analytics_range_all'] as $value => $labelKey): ?>
                        <option value="<?= htmlSC($value) ?>" <?= (string)($filters['period'] ?? '30') === $value ? 'selected' : '' ?>><?= print_translation($labelKey) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-xl-2">
                <label class="form-label" for="analytics-country"><?= print_translation('admin_analytics_col_country') ?></label>
                <select id="analytics-country" class="form-select" name="country">
                    <option value=""><?= print_translation('admin_analytics_filter_all') ?></option>
                    <?php foreach (($filterOptions['countries'] ?? []) as $option): ?>
                        <?php $value = (string)($option['value'] ?? ''); ?>
                        <option value="<?= htmlSC($value) ?>" <?= (string)($filters['country'] ?? '') === $value ? 'selected' : '' ?>><?= htmlSC($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-xl-2">
                <label class="form-label" for="analytics-device"><?= print_translation('admin_analytics_col_device') ?></label>
                <select id="analytics-device" class="form-select" name="device_type">
                    <option value=""><?= print_translation('admin_analytics_filter_all') ?></option>
                    <?php foreach (($filterOptions['devices'] ?? []) as $option): ?>
                        <?php $value = (string)($option['value'] ?? ''); ?>
                        <option value="<?= htmlSC($value) ?>" <?= (string)($filters['device_type'] ?? '') === $value ? 'selected' : '' ?>><?= htmlSC($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-xl-2">
                <label class="form-label" for="analytics-browser"><?= print_translation('admin_analytics_col_browser') ?></label>
                <select id="analytics-browser" class="form-select" name="browser">
                    <option value=""><?= print_translation('admin_analytics_filter_all') ?></option>
                    <?php foreach (($filterOptions['browsers'] ?? []) as $option): ?>
                        <?php $value = (string)($option['value'] ?? ''); ?>
                        <option value="<?= htmlSC($value) ?>" <?= (string)($filters['browser'] ?? '') === $value ? 'selected' : '' ?>><?= htmlSC($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-xl-2">
                <label class="form-label" for="analytics-source"><?= print_translation('admin_analytics_col_source') ?></label>
                <select id="analytics-source" class="form-select" name="source">
                    <option value=""><?= print_translation('admin_analytics_filter_all') ?></option>
                    <?php foreach (($filterOptions['sources'] ?? []) as $option): ?>
                        <?php $value = (string)($option['value'] ?? ''); ?>
                        <option value="<?= htmlSC($value) ?>" <?= (string)($filters['source'] ?? '') === $value ? 'selected' : '' ?>><?= htmlSC($value) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button class="btn btn-dark rounded-pill" type="submit"><?= print_translation('admin_analytics_filter_apply') ?></button>
                <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/admin/analytics') ?>"><?= print_translation('admin_analytics_filter_reset') ?></a>
            </div>
        </div>
    </form>

    <div class="row g-3">
        <div class="col-12">
            <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table data-ajax-table="analytics-pages">
                <h2 class="h5 mb-3"><?= print_translation('admin_analytics_pages_title') ?></h2>
                <div class="table-responsive overflow-auto admin-table-scroll">
                    <table class="table align-middle mb-0 admin-analytics-table admin-analytics-table--pages-full">
                        <thead class="position-sticky top-0">
                        <tr>
                            <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= $pagesSortUrl('page') ?>"><?= print_translation('admin_analytics_col_page') ?><?= $sortIndicator((string)($pages['sort'] ?? 'views'), (string)($pages['direction'] ?? 'desc'), 'page') ?></a></th>
                            <th scope="col" class="text-end"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= $pagesSortUrl('views') ?>"><?= print_translation('admin_analytics_col_views') ?><?= $sortIndicator((string)($pages['sort'] ?? 'views'), (string)($pages['direction'] ?? 'desc'), 'views') ?></a></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach (($pages['items'] ?? []) as $row): ?>
                            <tr>
                                <td class="text-break"><?= htmlSC((string)($row['label'] ?? '/')) ?></td>
                                <td class="text-end"><?= (int)($row['views'] ?? $row['total'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($pages['items'])): ?>
                            <tr><td colspan="2" class="text-center text-body-secondary py-5"><?= print_translation('admin_table_empty') ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?= view()->renderPartial('admin/partials/table_footer', [
                    'visible' => count((array)($pages['items'] ?? [])),
                    'total' => (int)($pages['total'] ?? 0),
                    'pagination' => $pages['pagination'] ?? null,
                    'show_results_label' => false,
                ]) ?>
            </div>
        </div>

        <div class="col-12">
            <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table data-ajax-table="analytics-visits">
                <h2 class="h5 mb-3"><?= print_translation('admin_analytics_latest_title') ?></h2>
                <div class="table-responsive overflow-auto admin-table-scroll">
                    <table class="table align-middle mb-0 admin-analytics-table admin-analytics-table--visits-full">
                        <thead class="position-sticky top-0">
                        <tr>
                            <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= $visitsSortUrl('created_at') ?>"><?= print_translation('admin_analytics_col_time') ?><?= $sortIndicator((string)($visits['sort'] ?? 'created_at'), (string)($visits['direction'] ?? 'desc'), 'created_at') ?></a></th>
                            <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= $visitsSortUrl('country') ?>"><?= print_translation('admin_analytics_col_country') ?><?= $sortIndicator((string)($visits['sort'] ?? 'created_at'), (string)($visits['direction'] ?? 'desc'), 'country') ?></a></th>
                            <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= $visitsSortUrl('device') ?>"><?= print_translation('admin_analytics_col_device') ?><?= $sortIndicator((string)($visits['sort'] ?? 'created_at'), (string)($visits['direction'] ?? 'desc'), 'device') ?></a></th>
                            <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= $visitsSortUrl('browser') ?>"><?= print_translation('admin_analytics_col_browser') ?><?= $sortIndicator((string)($visits['sort'] ?? 'created_at'), (string)($visits['direction'] ?? 'desc'), 'browser') ?></a></th>
                            <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= $visitsSortUrl('source') ?>"><?= print_translation('admin_analytics_col_source') ?><?= $sortIndicator((string)($visits['sort'] ?? 'created_at'), (string)($visits['direction'] ?? 'desc'), 'source') ?></a></th>
                            <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= $visitsSortUrl('page') ?>"><?= print_translation('admin_analytics_col_page') ?><?= $sortIndicator((string)($visits['sort'] ?? 'created_at'), (string)($visits['direction'] ?? 'desc'), 'page') ?></a></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach (($visits['items'] ?? []) as $row): ?>
                            <tr>
                                <td class="text-nowrap"><?= htmlSC(date('d.m.Y H:i', strtotime((string)($row['created_at'] ?? 'now')))) ?></td>
                                <td><?= htmlSC((string)($row['country'] ?? $unknownCountryLabel)) ?></td>
                                <td><?= htmlSC((string)($row['device_type'] ?? '')) ?> / <?= htmlSC((string)($row['os'] ?? '')) ?></td>
                                <td><?= htmlSC((string)($row['browser'] ?? '')) ?></td>
                                <td><?= htmlSC((string)($row['source'] ?? '')) ?></td>
                                <td class="text-break"><?= htmlSC((string)($row['current_page'] ?? '/')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($visits['items'])): ?>
                            <tr><td colspan="6" class="text-center text-body-secondary py-5"><?= print_translation('admin_table_empty') ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?= view()->renderPartial('admin/partials/table_footer', [
                    'visible' => count((array)($visits['items'] ?? [])),
                    'total' => (int)($visits['total'] ?? 0),
                    'pagination' => $visits['pagination'] ?? null,
                    'show_results_label' => false,
                ]) ?>
            </div>
        </div>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
