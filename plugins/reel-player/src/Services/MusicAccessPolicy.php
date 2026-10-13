<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

/** One public boundary for metadata, every Range request, downloads and embeds. */
final class MusicAccessPolicy
{
    public const MODES = ['private', 'internal', 'public', 'authenticated', 'subscription'];
    public const LICENSES = ['own', 'permission', 'creative_commons', 'other'];
    private ?array $paid = null;

    public function __construct(private ?array $user = null) {}
    public function viewerId(): int { return max(0, (int)($this->user['id'] ?? 0)); }

    /** Request-local entitlement cache; a new HTTP/Range request rechecks it. */
    private function entitlement(): array
    {
        if ($this->paid !== null) return $this->paid;
        $this->paid = ['plan_id' => 0, 'listen' => false, 'download' => false];
        if (!PlatformFeatures::enabled('monetization') || $this->viewerId() === 0) return $this->paid;
        if (!class_exists('FireballPluginSubscriptions')) return $this->paid;
        $active = db()->query('SELECT status FROM plugins WHERE slug=?', ['subscriptions'])->getColumn();
        if ($active !== 'active') return $this->paid;
        $service = new \Fireball\Subscriptions\Services\AccessService();
        $subscription = $service->activeSubscription($this->viewerId());
        if (!$subscription) return $this->paid;
        // can() alone bypasses plans for administrators. The exact live plan
        // and its actual permission rows are still required in public music.
        $planId = (int)$subscription['plan_id'];
        $permissions = $service->permissions($planId);
        $this->paid = [
            'plan_id' => $planId,
            'listen' => !empty($permissions['music.listen_paid']) && \FireballPluginSubscriptions::can($this->viewerId(), 'music.listen_paid'),
            'download' => !empty($permissions['music.download_paid']) && \FireballPluginSubscriptions::can($this->viewerId(), 'music.download_paid'),
        ];
        return $this->paid;
    }

    private function audience(array $rule, string $action): string
    {
        $mode = (string)($rule['access_mode'] ?? 'internal');
        if (!in_array($mode, ['public', 'authenticated', 'subscription'], true)) return 'not_published';
        if ($action === 'view_metadata' && !empty($rule['show_metadata'])) return '';
        if ($mode === 'public') return '';
        if ($this->viewerId() === 0) return 'authentication_required';
        if ($mode === 'authenticated') return '';
        $paid = $this->entitlement();
        if (!$paid['plan_id']) return 'subscription_required';
        $permission = $action === 'download' ? $paid['download'] : ($action === 'view_metadata' ? ($paid['listen'] || $paid['download']) : $paid['listen']);
        if (!$permission) return 'permission_required';
        $plans = array_map('intval', (array)($rule['plan_ids'] ?? []));
        if ($plans && !in_array($paid['plan_id'], $plans, true)) return 'plan_required';
        return '';
    }

    /** Source paths and album rows passed here are server-only. */
    public function decision(array $track, ?array $album = null, string $action = 'stream'): array
    {
        $deny = static fn(string $reason): array => ['allowed' => false, 'reason' => $reason];
        if (!PlatformFeatures::enabled('public_catalog')) return $deny('feature_disabled');
        if (!in_array($action, ['view_metadata', 'stream', 'download', 'embed'], true)) return $deny('invalid_action');
        $rule = (array)($track['distribution'] ?? []);
        if ($action !== 'view_metadata') {
            if (empty($rule['rights_confirmed']) || !in_array((string)($rule['license_type'] ?? ''), self::LICENSES, true)) return $deny('rights_required');
            if (($track['source'] ?? 'local') !== 'local') return $deny('cloud_public_unavailable');
            if ($action !== 'download' && empty($rule['allow_stream'])) return $deny('listening_disabled');
            if ($action === 'download' && empty($rule['allow_download'])) return $deny('download_disabled');
            if ($action === 'embed' && (!PlatformFeatures::enabled('embed') || empty($rule['allow_embed']))) return $deny('embed_disabled');
        }
        $reason = $this->audience($rule, $action);
        if ($reason !== '') return $deny($reason);
        if ($action === 'embed' && ($rule['access_mode'] ?? '') !== 'public') return $deny('embed_requires_public');
        $albums = $album !== null ? [$album] : (array)($track['albums'] ?? []);
        $lastReason = 'not_published';
        foreach ($albums as $candidate) {
            if (empty($candidate['published'])) continue;
            $albumRule = (array)($candidate['distribution'] ?? []);
            $lastReason = $this->audience($albumRule, $action);
            if ($action === 'embed' && ($albumRule['access_mode'] ?? '') !== 'public') $lastReason = 'embed_requires_public';
            if ($lastReason === '') return ['allowed' => true, 'reason' => 'allowed'];
        }
        return $deny($lastReason);
    }

    public function albumDecision(array $album, string $action = 'view_metadata'): array
    {
        if (!PlatformFeatures::enabled('public_catalog') || empty($album['published'])) return ['allowed' => false, 'reason' => 'not_published'];
        $reason = $this->audience((array)($album['distribution'] ?? []), $action);
        return ['allowed' => $reason === '', 'reason' => $reason ?: 'allowed'];
    }

    /** The exact same audience boundary is applied before COUNT and LIMIT. */
    private function audienceSql(string $ruleAlias, string $type, string $entityAlias, string $action = 'stream'): array
    {
        $paid = $this->entitlement();
        $sql = "{$ruleAlias}.access_mode='public'";
        if ($this->viewerId() > 0) $sql .= " OR {$ruleAlias}.access_mode='authenticated'";
        $params = [];
        if ($paid['listen'] || ($action === 'view_metadata' && $paid['download'])) {
            $table = $type === 'album' ? 'reel_album_distribution_plans' : 'reel_track_distribution_plans';
            $key = $type . '_id';
            $sql .= " OR ({$ruleAlias}.access_mode='subscription' AND (NOT EXISTS(SELECT 1 FROM {$table} mp WHERE mp.{$key}={$entityAlias}.id) OR EXISTS(SELECT 1 FROM {$table} mp WHERE mp.{$key}={$entityAlias}.id AND mp.plan_id=?)))";
            $params[] = $paid['plan_id'];
        }
        return ['sql' => '(' . $sql . ')', 'params' => $params];
    }

    public function metadataScope(string $type, string $alias): array
    {
        if (!in_array($type, ['track', 'album'], true) || !preg_match('/^[a-z][a-z0-9_]*$/D', $alias)) throw new \InvalidArgumentException('Invalid public scope.');
        if (!PlatformFeatures::enabled('public_catalog')) return ['sql' => '1=0', 'params' => []];
        $d = $alias . '_md';
        $audience = $this->audienceSql($d, $type, $alias, 'view_metadata');
        $scope = "EXISTS(SELECT 1 FROM reel_{$type}_distribution {$d} WHERE {$d}.{$type}_id={$alias}.id AND {$d}.access_mode IN ('public','authenticated','subscription') AND ({$d}.show_metadata=1 OR {$audience['sql']}))";
        if ($type === 'album') return ['sql' => "{$alias}.published=1 AND {$scope}", 'params' => $audience['params']];
        $albumScope = $this->metadataScope('album', $alias . '_ma');
        return [
            'sql' => $scope . " AND EXISTS(SELECT 1 FROM reel_album_tracks {$alias}_mat JOIN reel_albums {$alias}_ma ON {$alias}_ma.id={$alias}_mat.album_id WHERE {$alias}_mat.track_id={$alias}.id AND {$albumScope['sql']})",
            'params' => [...$audience['params'], ...$albumScope['params']],
        ];
    }

    public function streamScope(string $alias = 't'): array
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/D', $alias)) throw new \InvalidArgumentException('Invalid public scope.');
        if (!PlatformFeatures::enabled('public_catalog')) return ['sql' => '1=0', 'params' => []];
        $td = $this->audienceSql($alias . '_sd', 'track', $alias);
        $aa = $alias . '_sa'; $ad = $alias . '_sad';
        $album = $this->audienceSql($ad, 'album', $aa);
        return [
            'sql' => "{$alias}.source='local' AND EXISTS(SELECT 1 FROM reel_track_distribution {$alias}_sd WHERE {$alias}_sd.track_id={$alias}.id AND {$alias}_sd.rights_confirmed=1 AND {$alias}_sd.allow_stream=1 AND {$alias}_sd.license_type IN ('own','permission','creative_commons','other') AND {$td['sql']}) AND EXISTS(SELECT 1 FROM reel_album_tracks {$alias}_sat JOIN reel_albums {$aa} ON {$aa}.id={$alias}_sat.album_id JOIN reel_album_distribution {$ad} ON {$ad}.album_id={$aa}.id WHERE {$alias}_sat.track_id={$alias}.id AND {$aa}.published=1 AND {$album['sql']})",
            'params' => [...$td['params'], ...$album['params']],
        ];
    }
}
