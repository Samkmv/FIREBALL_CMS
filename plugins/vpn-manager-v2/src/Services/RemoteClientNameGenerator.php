<?php

namespace Fireball\VpnManagerV2\Services;

use Fireball\VpnManagerV2\Exceptions\ValidationException;

final class RemoteClientNameGenerator
{
    private const CYRILLIC = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e',
        'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i', 'й' => 'i', 'к' => 'k',
        'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
        'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts',
        'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];

    public function generate(string $name, string $login, string $countryCode): string
    {
        $name = $this->normalize($name);
        $login = $this->normalize($login);
        $countryCode = strtoupper(trim($countryCode));

        if ($name === '' || $login === '') {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_identity_required'));
        }
        if (preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
            throw new ValidationException(\FireballPluginVpnManagerV2::t('vpn_manager_v2_error_server_country_required'));
        }

        return mb_substr($name . '-' . $login . '-' . $countryCode, 0, 190);
    }

    public function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = strtr($value, self::CYRILLIC);
        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
            if ($transliterator !== null) {
                $candidate = $transliterator->transliterate($value);
                if (is_string($candidate)) {
                    $value = $candidate;
                }
            }
        }
        $value = preg_replace('/[\s\p{Z}]+/u', '-', $value) ?? '';
        $value = preg_replace('/[^a-z0-9_-]+/i', '-', $value) ?? '';
        $value = preg_replace('/-+/', '-', $value) ?? '';

        return trim($value, '-_');
    }
}
