<?php

$postUrl = static fn(array $item): string => base_href('/posts/' . $item['slug']);
$categoryUrl = static fn(string $category): string => base_href('/posts') . '?category=' . rawurlencode($category);
$shareUrl = base_href('/posts/' . $post['slug']);
$shareTitle = rawurlencode($post['title']);
$shareLink = rawurlencode($shareUrl);
?>

<nav class="container pt-3 my-3 my-md-4" aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_href('/') ?>"><?= print_translation('tpl_menu_nav_index') ?></a></li>
        <li class="breadcrumb-item"><a href="<?= base_href('/posts') ?>"><?= print_translation('posts_show_breadcrumb') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= htmlSC($post['title']) ?></li>
    </ol>
</nav>

<section class="container pb-5 mb-2 mb-md-3 mb-lg-4 mb-xl-5">
    <div class="row g-4 g-lg-5">
        <div class="col-lg-8">
            <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                <span class="badge text-bg-dark rounded-pill"><?= htmlSC($post['category_label'] ?? $post['category']) ?></span>
                <span class="text-body-tertiary fs-sm"><?= date('d.m.Y', strtotime($post['published_at'])) ?></span>
                <span class="text-body-tertiary fs-sm"><?= print_translation('posts_show_author') ?> <?= htmlSC($post['author_label']) ?></span>
                <span class="text-body-tertiary fs-sm"><?= print_translation('posts_show_views') ?> <?= (int)$post['views_count'] ?></span>
            </div>

            <h1 class="display-6 mb-4"><?= htmlSC($post['title']) ?></h1>

            <?php if (!empty($post['has_image']) || (int)($post['hide_placeholder_image'] ?? 0) !== 1): ?>
                <div class="ratio rounded-5 overflow-hidden mb-4" style="--cz-aspect-ratio: calc(560 / 856 * 100%)">
                    <img src="<?= get_image($post['image']) ?>" alt="<?= htmlSC($post['title']) ?>" style="object-fit: cover;">
                </div>
            <?php endif; ?>

            <?php if (!empty($post['excerpt'])): ?>
                <p class="fs-lg text-body-secondary mb-4"><?= htmlSC($post['excerpt']) ?></p>
            <?php endif; ?>

            <div class="fs-base lh-lg">
                <?= $post['content'] ?>
            </div>

            <div class="d-sm-flex align-items-center justify-content-between py-4 py-md-5 mt-n2 mt-md-n3 mb-2 mb-sm-3 mb-md-0">
                <div class="d-flex flex-wrap gap-2 mb-4 mb-sm-0 me-sm-4">
                    <a class="btn btn-outline-secondary px-3 mt-1 me-1" href="<?= $categoryUrl($post['category_slug'] ?? $post['category']) ?>">
                        <?= htmlSC($post['category_label'] ?? $post['category']) ?>
                    </a>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="text-body-emphasis fs-sm fw-medium"><?= print_translation('posts_show_share') ?></div>
                    <a
                        class="btn btn-icon fs-base btn-outline-secondary border-0"
                        href="https://x.com/intent/tweet?text=<?= $shareTitle ?>&url=<?= $shareLink ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        data-bs-toggle="tooltip"
                        data-bs-template="<div class=&quot;tooltip fs-xs mb-n2&quot; role=&quot;tooltip&quot;><div class=&quot;tooltip-inner bg-transparent text-body p-0&quot;></div></div>"
                        aria-label="Share on X"
                        data-bs-original-title="X (Twitter)"
                    >
                        <i class="ci-x"></i>
                    </a>
                    <a
                        class="btn btn-icon fs-base btn-outline-secondary border-0"
                        href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareLink ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        data-bs-toggle="tooltip"
                        data-bs-template="<div class=&quot;tooltip fs-xs mb-n2&quot; role=&quot;tooltip&quot;><div class=&quot;tooltip-inner bg-transparent text-body p-0&quot;></div></div>"
                        aria-label="Share on Facebook"
                        data-bs-original-title="Facebook"
                    >
                        <i class="ci-facebook"></i>
                    </a>
                    <a
                        class="btn btn-icon fs-base btn-outline-secondary border-0"
                        href="https://t.me/share/url?url=<?= $shareLink ?>&text=<?= $shareTitle ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                        data-bs-toggle="tooltip"
                        data-bs-template="<div class=&quot;tooltip fs-xs mb-n2&quot; role=&quot;tooltip&quot;><div class=&quot;tooltip-inner bg-transparent text-body p-0&quot;></div></div>"
                        aria-label="Share on Telegram"
                        data-bs-original-title="Telegram"
                    >
                        <i class="ci-telegram"></i>
                    </a>
                </div>
            </div>

            <div class="border-top mt-5 pt-4">
                <a class="btn btn-outline-secondary rounded-pill" href="<?= base_href('/posts') ?>">
                    <i class="ci-chevron-left fs-base me-2"></i>
                    <?= print_translation('posts_show_back') ?>
                </a>
            </div>
        </div>

        <aside class="col-lg-4 col-xl-3 offset-xl-1">
            <div class="sticky-lg-top" style="top: 110px">
                <div class="border rounded-5 p-4 mb-4">
                    <h2 class="h6 mb-3"><?= print_translation('posts_show_categories') ?></h2>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($categories as $category): ?>
                            <a class="btn btn-outline-secondary rounded-pill" href="<?= $categoryUrl($category['slug']) ?>">
                                <?= htmlSC($category['label'] ?? $category['name']) ?> (<?= (int)$category['total'] ?>)
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (!empty($trending_posts)): ?>
                    <div class="border rounded-5 p-4">
                        <h2 class="h6 mb-3"><?= print_translation('posts_show_recent') ?></h2>

                        <?php foreach ($trending_posts as $item): ?>
                            <article class="hover-effect-scale position-relative d-flex align-items-center border-bottom py-3">
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
                                <div class="ratio w-100 rounded-3 overflow-hidden" style="max-width: 86px; --cz-aspect-ratio: calc(64 / 86 * 100%)">
                                    <img src="<?= get_image($item['image']) ?>" alt="<?= htmlSC($item['title']) ?>" style="object-fit: cover;">
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </aside>

        <div class="container pb-5 mb-1 mb-sm-2 mb-md-3 mb-lg-4 mb-xl-5">
            <h2 class="h3 text-center pb-2 pb-sm-3">Популярные</h2>
            <div class="swiper swiper-initialized swiper-horizontal swiper-backface-hidden" data-swiper="{
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
                <div class="swiper-wrapper" id="swiper-wrapper-0a49d257939271bd" aria-live="polite">

                    <!-- Article -->
                    <article class="swiper-slide swiper-slide-active" style="width: 416px; margin-right: 24px;" role="group" aria-label="1 / 3">
                        <a class="ratio d-flex hover-effect-scale rounded overflow-hidden" href="#!" style="--cz-aspect-ratio: calc(305 / 416 * 100%)">
                            <img src="assets/img/blog/grid/v1/07.jpg" class="hover-effect-target" alt="Image">
                        </a>
                        <div class="pt-4">
                            <div class="nav align-items-center gap-2 pb-2 mt-n1 mb-1">
                                <a class="nav-link text-body fs-xs text-uppercase p-0" href="#!">IoT</a>
                                <hr class="vr my-1 mx-1">
                                <span class="text-body-tertiary fs-xs">August 23, 2024</span>
                            </div>
                            <h3 class="h5 mb-0">
                                <a class="hover-effect-underline" href="#!">Connecting the dots: How IoT technology is transforming everyday life</a>
                            </h3>
                        </div>
                    </article>

                    <!-- Article -->
                    <article class="swiper-slide swiper-slide-next" style="width: 416px; margin-right: 24px;" role="group" aria-label="2 / 3">
                        <a class="ratio d-flex hover-effect-scale rounded overflow-hidden" href="#!" style="--cz-aspect-ratio: calc(305 / 416 * 100%)">
                            <img src="assets/img/blog/grid/v1/08.jpg" class="hover-effect-target" alt="Image">
                        </a>
                        <div class="pt-4">
                            <div class="nav align-items-center gap-2 pb-2 mt-n1 mb-1">
                                <a class="nav-link text-body fs-xs text-uppercase p-0" href="#!">Buying guides</a>
                                <hr class="vr my-1 mx-1">
                                <span class="text-body-tertiary fs-xs">August 18, 2024</span>
                            </div>
                            <h3 class="h5 mb-0">
                                <a class="hover-effect-underline" href="#!">How to find the best deals and make secure transactions online</a>
                            </h3>
                        </div>
                    </article>

                    <!-- Article -->
                    <article class="swiper-slide" style="width: 416px; margin-right: 24px;" role="group" aria-label="3 / 3">
                        <a class="ratio d-flex hover-effect-scale rounded overflow-hidden" href="#!" style="--cz-aspect-ratio: calc(305 / 416 * 100%)">
                            <img src="assets/img/blog/grid/v1/10.jpg" class="hover-effect-target" alt="Image">
                        </a>
                        <div class="pt-4">
                            <div class="nav align-items-center gap-2 pb-2 mt-n1 mb-1">
                                <a class="nav-link text-body fs-xs text-uppercase p-0" href="#!">Gaming</a>
                                <hr class="vr my-1 mx-1">
                                <span class="text-body-tertiary fs-xs">July 27, 2024</span>
                            </div>
                            <h3 class="h5 mb-0">
                                <a class="hover-effect-underline" href="#!">Immersive worlds: A dive into the latest VR gear and experiences</a>
                            </h3>
                        </div>
                    </article>
                </div>

                <!-- Pagination (Bullets) -->
                <div class="swiper-pagination position-static mt-4 swiper-pagination-clickable swiper-pagination-bullets swiper-pagination-horizontal swiper-pagination-lock"><span class="swiper-pagination-bullet swiper-pagination-bullet-active" tabindex="0" role="button" aria-label="Go to slide 1" aria-current="true"></span></div>
                <span class="swiper-notification" aria-live="assertive" aria-atomic="true"></span></div>
        </div>

    </div>
</section>
