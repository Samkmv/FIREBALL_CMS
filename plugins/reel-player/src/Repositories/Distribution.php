<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Repositories;

use Fireball\ReelPlayer\Services\EmbedAuthorization;
use Fireball\ReelPlayer\Services\MusicAccessPolicy;

/** Additive publication policy: never changes existing published or audio rows. */
final class Distribution
{
    public function __construct(private int $owner) {}

    public function state(): array
    {
        $albums = db()->query('SELECT id,title,artist,published FROM reel_albums WHERE owner_id=? ORDER BY id DESC', [$this->owner])->get() ?: [];
        $tracks = db()->query('SELECT id,title,artist,source FROM reel_tracks WHERE owner_id=? ORDER BY id DESC', [$this->owner])->get() ?: [];
        foreach ($albums as &$album) { $album['id'] = (int)$album['id']; $album['published'] = (bool)$album['published']; $album['distribution'] = $this->rule('album', $album['id']); }
        unset($album);
        foreach ($tracks as &$track) {
            $track['id'] = (int)$track['id']; $track['distribution'] = $this->rule('track', $track['id']);
            $track['embed_settings'] = EmbedAuthorization::settings($track['id']);
            $track['embed_codes'] = EmbedAuthorization::tickets($track['id']);
            $track['albums'] = db()->query('SELECT a.id,a.title,a.published,COALESCE(d.access_mode,?) AS access_mode FROM reel_album_tracks at JOIN reel_albums a ON a.id=at.album_id LEFT JOIN reel_album_distribution d ON d.album_id=a.id WHERE at.track_id=? AND a.owner_id=?', ['internal',$track['id'],$this->owner])->get() ?: [];
        }
        unset($track);
        return ['albums' => $albums, 'tracks' => $tracks];
    }

    public function rule(string $type, int $id): array
    {
        self::type($type);
        $row = db()->query("SELECT * FROM reel_{$type}_distribution WHERE {$type}_id=?", [$id])->getOne();
        $rule = $row ?: ['access_mode' => 'internal', 'show_metadata' => false];
        if ($type === 'track') $rule += ['rights_confirmed' => false, 'license_type' => '', 'license_url' => '', 'allow_stream' => false, 'allow_embed' => false, 'allow_download' => false, 'purchase_url' => ''];
        foreach (['show_metadata', 'rights_confirmed', 'allow_stream', 'allow_embed', 'allow_download'] as $flag) if (array_key_exists($flag, $rule)) $rule[$flag] = (bool)$rule[$flag];
        $rule['plan_ids'] = array_map('intval', array_column(db()->query("SELECT plan_id FROM reel_{$type}_distribution_plans WHERE {$type}_id=? ORDER BY plan_id", [$id])->get() ?: [], 'plan_id'));
        unset($rule[$type . '_id']);
        return $rule;
    }

    private static function type(string $type): void
    {
        if (!in_array($type, ['album', 'track'], true)) throw new \InvalidArgumentException('Некорректный тип музыки.');
    }

    private function owned(string $type, int $id, bool $lock = false): array
    {
        self::type($type);
        $row = db()->query("SELECT id FROM reel_{$type}s WHERE id=? AND owner_id=?" . ($lock ? ' FOR UPDATE' : ''), [$id, $this->owner])->getOne();
        if (!$row) throw new \InvalidArgumentException('Эта запись недоступна для изменения.');
        return $row;
    }

    private static function flag(mixed $value): int
    {
        if (!in_array($value, [true, false, 1, 0, '1', '0', ''], true)) throw new \InvalidArgumentException('Некорректное разрешение.');
        return in_array($value, [true, 1, '1'], true) ? 1 : 0;
    }

    private static function url(mixed $value): string
    {
        if (!is_string($value)) throw new \InvalidArgumentException('Некорректная ссылка.');
        $value = trim($value);
        if ($value === '') return '';
        $parts = parse_url($value);
        if (strlen($value) > 2048 || preg_match('/[\x00-\x20\x7f]/', $value) || !filter_var($value, FILTER_VALIDATE_URL) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) throw new \InvalidArgumentException('Нужна безопасная HTTP или HTTPS ссылка.');
        return $value;
    }

    public function save(string $type, int $id, array $data): void
    {
        self::type($type); $this->owned($type, $id);
        $mode = $data['access_mode'] ?? 'internal';
        if (!is_string($mode) || !in_array($mode, MusicAccessPolicy::MODES, true)) throw new \InvalidArgumentException('Некорректный режим публикации.');
        $values = ['access_mode' => $mode, 'show_metadata' => self::flag($data['show_metadata'] ?? false)];
        $plans = $data['plan_ids'] ?? [];
        if (is_string($plans)) $plans = preg_split('/[\s,]+/', $plans, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($plans) || count($plans) > 100) throw new \InvalidArgumentException('Некорректный список тарифов.');
        $planIds = [];
        foreach ($plans as $plan) {
            if ((!is_int($plan) && !is_string($plan)) || !preg_match('/^[1-9]\d{0,9}$/D', (string)$plan)) throw new \InvalidArgumentException('Некорректный тариф.');
            $planIds[(int)$plan] = true;
        }
        $plans = array_keys($planIds);
        if ($mode !== 'subscription') $plans = [];
        if ($plans) {
            if (!class_exists('FireballPluginSubscriptions')) throw new \InvalidArgumentException('Подключите плагин подписок для выбора тарифов.');
            $valid = db()->query('SELECT id FROM subscription_plans WHERE is_active=1 AND id IN (' . implode(',', array_fill(0, count($plans), '?')) . ')', $plans)->get() ?: [];
            if (count($valid) !== count($plans)) throw new \InvalidArgumentException('Один из тарифов недоступен.');
        }
        if ($type === 'track') {
            foreach (['rights_confirmed', 'allow_stream', 'allow_embed', 'allow_download'] as $flag) $values[$flag] = self::flag($data[$flag] ?? false);
            $license = $data['license_type'] ?? '';
            if (!is_string($license) || ($license !== '' && !in_array($license, MusicAccessPolicy::LICENSES, true))) throw new \InvalidArgumentException('Некорректный тип лицензии.');
            $values += ['license_type' => $license, 'license_url' => self::url($data['license_url'] ?? ''), 'purchase_url' => self::url($data['purchase_url'] ?? '')];
            if (($values['allow_stream'] || $values['allow_embed'] || $values['allow_download']) && (!$values['rights_confirmed'] || $license === '')) throw new \InvalidArgumentException('Подтвердите права и укажите тип лицензии перед разрешением распространения.');
            if ($values['allow_embed'] && $mode !== 'public') throw new \InvalidArgumentException('Встраивать можно только открытую музыку.');
        }
        $now = date('Y-m-d H:i:s');
        db()->beginTransaction();
        try {
            $this->owned($type, $id, true);
            $columns = array_keys($values);
            db()->query("INSERT INTO reel_{$type}_distribution ({$type}_id," . implode(',', $columns) . ',created_at,updated_at) VALUES (' . implode(',', array_fill(0, count($values) + 3, '?')) . ') ON DUPLICATE KEY UPDATE ' . implode(',', array_map(static fn(string $column): string => $column . '=VALUES(' . $column . ')', $columns)) . ',updated_at=VALUES(updated_at)', [$id, ...array_values($values), $now, $now]);
            db()->query("DELETE FROM reel_{$type}_distribution_plans WHERE {$type}_id=?", [$id]);
            foreach ($plans as $plan) db()->query("INSERT INTO reel_{$type}_distribution_plans({$type}_id,plan_id) VALUES(?,?)", [$id, $plan]);
            db()->commit();
        } catch (\Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    }

    public function saveEmbed(int $id, array $data): void
    {
        $this->owned('track', $id);
        $theme = $data['theme'] ?? 'system';
        if (!in_array($theme, ['light', 'dark', 'system'], true)) throw new \InvalidArgumentException('Некорректная тема виджета.');
        $origins = EmbedAuthorization::origins($data['origins'] ?? []);
        EmbedAuthorization::ensureSecret();
        $now = date('Y-m-d H:i:s');
        db()->beginTransaction();
        try {
            $this->owned('track', $id, true);
            db()->query('INSERT INTO reel_embed_settings(track_id,theme,revision,created_at,updated_at) VALUES(?,?,1,?,?) ON DUPLICATE KEY UPDATE theme=VALUES(theme),revision=revision+1,updated_at=VALUES(updated_at)', [$id, $theme, $now, $now]);
            db()->query('DELETE FROM reel_embed_domains WHERE track_id=?', [$id]);
            foreach ($origins as $origin) db()->query('INSERT INTO reel_embed_domains(track_id,origin) VALUES(?,?)', [$id, $origin]);
            db()->commit();
        } catch (\Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
    }
}
