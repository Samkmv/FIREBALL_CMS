<?php
$publishedPosts = (array)($published_posts ?? []);
$draftPosts = (array)($draft_posts ?? []);
$publishedPagination = $published_pagination ?? null;
$draftPagination = $draft_pagination ?? null;
$publishedTotal = (int)($published_total ?? count($publishedPosts));
$draftTotal = (int)($draft_total ?? count($draftPosts));
$searchValue = (string)($search ?? '');
$activeStatus = (string)($active_status ?? 'published');
$emptyText = $searchValue !== '' ? return_translation('admin_table_empty_search') : return_translation('admin_posts_empty');
?>
<?php ob_start(); ?>
<a class="btn btn-dark rounded-pill d-inline-flex align-items-center gap-2" href="<?= base_href('/admin/posts/create') ?>"><i class="ci-plus"></i><?= print_translation('admin_posts_create') ?></a>
<?php $adminPageActions = ob_get_clean(); ?>
<?= view()->renderPartial('admin/shell_open', [
    'title' => return_translation('admin_posts_heading'),
    'subtitle' => return_translation('admin_posts_subtitle'),
    'actions' => $adminPageActions,
]) ?>

    <div class="border rounded-5 p-3 p-md-4 admin-table-card" data-admin-table data-ajax-table="posts" data-ajax-table-format="json" data-admin-posts-tabs>
        <form method="get" action="<?= htmlSC(base_href('/admin/posts')) ?>" class="position-relative mb-3" style="max-width: 320px" data-admin-table-form>
            <input type="hidden" name="sort" value="<?= htmlSC((string)($sort ?? '')) ?>">
            <input type="hidden" name="direction" value="<?= htmlSC((string)($direction ?? '')) ?>">
            <input type="hidden" name="status" value="<?= htmlSC($activeStatus) ?>">
            <input type="hidden" name="page" value="1">
            <i class="ci-search position-absolute top-50 start-0 translate-middle-y ms-3"></i>
            <input
                type="search"
                name="search"
                value="<?= htmlSC($searchValue) ?>"
                class="table-search form-control form-icon-start"
                placeholder="<?= print_translation('admin_table_search_placeholder') ?>"
                autocomplete="off"
                data-admin-table-search
            >
        </form>

        <ul class="nav nav-tabs mb-3 admin-posts-tabs" role="tablist" style="max-width: 450px">
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link <?= $activeStatus === 'published' ? 'active' : '' ?>" id="published-posts-tab" data-bs-toggle="tab" data-bs-target="#published-posts-tab-pane" role="tab" aria-controls="published-posts-tab-pane" aria-selected="<?= $activeStatus === 'published' ? 'true' : 'false' ?>" data-admin-posts-tab-button="published">
                    <?= print_translation('admin_posts_status_published') ?>
                    <span class="badge text-bg-secondary ms-2" data-admin-posts-count="published"><?= $publishedTotal ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link <?= $activeStatus === 'drafts' ? 'active' : '' ?>" id="draft-posts-tab" data-bs-toggle="tab" data-bs-target="#draft-posts-tab-pane" role="tab" aria-controls="draft-posts-tab-pane" aria-selected="<?= $activeStatus === 'drafts' ? 'true' : 'false' ?>" data-admin-posts-tab-button="drafts">
                    <?= print_translation('admin_posts_status_draft') ?>
                    <span class="badge text-bg-secondary ms-2" data-admin-posts-count="drafts"><?= $draftTotal ?></span>
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade <?= $activeStatus === 'published' ? 'show active' : '' ?>" id="published-posts-tab-pane" role="tabpanel" aria-labelledby="published-posts-tab" tabindex="0" data-admin-posts-pane="published">
                <?= view()->renderPartial('admin/partials/posts_table_pane', [
                    'items' => $publishedPosts,
                    'table_key' => 'published',
                    'empty_text' => $emptyText,
                    'pagination' => $publishedPagination,
                    'total' => $publishedTotal,
                    'sort' => $sort,
                    'direction' => $direction,
                ]) ?>
            </div>
            <div class="tab-pane fade <?= $activeStatus === 'drafts' ? 'show active' : '' ?>" id="draft-posts-tab-pane" role="tabpanel" aria-labelledby="draft-posts-tab" tabindex="0" data-admin-posts-pane="drafts">
                <?= view()->renderPartial('admin/partials/posts_table_pane', [
                    'items' => $draftPosts,
                    'table_key' => 'drafts',
                    'empty_text' => $emptyText,
                    'pagination' => $draftPagination,
                    'total' => $draftTotal,
                    'sort' => $sort,
                    'direction' => $direction,
                ]) ?>
            </div>
        </div>
    </div>
<?= view()->renderPartial('admin/shell_close') ?>
