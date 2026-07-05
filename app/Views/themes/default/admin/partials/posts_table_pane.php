<?php
$items = (array)($items ?? []);
$tableKey = (string)($table_key ?? 'published');
$emptyText = (string)($empty_text ?? return_translation('admin_posts_empty'));
$pagination = $pagination ?? null;
$total = (int)($total ?? count($items));
$sort = (string)($sort ?? 'published_at');
$direction = (string)($direction ?? 'desc');
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
$renderStatusBadges = static function (array $badges): string {
    $html = '';

    foreach ($badges as $badge) {
        $class = trim('badge fs-xs rounded-pill ' . (string)($badge['class'] ?? 'text-secondary bg-secondary-subtle'));
        $html .= '<span class="' . htmlSC($class) . '">' . htmlSC((string)($badge['label'] ?? '')) . '</span>';
    }

    return $html;
};
$renderActions = static function (array $post): string {
    ob_start();
    ?>
    <div class="dropdown admin-post-actions-dropdown" data-admin-post-actions-dropdown>
        <button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-display="static" data-bs-boundary="viewport" aria-expanded="false" aria-label="<?= htmlSC(return_translation('admin_posts_col_actions')) ?>">
            <i class="ci-more-vertical"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">
            <a class="dropdown-item d-flex align-items-center gap-2" href="<?= (int)$post['is_published'] === 1 ? base_href('/posts/' . $post['slug']) : base_href('/admin/posts/preview/' . (int)$post['id']) ?>" target="_blank" rel="noopener noreferrer">
                <i class="ci-external-link"></i><span><?= print_translation('admin_btn_view') ?></span>
            </a>
            <a class="dropdown-item d-flex align-items-center gap-2" href="<?= base_href('/admin/posts/edit/' . (int)$post['id']) ?>">
                <i class="ci-edit"></i><span><?= print_translation('admin_btn_edit') ?></span>
            </a>
            <form action="<?= base_href('/admin/posts/toggle-published') ?>" method="post">
                <?= get_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                <?php if ((int)$post['is_published'] === 1): ?>
                    <button class="dropdown-item d-flex align-items-center gap-2" type="submit"><i class="ci-eye-off"></i><span><?= print_translation('admin_btn_unpublish') ?></span></button>
                <?php else: ?>
                    <button class="dropdown-item d-flex align-items-center gap-2" type="submit"><i class="ci-check"></i><span><?= print_translation('admin_btn_publish') ?></span></button>
                <?php endif; ?>
            </form>
            <form action="<?= base_href('/admin/posts/delete') ?>" method="post" data-admin-delete-form data-delete-message="<?= htmlSC(return_translation('admin_confirm_delete_post')) ?>" data-delete-item="<?= htmlSC($post['title']) ?>">
                <?= get_csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
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
                        <col class="admin-posts-table__col-id">
                        <col class="admin-posts-table__col-title">
                        <col class="admin-posts-table__col-details">
                        <col class="admin-posts-table__col-status">
                        <col class="admin-posts-table__col-date">
                        <col class="admin-posts-table__col-actions">
                    </colgroup>
                    <thead class="position-sticky top-0">
                    <tr>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $sortUrl('id') ?>">#<?= $sortIndicator('id') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $sortUrl('title') ?>"><?= print_translation('admin_posts_col_title') ?><?= $sortIndicator('title') ?></a></th>
                        <th scope="col"><?= print_translation('admin_posts_col_details') ?></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $sortUrl('status') ?>"><?= print_translation('admin_posts_col_status') ?><?= $sortIndicator('status') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= $sortUrl('published_at') ?>"><?= print_translation('admin_posts_col_date') ?><?= $sortIndicator('published_at') ?></a></th>
                        <th scope="col"><?= print_translation('admin_posts_col_actions') ?></th>
                    </tr>
                    </thead>
                    <tbody class="table-list">
                    <?php foreach ($items as $post): ?>
                        <?php
                        $authorRole = trim((string)($post['author_role'] ?? 'admin')) ?: 'user';
                        $authorName = trim((string)($post['author_name'] ?? 'Fireball'));
                        $categoryName = (string)($post['category_name'] ?? $post['category'] ?? '-');
                        $statusBadges = (int)$post['is_published'] === 1
                            ? [['label' => return_translation('admin_posts_status_published'), 'class' => 'text-success bg-success-subtle']]
                            : [['label' => return_translation('admin_posts_status_draft'), 'class' => 'text-secondary bg-secondary-subtle']];
                        if ((int)($post['show_on_home'] ?? 0) === 1) {
                            $statusBadges[] = ['label' => return_translation('admin_posts_status_home'), 'class' => 'text-info bg-info-subtle'];
                        }
                        $actionsHtml = $renderActions($post);
                        $mobileCards[] = [
                            'id' => (int)$post['id'],
                            'title' => (string)$post['title'],
                            'slug' => (string)$post['slug'],
                            'category' => $categoryName,
                            'author' => get_user_role_label($authorRole) . ': ' . $authorName,
                            'order' => (int)($post['priority'] ?? 0),
                            'views' => (int)($post['views_count'] ?? 0),
                            'status' => $statusBadges,
                            'published_at' => date('d.m.Y H:i', strtotime($post['published_at'])),
                            'actions' => $actionsHtml,
                        ];
                        ?>
                        <tr data-admin-post-row>
                            <th class="text-nowrap" scope="row"><?= (int)$post['id'] ?></th>
                            <td>
                                <div class="fw-medium"><?= htmlSC($post['title']) ?></div>
                                <div class="text-body-tertiary small"><?= htmlSC($post['slug']) ?></div>
                            </td>
                            <td>
                                <div class="admin-table-meta">
                                    <span title="<?= htmlSC(return_translation('admin_posts_col_category')) ?>"><i class="ci-map-pin"></i><?= htmlSC($categoryName) ?></span>
                                    <span title="<?= htmlSC(return_translation('admin_posts_col_priority')) ?>"><i class="ci-activity"></i><?= (int)($post['priority'] ?? 0) ?></span>
                                    <span title="<?= htmlSC(return_translation('admin_posts_col_views')) ?>"><i class="ci-eye"></i><?= (int)($post['views_count'] ?? 0) ?></span>
                                </div>
                                <div class="small text-body-secondary mt-2">
                                    <?= htmlSC(get_user_role_label($authorRole)) ?>: <?= htmlSC($authorName) ?>
                                </div>
                            </td>
                            <td>
                                <?= $renderStatusBadges($statusBadges) ?>
                            </td>
                            <td class="text-nowrap"><?= date('d.m.Y H:i', strtotime($post['published_at'])) ?></td>
                            <td class="text-nowrap">
                                <?= $actionsHtml ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
            <?php $adminTableContent = ob_get_clean(); ?>
            <?= view()->renderPartial('admin/partials/table', [
                'content' => $adminTableContent,
                'table_class' => 'admin-posts-table',
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
