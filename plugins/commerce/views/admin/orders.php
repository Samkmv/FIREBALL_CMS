<?php
$tab = 'orders';
require __DIR__ . '/open.php';
$rows = $mobile = [];
foreach ($orders as $order) {
    $link = '<a class="fw-semibold" href="' . $url('/admin/commerce/orders/' . (int)$order['id']) . '">#' . (int)$order['id'] . '</a>';
    $badgeTone = match ($order['status']) {
        'new' => 'primary', 'completed' => 'success', 'cancelled' => 'secondary', default => 'info',
    };
    $badge = '<span class="badge rounded-pill text-bg-' . $badgeTone . '">' . $t('status_' . $order['status']) . '</span>';
    $rows[] = ['cells' => [
        ['html' => $link], ['value' => $order['customer_name']], ['html' => $badge],
        ['value' => FireballPluginCommerce::t('payment_' . $order['payment_status'])],
        ['html' => $money((int)$order['total_minor'])], ['value' => $order['created_at']],
    ]];
    $mobile[] = ['title' => ['html' => $link . ' · ' . htmlSC($order['customer_name'])], 'status' => [['html' => $badge]], 'extra_fields' => [
        ['label' => FireballPluginCommerce::t('paid_label'), 'value' => FireballPluginCommerce::t('payment_' . $order['payment_status'])],
        ['label' => FireballPluginCommerce::t('total'), 'html' => $money((int)$order['total_minor'])],
        ['label' => FireballPluginCommerce::t('date'), 'value' => $order['created_at']],
    ]];
}
?>
<form class="d-flex flex-wrap gap-2 mb-3" method="get" action="<?= $url('/admin/commerce/orders') ?>">
    <label class="visually-hidden" for="commerce-order-search"><?= $t('search') ?></label>
    <input class="form-control w-auto" id="commerce-order-search" name="q" type="search" value="<?= htmlSC($query) ?>" placeholder="<?= $t('order_search') ?>">
    <label class="visually-hidden" for="commerce-order-status"><?= $t('status') ?></label>
    <select id="commerce-order-status" name="status" class="form-select w-auto">
        <option value=""><?= $t('all_statuses') ?></option>
        <?php foreach (\Fireball\Commerce\Orders::STATUSES as $value): ?>
            <option value="<?= htmlSC($value) ?>" <?= $value === $status ? 'selected' : '' ?>><?= $t('status_' . $value) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-secondary" type="submit"><?= $t('apply') ?></button>
</form>
<?= view()->renderPartial('admin/partials/table', [
    'columns' => array_map(static fn(string $key): array => ['label' => FireballPluginCommerce::t($key)], ['order', 'customer', 'status', 'paid_label', 'total', 'date']),
    'rows' => $rows, 'mobile_cards' => $mobile, 'empty_text' => FireballPluginCommerce::t('empty_orders'),
]) ?>
<?php $paginationPath = '/admin/commerce/orders'; $filters = ['q' => $query, 'status' => $status]; require __DIR__ . '/../pagination.php'; ?>
<?= view()->renderPartial('admin/shell_close') ?>
