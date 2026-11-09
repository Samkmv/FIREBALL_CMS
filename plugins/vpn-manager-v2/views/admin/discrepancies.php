<?php
$t = static fn(string $key): string => htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_discrepancies_' . $key));
$url = base_href('/admin/plugins/vpn-manager-v2/discrepancies');
$id = (int)($server['id'] ?? 0);
?>
<?= view()->renderPartial('admin/shell_open', ['title' => $title]) ?>
<?php require __DIR__ . '/partials/tabs.php'; ?>

<section class="border rounded-5 p-3 p-md-4 mb-4">
    <p class="text-body-secondary"><?= $t('help') ?></p>
    <?php if ($servers !== []): ?>
        <form method="get" action="<?= htmlSC($url) ?>" class="d-flex flex-wrap align-items-end gap-2">
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
            <button class="btn btn-outline-secondary rounded-pill" type="submit"><?= $t('show') ?></button>
            <?php if ($id): ?>
                <a class="btn btn-primary rounded-pill" href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/servers/' . $id . '/recovery')) ?>"><?= $t('recover') ?></a>
            <?php endif; ?>
        </form>
    <?php endif; ?>
    <?php if ($server): ?>
        <div class="small text-body-secondary mt-3"><?= $t('last_sync') ?> · <?= htmlSC((string)($server['last_sync_at'] ?: '—')) ?></div>
    <?php endif; ?>
</section>

<?php if ($server): ?>
    <section class="border rounded-5 p-3 p-md-4">
        <?php if ($rows === []): ?>
            <p class="mb-0 text-body-secondary"><?= $t('empty') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr>
                        <th scope="col"><?= $t('subscription') ?></th>
                        <th scope="col"><?= $t('plan') ?></th>
                        <th scope="col"><?= $t('expected') ?></th>
                        <th scope="col"><?= $t('confirmed') ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><a href="<?= htmlSC(base_href('/admin/plugins/vpn-manager-v2/subscriptions/' . (int)$row['id'])) ?>">#<?= (int)$row['id'] ?> · <?= htmlSC((string)$row['customer_name']) ?></a></td>
                            <td><?= htmlSC((string)$row['plan_name']) ?></td>
                            <td><?= (int)$row['expected_count'] ?></td>
                            <td><span class="badge text-bg-warning"><?= (int)$row['confirmed_count'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if ($page > 1 || $hasNext): ?>
            <div class="d-flex gap-2 mt-3">
                <?php if ($page > 1): ?><a class="btn btn-outline-secondary" href="<?= htmlSC($url . '?server=' . $id . '&page=' . ($page - 1)) ?>">←</a><?php endif; ?>
                <?php if ($hasNext): ?><a class="btn btn-outline-secondary" href="<?= htmlSC($url . '?server=' . $id . '&page=' . ($page + 1)) ?>">→</a><?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?= view()->renderPartial('admin/shell_close') ?>
