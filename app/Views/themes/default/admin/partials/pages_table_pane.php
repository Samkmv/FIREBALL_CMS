<?php
$items = (array)($items ?? []);
$tableKey = (string)($table_key ?? 'published');
$emptyText = (string)($empty_text ?? return_translation('admin_pages_empty'));
$pagination = $pagination ?? null;
$total = (int)($total ?? count($items));
$sort = (string)($sort ?? 'menu_order');
$direction = (string)($direction ?? 'asc');
$status = $tableKey === 'drafts' ? 'drafts' : 'published';
$sortIndicator = static function (string $column) use ($sort, $direction): string {
    if ($sort !== $column) {
        return '';
    }

    return strtolower($direction) === 'asc' ? ' ↑' : ' ↓';
};
$sortUrl = static function (string $column) use ($sort, $direction, $status): string {
    $nextDirection = ($sort === $column && strtolower($direction) === 'asc') ? 'desc' : 'asc';

    return current_url_with_query([
        'sort' => $column,
        'direction' => $nextDirection,
        'status' => $status,
        'page' => 1,
        'published_page' => null,
        'draft_page' => null,
        'q' => null,
    ]);
};
$pageVisibilityLabel = static function (array $page): string {
    return match (true) {
        (int)$page['show_in_header'] === 1 && (int)$page['show_in_footer'] === 1 => return_translation('admin_page_visibility_both'),
        (int)$page['show_in_header'] === 1 => return_translation('admin_page_visibility_header'),
        (int)$page['show_in_footer'] === 1 => return_translation('admin_page_visibility_footer'),
        default => return_translation('admin_page_visibility_none'),
    };
};
$renderStatusBadges = static function (array $badges): string {
    $html = '';

    foreach ($badges as $badge) {
        $class = trim('badge fs-xs rounded-pill ' . (string)($badge['class'] ?? 'text-secondary bg-secondary-subtle'));
        $html .= '<span class="' . htmlSC($class) . '">' . htmlSC((string)($badge['label'] ?? '')) . '</span>';
    }

    return $html;
};
$renderActions = static function (array $page): string {
    ob_start();
    ?>
    <div class="dropdown admin-post-actions-dropdown" data-admin-post-actions-dropdown>
        <button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-display="static" data-bs-boundary="viewport" aria-expanded="false" aria-label="<?= htmlSC(return_translation('admin_posts_col_actions')) ?>">
            <i class="ci-more-vertical"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">
            <a class="dropdown-item d-flex align-items-center gap-2" href="<?= (int)$page['is_published'] === 1 ? base_href('/' . $page['slug']) : base_href('/admin/pages/preview/' . (int)$page['id']) ?>" target="_blank" rel="noopener noreferrer">
                <i class="ci-external-link"></i><span><?= print_translation('admin_btn_view') ?></span>
            </a>
            <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/admin/pages/edit/' . (int)$page['id']) ?>">
                <i class="ci-edit"></i><span><?= print_translation('admin_btn_edit') ?></span>
            </a>
            <form action="<?= base_href('/admin/pages/toggle-published') ?>" method="post">
                <?= get_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$page['id'] ?>">
                <?php if ((int)$page['is_published'] === 1): ?>
                    <button class="dropdown-item d-flex align-items-center gap-2" type="submit"><i class="ci-eye-off"></i><span><?= print_translation('admin_btn_unpublish') ?></span></button>
                <?php else: ?>
                    <button class="dropdown-item d-flex align-items-center gap-2" type="submit"><i class="ci-check"></i><span><?= print_translation('admin_btn_publish') ?></span></button>
                <?php endif; ?>
            </form>
            <form action="<?= base_href('/admin/pages/delete') ?>" method="post" data-admin-delete-form data-delete-message="<?= htmlSC(return_translation('admin_confirm_delete_page')) ?>" data-delete-item="<?= htmlSC($page['title']) ?>">
                <?= get_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$page['id'] ?>">
                <button class="dropdown-item d-flex align-items-center gap-2 text-danger" type="submit"><i class="ci-trash"></i><span><?= print_translation('admin_btn_delete') ?></span></button>
            </form>
        </div>
    </div>
    <?php

    return trim((string)ob_get_clean());
};
$mobileCards = [];
?>
<div data-admin-posts-pane-content="<?= htmlSC($tableKey) ?>">
    <div data-admin-posts-table-shell="<?= htmlSC($tableKey) ?>">
        <?php if (empty($items)): ?>
            <div class="admin-table-state" data-admin-posts-empty><?= print_translation('admin_table_empty') ?></div>
        <?php else: ?>
            <?php ob_start(); ?>
                <colgroup>
                        <col class="admin-pages-table__col-id">
                        <col class="admin-pages-table__col-title">
                        <col class="admin-pages-table__col-details">
                        <col class="admin-pages-table__col-status">
                        <col class="admin-pages-table__col-updated">
                        <col class="admin-pages-table__col-actions">
                    </colgroup>
                    <thead class="position-sticky top-0">
                    <tr>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $sortUrl('id') ?>">#<?= $sortIndicator('id') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $sortUrl('title') ?>"><?= print_translation('admin_pages_col_title') ?><?= $sortIndicator('title') ?></a></th>
                        <th scope="col"><?= print_translation('admin_pages_col_details') ?></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $sortUrl('status') ?>"><?= print_translation('admin_posts_col_status') ?><?= $sortIndicator('status') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $sortUrl('updated_at') ?>"><?= print_translation('admin_pages_col_updated_at') ?><?= $sortIndicator('updated_at') ?></a></th>
                        <th scope="col"><?= print_translation('admin_posts_col_actions') ?></th>
                    </tr>
                    </thead>
                    <tbody class="table-list">
                    <?php foreach ($items as $page): ?>
                        <?php
                        $visibilityLabel = $pageVisibilityLabel($page);
                        $statusBadges = (int)$page['is_published'] === 1
                            ? [['label' => return_translation('admin_posts_status_published'), 'class' => 'text-success bg-success-subtle']]
                            : [['label' => return_translation('admin_posts_status_draft'), 'class' => 'text-secondary bg-secondary-subtle']];
                        if ((int)($page['show_in_legal_information'] ?? 0) === 1) {
                            $statusBadges[] = ['label' => return_translation('footer_heading_legal_information'), 'class' => 'text-info bg-info-subtle'];
                        }
                        $actionsHtml = $renderActions($page);
                        $mobileCards[] = [
                            'id' => (int)$page['id'],
                            'title' => (string)$page['title'],
                            'slug' => '/' . (string)$page['slug'],
                            'order' => (int)$page['menu_order'],
                            'status' => $statusBadges,
                            'published_at' => $page['updated_at'] !== '' ? date('d.m.Y H:i', strtotime($page['updated_at'])) : '-',
                            'published_at_label' => return_translation('admin_pages_col_updated_at'),
                            'actions' => $actionsHtml,
                            'extra_fields' => [
                                [
                                    'label' => return_translation('admin_pages_col_menu_title'),
                                    'value' => (string)$page['menu_label'],
                                ],
                                [
                                    'label' => return_translation('admin_page_field_menu_visibility'),
                                    'value' => $visibilityLabel,
                                ],
                            ],
                        ];
                        ?>
                        <tr data-admin-post-row>
                            <th class="text-nowrap" scope="row"><?= (int)$page['id'] ?></th>
                            <td>
                                <div class="fw-medium"><?= htmlSC($page['title']) ?></div>
                                <div class="text-body-tertiary small"><?= htmlSC($visibilityLabel) ?></div>
                                <div class="text-body-tertiary small">/<?= htmlSC($page['slug']) ?></div>
                                <?php if ((int)($page['show_in_legal_information'] ?? 0) === 1): ?>
                                    <span class="badge fs-xs text-info bg-info-subtle rounded-pill mt-1"><?= print_translation('footer_heading_legal_information') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="admin-table-meta">
                                    <span title="<?= htmlSC(return_translation('admin_pages_col_menu_title')) ?>"><i class="ci-menu"></i><?= htmlSC($page['menu_label']) ?></span>
                                    <span title="<?= htmlSC(return_translation('admin_pages_col_order')) ?>"><i class="ci-sort"></i><?= (int)$page['menu_order'] ?></span>
                                </div>
                            </td>
                            <td>
                                <?= $renderStatusBadges(array_slice($statusBadges, 0, 1)) ?>
                            </td>
                            <td class="text-nowrap"><?= $page['updated_at'] !== '' ? date('d.m.Y H:i', strtotime($page['updated_at'])) : '-' ?></td>
                            <td class="text-nowrap">
                                <?= $actionsHtml ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
            <?php $adminTableContent = ob_get_clean(); ?>
            <?= view()->renderPartial('admin/partials/table', [
                'content' => $adminTableContent,
                'table_class' => 'admin-pages-table',
                'mobile_cards' => $mobileCards,
            ]) ?>
        <?php endif; ?>
    </div>
    <?= view()->renderPartial('admin/partials/table_footer', [
        'visible' => count($items),
        'total' => $total,
        'pagination' => $pagination,
        'visible_attributes' => ['data-admin-posts-visible' => $tableKey],
        'total_attributes' => ['data-admin-posts-total' => $tableKey],
        'pagination_attributes' => ['data-admin-posts-pagination' => $tableKey],
    ]) ?>
</div>
