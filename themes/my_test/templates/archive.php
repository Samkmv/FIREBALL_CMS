<?php
/**
 * Archive Template
 *
 * Available variables: $posts, $pagination.
 */
?>
<section class="theme-section">
    <div class="theme-container">
        <h1><?= htmlSC($title ?? 'Archive') ?></h1>
        <?php foreach ($posts ?? [] as $post): ?>
            <article>
                <h2><a href="<?= base_href('/posts/' . $post['slug']) ?>"><?= htmlSC($post['title']) ?></a></h2>
                <p><?= htmlSC($post['excerpt'] ?? '') ?></p>
            </article>
        <?php endforeach; ?>
        <?= $pagination ?? '' ?>
    </div>
</section>