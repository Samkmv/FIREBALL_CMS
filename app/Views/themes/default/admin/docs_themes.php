<?php
$currentArticle = (string)($article ?? 'introduction');
$routeBase = '/' . trim((string)($route_base ?? '/admin/docs/themes'), '/');
$docsPathLabel = (string)($docs_path_label ?? 'themes');
$shellTitle = (string)($shell_title ?? return_translation('admin_docs_themes_heading'));
$shellSubtitle = (string)($shell_subtitle ?? return_translation('admin_docs_themes_subtitle'));
$backUrl = (string)($back_url ?? '');
$backLabel = (string)($back_label ?? '');
$navLabel = (string)($nav_label ?? 'Documentation');
$articleBaseUrl = static fn(string $slug): string => base_href($routeBase . '/' . $slug);
?>

<?= view()->renderPartial('admin/shell_open', [
    'title' => $shellTitle,
    'subtitle' => $shellSubtitle,
    'actions' => $backUrl !== '' ? '<a class="btn btn-outline-secondary rounded-pill" href="' . htmlSC($backUrl) . '">' . htmlSC($backLabel !== '' ? $backLabel : 'Назад') . '</a>' : '',
    'sidebar_col_class' => 'col-lg-3',
    'main_col_class' => 'col-lg-9',
]) ?>

    <div class="row g-4 g-xl-5 align-items-start">
        <aside class="col-xl-3">
            <div class="position-sticky" style="top: 7rem;">
                <form class="mb-4" action="<?= $articleBaseUrl($currentArticle) ?>" method="get">
                    <label class="form-label small text-body-secondary"><?= print_translation('admin_docs_search_label') ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="ci-search"></i></span>
                        <input class="form-control" type="search" name="q" value="<?= htmlSC((string)$query) ?>" placeholder="<?= htmlSC(return_translation('admin_docs_search_placeholder')) ?>">
                    </div>
                </form>

                <nav class="list-group list-group-flush border rounded-4 p-2" aria-label="<?= htmlSC($navLabel) ?>">
                    <?php foreach ($articles as $slug => $label): ?>
                        <a class="list-group-item list-group-item-action border-0 rounded-3 <?= $slug === $currentArticle ? 'active' : 'bg-transparent' ?>" href="<?= $articleBaseUrl($slug) ?>">
                            <?= htmlSC((string)$label) ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </aside>

        <article class="col-xl-9">
            <?php if (!empty($query)): ?>
                <div class="border rounded-4 p-3 p-md-4 mb-4">
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                        <h2 class="h5 mb-0"><?= print_translation('admin_docs_search_results') ?></h2>
                        <span class="badge text-bg-secondary rounded-pill"><?= count($search_results) ?></span>
                    </div>
                    <?php if (!empty($search_results)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($search_results as $result): ?>
                                <a class="list-group-item list-group-item-action px-0" href="<?= $articleBaseUrl((string)$result['slug']) ?>?q=<?= rawurlencode((string)$query) ?>">
                                    <div class="fw-semibold"><?= htmlSC((string)$result['title']) ?></div>
                                    <div class="small text-body-secondary"><?= htmlSC((string)$result['excerpt']) ?></div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-body-secondary mb-0"><?= print_translation('admin_docs_search_empty') ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="border rounded-5 p-3 p-md-5 docs-theme-article">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
                    <span class="badge text-bg-light border rounded-pill">docs/<?= htmlSC((string)$resolved_language) ?>/<?= htmlSC($docsPathLabel) ?></span>
                    <span class="small text-body-secondary"><?= htmlSC((string)$article_title) ?></span>
                </div>
                <?= $content_html ?>
            </div>

            <div class="d-flex justify-content-between gap-3 mt-4">
                <?php if ($previous_article): ?>
                    <a class="btn btn-outline-secondary rounded-pill" href="<?= $articleBaseUrl($previous_article) ?>">
                        <i class="ci-chevron-left me-2"></i><?= htmlSC((string)$articles[$previous_article]) ?>
                    </a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>

                <?php if ($next_article): ?>
                    <a class="btn btn-outline-secondary rounded-pill" href="<?= $articleBaseUrl($next_article) ?>">
                        <?= htmlSC((string)$articles[$next_article]) ?><i class="ci-chevron-right ms-2"></i>
                    </a>
                <?php endif; ?>
            </div>
        </article>
    </div>

<?= view()->renderPartial('admin/shell_close') ?>
