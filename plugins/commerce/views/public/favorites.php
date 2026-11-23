<?php require __DIR__ . '/open.php'; ?>
<header class="d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom pb-3 mb-4">
    <h1 class="h3 mb-0"><?= $t('favorites') ?></h1>
    <a href="<?= $url('/shop/home#commerce-popular') ?>"><?= $t('continue_shopping') ?></a>
</header>
<div data-commerce-favorites-page>
    <p class="text-body-secondary" data-commerce-favorites-empty <?= $products === [] ? '' : 'hidden' ?>><?= $t('empty_favorites') ?></p>
    <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-4" data-commerce-favorites-grid>
        <?php foreach ($products as $product): ?>
            <div class="col" data-commerce-favorite-column><?php require __DIR__ . '/popular-card.php'; ?></div>
        <?php endforeach; ?>
    </div>
</div>
</div>
