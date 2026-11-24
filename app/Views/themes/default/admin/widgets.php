<?= view()->renderPartial('admin/shell_open', ['title' => return_translation('admin_nav_widgets'), 'subtitle' => return_translation('widgets_subtitle')]) ?>
<?php if ($editor_error !== ''): ?>
    <div class="alert alert-warning"><?= htmlSC($editor_error) ?></div>
<?php else: ?>
<div id="widget-editor" data-save-url="<?= htmlSC(base_href('/admin/widgets/save')) ?>" data-revision="<?= (int)$snapshot['revision'] ?>"
    data-error="<?= htmlSC(return_translation('widgets_save_error')) ?>">
    <?= get_csrf_field() ?>
    <div class="alert d-none" data-widget-status role="status" aria-live="polite"></div>
    <div class="widget-toolbar">
        <nav class="widget-view-switch" aria-label="<?= htmlSC(return_translation('admin_nav_widgets')) ?>">
            <button type="button" class="btn btn-sm" data-widget-view="installed" aria-pressed="true" aria-controls="widget-installed"><?= htmlSC(return_translation('widgets_installed')) ?></button>
            <button type="button" class="btn btn-sm" data-widget-view="catalog" aria-pressed="false" aria-controls="widget-catalog"><i class="ci-grid" aria-hidden="true"></i><?= htmlSC(return_translation('widgets_catalog')) ?></button>
        </nav>
        <div class="widget-filters" role="search" aria-label="<?= htmlSC(return_translation('widgets_search')) ?>">
            <div class="widget-search"><i class="ci-search" aria-hidden="true"></i><input type="search" class="form-control" data-widget-search aria-label="<?= htmlSC(return_translation('widgets_search')) ?>" placeholder="<?= htmlSC(return_translation('widgets_search_placeholder')) ?>" maxlength="200"></div>
            <select class="form-select" data-widget-category aria-label="<?= htmlSC(return_translation('widgets_categories_filter')) ?>">
                <option value=""><?= htmlSC(return_translation('widgets_all_categories')) ?></option>
                <?php foreach (widgets()->types->categories() as $id => $label): ?>
                    <option value="<?= htmlSC($id) ?>"><?= htmlSC(return_translation($label)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div data-widget-content><?= view()->renderPartial('admin/widgets_editor', compact('snapshot', 'widget_context')) ?></div>
    <form hidden data-widget-delete-form data-admin-delete-form
        data-delete-message="<?= htmlSC(return_translation('widgets_confirm_delete')) ?>"
        data-delete-confirm-label="<?= htmlSC(return_translation('widgets_delete')) ?>"></form>
</div>
<?php endif; ?>
<?= view()->renderPartial('admin/shell_close') ?>
