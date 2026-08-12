<?php
/**
 * @var string $entity_type
 * @var int $entity_id
 * @var string $field_name
 * @var string $field_id
 * @var string $content
 * @var string $validation_class
 * @var string $editor_id
 * @var array $config
 */
$labels = (array)($config['labels'] ?? []);
$label = static fn(string $name, string $fallback): string => trim((string)($labels[$name] ?? '')) ?: $fallback;
?>
<textarea
    class="form-control <?= htmlSC($validation_class) ?> d-none"
    id="<?= htmlSC($field_id) ?>"
    name="<?= htmlSC($field_name) ?>"
    rows="10"
    data-post-editor
    data-block-editor-source
    data-entity-type="<?= htmlSC($entity_type) ?>"
    data-entity-id="<?= (int)$entity_id ?>"
><?= htmlSC($content) ?></textarea>

<section
    class="fb-editor2"
    id="<?= htmlSC($editor_id) ?>"
    data-post-editor-app
    data-block-editor
    data-editor2
    data-entity-type="<?= htmlSC($entity_type) ?>"
    data-entity-id="<?= (int)$entity_id ?>"
    data-post-editor-config="<?= htmlSC(json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
>
    <div class="fb-editor2__toolbar" role="toolbar" aria-label="<?= htmlSC($label('contentSettings', 'Formatting')) ?>" data-editor-toolbar>
        <div class="fb-editor2__toolbar-scroll">
            <label class="visually-hidden" for="<?= htmlSC($editor_id) ?>BlockStyle"><?= htmlSC($label('style', 'Style')) ?></label>
            <select class="fb-editor2__select" id="<?= htmlSC($editor_id) ?>BlockStyle" data-editor-block-style>
                <option value="text"><?= htmlSC($label('textBlock', 'Paragraph')) ?></option>
                <?php for ($level = 1; $level <= 6; $level++): ?>
                    <option value="h<?= $level ?>">H<?= $level ?></option>
                <?php endfor; ?>
                <option value="quote"><?= htmlSC($label('quote', 'Quote')) ?></option>
            </select>

            <label class="visually-hidden" for="<?= htmlSC($editor_id) ?>Font"><?= htmlSC($label('font', 'Font')) ?></label>
            <select class="fb-editor2__select fb-editor2__select--font" id="<?= htmlSC($editor_id) ?>Font" data-editor-format-value="fontFamily">
                <?php foreach ((array)($config['fonts'] ?? []) as $font): ?>
                    <option value="<?= htmlSC((string)($font['value'] ?? '')) ?>"><?= htmlSC((string)($font['label'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>

            <label class="visually-hidden" for="<?= htmlSC($editor_id) ?>Size"><?= htmlSC($label('size', 'Size')) ?></label>
            <select class="fb-editor2__select fb-editor2__select--size" id="<?= htmlSC($editor_id) ?>Size" data-editor-format-value="fontSize">
                <?php foreach ((array)($config['sizes'] ?? []) as $size): ?>
                    <option value="<?= htmlSC((string)($size['value'] ?? '')) ?>"><?= htmlSC((string)($size['label'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>

            <span class="fb-editor2__tool-group">
                <button type="button" class="fb-editor2__tool" data-editor-inline="strong" aria-label="<?= htmlSC($label('bold', 'Bold')) ?>" title="<?= htmlSC($label('bold', 'Bold')) ?>"><strong>B</strong></button>
                <button type="button" class="fb-editor2__tool" data-editor-inline="em" aria-label="<?= htmlSC($label('italic', 'Italic')) ?>" title="<?= htmlSC($label('italic', 'Italic')) ?>"><em>I</em></button>
                <button type="button" class="fb-editor2__tool" data-editor-inline="u" aria-label="<?= htmlSC($label('underline', 'Underline')) ?>" title="<?= htmlSC($label('underline', 'Underline')) ?>"><u>U</u></button>
                <button type="button" class="fb-editor2__tool" data-editor-inline="s" aria-label="<?= htmlSC($label('strikethrough', 'Strikethrough')) ?>" title="<?= htmlSC($label('strikethrough', 'Strikethrough')) ?>"><s>S</s></button>
                <button type="button" class="fb-editor2__tool" data-editor-inline="sup" aria-label="<?= htmlSC($label('superscript', 'Superscript')) ?>" title="<?= htmlSC($label('superscript', 'Superscript')) ?>">x<sup>2</sup></button>
                <button type="button" class="fb-editor2__tool" data-editor-inline="sub" aria-label="<?= htmlSC($label('subscript', 'Subscript')) ?>" title="<?= htmlSC($label('subscript', 'Subscript')) ?>">x<sub>2</sub></button>
                <button type="button" class="fb-editor2__tool" data-editor-inline="code" aria-label="<?= htmlSC($label('inlineCode', 'Inline code')) ?>" title="<?= htmlSC($label('inlineCode', 'Inline code')) ?>"><i class="ci-code"></i></button>
            </span>

            <span class="fb-editor2__tool-group">
                <button type="button" class="fb-editor2__tool" data-editor-command="link" aria-label="<?= htmlSC($label('link', 'Link')) ?>" title="<?= htmlSC($label('link', 'Link')) ?>"><i class="ci-link"></i></button>
                <button type="button" class="fb-editor2__tool" data-editor-command="unlink" aria-label="<?= htmlSC($label('unlink', 'Unlink')) ?>" title="<?= htmlSC($label('unlink', 'Unlink')) ?>"><i class="ci-link-2"></i></button>
                <label class="fb-editor2__tool fb-editor2__color-tool" aria-label="<?= htmlSC($label('textColor', 'Text color')) ?>" title="<?= htmlSC($label('textColor', 'Text color')) ?>">
                    <i class="ci-type"></i><input type="color" value="#111827" data-editor-color="color">
                </label>
                <label class="fb-editor2__tool fb-editor2__color-tool" aria-label="<?= htmlSC($label('background', 'Background')) ?>" title="<?= htmlSC($label('background', 'Background')) ?>">
                    <i class="ci-paint-bucket"></i><input type="color" value="#fff3cd" data-editor-color="backgroundColor">
                </label>
                <button type="button" class="fb-editor2__tool" data-editor-command="clear" aria-label="<?= htmlSC($label('clearFormatting', 'Clear formatting')) ?>" title="<?= htmlSC($label('clearFormatting', 'Clear formatting')) ?>"><i class="ci-eraser"></i></button>
            </span>

            <span class="fb-editor2__tool-group">
                <button type="button" class="fb-editor2__tool" data-editor-align="left" aria-label="<?= htmlSC($label('alignLeft', 'Align left')) ?>"><i class="ci-align-left"></i></button>
                <button type="button" class="fb-editor2__tool" data-editor-align="center" aria-label="<?= htmlSC($label('alignCenter', 'Align center')) ?>"><i class="ci-align-center"></i></button>
                <button type="button" class="fb-editor2__tool" data-editor-align="right" aria-label="<?= htmlSC($label('alignRight', 'Align right')) ?>"><i class="ci-align-right"></i></button>
                <button type="button" class="fb-editor2__tool" data-editor-command="outdent" aria-label="<?= htmlSC($label('outdent', 'Decrease indent')) ?>"><i class="ci-arrow-left"></i></button>
                <button type="button" class="fb-editor2__tool" data-editor-command="indent" aria-label="<?= htmlSC($label('indent', 'Increase indent')) ?>"><i class="ci-arrow-right"></i></button>
            </span>

            <span class="fb-editor2__tool-group">
                <button type="button" class="fb-editor2__tool" data-editor-convert="bulletList" aria-label="<?= htmlSC($label('bulletList', 'Bullet list')) ?>"><i class="ci-list"></i></button>
                <button type="button" class="fb-editor2__tool" data-editor-convert="orderedList" aria-label="<?= htmlSC($label('orderedList', 'Numbered list')) ?>"><i class="ci-list-ordered"></i></button>
                <button type="button" class="fb-editor2__tool" data-editor-convert="checklist" aria-label="<?= htmlSC($label('checklist', 'Checklist')) ?>"><i class="ci-check-square"></i></button>
                <button type="button" class="fb-editor2__tool" data-editor-convert="quote" aria-label="<?= htmlSC($label('quote', 'Quote')) ?>"><i class="ci-quote"></i></button>
            </span>

            <span class="fb-editor2__tool-group">
                <button type="button" class="fb-editor2__tool" data-editor-command="search" aria-label="<?= htmlSC($label('searchReplace', 'Search and replace')) ?>"><i class="ci-search"></i></button>
                <button type="button" class="fb-editor2__tool" data-editor-command="commandPalette" aria-label="<?= htmlSC($label('commandSearch', 'Commands')) ?>"><i class="ci-command"></i></button>
            </span>
        </div>
    </div>

    <div class="fb-editor2__selection-toolbar" data-editor-selection-toolbar hidden>
        <button type="button" data-editor-inline="strong"><strong>B</strong></button>
        <button type="button" data-editor-inline="em"><em>I</em></button>
        <button type="button" data-editor-inline="u"><u>U</u></button>
        <button type="button" data-editor-command="link"><i class="ci-link"></i></button>
        <button type="button" data-editor-inline="code"><i class="ci-code"></i></button>
    </div>

    <div class="fb-editor2__canvas" data-editor-canvas aria-live="polite"></div>

    <div class="fb-editor2__slash-menu" data-editor-slash-menu hidden>
        <div class="fb-editor2__menu-search">
            <i class="ci-search"></i>
            <input type="search" data-editor-slash-search placeholder="<?= htmlSC($label('commandSearch', 'Search commands…')) ?>" autocomplete="off">
        </div>
        <div class="fb-editor2__menu-list" data-editor-slash-list></div>
    </div>

    <div class="fb-editor2__context-menu" data-editor-context-menu hidden></div>

    <dialog class="fb-editor2__dialog fb-editor2__command-dialog" data-editor-command-dialog>
        <form method="dialog" class="fb-editor2__dialog-card">
            <div class="fb-editor2__dialog-head">
                <i class="ci-search"></i>
                <input type="search" data-editor-command-search placeholder="<?= htmlSC($label('commandSearch', 'Search commands…')) ?>" autocomplete="off">
                <kbd aria-hidden="true">Esc</kbd>
                <button
                    type="button"
                    class="fb-editor2__dialog-close"
                    data-editor-command-close
                    aria-label="<?= htmlSC($label('close', 'Close')) ?>"
                    title="<?= htmlSC($label('close', 'Close')) ?>"
                >
                    <i class="ci-close" aria-hidden="true"></i>
                </button>
            </div>
            <div class="fb-editor2__command-list" data-editor-command-list role="listbox"></div>
        </form>
    </dialog>

    <dialog class="fb-editor2__dialog fb-editor2__preview-dialog" data-editor-preview-dialog>
        <div class="fb-editor2__preview-card">
            <header>
                <strong><?= htmlSC($label('preview', 'Preview')) ?></strong>
                <div class="fb-editor2__preview-devices" role="group">
                    <button type="button" data-preview-device="desktop" class="is-active"><i class="ci-monitor"></i></button>
                    <button type="button" data-preview-device="tablet"><i class="ci-tablet"></i></button>
                    <button type="button" data-preview-device="mobile"><i class="ci-smartphone"></i></button>
                </div>
                <button type="button" data-editor-preview-close aria-label="<?= htmlSC($label('close', 'Close')) ?>"><i class="ci-close"></i></button>
            </header>
            <div class="fb-editor2__preview-stage" data-preview-stage>
                <iframe title="<?= htmlSC($label('preview', 'Preview')) ?>" sandbox="allow-same-origin" data-editor-preview-frame></iframe>
            </div>
        </div>
    </dialog>

    <dialog class="fb-editor2__dialog fb-editor2__recovery-dialog" data-editor-recovery-dialog>
        <form method="dialog" class="fb-editor2__recovery-card">
            <span class="fb-editor2__dialog-icon"><i class="ci-history"></i></span>
            <h2><?= htmlSC($label('recoveryTitle', 'Restore local version?')) ?></h2>
            <p><?= htmlSC($label('recoveryText', 'A newer local version of this document was found.')) ?></p>
            <div>
                <button type="button" class="btn btn-outline-secondary rounded-pill" data-editor-recovery-discard><?= htmlSC($label('discard', 'Discard')) ?></button>
                <button type="button" class="btn btn-primary rounded-pill" data-editor-recovery-restore><?= htmlSC($label('restore', 'Restore')) ?></button>
            </div>
        </form>
    </dialog>

    <dialog class="fb-editor2__dialog fb-editor2__recovery-dialog" data-editor-delete-dialog>
        <form method="dialog" class="fb-editor2__recovery-card">
            <span class="fb-editor2__dialog-icon"><i class="ci-trash"></i></span>
            <h2><?= htmlSC((string)($delete_title ?? $label('deleteModalTitle', 'Remove block?'))) ?></h2>
            <p><?= htmlSC((string)($delete_text ?? $label('deleteModalText', 'This action cannot be undone.'))) ?></p>
            <div>
                <button type="button" class="btn btn-outline-secondary rounded-pill" data-editor-delete-cancel><?= htmlSC($label('close', 'Close')) ?></button>
                <button type="button" class="btn btn-danger rounded-pill" data-editor-delete-confirm><?= htmlSC($label('remove', 'Remove')) ?></button>
            </div>
        </form>
    </dialog>

    <div class="fb-editor2__search-panel" data-editor-search-panel hidden>
        <label>
            <span><?= htmlSC($label('search', 'Search')) ?></span>
            <input type="search" data-editor-search-input autocomplete="off">
        </label>
        <label>
            <span><?= htmlSC($label('replace', 'Replace')) ?></span>
            <input type="text" data-editor-replace-input autocomplete="off">
        </label>
        <label class="fb-editor2__search-case">
            <input type="checkbox" data-editor-search-case>
            <span><?= htmlSC($label('matchCase', 'Match case')) ?></span>
        </label>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-search-prev aria-label="<?= htmlSC($label('previous', 'Previous')) ?>"><i class="ci-chevron-up"></i></button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-search-next aria-label="<?= htmlSC($label('next', 'Next')) ?>"><i class="ci-chevron-down"></i></button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-replace-one><?= htmlSC($label('replace', 'Replace')) ?></button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-editor-replace-all><?= htmlSC($label('replaceAll', 'Replace all')) ?></button>
        <button type="button" class="btn btn-sm btn-icon btn-outline-secondary rounded-circle" data-editor-search-close><i class="ci-close"></i></button>
    </div>
</section>
