<nav class="admin-pagination-nav" aria-label="<?= htmlSC(return_translation('pagination_label')) ?>">
    <ul class="pagination">

        <?php if (!empty($back)): ?>
            <li class="page-item">
                <a class="page-link" href="<?= htmlSC($back); ?>" aria-label="<?= htmlSC(return_translation('pagination_previous')) ?>">
                    <span aria-hidden="true">&lt;</span>
                </a>
            </li>
        <?php endif; ?>

        <?php if (!empty($pages)): ?>
            <?php foreach ($pages as $page): ?>
                <?php if (!empty($page['ellipsis'])): ?>
                    <li class="page-item disabled admin-pagination-ellipsis" aria-hidden="true">
                        <span class="page-link">&hellip;</span>
                    </li>
                <?php elseif (!empty($page['active'])): ?>
                    <li class="page-item active">
                        <a class="page-link" aria-current="page"><?= (int)$page['number']; ?></a>
                    </li>
                <?php else: ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= htmlSC($page['link']); ?>">
                            <?= (int)$page['number']; ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($forward)): ?>
            <li class="page-item">
                <a class="page-link" href="<?= htmlSC($forward); ?>" aria-label="<?= htmlSC(return_translation('pagination_next')) ?>">
                    <span aria-hidden="true">&gt;</span>
                </a>
            </li>
        <?php endif; ?>

    </ul>
</nav>
