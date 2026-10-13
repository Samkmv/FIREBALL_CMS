<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Services;

use Fireball\ReelPlayer\Repositories\PlayerPreferences;
use Fireball\ReelPlayer\Repositories\PublicCatalog;
use Fireball\ReelPlayer\Repositories\Taxonomy;

/** Bounded, deterministic suggestions drawn only from the stream-authorized catalog. */
final class Recommendations
{
    private const MAX_CANDIDATES = 200;

    public function __construct(private PublicCatalog $catalog, private ?\DateTimeImmutable $now = null) {}

    private static function artistKey(string $value): string
    {
        return mb_strtolower(trim((string)preg_replace('/\s+/u', ' ', $value)), 'UTF-8');
    }

    private function personalization(): bool
    {
        $owner = $this->catalog->viewerId();
        if ($owner < 1 || !class_exists(PlayerPreferences::class)) return false;
        return ((new PlayerPreferences($owner))->get()['personalization'] ?? false) === true;
    }

    /** Favorites are preferences, never an alternate way of authorizing candidates. */
    private function personalSeeds(): array
    {
        $owner = $this->catalog->viewerId();
        if ($owner < 1) return [];
        $rows = db()->query('SELECT t.id,t.artist FROM reel_tracks t WHERE t.owner_id=? AND t.favorite=1
            UNION SELECT t.id,t.artist FROM reel_catalog_favorites f JOIN reel_tracks t ON t.id=f.track_id WHERE f.owner_id=? AND f.favorite=1
            UNION SELECT t.id,t.artist FROM reel_album_saves s JOIN reel_album_tracks members ON members.album_id=s.album_id JOIN reel_tracks t ON t.id=members.track_id WHERE s.owner_id=?
            ORDER BY id DESC LIMIT 200', [$owner,$owner,$owner])->get() ?: [];
        return Taxonomy::decorate('track', $rows);
    }

    private static function profile(array $seeds): array
    {
        $profile = ['genres'=>[], 'tags'=>[], 'artists'=>[]];
        foreach ($seeds as $seed) {
            foreach (['genres','tags'] as $kind) {
                foreach ($seed[$kind] ?? [] as $item) {
                    $id = (int)($item['id'] ?? 0);
                    if ($id > 0) $profile[$kind][$id] = ($profile[$kind][$id] ?? 0) + 1;
                }
            }
            $artist = self::artistKey((string)($seed['artist'] ?? ''));
            if ($artist !== '') $profile['artists'][$artist] = ($profile['artists'][$artist] ?? 0) + 1;
        }
        $count = max(1, count($seeds));
        foreach ($profile as &$weights) foreach ($weights as &$weight) $weight /= $count;
        unset($weight, $weights);
        return $profile;
    }

    /** Stable tie-breaking avoids cards moving arbitrarily between identical requests. */
    public static function rank(array $candidates, array $seeds, int $limit = 12, ?\DateTimeImmutable $now = null, array $exclude = []): array
    {
        $limit = max(1, min(24, $limit));
        $candidates = array_slice($candidates, 0, self::MAX_CANDIDATES);
        $profile = self::profile($seeds);
        $excluded = array_fill_keys(array_map('intval', $exclude), true);
        $timestamp = ($now ?? new \DateTimeImmutable())->getTimestamp();
        $ranked = []; $seen = [];
        foreach ($candidates as $candidate) {
            $id = (int)($candidate['id'] ?? 0);
            if ($id < 1 || isset($excluded[$id]) || isset($seen[$id])) continue;
            // Defensive checks complement the SQL stream scope in PublicCatalog.
            if (!empty($candidate['locked']) || (array_key_exists('can_stream', $candidate) && !$candidate['can_stream'])
                || (isset($candidate['access']) && (!is_array($candidate['access']) || empty($candidate['access']['stream'])))) continue;
            $seen[$id] = true;
            $score = 0.0; $matches = ['genres'=>0,'tags'=>0,'artist'=>false];
            foreach (['genres'=>5.0,'tags'=>3.0] as $kind=>$multiplier) {
                foreach ($candidate[$kind] ?? [] as $item) {
                    $weight = $profile[$kind][(int)($item['id'] ?? 0)] ?? 0;
                    if ($weight > 0) { $score += $multiplier * $weight; $matches[$kind]++; }
                }
            }
            $artist = self::artistKey((string)($candidate['artist'] ?? ''));
            if ($artist !== '' && isset($profile['artists'][$artist])) {
                $score += 4.0 * $profile['artists'][$artist]; $matches['artist'] = true;
            }
            $listens = max(0, (int)($candidate['listen_count'] ?? 0));
            $score += min(1.0, log(1.0 + $listens) / 10.0);
            $created = strtotime((string)($candidate['published_at'] ?? $candidate['created_at'] ?? ''));
            $recency = $created === false ? 0.0 : max(0.0, 1.0 - max(0, $timestamp - $created) / (90 * 86400));
            $score += .75 * $recency;
            $reason = $matches['genres'] ? 'genre' : ($matches['tags'] ? 'tag' : ($matches['artist'] ? 'artist' : ($listens > 0 ? 'popular' : 'new')));
            $ranked[] = ['item'=>[...$candidate,'recommendation_reason'=>$reason], 'score'=>$score, 'created'=>$created ?: 0];
        }
        usort($ranked, static fn(array $left, array $right): int => [$right['score'],$right['created'],(int)$right['item']['id']] <=> [$left['score'],$left['created'],(int)$left['item']['id']]);
        return array_column(array_slice($ranked, 0, $limit), 'item');
    }

    private function candidates(array $filters = []): array
    {
        // Access is checked in SQL before this bound, not after loading a full library.
        return self::withTaxonomy($this->catalog->recommendationCandidates(self::MAX_CANDIDATES, $filters));
    }

    private static function withTaxonomy(array $items): array
    {
        // PublicCatalog already batches this metadata when discovery is enabled.
        foreach ($items as $item) if (!isset($item['genres'],$item['tags'])) return Taxonomy::decorate('track', $items);
        return $items;
    }

    public function similar(int $trackId, int $limit = 12): array
    {
        $source = self::withTaxonomy([$this->catalog->track($trackId)])[0];
        return ['items'=>self::rank($this->candidates(), [$source], $limit, $this->now, [$trackId]),'personalized'=>false,'mode'=>'similar'];
    }

    public function forUser(int $limit = 12, array $filters = []): array
    {
        $personalized = $this->personalization();
        $seeds = $personalized ? $this->personalSeeds() : [];
        return ['items'=>self::rank($this->candidates($filters), $seeds, $limit, $this->now),'personalized'=>$personalized && $seeds !== [],'mode'=>$personalized && $seeds !== [] ? 'personal' : 'popular'];
    }
}
