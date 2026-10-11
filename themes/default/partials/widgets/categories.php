<?php
/** @var \App\Widgets\WidgetContext $context */
$categoryUrl = static fn(?string $slug = null): string => base_href('/posts') . ($slug === null || $slug === '' ? '' : '?category=' . rawurlencode($slug));
$current = $context->page === 'posts.show' ? (string)($context->get('post', [])['category_slug'] ?? $context->get('post', [])['category'] ?? '') : (string)$context->get('current_category', '');
?>
<h4 class="h6 mb-4"><?= htmlSC($settings['title'] ?: $context->translate('index_sidebar_categories', 'show_categories')) ?></h4>
<div class="d-flex flex-wrap gap-3">
    <a class="btn <?= $context->page !== 'posts.show' && $current === '' ? 'btn-dark' : 'btn-outline-secondary' ?> px-3" href="<?= htmlSC($categoryUrl()) ?>">
        <?= htmlSC($context->translate('index_all_categories', 'show_back')) ?> (<?= (int)$total ?>)
    </a>
    <?php foreach ($categories as $category): ?>
        <a class="btn <?= $current === (string)$category['slug'] ? 'btn-dark' : 'btn-outline-secondary' ?> px-3" href="<?= htmlSC($categoryUrl($category['slug'])) ?>">
            <?= htmlSC($category['label'] ?? $category['name']) ?> (<?= (int)($category['total'] ?? 0) ?>)
        </a>
    <?php endforeach; ?>
</div>
