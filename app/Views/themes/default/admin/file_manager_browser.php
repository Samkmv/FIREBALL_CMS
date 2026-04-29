<?php
$currentDir = (string)($manager['current_dir'] ?? '');
$parentDir = $manager['parent_dir'] ?? null;
$pickerMode = !empty($picker_mode);
$pickerField = trim((string)($picker_field ?? ''));
$items = $manager['items'] ?? [];
$total = (int)($manager['total'] ?? count($items));
$search = (string)($manager['search'] ?? '');
$sort = (string)($manager['sort'] ?? 'modified');
$direction = (string)($manager['direction'] ?? 'desc');
$pagination = $manager['pagination'] ?? null;
$breadcrumbs = $manager['breadcrumbs'] ?? [];
$directoryCount = count($manager['directories'] ?? []);
$fileCount = count($manager['files'] ?? []);

$sortIndicator = static function (string $column) use ($sort, $direction): string {
    if ($sort !== $column) {
        return '';
    }

    return strtolower($direction) === 'asc' ? ' ↑' : ' ↓';
};

$buildManagerUrl = static function (?string $dir = null, array $overrides = []) use ($pickerMode, $pickerField, $sort, $direction, $search, $currentDir): string {
    $params = [];
    $dirValue = array_key_exists('dir', $overrides) ? $overrides['dir'] : $dir;

    if ($dirValue === null) {
        $dirValue = $currentDir;
    }

    if ($dirValue !== '') {
        $params['dir'] = $dirValue;
    }

    if ($pickerMode) {
        $params['picker'] = '1';
    }

    if ($pickerField !== '') {
        $params['field'] = $pickerField;
    }

    $params['sort'] = (string)($overrides['sort'] ?? $sort);
    $params['direction'] = (string)($overrides['direction'] ?? $direction);

    $searchValue = array_key_exists('q', $overrides) ? (string)$overrides['q'] : $search;
    if ($searchValue !== '') {
        $params['q'] = $searchValue;
    }

    $query = $params ? ('?' . http_build_query($params)) : '';

    return base_href('/admin/files' . $query);
};

$buildSortUrl = static function (string $column) use ($sort, $direction, $buildManagerUrl): string {
    $nextDirection = ($sort === $column && strtolower($direction) === 'asc') ? 'desc' : 'asc';
    return $buildManagerUrl(null, ['sort' => $column, 'direction' => $nextDirection]);
};
?>

<div data-file-manager-workspace>
    <aside class="p-3 p-lg-4" data-file-manager-sidebar>
        <div class="d-flex align-items-center gap-2 mb-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 2.5rem; height: 2.5rem; background: #1f5c4f;">
                <i class="ci-folder"></i>
            </div>
            <div>
                <div class="fw-semibold"><?= print_translation('admin_files_root') ?></div>
                <div class="small text-body-secondary">/<?= htmlSC($currentDir !== '' ? $currentDir : return_translation('admin_files_root')) ?></div>
            </div>
        </div>

        <div class="list-group list-group-flush mb-3">
            <a class="list-group-item list-group-item-action <?= $currentDir === '' ? 'active' : '' ?>" href="<?= $buildManagerUrl('') ?>" data-fm-nav-link>
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <span class="d-inline-flex align-items-center gap-2"><i class="ci-home"></i><?= print_translation('admin_files_root') ?></span>
                    <span class="small text-body-secondary"><?= $directoryCount + $fileCount ?></span>
                </div>
            </a>
            <?php if ($parentDir !== null): ?>
                <a class="list-group-item list-group-item-action" href="<?= $buildManagerUrl($parentDir) ?>" data-fm-nav-link>
                    <span class="d-inline-flex align-items-center gap-2"><i class="ci-corner-up-left"></i><?= print_translation('admin_files_up') ?></span>
                </a>
            <?php endif; ?>
        </div>

        <div class="small text-uppercase text-body-secondary fw-semibold mb-2"><?= print_translation('admin_files_folders') ?></div>
        <div class="list-group list-group-flush">
            <?php foreach ($breadcrumbs as $crumb): ?>
                <a class="list-group-item list-group-item-action <?= ($crumb['dir'] ?? '') === $currentDir ? 'active' : '' ?>" href="<?= $buildManagerUrl($crumb['dir'] ?? '') ?>" data-fm-nav-link>
                    <span class="d-inline-flex align-items-center gap-2"><i class="ci-folder"></i><?= htmlSC((string)($crumb['label'] ?? '')) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </aside>

    <div data-file-manager-content>
        <div class="p-3 p-lg-4" data-file-manager-feedback-wrap></div>

        <div class="px-3 px-lg-4 py-3" data-file-manager-toolbar>
            <div class="d-flex flex-column gap-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="min-w-0">
                        <div class="small text-uppercase text-body-secondary fw-semibold mb-1"><?= print_translation('admin_files_heading') ?></div>
                        <div class="d-flex align-items-center gap-2" data-file-manager-breadcrumbs>
                            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                                <?php if ($index > 0): ?>
                                    <span class="text-body-secondary">/</span>
                                <?php endif; ?>
                                <a class="btn btn-sm btn-outline-secondary rounded-pill" href="<?= $buildManagerUrl($crumb['dir'] ?? '') ?>" data-fm-nav-link><?= htmlSC((string)($crumb['label'] ?? '')) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2" data-file-manager-toolbar-actions>
                        <div class="dropdown">
                            <button class="btn btn-dark rounded-pill dropdown-toggle d-inline-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="ci-plus"></i><?= print_translation('admin_files_add_btn') ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4">
                                <li>
                                    <button class="dropdown-item d-inline-flex align-items-center gap-2" type="button" data-file-manager-open-upload>
                                        <i class="ci-upload"></i><?= print_translation('admin_files_upload_label') ?>
                                    </button>
                                </li>
                                <li>
                                    <button class="dropdown-item d-inline-flex align-items-center gap-2" type="button" data-file-manager-open-folder>
                                        <i class="ci-folder-plus"></i><?= print_translation('admin_files_folder_label') ?>
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <div class="dropdown">
                            <button class="btn btn-outline-dark rounded-pill dropdown-toggle d-inline-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-file-manager-action-toggle disabled>
                                <i class="ci-settings"></i><?= print_translation('admin_files_actions_btn') ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4">
                                <li>
                                    <button class="dropdown-item d-inline-flex align-items-center gap-2" type="button" data-file-manager-action="open">
                                        <i class="ci-folder"></i><?= print_translation('admin_btn_open') ?>
                                    </button>
                                </li>
                                <li>
                                    <button class="dropdown-item d-inline-flex align-items-center gap-2" type="button" data-file-manager-action="rename">
                                        <i class="ci-edit"></i><?= print_translation('admin_files_rename') ?>
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item text-danger d-inline-flex align-items-center gap-2" type="button" data-file-manager-action="delete">
                                        <i class="ci-trash"></i><?= print_translation('admin_files_delete_selected') ?>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                    <div class="d-flex flex-wrap align-items-center gap-2" data-file-manager-status>
                        <span class="badge rounded-pill px-3 py-2 fw-medium" data-file-manager-selection-badge>
                            <?= print_translation('admin_files_selected_count') ?>: <span data-file-manager-selection-count>0</span>
                        </span>
                        <span class="small text-body-secondary"><?= str_replace(':size', '200', return_translation('admin_files_upload_limit_hint')) ?></span>
                    </div>

                    <form method="get" class="position-relative" data-fm-search-form data-file-manager-search-form>
                        <input type="hidden" name="dir" value="<?= htmlSC($currentDir) ?>">
                        <?php if ($pickerMode): ?>
                            <input type="hidden" name="picker" value="1">
                        <?php endif; ?>
                        <?php if ($pickerField !== ''): ?>
                            <input type="hidden" name="field" value="<?= htmlSC($pickerField) ?>">
                        <?php endif; ?>
                        <input type="hidden" name="sort" value="<?= htmlSC($sort) ?>">
                        <input type="hidden" name="direction" value="<?= htmlSC($direction) ?>">
                        <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                        <input type="search" name="q" value="<?= htmlSC($search) ?>" class="table-search form-control form-icon-start rounded-pill" placeholder="<?= print_translation('admin_table_search_placeholder') ?>">
                    </form>
                </div>
            </div>
        </div>

        <div class="p-3 p-lg-4">
            <?php if (empty($items)): ?>
                <div class="text-center py-5">
                    <div class="rounded-circle bg-body-tertiary border d-inline-flex align-items-center justify-content-center mb-3" style="width: 72px; height: 72px;">
                        <i class="ci-folder fs-2 text-body-secondary"></i>
                    </div>
                    <p class="text-body-secondary mb-0"><?= $search !== '' ? return_translation('admin_table_empty_search') : return_translation('admin_files_empty') ?></p>
                </div>
            <?php else: ?>
                <form action="<?= base_href('/admin/files/action') ?>" method="post" data-file-manager-bulk-form>
                    <?= get_csrf_field() ?>
                    <input type="hidden" name="dir" value="<?= htmlSC($currentDir) ?>">
                    <input type="hidden" name="picker" value="<?= $pickerMode ? '1' : '0' ?>">
                    <input type="hidden" name="field" value="<?= htmlSC($pickerField) ?>">
                    <input type="hidden" name="q" value="<?= htmlSC($search) ?>">
                    <input type="hidden" name="sort" value="<?= htmlSC($sort) ?>">
                    <input type="hidden" name="direction" value="<?= htmlSC($direction) ?>">
                    <input type="hidden" name="action_name" value="" data-file-manager-action-name>

                    <div class="table-responsive overflow-auto admin-table-scroll" data-file-manager-table-wrap>
                        <table class="table align-middle mb-0" data-file-manager-table>
                            <thead class="position-sticky top-0">
                            <tr>
                                <th scope="col" style="width: 48px;">
                                    <input class="form-check-input" type="checkbox" data-file-manager-toggle-all>
                                </th>
                                <th scope="col">
                                    <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $buildSortUrl('name') ?>" data-fm-nav-link>
                                        <?= print_translation('admin_files_col_name') ?><?= $sortIndicator('name') ?>
                                    </a>
                                </th>
                                <th scope="col">
                                    <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $buildSortUrl('type') ?>" data-fm-nav-link>
                                        <?= print_translation('admin_files_col_type') ?><?= $sortIndicator('type') ?>
                                    </a>
                                </th>
                                <th scope="col">
                                    <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $buildSortUrl('size') ?>" data-fm-nav-link>
                                        <?= print_translation('admin_files_size') ?><?= $sortIndicator('size') ?>
                                    </a>
                                </th>
                                <th scope="col">
                                    <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $buildSortUrl('modified') ?>" data-fm-nav-link>
                                        <?= print_translation('admin_files_modified') ?><?= $sortIndicator('modified') ?>
                                    </a>
                                </th>
                                <th scope="col" class="text-end"><?= print_translation('admin_posts_col_actions') ?></th>
                            </tr>
                            </thead>
                            <tbody class="table-list">
                            <?php foreach ($items as $index => $item): ?>
                                <?php
                                $isDirectory = ($item['type'] ?? '') === 'directory';
                                $openUrl = $isDirectory ? $buildManagerUrl($item['relative_path']) : (string)($item['url'] ?? '');
                                $previewable = !$isDirectory && !empty($item['is_image']);
                                ?>
                                <tr
                                    data-file-manager-row
                                    data-path="<?= htmlSC((string)($item['relative_path'] ?? '')) ?>"
                                    data-type="<?= htmlSC((string)($item['type'] ?? 'file')) ?>"
                                    data-open-url="<?= htmlSC($openUrl) ?>"
                                    data-public-path="<?= htmlSC((string)($item['public_path'] ?? '')) ?>"
                                    data-name="<?= htmlSC((string)($item['name'] ?? '')) ?>"
                                    data-base-name="<?= htmlSC((string)pathinfo((string)($item['name'] ?? ''), PATHINFO_FILENAME)) ?>"
                                    data-extension="<?= htmlSC((string)($item['extension'] ?? '')) ?>"
                                    data-preview-url="<?= htmlSC((string)($item['url'] ?? '')) ?>"
                                    data-can-preview="<?= $previewable ? '1' : '0' ?>"
                                >
                                    <td data-file-manager-select-cell>
                                        <input class="form-check-input" type="checkbox" name="selected_paths[]" value="<?= htmlSC((string)($item['relative_path'] ?? '')) ?>" data-file-manager-select>
                                        <input type="hidden" name="selected_types[]" value="<?= htmlSC((string)($item['type'] ?? 'file')) ?>" data-file-manager-select-type disabled>
                                    </td>
                                    <td data-file-manager-name-cell>
                                        <div class="d-flex align-items-center gap-3 min-w-0">
                                            <?php if ($previewable): ?>
                                                <button type="button" class="btn p-0 border-0 bg-transparent flex-shrink-0" data-file-preview data-file-preview-url="<?= htmlSC((string)$item['url']) ?>" data-file-preview-name="<?= htmlSC((string)$item['name']) ?>" style="cursor: zoom-in;">
                                                    <img src="<?= htmlSC((string)$item['url']) ?>" alt="<?= htmlSC((string)$item['name']) ?>" class="rounded-4 border object-fit-cover" style="width: 56px; height: 56px;">
                                                </button>
                                            <?php else: ?>
                                                <div class="rounded-4 border bg-body-tertiary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 56px; height: 56px;">
                                                    <i class="<?= $isDirectory ? 'ci-folder' : 'ci-file' ?> fs-3 text-body-secondary"></i>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($isDirectory): ?>
                                                <a class="text-decoration-none text-reset min-w-0" href="<?= $buildManagerUrl((string)$item['relative_path']) ?>" data-fm-nav-link data-file-manager-item-link>
                                                    <div class="fw-medium text-truncate"><?= htmlSC((string)$item['name']) ?></div>
                                                    <div class="small text-body-secondary text-truncate">/<?= htmlSC((string)$item['relative_path']) ?></div>
                                                </a>
                                            <?php else: ?>
                                                <div class="min-w-0">
                                                    <div class="fw-medium text-truncate"><?= htmlSC((string)$item['name']) ?></div>
                                                    <div class="small text-body-secondary text-truncate"><?= htmlSC((string)($item['public_path'] ?? '')) ?></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-nowrap">
                                        <span class="badge fs-xs rounded-pill <?= $isDirectory ? 'text-secondary bg-secondary-subtle' : 'text-dark bg-light' ?>">
                                            <?= $isDirectory ? print_translation('admin_files_type_directory') : print_translation('admin_files_type_file') ?>
                                        </span>
                                    </td>
                                    <td class="text-nowrap text-body-secondary small"><?= $isDirectory ? '—' : htmlSC((string)($item['size'] ?? '')) ?></td>
                                    <td class="text-nowrap text-body-secondary small"><?= htmlSC((string)($item['modified_at'] ?? '')) ?></td>
                                    <td class="text-end text-nowrap">
                                        <div class="dropdown d-inline-block" data-file-manager-actions-menu>
                                            <button class="btn btn-sm btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="ci-settings"></i><?= print_translation('admin_files_actions_btn') ?>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4">
                                                <?php if ($pickerMode && $pickerField !== '' && !$isDirectory): ?>
                                                    <li>
                                                        <button class="dropdown-item d-inline-flex align-items-center gap-2" type="button" data-file-select data-file-select-field="<?= htmlSC($pickerField) ?>" data-file-select-value="<?= htmlSC((string)($item['public_path'] ?? '')) ?>">
                                                            <i class="ci-check"></i><?= print_translation('admin_files_select') ?>
                                                        </button>
                                                    </li>
                                                <?php endif; ?>
                                                <li>
                                                    <button class="dropdown-item d-inline-flex align-items-center gap-2" type="button" data-file-manager-row-action="open">
                                                        <i class="<?= $isDirectory ? 'ci-folder' : 'ci-eye' ?>"></i><?= $isDirectory ? print_translation('admin_btn_open') : print_translation('admin_btn_view') ?>
                                                    </button>
                                                </li>
                                                <li>
                                                    <button class="dropdown-item d-inline-flex align-items-center gap-2" type="button" data-file-manager-row-action="rename">
                                                        <i class="ci-edit"></i><?= print_translation('admin_files_rename') ?>
                                                    </button>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <button class="dropdown-item text-danger d-inline-flex align-items-center gap-2" type="button" data-file-manager-row-action="delete">
                                                        <i class="ci-trash"></i><?= print_translation('admin_files_delete_selected') ?>
                                                    </button>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between pt-4 gap-3">
                    <div class="fs-sm text-body-secondary">
                        <?= print_translation('admin_table_showing') ?>
                        <span class="fw-semibold"><?= count($items) ?></span>
                        <?= print_translation('admin_table_of') ?>
                        <span class="fw-semibold"><?= $total ?></span>
                        <span class="d-none d-sm-inline"><?= print_translation('admin_table_results') ?></span>
                    </div>
                    <?php if ($total > 30): ?>
                        <nav aria-label="Pagination" data-fm-pagination>
                            <?= $pagination ?>
                        </nav>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="fileUploadModal" tabindex="-1" aria-hidden="true" data-file-upload-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-5 overflow-hidden">
            <form action="<?= base_href('/admin/files/upload') ?>" method="post" enctype="multipart/form-data" data-fm-async-form>
                <?= get_csrf_field() ?>
                <input type="hidden" name="dir" value="<?= htmlSC($currentDir) ?>">
                <input type="hidden" name="picker" value="<?= $pickerMode ? '1' : '0' ?>">
                <input type="hidden" name="field" value="<?= htmlSC($pickerField) ?>">
                <input type="hidden" name="q" value="<?= htmlSC($search) ?>">
                <input type="hidden" name="sort" value="<?= htmlSC($sort) ?>">
                <input type="hidden" name="direction" value="<?= htmlSC($direction) ?>">
                <div class="modal-header border-0 pb-0">
                    <h2 class="modal-title fs-5"><?= print_translation('admin_files_upload_label') ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <label class="form-label"><?= print_translation('admin_files_upload_label') ?></label>
                    <input class="form-control" type="file" name="upload_files[]" multiple required>
                    <div class="form-text"><?= print_translation('admin_files_upload_hint') ?></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal"><?= print_translation('admin_btn_cancel') ?></button>
                    <button type="submit" class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2"><i class="ci-upload"></i><?= print_translation('admin_files_upload_btn') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="fileFolderModal" tabindex="-1" aria-hidden="true" data-file-folder-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-5 overflow-hidden">
            <form action="<?= base_href('/admin/files/folder/create') ?>" method="post" data-fm-async-form>
                <?= get_csrf_field() ?>
                <input type="hidden" name="dir" value="<?= htmlSC($currentDir) ?>">
                <input type="hidden" name="picker" value="<?= $pickerMode ? '1' : '0' ?>">
                <input type="hidden" name="field" value="<?= htmlSC($pickerField) ?>">
                <input type="hidden" name="q" value="<?= htmlSC($search) ?>">
                <input type="hidden" name="sort" value="<?= htmlSC($sort) ?>">
                <input type="hidden" name="direction" value="<?= htmlSC($direction) ?>">
                <div class="modal-header border-0 pb-0">
                    <h2 class="modal-title fs-5"><?= print_translation('admin_files_folder_label') ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <label class="form-label"><?= print_translation('admin_files_folder_label') ?></label>
                    <input class="form-control" type="text" name="directory_name" required placeholder="<?= htmlSC(return_translation('admin_files_folder_placeholder')) ?>">
                    <div class="form-text"><?= print_translation('admin_files_folder_hint') ?></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal"><?= print_translation('admin_btn_cancel') ?></button>
                    <button type="submit" class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2"><i class="ci-folder-plus"></i><?= print_translation('admin_files_folder_submit') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="fileRenameModal" tabindex="-1" aria-hidden="true" data-file-rename-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-5 overflow-hidden">
            <form action="<?= base_href('/admin/files/rename') ?>" method="post" data-fm-async-form>
                <?= get_csrf_field() ?>
                <input type="hidden" name="dir" value="<?= htmlSC($currentDir) ?>">
                <input type="hidden" name="picker" value="<?= $pickerMode ? '1' : '0' ?>">
                <input type="hidden" name="field" value="<?= htmlSC($pickerField) ?>">
                <input type="hidden" name="q" value="<?= htmlSC($search) ?>">
                <input type="hidden" name="sort" value="<?= htmlSC($sort) ?>">
                <input type="hidden" name="direction" value="<?= htmlSC($direction) ?>">
                <input type="hidden" name="path" value="" data-file-rename-path-input>
                <div class="modal-header border-0 pb-0">
                    <div class="min-w-0">
                        <h2 class="modal-title fs-5"><?= print_translation('admin_files_rename_title') ?></h2>
                        <div class="small text-body-secondary text-truncate" data-file-rename-current-name></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <label class="form-label" for="fileRenameInput"><?= print_translation('admin_files_rename_label') ?></label>
                    <div class="input-group">
                        <input class="form-control" type="text" id="fileRenameInput" name="new_name" value="" required data-file-rename-input>
                        <span class="input-group-text" data-file-rename-extension-wrap>. <span data-file-rename-extension></span></span>
                    </div>
                    <div class="form-text"><?= print_translation('admin_files_rename_hint') ?></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal"><?= print_translation('admin_btn_cancel') ?></button>
                    <button type="submit" class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2"><i class="ci-check"></i><?= print_translation('admin_files_rename_submit') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="filePreviewModal" tabindex="-1" aria-hidden="true" data-file-preview-modal>
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 rounded-5 overflow-hidden">
            <div class="modal-header border-0 pb-0">
                <h2 class="modal-title fs-5 text-truncate" data-file-preview-title><?= print_translation('admin_files_preview_title') ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="rounded-4 bg-body-tertiary d-flex align-items-center justify-content-center p-3" style="min-height: 360px;">
                    <img src="" alt="" class="img-fluid rounded-4" style="max-height: 75vh; width: auto;" data-file-preview-image>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="" target="_blank" rel="noopener noreferrer" data-file-preview-open>
                    <i class="ci-eye"></i><?= print_translation('admin_btn_view') ?>
                </a>
                <button type="button" class="btn btn-dark rounded-pill" data-bs-dismiss="modal"><?= print_translation('admin_btn_cancel') ?></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="fileDeleteModal" tabindex="-1" aria-hidden="true" data-file-delete-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-5 overflow-hidden">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h2 class="modal-title fs-5"><?= print_translation('admin_delete_modal_title') ?></h2>
                    <p class="text-body-secondary small mb-0" data-file-delete-modal-message><?= print_translation('admin_delete_modal_default_message') ?></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <div class="border rounded-4 bg-body-tertiary px-3 py-2 d-none" data-file-delete-modal-item-wrap>
                    <div class="small text-body-secondary mb-1"><?= print_translation('admin_delete_modal_item_label') ?></div>
                    <div class="fw-semibold text-break" data-file-delete-modal-item></div>
                </div>
                <p class="small text-body-secondary mb-0 mt-3"><?= print_translation('admin_delete_modal_hint') ?></p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal"><?= print_translation('admin_btn_cancel') ?></button>
                <button type="button" class="btn btn-danger rounded-pill d-inline-flex align-items-center gap-2" data-file-delete-modal-confirm>
                    <i class="ci-trash"></i><?= print_translation('admin_btn_delete') ?>
                </button>
            </div>
        </div>
    </div>
</div>
