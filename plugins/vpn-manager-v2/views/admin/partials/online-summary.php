<?php
$t = static fn(string $key): string => FireballPluginVpnManagerV2::t('vpn_manager_v2_online_' . $key);
$summary = $snapshot['summary'] ?? [];
$noData = empty($summary) || ($summary['total_servers'] > 0 && $summary['checked'] === 0);
$cards = [
    'users' => 'ci-user',
    'connections' => 'ci-share-2',
    'subscriptions' => 'ci-link',
    'servers' => 'ci-server',
];
?>
<div data-vpn-online-summary>
    <?php if (!empty($summary['unavailable'])): ?>
        <p class="alert alert-warning py-2 mb-3" role="status"><?= htmlSC(sprintf($t('partial'), $summary['unavailable'])) ?></p>
    <?php endif; ?>
    <section class="row g-2 mb-3" aria-label="<?= htmlSC($t('summary')) ?>">
        <?php foreach ($cards as $key => $icon): ?>
            <div class="col-6 col-xl-3">
                <div class="fb-card d-flex flex-column gap-1 h-100 px-3 py-2">
                    <span class="small text-body-secondary"><i class="<?= htmlSC($icon) ?> me-1" aria-hidden="true"></i><?= htmlSC($t($key)) ?></span>
                    <strong class="fs-5 mt-auto" data-vpn-online-stat="<?= htmlSC($key) ?>"><?= $noData ? '—' : (int)$summary[$key] ?></strong>
                </div>
            </div>
        <?php endforeach; ?>
    </section>
    <?php if ($summary !== []): ?>
        <div class="d-flex flex-wrap align-items-start justify-content-between column-gap-3 row-gap-1 small mb-3">
            <?php if ($snapshot['servers'] !== []): ?>
                <details class="flex-grow-1" data-vpn-online-servers>
                    <summary class="text-body-secondary"><?= htmlSC(sprintf($t('checked'), $summary['checked'], $summary['total_servers'])) ?></summary>
                    <ul class="list-unstyled d-flex flex-wrap gap-2 mt-2 mb-0">
                        <?php foreach ($snapshot['servers'] as $server): ?>
                            <li class="d-inline-flex flex-wrap align-items-center gap-2 rounded-3 border px-2 py-1"
                                title="<?= htmlSC((string)($server['checked_at'] ?? '')) ?>">
                                <span class="fw-medium text-break"><?= htmlSC($server['name']) ?></span>
                                <span class="badge rounded-pill <?= !$server['available'] ? 'text-bg-warning' : ($server['online'] > 0 ? 'text-bg-success' : 'text-bg-secondary') ?>">
                                    <?= $server['available'] ? (int)$server['online'] : htmlSC($t('unknown')) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
            <span class="text-body-secondary ms-auto"><?= htmlSC($t('updated')) ?> <time><?= htmlSC($snapshot['updated_at']) ?></time></span>
        </div>
    <?php endif; ?>
</div>
