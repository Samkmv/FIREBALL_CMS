<?php
declare(strict_types=1);

// The player keeps its original complete HTML document, without a CMS wrapper.
if (str_starts_with(ltrim($this->content), '<!doctype html>')) {
    echo $this->content;
    return;
}

// Only an explicit device preference mounts the player on ordinary theme pages.
if (($_COOKIE['tr_site_player'] ?? '') === '1' && class_exists(\Fireball\ReelPlayer\Controllers\PlayerController::class)) {
    $site_page_content=$this->partial('site-page-open',get_defined_vars()).$this->content.$this->partial('site-page-close');
    extract((new \Fireball\ReelPlayer\Controllers\PlayerController())->pageData(),EXTR_SKIP);
    require dirname(__DIR__).'/includes/player-shell.php';
    return;
}

require dirname(__DIR__) . '/partials/header.php';
echo $this->content;
require dirname(__DIR__) . '/partials/footer.php';
