<?php
$cards = is_array($cards ?? null) ? $cards : [];
$displayClass = trim((string)($display_class ?? 'd-md-none'));
$componentClass = trim('admin-mobile-table-cards ' . $displayClass . ' ' . (string)($class ?? ''));
$attributes = is_array($attributes ?? null) ? $attributes : [];
$actionsLabel = (string)($actions_label ?? return_translation('admin_posts_col_actions'));
$emptyText = (string)($empty_text ?? return_translation('admin_table_empty'));

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

$hasValue = static function ($value): bool {
    if ($value === null || $value === false) {
        return false;
    }

    if (is_array($value)) {
        if (array_key_exists('html', $value) || array_key_exists('value', $value) || array_key_exists('label', $value)) {
            return trim((string)($value['html'] ?? $value['value'] ?? $value['label'] ?? '')) !== '';
        }

        return !empty($value);
    }

    return trim((string)$value) !== '';
};

$renderValue = static function ($value) use ($hasValue): string {
    if (!$hasValue($value)) {
        return '';
    }

    if (is_array($value)) {
        if (array_key_exists('html', $value)) {
            return (string)$value['html'];
        }

        return htmlSC((string)($value['value'] ?? $value['label'] ?? ''));
    }

    return htmlSC((string)$value);
};

$renderBadges = static function ($badges) use ($hasValue): string {
    if (!$hasValue($badges)) {
        return '';
    }

    $items = is_array($badges) && !array_key_exists('label', $badges) && !array_key_exists('html', $badges) && !array_key_exists('value', $badges)
        ? $badges
        : [$badges];

    $html = '';
    foreach ($items as $badge) {
        if (!$hasValue($badge)) {
            continue;
        }

        if (is_array($badge) && array_key_exists('html', $badge)) {
            $html .= (string)$badge['html'];
            continue;
        }

        $label = is_array($badge) ? (string)($badge['label'] ?? $badge['value'] ?? '') : (string)$badge;
        $class = trim('badge fs-xs rounded-pill ' . (is_array($badge) ? (string)($badge['class'] ?? 'text-secondary bg-secondary-subtle') : 'text-secondary bg-secondary-subtle'));
        $html .= '<span class="' . htmlSC($class) . '">' . htmlSC($label) . '</span>';
    }

    return $html;
};

$renderActionItems = static function (array $actions) use ($renderAttributes): string {
    $html = '';

    foreach ($actions as $action) {
        if (!is_array($action)) {
            continue;
        }

        if (($action['type'] ?? '') === 'divider') {
            $html .= '<hr class="dropdown-divider">';
            continue;
        }

        $icon = trim((string)($action['icon'] ?? ''));
        $label = htmlSC((string)($action['label'] ?? ''));
        $content = ($icon !== '' ? '<i class="' . htmlSC($icon) . '"></i>' : '') . '<span>' . $label . '</span>';
        $itemClass = trim('dropdown-item d-flex align-items-center gap-2 ' . (string)($action['class'] ?? ''));

        if (($action['type'] ?? 'link') === 'form') {
            $formAttributes = is_array($action['form_attributes'] ?? null) ? $action['form_attributes'] : [];
            $formAttributes['action'] = (string)($action['action'] ?? '#');
            $formAttributes['method'] = (string)($action['method'] ?? 'post');
            $html .= '<form' . $renderAttributes($formAttributes) . '>';
            if (($action['csrf'] ?? true) !== false) {
                $html .= get_csrf_field();
            }
            foreach ((array)($action['hidden'] ?? []) as $name => $value) {
                $html .= '<input type="hidden" name="' . htmlSC((string)$name) . '" value="' . htmlSC((string)$value) . '">';
            }
            $html .= '<button class="' . htmlSC($itemClass) . '" type="submit">' . $content . '</button></form>';
            continue;
        }

        if (($action['type'] ?? 'link') === 'button') {
            $buttonAttributes = is_array($action['attributes'] ?? null) ? $action['attributes'] : [];
            $buttonAttributes['class'] = trim($itemClass . ' ' . (string)($buttonAttributes['class'] ?? ''));
            $buttonAttributes['type'] = (string)($buttonAttributes['type'] ?? 'button');
            $html .= '<button' . $renderAttributes($buttonAttributes) . '>' . $content . '</button>';
            continue;
        }

        $linkAttributes = is_array($action['attributes'] ?? null) ? $action['attributes'] : [];
        $linkAttributes['class'] = trim($itemClass . ' ' . (string)($linkAttributes['class'] ?? ''));
        $linkAttributes['href'] = (string)($action['href'] ?? '#');
        if (!empty($action['target'])) {
            $linkAttributes['target'] = (string)$action['target'];
        }
        if (!empty($action['rel'])) {
            $linkAttributes['rel'] = (string)$action['rel'];
        }
        $html .= '<a' . $renderAttributes($linkAttributes) . '>' . $content . '</a>';
    }

    return $html;
};

$renderActions = static function ($actions) use ($actionsLabel, $renderActionItems): string {
    if (is_string($actions) && trim($actions) !== '') {
        return $actions;
    }

    if (!is_array($actions) || empty($actions)) {
        return '';
    }

    $items = $renderActionItems($actions);
    if ($items === '') {
        return '';
    }

    return '<div class="dropdown admin-post-actions-dropdown admin-mobile-table-card__actions d-inline-block">'
        . '<button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="button" data-bs-toggle="dropdown" data-bs-display="static" data-bs-boundary="viewport" aria-expanded="false" aria-label="' . htmlSC($actionsLabel) . '">'
        . '<i class="ci-more-vertical"></i>'
        . '</button>'
        . '<div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">'
        . $items
        . '</div>'
        . '</div>';
};

$renderField = static function (string $label, $value, string $class = '') use ($hasValue, $renderValue): void {
    if (!$hasValue($value)) {
        return;
    }
    ?>
    <li class="list-group-item px-3 py-2 <?= htmlSC($class) ?>">
        <div class="d-flex align-items-start justify-content-between gap-3">
            <span class="small text-body-secondary flex-shrink-0"><?= htmlSC($label) ?></span>
            <span class="fw-medium text-body-emphasis text-break text-end min-w-0"><?= $renderValue($value) ?></span>
        </div>
    </li>
    <?php
};

$attributes = array_merge(['data-admin-mobile-table-cards' => true], $attributes);
?>
<div class="<?= htmlSC($componentClass) ?>"<?= $renderAttributes($attributes) ?>>
    <?php if (empty($cards)): ?>
        <div class="card admin-mobile-table-card shadow-sm">
            <div class="card-body p-4 text-center text-body-secondary">
                <?= htmlSC($emptyText) ?>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($cards as $card): ?>
        <?php
        $card = is_array($card) ? $card : [];
        $cardAttributes = is_array($card['attributes'] ?? null) ? $card['attributes'] : [];
        $cardAttributes['class'] = trim('card admin-mobile-table-card shadow-sm ' . (string)($cardAttributes['class'] ?? ''));
        $actionsHtml = $renderActions($card['actions'] ?? null);
        $image = $card['image'] ?? null;
        $imageSrc = is_array($image) ? trim((string)($image['src'] ?? '')) : trim((string)$image);
        $icon = $card['icon'] ?? null;
        $iconClass = is_array($icon) ? trim((string)($icon['class'] ?? '')) : trim((string)$icon);
        $iconWrapClass = trim('rounded-circle border d-flex align-items-center justify-content-center admin-mobile-table-card__image ' . (is_array($icon) ? (string)($icon['wrapper_class'] ?? 'bg-body-tertiary') : 'bg-body-tertiary'));
        $iconTextClass = trim($iconClass . ' fs-5 ' . (is_array($icon) ? (string)($icon['text_class'] ?? 'text-body-secondary') : 'text-body-secondary'));
        $titleFallback = $card['title'] ?? '';
        if (is_array($titleFallback)) {
            $titleFallback = strip_tags((string)($titleFallback['html'] ?? $titleFallback['value'] ?? $titleFallback['label'] ?? ''));
        }
        $imageAlt = is_array($image) ? (string)($image['alt'] ?? $titleFallback) : (string)$titleFallback;
        $titleHtml = $renderValue($card['title'] ?? '');
        $idHtml = $renderValue($card['id'] ?? '');
        $selectionHtml = $renderValue($card['selection'] ?? null);
        $statusHtml = $renderBadges($card['status'] ?? null);
        ?>
        <article<?= $renderAttributes($cardAttributes) ?>>
            <div class="card-body p-3">
                <div class="d-flex align-items-start gap-3">
                    <?php if ($imageSrc !== ''): ?>
                        <img src="<?= htmlSC($imageSrc) ?>" alt="<?= htmlSC($imageAlt) ?>" class="rounded-circle border object-fit-cover admin-mobile-table-card__image">
                    <?php elseif ($iconClass !== ''): ?>
                        <span class="<?= htmlSC($iconWrapClass) ?>" aria-hidden="true">
                            <i class="<?= htmlSC($iconTextClass) ?>"></i>
                        </span>
                    <?php endif; ?>
                    <div class="min-w-0 flex-grow-1">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div class="min-w-0 d-flex align-items-baseline gap-2 flex-wrap">
                                <?php if ($selectionHtml !== ''): ?>
                                    <span class="flex-shrink-0"><?= $selectionHtml ?></span>
                                <?php endif; ?>
                                <?php if ($idHtml !== ''): ?>
                                    <span class="small text-body-secondary text-nowrap">ID: <span class="fw-semibold text-body"><?= $idHtml ?></span></span>
                                <?php endif; ?>
                                <?php if ($titleHtml !== ''): ?>
                                    <h3 class="h6 mb-0 admin-mobile-table-card__title"><?= $titleHtml ?></h3>
                                <?php endif; ?>
                            </div>
                            <?php if ($actionsHtml !== ''): ?>
                                <div class="flex-shrink-0"><?= $actionsHtml ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <ul class="list-group list-group-flush">
                <?php $renderField((string)($card['slug_label'] ?? 'Slug'), $card['slug'] ?? null); ?>
                <?php $renderField((string)($card['category_label'] ?? return_translation('admin_posts_col_category')), $card['category'] ?? null); ?>
                <?php $renderField((string)($card['author_label'] ?? return_translation('admin_posts_col_author')), $card['author'] ?? null); ?>

                <?php foreach ((array)($card['extra_fields'] ?? []) as $field): ?>
                    <?php
                    if (!is_array($field)) {
                        continue;
                    }
                    $fieldValue = array_key_exists('html', $field)
                        ? ['html' => (string)$field['html']]
                        : ($field['value'] ?? null);
                    $renderField((string)($field['label'] ?? ''), $fieldValue);
                    ?>
                <?php endforeach; ?>

                <?php if ($hasValue($card['order'] ?? null) || $hasValue($card['views'] ?? null)): ?>
                    <li class="list-group-item px-3 py-2">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <?php if ($hasValue($card['order'] ?? null)): ?>
                                <div class="min-w-0">
                                    <span class="d-block small text-body-secondary"><?= htmlSC((string)($card['order_label'] ?? return_translation('admin_pages_col_order'))) ?></span>
                                    <span class="fw-medium text-body-emphasis text-break"><?= $renderValue($card['order']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($hasValue($card['views'] ?? null)): ?>
                                <div class="min-w-0 text-end ms-auto">
                                    <span class="d-block small text-body-secondary"><?= htmlSC((string)($card['views_label'] ?? return_translation('admin_posts_col_views'))) ?></span>
                                    <span class="fw-medium text-body-emphasis text-break"><?= $renderValue($card['views']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endif; ?>

                <?php if ($statusHtml !== ''): ?>
                    <li class="list-group-item px-3 py-2">
                        <div class="d-flex align-items-start justify-content-between gap-3">
                            <span class="small text-body-secondary flex-shrink-0"><?= htmlSC((string)($card['status_label'] ?? return_translation('admin_posts_col_status'))) ?></span>
                            <span class="d-flex flex-wrap justify-content-end gap-1 min-w-0"><?= $statusHtml ?></span>
                        </div>
                    </li>
                <?php endif; ?>

                <?php if ($hasValue($card['published_at'] ?? null)): ?>
                    <li class="list-group-item px-3 py-2">
                        <div class="d-flex align-items-center justify-content-between gap-3">
                            <div class="min-w-0">
                                <span class="d-block small text-body-secondary"><?= htmlSC((string)($card['published_at_label'] ?? return_translation('admin_posts_col_date'))) ?></span>
                                <span class="fw-medium text-body-emphasis text-break"><?= $renderValue($card['published_at']) ?></span>
                            </div>
                            <?php if ($actionsHtml !== ''): ?>
                                <div class="flex-shrink-0"><?= $actionsHtml ?></div>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
