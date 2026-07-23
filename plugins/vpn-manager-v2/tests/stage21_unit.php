<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/config/config.php';
require ROOT . '/vendor/autoload.php';

use App\Services\PluginUpdateService;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$root = dirname(__DIR__, 3);
$expectedLocales = array_keys(LANGS);
$manifests = [];
foreach (glob($root . '/plugins/*/plugin.json') ?: [] as $file) {
    $manifest = json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (!empty($manifest['update']['enabled'])) {
        $manifests[(string)$manifest['slug']] = $manifest;
    }
}

$assert(isset($manifests['vpn-manager-v2'], $manifests['toy-car-rental']),
    'Every update-enabled plugin was not discovered.');
foreach ($manifests as $slug => $manifest) {
    $fallback = $manifest['release_notes'] ?? null;
    $notes = $manifest['release_notes_i18n'] ?? null;
    $assert(is_array($fallback) && array_is_list($fallback) && $fallback !== [] && count($fallback) <= 10,
        'Legacy release note fallback is invalid for ' . $slug . '.');
    $assert(is_array($notes) && array_keys($notes) === $expectedLocales,
        'Localized release note locales differ for ' . $slug . '.');
    foreach ($notes as $locale => $items) {
        $assert(is_array($items) && $items !== [] && count($items) <= 10,
            'Release note count is invalid for ' . $slug . ':' . $locale . '.');
        foreach ($items as $item) {
            $assert(is_string($item) && trim($item) !== '' && mb_strlen($item) <= 240,
                'A release note is invalid for ' . $slug . ':' . $locale . '.');
        }
    }
}

$reflection = new ReflectionClass(PluginUpdateService::class);
$service = $reflection->newInstanceWithoutConstructor();
$normalize = $reflection->getMethod('normalizeReleaseNotes');
$normalize->setAccessible(true);
$vpnNotes = $manifests['vpn-manager-v2']['release_notes_i18n'];
$assert($normalize->invoke($service, $vpnNotes, 'ru') === $vpnNotes['ru'],
    'Russian release notes are not selected.');
$assert($normalize->invoke($service, $vpnNotes, 'de') === $vpnNotes['de'],
    'German release notes are not selected.');
$assert($normalize->invoke($service, $vpnNotes, 'zh-cn') === $vpnNotes['zh-cn'],
    'Chinese release notes are not selected.');
$assert($normalize->invoke($service, ['en' => ['English fallback']], 'zh-cn') === ['English fallback'],
    'English fallback is not selected when the current translation is absent.');
$assert($normalize->invoke($service, "First\nSecond", 'ru') === ['First', 'Second'],
    'Legacy newline release notes are no longer supported.');
$assert($normalize->invoke($service, ['First', 'Second'], 'ru') === ['First', 'Second'],
    'Legacy array release notes are no longer supported.');

$source = (string)file_get_contents($root . '/app/Services/PluginUpdateService.php');
$documentation = (string)file_get_contents($root . '/docs/ru/plugins/introduction.md');
$assert(substr_count($source, 'release_notes_i18n') >= 4
    && str_contains($source, 'normalizeReleaseNoteTranslations'),
    'The update state does not preserve localized release notes.');
$assert(str_contains($documentation, '"zh-cn"')
    && str_contains($documentation, 'release_notes_i18n')
    && str_contains($documentation, 'совместимым со старыми версиями CMS'),
    'Localized release note documentation is incomplete.');

echo json_encode([
    'status' => 'ok',
    'cases' => [
        'all_update_enabled_plugins_localized',
        'four_locale_parity',
        'current_locale_selection',
        'english_fallback',
        'legacy_string_and_array_support',
        'localized_state_persistence',
        'documentation',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
