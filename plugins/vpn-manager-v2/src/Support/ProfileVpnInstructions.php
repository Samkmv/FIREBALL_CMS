<?php

namespace Fireball\VpnManagerV2\Support;

final class ProfileVpnInstructions
{
    private const PLATFORMS = [
        'ios' => ['ci-smartphone', 'vpn_manager_v2_profile_platform_ios'],
        'android' => ['ci-smartphone-2', 'vpn_manager_v2_profile_platform_android'],
        'windows' => ['ci-monitor', 'vpn_manager_v2_profile_platform_windows'],
        'macos' => ['ci-apple', 'vpn_manager_v2_profile_platform_macos'],
    ];

    public static function all(?string $selected = null): array
    {
        $selected = array_key_exists((string)$selected, self::PLATFORMS) ? (string)$selected : 'ios';
        $items = [];
        foreach (self::PLATFORMS as $key => [$icon, $labelKey]) {
            $items[] = [
                'key' => $key,
                'icon' => $icon,
                'label' => \FireballPluginVpnManagerV2::t($labelKey),
                'selected' => $key === $selected,
                'steps' => array_map(
                    static fn(int $step): string => \FireballPluginVpnManagerV2::t(
                        'vpn_manager_v2_profile_instruction_' . $key . '_' . $step
                    ),
                    [1, 2, 3, 4]
                ),
            ];
        }

        return $items;
    }

    public static function supports(string $platform): bool
    {
        return array_key_exists($platform, self::PLATFORMS);
    }

    private function __construct()
    {
    }
}
