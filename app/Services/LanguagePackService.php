<?php

namespace App\Services;

use App\Models\SiteSetting;

/**
 * Discovers translation override packs and resolves the pack selected for the site.
 */
final class LanguagePackService
{
    public const DEFAULT_PACK = 'cms';

    private ?array $packs = null;

    /**
     * Returns all available packs. Bundled packs are updated with the CMS, while
     * packs from storage/language-packs are preserved between updates.
     */
    public function all(): array
    {
        if ($this->packs !== null) {
            return $this->packs;
        }

        $this->packs = [
            self::DEFAULT_PACK => [
                'id' => self::DEFAULT_PACK,
                'name' => 'FIREBALL CMS',
                'description' => 'System translations for general-purpose websites.',
                'version' => '',
                'locales' => array_keys(defined('LANGS') && is_array(LANGS) ? LANGS : []),
                'path' => null,
                'source' => 'system',
            ],
        ];

        $directories = [
            ['path' => APP . '/LanguagePacks', 'source' => 'bundled'],
        ];
        if (defined('STORAGE')) {
            $directories[] = ['path' => STORAGE . '/language-packs', 'source' => 'custom'];
        }

        foreach ($directories as $directory) {
            $root = (string)$directory['path'];
            if (!is_dir($root)) {
                continue;
            }

            foreach (glob($root . '/*/pack.php') ?: [] as $manifestPath) {
                $pack = $this->readManifest($manifestPath, (string)$directory['source']);
                if ($pack !== null) {
                    $this->packs[$pack['id']] = $pack;
                }
            }
        }

        return $this->packs;
    }

    public function activeId(): string
    {
        $selected = self::DEFAULT_PACK;

        try {
            if (
                defined('INSTALLED_LOCK')
                && is_file(INSTALLED_LOCK)
                && function_exists('app')
                && app()->db !== null
            ) {
                $selected = (new SiteSetting())->get('language_pack', self::DEFAULT_PACK);
            }
        } catch (\Throwable) {
            $selected = self::DEFAULT_PACK;
        }

        $selected = $this->normalizeId($selected);

        return $selected !== '' && $this->has($selected) ? $selected : self::DEFAULT_PACK;
    }

    public function has(string $id): bool
    {
        return array_key_exists($this->normalizeId($id), $this->all());
    }

    public function normalizeId(string $id): string
    {
        $id = strtolower(trim($id));

        return preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $id) === 1
            ? $id
            : '';
    }

    /**
     * Loads layout and route-specific overrides for the requested locale chain.
     *
     * @return array{layout: array, view: array, files: array}
     */
    public function loadTranslations(
        string $id,
        array $localeCandidates,
        string $folder = '',
        string $file = ''
    ): array {
        $pack = $this->all()[$this->normalizeId($id)] ?? null;
        $root = is_array($pack) ? ($pack['path'] ?? null) : null;
        if (!is_string($root) || $root === '') {
            return ['layout' => [], 'view' => [], 'files' => []];
        }

        $layout = [];
        $view = [];
        $loadedFiles = [];

        foreach (array_reverse($localeCandidates) as $candidate) {
            $candidate = strtolower(trim((string)$candidate));
            if (preg_match('/^[a-z0-9-]+$/', $candidate) !== 1) {
                continue;
            }

            $layoutPath = $root . '/Languages/' . $candidate . '.php';
            $layout = array_merge($layout, $this->loadFile($layoutPath, $loadedFiles));

            if ($folder !== '' && $file !== '') {
                $viewPath = $root . '/Languages/' . $candidate . '/' . basename($folder) . '/' . basename($file) . '.php';
                $view = array_merge($view, $this->loadFile($viewPath, $loadedFiles));
            }
        }

        return ['layout' => $layout, 'view' => $view, 'files' => $loadedFiles];
    }

    private function readManifest(string $manifestPath, string $source): ?array
    {
        try {
            $manifest = require $manifestPath;
        } catch (\Throwable $exception) {
            error_log('Language pack manifest error [' . $manifestPath . ']: ' . $exception->getMessage());
            return null;
        }

        if (!is_array($manifest)) {
            return null;
        }

        $directoryId = basename(dirname($manifestPath));
        $id = $this->normalizeId((string)($manifest['id'] ?? $directoryId));
        if ($id === '' || $id === self::DEFAULT_PACK || $id !== $directoryId) {
            return null;
        }

        $name = trim((string)($manifest['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $locales = array_values(array_unique(array_filter(array_map(
            static fn(mixed $locale): string => strtolower(trim((string)$locale)),
            (array)($manifest['locales'] ?? [])
        ), static fn(string $locale): bool => preg_match('/^[a-z0-9-]+$/', $locale) === 1)));

        return [
            'id' => $id,
            'name' => $name,
            'description' => trim((string)($manifest['description'] ?? '')),
            'version' => trim((string)($manifest['version'] ?? '')),
            'locales' => $locales,
            'path' => dirname($manifestPath),
            'source' => $source,
        ];
    }

    private function loadFile(string $path, array &$loadedFiles): array
    {
        if (!is_file($path)) {
            return [];
        }

        try {
            $data = require $path;
            if (is_array($data)) {
                $loadedFiles[] = $path;
                return $data;
            }
        } catch (\Throwable $exception) {
            error_log('Language pack file error [' . $path . ']: ' . $exception->getMessage());
        }

        return [];
    }
}
