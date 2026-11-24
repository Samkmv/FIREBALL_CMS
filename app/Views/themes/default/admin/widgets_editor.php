<?php
$manager = widgets();
$areas = $manager->areas->all();
foreach ($snapshot['instances'] as $instance) {
    if (!isset($areas[$instance['area']])) $areas[$instance['area']] = ['id' => $instance['area'], 'label' => $instance['area'], 'pages' => [], 'types' => [], 'unavailable' => true];
}
$counts = array_count_values(array_column($snapshot['instances'], 'type'));
$catalogTypes = $manager->types->all();
// Put installable extensions before the built-in required widgets.
uasort($catalogTypes, static fn(array $a, array $b): int => $a['system'] <=> $b['system']);
?>
<section id="widget-installed" data-widget-panel="installed">
    <div class="widget-section-heading"><div><h2><?= htmlSC(return_translation('widgets_installed')) ?> <span class="widget-total"><?= count($snapshot['instances']) ?></span></h2><p><?= htmlSC(return_translation('widgets_compact_hint')) ?></p></div><button type="button" class="btn btn-primary btn-sm" data-widget-view="catalog"><i class="ci-plus" aria-hidden="true"></i><?= htmlSC(return_translation('widgets_add')) ?></button></div>
    <div class="widget-areas">
    <?php foreach ($areas as $area): ?>
        <section class="widget-area-card" data-widget-area-section>
            <div class="widget-area-heading"><i class="ci-sidebar" aria-hidden="true"></i><h3 class="h6 mb-0"><?= htmlSC(return_translation($area['label'])) ?></h3></div>
            <div class="vstack gap-2 widget-dropzone" data-widget-area="<?= htmlSC($area['id']) ?>">
                <?php foreach ($snapshot['instances'] as $instance): ?>
                    <?php if ($instance['area'] === $area['id']): ?>
                        <?= view()->renderPartial('admin/widget_instance', ['instance' => $instance, 'area' => $area, 'widget_context' => $widget_context]) ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <p class="widget-area-empty" data-widget-area-empty <?= array_filter($snapshot['instances'], static fn(array $item): bool => $item['area'] === $area['id']) ? 'hidden' : '' ?>><?= htmlSC(return_translation('widgets_area_empty')) ?></p>
        </section>
    <?php endforeach; ?>
    </div>
    <p class="widget-editor-hint"><i class="ci-info" aria-hidden="true"></i><span><?= htmlSC(return_translation('widgets_move_hint')) ?></span></p>
</section>
<section id="widget-catalog" data-widget-panel="catalog" hidden>
    <div class="widget-section-heading"><div><h2><?= htmlSC(return_translation('widgets_catalog')) ?> <span class="widget-total"><?= count($manager->types->all()) ?></span></h2><p><?= htmlSC(return_translation('widgets_catalog_hint')) ?></p></div></div>
    <div class="widget-catalog-grid">
    <?php foreach ($catalogTypes as $type): ?>
        <?php
        $targets = array_filter($manager->areas->all(), static fn(array $candidate): bool => $manager->types->allows($type['id'], $candidate));
        $count = $counts[$type['id']] ?? 0;
        $categoryLabel = return_translation($type['category_label']);
        $description = return_translation($type['description'] ?: 'widgets_custom_description');
        ?>
        <article class="widget-catalog-card" data-widget-catalog-item data-widget-category-id="<?= htmlSC($type['category']) ?>" data-widget-search-text="<?= htmlSC(implode(' ', [return_translation($type['label']), $type['id'], $categoryLabel, $description])) ?>">
            <div class="widget-catalog-card-heading"><span class="widget-type-icon"><i class="<?= htmlSC($type['icon']) ?>" aria-hidden="true"></i></span><span class="widget-badge"><?= htmlSC($categoryLabel) ?></span></div>
            <h3><?= htmlSC(return_translation($type['label'])) ?></h3>
            <p class="widget-catalog-description"><?= htmlSC($description) ?></p>
            <div class="widget-catalog-footer">
                <?php if ($type['system']): ?>
                    <span class="widget-catalog-system"><i class="ci-lock" aria-hidden="true"></i><?= htmlSC(return_translation('widgets_required')) ?></span>
                <?php elseif ($targets === []): ?>
                    <span class="small text-body-secondary"><?= htmlSC(return_translation('widgets_no_compatible_area')) ?></span>
                <?php else: ?>
                    <form data-widget-add class="widget-catalog-install">
                        <input type="hidden" name="type" value="<?= htmlSC($type['id']) ?>">
                        <?php if (count($targets) === 1): ?>
                            <input type="hidden" name="area" value="<?= htmlSC(array_key_first($targets)) ?>">
                        <?php else: ?>
                            <label class="form-label small" for="widget-target-<?= htmlSC($type['id']) ?>"><?= htmlSC(return_translation('widgets_area')) ?></label>
                            <select class="form-select form-select-sm" name="area" id="widget-target-<?= htmlSC($type['id']) ?>" data-widget-select>
                            <?php foreach ($targets as $target): ?><option value="<?= htmlSC($target['id']) ?>"><?= htmlSC(return_translation($target['label'])) ?></option><?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                        <button type="submit" class="btn <?= $count ? 'btn-outline-secondary' : 'btn-primary' ?> btn-sm"><i class="<?= $count ? 'ci-plus' : 'ci-download' ?>" aria-hidden="true"></i><?= htmlSC(return_translation($count ? 'widgets_add_another' : 'widgets_install')) ?></button>
                    </form>
                <?php endif; ?>
                <?php if ($count): ?><span class="widget-catalog-count"><?= htmlSC(return_translation('widgets_instances_count')) ?> <?= $count ?></span><?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
</section>
<div class="widget-empty" data-widget-no-results hidden><i class="ci-search" aria-hidden="true"></i><p><?= htmlSC(return_translation('widgets_no_results')) ?></p><button type="button" class="btn btn-outline-secondary btn-sm" data-widget-reset><?= htmlSC(return_translation('widgets_reset_filters')) ?></button></div>
<span class="visually-hidden" data-widget-result-count aria-live="polite" data-label="<?= htmlSC(return_translation('widgets_results_count')) ?>"></span>
