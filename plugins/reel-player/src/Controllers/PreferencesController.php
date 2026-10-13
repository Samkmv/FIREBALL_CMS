<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Controllers;

use Fireball\ReelPlayer\Repositories\PlayerPreferences;

final class PreferencesController
{
    public function get(): void { $this->respond(static fn(PlayerPreferences $repository): array => $repository->get()); }
    public function save(): void { $this->respond(static fn(PlayerPreferences $repository): array => $repository->save(request()->getData())); }

    private function respond(callable $operation): never
    {
        header('Cache-Control: private, no-store');
        header('Vary: Cookie');
        try {
            $owner = (int)(get_user()['id'] ?? 0);
            if ($owner<1) response()->json(['status'=>false,'message'=>'Необходима авторизация.'],401);
            $repository=new PlayerPreferences($owner);
            $preferences=$operation($repository);
            response()->json(['status'=>true,'preferences'=>$preferences,'initialized'=>$repository->hasStored()]);
        } catch (\InvalidArgumentException $error) {
            response()->json(['status'=>false,'message'=>$error->getMessage()],422);
        } catch (\Throwable $error) {
            log_error_details('Tape Room preferences failed',[],$error);
            response()->json(['status'=>false,'message'=>'Не удалось сохранить настройки плеера.'],500);
        }
        exit;
    }
}
