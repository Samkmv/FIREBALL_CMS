<?php
$navigationTabs = (array)($tabs ?? []);
$primaryKeys = ['overview', 'plans', 'subscribers', 'exclusions', 'payments'];
$primaryTabs = array_filter($navigationTabs, static fn(array $tab): bool => in_array($tab['key'] ?? '', $primaryKeys, true));
$secondaryTabs = array_filter($navigationTabs, static fn(array $tab): bool => !in_array($tab['key'] ?? '', $primaryKeys, true));
$secondaryActive = array_filter($secondaryTabs, static fn(array $tab): bool => !empty($tab['active'])) !== [];
?>
<nav class="d-flex flex-wrap gap-2 mb-4" aria-label="<?= htmlSC(FireballPluginSubscriptions::t('subscriptions_menu')) ?>" data-subscriptions-admin-nav>
    <?php foreach ($primaryTabs as $tab): ?>
        <a class="btn rounded-pill d-inline-flex align-items-center gap-2 <?= !empty($tab['active']) ? 'btn-dark' : 'btn-outline-secondary' ?>" href="<?= htmlSC((string)$tab['href']) ?>"<?= !empty($tab['active']) ? ' aria-current="page"' : '' ?>>
            <i class="<?= htmlSC((string)$tab['icon']) ?>" aria-hidden="true"></i>
            <span><?= htmlSC((string)$tab['label']) ?></span>
        </a>
    <?php endforeach; ?>
    <?php if ($secondaryTabs !== []): ?>
        <div class="dropdown">
            <button class="btn rounded-pill d-inline-flex align-items-center gap-2 dropdown-toggle <?= $secondaryActive ? 'btn-dark' : 'btn-outline-secondary' ?>" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="ci-menu" aria-hidden="true"></i>
                <span><?= htmlSC(FireballPluginSubscriptions::t('subscriptions_admin_more')) ?></span>
            </button>
            <ul class="dropdown-menu shadow-sm rounded-4 p-2">
                <?php foreach ($secondaryTabs as $tab): ?>
                    <li><a class="dropdown-item rounded-3 d-flex align-items-center gap-2 <?= !empty($tab['active']) ? 'active' : '' ?>" href="<?= htmlSC((string)$tab['href']) ?>"<?= !empty($tab['active']) ? ' aria-current="page"' : '' ?>>
                        <i class="<?= htmlSC((string)$tab['icon']) ?>" aria-hidden="true"></i>
                        <span><?= htmlSC((string)$tab['label']) ?></span>
                    </a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</nav>
