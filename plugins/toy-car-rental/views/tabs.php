<?php if (!empty($tabs)): ?>
    <nav class="d-flex flex-wrap toy-rental-tabs mb-4" aria-label="Toy rental navigation">
        <?php foreach ($tabs as $tab): ?>
            <a class="btn rounded-pill d-inline-flex align-items-center gap-2 <?= !empty($tab['active']) ? 'btn-dark' : 'btn-outline-secondary' ?>" href="<?= htmlSC((string)$tab['href']) ?>">
                <i class="<?= htmlSC((string)$tab['icon']) ?>"></i><?= htmlSC((string)$tab['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
