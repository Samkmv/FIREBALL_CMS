<?php

$postUrl = static fn(array $post): string => base_href('/posts/' . $post['slug']);
$postsTranslationPrefix = trim((string)($posts_translation_prefix ?? 'posts'));
if ($postsTranslationPrefix === '') {
    $postsTranslationPrefix = 'posts';
}
$postsTranslate = static fn(string $key): string => return_translation($postsTranslationPrefix . '_' . $key);
$categoryUrl = static function (?string $category = null): string {
    $url = base_href('/posts');
    if ($category === null || $category === '') {
        return $url;
    }

    return $url . '?category=' . rawurlencode($category);
};

$posts = array_values($posts ?? []);
$allPostsTotal = array_sum(array_map(static fn(array $category): int => (int)($category['total'] ?? 0), $categories ?? []));
if ($allPostsTotal <= 0) {
    $allPostsTotal = (int)$total_posts;
}

$paginationMarkup = !empty($pagination)
        ? str_replace('class="pagination"', 'class="pagination justify-content-center"', $pagination)
        : '';

$socialLinks = site_social_links();
$buildExcerpt = static function (array $post, int $limit = 170): string {
    $excerpt = trim((string)($post['excerpt'] ?? ''));
    if ($excerpt === '') {
        $excerpt = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($post['content'] ?? ''))));
    }

    if ($excerpt === '') {
        return '';
    }

    if (mb_strlen($excerpt) <= $limit) {
        return $excerpt;
    }

    return rtrim(mb_substr($excerpt, 0, $limit - 1)) . '...';
};
$renderGridPost = static function (array $post) use ($postUrl, $categoryUrl): string {
    ob_start();
    ?>
    <article class="col">
        <a class="ratio d-flex hover-effect-scale rounded overflow-hidden" href="<?= $postUrl($post) ?>" style="--cz-aspect-ratio: calc(305 / 416 * 100%)">
            <img src="<?= get_image($post['image']) ?>" class="hover-effect-target w-100 h-100 object-fit-cover" alt="<?= htmlSC($post['title']) ?>">
        </a>
        <div class="pt-4">
            <div class="nav align-items-center gap-2 pb-2 mt-n1 mb-1">
                <a class="nav-link text-body fs-xs text-uppercase p-0" href="<?= $categoryUrl($post['category_slug'] ?? $post['category']) ?>">
                    <?= htmlSC($post['category_label'] ?? $post['category']) ?>
                </a>
                <hr class="vr my-1 mx-1">
                <span class="text-body-tertiary fs-xs"><?= date('d.m.Y', strtotime($post['published_at'])) ?></span>
            </div>
            <h3 class="h5 mb-0">
                <a class="hover-effect-underline" href="<?= $postUrl($post) ?>"><?= htmlSC($post['title']) ?></a>
            </h3>
        </div>
    </article>
    <?php
    return (string)ob_get_clean();
};

?>

<nav class="container pt-3 my-3 my-md-4" aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_href('/') ?>"><?= print_translation('tpl_menu_nav_index') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= htmlSC($postsTranslate('index_breadcrumb')) ?></li>
    </ol>
</nav>

<section class="container pb-5 mb-2 mb-md-3 mb-lg-4 mb-xl-5">
    <div class="row">
        <div class="col-lg-8">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 pb-4 mb-2">
                <div>
                    <span class="badge text-bg-dark rounded-pill mb-3"><?= (int)$total_posts ?> <?= htmlSC($postsTranslate('index_total')) ?></span>
                    <h1 class="h3 mb-2"><?= htmlSC($postsTranslate('index_heading')) ?></h1>
                    <?php $postsIndexSubtitle = trim((string)$postsTranslate('index_subtitle')); ?>
                    <?php if ($postsIndexSubtitle !== ''): ?>
                        <p class="text-body-secondary mb-0"><?= htmlSC($postsIndexSubtitle) ?></p>
                    <?php endif; ?>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if (!empty($current_category)): ?>
                        <a class="btn btn-outline-secondary" href="<?= $categoryUrl() ?>">
                            <?= htmlSC($postsTranslate('index_clear_category')) ?>: <?= htmlSC($current_category_label ?? $current_category) ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($posts)): ?>
                <div class="row row-cols-1 row-cols-sm-2 gy-5 pb-2 pb-sm-0">
                    <?php foreach ($posts as $post): ?>
                        <?= $renderGridPost($post) ?>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_posts > PAGINATION_SETTINGS['perPage'] && $paginationMarkup !== ''): ?>
                    <hr class="mt-4 mt-sm-5">
                    <?= $paginationMarkup ?>
                <?php endif; ?>

            <?php else: ?>
                <div class="border rounded-5 p-5 text-center">
                    <h2 class="h5 mb-2"><?= htmlSC($postsTranslate('index_empty')) ?></h2>
                    <p class="text-body-secondary mb-0"><?= htmlSC($postsTranslate('index_empty_desc')) ?></p>
                </div>
            <?php endif; ?>
        </div>

        <aside class="col-lg-4 col-xl-3 offset-xl-1" style="margin-top: -115px">
            <div class="offcanvas-lg offcanvas-end sticky-lg-top ps-lg-4 ps-xl-0" id="blogSidebar">
                <div class="d-none d-lg-block" style="height: 115px"></div>
                <div class="offcanvas-header py-3">
                    <h5 class="offcanvas-title"><?= htmlSC($postsTranslate('index_sidebar_title')) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#blogSidebar" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body d-block pt-2 py-lg-0">
                    <h4 class="h6 mb-4"><?= htmlSC($postsTranslate('index_sidebar_categories')) ?></h4>
                    <div class="d-flex flex-wrap gap-3">
                        <a class="btn <?= empty($current_category) ? 'btn-dark' : 'btn-outline-secondary' ?> px-3" href="<?= $categoryUrl() ?>">
                            <?= htmlSC($postsTranslate('index_all_categories')) ?> (<?= (int)$allPostsTotal ?>)
                        </a>
                        <?php foreach ($categories as $category): ?>
                            <a class="btn <?= ($current_category ?? '') === $category['slug'] ? 'btn-dark' : 'btn-outline-secondary' ?> px-3" href="<?= $categoryUrl($category['slug']) ?>">
                                <?= htmlSC($category['label'] ?? $category['name']) ?> (<?= (int)($category['total'] ?? 0) ?>)
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($trending_posts)): ?>
                        <h4 class="h6 pt-5 mb-0"><?= htmlSC($postsTranslate('index_sidebar_trending')) ?></h4>

                        <?php foreach ($trending_posts as $index => $post): ?>
                            <article class="hover-effect-scale position-relative d-flex align-items-center <?= $index < count($trending_posts) - 1 ? 'border-bottom ' : '' ?>py-4">
                                <div class="w-100 pe-3">
                                    <h3 class="h6 lh-base fs-sm mb-1">
                                        <a class="hover-effect-underline stretched-link" href="<?= $postUrl($post) ?>">
                                            <?= htmlSC($post['title']) ?>
                                        </a>
                                    </h3>
                                    <div class="text-body-tertiary fs-xs">
                                        <?= date('d.m.Y', strtotime($post['published_at'])) ?>
                                        <span class="mx-1">•</span>
                                        <?= htmlSC($postsTranslate('show_views')) ?> <?= (int)$post['views_count'] ?>
                                    </div>
                                </div>
                                <div class="ratio w-100" style="max-width: 86px; --cz-aspect-ratio: calc(64 / 86 * 100%)">
                                    <img src="<?= get_image($post['image']) ?>" class="rounded-2" alt="<?= htmlSC($post['title']) ?>" style="object-fit: cover;">
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($socialLinks)): ?>
                        <h4 class="h6 pt-4"><?= htmlSC($postsTranslate('index_follow')) ?></h4>
                        <div class="d-flex gap-2 pb-2">
                            <?php foreach ($socialLinks as $link): ?>
                                <a
                                    class="btn btn-icon fs-base btn-outline-secondary border-0"
                                    href="<?= htmlSC($link['href']) ?>"
                                    <?= !empty($link['external']) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                                    data-bs-toggle="tooltip"
                                    data-bs-template="<div class=&quot;tooltip fs-xs mb-n2&quot; role=&quot;tooltip&quot;><div class=&quot;tooltip-inner bg-transparent text-body p-0&quot;></div></div>"
                                    aria-label="<?= htmlSC($link['label']) ?>"
                                    data-bs-original-title="<?= htmlSC($link['label']) ?>"
                                >
                                    <i class="<?= htmlSC($link['icon']) ?>"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>
</section>

<button
    type="button"
    class="fixed-bottom z-sticky w-100 btn btn-lg btn-dark border-0 border-top border-light border-opacity-10 rounded-0 pb-4 d-lg-none"
    data-bs-toggle="offcanvas"
    data-bs-target="#blogSidebar"
    aria-controls="blogSidebar"
    data-bs-theme="light"
>
    <i class="ci-sidebar fs-base me-2"></i>
    <?= htmlSC($postsTranslate('index_sidebar_open')) ?>
</button>
