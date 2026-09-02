<?php

namespace Fireball\Subscriptions\Support;

final class AddressNormalizer
{
    private const STREET_TYPES = [
        'улица' => 'street',
        'ул' => 'street',
        'проспект' => 'avenue',
        'пр-т' => 'avenue',
        'пр' => 'avenue',
        'переулок' => 'lane',
        'пер' => 'lane',
        'бульвар' => 'boulevard',
        'бул' => 'boulevard',
        'б-р' => 'boulevard',
    ];

    private const STREET_TYPE_LABELS = [
        'street' => 'улица',
        'avenue' => 'проспект',
        'lane' => 'переулок',
        'boulevard' => 'бульвар',
    ];

    public function normalizeRule(string $address): array
    {
        $value = $this->base($address);
        if ($value === '') {
            return $this->emptyAddress();
        }

        $apartment = null;
        if (preg_match('/(?<![\p{L}\p{N}])(?:квартира|кв)\s*([0-9]+[a-zа-я]?)(?![\p{L}\p{N}])/u', $value, $match) === 1) {
            $apartment = $this->normalizeApartment((string)$match[1]) ?: null;
            $value = trim((string)preg_replace('/(?<![\p{L}\p{N}])(?:квартира|кв)\s*[0-9]+[a-zа-я]?(?![\p{L}\p{N}])/u', ' ', $value, 1));
        }

        $house = null;
        $houseValuePattern = '[0-9]+(?:\s*[a-zа-я](?!\s*[0-9]))?(?:\s*\/\s*[0-9a-zа-я]+)?(?:\s*(?:корпус|корп|к)\s*[0-9a-zа-я]+)?';
        if (preg_match('/(?<![\p{L}\p{N}])(?:дом|д)\s*(' . $houseValuePattern . ')(?![\p{L}\p{N}])/u', $value, $match) === 1) {
            $house = $this->normalizeHouse((string)$match[1]) ?: null;
            $value = trim((string)preg_replace('/(?<![\p{L}\p{N}])(?:дом|д)\s*' . $houseValuePattern . '(?![\p{L}\p{N}])/u', ' ', $value, 1));
        } elseif (preg_match('/^(.+?)\s+(' . $houseValuePattern . ')$/u', $value, $match) === 1) {
            $candidateStreet = trim((string)$match[1]);
            if ($candidateStreet !== '' && preg_match('/\p{L}/u', $candidateStreet) === 1) {
                $value = $candidateStreet;
                $house = $this->normalizeHouse((string)$match[2]) ?: null;
            }
        }

        $streetType = null;
        $typePattern = '(улица|ул|проспект|пр-т|пр|переулок|пер|бульвар|бул|б-р)';
        if (preg_match('/(?<!\p{L})' . $typePattern . '(?!\p{L})/u', $value, $match) === 1) {
            $streetType = self::STREET_TYPES[(string)$match[1]] ?? null;
            $value = (string)preg_replace('/(?<!\p{L})' . $typePattern . '(?!\p{L})/u', ' ', $value);
        }

        $street = $this->normalizeStreetName($value);
        $normalized = $this->format($streetType, $street, $house, $apartment);

        return [
            'normalized_address' => $normalized,
            'rule_type' => $apartment !== null ? 'apartment' : ($house !== null ? 'address' : 'street'),
            'street_type' => $streetType,
            'normalized_street' => $street,
            'normalized_house' => $house,
            'normalized_apartment' => $apartment,
        ];
    }

    public function normalizeProfile(array $profile): array
    {
        $streetParts = $this->normalizeRule((string)($profile['street'] ?? ''));
        $house = $this->normalizeHouse((string)($profile['house'] ?? ''));
        $apartment = $this->normalizeApartment((string)($profile['apartment'] ?? ''));
        if ($house === '') {
            $house = (string)($streetParts['normalized_house'] ?? '');
        }
        if ($apartment === '') {
            $apartment = (string)($streetParts['normalized_apartment'] ?? '');
        }

        return [
            'normalized_address' => $this->format(
                $streetParts['street_type'] ?? null,
                (string)($streetParts['normalized_street'] ?? ''),
                $house !== '' ? $house : null,
                $apartment !== '' ? $apartment : null
            ),
            'street_type' => $streetParts['street_type'] ?? null,
            'normalized_street' => (string)($streetParts['normalized_street'] ?? ''),
            'normalized_house' => $house !== '' ? $house : null,
            'normalized_apartment' => $apartment !== '' ? $apartment : null,
        ];
    }

    public function normalizeHouse(string $house): string
    {
        $value = $this->base($house);
        $value = (string)preg_replace('/(?<!\p{L})(?:дом|д)(?!\p{L})/u', ' ', $value);
        $value = (string)preg_replace('/(?<!\p{L})(?:корпус|корп|к)(?!\p{L})/u', 'к', $value);
        $value = (string)preg_replace('/\s*\/\s*/u', '/', $value);
        $value = (string)preg_replace('/\s*-\s*/u', '-', $value);
        $value = (string)preg_replace('/\s+/u', '', $value);

        return mb_substr($value, 0, 50);
    }

    public function normalizeApartment(string $apartment): string
    {
        $value = $this->base($apartment);
        $value = (string)preg_replace('/(?<!\p{L})(?:квартира|кв)(?!\p{L})/u', ' ', $value);
        $value = (string)preg_replace('/\s+/u', '', $value);

        return mb_substr($value, 0, 50);
    }

    private function normalizeStreetName(string $street): string
    {
        $street = $this->base($street);
        $street = (string)preg_replace('/\s+/u', ' ', $street);

        return mb_substr(trim($street), 0, 255);
    }

    private function base(string $value): string
    {
        $value = mb_strtolower(trim(str_replace(["\xc2\xa0", 'ё', 'Ё'], [' ', 'е', 'е'], $value)), 'UTF-8');
        $value = str_replace(['–', '—', '−'], '-', $value);
        $value = (string)preg_replace('/[.,;:()\[\]{}"«»]+/u', ' ', $value);
        $value = (string)preg_replace('/[^\p{L}\p{N}\/-]+/u', ' ', $value);

        return trim((string)preg_replace('/\s+/u', ' ', $value));
    }

    private function format(?string $streetType, string $street, ?string $house, ?string $apartment): string
    {
        $parts = [];
        if ($streetType !== null && isset(self::STREET_TYPE_LABELS[$streetType])) {
            $parts[] = self::STREET_TYPE_LABELS[$streetType];
        }
        if ($street !== '') {
            $parts[] = $street;
        }
        if ($house !== null && $house !== '') {
            $parts[] = 'дом ' . $house;
        }
        if ($apartment !== null && $apartment !== '') {
            $parts[] = 'квартира ' . $apartment;
        }

        return implode(' ', $parts);
    }

    private function emptyAddress(): array
    {
        return [
            'normalized_address' => '',
            'rule_type' => 'street',
            'street_type' => null,
            'normalized_street' => '',
            'normalized_house' => null,
            'normalized_apartment' => null,
        ];
    }
}
