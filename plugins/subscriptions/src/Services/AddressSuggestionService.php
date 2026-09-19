<?php

namespace Fireball\Subscriptions\Services;

use Fireball\Subscriptions\Support\RussianRegionCatalog;

/** FIREBALL_SUBSCRIPTIONS_LOCAL_ADDRESS_V1 */
final class AddressSuggestionService
{
    private static bool $schemaReady = false;

    public function configured(): bool
    {
        $this->ensureSchema();

        return (int)db()->query('SELECT COUNT(*) FROM subscription_address_catalog')->getColumn() > 0
            || (int)db()->query(
                "SELECT COUNT(*) FROM subscription_profiles
                 WHERE TRIM(COALESCE(city, '')) <> ''"
            )->getColumn() > 0;
    }

    public function stats(): array
    {
        $this->ensureSchema();

        return [
            'rows' => (int)db()->query('SELECT COUNT(*) FROM subscription_address_catalog')->getColumn(),
            'cities' => (int)db()->query(
                "SELECT COUNT(DISTINCT CONCAT(normalized_region, '\n', normalized_city))
                 FROM subscription_address_catalog"
            )->getColumn(),
            'streets' => (int)db()->query(
                "SELECT COUNT(DISTINCT CONCAT(normalized_region, '\n', normalized_city, '\n', normalized_street))
                 FROM subscription_address_catalog
                 WHERE normalized_street <> ''"
            )->getColumn(),
        ];
    }

    public function clear(): void
    {
        $this->ensureSchema();
        db()->query('DELETE FROM subscription_address_catalog');
    }

    public function importUploaded(array $file, bool $replace = false): array
    {
        $this->ensureSchema();

        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $tmp = (string)($file['tmp_name'] ?? '');
        $name = (string)($file['name'] ?? '');
        $size = (int)($file['size'] ?? 0);

        if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
            throw new \InvalidArgumentException('Не удалось получить загруженный файл.');
        }

        if ($size <= 0 || $size > 150 * 1024 * 1024) {
            throw new \InvalidArgumentException('Файл пустой или превышает 150 МБ.');
        }

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'txt', 'tsv'], true)) {
            throw new \InvalidArgumentException('Поддерживаются CSV, TXT и TSV.');
        }

        $fh = fopen($tmp, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('Не удалось открыть файл справочника.');
        }

        try {
            $first = fgets($fh);
            if (!is_string($first)) {
                throw new \InvalidArgumentException('Файл справочника пуст.');
            }

            $delimiter = $this->detectDelimiter($first);
            rewind($fh);

            $header = fgetcsv($fh, 0, $delimiter);
            if (!is_array($header)) {
                throw new \InvalidArgumentException('Файл справочника пуст.');
            }

            $columns = $this->mapHeader($header);
            if (!isset($columns['region'], $columns['city'])) {
                throw new \InvalidArgumentException(
                    'В CSV обязательны колонки region и city.'
                );
            }

            if ($replace) {
                db()->query('DELETE FROM subscription_address_catalog');
            }

            $processed = 0;
            $skipped = 0;
            $batch = [];

            while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
                $region = $this->cell($row, $columns, 'region');
                $city = $this->cell($row, $columns, 'city');

                if ($region === '' || $city === '') {
                    $skipped++;
                    continue;
                }

                $region = (new RussianRegionCatalog())->resolve($region) ?? $region;
                $street = $this->cell($row, $columns, 'street');
                $house = $this->cell($row, $columns, 'house');
                $postal = $this->cell($row, $columns, 'postal_code');

                $batch[] = [
                    $region,
                    $city,
                    $street,
                    $house,
                    $postal,
                    $this->normalize($region),
                    $this->normalize($city),
                    $this->normalize($street),
                    $this->normalize($house),
                    date('Y-m-d H:i:s'),
                ];

                if (count($batch) >= 300) {
                    $processed += $this->insertBatch($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $processed += $this->insertBatch($batch);
            }

            return ['processed' => $processed, 'skipped' => $skipped];
        } finally {
            fclose($fh);
        }
    }

    public function suggest(string $type, string $query, array $context = []): array
    {
        $this->ensureSchema();

        if (!in_array($type, ['city', 'street', 'house'], true)) {
            return [];
        }

        $query = trim(mb_substr($query, 0, 120));
        if (mb_strlen($query) < ($type === 'house' ? 1 : 2)) {
            return [];
        }

        $country = trim((string)($context['country'] ?? ''));
        $regions = new RussianRegionCatalog();
        if (!$regions->appliesToCountry($country)) {
            return [];
        }

        $regionRaw = trim((string)($context['region'] ?? ''));
        $region = $regions->resolve($regionRaw) ?? $regionRaw;
        $city = trim((string)($context['city'] ?? ''));
        $street = trim((string)($context['street'] ?? ''));

        if ($type !== 'city' && $city === '') {
            return [];
        }
        if ($type === 'house' && $street === '') {
            return [];
        }

        $items = $this->catalogSuggestions(
            $type,
            $this->normalize($query),
            $this->normalize($region),
            $this->normalize($city),
            $this->normalize($street)
        );

        if (count($items) < 12) {
            $items = $this->mergeUnique(
                $items,
                $this->profileSuggestions($type, $query, $region, $city, $street)
            );
        }

        return array_slice($items, 0, 12);
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        db()->query(
            "CREATE TABLE IF NOT EXISTS subscription_address_catalog (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                region VARCHAR(190) NOT NULL,
                city VARCHAR(190) NOT NULL,
                street VARCHAR(255) NOT NULL DEFAULT '',
                house VARCHAR(80) NOT NULL DEFAULT '',
                postal_code VARCHAR(20) NOT NULL DEFAULT '',
                normalized_region VARCHAR(190) NOT NULL,
                normalized_city VARCHAR(190) NOT NULL,
                normalized_street VARCHAR(255) NOT NULL DEFAULT '',
                normalized_house VARCHAR(80) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_subscription_address_catalog (
                    normalized_region,
                    normalized_city,
                    normalized_street,
                    normalized_house,
                    postal_code
                ),
                KEY idx_subscription_address_city (normalized_region, normalized_city),
                KEY idx_subscription_address_street (normalized_region, normalized_city, normalized_street),
                KEY idx_subscription_address_house (normalized_region, normalized_city, normalized_street, normalized_house)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaReady = true;
    }

    private function catalogSuggestions(
        string $type,
        string $query,
        string $region,
        string $city,
        string $street
    ): array {
        $prefix = $query . '%';

        if ($type === 'city') {
            $rows = db()->query(
                "SELECT city AS value, city AS label, '' AS postal_code
                 FROM subscription_address_catalog
                 WHERE (? = '' OR normalized_region = ?)
                   AND normalized_city LIKE ?
                 GROUP BY city, normalized_city
                 ORDER BY normalized_city
                 LIMIT 12",
                [$region, $region, $prefix]
            )->get() ?: [];
        } elseif ($type === 'street') {
            $rows = db()->query(
                "SELECT street AS value, street AS label, '' AS postal_code
                 FROM subscription_address_catalog
                 WHERE (? = '' OR normalized_region = ?)
                   AND normalized_city = ?
                   AND normalized_street LIKE ?
                   AND normalized_street <> ''
                 GROUP BY street, normalized_street
                 ORDER BY normalized_street
                 LIMIT 12",
                [$region, $region, $city, $prefix]
            )->get() ?: [];
        } else {
            $rows = db()->query(
                "SELECT house AS value, house AS label, MAX(postal_code) AS postal_code
                 FROM subscription_address_catalog
                 WHERE (? = '' OR normalized_region = ?)
                   AND normalized_city = ?
                   AND normalized_street = ?
                   AND normalized_house LIKE ?
                   AND normalized_house <> ''
                 GROUP BY house, normalized_house
                 ORDER BY normalized_house
                 LIMIT 12",
                [$region, $region, $city, $street, $prefix]
            )->get() ?: [];
        }

        return array_map(static fn(array $row): array => [
            'value' => (string)($row['value'] ?? ''),
            'label' => (string)($row['label'] ?? ''),
            'postal_code' => (string)($row['postal_code'] ?? ''),
        ], $rows);
    }

    private function profileSuggestions(
        string $type,
        string $query,
        string $region,
        string $city,
        string $street
    ): array {
        $prefix = $query . '%';

        if ($type === 'city') {
            $rows = db()->query(
                "SELECT city AS value, city AS label, '' AS postal_code
                 FROM subscription_profiles
                 WHERE TRIM(COALESCE(city, '')) <> ''
                   AND (? = '' OR LOWER(region) = LOWER(?))
                   AND LOWER(city) LIKE LOWER(?)
                 GROUP BY city
                 ORDER BY city
                 LIMIT 12",
                [$region, $region, $prefix]
            )->get() ?: [];
        } elseif ($type === 'street') {
            $rows = db()->query(
                "SELECT street AS value, street AS label, '' AS postal_code
                 FROM subscription_profiles
                 WHERE TRIM(COALESCE(street, '')) <> ''
                   AND (? = '' OR LOWER(region) = LOWER(?))
                   AND LOWER(city) = LOWER(?)
                   AND LOWER(street) LIKE LOWER(?)
                 GROUP BY street
                 ORDER BY street
                 LIMIT 12",
                [$region, $region, $city, $prefix]
            )->get() ?: [];
        } else {
            $rows = db()->query(
                "SELECT house AS value, house AS label, MAX(postal_code) AS postal_code
                 FROM subscription_profiles
                 WHERE TRIM(COALESCE(house, '')) <> ''
                   AND (? = '' OR LOWER(region) = LOWER(?))
                   AND LOWER(city) = LOWER(?)
                   AND LOWER(street) = LOWER(?)
                   AND LOWER(house) LIKE LOWER(?)
                 GROUP BY house
                 ORDER BY house
                 LIMIT 12",
                [$region, $region, $city, $street, $prefix]
            )->get() ?: [];
        }

        return array_map(static fn(array $row): array => [
            'value' => (string)($row['value'] ?? ''),
            'label' => (string)($row['label'] ?? ''),
            'postal_code' => (string)($row['postal_code'] ?? ''),
        ], $rows);
    }

    private function mergeUnique(array $left, array $right): array
    {
        $result = [];
        $seen = [];

        foreach ([$left, $right] as $list) {
            foreach ($list as $item) {
                $key = $this->normalize((string)($item['value'] ?? ''));
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $result[] = $item;
            }
        }

        return $result;
    }

    private function insertBatch(array $batch): int
    {
        $groups = [];
        $params = [];

        foreach ($batch as $row) {
            $groups[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
            array_push($params, ...$row);
        }

        db()->query(
            'INSERT IGNORE INTO subscription_address_catalog
             (region, city, street, house, postal_code,
              normalized_region, normalized_city, normalized_street,
              normalized_house, created_at)
             VALUES ' . implode(', ', $groups),
            $params
        );

        return count($batch);
    }

    private function detectDelimiter(string $line): string
    {
        $counts = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
        ];
        arsort($counts);

        return (string)array_key_first($counts);
    }

    private function mapHeader(array $header): array
    {
        $aliases = [
            'region' => ['region', 'область', 'регион', 'край', 'субъект'],
            'city' => ['city', 'город', 'населенный пункт', 'населённый пункт'],
            'street' => ['street', 'улица'],
            'house' => ['house', 'дом'],
            'postal_code' => ['postal_code', 'postal code', 'postcode', 'zip', 'индекс', 'почтовый индекс'],
        ];

        $result = [];

        foreach ($header as $index => $name) {
            $key = $this->normalize((string)$name);

            foreach ($aliases as $field => $variants) {
                foreach ($variants as $variant) {
                    if ($key === $this->normalize($variant)) {
                        $result[$field] = (int)$index;
                        break 2;
                    }
                }
            }
        }

        return $result;
    }

    private function cell(array $row, array $columns, string $key): string
    {
        if (!isset($columns[$key])) {
            return '';
        }

        return trim(mb_substr((string)($row[$columns[$key]] ?? ''), 0, 500));
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace('ё', 'е', $value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
