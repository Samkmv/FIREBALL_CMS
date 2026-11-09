<?php
$healthT = static fn(string $key): string => htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_' . $key));
?>
<section class="border rounded-5 p-3 p-md-4 mb-4" id="server-health">
    <h3 class="h6 mb-3"><?= $healthT('health_title') ?></h3>
    <?= $switch('smart_connect_health_enabled', FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_health_enabled'), FireballPluginVpnManagerV2::t('vpn_manager_v2_smart_health_help')) ?>
    <p class="small text-body-secondary mt-3 mb-0"><?= $healthT('health_scope') ?></p>
    <?php if (isset($healthReady) && !$healthReady): ?>
        <div class="alert alert-warning rounded-4 mt-3 mb-0"><?= $healthT('migration_required') ?></div>
    <?php elseif (!empty($serverHealth)): ?>
        <div class="row g-3 mt-1">
            <?php foreach ($serverHealth as $healthRow):
                $snapshot = json_decode((string)($healthRow['snapshot_json'] ?? ''), true);
                $snapshot = is_array($snapshot) ? $snapshot : [];
            ?>
                <div class="col-12 col-lg-6">
                    <div class="border rounded-4 p-3 h-100">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <span class="fw-semibold text-break"><?= htmlSC($healthRow['server_name']) ?></span>
                            <span class="badge text-bg-<?= $healthRow['state'] === 'healthy' ? 'success' : 'warning' ?>"><?= $healthT('health_' . $healthRow['state']) ?></span>
                        </div>
                        <div class="small text-body-secondary mt-2">
                            <?= $healthT('panel') ?>: <?= ($snapshot['panel'] ?? '') === 'online' ? $healthT('online') : $healthT('unavailable') ?> ·
                            Xray: <?= htmlSC((string)($snapshot['xray'] ?? 'unknown')) ?>
                            <?php if (is_numeric($snapshot['load']['cpu']['percent'] ?? null)): ?>
                                · CPU: <?= (float)$snapshot['load']['cpu']['percent'] ?>%
                            <?php endif; ?>
                        </div>
                        <?php foreach ((array)($snapshot['ports'] ?? []) as $port): ?>
                            <div class="small text-body-secondary text-break mt-1">
                                <?= $healthT('port') ?> <?= (int)($port['port'] ?? 0) ?> · <?= htmlSC((string)($port['transport'] ?? '')) ?> ·
                                TCP: <?= $healthT('tcp_' . (in_array($port['tcp'] ?? '', ['open', 'closed'], true) ? $port['tcp'] : 'unknown')) ?>
                            </div>
                        <?php endforeach; ?>
                        <dl class="small mb-0 mt-2">
                            <dt><?= $healthT('last_check') ?></dt><dd><?= htmlSC((string)$healthRow['last_check_at']) ?></dd>
                            <dt><?= $healthT('last_success') ?></dt><dd><?= htmlSC((string)($healthRow['last_success_at'] ?? '—')) ?></dd>
                            <dt><?= $healthT('failures') ?></dt><dd class="mb-0"><?= (int)$healthRow['consecutive_failures'] ?></dd>
                        </dl>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="small text-body-secondary mt-3"><?= $healthT('health_empty') ?></div>
    <?php endif; ?>
</section>
