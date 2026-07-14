<?php if (!empty($tabs)): ?>
    <nav class="d-flex flex-wrap gap-2 mb-4" aria-label="VPN Manager V2 navigation">
        <?php foreach ($tabs as $tab): ?>
            <a class="btn rounded-pill d-inline-flex align-items-center gap-2 <?= !empty($tab['active']) ? 'btn-dark' : 'btn-outline-secondary' ?>"
               href="<?= htmlSC((string)$tab['href']) ?>">
                <i class="<?= htmlSC((string)$tab['icon']) ?>" aria-hidden="true"></i>
                <span><?= htmlSC((string)$tab['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
