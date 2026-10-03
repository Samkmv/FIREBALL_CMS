<?php
/**
 * Post Template
 *
 * Available variables: $post, $author, $category, $settings, $user.
 */
?>
<article class="theme-section">
    <div class="theme-container">
        <h1><?= htmlSC($post['title'] ?? $title ?? '') ?></h1>
        <?php if (!empty($post['published_at'])): ?>
            <p class="theme-muted"><?= htmlSC(date('d.m.Y', strtotime($post['published_at']))) ?></p>
        <?php endif; ?>
        <div class="theme-content">
            <?= $post['content'] ?? '' ?>
        </div>
    </div>
</article>