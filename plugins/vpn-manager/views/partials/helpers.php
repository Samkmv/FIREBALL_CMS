<?php

use Fireball\VpnManager\Support\Formatter;

if (!function_exists('vpnm_status_badge')) {
    function vpnm_status_badge(string $status): string
    {
        return Formatter::statusBadge($status);
    }
}

if (!function_exists('vpnm_actions_dropdown')) {
    function vpnm_actions_dropdown(array $actions, string $label = ''): string
    {
        static $dropdownIndex = 0;

        $dropdownIndex++;
        $menuId = 'vpn-action-menu-' . $dropdownIndex . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
        $label = $label !== '' ? $label : FireballPluginVpnManager::t('vpn_manager_actions');
        $html = '<div class="dropdown admin-post-actions-dropdown d-inline-block" data-vpn-dropdown>';
        $html .= '<button class="btn btn-sm btn-outline-secondary btn-icon rounded-circle" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="' . htmlSC($menuId) . '" aria-label="' . htmlSC($label) . '" data-vpn-dropdown-toggle><i class="ci-more-vertical"></i></button>';
        $html .= '<div id="' . htmlSC($menuId) . '" class="dropdown-menu dropdown-menu-end shadow-sm rounded-4" role="menu" hidden data-vpn-dropdown-menu>';

        foreach ($actions as $action) {
            if (($action['type'] ?? '') === 'divider') {
                $html .= '<hr class="dropdown-divider" role="separator">';
                continue;
            }

            $icon = trim((string)($action['icon'] ?? 'ci-chevron-right'));
            $text = htmlSC((string)($action['label'] ?? ''));
            $class = trim('dropdown-item d-flex align-items-center gap-2 ' . (string)($action['class'] ?? ''));
            $content = '<i class="' . htmlSC($icon) . '"></i><span>' . $text . '</span>';

            if (($action['type'] ?? 'link') === 'form') {
                $confirm = trim((string)($action['confirm'] ?? ''));
                $formAttributes = '';
                if ($confirm !== '') {
                    $deleteItem = trim((string)($action['delete_item'] ?? $action['item'] ?? ''));
                    $deleteConfirmLabel = trim((string)($action['delete_confirm_label'] ?? $action['label'] ?? ''));
                    $formAttributes .= ' data-admin-delete-form';
                    $formAttributes .= ' data-delete-message="' . htmlSC($confirm) . '"';
                    if ($deleteItem !== '') {
                        $formAttributes .= ' data-delete-item="' . htmlSC($deleteItem) . '"';
                    }
                    if ($deleteConfirmLabel !== '') {
                        $formAttributes .= ' data-delete-confirm-label="' . htmlSC($deleteConfirmLabel) . '"';
                    }
                }
                $html .= '<form action="' . htmlSC((string)($action['action'] ?? '#')) . '" method="post"'
                    . $formAttributes
                    . '>';
                $html .= get_csrf_field();
                foreach ((array)($action['hidden'] ?? []) as $name => $value) {
                    $html .= '<input type="hidden" name="' . htmlSC((string)$name) . '" value="' . htmlSC((string)$value) . '">';
                }
                $html .= '<button class="' . htmlSC($class) . '" type="submit" role="menuitem" data-vpn-dropdown-action>' . $content . '</button>';
                $html .= '</form>';
                continue;
            }

            $html .= '<a class="' . htmlSC($class) . '" href="' . htmlSC((string)($action['href'] ?? '#')) . '" role="menuitem" data-vpn-dropdown-action>' . $content . '</a>';
        }

        $html .= '</div></div>';

        return $html;
    }
}

if (!function_exists('vpnm_server_label')) {
    function vpnm_server_label(array $server, string $fallback = ''): string
    {
        $name = trim((string)($server['server_name'] ?? $server['name'] ?? $server['code'] ?? $fallback));
        if ($name === '') {
            $name = $fallback !== '' ? $fallback : FireballPluginVpnManager::t('vpn_manager_server_missing');
        }

        $flag = !empty($server['show_flag']) ? trim((string)($server['flag_emoji'] ?? '')) : '';
        if ($flag !== '' && !str_starts_with($name, $flag)) {
            $name = $flag . ' ' . $name;
        }

        return preg_replace('/\s+/u', ' ', trim($name)) ?? trim($name);
    }
}

if (!function_exists('vpnm_empty_state')) {
    function vpnm_empty_state(string $title, string $text, string $icon = 'ci-info'): string
    {
        return '<div class="border rounded-5 p-4 p-md-5 text-center text-body-secondary">'
            . '<div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-body-tertiary mb-3" style="width:3rem;height:3rem;"><i class="' . htmlSC($icon) . ' fs-4"></i></div>'
            . '<h2 class="h5 text-body-emphasis mb-2">' . htmlSC($title) . '</h2>'
            . '<p class="mb-0">' . htmlSC($text) . '</p>'
            . '</div>';
    }
}
