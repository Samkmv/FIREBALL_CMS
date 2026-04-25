<?php

$postUrl = static fn(array $post): string => base_href('/posts/' . $post['slug']);
$categoryUrl = static function (?string $category = null): string {
    $url = base_href('/posts');
    if ($category === null || $category === '') {
        return $url;
    }

    return $url . '?category=' . rawurlencode($category);
};

?>

<nav class="container pt-3 my-3 my-md-4" aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_href('/') ?>"><?= print_translation('tpl_menu_nav_index') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= print_translation('posts_index_breadcrumb') ?></li>
    </ol>
</nav>

<section class="container pb-5 mb-2 mb-md-3 mb-lg-4 mb-xl-5">
    <div class="row g-4 g-lg-5">
        <div class="col-lg-8">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                <div>
                    <span class="badge text-bg-dark rounded-pill mb-3"><?= (int)$total_posts ?> <?= print_translation('posts_index_total') ?></span>
                    <h1 class="h3 mb-2"><?= print_translation('posts_index_heading') ?></h1>
                </div>
                <?php if (!empty($current_category)): ?>
                    <a class="btn btn-outline-secondary rounded-pill" href="<?= $categoryUrl() ?>">
                        <?= print_translation('posts_index_clear_category') ?>: <?= htmlSC($current_category_label ?? $current_category) ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($posts)): ?>
                <div class="d-flex flex-column gap-4 mt-n3">
                    <?php foreach ($posts as $post): ?>
                        <article class="row align-items-start align-items-md-center gx-0 gy-4 pt-3">
                            <div class="col-sm-5 pe-sm-4">
                                <a class="ratio d-flex hover-effect-scale rounded-4 overflow-hidden flex-md-shrink-0" href="<?= $postUrl($post) ?>" style="--cz-aspect-ratio: calc(226 / 306 * 100%)">
                                    <img src="<?= get_image($post['image']) ?>" class="hover-effect-target" alt="<?= htmlSC($post['title']) ?>" style="object-fit: cover;">
                                </a>
                            </div>
                            <div class="col-sm-7">
                                <div class="nav align-items-center gap-2 pb-2 mt-n1 mb-1">
                                    <span class="nav-link text-body fs-xs text-uppercase p-0"><?= htmlSC($post['category_label'] ?? $post['category']) ?></span>
                                    <hr class="vr my-1 mx-1">
                                    <span class="text-body-tertiary fs-xs"><?= date('d.m.Y', strtotime($post['published_at'])) ?></span>
                                    <hr class="vr my-1 mx-1">
                                    <span class="text-body-tertiary fs-xs"><?= print_translation('posts_show_author') ?> <?= htmlSC($post['author_label']) ?></span>
                                    <hr class="vr my-1 mx-1">
                                    <span class="text-body-tertiary fs-xs"><?= print_translation('posts_show_views') ?> <?= (int)$post['views_count'] ?></span>
                                </div>
                                <h2 class="h5 mb-2 mb-md-3">
                                    <a class="hover-effect-underline" href="<?= $postUrl($post) ?>"><?= htmlSC($post['title']) ?></a>
                                </h2>
                                <?php if (!empty($post['excerpt'])): ?>
                                    <p class="mb-3"><?= htmlSC($post['excerpt']) ?></p>
                                <?php endif; ?>
                                <a class="btn btn-outline-secondary btn-sm rounded-pill" href="<?= $postUrl($post) ?>">
                                    <?= print_translation('posts_index_read_more') ?>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_posts > PAGINATION_SETTINGS['perPage']): ?>
                    <div class="pt-4 mt-4">
                        <?= $pagination ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="border rounded-5 p-5 text-center">
                    <h2 class="h5 mb-2"><?= print_translation('posts_index_empty') ?></h2>
                    <p class="text-body-secondary mb-0"><?= print_translation('posts_index_empty_desc') ?></p>
                </div>
            <?php endif; ?>
        </div>

        <aside class="col-lg-4 col-xl-3 offset-xl-1">
            <div class="sticky-lg-top" style="top: 110px">
                <div class="border rounded-5 p-4 mb-4">
                    <h2 class="h6 mb-3"><?= print_translation('posts_index_sidebar_categories') ?></h2>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn rounded-pill <?= empty($current_category) ? 'btn-dark' : 'btn-outline-secondary' ?>" href="<?= $categoryUrl() ?>">
                            <?= print_translation('posts_index_all_categories') ?>
                        </a>
                        <?php foreach ($categories as $category): ?>
                            <a class="btn rounded-pill <?= ($current_category ?? '') === $category['slug'] ? 'btn-dark' : 'btn-outline-secondary' ?>" href="<?= $categoryUrl($category['slug']) ?>">
                                <?= htmlSC($category['label'] ?? $category['name']) ?> (<?= (int)$category['total'] ?>)
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (!empty($trending_posts)): ?>
                    <div class="border rounded-5 p-4">
                        <h2 class="h6 mb-3"><?= print_translation('posts_index_sidebar_trending') ?></h2>

                        <?php foreach ($trending_posts as $post): ?>
                            <article class="hover-effect-scale position-relative d-flex align-items-center border-bottom py-3">
                                <div class="w-100 pe-3">
                                    <h3 class="h6 lh-base fs-sm mb-1">
                                        <a class="hover-effect-underline stretched-link" href="<?= $postUrl($post) ?>">
                                            <?= htmlSC($post['title']) ?>
                                        </a>
                                    </h3>
                                    <div class="text-body-tertiary fs-xs">
                                        <?= date('d.m.Y', strtotime($post['published_at'])) ?>
                                        <span class="mx-1">•</span>
                                        <?= print_translation('posts_show_views') ?> <?= (int)$post['views_count'] ?>
                                    </div>
                                </div>
                                <div class="ratio w-100 rounded-3 overflow-hidden" style="max-width: 86px; --cz-aspect-ratio: calc(64 / 86 * 100%)">
                                    <img src="<?= get_image($post['image']) ?>" alt="<?= htmlSC($post['title']) ?>" style="object-fit: cover;">
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</section>
