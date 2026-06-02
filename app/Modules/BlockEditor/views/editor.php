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
 * @var string $close_label
 * @var string $delete_title
 * @var string $delete_text
 */
$settingsModalId = $editor_id . 'SettingsModal';
$deleteModalId = $editor_id . 'DeleteModal';
$orderModalId = $editor_id . 'OrderModal';
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
<div
    class="fb-post-editor fb-post-editor--linear"
    data-post-editor-app
    data-block-editor
    data-entity-type="<?= htmlSC($entity_type) ?>"
    data-entity-id="<?= (int)$entity_id ?>"
    data-settings-modal-id="<?= htmlSC($settingsModalId) ?>"
    data-delete-modal-id="<?= htmlSC($deleteModalId) ?>"
    data-order-modal-id="<?= htmlSC($orderModalId) ?>"
    data-post-editor-config="<?= htmlSC(json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
></div>

<div class="modal fade" id="<?= htmlSC($settingsModalId) ?>" tabindex="-1" role="dialog" aria-hidden="true" data-post-editor-settings-modal>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg" role="document">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title" data-editor-settings-title><?= print_translation('admin_post_builder_block_settings') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlSC($close_label) ?>"></button>
            </div>
            <div class="modal-body" data-editor-settings-body></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark rounded-pill" data-bs-dismiss="modal">
                    <?= print_translation('admin_btn_done') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="<?= htmlSC($deleteModalId) ?>" tabindex="-1" aria-hidden="true" data-post-editor-delete-modal>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title" data-editor-delete-title><?= htmlSC($delete_title) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlSC($close_label) ?>"></button>
            </div>
            <div class="modal-body" data-editor-delete-text>
                <?= htmlSC($delete_text) ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">
                    <?= print_translation('admin_btn_cancel') ?>
                </button>
                <button type="button" class="btn btn-danger rounded-pill" data-editor-confirm-remove>
                    <?= print_translation('admin_btn_delete') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="<?= htmlSC($orderModalId) ?>" tabindex="-1" aria-hidden="true" data-block-editor-order-modal>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title" data-editor-order-title></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= htmlSC($close_label) ?>"></button>
            </div>
            <div class="modal-body">
                <div class="fb-block-order-list" data-editor-order-list></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">
                    <?= print_translation('admin_btn_cancel') ?>
                </button>
                <button type="button" class="btn btn-dark rounded-pill" data-editor-order-save>
                    <?= print_translation('admin_btn_save') ?>
                </button>
            </div>
        </div>
    </div>
</div>
