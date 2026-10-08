<?php require __DIR__ . '/shell-open.php'; ?>
    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['subscriptions_stat_active', (int)$stats['active'], 'ci-check-circle', 'green', '/admin/subscriptions/subscribers?status=active'],
            ['subscriptions_stat_expiring', (int)$stats['expiring'], 'ci-clock', 'purple', '/admin/subscriptions/subscribers'],
            ['subscriptions_stat_revenue', \Fireball\Subscriptions\Support\Money::display((int)$stats['paid_total_minor']), 'ci-credit-card', 'blue', '/admin/subscriptions/payments'],
            ['subscriptions_stat_failed', (int)$stats['failed'], 'ci-alert-triangle', 'primary', '/admin/subscriptions/payments'],
        ];
        ?>
        <?php foreach ($cards as [$label, $value, $icon, $tone, $href]): ?>
            <div class="col-12 col-sm-6 col-xl-3">
                <a class="fb-card fb-stat-card fb-vpn-stat-card is-<?= htmlSC($tone) ?> rounded-5 p-3 p-md-4 h-100 d-flex flex-column align-items-stretch gap-0 text-reset text-decoration-none" href="<?= htmlSC(base_href($href)) ?>">
                    <div class="d-flex align-items-center justify-content-between gap-2 w-100">
                        <div class="small text-body-secondary text-break min-w-0"><?= htmlSC(FireballPluginSubscriptions::t($label)) ?></div>
                        <span class="fb-stat-icon rounded-circle flex-shrink-0"><i class="<?= htmlSC($icon) ?>" aria-hidden="true"></i></span>
                    </div>
                    <div class="display-6 fw-semibold lh-1 mt-3 text-break"><?= htmlSC((string)$value) ?></div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="border rounded-5 p-3 p-md-4">
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
