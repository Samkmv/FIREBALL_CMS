<?php
$theme = $theme_item ?? [];
$slug = (string)($theme['slug'] ?? '');
$selected = $selected_file ?? null;
$selectedPath = (string)($selected_path ?? '');
$editorBase = base_href('/admin/theme-editor/');
$previewUrl = base_href('/') . '?preview_theme=' . rawurlencode($slug);

$directories = [];
$collectDirectories = static function (array $nodes) use (&$collectDirectories, &$directories): void {
    foreach ($nodes as $node) {
        if (($node['type'] ?? '') !== 'directory') {
            continue;
        }
        $directories[] = (string)$node['path'];
        $collectDirectories($node['children'] ?? []);
    }
};
$collectDirectories($tree ?? []);
$displayedCurrentPath = '/themes/' . $slug;

$fileIcon = static function (array $node): string {
    if (!empty($node['is_image'])) {
        return 'ci-image';
    }
    return match (strtolower((string)($node['extension'] ?? pathinfo((string)($node['name'] ?? ''), PATHINFO_EXTENSION)))) {
        'php', 'html', 'htm' => 'ci-code',
        'css' => 'ci-paint',
        'js' => 'ci-terminal',
        'json' => 'ci-code',
        'md', 'txt' => 'ci-file-text',
        default => 'ci-file',
    };
};

$renderTree = static function (array $nodes) use (&$renderTree, $slug, $selectedPath, $editorBase, $fileIcon): string {
    ob_start();
    ?>
    <ul class="theme-editor-tree-list list-unstyled mb-0">
        <?php foreach ($nodes as $node): ?>
            <?php if (($node['type'] ?? '') === 'directory'): ?>
                <?php $directoryHref = $editorBase . rawurlencode($slug) . '?' . http_build_query(['directory' => (string)$node['path']]); ?>
                <li>
                    <details open>
                        <summary class="theme-editor-tree-directory <?= (string)$node['path'] === $selectedPath ? 'active' : '' ?>">
                            <i class="ci-folder flex-shrink-0"></i>
                            <a class="<?= (string)$node['path'] === $selectedPath ? 'active' : '' ?>" href="<?= htmlSC($directoryHref) ?>" data-theme-editor-file-link onclick="event.stopPropagation()" <?= (string)$node['path'] === $selectedPath ? 'aria-current="true"' : '' ?>>
                                <?= htmlSC((string)$node['name']) ?>
                            </a>
                        </summary>
                        <?= $renderTree($node['children'] ?? []) ?>
                    </details>
                </li>
            <?php else: ?>
                <?php
                $path = (string)$node['path'];
                $href = $editorBase . rawurlencode($slug) . '?' . http_build_query(['file' => $path]);
                ?>
                <li>
                    <a class="theme-editor-tree-file <?= $path === $selectedPath ? 'active' : '' ?>" href="<?= htmlSC($href) ?>" data-theme-editor-file-link <?= $path === $selectedPath ? 'aria-current="true"' : '' ?>>
                        <i class="<?= htmlSC($fileIcon($node)) ?> theme-editor-file-icon flex-shrink-0" data-extension="<?= htmlSC(strtolower((string)($node['extension'] ?? ''))) ?>"></i>
                        <span><?= htmlSC((string)$node['name']) ?></span>
                    </a>
                </li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ul>
    <?php
    return (string)ob_get_clean();
};
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_theme_editor_heading'),
    'subtitle' => return_translation('admin_theme_editor_subtitle'),
    'show_header' => false,
    'container_class' => 'theme-editor-page',
    'sidebar_col_class' => 'col-lg-4 col-xl-3',
    'main_col_class' => 'col-lg-8 col-xl-9',
]) ?>

    <div class="theme-editor" data-theme-editor data-editor-adapter="monaco" data-monaco-url="<?= htmlSC(base_url('/assets/default/vendor/monaco-0.55.1/editor.js')) ?>" data-editor-fallback-message="<?= htmlSC(return_translation('admin_theme_editor_fallback')) ?>" data-unsaved-message="<?= htmlSC(return_translation('admin_theme_editor_unsaved')) ?>">
        <header class="theme-editor-heading">
            <div>
                <div class="small text-body-secondary mb-1"><?= print_translation('admin_nav_group_appearance') ?> / <?= print_translation('admin_theme_editor_heading') ?></div>
                <h1 class="h4 mb-1"><?= print_translation('admin_theme_editor_heading') ?></h1>
                <p class="small text-body-secondary mb-0"><?= print_translation('admin_theme_editor_subtitle') ?></p>
            </div>
            <div class="theme-editor-summary card border rounded-3">
                <?php if (!empty($theme['preview_url'])): ?><img class="theme-editor-thumbnail" src="<?= htmlSC((string)$theme['preview_url']) ?>" alt=""><?php endif; ?>
                <div class="theme-editor-summary-name">
                    <strong><?= htmlSC((string)($theme['name'] ?? $slug)) ?></strong>
                    <?php if (($active_theme['slug'] ?? '') === $slug): ?><span class="badge text-bg-success ms-1"><?= print_translation('admin_themes_active') ?></span><?php endif; ?>
                    <div class="small text-body-secondary text-truncate"><?= htmlSC((string)($theme['description'] ?? '')) ?></div>
                </div>
                <a class="btn btn-sm btn-outline-secondary" href="<?= base_href('/admin/themes/edit/' . rawurlencode($slug)) ?>" data-theme-editor-file-link><i class="ci-settings me-1"></i><?= print_translation('admin_theme_editor_settings') ?></a>
            </div>
        </header>
        <?php if ($slug === 'default'): ?>
            <div class="alert alert-warning theme-editor-notice d-flex align-items-center justify-content-between flex-wrap gap-2" role="alert">
                <div>
                    <span class="fw-semibold"><?= print_translation('admin_theme_editor_default_warning_title') ?></span> <?= print_translation('admin_theme_editor_default_warning') ?>
                </div>
                <button class="btn btn-sm btn-warning rounded-pill" type="button" data-bs-toggle="modal" data-bs-target="#themeCopyModal">
                    <?= print_translation('admin_theme_editor_copy') ?>
                </button>
            </div>
        <?php endif; ?>

        <div class="theme-editor-toolbar border rounded-top-3 p-2">
            <div class="d-flex align-items-center gap-2 theme-editor-toolbar-actions">
                <select class="form-select theme-editor-theme-select" aria-label="<?= htmlSC(return_translation('admin_theme_editor_select_theme')) ?>" data-theme-editor-theme-select>
                    <?php foreach ($themes as $item): ?>
                        <option value="<?= htmlSC($editorBase . rawurlencode((string)$item['slug'])) ?>" <?= (string)$item['slug'] === $slug ? 'selected' : '' ?>>
                            <?= htmlSC((string)$item['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if ($selected && ($selected['type'] ?? '') === 'file' && empty($selected['is_image'])): ?>
                    <button class="btn btn-sm btn-primary" type="submit" form="themeEditorSaveForm">
                        <i class="ci-save me-1"></i><?= print_translation('admin_theme_editor_save') ?><kbd class="theme-editor-shortcut d-none d-md-inline ms-2">Ctrl/Cmd+S</kbd>
                    </button>
                    <button class="btn btn-icon btn-sm btn-outline-secondary" type="button" data-theme-editor-reset title="<?= htmlSC(return_translation('admin_theme_editor_discard')) ?>" aria-label="<?= htmlSC(return_translation('admin_theme_editor_discard')) ?>">
                        <i class="ci-rotate-ccw"></i>
                    </button>
                <?php endif; ?>

                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="ci-folder me-1"></i><?= print_translation('admin_themes_files') ?></button>
                    <div class="dropdown-menu">
                <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#themeCreateFileModal">
                    <i class="ci-file-plus me-1"></i><?= print_translation('admin_theme_editor_create_file') ?>
                </button>
                <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#themeCreateFolderModal">
                    <i class="ci-folder-plus me-1"></i><?= print_translation('admin_theme_editor_create_folder') ?>
                </button>

                <?php if ($selected && empty($selected['protected'])): ?>
                    <hr class="dropdown-divider">
                    <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#themeRenameModal">
                        <i class="ci-edit me-1"></i><?= print_translation('admin_theme_editor_rename') ?>
                    </button>
                    <button class="dropdown-item text-danger" type="submit" form="themeDeleteForm">
                        <i class="ci-trash me-1"></i><?= print_translation('admin_theme_editor_delete') ?>
                    </button>
                <?php endif; ?>
                <?php if ($selected && ($selected['type'] ?? '') === 'file'): ?>
                    <hr class="dropdown-divider">
                    <button class="dropdown-item" type="button" data-bs-toggle="offcanvas" data-bs-target="#themeHistory">
                        <i class="ci-clock me-1"></i><?= print_translation('admin_theme_editor_backups') ?>
                    </button>
                <?php endif; ?>
                    </div>
                </div>
                <button class="btn btn-sm btn-outline-secondary ms-auto" type="button" data-theme-preview-toggle aria-expanded="true" aria-controls="themeEditorPreview"><i class="ci-eye me-1"></i><?= print_translation('admin_theme_editor_preview') ?></button>
                <a class="btn btn-icon btn-sm btn-outline-secondary" title="<?= htmlSC(return_translation('admin_theme_editor_open_site')) ?>" href="<?= htmlSC($previewUrl) ?>" target="_blank" rel="noopener noreferrer"><i class="ci-external-link"></i><span class="visually-hidden"><?= print_translation('admin_theme_editor_open_site') ?></span></a>
            </div>
        </div>

        <div class="theme-editor-layout">
            <div class="theme-editor-sidebar-col">
                <div class="theme-editor-tree p-2">
                    <div class="theme-editor-current-path small fw-semibold px-2 py-2 border-bottom mb-2"><?= htmlSC($displayedCurrentPath) ?></div>
                    <?= $renderTree($tree ?? []) ?>
                </div>
            </div>

            <div class="theme-editor-main-col">
                <div class="theme-editor-workspace overflow-hidden">
                    <?php if ($editor_error !== ''): ?>
                        <div class="alert alert-danger rounded-0 mb-0"><?= htmlSC($editor_error) ?></div>
                    <?php elseif (!$selected): ?>
                        <div class="p-5 text-center text-body-secondary"><?= print_translation('admin_theme_editor_select_file') ?></div>
                    <?php elseif (($selected['type'] ?? '') === 'directory'): ?>
                        <div class="p-5 text-center">
                            <i class="ci-folder fs-1 text-body-secondary"></i>
                            <h2 class="h5 mt-3 mb-1"><?= htmlSC((string)$selected['name']) ?></h2>
                            <code><?= htmlSC((string)$selected['path']) ?></code>
                        </div>
                    <?php else: ?>
                        <div class="theme-editor-file-header d-flex align-items-center justify-content-between gap-3 border-bottom px-3 py-2">
                            <div class="min-w-0">
                                <span class="theme-editor-file-tab"><i class="<?= htmlSC($fileIcon($selected)) ?> me-2"></i><?= htmlSC((string)$selected['name']) ?><span class="theme-editor-dirty-dot" data-theme-editor-dirty hidden aria-label="<?= htmlSC(return_translation('admin_theme_editor_unsaved')) ?>">●</span></span>
                                <span class="small text-body-secondary ms-2 d-none d-xxl-inline"><?= htmlSC((string)$selected['path']) ?></span>
                            </div>
                            <span class="badge text-bg-secondary">
                                <?= ($selected['type'] ?? '') === 'directory'
                                    ? htmlSC(return_translation('admin_theme_editor_directory'))
                                    : htmlSC(strtoupper((string)$selected['extension'])) ?>
                            </span>
                        </div>

                        <?php if (!empty($selected['is_image'])): ?>
                            <div class="theme-editor-image-preview p-4 text-center">
                                <img src="<?= htmlSC((string)$selected['url']) ?>?v=<?= (int)$selected['modified_at'] ?>" alt="<?= htmlSC((string)$selected['name']) ?>">
                            </div>
                            <form class="border-top p-3" method="post" enctype="multipart/form-data" action="<?= base_href('/admin/theme-editor/replace-image') ?>">
                                <?= get_csrf_field() ?>
                                <input type="hidden" name="slug" value="<?= htmlSC($slug) ?>">
                                <input type="hidden" name="path" value="<?= htmlSC((string)$selected['path']) ?>">
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <input class="form-control" style="max-width: 32rem" type="file" name="image" accept=".png,.jpg,.jpeg,.webp,.gif,.svg" required>
                                    <button class="btn btn-primary rounded-pill" type="submit"><?= print_translation('admin_theme_editor_replace_image') ?></button>
                                </div>
                            </form>
                        <?php else: ?>
                            <form id="themeEditorSaveForm" method="post" action="<?= base_href('/admin/theme-editor/save') ?>">
                                <?= get_csrf_field() ?>
                                <input type="hidden" name="slug" value="<?= htmlSC($slug) ?>">
                                <input type="hidden" name="path" value="<?= htmlSC((string)$selected['path']) ?>">
                                <div class="theme-editor-monaco" data-theme-editor-monaco hidden></div>
                                <textarea
                                    aria-label="<?= htmlSC(return_translation('admin_theme_editor_current_path')) ?>"
                                    class="form-control theme-editor-code"
                                    name="content"
                                    spellcheck="false"
                                    data-theme-editor-code
                                    data-editor-adapter="textarea"
                                    data-editor-language="<?= htmlSC((string)$selected['language']) ?>"
                                ><?= htmlSC((string)$selected['content']) ?></textarea>
                                <div class="small text-body-secondary border-top px-3 py-1" data-theme-editor-status role="status">Monaco · Ctrl/Cmd+S · Tab · Ctrl/Cmd+F</div>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <aside class="theme-editor-preview" id="themeEditorPreview" data-theme-preview-pane>
                <div class="theme-editor-preview-toolbar border-bottom">
                    <strong class="small"><?= print_translation('admin_theme_editor_preview') ?></strong>
                    <div class="d-flex gap-1">
                        <div class="btn-group btn-group-sm theme-editor-device-group" role="group" aria-label="<?= htmlSC(return_translation('admin_theme_editor_preview')) ?>">
                        <?php foreach (['desktop' => 'ci-monitor', 'tablet' => 'ci-tablet', 'mobile' => 'ci-smartphone'] as $device => $icon): ?>
                            <button type="button" class="btn btn-icon btn-sm btn-outline-secondary" data-theme-preview-device="<?= $device ?>" aria-label="<?= htmlSC(return_translation('admin_theme_editor_' . $device)) ?>" aria-pressed="<?= $device === 'desktop' ? 'true' : 'false' ?>"><i class="<?= $icon ?>"></i></button>
                        <?php endforeach; ?>
                        </div>
                        <button class="btn btn-icon btn-sm btn-outline-secondary" type="button" data-theme-preview-reload aria-label="<?= htmlSC(return_translation('admin_theme_editor_reload')) ?>"><i class="ci-refresh-cw"></i></button>
                    </div>
                </div>
                <div class="small text-body-secondary border-bottom px-3 py-2"><?= print_translation('admin_theme_editor_preview_saved') ?></div>
                <div class="theme-editor-preview-stage" data-theme-preview-stage data-device="desktop">
                    <iframe data-theme-preview-frame src="<?= htmlSC($previewUrl) ?>" title="<?= htmlSC(return_translation('admin_theme_editor_preview')) ?>" sandbox="allow-scripts allow-same-origin allow-forms" loading="lazy"></iframe>
                </div>
            </aside>
        </div>

        <?php if ($selected && empty($selected['protected'])): ?>
            <form id="themeDeleteForm" method="post" action="<?= base_href('/admin/theme-editor/delete') ?>" data-theme-editor-delete-form data-confirm="<?= htmlSC(return_translation('admin_theme_editor_delete_confirm')) ?>">
                <?= get_csrf_field() ?>
                <input type="hidden" name="slug" value="<?= htmlSC($slug) ?>">
                <input type="hidden" name="path" value="<?= htmlSC((string)$selected['path']) ?>">
            </form>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="themeCreateFileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><form class="modal-content" method="post" action="<?= base_href('/admin/theme-editor/create-file') ?>">
            <div class="modal-header"><h2 class="modal-title fs-5"><?= print_translation('admin_theme_editor_create_file') ?></h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <?= get_csrf_field() ?><input type="hidden" name="slug" value="<?= htmlSC($slug) ?>">
                <label class="form-label"><?= print_translation('admin_theme_editor_directory') ?></label>
                <select class="form-select mb-3" name="directory" required>
                    <?php foreach ($directories as $directory): ?><option value="<?= htmlSC($directory) ?>"><?= htmlSC($directory) ?></option><?php endforeach; ?>
                </select>
                <label class="form-label"><?= print_translation('admin_theme_editor_name') ?></label>
                <input class="form-control" name="name" placeholder="custom.php" pattern="^[A-Za-z0-9][A-Za-z0-9._-]*\.(php|css|js|json|md|txt)$" required>
                <div class="form-text"><?= print_translation('admin_theme_editor_allowed_file_extensions') ?></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary rounded-pill" type="submit"><?= print_translation('admin_theme_editor_create') ?></button></div>
        </form></div>
    </div>

    <div class="modal fade" id="themeCreateFolderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><form class="modal-content" method="post" action="<?= base_href('/admin/theme-editor/create-directory') ?>">
            <div class="modal-header"><h2 class="modal-title fs-5"><?= print_translation('admin_theme_editor_create_folder') ?></h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <?= get_csrf_field() ?><input type="hidden" name="slug" value="<?= htmlSC($slug) ?>">
                <label class="form-label"><?= print_translation('admin_theme_editor_directory') ?></label>
                <select class="form-select mb-3" name="directory" required>
                    <?php foreach ($directories as $directory): ?><option value="<?= htmlSC($directory) ?>"><?= htmlSC($directory) ?></option><?php endforeach; ?>
                </select>
                <label class="form-label"><?= print_translation('admin_theme_editor_name') ?></label>
                <input class="form-control" name="name" placeholder="components" required>
            </div>
            <div class="modal-footer"><button class="btn btn-primary rounded-pill" type="submit"><?= print_translation('admin_theme_editor_create') ?></button></div>
        </form></div>
    </div>

    <?php if ($selected && empty($selected['protected'])): ?>
        <div class="modal fade" id="themeRenameModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog"><form class="modal-content" method="post" action="<?= base_href('/admin/theme-editor/rename') ?>">
                <div class="modal-header"><h2 class="modal-title fs-5"><?= print_translation('admin_theme_editor_rename') ?></h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <?= get_csrf_field() ?><input type="hidden" name="slug" value="<?= htmlSC($slug) ?>"><input type="hidden" name="path" value="<?= htmlSC((string)$selected['path']) ?>">
                    <label class="form-label"><?= print_translation('admin_theme_editor_name') ?></label>
                    <input class="form-control" name="name" value="<?= htmlSC((string)$selected['name']) ?>" required>
                </div>
                <div class="modal-footer"><button class="btn btn-primary rounded-pill" type="submit"><?= print_translation('admin_theme_editor_rename') ?></button></div>
            </form></div>
        </div>
    <?php endif; ?>

    <?php if ($selected && ($selected['type'] ?? '') === 'file'): ?>
        <div class="offcanvas offcanvas-end" tabindex="-1" id="themeHistory" aria-labelledby="themeHistoryLabel">
            <div class="offcanvas-header"><h2 class="offcanvas-title fs-5" id="themeHistoryLabel"><?= print_translation('admin_theme_editor_history') ?></h2><button class="btn-close" type="button" data-bs-dismiss="offcanvas"></button></div>
            <div class="offcanvas-body">
                <?php if (empty($history)): ?>
                    <p class="text-body-secondary"><?= print_translation('admin_theme_editor_history_empty') ?></p>
                <?php else: ?>
                    <div class="vstack gap-3">
                        <?php foreach ($history as $backup): ?>
                            <div class="border rounded-4 p-3">
                                <div class="fw-semibold"><?= htmlSC(date('d.m.Y H:i:s', strtotime((string)$backup['created_at']))) ?></div>
                                <div class="small text-body-secondary"><?= htmlSC((string)($backup['path'] ?? $selected['path'])) ?></div>
                                <div class="small text-body-secondary mb-2"><?= htmlSC((string)$backup['user']) ?> · <?= number_format((int)$backup['size'] / 1024, 1) ?> KB</div>
                                <form method="post" action="<?= base_href('/admin/theme-editor/restore') ?>">
                                    <?= get_csrf_field() ?><input type="hidden" name="slug" value="<?= htmlSC($slug) ?>"><input type="hidden" name="path" value="<?= htmlSC((string)$selected['path']) ?>"><input type="hidden" name="backup_id" value="<?= htmlSC((string)$backup['id']) ?>">
                                    <button class="btn btn-sm btn-outline-primary rounded-pill" type="submit"><?= print_translation('admin_theme_editor_restore') ?></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="modal fade" id="themeCopyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><form class="modal-content" method="post" action="<?= base_href('/admin/theme-editor/copy') ?>">
            <div class="modal-header"><h2 class="modal-title fs-5"><?= print_translation('admin_theme_editor_copy') ?></h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <?= get_csrf_field() ?><input type="hidden" name="slug" value="<?= htmlSC($slug) ?>">
                <label class="form-label"><?= print_translation('admin_themes_field_name') ?></label>
                <input class="form-control mb-3" name="new_name" value="<?= htmlSC((string)($theme['name'] ?? '') . ' Copy') ?>" required>
                <label class="form-label">Slug</label>
                <input class="form-control" name="new_slug" value="<?= htmlSC($slug . '-copy') ?>" pattern="[a-z0-9][a-z0-9_-]*" required>
            </div>
            <div class="modal-footer"><button class="btn btn-warning rounded-pill" type="submit"><?= print_translation('admin_theme_editor_copy') ?></button></div>
        </form></div>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
