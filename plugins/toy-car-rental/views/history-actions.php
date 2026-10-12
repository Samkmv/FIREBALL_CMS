<?php if (empty($mobileActions)): ?>
    <span class="text-body-tertiary">—</span>
<?php else: ?>
    <div class="dropdown admin-post-actions-dropdown" data-admin-post-actions-dropdown>
        <button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-display="static" data-bs-boundary="viewport" aria-expanded="false" aria-label="<?= htmlSC(FireballPluginToyCarRental::t('toy_rental_table_actions')) ?>">
            <i class="ci-more-vertical" aria-hidden="true"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">
            <?php foreach ($mobileActions as $historyAction): ?>
                <?php if (($historyAction['type'] ?? '') === 'divider'): ?>
                    <hr class="dropdown-divider">
                    <?php continue; ?>
                <?php endif; ?>
                <form action="<?= htmlSC((string)$historyAction['action']) ?>" method="post"<?php foreach ((array)($historyAction['form_attributes'] ?? []) as $attribute => $value): ?> <?= htmlSC((string)$attribute) ?><?= $value === true ? '' : '="' . htmlSC((string)$value) . '"' ?><?php endforeach; ?>>
                    <?= get_csrf_field() ?>
                    <?php foreach ((array)($historyAction['hidden'] ?? []) as $name => $value): ?>
                        <input type="hidden" name="<?= htmlSC((string)$name) ?>" value="<?= htmlSC((string)$value) ?>">
                    <?php endforeach; ?>
                    <button class="dropdown-item d-flex align-items-center gap-2 <?= htmlSC((string)($historyAction['class'] ?? '')) ?>" type="submit"><i class="<?= htmlSC((string)$historyAction['icon']) ?>" aria-hidden="true"></i><span><?= htmlSC((string)$historyAction['label']) ?></span></button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
