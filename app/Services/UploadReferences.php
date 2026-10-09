<?php

namespace App\Services;

use FBL\Database;

/** Request-scoped public upload references. Never decrypts or reads chat text. */
final class UploadReferences
{
    private ?array $paths = null;
    private bool $failed = false;

    public function __construct(private ?Database $database = null, private ?array $templateRoots = null)
    {
    }

    public function contains(string $relativePath, bool $directory): bool
    {
        $this->load();
        // Conservative case folding also protects aliases on case-insensitive
        // filesystems (e.g. the local macOS installation).
        $relativePath = strtolower(trim($relativePath, '/'));
        foreach ($this->paths as $path => $_) {
            if ($path === $relativePath || ($directory && str_starts_with($path, $relativePath . '/'))) {
                return true;
            }
        }
        return false;
    }

    private function load(): void
    {
        if ($this->failed) {
            throw new \RuntimeException('Upload reference check unavailable.');
        }
        if ($this->paths !== null) {
            return;
        }
        $this->paths = [];
        try {
            $database = $this->database ?? db();
            $columns = $database->query(
                "SELECT c.TABLE_NAME, c.COLUMN_NAME FROM information_schema.COLUMNS c
                 JOIN information_schema.TABLES t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
                 WHERE c.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = 'BASE TABLE'
                   AND c.DATA_TYPE IN ('char', 'varchar', 'text', 'mediumtext', 'longtext', 'json')"
            )->get();
            if (!is_array($columns) || $columns === []) {
                throw new \RuntimeException('Upload reference schema unavailable.');
            }
            $selects = [];
            $params = [];
            foreach ($columns as $column) {
                $table = (string)$column['TABLE_NAME'];
                $name = (string)$column['COLUMN_NAME'];
                if (!$this->canInspect($table, $name)) {
                    continue;
                }
                // Names come from schema metadata, never a submitted path or request.
                $field = '`' . $name . '`';
                $selects[] = "SELECT CAST({$field} AS CHAR) AS upload_reference FROM `{$table}`
                              WHERE {$field} LIKE ? OR {$field} LIKE ?";
                $params[] = '%uploads%';
                $params[] = '%data-fb-editor-state%';
            }
            // One inventory per request, not one database scan per folder/file.
            foreach (array_chunk($selects, 40) as $chunkIndex => $chunk) {
                $rows = $database->query(
                    implode(' UNION ALL ', $chunk) . ' LIMIT 10001',
                    array_slice($params, $chunkIndex * 80, count($chunk) * 2)
                )->get();
                if (!is_array($rows) || count($rows) > 10000) {
                    throw new \RuntimeException('Upload reference inventory incomplete.');
                }
                foreach ($rows as $row) {
                    $this->addValue((string)$row['upload_reference']);
                }
            }
            $roots = $this->templateRoots ?? [ROOT . '/themes', APP . '/Views/themes'];
            foreach ($roots as $root) {
                $this->inspectTemplates($root);
            }
        } catch (\Throwable $error) {
            $this->failed = true;
            $this->paths = null;
            // No paths, message bodies, credentials or file contents go to logs/UI.
            throw new \RuntimeException('Upload reference check unavailable.', 0, $error);
        }
    }

    private function canInspect(string $table, string $column): bool
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)
            || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
            throw new \RuntimeException('Unsupported reference schema identifier.');
        }
        // Historical/cached records are not live content references.
        if (preg_match('/(?:^|_)(?:logs?|events|jobs|operations|snapshots|visits|sessions|deliveries)$/', $table)
            || str_starts_with($table, 'search_index')
            || in_array($table, ['password_resets', 'two_factor_recovery_tokens', 'pwa_subscriptions',
                'pwa_notifications', 'subscription_orders', 'subscription_payments',
                'subscription_webhook_events', 'camera_manager_publications'], true)) {
            return false;
        }
        // Chat is inspected ONLY for public legacy attachment/avatar paths.
        if (str_starts_with($table, 'chat_')) {
            return in_array($table . '.' . $column, [
                'chat_messages.attachment_path', 'chat_attachments.path', 'chat_conversations.avatar_path',
            ], true);
        }
        if ($table === 'notifications') {
            return in_array($column, ['icon', 'action_url'], true);
        }
        return !preg_match('/(?:ciphertext|encrypted|password|secret|token|credential|recovery_codes)/i', $column);
    }

    private function addValue(string $value): void
    {
        // Hidden/disabled editor blocks may exist only in the encoded snapshot.
        if (preg_match_all('~<template\b[^>]*data-fb-editor-state[^>]*>([^<]+)</template>~is', $value, $snapshots)) {
            foreach ($snapshots[1] as $encoded) {
                $decoded = base64_decode(trim($encoded), true);
                if ($decoded === false || !is_array(json_decode($decoded, true))) {
                    throw new \RuntimeException('Unreadable editor snapshot.');
                }
                $this->addPaths($decoded);
            }
        }
        $this->addPaths($value);
    }

    private function addPaths(string $value): void
    {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = str_replace(['\\/', '\\u002f', '\\u002F'], '/', $value);
        $value = rawurldecode($value);
        if (!preg_match_all('~(?<![A-Za-z0-9_-])uploads/([^\s"\'<>?#(){}\[\]\\\\;,]+)~u', $value, $matches)) {
            return;
        }
        foreach ($matches[1] as $path) {
            $segments = [];
            foreach (explode('/', $path) as $segment) {
                if ($segment === '' || $segment === '.') {
                    continue;
                }
                if ($segment === '..') {
                    array_pop($segments);
                } else {
                    $segments[] = $segment;
                }
            }
            $normalized = implode('/', $segments);
            if ($normalized !== '') {
                $this->paths[strtolower($normalized)] = true;
            }
        }
    }

    private function inspectTemplates(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            static function (\SplFileInfo $file): bool {
                return !$file->isLink() && !in_array($file->getFilename(),
                    ['admin', 'vendor', 'node_modules', 'tests', '.git'], true);
            }
        ));
        foreach ($iterator as $file) {
            if (!$file->isFile() || !in_array(strtolower($file->getExtension()), ['php', 'html', 'css', 'js', 'json'], true)) {
                continue;
            }
            $value = file_get_contents($file->getPathname());
            if ($value === false) {
                throw new \RuntimeException('Template reference check unavailable.');
            }
            // Source files can contain the serializer's literal template markup,
            // not an actual saved base64 snapshot.
            $this->addPaths($value);
        }
    }
}
