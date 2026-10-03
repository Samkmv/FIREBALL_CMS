<?php
/**
 * Page Template
 *
 * Available variables: $page, $settings, $user, $locale.
 */
?>
<section class="theme-section">
    <div class="theme-container">
        <h1><?= htmlSC($page['title'] ?? $title ?? '') ?></h1>
        <div class="theme-content">
            <?= $page['content'] ?? '' ?>
        </div>
    </div>
</section>