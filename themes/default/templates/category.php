<?php
/**
 * Category Template
 *
 * Available variables:
 * $category
 * $posts
 * $pagination
 */
?>
<main class="container py-5">
    <header class="mb-5">
        <h1 class="h2 mb-2"><?= htmlSC($category['name'] ?? '') ?></h1>
        <?php if (!empty($category['description'])): ?>
            <p class="text-body-secondary mb-0"><?= htmlSC($category['description']) ?></p>
        <?php endif; ?>
    </header>

    <div class="row row-cols-1 row-cols-md-2 g-4">
        <?php foreach ($posts ?? [] as $post): ?>
            <article class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-2 post-favorite-heading">
                            <h2 class="h5 mb-0 flex-grow-1">
                                <a href="<?= base_href('/posts/' . $post['slug']) ?>"><?php if (isset($post['subscription_access']) && empty($post['subscription_access']['allowed'])): ?><i class="ci-lock me-1" aria-hidden="true"></i><?php endif; ?><?= htmlSC($post['title']) ?></a>
                            </h2>
                            <?= $this->partial('favorite_button', ['post' => $post]) ?>
                        </div>
                        <p class="text-body-secondary mb-0"><?= htmlSC($post['excerpt'] ?? '') ?></p>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($pagination)): ?>
        <div class="mt-5"><?= $pagination ?></div>
    <?php endif; ?>
</main>
