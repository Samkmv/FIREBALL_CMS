<?php
$t = static fn(string $key): string => htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_discrepancies_' . $key));
$url = base_href('/admin/plugins/vpn-manager-v2/discrepancies');
$id = (int)($server['id'] ?? 0);
$recoveryUrl = $id > 0 ? base_href('/admin/plugins/vpn-manager-v2/servers/' . $id . '/recovery') : '';
?>
<?= view()->renderPartial('admin/shell_open', ['title' => $title]) ?>
<?php require __DIR__ . '/partials/tabs.php'; ?>

<section class="mb-3">
    <?php if ($servers !== []): ?>
        <form method="get" action="<?= htmlSC($url) ?>" class="d-flex flex-wrap align-items-end gap-2 mb-2">
            <div>
                <label class="form-label" for="vpnCompareServer"><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_col_server')) ?></label>
                <select class="form-select" name="server" id="vpnCompareServer" required>
                    <?php foreach ($servers as $option): ?>
                        <option value="<?= (int)$option['id'] ?>" <?= (int)$option['id'] === $id ? 'selected' : '' ?>>
                            <?= htmlSC((string)$option['name']) ?> · #<?= (int)$option['id'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-outline-secondary" type="submit"><?= $t('show') ?></button>
        </form>
    <?php endif; ?>
    <p class="small text-body-secondary mb-1"><?= $t('help') ?></p>
    <?php if ($server): ?>
        <div class="small text-body-secondary"><?= $t('last_sync') ?> · <?= htmlSC((string)(($server['last_sync_at'] ?? '') ?: '—')) ?></div>
    <?php endif; ?>
</section>

<?php if ($server): ?>
    <section class="mb-3" aria-labelledby="vpnV2DiscrepancyResult">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <h2 class="h5 mb-0 text-break" id="vpnV2DiscrepancyResult"><?= htmlSC((string)$server['name']) ?></h2>
            <a class="btn <?= $rows !== [] ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= htmlSC($recoveryUrl) ?>"><?= $t('recover') ?></a>
        </div>
        <?php if ($rows === []): ?>
            <div class="fb-empty-state"><p class="mb-0"><?= $t('empty') ?></p></div>
        <?php else: ?>
            <?php
            $tableRows = [];
            $mobileCards = [];
            foreach ($rows as $row) {
                $subscriptionId = (int)$row['id'];
                $name = trim((string)$row['customer_name']);
                $subscriptionLink = '<a class="fw-semibold text-break" href="'
                    . htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . $subscriptionId)) . '">#'
                    . $subscriptionId . ($name !== '' ? ' · ' . htmlSC($name) : '') . '</a>';
                $confirmed = (int)$row['confirmed_count'];
                $expected = (int)$row['expected_count'];
                $unavailable = (int)$row['unavailable_inbound_count'];
                $missing = (int)$row['missing_connection_count'];
                $unconfirmed = max(0, $expected - $confirmed - $unavailable - $missing);
                $issues = [];
                foreach (['reason_inbound' => $unavailable, 'reason_missing' => $missing, 'reason_unconfirmed' => $unconfirmed] as $key => $count) {
                    if ($count > 0) {
                        $issues[] = $t($key) . ' ' . $count;
                    }
                }
                $reason = implode(' · ', $issues);
                $countLabel = $confirmed . ' / ' . $expected;
                $tableRows[] = ['cells' => [
                    ['html' => $subscriptionLink],
                    ['value' => $row['plan_name']],
                    ['html' => $reason],
                    ['value' => $countLabel],
                ]];
                $mobileCards[] = [
                    'title' => ['html' => $subscriptionLink],
                    'extra_fields' => [
                        ['label' => $t('plan'), 'value' => $row['plan_name']],
                        ['label' => $t('reason'), 'html' => $reason],
                        ['label' => $t('coverage'), 'value' => $countLabel],
                    ],
                ];
            }
            ?>
            <p class="small text-body-secondary"><?= $t('recovery_next') ?></p>
            <?= view()->renderPartial('admin/partials/table', [
                'columns' => [
                    ['label' => $t('subscription')],
                    ['label' => $t('plan')],
                    ['label' => $t('reason')],
                    ['label' => $t('coverage')],
                ],
                'rows' => $tableRows,
                'mobile_cards' => $mobileCards,
            ]) ?>
        <?php endif; ?>
        <?php if ($page > 1 || $hasNext): ?>
            <div class="d-flex gap-2 mt-3">
                <?php if ($page > 1): ?><a class="btn btn-outline-secondary" rel="prev" href="<?= htmlSC($url . '?server=' . $id . '&page=' . ($page - 1)) ?>"><?= $t('previous') ?></a><?php endif; ?>
                <?php if ($hasNext): ?><a class="btn btn-outline-secondary" rel="next" href="<?= htmlSC($url . '?server=' . $id . '&page=' . ($page + 1)) ?>"><?= $t('next') ?></a><?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<div class="fb-card p-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
    <span><?= $t('obsolete_help') ?></span>
    <a class="btn btn-sm btn-outline-secondary" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/plans')) ?>"><?= $t('open_plans') ?></a>
</div>
<?= view()->renderPartial('admin/shell_close') ?>
