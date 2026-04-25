<?php
$sortIndicator = static function (string $column) use ($sort, $direction): string {
    if (($sort ?? '') !== $column) {
        return '';
    }

    return strtolower((string)$direction) === 'asc' ? ' ↑' : ' ↓';
};
?>

<section class="container py-5 my-2 my-md-4 my-lg-5">
    <div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1"><?= print_translation('admin_posts_heading') ?></h1>
            <p class="text-body-secondary mb-0"><?= print_translation('admin_posts_subtitle') ?></p>
        </div>
        <a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin/posts/create') ?>"><i class="ci-plus"></i><?= print_translation('admin_posts_create') ?></a>
    </div>

    <?= view()->renderPartial('admin/nav') ?>

    <div class="border rounded-5 p-3 p-md-4">
        <form method="get" class="position-relative mb-3" style="max-width: 280px">
            <input type="hidden" name="sort" value="<?= htmlSC((string)($sort ?? '')) ?>">
            <input type="hidden" name="direction" value="<?= htmlSC((string)($direction ?? '')) ?>">
            <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3"></i>
            <input type="search" name="q" value="<?= htmlSC((string)($search ?? '')) ?>" class="table-search form-control form-icon-start" placeholder="<?= print_translation('admin_table_search_placeholder') ?>">
        </form>

        <?php if (empty($posts)): ?>
            <p class="text-body-secondary mb-0"><?= ($search ?? '') !== '' ? return_translation('admin_table_empty_search') : return_translation('admin_posts_empty') ?></p>
        <?php else: ?>
            <div class="table-responsive overflow-auto admin-table-scroll">
                <table class="table align-middle mb-0">
                    <thead class="position-sticky top-0">
                    <tr>
                        <th scope="col">
                            <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('id', (string)$sort, (string)$direction) ?>">#<?= $sortIndicator('id') ?></a>
                        </th>
                        <th scope="col">
                            <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('title', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_posts_col_title') ?><?= $sortIndicator('title') ?></a>
                        </th>
                        <th scope="col">
                            <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('category', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_posts_col_category') ?><?= $sortIndicator('category') ?></a>
                        </th>
                        <th scope="col">
                            <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('author', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_posts_col_author') ?><?= $sortIndicator('author') ?></a>
                        </th>
                        <th scope="col">
                            <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('views', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_posts_col_views') ?><?= $sortIndicator('views') ?></a>
                        </th>
                        <th scope="col">
                            <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('status', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_posts_col_status') ?><?= $sortIndicator('status') ?></a>
                        </th>
                        <th scope="col">
                            <a class="btn fs-base fw-semibold text-dark-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('published_at', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_posts_col_date') ?><?= $sortIndicator('published_at') ?></a>
                        </th>
                        <th scope="col"><?= print_translation('admin_posts_col_actions') ?></th>
                    </tr>
                    </thead>
                    <tbody class="table-list">
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <th class="text-nowrap" scope="row"><?= (int)$post['id'] ?></th>
                            <td>
                                <div class="fw-medium"><?= htmlSC($post['title']) ?></div>
                                <div class="text-body-tertiary small"><?= htmlSC($post['slug']) ?></div>
                            </td>
                            <td><?= htmlSC($post['category_name'] ?? $post['category'] ?? '-') ?></td>
                            <td>
                                <?php
                                $authorRole = trim((string)($post['author_role'] ?? 'admin')) ?: 'user';
                                $authorName = trim((string)($post['author_name'] ?? 'Fireball'));
                                ?>
                                <div class="fw-medium"><?= htmlSC(get_user_role_label($authorRole)) ?></div>
                                <div class="text-body-tertiary small"><?= htmlSC($authorName) ?></div>
                            </td>
                            <td class="text-nowrap"><?= (int)($post['views_count'] ?? 0) ?></td>
                            <td>
                                <?php if ((int)$post['is_published'] === 1): ?>
                                    <span class="badge fs-xs text-success bg-success-subtle rounded-pill"><?= print_translation('admin_posts_status_published') ?></span>
                                <?php else: ?>
                                    <span class="badge fs-xs text-secondary bg-secondary-subtle rounded-pill"><?= print_translation('admin_posts_status_draft') ?></span>
                                <?php endif; ?>
                                <?php if ((int)($post['show_on_home'] ?? 0) === 1): ?>
                                    <span class="badge fs-xs text-info bg-info-subtle rounded-pill"><?= print_translation('admin_posts_status_home') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= date('d.m.Y H:i', strtotime($post['published_at'])) ?></td>
                            <td class="text-nowrap">
                                <div class="d-inline-flex flex-nowrap align-items-center gap-2">
                                    <?php if ((int)$post['is_published'] === 1): ?>
                                        <a
                                            class="btn btn-sm btn-outline-primary btn-icon rounded-circle"
                                            href="<?= base_href('/posts/' . $post['slug']) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            aria-label="<?= htmlSC(return_translation('admin_btn_view')) ?>"
                                            title="<?= htmlSC(return_translation('admin_btn_view')) ?>"
                                            data-bs-toggle="tooltip"
                                        >
                                            <i class="ci-eye"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a
                                        class="btn btn-sm btn-outline-secondary btn-icon rounded-circle"
                                        href="<?= base_href('/admin/posts/edit/' . (int)$post['id']) ?>"
                                        aria-label="<?= htmlSC(return_translation('admin_btn_edit')) ?>"
                                        title="<?= htmlSC(return_translation('admin_btn_edit')) ?>"
                                        data-bs-toggle="tooltip"
                                    >
                                        <i class="ci-edit"></i>
                                    </a>
                                    <form
                                        action="<?= base_href('/admin/posts/delete') ?>"
                                        method="post"
                                        data-admin-delete-form
                                        data-delete-message="<?= htmlSC(return_translation('admin_confirm_delete_post')) ?>"
                                        data-delete-item="<?= htmlSC($post['title']) ?>"
                                    >
                                        <?= get_csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int)$post['id'] ?>">
                                        <button
                                            class="btn btn-sm btn-outline-danger btn-icon rounded-circle"
                                            type="submit"
                                            aria-label="<?= htmlSC(return_translation('admin_btn_delete')) ?>"
                                            title="<?= htmlSC(return_translation('admin_btn_delete')) ?>"
                                            data-bs-toggle="tooltip"
                                        ><i class="ci-trash"></i></button>
                                    </form>
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
                    <span class="fw-semibold"><?= count($posts) ?></span>
                    <?= print_translation('admin_table_of') ?>
                    <span class="fw-semibold"><?= (int)$total ?></span>
                    <span class="d-none d-sm-inline"><?= print_translation('admin_table_results') ?></span>
                </div>
                <?php if ((int)$total > 15): ?>
                    <nav aria-label="Pagination">
                        <?= $pagination ?>
                    </nav>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
