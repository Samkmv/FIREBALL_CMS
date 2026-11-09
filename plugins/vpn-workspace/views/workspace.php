<?php

use Fireball\VpnManagerV2\Support\LocalizedValue;
use Fireball\VpnManagerV2\Support\ProvisioningStatus;

$t = static fn(string $key): string => htmlSC(return_translation('vpn_workspace_' . $key));
$base = '/admin/plugins/vpn-workspace';
$legacy = '/admin/plugins/vpn-manager-v2';
$url = static fn(string $path, array $params = []): string => htmlSC(
    base_href($path) . ($params === [] ? '' : '?' . http_build_query($params))
);
?>
<?= view()->renderPartial('admin/shell_open', ['title' => $title, 'subtitle' => return_translation('vpn_workspace_intro')]) ?>

<nav class="nav nav-pills gap-2 flex-wrap mb-4" aria-label="<?= htmlSC($title) ?>">
    <?php foreach ($sections as $item): ?>
        <a class="nav-link rounded-pill <?= $section === $item ? 'active' : '' ?>"
           <?= $section === $item ? 'aria-current="page"' : '' ?>
           href="<?= $url($base, ['section' => $item]) ?>"><?= $t($item) ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!$ready || $loadError): ?>
    <div class="alert alert-warning"><?= $t($ready ? 'load_error' : 'dependency') ?></div>
<?php else: ?>
    <section class="border rounded-4 p-3 p-md-4">
        <h2 class="h5 mb-3"><?= $t($section) ?></h2>
        <?php if ($section === 'subscriptions'): ?>
            <form method="get" action="<?= $url($base) ?>" class="d-flex flex-wrap align-items-end gap-2 mb-3">
                <input type="hidden" name="section" value="subscriptions">
                <div>
                    <label class="form-label" for="vpnWorkspaceSearch"><?= $t('search') ?></label>
                    <input class="form-control" id="vpnWorkspaceSearch" type="search" name="search" maxlength="120" value="<?= htmlSC($search) ?>">
                </div>
                <button class="btn btn-outline-primary" type="submit"><?= $t('find') ?></button>
            </form>
        <?php elseif ($section === 'errors'): ?>
            <p class="text-body-secondary"><?= $t('error_help') ?>
                <a href="<?= $url($legacy . '/sync-logs') ?>"><?= $t('full_log') ?></a>
            </p>
        <?php endif; ?>

        <?php if ($rows === []): ?>
            <p class="text-body-secondary mb-0"><?= $t('empty') ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr>
                        <th scope="col"><?= $t($section) ?></th>
                        <th scope="col"><?= $t('status') ?></th>
                        <th scope="col"><?= $t(match ($section) {
                            'servers' => 'last_check',
                            'plans' => 'targets',
                            'subscriptions' => 'expiration',
                            'errors' => 'details',
                        }) ?></th>
                        <th scope="col"><?= $t('open') ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach (array_slice($rows, 0, $section === 'subscriptions' ? 30 : count($rows)) as $row): ?>
                        <?php
                        $id = (int)($row['id'] ?? $row['server_id'] ?? 0);
                        $link = '';
                        if ($section === 'servers') {
                            $name = (string)$row['name'];
                            $state = empty($row['is_enabled']) ? 'disabled' : (string)$row['status'];
                            $status = !empty($row['is_enabled']) && !empty($row['maintenance_mode'])
                                ? return_translation('vpn_manager_v2_subscription_notice_maintenance')
                                : return_translation('vpn_manager_v2_status_' . $state);
                            $details = ($row['last_check_at'] ?: '—');
                            $link = $legacy . '/servers/' . $id . '/recovery';
                        } elseif ($section === 'plans') {
                            $name = (string)$row['name'];
                            $status = return_translation('vpn_manager_v2_plan_status_' . (!empty($row['is_active']) ? 'active' : 'disabled'));
                            $details = (int)$row['node_count'] . ' · ' . return_translation('vpn_workspace_mismatches')
                                . ' ' . (int)$row['mismatch_subscription_count'];
                            $link = $legacy . '/plans/edit/' . $id;
                        } elseif ($section === 'subscriptions') {
                            $name = (string)($row['client_display_name'] ?: ($row['user_name'] ?: '#' . $id));
                            $status = ProvisioningStatus::label(ProvisioningStatus::subscriptionStatus($row));
                            $details = (string)($row['expires_at'] ?: '—');
                            $link = $legacy . '/subscriptions/' . $id;
                        } else {
                            $name = (string)($row['server_name'] ?: ($id > 0 ? '#' . $id : '—'));
                            $status = LocalizedValue::operationType($row['operation_type']);
                            $details = (string)($row['safe_error'] ?: ($row['error_code'] ?: $row['status']));
                            if ((int)$row['subscription_id'] > 0) {
                                $link = $legacy . '/subscriptions/' . (int)$row['subscription_id'];
                            } elseif ($id > 0) {
                                $link = $legacy . '/servers/' . $id . '/recovery';
                            }
                        }
                        ?>
                        <tr>
                            <th scope="row">
                                <?= htmlSC($name) ?>
                                <?php if ($section === 'subscriptions' || $section === 'errors'): ?>
                                    <div class="small text-body-secondary fw-normal"><?= htmlSC((string)($row['plan_name'] ?? $row['created_at'] ?? '')) ?></div>
                                <?php endif; ?>
                            </th>
                            <td><?= htmlSC($status) ?></td>
                            <td class="text-break"><?= htmlSC($details) ?></td>
                            <td><?php if ($link !== ''): ?><a class="btn btn-sm btn-outline-secondary" href="<?= $url($link) ?>"><?= $t('open') ?></a><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <?php if ($section === 'subscriptions' && ($page > 1 || count($rows) > 30)): ?>
            <div class="d-flex gap-2 mt-3">
                <?php foreach (['previous' => $page > 1 ? $page - 1 : 0, 'next' => count($rows) > 30 ? $page + 1 : 0] as $direction => $destination): ?>
                    <?php if ($destination > 0): ?>
                        <a class="btn btn-outline-secondary" href="<?= $url($base, ['section' => $section, 'search' => $search, 'page' => $destination]) ?>"><?= $t($direction) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?= view()->renderPartial('admin/shell_close') ?>
