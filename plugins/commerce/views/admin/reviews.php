<?php $tab = 'reviews'; require __DIR__ . '/open.php'; ?>
<div class="nav nav-pills gap-2 mb-4" aria-label="<?= $t('reviews') ?>">
    <?php foreach (\Fireball\Commerce\Reviews::STATUSES as $key): ?>
        <a class="nav-link<?= $status === $key ? ' active' : '' ?>" href="<?= $url('/admin/commerce/reviews?status=' . $key) ?>" <?= $status === $key ? 'aria-current="page"' : '' ?>><?= $t('review_status_' . $key) ?></a>
    <?php endforeach; ?>
</div>
<?php if ($reviews === []): ?>
    <p class="fb-card p-3 mb-0 text-body-secondary"><?= $t('empty_reviews') ?></p>
<?php endif; ?>
<div class="d-grid gap-3">
    <?php foreach ($reviews as $review): ?>
        <article class="fb-card p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h2 class="h6 mb-0 text-break"><a href="<?= $url('/shop/product/' . $review['slug'] . '/reviews') ?>"><?= htmlSC($review['product']) ?></a></h2>
                <span class="badge rounded-pill text-bg-secondary"><?= (int)$review['rating'] ?> / 5</span>
            </div>
            <p class="text-body-secondary fs-sm mb-2"><?= htmlSC($review['author']) ?> · <?= htmlSC($review['created_at']) ?></p>
            <p class="text-break"><?= nl2br(htmlSC($review['body'])) ?></p>
            <form method="post" class="d-flex flex-wrap gap-2" action="<?= $url('/admin/commerce/reviews/' . (int)$review['id']) ?>">
                <?= get_csrf_field() ?>
                <?php if ($status !== 'published'): ?><button class="btn btn-sm btn-primary" name="status" value="published"><?= $t('approve_review') ?></button><?php endif; ?>
                <?php if ($status !== 'hidden'): ?><button class="btn btn-sm btn-outline-secondary" name="status" value="hidden"><?= $t('hide_review') ?></button><?php endif; ?>
            </form>
        </article>
    <?php endforeach; ?>
</div>
<?php $paginationPath = '/admin/commerce/reviews'; $filters = ['status' => $status]; require __DIR__ . '/../pagination.php'; ?>
<?= view()->renderPartial('admin/shell_close') ?>
