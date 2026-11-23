<?php

namespace App\Support;

final class HomepageModes
{
    /** @return array<string, array{label: string, render: callable}> */
    public static function extensions(): array
    {
        $modes = (array)apply_filters('site_homepage_modes', []);
        $valid = [];
        foreach ($modes as $key => $mode) {
            if (is_string($key) && preg_match('/\A[a-z][a-z0-9_-]{0,47}\z/', $key)
                && !in_array($key, ['default', 'page', 'posts'], true)
                && is_array($mode) && is_string($mode['label'] ?? null) && is_callable($mode['render'] ?? null)) {
                $valid[$key] = $mode;
            }
        }
        return $valid;
    }
}
