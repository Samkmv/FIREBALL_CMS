<?php
/**
 * Sidebar Partial
 *
 * Pass data explicitly through render_partial().
 */
?>
<?php if (!empty($items)): ?>
    <aside class="vstack gap-2">
        <?php foreach ($items as $item): ?>
            <a href="<?= htmlSC($item['url'] ?? '#') ?>"><?= htmlSC($item['title'] ?? '') ?></a>
        <?php endforeach; ?>
    </aside>
<?php endif; ?>
