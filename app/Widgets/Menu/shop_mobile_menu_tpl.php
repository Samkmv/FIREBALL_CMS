<?php if ($item['parent_id'] == 0): ?>
    <div class="pt-4 h6"><?= htmlSC($item['title']) ?></div>
    <?php else: ?>
        <li class="d-flex w-100 pt-1"> <!-- классы d-flex w-100 - для отображения по списку -->
            <a class="nav-link animate-underline animate-target d-inline fw-normal text-truncate p-0" href="<?= base_href("/category/{$item['slug']}") ?>"><?= htmlSC($item['title']) ?></a>
        </li>
    <?php endif; ?>

    <?php if (isset($item['children'])): ?>
        <ul class="nav gap-2 mt-n2">
            <?= $this->getMenuHtml($item['children']) ?>
        </ul>
    <?php endif; ?>

    <?php if ($item['parent_id'] == 0): ?>

<?php endif; ?>
