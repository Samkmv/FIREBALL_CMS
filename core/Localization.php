<?php

namespace FBL;

use App\Models\SiteSetting;

final class Localization
{
    public const PUBLIC_SESSION_KEY = 'locale.public';
    public const ADMIN_SESSION_KEY = 'locale.admin';
    public const PUBLIC_COOKIE = 'fireball_locale';
    public const ADMIN_COOKIE = 'fireball_admin_locale';

    protected static bool $userLocaleSchemaReady = false;

    public static function resolve(?string $routeLocale = null, string $path = ''): array
    {
        $path = self::pathWithoutLocale($path);
        $isAdmin = self::isAdminPath($path);
        $queryLocale = request()->isGet()
            ? self::normalizeLocale((string)(request()->get('locale', '') ?: request()->get('_locale', '')))
            : '';
        $urlLocale = self::normalizeLocale((string)($routeLocale ?: $queryLocale));
        $siteLocale = self::siteLocale();
        $fallbackLocale = self::fallbackLocale();
        $userLocale = self::userLocale($isAdmin);
        $cookieLocale = self::cookieLocale($isAdmin);
        $sessionLocale = self::sessionLocale($isAdmin);

        $ordered = [
            'url' => $urlLocale,
            'user' => $userLocale,
            'cookie' => $cookieLocale,
            'session' => $sessionLocale,
            'site' => $siteLocale,
            'fallback' => $fallbackLocale,
        ];

        $source = 'fallback';
        $current = $fallbackLocale;
        foreach ($ordered as $candidateSource => $candidate) {
            $candidate = self::normalizeLocale($candidate);
            if ($candidate === '') {
                continue;
            }

            $source = $candidateSource;
            $current = $candidate;
            break;
        }

        if ($urlLocale !== '') {
            self::persistLocaleChoice($urlLocale, $isAdmin);
        }

        $context = [
            'current_locale' => $current,
            'site_locale' => $siteLocale,
            'fallback_locale' => $fallbackLocale,
            'session_locale' => $sessionLocale,
            'cookie_locale' => $cookieLocale,
            'user_locale' => $userLocale,
            'source' => $source,
            'scope' => $isAdmin ? 'admin' : 'public',
        ];

        app()->set('locale_context', $context);
        self::debug('resolve', $context);

        return $context;
    }

    public static function normalizeLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));
        $locale = str_replace('_', '-', $locale);

        return $locale !== '' && array_key_exists($locale, LANGS) ? $locale : '';
    }

    public static function currentLocale(): string
    {
        $lang = app()->get('lang');
        $locale = is_array($lang) ? self::normalizeLocale((string)($lang['code'] ?? '')) : '';

        return $locale !== '' ? $locale : self::siteLocale();
    }

    public static function siteLocale(): string
    {
        $configured = '';

        try {
            if (function_exists('app') && app()->db !== null) {
                $configured = (new SiteSetting())->get('default_locale', '');
            }
        } catch (\Throwable) {
            $configured = '';
        }

        $configured = self::normalizeLocale($configured);
        if ($configured !== '') {
            return $configured;
        }

        $default = self::normalizeLocale((string)DEFAULT_LOCALE);
        if ($default !== '') {
            return $default;
        }

        $base = self::baseConfiguredLocale();

        return $base !== '' ? $base : (array_key_first(LANGS) ?: 'ru');
    }

    public static function fallbackLocale(): string
    {
        $default = self::normalizeLocale((string)DEFAULT_LOCALE);
        if ($default !== '') {
            return $default;
        }

        $base = self::baseConfiguredLocale();

        return $base !== '' ? $base : (array_key_first(LANGS) ?: 'ru');
    }

    public static function localeCandidates(?string $locale = null, array $extraFallbacks = []): array
    {
        $candidates = [
            $locale ? self::normalizeLocale($locale) : self::currentLocale(),
            self::siteLocale(),
            ...array_map(static fn($item): string => self::normalizeLocale((string)$item), $extraFallbacks),
            self::fallbackLocale(),
        ];

        return array_values(array_unique(array_filter($candidates)));
    }

    public static function localizedSql(string $baseColumn, string $originalColumn, string $alias = '', array $availableColumns = []): string
    {
        $prefix = $alias !== '' ? "{$alias}." : '';
        $available = array_fill_keys($availableColumns, true);
        $expressions = [];

        foreach (self::localeCandidates() as $locale) {
            $column = $baseColumn . '_' . self::columnSuffix($locale);
            if ($availableColumns !== [] && !isset($available[$column])) {
                continue;
            }

            $expressions[] = "NULLIF({$prefix}{$column}, '')";
        }

        $expressions[] = "NULLIF({$prefix}{$originalColumn}, '')";
        $expressions[] = "{$prefix}{$originalColumn}";

        return 'COALESCE(' . implode(', ', array_values(array_unique($expressions))) . ')';
    }

    public static function localizedValue(array $row, string $baseKey, string $originalKey): string
    {
        foreach (self::localeCandidates() as $locale) {
            $key = $baseKey . '_' . self::columnSuffix($locale);
            $value = trim((string)($row[$key] ?? ''));
            if ($value !== '') {
                self::debug('localized_value', [
                    'key' => $key,
                    'locale' => $locale,
                    'source' => 'localized_field',
                ]);
                return $value;
            }
        }

        return trim((string)($row[$originalKey] ?? ''));
    }

    public static function localeCacheKey(string $prefix, string $name, ?string $locale = null): string
    {
        $locale = self::normalizeLocale((string)$locale) ?: self::currentLocale();
        $key = $prefix . ':' . $locale . ':' . ltrim($name, ':');
        self::debug('cache_key', ['key' => $key, 'locale' => $locale]);

        return $key;
    }

    public static function persistLocaleChoice(string $locale, bool $adminScope): void
    {
        $locale = self::normalizeLocale($locale);
        if ($locale === '') {
            return;
        }

        $sessionKey = $adminScope ? self::ADMIN_SESSION_KEY : self::PUBLIC_SESSION_KEY;
        $cookieName = $adminScope ? self::ADMIN_COOKIE : self::PUBLIC_COOKIE;

        session()->set($sessionKey, $locale);
        if (!headers_sent()) {
            setcookie($cookieName, $locale, [
                'expires' => time() + 31536000,
                'path' => '/',
                'secure' => request_is_secure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            $_COOKIE[$cookieName] = $locale;
        }

        self::persistUserLocale($locale, $adminScope);
    }

    public static function isAdminPath(string $path): bool
    {
        $path = self::pathWithoutLocale($path);
        $firstSegment = strtok(trim($path, '/'), '/');

        return $firstSegment === 'admin';
    }

    public static function pathWithoutLocale(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            try {
                $path = request()->getPath();
            } catch (\Throwable) {
                $path = '';
            }
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn(string $segment): bool => $segment !== ''));
        if (isset($segments[0]) && array_key_exists((string)$segments[0], LANGS)) {
            array_shift($segments);
        }

        return implode('/', $segments);
    }

    public static function debug(string $event, array $context = []): void
    {
        if (!defined('DEBUG') || !DEBUG) {
            return;
        }

        $context += app()->get('locale_context', []);
        $line = '[localization] ' . $event . ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        error_log($line . PHP_EOL, 3, ERROR_LOGS);
    }

    protected static function userLocale(bool $adminScope): string
    {
        try {
            if (!function_exists('check_auth') || !check_auth()) {
                return '';
            }

            $user = get_user();
            $key = $adminScope ? 'admin_locale' : 'locale';

            return self::normalizeLocale((string)($user[$key] ?? ''));
        } catch (\Throwable) {
            return '';
        }
    }

    protected static function cookieLocale(bool $adminScope): string
    {
        $cookieName = $adminScope ? self::ADMIN_COOKIE : self::PUBLIC_COOKIE;

        return self::normalizeLocale((string)($_COOKIE[$cookieName] ?? ''));
    }

    protected static function sessionLocale(bool $adminScope): string
    {
        $sessionKey = $adminScope ? self::ADMIN_SESSION_KEY : self::PUBLIC_SESSION_KEY;

        return self::normalizeLocale((string)session()->get($sessionKey, ''));
    }

    protected static function persistUserLocale(string $locale, bool $adminScope): void
    {
        try {
            if (!function_exists('check_auth') || !check_auth() || app()->db === null) {
                return;
            }

            $user = get_user();
            $userId = (int)($user['id'] ?? 0);
            if ($userId <= 0) {
                return;
            }

            self::ensureUserLocaleColumns();
            $column = $adminScope ? 'admin_locale' : 'locale';
            db()->query("UPDATE users SET {$column} = ? WHERE id = ?", [$locale, $userId]);
            $user[$column] = $locale;
            session()->set('user', $user);
        } catch (\Throwable $exception) {
            self::debug('persist_user_locale_failed', [
                'error' => $exception->getMessage(),
                'locale' => $locale,
                'scope' => $adminScope ? 'admin' : 'public',
            ]);
        }
    }

    protected static function ensureUserLocaleColumns(): void
    {
        if (self::$userLocaleSchemaReady) {
            return;
        }

        $localeExists = (bool)db()->query("SHOW COLUMNS FROM users LIKE 'locale'")->getColumn();
        if (!$localeExists) {
            db()->query("ALTER TABLE users ADD COLUMN locale VARCHAR(12) NULL AFTER role");
        }

        $adminLocaleExists = (bool)db()->query("SHOW COLUMNS FROM users LIKE 'admin_locale'")->getColumn();
        if (!$adminLocaleExists) {
            db()->query("ALTER TABLE users ADD COLUMN admin_locale VARCHAR(12) NULL AFTER locale");
        }

        self::$userLocaleSchemaReady = true;
    }

    protected static function columnSuffix(string $locale): string
    {
        return preg_replace('/[^a-z0-9]+/', '_', strtolower($locale)) ?: $locale;
    }

    protected static function baseConfiguredLocale(): string
    {
        foreach (LANGS as $code => $language) {
            if ((int)($language['base'] ?? 0) === 1) {
                return self::normalizeLocale((string)$code);
            }
        }

        return '';
    }
}
