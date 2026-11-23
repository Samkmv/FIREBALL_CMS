<?php require __DIR__ . '/context.php'; ?>
<?php foreach ($review['lines'] as $line): ?>
    <li class="d-flex align-items-center gap-2 py-2">
        <?php if (!empty($line['image_path'])): ?>
            <img class="commerce-checkout-thumb rounded-2" src="<?= $media($line['image_path']) ?>" alt="" width="44" height="44" loading="lazy">
        <?php else: ?>
            <span class="commerce-checkout-thumb rounded-2"><i class="ci-image" aria-hidden="true"></i></span>
        <?php endif; ?>
        <span class="min-w-0 flex-grow-1 text-break fs-sm"><?= htmlSC($line['name']) ?> <span class="text-body-secondary text-nowrap">×<?= (int)$line['quantity'] ?></span></span>
        <strong class="text-nowrap fs-sm"><?= $money((int)$line['line_minor']) ?></strong>
    </li>
<?php endforeach; ?>
