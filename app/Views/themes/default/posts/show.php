<?php

$postUrl = static fn(array $item): string => base_href('/posts/' . $item['slug']);
$categoryUrl = static fn(?string $category = null): string => $category === null || $category === ''
    ? base_href('/posts')
    : base_href('/posts') . '?category=' . rawurlencode($category);
$shareUrl = base_href('/posts/' . $post['slug']);
$socialLinks = site_social_links();
$currentCategorySlug = (string)($post['category_slug'] ?? $post['category'] ?? '');
$allPostsTotal = array_sum(array_map(static fn(array $category): int => (int)($category['total'] ?? 0), $categories ?? []));
?>

<nav class="container pt-3 my-3 my-md-4" aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_href('/') ?>"><?= print_translation('tpl_menu_nav_index') ?></a></li>
        <li class="breadcrumb-item"><a href="<?= base_href('/posts') ?>"><?= print_translation('posts_show_breadcrumb') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= htmlSC($post['title']) ?></li>
    </ol>
</nav>

<section class="container pb-5 mb-2 mb-md-3 mb-lg-4 mb-xl-5">
    <div class="row">
        <div class="col-lg-8 position-relative z-2">
            <h1 class="h3 mb-4"><?= htmlSC($post['title']) ?></h1>

            <div class="nav align-items-center gap-2 border-bottom pb-4 mt-n1 mb-4">
                <a class="nav-link text-body fs-xs text-uppercase p-0" href="<?= $categoryUrl($post['category_slug'] ?? $post['category']) ?>">
                    <?= htmlSC($post['category_label'] ?? $post['category']) ?>
                </a>
                <hr class="vr my-1 mx-1">
                <span class="text-body-tertiary fs-xs"><?= date('d.m.Y', strtotime($post['published_at'])) ?></span>
                <hr class="vr my-1 mx-1">
                <span class="text-body-tertiary fs-xs"><?= print_translation('posts_show_author') ?> <?= htmlSC($post['author_label']) ?></span>
                <hr class="vr my-1 mx-1">
                <span class="text-body-tertiary fs-xs"><?= print_translation('posts_show_views') ?> <?= (int)$post['views_count'] ?></span>
            </div>

            <?php if (!empty($post['excerpt'])): ?>
                <p class="fs-lg text-body-secondary mb-4"><?= htmlSC($post['excerpt']) ?></p>
            <?php endif; ?>

            <?php if (!empty($post['show_post_image'])): ?>
                <figure class="figure w-100 py-3 py-md-4 mb-3">
                    <div class="ratio" style="--cz-aspect-ratio: calc(560 / 856 * 100%)">
                        <img src="<?= htmlSC($post['image_webp'] ?? $post['image_thumb'] ?? get_image($post['image'])) ?>" srcset="<?= htmlSC($post['image_srcset'] ?? '') ?>" sizes="(max-width: 991px) 100vw, 856px" data-image-fallback="<?= htmlSC(base_url('/assets/img/no-image.png')) ?>" onerror="this.onerror=null;this.removeAttribute('srcset');this.src=this.dataset.imageFallback;" class="rounded-4" width="<?= (int)($post['image_width'] ?: 856) ?>" height="<?= (int)($post['image_height'] ?: 560) ?>" alt="<?= htmlSC($post['title']) ?>" loading="lazy" decoding="async" style="object-fit: cover;">
                    </div>
                </figure>
            <?php endif; ?>

            <?php if (!empty($post['content'])): ?>
                <div class="post-content fs-base lh-lg">
                    <?= $post['content'] ?>
                </div>
            <?php endif; ?>

            <div class="d-sm-flex align-items-center justify-content-between py-4 py-md-5 mt-n2 mt-md-n3 mb-2 mb-sm-3 mb-md-0">
                <div class="d-flex flex-wrap gap-2 mb-4 mb-sm-0 me-sm-4">
                    <a class="btn btn-outline-secondary px-3 mt-1 me-1" href="<?= $categoryUrl($post['category_slug'] ?? $post['category']) ?>">
                        <?= htmlSC($post['category_label'] ?? $post['category']) ?>
                    </a>
                </div>
                <button
                    class="btn btn-outline-secondary rounded-pill d-inline-flex align-items-center gap-2"
                    type="button"
                    data-share-button
                    data-share-title="<?= htmlSC($post['title']) ?>"
                    data-share-text="<?= htmlSC((string)($post['excerpt'] ?? '')) ?>"
                    data-share-url="<?= htmlSC($shareUrl) ?>"
                    data-share-copied="<?= htmlSC(return_translation('posts_show_share_copied')) ?>"
                >
                    <i class="ci-share-2" data-share-icon></i>
                    <span data-share-label><?= print_translation('posts_show_share') ?></span>
                </button>
            </div>

            <div class="border-top mt-5 pt-4">
                <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/posts') ?>">
                    <i class="ci-chevron-left fs-base me-2"></i>
                    <?= print_translation('posts_show_back') ?>
                </a>
            </div>
        </div>

        <aside class="col-lg-4 col-xl-3 offset-xl-1" style="margin-top: -115px">
            <div class="offcanvas-lg offcanvas-end sticky-lg-top ps-lg-4 ps-xl-0" id="blogSidebar">
                <div class="d-none d-lg-block" style="height: 115px"></div>
                <div class="offcanvas-header py-3">
                    <h5 class="offcanvas-title"><?= print_translation('posts_show_sidebar_title') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#blogSidebar" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body d-block pt-2 py-lg-0">
                    <h4 class="h6 mb-4"><?= print_translation('posts_show_categories') ?></h4>
                    <div class="d-flex flex-wrap gap-3">
                        <a class="btn btn-outline-secondary px-3" href="<?= $categoryUrl() ?>">
                            <?= print_translation('posts_show_back') ?> (<?= (int)$allPostsTotal ?>)
                        </a>
                        <?php foreach ($categories as $category): ?>
                            <a class="btn <?= $currentCategorySlug === (string)$category['slug'] ? 'btn-dark' : 'btn-outline-secondary' ?> px-3" href="<?= $categoryUrl($category['slug']) ?>">
                                <?= htmlSC($category['label'] ?? $category['name']) ?> (<?= (int)$category['total'] ?>)
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($trending_posts)): ?>
                        <h4 class="h6 pt-5 mb-0"><?= print_translation('posts_show_recent') ?></h4>

                        <?php foreach ($trending_posts as $index => $item): ?>
                            <article class="hover-effect-scale position-relative d-flex align-items-center <?= $index < count($trending_posts) - 1 ? 'border-bottom ' : '' ?>py-4">
                                <div class="w-100 pe-3">
                                    <h3 class="h6 lh-base fs-sm mb-1">
                                        <a class="hover-effect-underline stretched-link" href="<?= $postUrl($item) ?>">
                                            <?= htmlSC($item['title']) ?>
                                        </a>
                                    </h3>
                                    <div class="text-body-tertiary fs-xs">
                                        <?= date('d.m.Y', strtotime($item['published_at'])) ?>
                                        <span class="mx-1">•</span>
                                        <?= print_translation('posts_show_views') ?> <?= (int)$item['views_count'] ?>
                                    </div>
                                </div>
                                <div class="ratio w-100" style="max-width: 86px; --cz-aspect-ratio: calc(64 / 86 * 100%)">
                                    <img src="<?= htmlSC($item['image_mobile'] ?? get_image($item['image'])) ?>" srcset="<?= htmlSC($item['image_srcset'] ?? '') ?>" sizes="86px" data-image-fallback="<?= htmlSC(base_url('/assets/img/no-image.png')) ?>" onerror="this.onerror=null;this.removeAttribute('srcset');this.src=this.dataset.imageFallback;" class="rounded-2" width="86" height="64" alt="<?= htmlSC($item['title']) ?>" loading="lazy" decoding="async" style="object-fit: cover;">
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($socialLinks)): ?>
                        <h4 class="h6 pt-4"><?= print_translation('posts_show_follow') ?></h4>
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

    <?php if (!empty($popular_posts)): ?>
        <div class="pt-5 pb-5 mb-1 mb-sm-2 mb-md-3 mb-lg-4 mb-xl-5">
            <h2 class="h3 text-center pb-2 pb-sm-3"><?= print_translation('posts_show_popular') ?></h2>
            <div class="swiper" data-swiper="{
          &quot;slidesPerView&quot;: 1,
          &quot;spaceBetween&quot;: 24,
          &quot;pagination&quot;: {
            &quot;el&quot;: &quot;.swiper-pagination&quot;,
            &quot;clickable&quot;: true
          },
          &quot;breakpoints&quot;: {
            &quot;500&quot;: {
              &quot;slidesPerView&quot;: 2
            },
            &quot;900&quot;: {
              &quot;slidesPerView&quot;: 3
            }
          }
        }">
                <div class="swiper-wrapper">
                    <?php foreach ($popular_posts as $item): ?>
                    <article class="swiper-slide">
                        <a class="ratio d-flex hover-effect-scale rounded overflow-hidden" href="<?= $postUrl($item) ?>" style="--cz-aspect-ratio: calc(305 / 416 * 100%)">
                            <img src="<?= htmlSC($item['image_thumb'] ?? get_image($item['image'])) ?>" srcset="<?= htmlSC($item['image_srcset'] ?? '') ?>" sizes="(max-width: 499px) 100vw, (max-width: 899px) 50vw, 33vw" data-image-fallback="<?= htmlSC(base_url('/assets/img/no-image.png')) ?>" onerror="this.onerror=null;this.removeAttribute('srcset');this.src=this.dataset.imageFallback;" class="hover-effect-target w-100 h-100 object-fit-cover" width="<?= (int)($item['image_width'] ?: 416) ?>" height="<?= (int)($item['image_height'] ?: 305) ?>" alt="<?= htmlSC($item['title']) ?>" loading="lazy" decoding="async">
                        </a>
                        <div class="pt-4">
                            <div class="nav align-items-center gap-2 pb-2 mt-n1 mb-1">
                                <a class="nav-link text-body fs-xs text-uppercase p-0" href="<?= $categoryUrl($item['category_slug'] ?? $item['category']) ?>"><?= htmlSC($item['category_label'] ?? $item['category']) ?></a>
                                <hr class="vr my-1 mx-1">
                                <span class="text-body-tertiary fs-xs"><?= date('d.m.Y', strtotime($item['published_at'])) ?></span>
                            </div>
                            <h3 class="h5 mb-0">
                                <a class="hover-effect-underline" href="<?= $postUrl($item) ?>"><?= htmlSC($item['title']) ?></a>
                            </h3>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>

                <div class="swiper-pagination position-static mt-4"></div>
            </div>
        </div>
    <?php endif; ?>
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
    <?= print_translation('posts_show_sidebar_open') ?>
</button>
