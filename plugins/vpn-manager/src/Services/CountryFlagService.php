<?php

namespace Fireball\VpnManager\Services;

final class CountryFlagService
{
    /**
     * Small local ISO 3166-1 alpha-2 directory. It is intentionally local so
     * server forms and config names never depend on a remote country API.
     */
    private const COUNTRIES = [
        'DE' => ['ru' => 'Германия', 'en' => 'Germany', 'de' => 'Deutschland', 'zh-cn' => '德国'],
        'NL' => ['ru' => 'Нидерланды', 'en' => 'Netherlands', 'de' => 'Niederlande', 'zh-cn' => '荷兰'],
        'EE' => ['ru' => 'Эстония', 'en' => 'Estonia', 'de' => 'Estland', 'zh-cn' => '爱沙尼亚'],
        'FI' => ['ru' => 'Финляндия', 'en' => 'Finland', 'de' => 'Finnland', 'zh-cn' => '芬兰'],
        'US' => ['ru' => 'США', 'en' => 'United States', 'de' => 'USA', 'zh-cn' => '美国'],
        'GB' => ['ru' => 'Великобритания', 'en' => 'United Kingdom', 'de' => 'Vereinigtes Koenigreich', 'zh-cn' => '英国'],
    ];

    public function countries(?string $locale = null): array
    {
        $locale = strtolower(trim((string)($locale ?: (function_exists('current_locale') ? current_locale() : 'en'))));
        $fallback = 'en';
        $items = [];

        foreach (self::COUNTRIES as $code => $labels) {
            $items[$code] = [
                'code' => $code,
                'name' => $labels[$locale] ?? $labels[$fallback] ?? $code,
                'flag' => $this->flagFromCountryCode($code),
            ];
        }

        return $items;
    }

    public function countryName(string $code, ?string $locale = null): string
    {
        $code = $this->normalizeCountryCode($code);
        if (!$this->isValidCountryCode($code)) {
            return '';
        }

        $locale = strtolower(trim((string)($locale ?: (function_exists('current_locale') ? current_locale() : 'en'))));
        $labels = self::COUNTRIES[$code] ?? [];

        return (string)($labels[$locale] ?? $labels['en'] ?? $code);
    }

    public function normalizeCountryCode(string $code): string
    {
        return strtoupper(preg_replace('/[^a-z]/i', '', trim($code)) ?? '');
    }

    public function isValidCountryCode(string $code): bool
    {
        return isset(self::COUNTRIES[$this->normalizeCountryCode($code)]);
    }

    public function flagFromCountryCode(string $code): string
    {
        $code = $this->normalizeCountryCode($code);
        if (!$this->isValidCountryCode($code)) {
            return '';
        }

        $flag = '';
        foreach (str_split($code) as $letter) {
            $flag .= mb_chr(0x1F1E6 + ord($letter) - ord('A'), 'UTF-8');
        }

        return $flag;
    }
}
