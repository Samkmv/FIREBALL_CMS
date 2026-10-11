<?php if ($links): ?>
<h4 class="h6 pt-4"><?= htmlSC($settings['title'] ?: $context->translate('index_follow', 'show_follow')) ?></h4>
<div class="d-flex gap-2 pb-2">
    <?php foreach ($links as $link): ?>
        <a class="btn btn-icon fs-base btn-outline-secondary border-0" href="<?= htmlSC($link['href']) ?>"
            <?= !empty($link['external']) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
            data-bs-toggle="tooltip" data-bs-template="<div class=&quot;tooltip fs-xs mb-n2&quot; role=&quot;tooltip&quot;><div class=&quot;tooltip-inner bg-transparent text-body p-0&quot;></div></div>"
            aria-label="<?= htmlSC($link['label']) ?>" data-bs-original-title="<?= htmlSC($link['label']) ?>">
            <i class="<?= htmlSC($link['icon']) ?>"></i>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
