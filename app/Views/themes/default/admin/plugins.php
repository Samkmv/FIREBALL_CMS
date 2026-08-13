<?php
$statusLabels = [
    'active' => [return_translation('admin_plugins_status_active'), 'text-success bg-success-subtle'],
    'inactive' => [return_translation('admin_plugins_status_inactive'), 'text-secondary bg-secondary-subtle'],
    'not_installed' => [return_translation('admin_plugins_status_not_installed'), 'text-body bg-body-tertiary'],
];
$canUpdatePlugins = \FBL\Auth::isAdmin();
$configuredUpdateCount = count(array_filter(
    $plugins,
    static fn(array $plugin): bool => !empty($plugin['update']['configured'])
));
$availableUpdateCount = count(array_filter(
    $plugins,
    static fn(array $plugin): bool => !empty($plugin['update']['update_available'])
));
$olderSourceCount = count(array_filter(
    $plugins,
    static fn(array $plugin): bool => !empty($plugin['update']['source_older'])
));
$pageActions = '<div class="d-flex flex-wrap align-items-center gap-2">';
$pageActions .= '<a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="'
    . htmlSC(base_href('/admin/docs/plugins')) . '"><i class="ci-book-open"></i>'
    . htmlSC(return_translation('admin_plugins_docs')) . '</a>';
if ($canUpdatePlugins && $configuredUpdateCount > 0) {
    $pageActions .= '<form action="' . htmlSC(base_href('/admin/plugins/check-updates')) . '" method="post" class="d-inline-flex">'
        . get_csrf_field()
        . '<button class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" type="submit">'
        . '<i class="ci-refresh-cw" aria-hidden="true"></i>'
        . htmlSC(return_translation('admin_plugin_updates_check_all'))
        . '</button></form>';
}
$pageActions .= '</div>';
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_plugins_title'),
    'subtitle' => return_translation('admin_plugins_subtitle'),
    'actions' => $pageActions,
]) ?>

    <section class="fb-plugin-overview mb-4" aria-label="<?= htmlSC(return_translation('admin_plugin_updates_independent_title')) ?>">
        <div class="fb-plugin-overview-copy">
            <span class="fb-plugin-overview-icon" aria-hidden="true"><i class="ci-refresh-cw"></i></span>
            <div>
                <h2><?= print_translation('admin_plugin_updates_independent_title') ?></h2>
                <p><?= print_translation('admin_plugin_updates_independent_text') ?></p>
            </div>
        </div>
        <?php if ($configuredUpdateCount > 0): ?>
            <div class="fb-plugin-overview-stats">
                <span class="fb-plugin-stat">
                    <strong><?= htmlSC((string)$configuredUpdateCount) ?></strong>
                    <span><?= print_translation('admin_plugin_updates_configured_count') ?></span>
                </span>
                <?php if ($availableUpdateCount > 0): ?>
                    <span class="fb-plugin-stat is-update">
                        <strong><?= htmlSC((string)$availableUpdateCount) ?></strong>
                        <span><?= print_translation('admin_plugin_updates_available_count') ?></span>
                    </span>
                <?php endif; ?>
                <?php if ($olderSourceCount > 0): ?>
                    <span class="fb-plugin-stat is-warning">
                        <strong><?= htmlSC((string)$olderSourceCount) ?></strong>
                        <span><?= print_translation('admin_plugin_updates_source_older_count') ?></span>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if (empty($plugins)): ?>
        <div class="border rounded-5 p-4 p-md-5 text-center">
            <i class="ci-box fs-1 text-body-tertiary d-block mb-3"></i>
            <h2 class="h5 mb-2"><?= print_translation('admin_plugins_empty_title') ?></h2>
            <p class="text-body-secondary mb-0"><?= print_translation('admin_plugins_empty_text_before') ?> <code>/plugins</code><?= print_translation('admin_plugins_empty_text_after') ?></p>
        </div>
    <?php else: ?>
        <div class="row g-4 align-items-start fb-plugin-grid">
            <?php foreach ($plugins as $plugin): ?>
                <?php
                $pluginSlug = (string)($plugin['slug'] ?? '');
                $pluginDescription = trim((string)($plugin['description'] ?? ''));
                $status = (string)($plugin['status'] ?? 'not_installed');
                $statusData = $statusLabels[$status] ?? [$status, 'text-body bg-body-tertiary'];
                $isValid = !empty($plugin['valid']);
                $isInstalled = !empty($plugin['installed']);
                $isActive = $status === 'active';
                $update = is_array($plugin['update'] ?? null) ? $plugin['update'] : [];
                $updateConfigured = !empty($update['configured']);
                $remoteVersion = trim((string)($update['remote_version'] ?? ''));
                $updateAvailable = !empty($update['update_available']) && $remoteVersion !== '';
                $sourceOlder = !empty($update['source_older']) && $remoteVersion !== '';
                $updateStatus = (string)($update['status'] ?? 'never');
                $releaseNotes = is_array($update['release_notes'] ?? null)
                    ? array_values(array_filter($update['release_notes'], 'is_string'))
                    : [];
                $backupFile = trim((string)($update['backup_file'] ?? ''));
                $updateMessageKey = $updateAvailable
                    ? 'admin_plugin_updates_available'
                    : ($updateStatus === 'error'
                        ? 'admin_plugin_updates_check_failed'
                        : ($sourceOlder
                            ? 'admin_plugin_updates_source_older'
                            : ($updateStatus === 'never'
                                ? 'admin_plugin_updates_not_checked'
                                : 'admin_plugin_updates_current')));
                $updateMessage = return_translation($updateMessageKey);
                $storedUpdateMessage = trim((string)($update['message'] ?? ''));
                $updateAlert = $updateStatus === 'error'
                    ? 'danger'
                    : (($updateAvailable || $sourceOlder)
                        ? 'warning'
                        : ($updateStatus === 'ok' || $updateStatus === 'updated' ? 'success' : 'secondary'));
                $updateIcon = $updateStatus === 'error'
                    ? 'ci-alert-triangle'
                    : ($updateAvailable
                        ? 'ci-download'
                        : ($sourceOlder
                            ? 'ci-alert-triangle'
                            : ($updateStatus === 'ok' || $updateStatus === 'updated'
                                ? 'ci-check-circle'
                                : 'ci-clock')));
                $detailsId = 'plugin-details-' . (preg_replace('/[^a-z0-9_-]+/i', '-', $pluginSlug) ?: 'plugin');
                ?>
                <div class="col-md-6 col-xl-4">
                    <article id="plugin-<?= htmlSC($pluginSlug) ?>" class="card rounded-5 fb-plugin-card<?= $isActive ? ' is-active' : '' ?>">
                        <div class="card-body fb-plugin-card-body">
                            <div class="fb-plugin-card-header">
                                <span class="fb-plugin-card-icon" aria-hidden="true"><i class="ci-package"></i></span>
                                <div class="fb-plugin-card-heading">
                                    <h2><?= htmlSC((string)$plugin['name']) ?></h2>
                                    <code><?= htmlSC($pluginSlug) ?></code>
                                </div>
                                <span class="badge rounded-pill <?= htmlSC($statusData[1]) ?>"><?= htmlSC($statusData[0]) ?></span>
                            </div>

                            <?php if ($pluginDescription !== ''): ?>
                                <p class="fb-plugin-card-description" title="<?= htmlSC($pluginDescription) ?>"><?= htmlSC($pluginDescription) ?></p>
                            <?php endif; ?>

                            <div class="fb-plugin-version-strip">
                                <span>
                                    <small><?= print_translation('admin_plugin_updates_installed_version') ?></small>
                                    <strong><?= htmlSC((string)$plugin['version']) ?></strong>
                                </span>
                                <?php if ($isInstalled && $updateConfigured): ?>
                                    <i class="ci-arrow-right" aria-hidden="true"></i>
                                    <span>
                                        <small><?= print_translation('admin_plugin_updates_source_version') ?></small>
                                        <strong><?= htmlSC($remoteVersion !== '' ? $remoteVersion : '—') ?></strong>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($isInstalled): ?>
                                <?php if ($updateConfigured): ?>
                                    <div class="fb-plugin-update-state is-<?= htmlSC($updateAlert) ?>" role="status">
                                        <i class="<?= htmlSC($updateIcon) ?>" aria-hidden="true"></i>
                                        <span><?= htmlSC($updateMessage) ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="fb-plugin-update-state is-secondary" role="status">
                                        <i class="ci-info" aria-hidden="true"></i>
                                        <span><?= print_translation('admin_plugin_updates_not_configured') ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (!$isValid || !empty($plugin['error']) || !empty($plugin['load_error'])): ?>
                                <div class="alert alert-warning small mt-3 mb-0" role="alert">
                                    <?= htmlSC((string)($plugin['error'] ?: $plugin['load_error'])) ?>
                                </div>
                            <?php endif; ?>

                            <details class="fb-plugin-details" id="<?= htmlSC($detailsId) ?>">
                                <summary>
                                    <span><i class="ci-info" aria-hidden="true"></i><?= print_translation('admin_plugins_details') ?></span>
                                    <i class="ci-chevron-down" aria-hidden="true"></i>
                                </summary>
                                <div class="fb-plugin-details-body">
                                    <dl>
                                        <div>
                                            <dt><?= print_translation('admin_plugins_author') ?></dt>
                                            <dd><?= htmlSC((string)$plugin['author']) ?></dd>
                                        </div>
                                        <?php if ($isInstalled && $updateConfigured): ?>
                                            <div>
                                                <dt><?= print_translation('admin_plugin_updates_checked_at') ?></dt>
                                                <dd><?= htmlSC((string)(($update['checked_at'] ?? '') !== '' ? $update['checked_at'] : '—')) ?></dd>
                                            </div>
                                            <?php if (($update['last_updated_at'] ?? '') !== ''): ?>
                                                <div>
                                                    <dt><?= print_translation('admin_plugin_updates_updated_at') ?></dt>
                                                    <dd><?= htmlSC((string)$update['last_updated_at']) ?></dd>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($backupFile !== ''): ?>
                                                <div>
                                                    <dt><?= print_translation('admin_plugin_updates_backup') ?></dt>
                                                    <dd><code><?= htmlSC($backupFile) ?></code></dd>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </dl>

                                    <?php if ($storedUpdateMessage !== '' && $storedUpdateMessage !== $updateMessage): ?>
                                        <p class="fb-plugin-details-message"><?= htmlSC($storedUpdateMessage) ?></p>
                                    <?php endif; ?>

                                    <?php if ($releaseNotes !== []): ?>
                                        <div class="fb-plugin-release-notes">
                                            <strong><?= print_translation('admin_plugin_updates_release_notes') ?></strong>
                                            <ul>
                                                <?php foreach ($releaseNotes as $note): ?>
                                                    <li><?= htmlSC($note) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </details>
                        </div>
                        <div class="card-footer fb-plugin-card-footer">
                            <div class="fb-plugin-card-actions">
                                <?php if ($canUpdatePlugins && $isInstalled && $isValid && $updateConfigured && $updateAvailable): ?>
                                    <form
                                        class="fb-plugin-action-primary"
                                        action="<?= base_href('/admin/plugins/update') ?>"
                                        method="post"
                                        data-admin-delete-form
                                        data-delete-message="<?= htmlSC(return_translation('admin_plugin_updates_confirm')) ?>"
                                        data-delete-item="<?= htmlSC((string)$plugin['name']) ?>"
                                        data-delete-confirm-label="<?= htmlSC(return_translation('admin_plugin_updates_install')) ?>"
                                        data-confirm-title="<?= htmlSC(return_translation('admin_plugin_updates_modal_title')) ?>"
                                        data-confirm-item-label="<?= htmlSC(return_translation('admin_plugin_updates_modal_item_label')) ?>"
                                        data-confirm-hint="<?= htmlSC(return_translation('admin_plugin_updates_modal_hint')) ?>"
                                        data-confirm-icon="ci-download"
                                        data-confirm-variant="warning"
                                    >
                                        <?= get_csrf_field() ?>
                                        <input type="hidden" name="slug" value="<?= htmlSC($pluginSlug) ?>">
                                        <button class="btn btn-warning rounded-pill w-100 d-inline-flex align-items-center justify-content-center gap-2" type="submit">
                                            <i class="ci-download" aria-hidden="true"></i>
                                            <?= print_translation('admin_plugin_updates_install') ?>
                                            <?= htmlSC($remoteVersion) ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($canUpdatePlugins && $isInstalled && $isValid && $updateConfigured): ?>
                                    <form action="<?= base_href('/admin/plugins/check-update') ?>" method="post">
                                        <?= get_csrf_field() ?>
                                        <input type="hidden" name="slug" value="<?= htmlSC($pluginSlug) ?>">
                                        <button class="btn btn-outline-dark rounded-pill w-100 d-inline-flex align-items-center justify-content-center gap-2" type="submit">
                                            <i class="ci-refresh-cw" aria-hidden="true"></i>
                                            <?= print_translation('admin_plugin_updates_check') ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if (!$isInstalled): ?>
                                    <form class="fb-plugin-action-primary" action="<?= base_href('/admin/plugins/install') ?>" method="post">
                                        <?= get_csrf_field() ?>
                                        <input type="hidden" name="slug" value="<?= htmlSC($pluginSlug) ?>">
                                        <button class="btn btn-dark rounded-pill w-100" type="submit" <?= $isValid ? '' : 'disabled' ?>>
                                            <?= print_translation('admin_plugins_install') ?>
                                        </button>
                                    </form>
                                <?php elseif (!$isActive): ?>
                                    <form class="<?= $updateConfigured ? '' : 'fb-plugin-action-primary' ?>" action="<?= base_href('/admin/plugins/activate') ?>" method="post">
                                        <?= get_csrf_field() ?>
                                        <input type="hidden" name="slug" value="<?= htmlSC($pluginSlug) ?>">
                                        <button class="btn btn-dark rounded-pill w-100" type="submit" <?= $isValid ? '' : 'disabled' ?>>
                                            <?= print_translation('admin_plugins_activate') ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form class="<?= $updateConfigured ? '' : 'fb-plugin-action-primary' ?>" action="<?= base_href('/admin/plugins/deactivate') ?>" method="post" data-admin-delete-form data-delete-message="<?= htmlSC(return_translation('admin_plugins_deactivate_confirm')) ?>" data-delete-item="<?= htmlSC((string)$plugin['name']) ?>">
                                        <?= get_csrf_field() ?>
                                        <input type="hidden" name="slug" value="<?= htmlSC($pluginSlug) ?>">
                                        <button class="btn btn-outline-secondary rounded-pill w-100" type="submit">
                                            <?= print_translation('admin_plugins_deactivate') ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?= view()->renderPartial('admin/shell_close') ?>
