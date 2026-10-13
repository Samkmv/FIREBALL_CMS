<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Repositories;

/** Appearance belongs to the signed-in listener, never to an audio-file owner. */
final class PlayerPreferences
{
    public const DEFAULTS = ['display_mode'=>'reel','sitewide_player'=>false,'remember_queue'=>true,'restore_position'=>true,'theme'=>'light','personalization'=>false];
    private bool $stored=false;

    public function __construct(private int $owner)
    {
        if ($owner<1) throw new \InvalidArgumentException('Войдите в аккаунт, чтобы сохранить настройки.');
    }

    public static function normalize(array $values, array $base = self::DEFAULTS): array
    {
        $result = self::DEFAULTS;
        foreach (self::DEFAULTS as $key=>$default) {
            $value = array_key_exists($key,$values) ? $values[$key] : ($base[$key] ?? $default);
            if (is_bool($default)) {
                if (!in_array($value,[true,false,0,1,'0','1'],true)) throw new \InvalidArgumentException('Некорректный переключатель настроек.');
                $result[$key] = (bool)$value;
            } else {
                $allowed = $key==='theme' ? ['light','dark','system'] : ['reel','compact','auto'];
                if (!is_string($value) || !in_array($value,$allowed,true)) throw new \InvalidArgumentException('Выберите поддерживаемый внешний вид плеера.');
                $result[$key] = $value;
            }
        }
        return $result;
    }

    public function get(): array
    {
        try { $row = db()->query('SELECT display_mode,sitewide_player,remember_queue,restore_position,theme,personalization FROM reel_player_preferences WHERE owner_id=?',[$this->owner])->getOne(); }
        catch (\PDOException $error) {
            // Installing assets before the additive migration must preserve the old player.
            if ((string)$error->getCode()==='42S02' || str_contains($error->getMessage(),'no such table: reel_player_preferences')) { $this->stored=false; return self::DEFAULTS; }
            throw $error;
        }
        $this->stored=(bool)$row;
        return $row ? self::normalize($row) : self::DEFAULTS;
    }

    public function hasStored(): bool { return $this->stored; }

    public function save(array $values): array
    {
        $preferences = self::normalize($values,$this->get());
        db()->query('INSERT INTO reel_player_preferences (owner_id,display_mode,sitewide_player,remember_queue,restore_position,theme,personalization,updated_at) VALUES (?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE display_mode=VALUES(display_mode),sitewide_player=VALUES(sitewide_player),remember_queue=VALUES(remember_queue),restore_position=VALUES(restore_position),theme=VALUES(theme),personalization=VALUES(personalization),updated_at=VALUES(updated_at)',
            [$this->owner,$preferences['display_mode'],(int)$preferences['sitewide_player'],(int)$preferences['remember_queue'],(int)$preferences['restore_position'],$preferences['theme'],(int)$preferences['personalization'],date('Y-m-d H:i:s')]);
        $this->stored=true;
        return $preferences;
    }
}
