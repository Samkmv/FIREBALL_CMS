<?php
declare(strict_types=1);

// The player keeps its original complete HTML document, without a CMS wrapper.
if (str_starts_with(ltrim($this->content), '<!doctype html>')) {
    echo $this->content;
    return;
}

require dirname(__DIR__) . '/partials/header.php';
echo $this->content;
require dirname(__DIR__) . '/partials/footer.php';
