<?php
declare(strict_types=1);
$icon = static function (string $name, string $class = ''): string {
    return '<svg class="rp-icon ' . htmlSC($class) . '" aria-hidden="true"><use href="#rp-i-' . htmlSC($name) . '"></use></svg>';
};
$button = static function (string $action, string $label, string $name, string $class = '') use ($icon): string {
    return '<button type="button" class="rp-icon-button ' . $class . '" data-action="' . $action . '" aria-label="' . htmlSC($label) . '" title="' . htmlSC($label) . '">' . $icon($name) . '</button>';
};
?>
