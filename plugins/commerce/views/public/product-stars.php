<span class="d-inline-flex gap-1 text-nowrap" aria-hidden="true">
    <?php for ($star = 1; $star <= 5; $star++): ?>
        <?php $filled = $stars >= $star; $half = !$filled && $stars >= $star - .5; ?>
        <i class="ci-star<?= $filled ? '-filled' : ($half ? '-half' : '') ?> <?= $filled || $half ? 'text-warning' : 'text-body-tertiary' ?>"></i>
    <?php endfor; ?>
</span>
