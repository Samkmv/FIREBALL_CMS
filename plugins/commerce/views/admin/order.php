<?php $tab = 'orders'; require __DIR__ . '/open.php'; ?>
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <section class="fb-card p-3 mb-3">
            <h2 class="h5"><?= $t('summary') ?></h2>
            <?php require __DIR__ . '/../order-items.php'; ?>
        </section>
        <section class="fb-card p-3">
            <h2 class="h5"><?= $t('order_events') ?></h2>
            <?php if ($events === []): ?><p class="text-body-secondary mb-0"><?= $t('empty_events') ?></p><?php endif; ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($events as $event): ?>
                    <li class="list-group-item px-0 d-flex flex-wrap justify-content-between gap-2">
                        <span><?= $t($event['event_type'] === 'status' ? 'status_' . $event['new_value'] : 'payment_' . $event['new_value']) ?></span>
                        <span class="small text-body-secondary"><?= htmlSC($event['admin_name'] ?? '—') ?> · <?= htmlSC($event['created_at']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>
    <div class="col-lg-4">
        <section class="fb-card p-3 mb-3">
            <h2 class="h5"><?= $t('customer') ?></h2>
            <dl class="mb-0 text-break">
                <dt><?= $t('name') ?></dt><dd><?= htmlSC($order['customer_name']) ?></dd>
                <dt><?= $t('email') ?></dt><dd><?= htmlSC($order['customer_email']) ?></dd>
                <dt><?= $t('phone') ?></dt><dd><?= htmlSC($order['customer_phone']) ?></dd>
                <dt><?= $t($order['fulfillment']) ?></dt><dd><?= htmlSC($order['fulfillment_address'] ?: FireballPluginCommerce::t('pickup_address_pending')) ?></dd>
                <?php if ($order['customer_note'] !== ''): ?><dt><?= $t('note') ?></dt><dd><?= nl2br(htmlSC($order['customer_note'])) ?></dd><?php endif; ?>
            </dl>
        </section>
        <section class="fb-card p-3">
            <h2 class="h5"><?= $t('management') ?></h2>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge text-bg-primary"><?= $t('status_' . $order['status']) ?></span>
                <span class="badge text-bg-secondary"><?= $t('payment_' . $order['payment_status']) ?></span>
            </div>
            <?php foreach (['status' => $statuses, 'payment_status' => $payment] as $type => $options): ?>
                <?php if ($options === []) { continue; } ?>
                <form method="post" action="<?= $url('/admin/commerce/orders/' . (int)$order['id']) ?>" class="mb-3">
                    <?= get_csrf_field() ?>
                    <input type="hidden" name="type" value="<?= htmlSC($type) ?>">
                    <label class="form-label" for="commerce-change-<?= htmlSC($type) ?>"><?= $t($type === 'status' ? 'change_status' : 'change_payment') ?></label>
                    <div class="input-group">
                        <select id="commerce-change-<?= htmlSC($type) ?>" class="form-select" name="value" required>
                            <option value=""><?= $t('choose_action') ?></option>
                            <?php foreach ($options as $value): ?><option value="<?= htmlSC($value) ?>"><?= $t(($type === 'status' ? 'action_' : 'payment_action_') . $value) ?></option><?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-secondary" type="submit"><?= $t('apply') ?></button>
                    </div>
                </form>
            <?php endforeach; ?>
            <p class="small text-body-secondary mb-0"><?= htmlSC($order['created_at']) ?></p>
        </section>
    </div>
</div>
<?= view()->renderPartial('admin/shell_close') ?>
