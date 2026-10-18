<?php
$href=base_href($path);
$currentPath=rtrim((string)parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH),'/');
$linkPath=rtrim((string)parse_url($href,PHP_URL_PATH),'/');
$active=$current??($currentPath===$linkPath||str_starts_with($currentPath,$linkPath.'/'));
?>
<a class="nav-link<?= $active?' active':'' ?>" data-tr-nav-page href="<?= htmlSC($href) ?>"<?= $active?' aria-current="page"':'' ?>><?= $this->partial('site-icon',['name'=>$icon]) ?><span><?= htmlSC($label) ?></span></a>
