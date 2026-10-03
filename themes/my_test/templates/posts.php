<?php
/** Posts listing: $posts, $pagination, $title. Other public pages fall back to default. */
?>
<section class="theme-section"><div class="theme-container">
    <h1><?= htmlSC($title ?? return_translation('posts_index_title')) ?></h1>
    <?php foreach (($posts ?? []) as $post): ?>
        <article class="theme-card">
            <h2><a href="<?= htmlSC($post['url'] ?? base_href('/posts/' . rawurlencode($post['slug'] ?? ''))) ?>"><?= htmlSC($post['title'] ?? '') ?></a></h2>
            <p><?= htmlSC($post['excerpt'] ?? '') ?></p>
        </article>
    <?php endforeach; ?>
    <?php if (empty($posts)): ?><p><?= htmlSC(return_translation('posts_index_empty')) ?></p><?php endif; ?>
    <?= $pagination ?? '' ?>
</div></section>