<?php

namespace Fireball\Subscriptions\Services;

use Fireball\Subscriptions\Support\RussianRegionCatalog;

/**
 * FIREBALL_SUBSCRIPTIONS_DADATA_ADDRESS_V1
 *
 * Server-side proxy for DaData address suggestions.
 * The API token never reaches the browser.
 */
final class AddressSuggestionService
{
    private const ENDPOINT = 'https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest/address';

    public function configured(): bool
    {
        $settings = (new SettingsService())->current();

        return !empty($settings['dadata_enabled'])
            && !empty($settings['dadata_token_configured']);
    }

    public function suggest(string $type, string $query, array $context = []): array
    {
        if (!in_array($type, ['city', 'street', 'house'], true)) {
            return [];
        }

        $query = trim(mb_substr($query, 0, 120));
        $minimum = $type === 'house' ? 1 : 2;
        if (mb_strlen($query) < $minimum) {
            return [];
        }

        $settings = (new SettingsService())->current(true);
        $token = trim((string)($settings['dadata_token'] ?? ''));
        if (empty($settings['dadata_enabled']) || $token === '') {
            return [];
        }

        $country = trim((string)($context['country'] ?? ''));
        $catalog = new RussianRegionCatalog();

        // The existing region directory is Russian; keep the enhanced hierarchy
        // consistent with it. Foreign addresses remain regular manual inputs.
        if (!$catalog->appliesToCountry($country)) {
            return [];
        }

        $region = trim((string)($context['region'] ?? ''));
        $city = trim((string)($context['city'] ?? ''));
        $street = trim((string)($context['street'] ?? ''));

        $parts = array_values(array_filter([
            $region,
            $type !== 'city' ? $city : '',
            $type === 'house' ? $street : '',
            $query,
        ], static fn(string $value): bool => $value !== ''));

        $payload = [
            'query' => implode(', ', $parts),
            'count' => 10,
            'language' => 'ru',
        ];

        if ($type === 'city') {
            $payload['from_bound'] = ['value' => 'city'];
            $payload['to_bound'] = ['value' => 'settlement'];
        } elseif ($type === 'street') {
            $payload['from_bound'] = ['value' => 'street'];
            $payload['to_bound'] = ['value' => 'street'];
        } else {
            $payload['from_bound'] = ['value' => 'house'];
            $payload['to_bound'] = ['value' => 'house'];
        }

        $response = $this->request($token, $payload);
        $suggestions = [];

        foreach ((array)($response['suggestions'] ?? []) as $suggestion) {
            if (!is_array($suggestion)) {
                continue;
            }

            $data = is_array($suggestion['data'] ?? null) ? $suggestion['data'] : [];

            if (!$this->matchesRegion($catalog, $region, $data)) {
                continue;
            }

            if ($type !== 'city' && !$this->matchesCity($city, $data)) {
                continue;
            }

            if ($type === 'house' && !$this->matchesStreet($street, $data)) {
                continue;
            }

            $mapped = $this->mapSuggestion($type, $suggestion, $data);
            if ($mapped !== null) {
                $suggestions[] = $mapped;
            }
        }

        $unique = [];
        foreach ($suggestions as $suggestion) {
            $key = $this->normalize((string)$suggestion['value']);
            if ($key === '' || isset($unique[$key])) {
                continue;
            }
            $unique[$key] = $suggestion;
        }

        return array_values($unique);
    }

    private function mapSuggestion(string $type, array $suggestion, array $data): ?array
    {
        if ($type === 'city') {
            $value = trim((string)($data['city'] ?? ''));
            $label = trim((string)($data['city_with_type'] ?? ''));

            if ($value === '') {
                $value = trim((string)($data['settlement'] ?? ''));
                $label = trim((string)($data['settlement_with_type'] ?? ''));
            }
        } elseif ($type === 'street') {
            $value = trim((string)($data['street'] ?? ''));
            $label = trim((string)($data['street_with_type'] ?? ''));
        } else {
            $house = trim((string)($data['house'] ?? ''));
            if ($house === '') {
                return null;
            }

            $value = $house;
            $label = trim(((string)($data['house_type'] ?? 'д')) . ' ' . $house);

            $block = trim((string)($data['block'] ?? ''));
            if ($block !== '') {
                $blockType = trim((string)($data['block_type'] ?? 'к'));
                $value .= ' ' . $blockType . ' ' . $block;
                $label .= ' ' . $blockType . ' ' . $block;
            }

            $building = trim((string)($data['building'] ?? ''));
            if ($building !== '') {
                $buildingType = trim((string)($data['building_type'] ?? 'стр'));
                $value .= ' ' . $buildingType . ' ' . $building;
                $label .= ' ' . $buildingType . ' ' . $building;
            }
        }

        if ($value === '') {
            return null;
        }

        if ($label === '') {
            $label = $value;
        }

        return [
            'value' => $value,
            'label' => $label,
            'postal_code' => trim((string)($data['postal_code'] ?? '')),
            'fias_id' => trim((string)($data['fias_id'] ?? '')),
            'unrestricted_value' => trim((string)($suggestion['unrestricted_value'] ?? '')),
        ];
    }

    private function matchesRegion(RussianRegionCatalog $catalog, string $region, array $data): bool
    {
        if ($region === '') {
            return true;
        }

        $expected = $catalog->resolve($region) ?? $region;
        $actualRaw = trim((string)($data['region_with_type'] ?? $data['region'] ?? ''));
        $actual = $catalog->resolve($actualRaw) ?? $actualRaw;

        return $this->normalize($expected) === $this->normalize($actual);
    }

    private function matchesCity(string $city, array $data): bool
    {
        if ($city === '') {
            return true;
        }

        $actual = trim((string)($data['city'] ?? ''));
        if ($actual === '') {
            $actual = trim((string)($data['settlement'] ?? ''));
        }

        return $this->normalize($city) === $this->normalize($actual);
    }

    private function matchesStreet(string $street, array $data): bool
    {
        if ($street === '') {
            return true;
        }

        return $this->normalize($street) === $this->normalize((string)($data['street'] ?? ''));
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace('ё', 'е', $value);
        $value = preg_replace(
            '/\b(?:г|город|ул|улица|пр-кт|проспект|пер|переулок|п|пос|поселок|с|село|ст-ца|станица|д|дом)\b/u',
            ' ',
            $value
        ) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function request(string $token, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body)) {
            throw new \RuntimeException('Could not encode DaData request.');
        }

        if (function_exists('curl_init')) {
            $curl = curl_init(self::ENDPOINT);
            if ($curl === false) {
                throw new \RuntimeException('Could not initialize HTTP client.');
            }

            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 6,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json; charset=utf-8',
                    'Authorization: Token ' . $token,
                ],
                CURLOPT_POSTFIELDS => $body,
            ]);

            $raw = curl_exec($curl);
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if (!is_string($raw) || $raw === '' || $status < 200 || $status >= 300) {
                throw new \RuntimeException(
                    'DaData request failed' . ($error !== '' ? ': ' . $error : ' (HTTP ' . $status . ')')
                );
            }

            return $this->decode($raw);
        }

        if (!filter_var((string)ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            throw new \RuntimeException('No HTTP client is available for DaData.');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => 6,
                'ignore_errors' => true,
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'Content-Type: application/json; charset=utf-8',
                    'Authorization: Token ' . $token,
                ]),
                'content' => $body,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $raw = @file_get_contents(self::ENDPOINT, false, $context);
        if (!is_string($raw) || $raw === '') {
            throw new \RuntimeException('DaData request failed.');
        }

        return $this->decode($raw);
    }

    private function decode(string $raw): array
    {
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('DaData returned invalid JSON.');
        }

        return $decoded;
    }
}
