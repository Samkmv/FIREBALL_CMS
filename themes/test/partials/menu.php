<nav class="theme-menu" aria-label="Main menu">
    <div class="theme-container">
        <a href="<?= site_url() ?>">Home</a>
        <?php foreach (get_menu('header') as $item): ?>
            <a href="<?= htmlSC($item['url']) ?>" <?= $item['active'] ? 'aria-current="page"' : '' ?>>
                <?= htmlSC($item['title']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</nav>