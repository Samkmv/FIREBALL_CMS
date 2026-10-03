<?php
/**
 * Sidebar Partial
 *
 * Pass data explicitly: render_partial('sidebar', ['items' => $items]).
 */
?>
<?php if (!empty($items)): ?>
    <aside class="theme-sidebar">
        <?php foreach ($items as $item): ?>
            <a href="<?= htmlSC($item['url'] ?? '#') ?>"><?= htmlSC($item['title'] ?? '') ?></a>
        <?php endforeach; ?>
    </aside>
<?php endif; ?>