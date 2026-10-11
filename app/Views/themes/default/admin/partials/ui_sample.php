<?php
$sampleId = (string)($id ?? '');
$sampleTitle = (string)($title ?? '');
$sampleHint = (string)($hint ?? '');
$sampleContent = trim((string)($content ?? ''));
$sampleCode = trim((string)($code ?? $sampleContent));
$sampleLanguage = str_starts_with($sampleCode, '.my-component') ? 'CSS'
    : (str_starts_with($sampleCode, '<?=') ? 'PHP'
        : (str_starts_with($sampleCode, '//') ? 'JavaScript / PHP' : 'HTML'));
$samplePreviewClass = trim('fb-ui-preview ' . (string)($preview_class ?? ''));
?>
<article class="fb-card fb-ui-sample" data-ui-sample>
    <div class="fb-card-header">
        <div>
            <h3 class="fb-card-title"><?= htmlSC($sampleTitle) ?></h3>
            <?php if ($sampleHint !== ''): ?>
                <p class="fb-card-subtitle"><?= htmlSC($sampleHint) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <div class="fb-card-body <?= htmlSC($samplePreviewClass) ?>">
        <?= $sampleContent ?>
    </div>
    <details class="fb-ui-source">
        <summary><i class="ci-code" aria-hidden="true"></i> <?= htmlSC(return_translation('admin_ui_kit_code')) ?></summary>
        <div class="fb-ui-source-toolbar">
            <span class="text-body-secondary small"><?= htmlSC($sampleLanguage) ?></span>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-ui-copy="<?= htmlSC($sampleId) ?>">
                <i class="ci-copy me-1" aria-hidden="true"></i><span><?= htmlSC(return_translation('admin_ui_kit_copy')) ?></span>
            </button>
        </div>
        <pre tabindex="0" aria-label="<?= htmlSC($sampleTitle) ?>"><code id="<?= htmlSC($sampleId) ?>"><?= htmlSC($sampleCode) ?></code></pre>
    </details>
</article>
