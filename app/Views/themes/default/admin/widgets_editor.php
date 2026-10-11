<?php
$manager = widgets();
$areas = $manager->areas->all();
foreach ($snapshot['instances'] as $instance) {
    if (!isset($areas[$instance['area']])) $areas[$instance['area']] = ['id' => $instance['area'], 'label' => $instance['area'], 'pages' => [], 'types' => [], 'unavailable' => true];
}
?>
<p class="widget-editor-hint"><i class="ci-info" aria-hidden="true"></i><span><?= htmlSC(return_translation('widgets_move_hint')) ?></span></p>
<div class="row g-4">
<?php foreach ($areas as $area): ?>
    <section class="col-12 <?= count($areas) > 1 ? 'col-xl-6' : '' ?>">
        <div class="card rounded-4 h-100 widget-area-card">
            <div class="card-body p-3 p-sm-4">
                <div class="widget-area-heading"><i class="ci-sidebar" aria-hidden="true"></i><h2 class="h5 mb-0"><?= htmlSC(return_translation($area['label'])) ?></h2></div>
                <?php if (empty($area['unavailable'])): ?>
                <form class="widget-add-form" data-widget-add>
                    <input type="hidden" name="area" value="<?= htmlSC($area['id']) ?>">
                    <div class="widget-add-select"><select class="form-select" name="type" data-widget-select aria-label="<?= htmlSC(return_translation('widgets_available_types')) ?>" required>
                        <option value=""><?= htmlSC(return_translation('widgets_available_types')) ?></option>
                        <?php foreach ($manager->types->all() as $type): ?>
                            <?php if (!$type['system'] && $manager->types->allows($type['id'], $area)): ?><option value="<?= htmlSC($type['id']) ?>"><?= htmlSC(return_translation($type['label'])) ?></option><?php endif; ?>
                        <?php endforeach; ?>
                    </select></div>
                    <button type="submit" class="btn btn-primary"><i class="ci-plus" aria-hidden="true"></i><?= htmlSC(return_translation('widgets_add')) ?></button>
                </form>
                <?php endif; ?>
                <div class="vstack gap-3 widget-dropzone" data-widget-area="<?= htmlSC($area['id']) ?>">
                    <?php foreach ($snapshot['instances'] as $instance): ?>
                        <?php if ($instance['area'] === $area['id']): ?>
                            <?= view()->renderPartial('admin/widget_instance', ['instance' => $instance, 'area' => $area, 'widget_context' => $widget_context]) ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
<?php endforeach; ?>
</div>
