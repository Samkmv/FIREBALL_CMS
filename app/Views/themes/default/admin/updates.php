<?php
$formData = session()->get('form_data') ?: [];
$updateCenter = $update_center ?? [];
$updateConfig = $updateCenter['config'] ?? [];
$updateLocal = $updateCenter['local'] ?? [];
$lastCheck = $updateCenter['last_check'] ?? null;
$updateBlockers = $updateCenter['update_blockers'] ?? [];
$shouldScrollToUpdateCenter = (string)request()->get('scroll', '') === 'update-center';
$updaterRepository = $formData['updater_github_repository'] ?? ($settings['updater_github_repository'] ?? ($updateConfig['repository'] ?? ''));
$updaterBranch = $formData['updater_github_branch'] ?? ($settings['updater_github_branch'] ?? ($updateConfig['branch'] ?? 'main'));
$updaterToken = $formData['updater_github_token'] ?? '';
$updaterChannel = $formData['update_channel'] ?? ($settings['update_channel'] ?? ($updateConfig['channel'] ?? 'stable'));
$updaterChannel = $updaterChannel === 'dev' ? 'dev' : 'stable';
$updaterChannelLabel = $updaterChannel === 'dev'
    ? return_translation('admin_update_channel_dev')
    : return_translation('admin_update_channel_stable');
$isGitRepo = !empty($updateLocal['is_git_repo']);
$updateSourceLabel = $updaterChannel === 'dev'
    ? return_translation('admin_update_source_main_branch')
    : return_translation($isGitRepo
        ? 'admin_update_source_github_release_git'
        : 'admin_update_source_github_release_archive');
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
$canRollback = $isGitRepo
    && !empty($updateLocal['git_available'])
    && trim((string)($updateConfig['rollback_commit'] ?? '')) !== '';
$gitStatusLabel = !$isGitRepo
    ? return_translation('admin_update_git_not_applicable')
    : (!empty($updateLocal['is_update_clean'])
        ? return_translation('admin_update_git_clean')
        : return_translation('admin_update_git_dirty'));
$installedVersionLabel = (string)($updateLocal['version'] ?? ($engine_release['version'] ?? '0.0.0'));
$remoteVersionLabel = trim((string)($lastCheck['remote_version'] ?? ''));
$checkedAtLabel = trim((string)($updateConfig['last_checked_at'] ?? ''));
$lastUpdatedAtLabel = trim((string)($updateConfig['last_updated_at'] ?? ''));
$lastCheckMessage = trim((string)($lastCheck['message'] ?? ''));
$updateAvailable = is_array($lastCheck)
    && ($lastCheck['status'] ?? '') === 'ok'
    && !empty($lastCheck['update_available']);
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

$statusIcon = match ($statusVariant) {
    'warning' => 'ci-download',
    'success' => 'ci-check-circle',
    'danger' => 'ci-alert-triangle',
    'info' => 'ci-info',
    default => 'ci-refresh',
};
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_updates_heading'),
    'subtitle' => return_translation('admin_updates_subtitle'),
    'actions' => '',
]) ?>

    <div id="update-center" class="admin-update-page">
        <section class="admin-update-overview">
            <div class="admin-update-overview__header">
                <div class="d-flex align-items-center gap-3 min-w-0">
                    <span class="admin-update-overview__icon" aria-hidden="true">
                        <i class="<?= htmlSC($statusIcon) ?>"></i>
                    </span>
                    <div class="min-w-0">
                        <h2 class="h4 mb-1"><?= print_translation('admin_update_center_heading') ?></h2>
                        <p class="text-body-secondary mb-0"><?= print_translation('admin_update_center_subtitle') ?></p>
                    </div>
                </div>
                <span class="admin-update-status admin-update-status--<?= htmlSC($statusVariant) ?>">
                    <?= htmlSC($statusLabel) ?>
                </span>
            </div>

            <div class="admin-update-version-flow">
                <div class="admin-update-version">
                    <span class="admin-update-version__label"><?= print_translation('admin_update_current_version') ?></span>
                    <strong class="admin-update-version__number">v<?= htmlSC($installedVersionLabel !== '' ? $installedVersionLabel : '—') ?></strong>
                    <span class="admin-update-version__meta"><?= htmlSC($localReleaseName !== '' ? $localReleaseName : 'FIREBALL_CMS') ?></span>
                </div>
                <span class="admin-update-version-flow__arrow" aria-hidden="true">
                    <i class="ci-arrow-right"></i>
                </span>
                <div class="admin-update-version admin-update-version--remote">
                    <span class="admin-update-version__label"><?= print_translation('admin_update_latest_version') ?></span>
                    <strong class="admin-update-version__number"><?= $remoteVersionLabel !== '' ? 'v' . htmlSC($remoteVersionLabel) : '—' ?></strong>
                    <span class="admin-update-version__meta">
                        <?= htmlSC($remoteReleaseTitle !== '' ? $remoteReleaseTitle : $updaterChannelLabel) ?>
                    </span>
                </div>
            </div>

            <div class="admin-update-result admin-update-result--<?= htmlSC($statusVariant) ?>">
                <span class="admin-update-result__icon" aria-hidden="true">
                    <i class="<?= htmlSC($statusIcon) ?>"></i>
                </span>
                <div class="min-w-0">
                    <div class="fw-semibold"><?= htmlSC($statusLabel) ?></div>
                    <p class="mb-0 mt-1">
                        <?= htmlSC($lastCheckMessage !== '' ? $lastCheckMessage : $remoteReleaseDescription) ?>
                    </p>
                </div>
            </div>

            <?php if ($updaterChannel === 'dev'): ?>
                <div class="admin-update-notice admin-update-notice--warning">
                    <i class="ci-alert-triangle" aria-hidden="true"></i>
                    <div>
                        <strong><?= print_translation('admin_update_dev_warning_title') ?></strong>
                        <div class="small mt-1"><?= print_translation('admin_update_dev_warning_text') ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($updateBlockers)): ?>
                <div class="admin-update-notice admin-update-notice--danger">
                    <i class="ci-banned" aria-hidden="true"></i>
                    <div>
                        <strong><?= print_translation('admin_update_blockers') ?></strong>
                        <ul class="mb-0 mt-2 ps-3">
                            <?php foreach ($updateBlockers as $blocker): ?>
                                <li><?= htmlSC((string)$blocker) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($isCreator): ?>
                <div class="admin-update-actions">
                    <form action="<?= base_href('/admin/settings/update-center/check') ?>" method="post">
                        <?= get_csrf_field() ?>
                        <button class="btn <?= $updateAvailable ? 'btn-outline-secondary' : 'btn-primary' ?> rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4" type="submit">
                            <i class="ci-refresh"></i><?= print_translation('admin_update_check_btn') ?>
                        </button>
                    </form>

                    <?php if ($updateAvailable): ?>
                        <form action="<?= base_href('/admin/settings/update-center/update') ?>" method="post">
                            <?= get_csrf_field() ?>
                            <button class="btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4" type="submit" <?= !empty($updateBlockers) ? 'disabled' : '' ?>>
                                <i class="ci-download"></i><?= print_translation('admin_update_run_btn') ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="admin-update-overview__meta">
                <span>
                    <i class="ci-layers" aria-hidden="true"></i>
                    <?= htmlSC($updaterChannelLabel) ?>
                </span>
                <span>
                    <i class="ci-github" aria-hidden="true"></i>
                    <?= htmlSC($updateSourceLabel) ?>
                </span>
                <span>
                    <i class="ci-clock" aria-hidden="true"></i>
                    <?= print_translation('admin_update_checked_at') ?>:
                    <?= htmlSC($checkedAtLabel !== '' ? $checkedAtLabel : '—') ?>
                </span>
            </div>
        </section>

        <section class="admin-update-release">
            <div class="admin-update-section-heading">
                <span class="admin-update-section-heading__icon" aria-hidden="true"><i class="ci-file-text"></i></span>
                <div>
                    <h3 class="h5 mb-1"><?= print_translation('admin_update_release_notes') ?></h3>
                    <p class="text-body-secondary mb-0"><?= htmlSC($remoteReleaseDescription) ?></p>
                </div>
            </div>

            <?php if (!empty($remoteReleaseLines)): ?>
                <ul class="admin-update-changes">
                    <?php foreach (array_slice($remoteReleaseLines, 0, 8) as $line): ?>
                        <li><?= htmlSC((string)$line) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php elseif (empty($release)): ?>
                <p class="text-body-secondary mb-0"><?= print_translation('admin_update_no_release_data') ?></p>
            <?php endif; ?>

            <?php if (($release['html_url'] ?? '') !== ''): ?>
                <a class="btn btn-outline-secondary rounded-pill align-self-start" href="<?= htmlSC((string)$release['html_url']) ?>" target="_blank" rel="noopener noreferrer">
                    <i class="ci-external-link me-2"></i><?= print_translation('admin_update_release_link') ?>
                </a>
            <?php endif; ?>

            <?php if (!$isCreator): ?>
                <div class="small text-body-secondary"><?= print_translation('admin_update_admin_simple_note') ?></div>
            <?php endif; ?>
        </section>

        <?php if ($isCreator): ?>
            <details class="admin-update-disclosure">
                <summary>
                    <span class="admin-update-disclosure__summary-icon" aria-hidden="true"><i class="ci-code"></i></span>
                    <span class="min-w-0">
                        <strong class="d-block"><?= print_translation('admin_update_details_title') ?></strong>
                        <span class="d-block small text-body-secondary mt-1"><?= print_translation('admin_update_details_hint') ?></span>
                    </span>
                    <i class="ci-chevron-down admin-update-disclosure__chevron" aria-hidden="true"></i>
                </summary>
                <div class="admin-update-disclosure__body">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h3 class="h6 mb-3"><?= print_translation('admin_update_repository_label') ?></h3>
                            <dl class="admin-update-facts">
                                <div>
                                    <dt><?= print_translation('admin_update_repository_label') ?></dt>
                                    <dd><?= htmlSC((string)($updateConfig['repository'] ?? '—')) ?></dd>
                                </div>
                                <div>
                                    <dt><?= print_translation('admin_update_branch_label') ?></dt>
                                    <dd><?= htmlSC((string)($updateConfig['branch'] ?? 'main')) ?></dd>
                                </div>
                                <div>
                                    <dt><?= print_translation('admin_update_origin_label') ?></dt>
                                    <dd class="text-break"><?= htmlSC((string)($updateLocal['origin_url'] ?? '—')) ?></dd>
                                </div>
                                <div>
                                    <dt><?= print_translation('admin_update_git_status') ?></dt>
                                    <dd><?= htmlSC($gitStatusLabel) ?></dd>
                                </div>
                                <div>
                                    <dt><?= print_translation('admin_update_branch_sync_status') ?></dt>
                                    <dd><?= htmlSC($branchStatusLabel) ?></dd>
                                </div>
                                <div>
                                    <dt><?= print_translation('admin_update_commit_label') ?></dt>
                                    <dd><?= htmlSC((string)($updateLocal['short_commit'] ?? '—')) ?> → <?= htmlSC($remoteCommitLabel) ?></dd>
                                </div>
                            </dl>
                        </div>
                        <div class="col-lg-6">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                                <h3 class="h6 mb-0"><?= print_translation('admin_update_installed_metadata') ?></h3>
                                <span class="badge text-bg-secondary"><?= print_translation('admin_update_version_source_file') ?></span>
                            </div>
                            <div class="fw-semibold mb-1">
                                <?= htmlSC($localReleaseName !== '' ? $localReleaseName : 'FIREBALL_CMS') ?>
                                <span class="text-body-secondary">v<?= htmlSC($installedVersionLabel) ?></span>
                            </div>
                            <p class="text-body-secondary"><?= htmlSC($localReleaseDescription) ?></p>
                            <div class="small text-body-secondary mb-2"><?= print_translation('admin_update_changes_label') ?></div>
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
                    </div>

                    <div class="admin-update-technical-footer">
                        <span class="small text-body-secondary">
                            <?= print_translation('admin_update_last_updated_at') ?>:
                            <?= htmlSC($lastUpdatedAtLabel !== '' ? $lastUpdatedAtLabel : '—') ?>
                        </span>
                        <form
                            action="<?= base_href('/admin/settings/update-center/rollback') ?>"
                            method="post"
                            data-admin-delete-form
                            data-delete-message="<?= htmlSC(return_translation('admin_update_rollback_confirm')) ?>"
                            data-delete-confirm-label="<?= htmlSC(return_translation('admin_update_rollback_btn')) ?>"
                        >
                            <?= get_csrf_field() ?>
                            <button class="btn btn-sm btn-outline-danger rounded-pill d-inline-flex align-items-center gap-2" type="submit" <?= !$canRollback ? 'disabled' : '' ?>>
                                <i class="ci-rotate-ccw"></i><?= print_translation('admin_update_rollback_btn') ?>
                            </button>
                        </form>
                    </div>
                </div>
            </details>

            <details class="admin-update-disclosure" <?= !empty($formData) ? 'open' : '' ?>>
                <summary>
                    <span class="admin-update-disclosure__summary-icon" aria-hidden="true"><i class="ci-settings"></i></span>
                    <span class="min-w-0">
                        <strong class="d-block"><?= print_translation('admin_update_settings_title') ?></strong>
                        <span class="d-block small text-body-secondary mt-1"><?= print_translation('admin_update_settings_hint') ?></span>
                    </span>
                    <i class="ci-chevron-down admin-update-disclosure__chevron" aria-hidden="true"></i>
                </summary>
                <div class="admin-update-disclosure__body">
                    <form action="<?= base_href('/admin/updates') ?>" method="post">
                        <?= get_csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label"><?= print_translation('admin_settings_update_repository') ?></label>
                                <input class="form-control <?= get_validation_class('updater_github_repository') ?>" type="text" name="updater_github_repository" value="<?= htmlSC($updaterRepository) ?>" placeholder="owner/repository">
                                <div class="form-text"><?= print_translation('admin_settings_update_repository_hint') ?></div>
                                <?= get_errors('updater_github_repository') ?>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="update-channel"><?= print_translation('admin_update_channel_label') ?></label>
                                <select class="form-select <?= get_validation_class('update_channel') ?>" id="update-channel" name="update_channel">
                                    <option value="stable" <?= $updaterChannel === 'stable' ? 'selected' : '' ?>><?= print_translation('admin_update_channel_stable') ?></option>
                                    <option value="dev" <?= $updaterChannel === 'dev' ? 'selected' : '' ?>><?= print_translation('admin_update_channel_dev') ?></option>
                                </select>
                                <?= get_errors('update_channel') ?>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><?= print_translation('admin_settings_update_branch') ?></label>
                                <input class="form-control <?= get_validation_class('updater_github_branch') ?>" type="text" name="updater_github_branch" value="<?= htmlSC($updaterBranch) ?>" placeholder="main">
                                <div class="form-text"><?= print_translation('admin_settings_update_branch_hint') ?></div>
                                <?= get_errors('updater_github_branch') ?>
                            </div>
                            <div class="col-12">
                                <?= view()->renderPartial('incs/password_field', [
                                    'id' => 'updater-github-token',
                                    'name' => 'updater_github_token',
                                    'label' => return_translation('admin_settings_update_token'),
                                    'value' => $updaterToken,
                                    'placeholder' => 'ghp_...',
                                    'autocomplete' => 'off',
                                    'hint' => return_translation('admin_settings_update_token_hint')
                                        . (($settings['updater_github_token'] ?? '') !== ''
                                            ? ' ' . return_translation('admin_settings_update_token_keep_hint')
                                            : ''),
                                ]) ?>
                            </div>
                            <div class="col-12 d-grid d-sm-flex align-items-sm-center gap-3">
                                <button class="btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4" type="submit">
                                    <i class="ci-save"></i><?= print_translation('admin_btn_save') ?>
                                </button>
                                <div class="form-text mb-0"><?= print_translation('admin_settings_update_save_hint') ?></div>
                            </div>
                        </div>
                    </form>
                </div>
            </details>
        <?php endif; ?>
    </div>
    <?php if ($shouldScrollToUpdateCenter): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var target = document.getElementById('update-center');
                if (!target) {
                    return;
                }

                var scrollToTarget = function () {
                    var offset = window.innerWidth < 992 ? 24 : 16;
                    var top = target.getBoundingClientRect().top + window.pageYOffset - offset;
                    window.scrollTo({
                        top: Math.max(0, top),
                        behavior: 'smooth'
                    });

                    if (window.history && typeof window.history.replaceState === 'function') {
                        try {
                            var url = new URL(window.location.href);
                            url.searchParams.delete('scroll');
                            window.history.replaceState({}, document.title, url.toString());
                        } catch (error) {
                            // URL cleanup is optional; scrolling should still work in older browsers.
                        }
                    }
                };

                if (typeof window.requestAnimationFrame === 'function') {
                    window.requestAnimationFrame(function () {
                        window.setTimeout(scrollToTarget, 50);
                    });
                } else {
                    window.setTimeout(scrollToTarget, 50);
                }
            });
        </script>
    <?php endif; ?>
<?= view()->renderPartial('admin/shell_close') ?>
