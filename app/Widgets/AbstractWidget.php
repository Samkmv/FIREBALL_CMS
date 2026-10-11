<?php

namespace App\Widgets;

abstract class AbstractWidget implements WidgetInterface
{
    public function validate(array $settings, WidgetContext $context): array
    {
        $result = $this->defaults();
        foreach ($this->fields($context) as $key => $field) {
            $value = $settings[$key] ?? $result[$key] ?? null;
            $valid = true;
            switch ($field['type']) {
                case 'checkbox':
                    $valid = in_array($value, [true, false, 0, 1, '0', '1'], true);
                    $value = (bool)$value;
                    break;
                case 'number':
                    $valid = filter_var($value, FILTER_VALIDATE_INT) !== false
                        && (int)$value >= $field['min'] && (int)$value <= $field['max'];
                    $value = (int)$value;
                    break;
                case 'select':
                    $valid = is_string($value) && array_key_exists($value, $field['options']);
                    break;
                case 'categories':
                    $valid = is_array($value) && count($value) <= 500;
                    if ($valid) {
                        foreach ($value as $slug) {
                            if (!is_string($slug) || strlen($slug) > 255 || $slug === '') { $valid = false; break; }
                        }
                        $value = array_values(array_unique($value));
                    }
                    break;
                default:
                    $valid = is_string($value) && mb_strlen($value) <= ($field['max'] ?? 150);
                    if ($valid) $value = trim($value);
            }
            if (!$valid) throw new \InvalidArgumentException(return_translation('widgets_invalid_setting') . ': ' . return_translation($field['label']));
            $result[$key] = $value;
        }
        return $result;
    }

    protected function titleField(): array { return ['type' => 'text', 'label' => 'widgets_title', 'max' => 150]; }
}
