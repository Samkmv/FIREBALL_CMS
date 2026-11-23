<h2 class="h4 mb-3"><?= $t('product_details') ?></h2>
<?php if ($product['description'] !== ''): ?>
    <p class="commerce-copy text-body-secondary mb-4"><?= htmlSC($product['description']) ?></p>
<?php endif; ?>
<dl class="commerce-specifications mb-0">
    <div class="d-flex flex-wrap justify-content-between gap-2 py-2 border-bottom">
        <dt><?= $t('sku') ?></dt><dd class="mb-0 text-break"><?= htmlSC($product['sku']) ?></dd>
    </div>
    <?php if (!empty($product['color_name'])): ?>
        <div class="d-flex flex-wrap justify-content-between gap-2 py-2 border-bottom">
            <dt><?= $t('color_name') ?></dt><dd class="mb-0 text-break"><?= htmlSC($product['color_name']) ?></dd>
        </div>
    <?php endif; ?>
    <?php foreach (preg_split('/\R/u', (string)($product['specifications'] ?? '')) ?: [] as $line): ?>
        <?php
        [$name, $value] = array_pad(array_map('trim', explode(':', $line, 2)), 2, '');
        if ($name === '') {
            continue;
        }
        ?>
        <div class="d-flex flex-wrap justify-content-between gap-2 py-2 border-bottom">
            <dt class="text-break"><?= htmlSC($name) ?></dt><dd class="mb-0 text-break"><?= htmlSC($value) ?></dd>
        </div>
    <?php endforeach; ?>
</dl>
