<?php
/**
 * Category Template
 *
 * Available variables: $category, $posts, $pagination.
 */
?>
<section class="theme-section">
    <div class="theme-container">
        <h1><?= htmlSC($category['name'] ?? '') ?></h1>
        <?php if (!empty($category['description'])): ?>
            <p class="theme-muted"><?= htmlSC($category['description']) ?></p>
        <?php endif; ?>
        <?php foreach ($posts ?? [] as $post): ?>
            <article>
                <h2><a href="<?= htmlSC($post['url'] ?? base_href('/posts/' . $post['slug'])) ?>"><?= htmlSC($post['title']) ?></a></h2>
                <p><?= htmlSC($post['excerpt'] ?? '') ?></p>
            </article>
        <?php endforeach; ?>
        <?= $pagination ?? '' ?>
    </div>
</section>