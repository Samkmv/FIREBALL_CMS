<section class="container py-5 my-2 my-md-4 my-lg-5">
    <?php
    $updateCenter = $update_center ?? [];
    $updateLocal = $updateCenter['local'] ?? [];
    $gitTag = trim((string)($updateLocal['git_tag'] ?? ''));
    $gitDescribe = trim((string)($updateLocal['git_describe'] ?? ''));
    $shortCommit = trim((string)($updateLocal['short_commit'] ?? ''));
    $installedVersionLabel = $engine_release['version'] ?? '0.0.0';

    if ($gitTag !== '') {
        $installedVersionLabel = $gitTag;
    } elseif ($gitDescribe !== '' && preg_match('/^(.+)-\d+-g([0-9a-f]+)$/i', $gitDescribe, $matches) === 1) {
        $installedVersionLabel = $matches[1] . ' + ' . $matches[2];
    } elseif ($gitDescribe !== '') {
        $installedVersionLabel = $gitDescribe;
    } elseif ($shortCommit !== '') {
        $installedVersionLabel = $shortCommit;
    } elseif (($updateLocal['version'] ?? '') !== '') {
        $installedVersionLabel = (string)$updateLocal['version'];
    }
    ?>
    <div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= print_translation('admin_dashboard_heading') ?></h1>
            <p class="text-body-secondary mb-0"><?= print_translation('admin_dashboard_subtitle') ?></p>
        </div>
    </div>

    <?= view()->renderPartial('admin/nav') ?>

    <div class="row g-3">
        <div class="col-md-6 col-xl-3">
            <div class="border rounded-5 p-4 h-100">
                <div class="text-body-tertiary fs-sm mb-2"><?= print_translation('admin_stat_posts') ?></div>
                <div class="display-6 mb-0"><?= (int)$stats['posts'] ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="border rounded-5 p-4 h-100">
                <div class="text-body-tertiary fs-sm mb-2"><?= print_translation('admin_stat_contacts') ?></div>
                <div class="display-6 mb-0"><?= (int)($stats['contact_requests'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="border rounded-5 p-4 h-100">
                <div class="text-body-tertiary fs-sm mb-2"><?= print_translation('admin_stat_categories') ?></div>
                <div class="display-6 mb-0"><?= (int)$stats['categories'] ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="border rounded-5 p-4 h-100">
                <div class="text-body-tertiary fs-sm mb-2"><?= print_translation('admin_stat_users') ?></div>
                <div class="display-6 mb-0"><?= (int)$stats['users'] ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="border rounded-5 p-4 h-100">
                <div class="text-body-tertiary fs-sm mb-2"><?= print_translation('admin_stat_visits') ?></div>
                <div class="display-6 mb-0"><?= (int)($stats['site_visits'] ?? 0) ?></div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="border rounded-5 p-4 h-100">
                <div class="text-body-tertiary fs-sm mb-2"><?= print_translation('admin_update_current_version') ?></div>
                <div class="display-6 mb-0"><?= htmlSC($installedVersionLabel) ?></div>
            </div>
        </div>
    </div>
</section>
