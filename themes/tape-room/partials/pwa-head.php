<?php
declare(strict_types=1);

// Preserve owner-configured icons. Theme-owned defaults use the checksum of
// the actual theme file, rather than the unrelated core icon's cache key.
$head=function_exists('pwa_head_tags')?pwa_head_tags():'';
foreach ([
    '/assets/default/icons/fireball-cms.svg'=>'images/fireball-cms.svg',
    '/assets/default/pwa/apple-touch-icon.png'=>'images/apple-touch-icon.png',
    '/assets/default/pwa/icon-192.png'=>'images/app-icon-192.png',
] as $source=>$target) {
    $head=preg_replace_callback('~'.preg_quote(htmlSC(base_url($source)),'~').'(?:\?[^"\s]*)?~',static fn()=>htmlSC(theme_asset_versioned($target)),$head);
}
echo $head;
