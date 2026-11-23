<?php
$t = static fn(string $key): string => htmlSC(FireballPluginCommerce::t($key));
$url = static fn(string $path): string => htmlSC(base_href($path));
$money = static fn(int $minor): string => htmlSC(\Fireball\Commerce\Input::money($minor));
?>
<?= view()->renderPartial('admin/shell_open', ['title' => $title, 'actions' => $actions ?? '']) ?>
<nav class="nav nav-underline flex-wrap column-gap-3 row-gap-0 border-bottom mb-4" aria-label="<?= $t('admin_title') ?>">
    <?php foreach (['' => 'overview', '/products' => 'products', '/categories' => 'categories', '/slides' => 'slides', '/reviews' => 'reviews', '/orders' => 'orders', '/settings' => 'settings'] as $path => $label): ?>
        <a class="nav-link<?= $tab === $label ? ' active' : '' ?>" <?= $tab === $label ? 'aria-current="page"' : '' ?>
           href="<?= $url('/admin/commerce' . $path) ?>"><?= $t($label) ?></a>
    <?php endforeach; ?>
</nav>
