<?php require __DIR__ . '/open.php'; ?>
<h1 class="mb-4"><?= $t('my_orders') ?></h1>
<?php if ($orders === []): ?>
    <div class="border rounded-4 p-4"><?= $t('empty_orders') ?></div>
<?php else: ?>
    <div class="list-group">
        <?php foreach ($orders as $order): ?>
            <a class="list-group-item list-group-item-action py-3" href="<?= $url('/shop/order/' . (int)$order['id']) ?>">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span class="fw-semibold"><?= $t('order') ?> #<?= (int)$order['id'] ?></span>
                    <span class="badge text-bg-dark"><?= $t('status_' . $order['status']) ?></span>
                    <strong><?= $money((int)$order['total_minor']) ?></strong>
                </div>
                <small class="text-body-secondary"><?= htmlSC($order['created_at']) ?></small>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php $paginationPath = '/profile/shop'; require __DIR__ . '/../pagination.php'; ?>
</div>
