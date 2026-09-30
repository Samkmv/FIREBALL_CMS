<div class="business-statistics-grid">
<?php foreach (['posts'=>['publications','ci-file-text'], 'photos'=>['gallery','ci-image'], 'reviews'=>['reviews','ci-message-circle']] as $key=>[$label,$icon]): ?><div><strong><i class="<?= $icon ?>" aria-hidden="true"></i><?= (int)$business_stats[$key] ?></strong><span><?= $t($label) ?></span></div><?php endforeach; ?>
</div>
