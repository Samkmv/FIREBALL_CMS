<?php

namespace Fireball\Subscriptions\Services;

/** A single, session-owned, sequential import; routes retain normal auth/admin/CSRF. */
final class AddressCatalogImportService
{
    private const SESSION_KEY = 'subscriptions.address_catalog_import';
    public const MAX_ROWS = 1500;

    public static function requestLimit(): int
    {
        $value = trim((string)ini_get('post_max_size'));
        $bytes = (int)$value;
        $bytes *= match (strtolower(substr($value, -1))) { 'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1 };
        return min(256 * 1024, $bytes > 0 ? max(1024, (int)floor($bytes / 2)) : 256 * 1024);
    }

    public function handle(array $data): array
    {
        $directory = new AddressSuggestionService();
        $action = (string)($data['action'] ?? '');
        if ($action === 'start') {
            $header = $data['header'] ?? null;
            if (!is_array($header) || count($header) > 100 || array_filter($header, static fn($v) => !is_string($v))) {
                throw new \InvalidArgumentException('Не удалось прочитать заголовок CSV.');
            }
            $columns = $directory->importColumns($header);
            $previous = session()->get(self::SESSION_KEY);
            if (is_array($previous) && empty($previous['completed'])) $directory->discardImportTable($previous['table']);
            $id = bin2hex(random_bytes(16));
            $replace = ($data['replace'] ?? false) === true;
            $table = $replace ? 'subscription_address_import_' . $id : AddressSuggestionService::TABLE;
            $directory->prepareImportTable($table);
            $job = ['id' => $id, 'table' => $table, 'columns' => $columns, 'replace' => $replace,
                'next' => 0, 'processed' => 0, 'skipped' => 0, 'completed' => false, 'last_hash' => ''];
            session()->set(self::SESSION_KEY, $job);
            return $this->result($job);
        }

        $job = session()->get(self::SESSION_KEY);
        if (!is_array($job) || !hash_equals($job['id'], (string)($data['id'] ?? ''))) {
            throw new \InvalidArgumentException('Сессия импорта не найдена. Обновите страницу и выберите файл заново.');
        }
        if ($action === 'cancel') {
            if (!$job['completed']) $directory->discardImportTable($job['table']);
            session()->remove(self::SESSION_KEY);
            return ['ok' => true, 'cancelled' => true, 'replace' => $job['replace']];
        }
        if ($action === 'finish') {
            if (!$job['completed']) {
                if ((int)($data['sequence'] ?? -1) !== $job['next']) throw new \InvalidArgumentException('Импорт ещё не завершён.');
                if ($job['processed'] === 0) throw new \InvalidArgumentException('В файле нет пригодных адресов. Текущий справочник не заменён.');
                $directory->publishImportTable($job['table']);
                $job['completed'] = true;
                session()->set(self::SESSION_KEY, $job);
            }
            return $this->result($job);
        }
        if ($action !== 'batch' || $job['completed']) throw new \InvalidArgumentException('Недопустимый этап импорта.');
        $rows = $data['rows'] ?? null;
        if (!is_array($rows) || count($rows) < 1 || count($rows) > self::MAX_ROWS) throw new \InvalidArgumentException('Некорректный размер порции CSV.');
        $sequence = (int)($data['sequence'] ?? -1);
        try {
            $hash = hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
        } catch (\JsonException) {
            throw new \InvalidArgumentException('CSV должен содержать текст в кодировке UTF-8.');
        }
        if ($sequence === $job['next'] - 1 && hash_equals($job['last_hash'], $hash)) return $this->result($job);
        if ($sequence !== $job['next']) throw new \InvalidArgumentException('Порции CSV получены в неверном порядке.');
        $database = db();
        try {
            $database->beginTransaction();
            $result = $directory->importRecords($rows, $job['columns'], $job['table']);
            $database->commit();
        } catch (\Throwable $exception) {
            if ($database->inTransaction()) $database->rollBack();
            throw $exception;
        }
        $job['processed'] += $result['processed'];
        $job['skipped'] += $result['skipped'];
        $job['next']++;
        $job['last_hash'] = $hash;
        session()->set(self::SESSION_KEY, $job);
        return $this->result($job);
    }

    private function result(array $job): array
    {
        return array_intersect_key($job, array_flip(['id', 'next', 'processed', 'skipped', 'completed', 'replace'])) + ['ok' => true];
    }
}
