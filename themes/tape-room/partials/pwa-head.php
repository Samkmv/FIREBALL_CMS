<?php
declare(strict_types=1);

// Preserve CMS PWA metadata and owner-configured icons, with local copies of
// the original player's built-in icons so no default theme assets are loaded.
$head = function_exists('pwa_head_tags') ? pwa_head_tags() : '';
echo str_replace([
    base_url('/assets/default/icons/fireball-cms.svg'),
    base_url('/assets/default/pwa/apple-touch-icon.png'),
], [
    theme_asset('images/fireball-cms.svg'),
    theme_asset('images/apple-touch-icon.png'),
], $head);
