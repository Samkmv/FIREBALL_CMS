<?php

$productUrl = static fn(array $product): string => base_href('/product/' . $product['slug']);
$postUrl = static fn(array $post): string => base_href('/posts/' . $post['slug']);
?>

<nav class="container pt-3 my-3 my-md-4" aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_href('/') ?>"><?= print_translation('tpl_menu_nav_index') ?></a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= print_translation('search_index_breadcrumb') ?></li>
    </ol>
</nav>

<section class="container pb-5 mb-2 mb-md-3 mb-lg-4 mb-xl-5">
    <div class="row justify-content-center">
        <div class="col-xl-10">
            <div class="border rounded-5 p-4 p-md-5 mb-4 mb-lg-5">
                <h1 class="h3 mb-3"><?= print_translation('search_index_heading') ?></h1>
                <form action="<?= base_href('/search') ?>" method="get" class="position-relative">
                    <input
                        type="search"
                        name="q"
                        value="<?= htmlSC($search_query) ?>"
                        class="form-control form-control-lg rounded-pill pe-5"
                        placeholder="<?= print_translation('tpl_menu_search') ?>"
                        aria-label="<?= print_translation('search_index_heading') ?>"
                    >
                    <button type="submit" class="btn btn-icon btn-ghost fs-lg btn-secondary border-0 position-absolute top-50 end-0 translate-middle-y rounded-circle me-2" aria-label="Search button">
                        <i class="ci-search"></i>
                    </button>
                </form>

                <?php if ($search_query !== ''): ?>
                    <p class="text-body-secondary mt-3 mb-0">
                        <?= print_translation('search_index_found') ?>: <strong><?= (int)$total_results ?></strong>
                    </p>
                <?php else: ?>
                    <p class="text-body-secondary mt-3 mb-0"><?= print_translation('search_index_hint') ?></p>
                <?php endif; ?>
            </div>

            <?php if ($search_query !== ''): ?>
                <?php if (!empty($products)): ?>
                    <div class="mb-5">
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
                            <h2 class="h4 mb-0"><?= print_translation('search_index_products') ?></h2>
                            <span class="text-body-tertiary"><?= (int)$products_total ?></span>
                        </div>

                        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
                            <?php foreach ($products as $product): ?>
                                <div class="col">
                                    <article class="border rounded-5 h-100 p-3 p-xl-4">
                                        <a class="ratio ratio-1x1 d-block mb-3" href="<?= $productUrl($product) ?>">
                                            <img src="<?= get_image($product['image']) ?>" alt="<?= htmlSC($product['title']) ?>" class="rounded-4" style="object-fit: cover;">
                                        </a>
                                        <h3 class="h6 mb-2">
                                            <a class="text-decoration-none" href="<?= $productUrl($product) ?>"><?= htmlSC($product['title']) ?></a>
                                        </h3>
                                        <?php if (!empty($product['excerpt'])): ?>
                                            <p class="text-body-secondary fs-sm mb-3"><?= htmlSC($product['excerpt']) ?></p>
                                        <?php endif; ?>
                                        <div class="d-flex align-items-center justify-content-between mt-auto">
                                            <div class="h6 mb-0">$<?= (int)$product['price'] ?></div>
                                            <a class="btn btn-outline-secondary btn-sm rounded-pill" href="<?= $productUrl($product) ?>">
                                                <?= print_translation('search_index_open') ?>
                                            </a>
                                        </div>
                                    </article>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if (!empty($products_pagination)): ?>
                            <div class="pt-4 mt-2">
                                <?= $products_pagination ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($posts)): ?>
                    <div class="mb-5">
                        <div class="d-flex align-items-center justify-content-between border-bottom pb-3 mb-4">
                            <h2 class="h4 mb-0"><?= print_translation('search_index_posts') ?></h2>
                            <span class="text-body-tertiary"><?= (int)$posts_total ?></span>
                        </div>

                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($posts as $post): ?>
                                <article class="border rounded-5 p-3 p-md-4">
                                    <div class="row g-3 align-items-center">
                                        <div class="col-md-3">
                                            <a class="ratio d-block rounded-4 overflow-hidden" href="<?= $postUrl($post) ?>" style="--cz-aspect-ratio: calc(180 / 240 * 100%)">
                                                <img src="<?= get_image($post['image']) ?>" alt="<?= htmlSC($post['title']) ?>" style="object-fit: cover;">
                                            </a>
                                        </div>
                                        <div class="col-md-9">
                                            <div class="d-flex flex-wrap gap-2 text-body-tertiary fs-xs mb-2">
                                                <span><?= htmlSC($post['category_label'] ?? $post['category']) ?></span>
                                                <span><?= date('d.m.Y', strtotime($post['published_at'])) ?></span>
                                            </div>
                                            <h3 class="h5 mb-2">
                                                <a class="text-decoration-none" href="<?= $postUrl($post) ?>"><?= htmlSC($post['title']) ?></a>
                                            </h3>
                                            <?php if (!empty($post['excerpt'])): ?>
                                                <p class="text-body-secondary mb-3"><?= htmlSC($post['excerpt']) ?></p>
                                            <?php endif; ?>
                                            <a class="btn btn-outline-secondary btn-sm rounded-pill" href="<?= $postUrl($post) ?>">
                                                <?= print_translation('search_index_open') ?>
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <?php if (!empty($posts_pagination)): ?>
                            <div class="pt-4 mt-2">
                                <?= $posts_pagination ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($total_results === 0): ?>
                    <div class="border rounded-5 p-5 text-center">
                        <h2 class="h5 mb-2"><?= print_translation('search_index_empty') ?></h2>
                        <p class="text-body-secondary mb-0"><?= print_translation('search_index_empty_desc') ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
