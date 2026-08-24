<?php
$tabs = is_array($tabs ?? null) ? $tabs : [];
$primaryKeys = ['overview', 'servers', 'plans', 'subscriptions', 'connections'];
$primaryTabs = array_values(array_filter(
    $tabs,
    static fn(array $tab): bool => in_array((string)($tab['key'] ?? ''), $primaryKeys, true)
));
$secondaryTabs = array_values(array_filter(
    $tabs,
    static fn(array $tab): bool => !in_array((string)($tab['key'] ?? ''), $primaryKeys, true)
));
$secondaryActive = array_filter($secondaryTabs, static fn(array $tab): bool => !empty($tab['active'])) !== [];
?>
<?php if ($tabs !== []): ?>
    <nav class="d-flex flex-wrap gap-2 mb-4" aria-label="<?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_navigation_label')) ?>">
        <?php foreach ($primaryTabs as $tab): ?>
            <a class="btn rounded-pill d-inline-flex align-items-center gap-2 <?= !empty($tab['active']) ? 'btn-dark' : 'btn-outline-secondary' ?>"
               href="<?= htmlSC((string)$tab['href']) ?>">
                <i class="<?= htmlSC((string)$tab['icon']) ?>" aria-hidden="true"></i>
                <span><?= htmlSC((string)$tab['label']) ?></span>
            </a>
        <?php endforeach; ?>
        <?php if ($secondaryTabs !== []): ?>
            <div class="dropdown">
                <button class="btn rounded-pill d-inline-flex align-items-center gap-2 dropdown-toggle <?= $secondaryActive ? 'btn-dark' : 'btn-outline-secondary' ?>"
                        type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="ci-menu" aria-hidden="true"></i>
                    <span><?= htmlSC(FireballPluginVpnManagerV2::t('vpn_manager_v2_tab_more')) ?></span>
                </button>
                <ul class="dropdown-menu shadow-sm rounded-4 p-2">
                    <?php foreach ($secondaryTabs as $tab): ?>
                        <li>
                            <a class="dropdown-item rounded-3 d-flex align-items-center gap-2 <?= !empty($tab['active']) ? 'active' : '' ?>"
                               href="<?= htmlSC((string)$tab['href']) ?>">
                                <i class="<?= htmlSC((string)$tab['icon']) ?>" aria-hidden="true"></i>
                                <span><?= htmlSC((string)$tab['label']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </nav>
<?php endif; ?>
