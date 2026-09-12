<?php

namespace Fireball\Subscriptions\Support;

final class RussianRegionCatalog
{
    private static ?array $entries = null;
    private static ?array $index = null;

    public static function countryAliases(): array
    {
        return ['Россия', 'Российская Федерация', 'РФ', 'Russia', 'Russian Federation', 'RU', 'RUS', 'Rossiya', 'Rossia', 'Rossija'];
    }

    public function appliesToCountry(string $country): bool
    {
        $country = self::key($country);

        return $country === '' || in_array($country, array_map(self::key(...), self::countryAliases()), true);
    }

    public function entries(): array
    {
        if (self::$entries !== null) {
            return self::$entries;
        }
        $rows = require __DIR__ . '/../../resources/regions.php';
        $entries = [];
        foreach ($rows as $name => $aliases) {
            $aliases[] = $this->transliterate($name);
            // Common English translations of the region types must also be searchable.
            foreach ($aliases as $alias) {
                $aliases[] = str_replace(['Oblast', 'Krai', 'Republic of '], ['Region', 'Territory', ''], $alias);
            }
            $entries[] = ['name' => $name, 'aliases' => array_values(array_unique($aliases))];
        }
        usort($entries, static fn(array $a, array $b): int => strcmp($a['name'], $b['name']));

        return self::$entries = $entries;
    }

    public function resolve(string $value): ?string
    {
        if (self::$index === null) {
            self::$index = [];
            foreach ($this->entries() as $entry) {
                foreach ([$entry['name'], ...$entry['aliases']] as $alias) {
                    $key = self::key($alias);
                    // Ambiguous aliases must be selected explicitly, never guessed.
                    if (array_key_exists($key, self::$index) && self::$index[$key] !== $entry['name']) {
                        self::$index[$key] = null;
                    } else {
                        self::$index[$key] = $entry['name'];
                    }
                }
            }
        }

        return self::$index[self::key($value)] ?? null;
    }

    public function normalize(string $value, string $country): string
    {
        $value = trim($value);
        if ($value === '' || !$this->appliesToCountry($country)) {
            return $value;
        }

        return $this->resolve($value)
            ?? throw new \InvalidArgumentException(\FireballPluginSubscriptions::t('subscriptions_error_region_select'));
    }

    private static function key(string $value): string
    {
        $value = str_replace(['ё', "'", '’', 'ʼ'], ['е', '', '', ''], mb_strtolower(trim($value), 'UTF-8'));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\bautonomous (?:okrug|district)\b/u', 'автономный округ', $value) ?? '';
        $value = preg_replace('/\bautonomous\b/u', 'автономная', $value) ?? '';
        $tokens = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $types = [
            'обл' => 'область', 'oblast' => 'область', 'region' => 'область',
            'krai' => 'край', 'kray' => 'край', 'territory' => 'край',
            'респ' => 'республика', 'republic' => 'республика', 'respublika' => 'республика',
            'of' => '',
        ];
        $tokens = array_filter(array_map(static fn(string $token): string => $types[$token] ?? $token, $tokens));
        sort($tokens, SORT_STRING);

        return implode(' ', $tokens);
    }

    private function transliterate(string $value): string
    {
        return strtr(mb_strtolower($value, 'UTF-8'), [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e',
            'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm',
            'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u',
            'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
            'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ]);
    }
}
