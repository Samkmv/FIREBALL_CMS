<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Controllers;

use Fireball\ReelPlayer\Repositories\PublicCatalog;
use Fireball\ReelPlayer\Repositories\Taxonomy;
use Fireball\ReelPlayer\Services\PlatformFeatures;
use Fireball\ReelPlayer\Services\Recommendations;

final class DiscoveryController
{
    private function publicGuard(): void
    {
        header('Cache-Control: private, no-store');
        if (!PlatformFeatures::enabled('public_catalog') || !PlatformFeatures::enabled('discovery')) response()->json(['status'=>false,'message'=>'Музыкальные рекомендации отключены.'], 404);
    }

    private function adminGuard(): Taxonomy
    {
        header('Cache-Control: private, no-store');
        if (!check_admin() || !check_auth()) response()->json(['status'=>false,'message'=>'Требуется доступ администратора.'], 403);
        if (!PlatformFeatures::enabled('discovery')) response()->json(['status'=>false,'message'=>'Жанры и теги отключены.'], 404);
        return new Taxonomy((int)get_user()['id'], true);
    }

    private function catalog(): PublicCatalog
    {
        return new PublicCatalog(check_auth() ? get_user() : null);
    }

    private static function integer(mixed $value, bool $optional = false): int
    {
        if ($optional && ($value === null || $value === '' || $value === 0 || $value === '0')) return 0;
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[1-9][0-9]*$/D', (string)$value) || filter_var($value, FILTER_VALIDATE_INT) === false) throw new \InvalidArgumentException('Некорректный идентификатор.');
        return (int)$value;
    }

    private static function text(mixed $value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) throw new \InvalidArgumentException('Некорректный тип музыки.');
        return $value;
    }

    private function respond(callable $callback): never
    {
        try { response()->json(['status'=>true, ...$callback()]); }
        catch (\InvalidArgumentException $error) { response()->json(['status'=>false,'message'=>$error->getMessage()], 422); }
        catch (\Throwable $error) {
            log_error_details('Tape Room discovery request failed', [], $error);
            response()->json(['status'=>false,'message'=>'Не удалось получить музыкальные данные.'], 500);
        }
    }

    public function genres(): void
    {
        $this->publicGuard();
        $this->respond(fn(): array => ['items'=>$this->catalog()->taxonomy('genre')]);
    }

    public function tags(): void
    {
        $this->publicGuard();
        $this->respond(fn(): array => ['items'=>$this->catalog()->taxonomy('tag')]);
    }

    public function recommendations(): void
    {
        $this->publicGuard();
        $this->respond(function (): array {
            $track = self::integer(request()->get('track_id', 0), true);
            $limit = min(24, self::integer(request()->get('limit', 12)));
            $recommendations = new Recommendations($this->catalog());
            if ($track) {
                try { return $recommendations->similar($track, $limit); }
                catch (\InvalidArgumentException) { response()->json(['status'=>false,'message'=>'Запись не найдена.'], 404); }
            }
            return $recommendations->forUser($limit, ['genre'=>request()->get('genre', []),'tag'=>request()->get('tag', [])]);
        });
    }

    public function dictionary(): void
    {
        $taxonomy = $this->adminGuard();
        $this->respond(fn(): array => ['genres'=>$taxonomy->dictionary('genre'),'tags'=>$taxonomy->dictionary('tag')]);
    }

    public function entity(): void
    {
        $taxonomy = $this->adminGuard();
        $this->respond(fn(): array => ['entity'=>$taxonomy->entity(self::text(request()->get('type', '')), self::integer(request()->get('id')))]);
    }

    public function action(): void
    {
        $taxonomy = $this->adminGuard();
        $this->respond(function () use ($taxonomy): array {
            $data = request()->getData();
            $action = $data['action'] ?? '';
            if ($action === 'entity.set') return ['entity'=>$taxonomy->replace(self::text($data['type'] ?? ''), self::integer($data['id'] ?? null), $data['genres'] ?? [], $data['tags'] ?? [])];
            if (!is_string($action) || !preg_match('/^(genre|tag)\.(create|update|delete)$/D', $action, $parts)) throw new \InvalidArgumentException('Неизвестное действие справочника.');
            [$unused,$kind,$operation] = $parts;
            $id = $operation === 'create' ? 0 : self::integer($data['id'] ?? null);
            if ($operation === 'delete') $taxonomy->delete($kind, $id);
            else $id = $taxonomy->save($kind, $data, $id);
            return ['id'=>$id,'genres'=>$taxonomy->dictionary('genre'),'tags'=>$taxonomy->dictionary('tag')];
        });
    }
}
