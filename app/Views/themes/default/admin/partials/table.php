<?php
$tableClass = trim('table align-middle mb-0 admin-table-component__table ' . (string)($table_class ?? ''));
$wrapperClass = trim('table-responsive admin-table-component__scroll ' . (string)($wrapper_class ?? ''));
$tbodyClass = trim((string)($tbody_class ?? ''));
$emptyText = (string)($empty_text ?? return_translation('admin_table_empty'));
$columns = is_array($columns ?? null) ? $columns : [];
$rows = is_array($rows ?? null) ? $rows : [];
$content = (string)($content ?? '');
$caption = (string)($caption ?? '');
$tableAttributes = is_array($table_attributes ?? null) ? $table_attributes : [];
$wrapperAttributes = is_array($wrapper_attributes ?? null) ? $wrapper_attributes : [];
$mobileCardsProvided = isset($mobile_cards) && is_array($mobile_cards);
$mobileCards = $mobileCardsProvided ? $mobile_cards : null;
$mobileCardsAutoBuilt = false;
$mobileAttributes = is_array($mobile_attributes ?? null) ? $mobile_attributes : [];
$mobileBreakpoint = (string)($mobile_breakpoint ?? 'md');
$allowedMobileBreakpoints = ['sm', 'md', 'lg', 'xl', 'xxl'];
if (!in_array($mobileBreakpoint, $allowedMobileBreakpoints, true)) {
    $mobileBreakpoint = 'md';
}
$mobileTableHideClass = 'd-none d-' . $mobileBreakpoint . '-block';
$mobileCardsDisplayClass = 'd-' . $mobileBreakpoint . '-none';
$clickableRows = !empty($clickable_rows);

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

$renderCell = static function (array $cell, string $tag = 'td') use ($renderAttributes): string {
    $cellTag = !empty($cell['header']) ? 'th' : $tag;
    $attributes = is_array($cell['attributes'] ?? null) ? $cell['attributes'] : [];

    if (!empty($cell['class'])) {
        $attributes['class'] = trim((string)($attributes['class'] ?? '') . ' ' . (string)$cell['class']);
    }

    if ($cellTag === 'th' && !isset($attributes['scope'])) {
        $attributes['scope'] = 'row';
    }

    $html = array_key_exists('html', $cell)
        ? (string)$cell['html']
        : htmlSC((string)($cell['value'] ?? ''));

    return '<' . $cellTag . $renderAttributes($attributes) . '>' . $html . '</' . $cellTag . '>';
};

$cellHtml = static function (array $cell): string {
    return array_key_exists('html', $cell)
        ? (string)$cell['html']
        : htmlSC((string)($cell['value'] ?? ''));
};

$columnLabel = static function (array $column): string {
    return trim(strip_tags(array_key_exists('html', $column) ? (string)$column['html'] : (string)($column['label'] ?? '')));
};

if ($mobileCards === null && $content === '') {
    $mobileCardsAutoBuilt = true;
    $mobileCards = [];

    foreach ($rows as $row) {
        $cells = array_values((array)($row['cells'] ?? []));
        if (empty($cells)) {
            continue;
        }

        $actionIndex = null;
        $statusIndex = null;
        foreach ($cells as $index => $cell) {
            $label = $columnLabel((array)($columns[$index] ?? []));
            $html = $cellHtml((array)$cell);
            $normalizedLabel = mb_strtolower($label);

            if ($actionIndex === null && (str_contains($normalizedLabel, 'action') || str_contains($normalizedLabel, 'действ') || str_contains($html, 'dropdown'))) {
                $actionIndex = $index;
                continue;
            }

            if ($statusIndex === null && (str_contains($normalizedLabel, 'status') || str_contains($normalizedLabel, 'статус') || str_contains($html, 'badge'))) {
                $statusIndex = $index;
            }
        }

        $card = [
            'id' => isset($cells[0]) ? ['html' => $cellHtml((array)$cells[0])] : null,
            'title' => isset($cells[1]) ? ['html' => $cellHtml((array)$cells[1])] : null,
            'actions' => $actionIndex !== null ? $cellHtml((array)$cells[$actionIndex]) : null,
            'status' => $statusIndex !== null ? [['html' => $cellHtml((array)$cells[$statusIndex])]] : null,
            'extra_fields' => [],
        ];

        foreach ($cells as $index => $cell) {
            if ($index === 0 || $index === 1 || $index === $actionIndex || $index === $statusIndex) {
                continue;
            }

            $label = $columnLabel((array)($columns[$index] ?? []));
            if ($label === '') {
                continue;
            }

            $card['extra_fields'][] = [
                'label' => $label,
                'html' => $cellHtml((array)$cell),
            ];
        }

        $mobileCards[] = $card;
    }
}

$shouldRenderMobileCards = is_array($mobileCards) && ($mobileCardsProvided || $mobileCardsAutoBuilt || !empty($mobileCards));
if ($shouldRenderMobileCards) {
    $wrapperClass = trim($wrapperClass . ' ' . $mobileTableHideClass);
}
?>
<div class="<?= htmlSC($wrapperClass) ?>"<?= $renderAttributes($wrapperAttributes) ?>>
    <table class="<?= htmlSC($tableClass) ?>"<?= $renderAttributes($tableAttributes) ?>>
        <?php if ($caption !== ''): ?>
            <caption class="visually-hidden"><?= htmlSC($caption) ?></caption>
        <?php endif; ?>

        <?php if ($content !== ''): ?>
            <?= $content ?>
        <?php else: ?>
            <thead class="position-sticky top-0">
            <tr>
                <?php foreach ($columns as $column): ?>
                    <?php
                    $attributes = is_array($column['attributes'] ?? null) ? $column['attributes'] : [];
                    if (!empty($column['class'])) {
                        $attributes['class'] = trim((string)($attributes['class'] ?? '') . ' ' . (string)$column['class']);
                    }
                    if (!isset($attributes['scope'])) {
                        $attributes['scope'] = 'col';
                    }
                    $label = array_key_exists('html', $column)
                        ? (string)$column['html']
                        : htmlSC((string)($column['label'] ?? ''));
                    ?>
                    <th<?= $renderAttributes($attributes) ?>><?= $label ?></th>
                <?php endforeach; ?>
            </tr>
            </thead>
            <tbody<?= $tbodyClass !== '' ? ' class="' . htmlSC($tbodyClass) . '"' : '' ?>>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="<?= max(1, count($columns)) ?>" class="text-center text-body-secondary py-5">
                        <?= htmlSC($emptyText) ?>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $rowAttributes = is_array($row['attributes'] ?? null) ? $row['attributes'] : [];
                    $rowClass = trim((string)($rowAttributes['class'] ?? '') . ($clickableRows ? ' admin-table-component__row--clickable' : ''));
                    if ($rowClass !== '') {
                        $rowAttributes['class'] = $rowClass;
                    }
                    ?>
                    <tr<?= $renderAttributes($rowAttributes) ?>>
                        <?php foreach ((array)($row['cells'] ?? []) as $cell): ?>
                            <?= $renderCell((array)$cell) ?>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        <?php endif; ?>
    </table>
</div>
<?php if ($shouldRenderMobileCards): ?>
    <?= view()->renderPartial('admin/partials/responsive_table_cards', [
        'cards' => $mobileCards,
        'attributes' => $mobileAttributes,
        'display_class' => $mobileCardsDisplayClass,
        'empty_text' => $emptyText,
    ]) ?>
<?php endif; ?>
