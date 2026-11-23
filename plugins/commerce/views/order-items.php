<div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead><tr><th scope="col"><?= $t('product') ?></th><th scope="col" class="text-end"><?= $t('quantity') ?></th><th scope="col" class="text-end"><?= $t('total') ?></th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td class="text-break"><?= htmlSC($item['name']) ?><span class="d-block small text-body-secondary"><?= htmlSC($item['sku']) ?> · <?= $money((int)$item['unit_minor']) ?></span></td>
                <td class="text-end"><?= (int)$item['quantity'] ?></td>
                <td class="text-end text-nowrap"><?= $money((int)$item['unit_minor'] * (int)$item['quantity']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><th scope="row" colspan="2"><?= $t('delivery_cost') ?></th><td class="text-end"><?= $money((int)$order['delivery_minor']) ?></td></tr>
            <tr><th scope="row" colspan="2"><?= $t('total') ?></th><td class="text-end fw-bold text-nowrap"><?= $money((int)$order['total_minor']) ?></td></tr>
        </tfoot>
    </table>
</div>
