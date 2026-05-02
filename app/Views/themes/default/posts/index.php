<?php

$postUrl = static fn(array $post): string => base_href('/posts/' . $post['slug']);
$categoryUrl = static function (?string $category = null): string {
    $url = base_href('/posts');
    if ($category === null || $category === '') {
        return $url;
    }

    return $url . '?category=' . rawurlencode($category);
};
$posts = array_values($posts ?? []);
$topPosts = array_slice($posts, 0, 4);
$featuredPosts = array_slice($posts, 4, 2);
$bottomPosts = array_slice($posts, 6);
$featuredLoop = count($featuredPosts) > 1 ? 'true' : 'false';
$paginationMarkup = !empty($pagination)
    ? str_replace('class="pagination"', 'class="pagination pagination-lg"', (string)$pagination)
    : '';
$socialLinks = array_values(array_filter([
    [
        'href' => site_setting('social_instagram', ''),
        'icon' => 'ci-instagram',
        'label' => 'Instagram',
    ],
    [
        'href' => site_setting('social_facebook', ''),
        'icon' => 'ci-facebook',
        'label' => 'Facebook',
    ],
    [
        'href' => site_setting('social_telegram', ''),
        'icon' => 'ci-telegram',
        'label' => 'Telegram',
    ],
    [
        'href' => site_setting('social_youtube', ''),
        'icon' => 'ci-youtube',
        'label' => 'YouTube',
    ],
], static fn(array $link): bool => trim((string)($link['href'] ?? '')) !== ''));
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
        <li class="breadcrumb-item active" aria-current="page"><?= print_translation('posts_index_breadcrumb') ?></li>
    </ol>
</nav>

<section class="container pb-5 mb-2 mb-md-3 mb-lg-4 mb-xl-5">
    <div class="row">
        <div class="col-lg-8">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 pb-4 mb-2">
                <div>
                    <span class="badge text-bg-dark rounded-pill mb-3"><?= (int)$total_posts ?> <?= print_translation('posts_index_total') ?></span>
                    <h1 class="h3 mb-2"><?= print_translation('posts_index_heading') ?></h1>
                    <?php $postsIndexSubtitle = trim((string)return_translation('posts_index_subtitle')); ?>
                    <?php if ($postsIndexSubtitle !== ''): ?>
                        <p class="text-body-secondary mb-0"><?= htmlSC($postsIndexSubtitle) ?></p>
                    <?php endif; ?>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <?php if (!empty($current_category)): ?>
                        <a class="btn btn-outline-secondary" href="<?= $categoryUrl() ?>">
                            <?= print_translation('posts_index_clear_category') ?>: <?= htmlSC($current_category_label ?? $current_category) ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($posts)): ?>
                <?php if (!empty($topPosts)): ?>
                    <div class="row row-cols-1 row-cols-sm-2 gy-5">
                        <?php foreach ($topPosts as $post): ?>
                            <?= $renderGridPost($post) ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($featuredPosts)): ?>
                    <div class="py-5 my-1 my-sm-2 my-md-3 my-lg-4 my-xl-5">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <h2 class="h3 mb-0"><?= print_translation('posts_index_slider_heading') ?></h2>
                            <?php if (count($featuredPosts) > 1): ?>
                                <div class="d-flex gap-2">
                                    <button type="button" id="posts-feature-prev" class="btn btn-prev btn-icon btn-outline-secondary rounded-circle animate-slide-start me-1" aria-label="Previous slide">
                                        <i class="ci-chevron-left fs-lg animate-target"></i>
                                    </button>
                                    <button type="button" id="posts-feature-next" class="btn btn-next btn-icon btn-outline-secondary rounded-circle animate-slide-end" aria-label="Next slide">
                                        <i class="ci-chevron-right fs-lg animate-target"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="row row-cols-1 row-cols-md-2 g-0 overflow-hidden rounded-5">
                            <div class="col order-md-2 user-select-none">
                                <div class="swiper h-100 swiper-fade" id="postsFeatureImages" data-swiper="{
                                  &quot;allowTouchMove&quot;: false,
                                  &quot;loop&quot;: <?= $featuredLoop ?>,
                                  &quot;effect&quot;: &quot;fade&quot;
                                }">
                                    <div class="swiper-wrapper">
                                        <?php foreach ($featuredPosts as $post): ?>
                                            <div class="swiper-slide">
                                                <div class="ratio ratio-16x9"></div>
                                                <img src="<?= get_image($post['image']) ?>" class="position-absolute top-0 start-0 w-100 h-100 object-fit-cover" alt="<?= htmlSC($post['title']) ?>">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col bg-dark order-md-1 py-5 px-4 px-sm-5" data-bs-theme="dark">
                                <div class="swiper py-sm-2 py-md-3 my-xl-2 my-xxl-3" data-swiper="{
                                  &quot;spaceBetween&quot;: 40,
                                  &quot;loop&quot;: <?= $featuredLoop ?>,
                                  &quot;speed&quot;: 400,
                                  &quot;controlSlider&quot;: &quot;#postsFeatureImages&quot;<?php if (count($featuredPosts) > 1): ?>,
                                  &quot;navigation&quot;: {
                                    &quot;prevEl&quot;: &quot;#posts-feature-prev&quot;,
                                    &quot;nextEl&quot;: &quot;#posts-feature-next&quot;
                                  }<?php endif; ?>
                                }">
                                    <div class="swiper-wrapper">
                                        <?php foreach ($featuredPosts as $post): ?>
                                            <div class="swiper-slide">
                                                <h3 class="h5"><?= htmlSC($post['title']) ?></h3>
                                                <?php $featuredExcerpt = $buildExcerpt($post); ?>
                                                <?php if ($featuredExcerpt !== ''): ?>
                                                    <p class="text-body fs-sm pb-4 mb-0"><?= htmlSC($featuredExcerpt) ?></p>
                                                <?php endif; ?>
                                                <a class="btn btn-outline-light mt-4" href="<?= $postUrl($post) ?>">
                                                    <?= print_translation('posts_index_read_more') ?>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($bottomPosts)): ?>
                    <div class="row row-cols-1 row-cols-sm-2 gy-5 pb-2 pb-sm-0">
                        <?php foreach ($bottomPosts as $post): ?>
                            <?= $renderGridPost($post) ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($total_posts > PAGINATION_SETTINGS['perPage'] && $paginationMarkup !== ''): ?>
                    <hr class="mt-4 mt-sm-5">
                    <?= $paginationMarkup ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="border rounded-5 p-5 text-center">
                    <h2 class="h5 mb-2"><?= print_translation('posts_index_empty') ?></h2>
                    <p class="text-body-secondary mb-0"><?= print_translation('posts_index_empty_desc') ?></p>
                </div>
            <?php endif; ?>
        </div>

        <aside class="col-lg-4 col-xl-3 offset-xl-1" style="margin-top: -115px">
            <div class="offcanvas-lg offcanvas-end sticky-lg-top ps-lg-4 ps-xl-0" id="blogSidebar" tabindex="-1" aria-labelledby="blogSidebarLabel">
                <div class="d-none d-lg-block" style="height: 115px"></div>
                <div class="offcanvas-header py-3">
                    <h2 class="offcanvas-title h5 mb-0" id="blogSidebarLabel"><?= print_translation('posts_index_sidebar_title') ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#blogSidebar" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body d-block pt-2 py-lg-0">
                    <h3 class="h6 mb-4"><?= print_translation('posts_index_sidebar_categories') ?></h3>
                    <div class="d-flex flex-wrap gap-3">
                        <a class="btn <?= empty($current_category) ? 'btn-dark' : 'btn-outline-secondary' ?> px-3" href="<?= $categoryUrl() ?>">
                            <?= print_translation('posts_index_all_categories') ?>
                        </a>
                        <?php foreach ($categories as $category): ?>
                            <a class="btn <?= ($current_category ?? '') === $category['slug'] ? 'btn-dark' : 'btn-outline-secondary' ?> px-3" href="<?= $categoryUrl($category['slug']) ?>">
                                <?= htmlSC($category['label'] ?? $category['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($trending_posts)): ?>
                        <h3 class="h6 pt-5 mb-0"><?= print_translation('posts_index_sidebar_trending') ?></h3>

                        <?php foreach ($trending_posts as $post): ?>
                            <article class="hover-effect-scale position-relative d-flex align-items-center border-bottom py-4">
                                <div class="w-100 pe-3">
                                    <h4 class="h6 lh-base fs-sm mb-1">
                                        <a class="hover-effect-underline stretched-link" href="<?= $postUrl($post) ?>">
                                            <?= htmlSC($post['title']) ?>
                                        </a>
                                    </h4>
                                    <div class="text-body-tertiary fs-xs">
                                        <?= date('d.m.Y', strtotime($post['published_at'])) ?>
                                        <span class="mx-1">•</span>
                                        <?= print_translation('posts_show_views') ?> <?= (int)$post['views_count'] ?>
                                    </div>
                                </div>
                                <div class="ratio w-100" style="max-width: 86px; --cz-aspect-ratio: calc(64 / 86 * 100%)">
                                    <img src="<?= get_image($post['image']) ?>" class="rounded-2" alt="<?= htmlSC($post['title']) ?>" style="object-fit: cover;">
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($socialLinks)): ?>
                        <h3 class="h6 pt-4"><?= print_translation('posts_index_follow') ?></h3>
                        <div class="d-flex flex-wrap gap-2 pb-2">
                            <?php foreach ($socialLinks as $link): ?>
                                <a
                                    class="btn btn-icon fs-base btn-outline-secondary border-0"
                                    href="<?= htmlSC($link['href']) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
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
    <?= print_translation('posts_index_sidebar_open') ?>
</button>
