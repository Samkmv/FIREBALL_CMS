<?php

namespace Fireball\VpnManagerV2\Support;

final class AdminActionDropdown
{
    public static function render(array $actions, ?string $label = null): string
    {
        $items = '';
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            if (($action['type'] ?? '') === 'divider') {
                $items .= '<hr class="dropdown-divider">';
                continue;
            }
            $items .= self::item($action);
        }
        if ($items === '') {
            return '—';
        }

        $label = trim((string)$label) ?: \FireballPluginVpnManagerV2::t('vpn_manager_v2_col_actions');

        return '<div class="dropdown admin-post-actions-dropdown d-inline-block" data-admin-post-actions-dropdown>'
            . '<button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="button"'
            . ' data-bs-toggle="dropdown" data-bs-display="static" data-bs-boundary="viewport"'
            . ' aria-expanded="false" aria-label="' . htmlSC($label) . '">'
            . '<i class="ci-more-vertical" aria-hidden="true"></i></button>'
            . '<div class="dropdown-menu dropdown-menu-end shadow-sm rounded-4">'
            . $items
            . '</div></div>';
    }

    private static function item(array $action): string
    {
        $icon = trim((string)($action['icon'] ?? ''));
        $content = ($icon !== '' ? '<i class="' . htmlSC($icon) . '" aria-hidden="true"></i>' : '')
            . '<span>' . htmlSC((string)($action['label'] ?? '')) . '</span>';
        $class = trim('dropdown-item d-flex align-items-center gap-2 ' . (string)($action['class'] ?? ''));
        $type = (string)($action['type'] ?? 'link');
        if ($type === 'form') {
            $form = is_array($action['form_attributes'] ?? null) ? $action['form_attributes'] : [];
            $form['action'] = (string)($action['action'] ?? '#');
            $form['method'] = (string)($action['method'] ?? 'post');
            $html = '<form' . self::attributes($form) . '>';
            if (($action['csrf'] ?? true) !== false) {
                $html .= get_csrf_field();
            }
            foreach ((array)($action['hidden'] ?? []) as $name => $value) {
                $html .= '<input type="hidden" name="' . htmlSC((string)$name)
                    . '" value="' . htmlSC((string)$value) . '">';
            }

            return $html . '<button class="' . htmlSC($class) . '" type="submit">'
                . $content . '</button></form>';
        }
        if ($type === 'button') {
            $attributes = is_array($action['attributes'] ?? null) ? $action['attributes'] : [];
            $attributes['class'] = trim($class . ' ' . (string)($attributes['class'] ?? ''));
            $attributes['type'] = (string)($attributes['type'] ?? 'button');

            return '<button' . self::attributes($attributes) . '>' . $content . '</button>';
        }
        $attributes = is_array($action['attributes'] ?? null) ? $action['attributes'] : [];
        $attributes['class'] = trim($class . ' ' . (string)($attributes['class'] ?? ''));
        $attributes['href'] = (string)($action['href'] ?? '#');
        if (!empty($action['target'])) {
            $attributes['target'] = (string)$action['target'];
        }
        if (!empty($action['rel'])) {
            $attributes['rel'] = (string)$action['rel'];
        }

        return '<a' . self::attributes($attributes) . '>' . $content . '</a>';
    }

    private static function attributes(array $attributes): string
    {
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
    }

    private function __construct()
    {
    }
}
