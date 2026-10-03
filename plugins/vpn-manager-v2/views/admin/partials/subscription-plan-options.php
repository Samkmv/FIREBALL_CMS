<?php
use Fireball\VpnManagerV2\Support\TrafficFormatter;
foreach ($plans as $plan):
    $planTraffic = TrafficFormatter::inputParts(isset($plan['traffic_limit_bytes']) ? (int)$plan['traffic_limit_bytes'] : null);
?>
<option value="<?= (int)$plan['id'] ?>" <?= (int)($selectedPlanId ?? 0) === (int)$plan['id'] ? 'selected' : '' ?>
        data-duration-days="<?= (int)$plan['duration_days'] ?>"
        data-traffic-value="<?= htmlSC($planTraffic['value']) ?>"
        data-traffic-unit="<?= htmlSC($planTraffic['unit']) ?>">
    <?= htmlSC((string)$plan['name']) ?> ·
    <?php if (!empty($plan['unavailable'])): ?>
        <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_current_plan_unavailable')) ?>
    <?php else: ?>
        <?= (int)$plan['duration_days'] ?> <?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_days')) ?> ·
        <?= htmlSC(sprintf(FireballPluginVpnManagerV2::t('vpn_manager_v2_plan_devices_summary'), (int)$plan['device_limit'])) ?> ·
        <?= htmlSC(TrafficFormatter::limit(isset($plan['traffic_limit_bytes']) ? (int)$plan['traffic_limit_bytes'] : null)) ?>
    <?php endif; ?>
</option>
<?php endforeach; ?>
