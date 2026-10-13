<?php
declare(strict_types=1);
namespace Fireball\ReelPlayer\Controllers;

use Fireball\ReelPlayer\Repositories\Distribution;
use Fireball\ReelPlayer\Services\PlatformFeatures;

final class DistributionController
{
    private function repository(): Distribution { return new Distribution((int)get_user()['id']); }
    public function index(): string
    {
        header('Cache-Control: private, no-store');
        if (!PlatformFeatures::schemaReady(true)) return plugin_view('reel-player', 'platform/admin', ['title' => 'Tape Room · Настройки платформы', 'features' => PlatformFeatures::all(), 'albums' => [], 'tracks' => [], 'plans' => [], 'csrf' => (string)session()->get('needCSRFToken', ''), 'canManageFeatures' => check_creator(), 'migrationPending' => true, 'admin_context' => true], true);
        $plans = [];
        if (class_exists('FireballPluginSubscriptions') && db()->query('SELECT status FROM plugins WHERE slug=?', ['subscriptions'])->getColumn() === 'active') $plans = db()->query('SELECT id,name FROM subscription_plans WHERE is_active=1 ORDER BY sort_order,id')->get() ?: [];
        return plugin_view('reel-player', 'platform/admin', ['title' => 'Tape Room · Настройки платформы', 'features' => PlatformFeatures::all(), ...$this->repository()->state(), 'plans' => $plans, 'csrf' => (string)session()->get('needCSRFToken', ''), 'canManageFeatures' => check_creator(), 'admin_context' => true], true);
    }
    public function save(): never
    {
        header('Cache-Control: private, no-store');
        try {
            if (!PlatformFeatures::schemaReady(true)) throw new \InvalidArgumentException('Сначала завершите обновление Tape Room в настройках плагинов CMS.');
            $data = request()->getData(); $action = $data['action'] ?? '';
            if (!is_string($action)) throw new \InvalidArgumentException('Некорректное действие платформы.');
            if ($action === 'settings') {
                if (!check_creator()) response()->json(['status' => false, 'message' => 'Общие настройки доступны только создателю сайта.'], 403);
                PlatformFeatures::save($data);
            } elseif ($action === 'embed.settings') $this->repository()->saveEmbed((int)($data['id'] ?? 0), $data);
            elseif (in_array($action, ['album','track'], true)) $this->repository()->save($action, (int)($data['id'] ?? 0), $data);
            else throw new \InvalidArgumentException('Неизвестное действие платформы.');
            response()->json(['status' => true, 'features' => PlatformFeatures::all(), ...$this->repository()->state()]);
        } catch (\InvalidArgumentException $error) { response()->json(['status' => false, 'message' => $error->getMessage()], 422); }
        catch (\Throwable $error) { log_error_details('Tape Room distribution failed', [], $error); response()->json(['status' => false, 'message' => 'Не удалось сохранить настройки.'], 500); }
        exit;
    }
}
