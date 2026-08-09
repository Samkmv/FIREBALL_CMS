<?php require __DIR__ . '/shell-open.php'; ?>
    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['subscriptions_stat_active', (int)$stats['active'], 'ci-check-circle'],
            ['subscriptions_stat_expiring', (int)$stats['expiring'], 'ci-clock'],
            ['subscriptions_stat_revenue', \Fireball\Subscriptions\Support\Money::display((int)$stats['paid_total_minor']), 'ci-credit-card'],
            ['subscriptions_stat_failed', (int)$stats['failed'], 'ci-alert-triangle'],
        ];
        ?>
        <?php foreach ($cards as [$label, $value, $icon]): ?>
            <div class="col-sm-6 col-xl-3">
                <div class="border rounded-4 p-4 h-100">
                    <i class="<?= htmlSC($icon) ?> fs-3 text-body-tertiary"></i>
                    <div class="display-6 fw-semibold mt-3"><?= htmlSC((string)$value) ?></div>
                    <div class="text-body-secondary"><?= htmlSC(FireballPluginSubscriptions::t($label)) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="border rounded-4 p-4">
        <h2 class="h5 mb-3"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_distribution_title')) ?></h2>
        <?php if (!$by_plan): ?>
            <p class="text-body-secondary mb-0"><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_empty')) ?></p>
        <?php else: ?>
            <div class="vstack gap-2">
                <?php foreach ($by_plan as $item): ?>
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span><?= htmlSC((string)$item['name']) ?></span>
                        <strong><?= (int)$item['total'] ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php require __DIR__ . '/shell-close.php'; ?>
