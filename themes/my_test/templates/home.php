<?php
/**
 * Home Template
 *
 * Available variables: $page, $posts, $settings, $user, $locale.
 */
?>
<section class="theme-section">
    <div class="theme-container">
        <h1><?= htmlSC($title ?? site_setting('site_title', SITE_NAME)) ?></h1>
        <p>This is the home page template for your new FIREBALL CMS theme.</p>
    </div>
</section>