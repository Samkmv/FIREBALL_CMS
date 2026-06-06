<?php

/**
 * Page Template
 *
 * Available variables:
 *
 * $page
 * $settings
 * $user
 * $locale
 */
?>

<main class="py-5">
    <div class="container">
        <div class="post-content fs-base lh-lg">
            <?= $page['content'] ?? '' ?>
        </div>
    </div>
</main>
