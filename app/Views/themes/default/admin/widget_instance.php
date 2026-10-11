<?php
$manager = widgets();
$type = $manager->types->get($instance['type']);
$system = $manager->instances->isSystem($instance);
$slot = $manager->areas->slot((string)($instance['system_key'] ?? ''));
$available = $type !== null && empty($area['unavailable']);
$label = $type !== null ? return_translation($type['label']) : $instance['type'];
$icon = ['posts.categories' => 'ci-folder', 'posts.trending' => 'ci-trending-up', 'posts.social' => 'ci-share-2', 'posts.latest' => 'ci-file-text', 'weather' => 'ci-cloud', 'html' => 'ci-code'][$instance['type']] ?? 'ci-box';
$fields = [];
try { if ($available) $fields = $type['widget']->fields($widget_context); } catch (\Throwable $exception) { $available = false; log_error_details('Widget settings form failed', ['Type' => $instance['type']], $exception); }
$settings = $instance['settings'] + ($type !== null ? $type['widget']->defaults() : []);
$allowed = [];
foreach ($manager->areas->all() as $candidate) {
    if ($manager->types->allows($instance['type'], $candidate) && (!$system || ($slot['area'] ?? '') === $candidate['id'])) $allowed[] = $candidate['id'];
}
?>
<article class="widget-instance" data-widget-id="<?= (int)$instance['id'] ?>" data-allowed-areas="<?= htmlSC(json_encode($allowed)) ?>">
    <div class="widget-instance-header">
        <button class="btn btn-sm btn-outline-secondary widget-drag-handle widget-icon-button" type="button" draggable="true" data-widget-handle aria-label="<?= htmlSC(return_translation('widgets_move')) ?>"><i class="ci-move" aria-hidden="true"></i></button>
        <span class="widget-type-icon"><i class="<?= $icon ?>" aria-hidden="true"></i></span>
        <div class="widget-instance-title"><span class="fw-semibold"><?= htmlSC($label) ?></span>
            <?php if ($system): ?><span class="widget-badge"><i class="ci-lock" aria-hidden="true"></i><?= htmlSC(return_translation('widgets_required')) ?></span><?php elseif (!$instance['enabled']): ?><span class="widget-badge"><?= htmlSC(return_translation('widgets_disabled')) ?></span><?php endif; ?>
        </div>
        <div class="widget-order-buttons">
            <button type="button" class="btn btn-sm btn-outline-secondary widget-icon-button" data-widget-up aria-label="<?= htmlSC(return_translation('widgets_up')) ?>"><i class="ci-chevron-up" aria-hidden="true"></i></button>
            <button type="button" class="btn btn-sm btn-outline-secondary widget-icon-button" data-widget-down aria-label="<?= htmlSC(return_translation('widgets_down')) ?>"><i class="ci-chevron-down" aria-hidden="true"></i></button>
        </div>
    </div>
    <?php if (count($allowed) > 1): ?>
        <label class="form-label small"><?= htmlSC(return_translation('widgets_area')) ?></label>
        <select class="form-select form-select-sm mb-3" data-widget-move data-widget-select>
            <?php foreach ($allowed as $id): ?><option value="<?= htmlSC($id) ?>" <?= $instance['area'] === $id ? 'selected' : '' ?>><?= htmlSC(return_translation($manager->areas->get($id)['label'])) ?></option><?php endforeach; ?>
        </select>
    <?php endif; ?>
    <?php if (!$available): ?><p class="small text-body-secondary"><?= htmlSC(return_translation('widgets_type_unavailable')) ?></p><?php else: ?>
    <details class="widget-settings-panel" data-widget-details>
        <summary><i class="ci-sliders" aria-hidden="true"></i><span><?= htmlSC(return_translation('widgets_settings')) ?></span><i class="ci-chevron-down widget-settings-chevron" aria-hidden="true"></i></summary>
        <form data-widget-settings>
            <div class="widget-fields-grid">
            <?php foreach ($fields as $key => $field): ?>
                <?php $value = $settings[$key] ?? ''; $inputId = 'widget-' . $instance['id'] . '-' . $key; $wide = !empty($field['wide']) || in_array($field['type'], ['text', 'textarea', 'html', 'categories'], true); ?>
                <div class="widget-field <?= $wide ? 'widget-field-wide' : '' ?>">
                <?php if ($field['type'] === 'checkbox'): ?>
                    <label class="widget-toggle" for="<?= htmlSC($inputId) ?>"><span><?= htmlSC(return_translation($field['label'])) ?></span><span class="form-check form-switch mb-0"><input class="form-check-input" id="<?= htmlSC($inputId) ?>" type="checkbox" role="switch" data-setting="<?= htmlSC($key) ?>" <?= $value ? 'checked' : '' ?>></span></label>
                <?php elseif ($field['type'] === 'categories' && empty($field['multiple'])): ?>
                    <span class="form-label d-block"><?= htmlSC(return_translation($field['label'])) ?></span>
                    <div class="vstack gap-2" data-category-order="<?= htmlSC($key) ?>">
                        <?php
                        $options = $field['options'];
                        $order = array_unique(array_merge(is_array($value) ? $value : [], array_keys($options)));
                        foreach ($order as $slug): if (!isset($options[$slug])) continue;
                        ?>
                        <div class="widget-category-row" data-category-slug="<?= htmlSC($slug) ?>"><span class="flex-grow-1 small"><?= htmlSC($options[$slug]) ?></span><button type="button" class="btn btn-sm btn-outline-secondary widget-icon-button" data-category-up aria-label="<?= htmlSC(return_translation('widgets_up')) ?>"><i class="ci-chevron-up" aria-hidden="true"></i></button><button type="button" class="btn btn-sm btn-outline-secondary widget-icon-button" data-category-down aria-label="<?= htmlSC(return_translation('widgets_down')) ?>"><i class="ci-chevron-down" aria-hidden="true"></i></button></div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($field['type'] === 'categories'): ?>
                    <span class="form-label d-block"><?= htmlSC(return_translation($field['label'])) ?></span>
                    <div class="widget-category-checks" data-category-hidden="<?= htmlSC($key) ?>">
                        <?php foreach ($field['options'] as $slug => $option): ?>
                        <label class="form-check"><input class="form-check-input" type="checkbox" value="<?= htmlSC($slug) ?>" <?= in_array($slug, (array)$value, true) ? 'checked' : '' ?>><span class="form-check-label small"><?= htmlSC($option) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <label class="form-label small" for="<?= htmlSC($inputId) ?>"><?= htmlSC(return_translation($field['label'])) ?></label>
                    <?php if ($field['type'] === 'select'): ?>
                        <select id="<?= htmlSC($inputId) ?>" class="form-select" data-setting="<?= htmlSC($key) ?>" data-widget-select>
                            <?php foreach ($field['options'] as $option => $text): ?><option value="<?= htmlSC($option) ?>" <?= (string)$value === (string)$option ? 'selected' : '' ?>><?= htmlSC($text) ?></option><?php endforeach; ?>
                        </select>
                    <?php elseif (in_array($field['type'], ['textarea', 'html'], true)): ?>
                        <textarea id="<?= htmlSC($inputId) ?>" class="form-control" rows="6" data-setting="<?= htmlSC($key) ?>" maxlength="<?= (int)($field['max'] ?? 150) ?>"><?= htmlSC(is_string($value) ? $value : '') ?></textarea>
                        <?php if ($field['type'] === 'html'): ?><p class="form-text mb-0"><?= htmlSC(return_translation('widgets_html_policy')) ?></p><?php endif; ?>
                    <?php else: ?>
                        <input id="<?= htmlSC($inputId) ?>" class="form-control" type="<?= $field['type'] === 'number' ? 'number' : 'text' ?>" data-setting="<?= htmlSC($key) ?>" value="<?= htmlSC(is_scalar($value) ? (string)$value : '') ?>" <?= $field['type'] === 'number' ? 'min="' . (int)$field['min'] . '" max="' . (int)$field['max'] . '"' : 'maxlength="' . (int)($field['max'] ?? 150) . '"' ?>>
                    <?php endif; ?>
                <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
            <div class="widget-display-settings">
            <label class="widget-toggle widget-enabled-toggle" for="widget-enabled-<?= (int)$instance['id'] ?>"><span><?= htmlSC(return_translation('widgets_enabled')) ?></span><span class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" role="switch" id="widget-enabled-<?= (int)$instance['id'] ?>" data-widget-enabled <?= $instance['enabled'] ? 'checked' : '' ?> <?= $system ? 'disabled' : '' ?>></span></label>
            <fieldset class="widget-visibility"><legend><?= htmlSC(return_translation('widgets_visibility')) ?></legend><div class="widget-visibility-options">
                <?php foreach ($area['pages'] as $page => $pageLabel): ?>
                <label class="form-check"><input class="form-check-input" type="checkbox" data-widget-page="<?= htmlSC($page) ?>" <?= !empty($instance['visibility'][$page]) ? 'checked' : '' ?> <?= in_array($page, $slot['requiredPages'] ?? [], true) ? 'disabled' : '' ?>><span class="form-check-label"><?= htmlSC(return_translation($pageLabel)) ?></span></label>
                <?php endforeach; ?>
            </div></fieldset>
            </div>
            <div class="widget-settings-actions"><button class="btn btn-primary btn-sm" type="submit"><i class="ci-check" aria-hidden="true"></i><?= htmlSC(return_translation('widgets_save')) ?></button>
                <?php if (!$system): ?><button class="btn btn-outline-danger btn-sm" type="button" data-widget-delete><i class="ci-trash" aria-hidden="true"></i><?= htmlSC(return_translation('widgets_delete')) ?></button><?php endif; ?>
            </div>
        </form>
    </details>
    <?php endif; ?>
    <?php if (!$system && !$available): ?><button class="btn btn-outline-danger btn-sm mt-3" type="button" data-widget-delete><?= htmlSC(return_translation('widgets_delete')) ?></button><?php endif; ?>
</article>
