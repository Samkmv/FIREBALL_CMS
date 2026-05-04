<?php
$sortIndicator = static function (string $column) use ($sort, $direction): string {
    if (($sort ?? '') !== $column) {
        return '';
    }

    return strtolower((string)$direction) === 'asc' ? ' ↑' : ' ↓';
};
?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_contacts_heading'),
    'subtitle' => return_translation('admin_contacts_subtitle'),
    'actions' => '',
]) ?>

    <div class="border rounded-5 p-3 p-md-4">
        <form method="get" class="position-relative mb-3" style="max-width: 280px">
            <input type="hidden" name="sort" value="<?= htmlSC((string)($sort ?? '')) ?>">
            <input type="hidden" name="direction" value="<?= htmlSC((string)($direction ?? '')) ?>">
            <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3"></i>
            <input type="search" name="q" value="<?= htmlSC((string)($search ?? '')) ?>" class="table-search form-control form-icon-start" placeholder="<?= print_translation('admin_table_search_placeholder') ?>">
        </form>

        <?php if (empty($requests)): ?>
            <p class="text-body-secondary mb-0"><?= ($search ?? '') !== '' ? return_translation('admin_table_empty_search') : return_translation('admin_contacts_empty') ?></p>
        <?php else: ?>
            <div class="table-responsive overflow-auto admin-table-scroll">
                <table class="table align-middle mb-0">
                    <thead class="position-sticky top-0">
                    <tr>
                        <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('id', (string)$sort, (string)$direction) ?>">#<?= $sortIndicator('id') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('name', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_contacts_col_name') ?><?= $sortIndicator('name') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('email', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_contacts_col_email') ?><?= $sortIndicator('email') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('subject', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_contacts_col_subject') ?><?= $sortIndicator('subject') ?></a></th>
                        <th scope="col"><?= print_translation('admin_contacts_col_message') ?></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('status', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_contacts_col_status') ?><?= $sortIndicator('status') ?></a></th>
                        <th scope="col"><a class="btn fs-base fw-semibold text-body-emphasis text-decoration-none p-0" href="<?= admin_table_sort_url('created_at', (string)$sort, (string)$direction) ?>"><?= print_translation('admin_contacts_col_date') ?><?= $sortIndicator('created_at') ?></a></th>
                        <th scope="col"><?= print_translation('admin_posts_col_actions') ?></th>
                    </tr>
                    </thead>
                    <tbody class="table-list">
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <th class="text-nowrap" scope="row"><?= (int)$request['id'] ?></th>
                            <td class="fw-medium"><?= htmlSC($request['name']) ?></td>
                            <td><a href="mailto:<?= htmlSC($request['email']) ?>"><?= htmlSC($request['email']) ?></a></td>
                            <td><?= htmlSC($request['subject']) ?></td>
                            <td style="min-width: 320px;">
                                <div class="small lh-base"><?= nl2br(htmlSC($request['message'])) ?></div>
                            </td>
                            <td>
                                <?php if ((int)$request['is_viewed'] === 1): ?>
                                    <span class="badge fs-xs text-secondary bg-secondary-subtle rounded-pill"><?= print_translation('admin_contacts_status_viewed') ?></span>
                                <?php else: ?>
                                    <span class="badge fs-xs text-success bg-success-subtle rounded-pill"><?= print_translation('admin_contacts_status_new') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap"><?= date('d.m.Y H:i', strtotime($request['created_at'])) ?></td>
                            <td class="text-nowrap">
                                <form
                                    action="<?= base_href('/admin/contact-requests/delete') ?>"
                                    method="post"
                                    class="d-inline-flex"
                                    data-admin-delete-form
                                    data-delete-message="<?= htmlSC(return_translation('admin_confirm_delete_contact')) ?>"
                                    data-delete-item="<?= htmlSC($request['name'] . ' <' . $request['email'] . '>') ?>"
                                >
                                    <?= get_csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int)$request['id'] ?>">
                                    <button
                                        class="btn btn-sm btn-outline-danger btn-icon rounded-circle"
                                        type="submit"
                                        aria-label="<?= htmlSC(return_translation('admin_btn_delete')) ?>"
                                        title="<?= htmlSC(return_translation('admin_btn_delete')) ?>"
                                        data-bs-toggle="tooltip"
                                    ><i class="ci-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex align-items-center justify-content-between pt-4 gap-3">
                <div class="fs-sm">
                    <?= print_translation('admin_table_showing') ?>
                    <span class="fw-semibold"><?= count($requests) ?></span>
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
<?= view()->renderPartial('admin/shell_close') ?>
