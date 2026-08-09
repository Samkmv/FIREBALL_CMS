<nav class="nav nav-pills flex-nowrap overflow-x-auto gap-2 mb-4" aria-label="<?= htmlSC(FireballPluginSubscriptions::t('subscriptions_menu')) ?>">
    <?php foreach ((array)($tabs ?? []) as $tab): ?>
        <a class="nav-link rounded-pill d-inline-flex align-items-center gap-2 <?= !empty($tab['active']) ? 'active' : '' ?>" href="<?= htmlSC((string)$tab['href']) ?>">
            <i class="<?= htmlSC((string)$tab['icon']) ?>"></i>
            <span><?= htmlSC((string)$tab['label']) ?></span>
        </a>
    <?php endforeach; ?>
</nav>
