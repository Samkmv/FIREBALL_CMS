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
$sortIndicator = static function (string $column) use ($sort, $direction): string {
    if ($sort !== $column) {
        return '';
    }

    return strtolower($direction) === 'asc' ? ' ↑' : ' ↓';
};
$buildManagerUrl = static function (?string $dir = null) use ($pickerMode, $pickerField, $sort, $direction): string {
    $params = [];

    if ($dir !== null && $dir !== '') {
        $params['dir'] = $dir;
    }

    if ($pickerMode) {
        $params['picker'] = '1';
    }

    if ($pickerField !== '') {
        $params['field'] = $pickerField;
    }

    if ($sort !== '') {
        $params['sort'] = $sort;
    }

    if ($direction !== '') {
        $params['direction'] = $direction;
    }

    $query = $params ? ('?' . http_build_query($params)) : '';

    return base_href('/admin/files' . $query);
};
?>

<section class="container py-5 my-2 my-md-4 my-lg-5" data-file-manager-page>
    <div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= print_translation('admin_files_heading') ?></h1>
            <p class="text-body-secondary mb-0"><?= print_translation('admin_files_subtitle') ?></p>
        </div>
    </div>

    <?= view()->renderPartial('admin/nav') ?>

    <div class="border rounded-5 p-3 p-md-4 mb-4">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                <div class="fw-semibold"><?= print_translation('admin_files_root') ?></div>
                <div class="small text-body-secondary text-break">
                    /<?= htmlSC($currentDir !== '' ? $currentDir : return_translation('admin_files_root')) ?>
                </div>
            </div>
            <?php if ($parentDir !== null): ?>
                <div class="flex-shrink-0">
                    <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= $buildManagerUrl($parentDir) ?>">
                        <i class="ci-arrow-up"></i><?= print_translation('admin_files_up') ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 pt-3">
            <?php foreach (($manager['breadcrumbs'] ?? []) as $index => $crumb): ?>
                <?php if ($index > 0): ?>
                    <span class="text-body-secondary">/</span>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="<?= $buildManagerUrl($crumb['dir']) ?>">
                    <i class="ci-folder"></i>
                    <?= htmlSC($crumb['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="border rounded-5 p-3 p-md-4 mb-4">
        <div class="row g-4">
            <div class="col-md-7">
                <form action="<?= base_href('/admin/files/upload') ?>" method="post" enctype="multipart/form-data">
                    <?= get_csrf_field() ?>
                    <input type="hidden" name="dir" value="<?= htmlSC($currentDir) ?>">
                    <input type="hidden" name="picker" value="<?= $pickerMode ? '1' : '0' ?>">
                    <input type="hidden" name="field" value="<?= htmlSC($pickerField) ?>">
                    <input type="hidden" name="q" value="<?= htmlSC($search) ?>">
                    <input type="hidden" name="sort" value="<?= htmlSC($sort) ?>">
                    <input type="hidden" name="direction" value="<?= htmlSC($direction) ?>">
                    <div>
                        <label class="form-label"><?= print_translation('admin_files_upload_label') ?></label>
                        <div class="input-group">
                            <input class="form-control" type="file" name="upload_file" required>
                            <button class="btn btn-dark rounded-end-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-upload"></i><?= print_translation('admin_files_upload_btn') ?></button>
                        </div>
                        <div class="form-text"><?= print_translation('admin_files_upload_hint') ?></div>
                    </div>
                </form>
            </div>
            <div class="col-md-5">
                <form action="<?= base_href('/admin/files/folder/create') ?>" method="post">
                    <?= get_csrf_field() ?>
                    <input type="hidden" name="dir" value="<?= htmlSC($currentDir) ?>">
                    <input type="hidden" name="picker" value="<?= $pickerMode ? '1' : '0' ?>">
                    <input type="hidden" name="field" value="<?= htmlSC($pickerField) ?>">
                    <input type="hidden" name="q" value="<?= htmlSC($search) ?>">
                    <input type="hidden" name="sort" value="<?= htmlSC($sort) ?>">
                    <input type="hidden" name="direction" value="<?= htmlSC($direction) ?>">
                    <div>
                        <label class="form-label"><?= print_translation('admin_files_folder_label') ?></label>
                        <div class="input-group">
                            <input class="form-control" type="text" name="directory_name" required placeholder="<?= htmlSC(return_translation('admin_files_folder_placeholder')) ?>">
                            <button class="btn btn-outline-dark rounded-end-pill d-inline-flex align-items-center gap-2" type="submit"><i class="ci-folder-plus"></i><?= print_translation('admin_files_folder_submit') ?></button>
                        </div>
                        <div class="form-text"><?= print_translation('admin_files_folder_hint') ?></div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="border rounded-5 p-3 p-md-4">
        <form method="get" class="position-relative mb-3" style="max-width: 280px">
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
            <input type="search" name="q" value="<?= htmlSC($search) ?>" class="table-search form-control form-icon-start" placeholder="<?= print_translation('admin_table_search_placeholder') ?>">
        </form>

        <?php if (empty($items)): ?>
            <p class="text-body-secondary mb-0"><?= $search !== '' ? return_translation('admin_table_empty_search') : return_translation('admin_files_empty') ?></p>
        <?php else: ?>
            <div class="table-responsive overflow-auto admin-table-scroll">
                <table class="table align-middle mb-0">
                    <thead class="position-sticky top-0">
                    <tr>
                        <th scope="col">
                            <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('name', $sort, $direction) ?>">
                                <?= print_translation('admin_files_col_name') ?><?= $sortIndicator('name') ?>
                            </a>
                        </th>
                        <th scope="col">
                            <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('type', $sort, $direction) ?>">
                                <?= print_translation('admin_files_col_type') ?><?= $sortIndicator('type') ?>
                            </a>
                        </th>
                        <th scope="col">
                            <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('size', $sort, $direction) ?>">
                                <?= print_translation('admin_files_size') ?><?= $sortIndicator('size') ?>
                            </a>
                        </th>
                        <th scope="col">
                            <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('modified', $sort, $direction) ?>">
                                <?= print_translation('admin_files_modified') ?><?= $sortIndicator('modified') ?>
                            </a>
                        </th>
                        <th scope="col"><?= print_translation('admin_posts_col_actions') ?></th>
                    </tr>
                    </thead>
                    <tbody class="table-list">
                    <?php foreach ($items as $item): ?>
                        <?php $isDirectory = ($item['type'] ?? '') === 'directory'; ?>
                        <tr>
                            <td>
                                <?php if ($isDirectory): ?>
                                    <a class="d-flex align-items-center gap-3 min-w-0 text-decoration-none text-reset" href="<?= $buildManagerUrl($item['relative_path']) ?>">
                                        <div class="rounded-3 border bg-body-tertiary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 56px; height: 56px;">
                                            <i class="ci-folder fs-3 text-body-secondary"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="fw-medium"><?= htmlSC($item['name']) ?></div>
                                            <div class="text-body-tertiary small text-break"><?= htmlSC($item['public_path']) ?></div>
                                        </div>
                                    </a>
                                <?php else: ?>
                                    <div class="d-flex align-items-center gap-3 min-w-0">
                                        <?php if (!empty($item['is_image'])): ?>
                                            <button
                                                type="button"
                                                class="btn p-0 border-0 bg-transparent flex-shrink-0"
                                                data-file-preview
                                                data-file-preview-url="<?= htmlSC($item['url']) ?>"
                                                data-file-preview-name="<?= htmlSC($item['name']) ?>"
                                                style="cursor: zoom-in;"
                                            >
                                                <img src="<?= htmlSC($item['url']) ?>" alt="<?= htmlSC($item['name']) ?>" class="rounded-3 border object-fit-cover" style="width: 56px; height: 56px;">
                                            </button>
                                        <?php else: ?>
                                            <div class="rounded-3 border bg-body-tertiary d-flex align-items-center justify-content-center flex-shrink-0" style="width: 56px; height: 56px;">
                                                <i class="ci-file fs-3 text-body-secondary"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="min-w-0">
                                            <div class="fw-medium"><?= htmlSC($item['name']) ?></div>
                                        </div>
                                        <div class="text-body-tertiary small text-break"><?= htmlSC($item['public_path']) ?></div>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <?php if ($isDirectory): ?>
                                    <span class="badge fs-xs text-secondary bg-secondary-subtle rounded-pill"><?= print_translation('admin_files_type_directory') ?></span>
                                <?php else: ?>
                                    <span class="badge fs-xs text-dark bg-light rounded-pill"><?= print_translation('admin_files_type_file') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap text-body-secondary small"><?= $isDirectory ? '-' : htmlSC((string)$item['size']) ?></td>
                            <td class="text-nowrap text-body-secondary small"><?= htmlSC((string)$item['modified_at']) ?></td>
                            <td class="text-nowrap">
                                <div class="d-inline-flex flex-nowrap align-items-center gap-2">
                                    <?php if ($isDirectory): ?>
                                        <a
                                            class="btn btn-sm btn-outline-secondary btn-icon rounded-circle"
                                            href="<?= $buildManagerUrl($item['relative_path']) ?>"
                                            aria-label="<?= htmlSC(return_translation('admin_btn_open')) ?>"
                                            title="<?= htmlSC(return_translation('admin_btn_open')) ?>"
                                            data-bs-toggle="tooltip"
                                        ><i class="ci-folder"></i></a>
                                        <?php if (!empty($item['can_delete'])): ?>
                                            <form
                                                action="<?= base_href('/admin/files/folder/delete') ?>"
                                                method="post"
                                                class="m-0"
                                                data-admin-delete-form
                                                data-delete-message="<?= htmlSC(return_translation('admin_files_folder_delete_confirm')) ?>"
                                                data-delete-item="<?= htmlSC($item['name']) ?>"
                                            >
                                                <?= get_csrf_field() ?>
                                                <input type="hidden" name="dir" value="<?= htmlSC($currentDir) ?>">
                                                <input type="hidden" name="picker" value="<?= $pickerMode ? '1' : '0' ?>">
                                                <input type="hidden" name="field" value="<?= htmlSC($pickerField) ?>">
                                                <input type="hidden" name="q" value="<?= htmlSC($search) ?>">
                                                <input type="hidden" name="sort" value="<?= htmlSC($sort) ?>">
                                                <input type="hidden" name="direction" value="<?= htmlSC($direction) ?>">
                                                <input type="hidden" name="path" value="<?= htmlSC($item['relative_path']) ?>">
                                                <button
                                                    class="btn btn-sm btn-outline-danger btn-icon rounded-circle"
                                                    type="submit"
                                                    aria-label="<?= htmlSC(return_translation('admin_btn_delete')) ?>"
                                                    title="<?= htmlSC(return_translation('admin_btn_delete')) ?>"
                                                    data-bs-toggle="tooltip"
                                                ><i class="ci-trash"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <button
                                                class="btn btn-sm btn-outline-secondary btn-icon rounded-circle disabled"
                                                type="button"
                                                aria-label="<?= htmlSC(return_translation('admin_files_folder_delete_protected')) ?>"
                                                title="<?= htmlSC(return_translation('admin_files_folder_delete_protected')) ?>"
                                                data-bs-toggle="tooltip"
                                            ><i class="ci-lock"></i></button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a
                                            class="btn btn-sm btn-outline-secondary btn-icon rounded-circle"
                                            href="<?= htmlSC($item['url']) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            aria-label="<?= htmlSC(return_translation('admin_btn_view')) ?>"
                                            title="<?= htmlSC(return_translation('admin_btn_view')) ?>"
                                            data-bs-toggle="tooltip"
                                        ><i class="ci-eye"></i></a>
                                        <button
                                            class="btn btn-sm btn-outline-secondary btn-icon rounded-circle"
                                            type="button"
                                            data-file-rename-open
                                            data-file-rename-path="<?= htmlSC($item['relative_path']) ?>"
                                            data-file-rename-name="<?= htmlSC(pathinfo($item['name'], PATHINFO_FILENAME)) ?>"
                                            data-file-rename-full-name="<?= htmlSC($item['name']) ?>"
                                            data-file-rename-extension="<?= htmlSC($item['extension']) ?>"
                                            aria-label="<?= htmlSC(return_translation('admin_files_rename')) ?>"
                                            title="<?= htmlSC(return_translation('admin_files_rename')) ?>"
                                            data-bs-toggle="tooltip"
                                        ><i class="ci-edit"></i></button>
                                        <?php if ($pickerMode && $pickerField !== ''): ?>
                                            <button
                                                class="btn btn-sm btn-dark btn-icon rounded-circle"
                                                type="button"
                                                data-file-select
                                                data-file-select-field="<?= htmlSC($pickerField) ?>"
                                                data-file-select-value="<?= htmlSC($item['public_path']) ?>"
                                                aria-label="<?= htmlSC(return_translation('admin_files_select')) ?>"
                                                title="<?= htmlSC(return_translation('admin_files_select')) ?>"
                                                data-bs-toggle="tooltip"
                                            ><i class="ci-check"></i></button>
                                        <?php endif; ?>
                                        <form
                                            action="<?= base_href('/admin/files/delete') ?>"
                                            method="post"
                                            class="m-0"
                                            data-admin-delete-form
                                            data-delete-message="<?= htmlSC(return_translation('admin_files_delete_confirm')) ?>"
                                            data-delete-item="<?= htmlSC($item['name']) ?>"
                                        >
                                            <?= get_csrf_field() ?>
                                            <input type="hidden" name="dir" value="<?= htmlSC($currentDir) ?>">
                                            <input type="hidden" name="picker" value="<?= $pickerMode ? '1' : '0' ?>">
                                            <input type="hidden" name="field" value="<?= htmlSC($pickerField) ?>">
                                            <input type="hidden" name="q" value="<?= htmlSC($search) ?>">
                                            <input type="hidden" name="sort" value="<?= htmlSC($sort) ?>">
                                            <input type="hidden" name="direction" value="<?= htmlSC($direction) ?>">
                                            <input type="hidden" name="path" value="<?= htmlSC($item['relative_path']) ?>">
                                            <button
                                                class="btn btn-sm btn-outline-danger btn-icon rounded-circle"
                                                type="submit"
                                                aria-label="<?= htmlSC(return_translation('admin_btn_delete')) ?>"
                                                title="<?= htmlSC(return_translation('admin_btn_delete')) ?>"
                                                data-bs-toggle="tooltip"
                                            ><i class="ci-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex align-items-center justify-content-between pt-4 gap-3">
                <div class="fs-sm">
                    <?= print_translation('admin_table_showing') ?>
                    <span class="fw-semibold"><?= count($items) ?></span>
                    <?= print_translation('admin_table_of') ?>
                    <span class="fw-semibold"><?= $total ?></span>
                    <span class="d-none d-sm-inline"><?= print_translation('admin_table_results') ?></span>
                </div>
                <?php if ($total > 15): ?>
                    <nav aria-label="Pagination">
                        <?= $manager['pagination'] ?>
                    </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="fileRenameModal" tabindex="-1" aria-hidden="true" data-file-rename-modal>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-5 overflow-hidden">
                <form action="<?= base_href('/admin/files/rename') ?>" method="post">
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
                            <span class="input-group-text" data-file-rename-extension-wrap>
                                .<span data-file-rename-extension></span>
                            </span>
                        </div>
                        <div class="form-text"><?= print_translation('admin_files_rename_hint') ?></div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" data-bs-dismiss="modal"><i class="ci-close"></i><?= print_translation('admin_btn_cancel') ?></button>
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
                    <div class="min-w-0">
                        <h2 class="modal-title fs-5 text-truncate" data-file-preview-title><?= print_translation('admin_files_preview_title') ?></h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <div class="rounded-4 bg-body-tertiary d-flex align-items-center justify-content-center p-3" style="min-height: 360px;">
                        <img
                            src=""
                            alt=""
                            class="img-fluid rounded-4"
                            style="max-height: 75vh; width: auto;"
                            data-file-preview-image
                        >
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <a class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2" href="" target="_blank" rel="noopener noreferrer" data-file-preview-open>
                        <i class="ci-eye"></i><?= print_translation('admin_btn_view') ?>
                    </a>
                    <button type="button" class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" data-bs-dismiss="modal"><i class="ci-close"></i><?= print_translation('admin_btn_cancel') ?></button>
                </div>
            </div>
        </div>
    </div>
</section>
