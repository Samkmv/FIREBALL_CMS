<?php
$formData = session()->get('form_data') ?: [];
$updateCenter = $update_center ?? [];
$updateConfig = $updateCenter['config'] ?? [];
$updateLocal = $updateCenter['local'] ?? [];
$lastCheck = $updateCenter['last_check'] ?? null;
$updateBlockers = $updateCenter['update_blockers'] ?? [];
$updaterRepository = $formData['updater_github_repository'] ?? ($settings['updater_github_repository'] ?? ($updateConfig['repository'] ?? ''));
$updaterBranch = $formData['updater_github_branch'] ?? ($settings['updater_github_branch'] ?? ($updateConfig['branch'] ?? 'main'));
$updaterToken = $formData['updater_github_token'] ?? '';
$release = is_array($lastCheck['release'] ?? null) ? $lastCheck['release'] : [];
$localReleaseName = trim((string)($updateLocal['name'] ?? ($engine_release['name'] ?? 'FIREBALL_CMS')));
$localReleaseSummary = trim((string)($updateLocal['summary'] ?? ($engine_release['summary'] ?? '')));
$localReleaseChanges = array_values(array_filter(array_map('trim', is_array($updateLocal['changes'] ?? null) ? $updateLocal['changes'] : (is_array($engine_release['changes'] ?? null) ? $engine_release['changes'] : []))));
$remoteReleaseLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string)($release['body'] ?? '')) ?: [])));
$remoteReleaseTitle = trim((string)(($release['name'] ?? '') !== '' ? $release['name'] : ($release['tag_name'] ?? '')));
$localReleaseDescription = $localReleaseSummary !== '' ? $localReleaseSummary : return_translation('admin_update_no_summary');
$remoteReleaseDescription = trim((string)($release['excerpt'] ?? ''));
if ($remoteReleaseDescription === '' && !empty($remoteReleaseLines)) {
    $remoteReleaseDescription = implode(' ', array_slice($remoteReleaseLines, 0, 2));
}
if ($remoteReleaseDescription === '') {
    $remoteReleaseDescription = is_array($lastCheck)
        ? return_translation('admin_update_no_summary')
        : return_translation('admin_update_public_summary_empty');
}
$statusVariant = 'secondary';
$statusLabel = return_translation('admin_update_status_unknown');
$isCreator = (string)(get_user()['role'] ?? 'user') === 'creator';
$isGitRepo = !empty($updateLocal['is_git_repo']);
$gitStatusLabel = !$isGitRepo
    ? return_translation('admin_update_git_not_applicable')
    : (!empty($updateLocal['is_update_clean'])
        ? return_translation('admin_update_git_clean')
        : return_translation('admin_update_git_dirty'));
$installedVersionLabel = (string)($updateLocal['version'] ?? ($engine_release['version'] ?? '0.0.0'));
$remoteCommitLabel = trim((string)($lastCheck['remote_commit'] ?? '')) !== ''
    ? substr((string)$lastCheck['remote_commit'], 0, 7)
    : '—';
$branchStatus = (string)($lastCheck['branch_status'] ?? 'unknown');
$branchStatusLabel = match ($branchStatus) {
    'not_applicable' => return_translation('admin_update_branch_status_not_applicable'),
    'behind', 'no_local_commit' => return_translation('admin_update_branch_status_behind'),
    'ahead' => return_translation('admin_update_branch_status_ahead'),
    'diverged' => return_translation('admin_update_branch_status_diverged'),
    'identical' => return_translation('admin_update_branch_status_identical'),
    default => return_translation('admin_update_branch_status_unknown'),
};

if (is_array($lastCheck)) {
    if (($lastCheck['status'] ?? '') === 'ok' && !empty($lastCheck['update_available'])) {
        $statusVariant = 'warning';
        $statusLabel = return_translation('admin_update_status_available');
    } elseif (($lastCheck['status'] ?? '') === 'ok' && $branchStatus === 'diverged') {
        $statusVariant = 'danger';
        $statusLabel = return_translation('admin_update_status_diverged');
    } elseif (($lastCheck['status'] ?? '') === 'ok' && $branchStatus === 'ahead') {
        $statusVariant = 'info';
        $statusLabel = return_translation('admin_update_status_ahead');
    } elseif (($lastCheck['status'] ?? '') === 'ok') {
        $statusVariant = 'success';
        $statusLabel = return_translation('admin_update_status_latest');
    } elseif (($lastCheck['status'] ?? '') === 'error') {
        $statusVariant = 'danger';
        $statusLabel = return_translation('admin_update_status_error');
    }
}
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_updates_heading'),
    'subtitle' => return_translation('admin_updates_subtitle'),
    'actions' => '',
]) ?>

    <?php if ($isCreator): ?>
        <form class="border rounded-5 p-3 p-md-4 mb-4" action="<?= base_href('/admin/updates') ?>" method="post">
            <?= get_csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label"><?= print_translation('admin_settings_update_repository') ?></label>
                    <input class="form-control <?= get_validation_class('updater_github_repository') ?>" type="text" name="updater_github_repository" value="<?= htmlSC($updaterRepository) ?>" placeholder="owner/repository">
                    <div class="form-text"><?= print_translation('admin_settings_update_repository_hint') ?></div>
                    <?= get_errors('updater_github_repository') ?>
                </div>
                <div class="col-md-5">
                    <label class="form-label"><?= print_translation('admin_settings_update_branch') ?></label>
                    <input class="form-control <?= get_validation_class('updater_github_branch') ?>" type="text" name="updater_github_branch" value="<?= htmlSC($updaterBranch) ?>" placeholder="main">
                    <div class="form-text"><?= print_translation('admin_settings_update_branch_hint') ?></div>
                    <?= get_errors('updater_github_branch') ?>
                </div>
                <div class="col-12">
                    <label class="form-label"><?= print_translation('admin_settings_update_token') ?></label>
                    <input class="form-control" type="password" name="updater_github_token" value="<?= htmlSC($updaterToken) ?>" autocomplete="off" placeholder="ghp_...">
                    <div class="form-text"><?= print_translation('admin_settings_update_token_hint') ?><?php if (($settings['updater_github_token'] ?? '') !== ''): ?> <?= print_translation('admin_settings_update_token_keep_hint') ?><?php endif; ?></div>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-save"></i><?= print_translation('admin_btn_save') ?></button>
                    <div class="form-text align-self-center mb-0"><?= print_translation('admin_settings_update_save_hint') ?></div>
                </div>
            </div>
        </form>
    <?php endif; ?>

    <div id="update-center" class="border rounded-5 p-3 p-md-4">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-4">
            <div>
                <h2 class="h4 mb-1"><?= print_translation('admin_update_center_heading') ?></h2>
                <p class="text-body-secondary mb-0"><?= print_translation('admin_update_center_subtitle') ?></p>
            </div>
            <span class="badge text-bg-<?= $statusVariant ?> fs-sm"><?= htmlSC($statusLabel) ?></span>
        </div>

        <?php if (is_array($lastCheck) && ($lastCheck['message'] ?? '') !== ''): ?>
            <div class="alert alert-<?= ($lastCheck['status'] ?? '') === 'error' ? 'danger' : (($lastCheck['update_available'] ?? false) ? 'warning' : 'success') ?> mb-4">
                <?= htmlSC((string)$lastCheck['message']) ?>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="<?= $isCreator ? 'col-md-6 col-xl-3' : 'col-md-6 col-lg-4' ?>">
                <div class="border rounded-4 p-3 h-100">
                    <div class="text-body-secondary fs-sm mb-1"><?= print_translation('admin_update_current_version') ?></div>
                    <div class="fw-semibold"><?= htmlSC($installedVersionLabel !== '' ? $installedVersionLabel : '—') ?></div>
                    <?php if ($localReleaseName !== ''): ?>
                        <div class="text-body-secondary fs-sm mt-2"><?= htmlSC($localReleaseName) ?></div>
                    <?php endif; ?>
                    <?php if (($updateLocal['released_at'] ?? '') !== ''): ?>
                        <div class="text-body-secondary fs-sm mt-2"><?= print_translation('admin_update_published_at') ?>: <?= htmlSC((string)$updateLocal['released_at']) ?></div>
                    <?php endif; ?>
                    <div class="small mt-2"><?= htmlSC($localReleaseDescription) ?></div>
                </div>
            </div>
            <div class="<?= $isCreator ? 'col-md-6 col-xl-3' : 'col-md-6 col-lg-4' ?>">
                <div class="border rounded-4 p-3 h-100">
                    <div class="text-body-secondary fs-sm mb-1"><?= print_translation('admin_update_latest_version') ?></div>
                    <div class="fw-semibold"><?= htmlSC((string)($lastCheck['remote_version'] ?? '—')) ?></div>
                    <?php if ($remoteReleaseTitle !== ''): ?>
                        <div class="text-body-secondary fs-sm mt-2"><?= htmlSC($remoteReleaseTitle) ?></div>
                    <?php endif; ?>
                    <?php if ($isCreator): ?>
                        <div class="text-body-secondary fs-sm mt-2"><?= print_translation('admin_update_remote_commit') ?></div>
                        <div class="fs-sm"><?= htmlSC($remoteCommitLabel) ?></div>
                    <?php endif; ?>
                    <?php if (($release['published_at'] ?? '') !== ''): ?>
                        <div class="text-body-secondary fs-sm mt-2"><?= print_translation('admin_update_published_at') ?>: <?= htmlSC((string)$release['published_at']) ?></div>
                    <?php endif; ?>
                    <div class="small mt-2"><?= htmlSC($remoteReleaseDescription) ?></div>
                </div>
            </div>
            <div class="<?= $isCreator ? 'col-md-6 col-xl-3' : 'col-md-6 col-lg-4' ?>">
                <div class="border rounded-4 p-3 h-100">
                    <div class="text-body-secondary fs-sm mb-1"><?= print_translation('admin_update_checked_at') ?></div>
                    <div class="fw-semibold"><?= htmlSC((string)($updateConfig['last_checked_at'] ?? '—')) ?></div>
                    <div class="text-body-secondary fs-sm mt-2"><?= print_translation('admin_update_last_updated_at') ?>: <?= htmlSC((string)($updateConfig['last_updated_at'] ?? '—')) ?></div>
                </div>
            </div>
            <?php if ($isCreator): ?>
                <div class="col-md-6 col-xl-3">
                    <div class="border rounded-4 p-3 h-100">
                        <div class="text-body-secondary fs-sm mb-1"><?= print_translation('admin_update_git_status') ?></div>
                        <div class="fw-semibold"><?= htmlSC($gitStatusLabel) ?></div>
                        <div class="text-body-secondary fs-sm mt-2"><?= print_translation('admin_update_branch_sync_status') ?></div>
                        <div class="fs-sm"><?= htmlSC($branchStatusLabel) ?></div>
                        <div class="text-body-secondary fs-sm mt-2"><?= print_translation('admin_update_commit_label') ?>: <?= htmlSC((string)($updateLocal['short_commit'] ?? '—')) ?></div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <?php if ($isCreator): ?>
                <div class="col-lg-7">
                    <div class="border rounded-4 p-3 h-100 d-flex flex-column gap-4">
                        <div>
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                                <h3 class="h6 mb-0"><?= print_translation('admin_update_installed_metadata') ?></h3>
                                <span class="badge text-bg-secondary"><?= print_translation('admin_update_version_source_file') ?></span>
                            </div>
                            <div class="fw-semibold mb-1">
                                <?= htmlSC($localReleaseName !== '' ? $localReleaseName : 'FIREBALL_CMS') ?>
                                <?php if ($installedVersionLabel !== ''): ?>
                                    <span class="text-body-secondary">v<?= htmlSC($installedVersionLabel) ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="text-body-secondary mb-3"><?= htmlSC($localReleaseDescription) ?></p>
                            <div class="text-body-secondary fs-sm mb-1"><?= print_translation('admin_update_changes_label') ?></div>
                            <?php if (!empty($localReleaseChanges)): ?>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($localReleaseChanges as $change): ?>
                                        <li><?= htmlSC((string)$change) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-body-secondary mb-0"><?= print_translation('admin_update_no_changes') ?></p>
                            <?php endif; ?>
                        </div>

                        <hr class="my-0">

                        <div>
                            <h3 class="h6 mb-2"><?= print_translation('admin_update_repository_label') ?></h3>
                            <dl class="row mb-0">
                                <dt class="col-sm-4 text-body-secondary"><?= print_translation('admin_update_repository_label') ?></dt>
                                <dd class="col-sm-8"><?= htmlSC((string)($updateConfig['repository'] ?? '—')) ?></dd>
                                <dt class="col-sm-4 text-body-secondary"><?= print_translation('admin_update_branch_label') ?></dt>
                                <dd class="col-sm-8"><?= htmlSC((string)($updateConfig['branch'] ?? 'main')) ?></dd>
                                <dt class="col-sm-4 text-body-secondary"><?= print_translation('admin_update_origin_label') ?></dt>
                                <dd class="col-sm-8 text-break"><?= htmlSC((string)($updateLocal['origin_url'] ?? '—')) ?></dd>
                            </dl>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="border rounded-4 p-3 h-100 d-flex flex-column gap-3">
                        <div>
                            <h3 class="h6 mb-2"><?= print_translation('admin_update_release_notes') ?></h3>
                            <?php if (!empty($release)): ?>
                                <div class="fw-semibold mb-2"><?= htmlSC($remoteReleaseTitle) ?></div>
                                <p class="text-body-secondary mb-3"><?= htmlSC($remoteReleaseDescription) ?></p>
                                <?php if (!empty($remoteReleaseLines)): ?>
                                    <div class="text-body-secondary fs-sm mb-1"><?= print_translation('admin_update_changes_label') ?></div>
                                    <ul class="mb-3 ps-3">
                                        <?php foreach ($remoteReleaseLines as $line): ?>
                                            <li><?= htmlSC((string)$line) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <?php if (($release['html_url'] ?? '') !== ''): ?>
                                    <a class="btn btn-outline-secondary rounded-pill" href="<?= htmlSC((string)$release['html_url']) ?>" target="_blank" rel="noopener noreferrer"><?= print_translation('admin_update_release_link') ?></a>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-body-secondary mb-0"><?= print_translation('admin_update_no_release_data') ?></p>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($updateBlockers)): ?>
                            <div class="alert alert-warning mb-0">
                                <div class="fw-semibold mb-2"><?= print_translation('admin_update_blockers') ?></div>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($updateBlockers as $blocker): ?>
                                        <li><?= htmlSC((string)$blocker) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form action="<?= base_href('/admin/settings/update-center/check') ?>" method="post">
                            <?= get_csrf_field() ?>
                            <button class="btn btn-outline-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                                <i class="ci-refresh"></i><?= print_translation('admin_update_check_btn') ?>
                            </button>
                        </form>

                        <form action="<?= base_href('/admin/settings/update-center/update') ?>" method="post">
                            <?= get_csrf_field() ?>
                            <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit" <?= !empty($updateBlockers) ? 'disabled' : '' ?>>
                                <i class="ci-download"></i><?= print_translation('admin_update_run_btn') ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="col-lg-7">
                    <div class="border rounded-4 p-4 h-100 d-flex flex-column gap-3 justify-content-between">
                        <div>
                            <div class="text-body-secondary fs-sm mb-2"><?= print_translation('admin_update_local_overview') ?></div>
                            <h3 class="h5 mb-2">
                                <?= htmlSC($localReleaseName !== '' ? $localReleaseName : 'FIREBALL_CMS') ?>
                                <?php if ($installedVersionLabel !== ''): ?>
                                    <span class="text-body-secondary">v<?= htmlSC($installedVersionLabel) ?></span>
                                <?php endif; ?>
                            </h3>
                            <p class="text-body-secondary mb-0"><?= htmlSC($localReleaseDescription) ?></p>
                        </div>
                        <div class="small text-body-secondary"><?= print_translation('admin_update_admin_simple_note') ?></div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="border rounded-4 p-4 h-100 d-flex flex-column gap-3">
                        <div>
                            <div class="text-body-secondary fs-sm mb-2"><?= print_translation('admin_update_public_summary') ?></div>
                            <?php if (($lastCheck['remote_version'] ?? '') !== '' || $remoteReleaseTitle !== ''): ?>
                                <h3 class="h5 mb-2">
                                    <?= htmlSC((string)(($lastCheck['remote_version'] ?? '') !== '' ? $lastCheck['remote_version'] : $remoteReleaseTitle)) ?>
                                </h3>
                            <?php endif; ?>
                            <p class="text-body-secondary mb-0"><?= htmlSC($remoteReleaseDescription) ?></p>
                        </div>

                        <?php if (!empty($updateBlockers)): ?>
                            <div class="alert alert-warning mb-0">
                                <div class="fw-semibold mb-2"><?= print_translation('admin_update_blockers') ?></div>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($updateBlockers as $blocker): ?>
                                        <li><?= htmlSC((string)$blocker) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form action="<?= base_href('/admin/settings/update-center/check') ?>" method="post">
                            <?= get_csrf_field() ?>
                            <button class="btn btn-outline-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit">
                                <i class="ci-refresh"></i><?= print_translation('admin_update_check_btn') ?>
                            </button>
                        </form>

                        <form action="<?= base_href('/admin/settings/update-center/update') ?>" method="post">
                            <?= get_csrf_field() ?>
                            <button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit" <?= !empty($updateBlockers) ? 'disabled' : '' ?>>
                                <i class="ci-download"></i><?= print_translation('admin_update_run_btn') ?>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?= view()->renderPartial('admin/shell_close') ?>
