<?php
/**
 * Archive Template
 *
 * Available variables:
 * $posts
 * $pagination
 */
?>
<main class="container py-5">
    <h1 class="h2 mb-5"><?= htmlSC($title ?? 'Archive') ?></h1>

    <div class="vstack gap-4">
        <?php foreach ($posts ?? [] as $post): ?>
            <article class="border-bottom pb-4">
                <time class="text-body-secondary small" datetime="<?= htmlSC($post['published_at'] ?? '') ?>">
                    <?= !empty($post['published_at']) ? date('d.m.Y', strtotime($post['published_at'])) : '' ?>
                </time>
                <h2 class="h5 mt-2">
                    <a href="<?= base_href('/posts/' . $post['slug']) ?>"><?= htmlSC($post['title']) ?></a>
                </h2>
                <p class="mb-0"><?= htmlSC($post['excerpt'] ?? '') ?></p>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if (!empty($pagination)): ?>
        <div class="mt-5"><?= $pagination ?></div>
    <?php endif; ?>
</main>
