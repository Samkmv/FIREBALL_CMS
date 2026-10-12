<?php if ($settings['title'] !== '' || $settings['html'] !== ''): ?>
<section class="card border rounded-4 mt-4"><div class="card-body p-4 text-break">
    <?php if ($settings['title'] !== ''): ?><h4 class="h6"><?= htmlSC($settings['title']) ?></h4><?php endif; ?>
    <?= \App\Widgets\WidgetHtml::sanitize($settings['html']) ?>
</div></section>
<?php endif; ?>
