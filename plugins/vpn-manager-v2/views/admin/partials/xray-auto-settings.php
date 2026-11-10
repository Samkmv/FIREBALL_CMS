<?php
$xray = $settings['xray_auto'] ?? \Fireball\VpnManagerV2\Support\XrayAutoSettings::defaults();
$xrayT = static fn(string $key): string => htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_xray_' . $key));
?>
<section class="border rounded-5 p-3 p-md-4 mb-4" aria-labelledby="vpnXrayTitle">
    <h2 class="h5" id="vpnXrayTitle"><?= $xrayT('settings') ?></h2>
    <p class="text-body-secondary"><?= $xrayT('settings_help') ?></p>
    <div class="row g-3">
        <?php foreach (['enabled', 'export', 'visible', 'include_external'] as $key): ?>
            <div class="col-12 col-lg-6 form-check form-switch ps-5">
                <input type="hidden" name="xray_auto[<?= $key ?>]" value="0">
                <input class="form-check-input" type="checkbox" id="vpnXray-<?= $key ?>"
                       name="xray_auto[<?= $key ?>]" value="1" <?= $xray[$key] ? 'checked' : '' ?>>
                <label class="form-check-label" for="vpnXray-<?= $key ?>"><?= $xrayT($key) ?></label>
            </div>
        <?php endforeach; ?>
        <div class="col-12 col-md-6">
            <label for="vpnXrayStrategy" class="form-label"><?= $xrayT('strategy') ?></label>
            <select id="vpnXrayStrategy" name="xray_auto[strategy]" class="form-select">
                <option value="leastPing" <?= $xray['strategy'] === 'leastPing' ? 'selected' : '' ?>>leastPing</option>
                <option value="leastLoad" <?= $xray['strategy'] === 'leastLoad' ? 'selected' : '' ?>>leastLoad</option>
            </select>
        </div>
        <div class="col-12 col-md-6">
            <label for="vpnXrayFallback" class="form-label"><?= $xrayT('fallback') ?></label>
            <select id="vpnXrayFallback" name="xray_auto[fallback]" class="form-select">
                <option value="block" <?= $xray['fallback'] === 'block' ? 'selected' : '' ?>><?= $xrayT('block') ?></option>
                <option value="first" <?= $xray['fallback'] === 'first' ? 'selected' : '' ?>><?= $xrayT('first') ?></option>
            </select>
        </div>
        <?php foreach (['interval' => [10, 300], 'timeout' => [1, 15]] as $key => [$min, $max]): ?>
            <div class="col-12 col-md-6">
                <label for="vpnXray-<?= $key ?>" class="form-label"><?= $xrayT($key) ?></label>
                <input id="vpnXray-<?= $key ?>" name="xray_auto[<?= $key ?>]" class="form-control" type="number"
                       min="<?= $min ?>" max="<?= $max ?>" value="<?= (int)$xray[$key] ?>" required>
                <?php if ($key === 'timeout'): ?><div class="form-text"><?= $xrayT('timeout_help') ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <div class="col-12">
            <label for="vpnXrayProbe" class="form-label"><?= $xrayT('probe_url') ?></label>
            <input id="vpnXrayProbe" name="xray_auto[probe_url]" class="form-control" type="url" maxlength="512"
                   value="<?= htmlSC($xray['probe_url']) ?>" required>
        </div>
        <fieldset class="col-12">
            <legend class="form-label fs-6"><?= $xrayT('servers') ?></legend>
            <p class="form-text mt-0"><?= $xrayT('servers_help') ?></p>
            <div class="d-flex flex-wrap gap-3">
                <?php
                $xrayOptions = array_column($xrayServers ?? [], 'name', 'id');
                foreach ($xray['servers'] as $serverId) { $xrayOptions[$serverId] ??= '#' . $serverId; }
                ?>
                <?php foreach ($xrayOptions as $serverId => $serverName): ?>
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" name="xray_auto[servers][]" value="<?= (int)$serverId ?>"
                            <?= in_array((int)$serverId, $xray['servers'], true) ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= htmlSC((string)$serverName) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
    </div>
</section>
