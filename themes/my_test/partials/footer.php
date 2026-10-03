<?php $legalInformationMenu = $this->getLegalInformationMenu(); ?>
<footer class="theme-footer">
    <div class="theme-container">
        <?php if ($legalInformationMenu): ?>
            <nav aria-label="<?= htmlSC(return_translation('footer_heading_legal_information')) ?>">
                <strong><?= print_translation('footer_heading_legal_information') ?></strong>
                <?php foreach ($legalInformationMenu as $item): ?>
                    <a href="<?= htmlSC($item['href']) ?>"><?= htmlSC($item['label']) ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
        <p>&copy; <?= date('Y') ?> <?= htmlSC(site_setting('site_title', SITE_NAME)) ?></p>
    </div>
</footer>