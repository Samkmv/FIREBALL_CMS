<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

/** CSP is the browser's ancestor boundary. Never authorize using Referer. */
final class EmbedAuthorization
{
    public static function origins(mixed $input): array
    {
        if (is_string($input)) $input = preg_split('/[\r\n,]+/', $input, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($input) || count($input) > 32) throw new \InvalidArgumentException('Укажите до 32 разрешённых адресов сайтов.');
        $origins = [];
        foreach ($input as $value) {
            if (!is_string($value)) throw new \InvalidArgumentException('Некорректный адрес сайта.');
            $value = rtrim(strtolower(trim($value)), '/');
            $parts = parse_url($value);
            if (!$parts || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !empty($parts['path'])
                || !preg_match('/^(?:[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?|\[::1\])$/D', (string)($parts['host'] ?? ''))
                || !in_array($parts['scheme'] ?? '', ['https', 'http'], true)
                || (($parts['scheme'] ?? '') === 'http' && !in_array($parts['host'], ['localhost', '127.0.0.1', '[::1]'], true))
                || (isset($parts['port']) && ($parts['port'] < 1 || $parts['port'] > 65535)) || strlen($value) > 255) {
                throw new \InvalidArgumentException('Нужен HTTPS-адрес сайта без пути; HTTP разрешён только для localhost.');
            }
            $origins[$value] = true;
        }
        return array_keys($origins);
    }

    public static function ensureSecret(): void
    {
        if (!self::secret()) plugin_setting_set('reel-player', 'embed_signing_key', bin2hex(random_bytes(32)));
    }

    private static function secret(): string
    {
        $value = plugin_setting('reel-player', 'embed_signing_key', '');
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/D', $value) ? (string)hex2bin($value) : '';
    }

    public static function settings(int $id): array
    {
        $row = db()->query('SELECT theme,revision FROM reel_embed_settings WHERE track_id=?', [$id])->getOne();
        $origins = $row ? array_column(db()->query('SELECT origin FROM reel_embed_domains WHERE track_id=? ORDER BY origin', [$id])->get() ?: [], 'origin') : [];
        return ['theme' => $row['theme'] ?? 'system', 'revision' => (int)($row['revision'] ?? 0), 'origins' => $origins];
    }

    private static function sign(array $claims): string
    {
        $secret = self::secret();
        if ($secret === '') throw new \InvalidArgumentException('Сначала сохраните настройки встраивания.');
        $payload = rtrim(strtr(base64_encode(json_encode($claims, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        return $payload . '.' . hash_hmac('sha256', $payload, $secret);
    }

    private static function verify(string $token, int $id, array $settings, string $kind): array
    {
        if (strlen($token) > 2048 || !preg_match('/^([a-zA-Z0-9_-]+)\.([a-f0-9]{64})$/D', $token, $m) || self::secret() === '') throw new \InvalidArgumentException('Встраивание недоступно.');
        if (!hash_equals(hash_hmac('sha256', $m[1], self::secret()), $m[2])) throw new \InvalidArgumentException('Встраивание недоступно.');
        $decoded = base64_decode(strtr($m[1], '-_', '+/'), true);
        $claims = $decoded === false ? null : json_decode($decoded, true);
        if (!is_array($claims) || ($claims['kind'] ?? '') !== $kind || ($claims['id'] ?? 0) !== $id || ($claims['revision'] ?? -1) !== $settings['revision']
            || !is_string($claims['origin'] ?? null) || !in_array($claims['origin'], $settings['origins'], true)
            || ($kind === 'media' && (!is_int($claims['expires'] ?? null) || $claims['expires'] <= time()))) throw new \InvalidArgumentException('Встраивание недоступно.');
        return $claims;
    }

    /** Generated only by the authenticated owner's distribution editor. */
    public static function tickets(int $id): array
    {
        $settings = self::settings($id); $items = [];
        if (!self::secret()) return $items;
        foreach ($settings['origins'] as $origin) $items[] = ['origin' => $origin, 'url' => base_href('/music/embed/track/' . $id) . '?ticket=' . rawurlencode(self::sign(['kind' => 'ticket', 'id' => $id, 'origin' => $origin, 'revision' => $settings['revision']]))];
        return $items;
    }

    public static function frame(int $id, string $ticket, float $duration = 0): array
    {
        $settings = self::settings($id);
        $origin = '';
        if ($settings['origins']) $origin = self::verify($ticket, $id, $settings, 'ticket')['origin'];
        // A multi-hour recording must not outlive its media token. Rights and
        // the settings revision are still checked on every new Range request.
        $lifetime = min(86400, max(7200, (int)ceil(is_finite($duration) && $duration > 0 ? $duration : 0) + 1800));
        $settings['mediaToken'] = $origin === '' ? '' : self::sign(['kind' => 'media', 'id' => $id, 'origin' => $origin, 'revision' => $settings['revision'], 'expires' => time() + $lifetime]);
        $settings['ancestors'] = $origin ?: '*';
        return $settings;
    }

    public static function media(int $id, string $token): void
    {
        $settings = self::settings($id);
        if ($settings['origins']) self::verify($token, $id, $settings, 'media');
    }
}
