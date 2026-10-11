<?= view()->renderPartial('admin/shell_open', ['title' => return_translation('admin_nav_widgets'), 'subtitle' => return_translation('widgets_subtitle')]) ?>
<?php if ($editor_error !== ''): ?>
    <div class="alert alert-warning"><?= htmlSC($editor_error) ?></div>
<?php else: ?>
<div id="widget-editor" data-save-url="<?= htmlSC(base_href('/admin/widgets/save')) ?>" data-revision="<?= (int)$snapshot['revision'] ?>"
    data-error="<?= htmlSC(return_translation('widgets_save_error')) ?>" data-delete-message="<?= htmlSC(return_translation('widgets_confirm_delete')) ?>">
    <?= get_csrf_field() ?>
    <div class="alert d-none" data-widget-status role="status" aria-live="polite"></div>
    <div data-widget-content><?= view()->renderPartial('admin/widgets_editor', compact('snapshot', 'widget_context')) ?></div>
</div>
<?php endif; ?>
<?= view()->renderPartial('admin/shell_close') ?>
