<?php if ($page > 1 || $hasNext): ?>
    <nav class="d-flex gap-2 align-items-center mt-4" aria-label="<?= $t('pagination') ?>">
        <?php
        $pageUrl = static fn(int $p): string => htmlSC(base_href($paginationPath . '?' . http_build_query(
            array_filter($filters ?? [], static fn($value): bool => $value !== '') + ['page' => $p]
        )));
        ?>
        <?php if ($page > 1): ?><a class="btn btn-sm btn-outline-secondary" rel="prev" href="<?= $pageUrl($page - 1) ?>"><?= $t('previous') ?></a><?php endif; ?>
        <span class="small text-body-secondary" aria-current="page"><?= (int)$page ?></span>
        <?php if ($hasNext): ?><a class="btn btn-sm btn-outline-secondary" rel="next" href="<?= $pageUrl($page + 1) ?>"><?= $t('next') ?></a><?php endif; ?>
    </nav>
<?php endif; ?>
