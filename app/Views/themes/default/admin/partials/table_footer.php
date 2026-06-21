<?php
$visible = max(0, (int)($visible ?? 0));
$total = max(0, (int)($total ?? 0));
$pagination = $pagination ?? null;
$showResultsLabel = (bool)($show_results_label ?? true);
$infoClass = trim((string)($info_class ?? ''));
$infoAttributes = (array)($info_attributes ?? []);
$visibleAttributes = (array)($visible_attributes ?? []);
$totalAttributes = (array)($total_attributes ?? []);
$paginationAttributes = array_merge(['data-datatable-pagination' => true], (array)($pagination_attributes ?? []));

$renderAttributes = static function (array $attributes): string {
    $html = '';
    foreach ($attributes as $name => $value) {
        if ($value === false || $value === null) {
            continue;
        }
        $html .= ' ' . htmlSC((string)$name);
        if ($value !== true) {
            $html .= '="' . htmlSC((string)$value) . '"';
        }
    }

    return $html;
};

if ($total === 0) {
    return;
}
?>

<div class="admin-table-footer">
    <div class="admin-table-info<?= $infoClass !== '' ? ' ' . htmlSC($infoClass) : '' ?>"<?= $renderAttributes($infoAttributes) ?>>
        <?= print_translation('admin_table_showing') ?>
        <span class="fw-semibold"<?= $renderAttributes($visibleAttributes) ?>><?= $visible ?></span>
        <?= print_translation('admin_table_of') ?>
        <span class="fw-semibold"<?= $renderAttributes($totalAttributes) ?>><?= $total ?></span>
        <?php if ($showResultsLabel): ?>
            <span class="d-none d-sm-inline"><?= print_translation('admin_table_results') ?></span>
        <?php endif; ?>
    </div>
    <?php if (!empty($pagination)): ?>
        <div class="admin-pagination-wrap"<?= $renderAttributes($paginationAttributes) ?>>
            <?= $pagination ?>
        </div>
    <?php endif; ?>
</div>
