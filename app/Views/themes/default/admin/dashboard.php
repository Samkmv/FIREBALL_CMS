<?php
$updateCenter = $update_center ?? [];
$updateLocal = $updateCenter['local'] ?? [];
$installedVersionLabel = (string)($updateLocal['version'] ?? ($engine_release['version'] ?? '0.0.0'));
$analytics = $analytics_dashboard ?? [];
$analyticsCards = $analytics['cards'] ?? [];
$analyticsJson = json_encode($analytics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

echo view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_dashboard_heading'),
    'subtitle' => return_translation('admin_dashboard_subtitle'),
    'actions' => '',
]);
?>

    <div class="row g-3">
        <div class="col-md-6 col-xl-3">
            <div class="border rounded-5 p-4 h-100 admin-shell-profile-card">
                <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2"><i class="ci-file-text"></i><?= print_translation('admin_stat_posts') ?></div>
                <div class="display-6 mb-0"><?= (int)$stats['posts'] ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="border rounded-5 p-4 h-100 admin-shell-profile-card">
                <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2"><i class="ci-mail"></i><?= print_translation('admin_stat_contacts') ?></div>
                <div class="display-6 mb-0"><?= (int)($stats['contact_requests'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="border rounded-5 p-4 h-100 admin-shell-profile-card">
                <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2"><i class="ci-folder"></i><?= print_translation('admin_stat_categories') ?></div>
                <div class="display-6 mb-0"><?= (int)$stats['categories'] ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="border rounded-5 p-4 h-100 admin-shell-profile-card">
                <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2"><i class="ci-user"></i><?= print_translation('admin_stat_users') ?></div>
                <div class="display-6 mb-0"><?= (int)$stats['users'] ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="border rounded-5 p-4 h-100 admin-shell-profile-card">
                <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2"><i class="ci-activity"></i><?= print_translation('admin_stat_visits') ?></div>
                <div class="display-6 mb-0"><?= (int)($stats['site_visits'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="border rounded-5 p-4 h-100 admin-shell-profile-card">
                <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2"><i class="ci-refresh-cw"></i><?= print_translation('admin_update_current_version') ?></div>
                <div class="display-6 mb-0"><?= htmlSC($installedVersionLabel) ?></div>
            </div>
        </div>
    </div>

    <section class="mt-4" data-admin-analytics data-admin-analytics-payload="<?= htmlSC($analyticsJson ?: '{}') ?>">
        <div class="mb-3">
            <h2 class="h5 mb-1">Аналитика посетителей</h2>
            <p class="text-body-secondary mb-0">Статистика собирается локально в FIREBALL CMS без сторонних сервисов.</p>
        </div>

        <div class="row g-3">
            <?php
            $analyticsMetricCards = [
                ['label' => 'Посетителей сегодня', 'value' => (int)($analyticsCards['today_visits'] ?? 0), 'icon' => 'ci-eye'],
                ['label' => 'Уникальных сегодня', 'value' => (int)($analyticsCards['today_unique'] ?? 0), 'icon' => 'ci-user'],
                ['label' => 'Посетителей за 7 дней', 'value' => (int)($analyticsCards['visits_7'] ?? 0), 'icon' => 'ci-activity'],
                ['label' => 'Посетителей за 30 дней', 'value' => (int)($analyticsCards['visits_30'] ?? 0), 'icon' => 'ci-calendar'],
                ['label' => 'Мобильные устройства', 'value' => (float)($analyticsCards['mobile_percent'] ?? 0) . '%', 'icon' => 'ci-smartphone'],
                ['label' => 'Компьютеры', 'value' => (float)($analyticsCards['desktop_percent'] ?? 0) . '%', 'icon' => 'ci-monitor'],
            ];
            ?>
            <?php foreach ($analyticsMetricCards as $card): ?>
                <div class="col-md-6 col-xl-2">
                    <div class="border rounded-5 p-3 h-100 admin-shell-profile-card">
                        <div class="d-flex align-items-center gap-2 text-body-secondary fs-sm mb-2">
                            <i class="<?= htmlSC($card['icon']) ?>"></i><?= htmlSC($card['label']) ?>
                        </div>
                        <div class="h3 mb-0"><?= htmlSC((string)$card['value']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-xl-8">
                <div class="border rounded-5 p-3 p-md-4 h-100 admin-shell-profile-card">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h3 class="h5 mb-1">График посещаемости</h3>
                            <p class="text-body-secondary mb-0">Просмотры страниц за выбранный период.</p>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Период аналитики">
                            <button class="btn btn-outline-secondary active" type="button" data-analytics-range="7">7 дней</button>
                            <button class="btn btn-outline-secondary" type="button" data-analytics-range="30">30 дней</button>
                            <button class="btn btn-outline-secondary" type="button" data-analytics-range="90">90 дней</button>
                        </div>
                    </div>
                    <div style="min-height: 320px" data-analytics-chart="traffic"></div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="border rounded-5 p-3 p-md-4 h-100 admin-shell-profile-card">
                    <h3 class="h5 mb-1">Источники трафика</h3>
                    <p class="text-body-secondary mb-3">Google, Telegram, прямые заходы, Yandex и прочие.</p>
                    <div style="min-height: 320px" data-analytics-chart="sources"></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-lg-6">
                <div class="border rounded-5 p-3 p-md-4 h-100 admin-shell-profile-card">
                    <h3 class="h5 mb-3">География</h3>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr><th>Страна</th><th class="text-end">Визиты</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach (($analytics['countries'] ?? []) as $row): ?>
                                <tr>
                                    <td><?= htmlSC((string)($row['label'] ?? 'Unknown')) ?></td>
                                    <td class="text-end"><?= (int)($row['total'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($analytics['countries'])): ?>
                                <tr><td colspan="2" class="text-body-secondary">Данных пока нет.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="border rounded-5 p-3 p-md-4 h-100 admin-shell-profile-card">
                    <h3 class="h5 mb-1">Устройства и ОС</h3>
                    <p class="text-body-secondary mb-3">Android, iPhone/iOS, Windows, macOS, Linux и другие.</p>
                    <div style="min-height: 300px" data-analytics-chart="devices"></div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-xl-6">
                <div class="border rounded-5 p-3 p-md-4 h-100 admin-shell-profile-card">
                    <h3 class="h5 mb-3">Популярные страницы</h3>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr><th>Страница</th><th class="text-end">Просмотры</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach (($analytics['pages'] ?? []) as $row): ?>
                                <tr>
                                    <td class="text-break"><?= htmlSC((string)($row['label'] ?? '/')) ?></td>
                                    <td class="text-end"><?= (int)($row['total'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($analytics['pages'])): ?>
                                <tr><td colspan="2" class="text-body-secondary">Данных пока нет.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="border rounded-5 p-3 p-md-4 h-100 admin-shell-profile-card">
                    <h3 class="h5 mb-3">Последние посетители</h3>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                            <tr>
                                <th>Время</th>
                                <th>Страна</th>
                                <th>Устройство</th>
                                <th>Браузер</th>
                                <th>Источник</th>
                                <th>Страница</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach (($analytics['latest'] ?? []) as $row): ?>
                                <tr>
                                    <td class="text-nowrap"><?= htmlSC(date('d.m H:i', strtotime((string)($row['created_at'] ?? 'now')))) ?></td>
                                    <td><?= htmlSC((string)($row['country'] ?? 'Unknown')) ?></td>
                                    <td><?= htmlSC((string)($row['device_type'] ?? '')) ?> / <?= htmlSC((string)($row['os'] ?? '')) ?></td>
                                    <td><?= htmlSC((string)($row['browser'] ?? '')) ?></td>
                                    <td><?= htmlSC((string)($row['source'] ?? '')) ?></td>
                                    <td class="text-break"><?= htmlSC((string)($row['current_page'] ?? '/')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($analytics['latest'])): ?>
                                <tr><td colspan="6" class="text-body-secondary">Данных пока нет.</td></tr>
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
        <div class="col-md-6 col-xl-4">
            <a class="border rounded-5 p-4 h-100 d-flex align-items-start gap-3 text-decoration-none text-reset admin-shell-profile-card admin-shell-action-card" href="<?= base_href('/admin/updates') ?>">
                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-body-tertiary flex-shrink-0 admin-shell-action-card__icon" style="width: 3rem; height: 3rem;"><i class="ci-refresh-cw"></i></span>
                <span>
                    <span class="d-block fw-semibold mb-1"><?= print_translation('admin_nav_updates') ?></span>
                    <span class="d-block text-body-secondary small"><?= print_translation('admin_update_center_subtitle') ?></span>
                </span>
            </a>
        </div>
    </div>
    <?= view()->renderPartial('admin/shell_close') ?>
