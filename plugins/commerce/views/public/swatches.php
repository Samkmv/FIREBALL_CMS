<?php
$swatches = $variants[mb_strtolower($product['variant_group'] ?? '')] ?? [];
if ($swatches === [] && !empty($product['color_hex'])) {
    $swatches = [$product];
}
?>
<?php if ($swatches !== []): ?>
    <div class="d-flex flex-wrap gap-1 mb-2" aria-label="<?= $t('variant_colors') ?>" role="group">
        <?php foreach ($swatches as $swatch): ?>
            <?php
            if (!preg_match('/\A#[0-9a-f]{6}\z/i', $swatch['color_hex'])) {
                continue;
            }
            $current = (int)$swatch['id'] === (int)$product['id'];
            ?>
            <a class="commerce-swatch<?= $current ? ' commerce-swatch-current' : '' ?>" href="<?= $url('/shop/product/' . $swatch['slug']) ?>"
               style="--commerce-swatch-color:<?= htmlSC($swatch['color_hex']) ?>" aria-label="<?= htmlSC($swatch['color_name']) ?>"
               title="<?= htmlSC($swatch['color_name']) ?>" <?= $current ? 'aria-current="true"' : '' ?>></a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
