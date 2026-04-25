<?php

namespace FBL;

/**
 * Обёртка над PDO для выполнения запросов и получения результатов.
 */
class Database
{

    protected \PDO $connection;
    protected \PDOStatement $stmt;

    /**
     * Создаёт подключение к базе данных по настройкам проекта.
     */
    public function __construct()
    {
        $dsn = "mysql:host=" . DB_SETTINGS['host'] . ";dbname=" . DB_SETTINGS['database'] . ";charset=" . DB_SETTINGS['charset'];
        if (!empty(DB_SETTINGS['port'])) {
            $dsn .= ";port=" . (int)DB_SETTINGS['port'];
        }

        try {
            $this->connection = new \PDO($dsn, DB_SETTINGS['username'], DB_SETTINGS['password'], DB_SETTINGS['options']);
        } catch (\PDOException $e) {
            log_error_details('Database connection error', [
                'Driver' => DB_SETTINGS['driver'] ?? 'mysql',
                'Host' => DB_SETTINGS['host'] ?? '',
                'Port' => DB_SETTINGS['port'] ?? '',
                'Database' => DB_SETTINGS['database'] ?? '',
                'Username' => DB_SETTINGS['username'] ?? '',
                'DSN' => $dsn,
            ], $e);
            abort('Database connection error!', 500);
        }

        return $this;
    }

    /**
     * Подготавливает и выполняет SQL-запрос с параметрами.
     */
    public function query(string $query, array $params = []): static
    {
        try {
            $this->stmt = $this->connection->prepare($query);
            $this->stmt->execute($params);
        } catch (\PDOException $e) {
            log_error_details('Database query error', [
                'SQL' => $query,
                'Params' => $params,
            ], $e);
            throw $e;
        }

        return $this;
    }

    /**
     * Возвращает все строки результата текущего запроса.
     */
    public function get(): false|array
    {
        return $this->stmt->fetchAll();
    }

    /**
     * Возвращает результат в виде массива, индексированного по указанному ключу.
     */
    public function getAssoc($key = 'id')
    {
        $data = [];

        while ($row = $this->stmt->fetch()) {
            $data[$row[$key]] = $row;
        }

        return $data;
    }

    /**
     * Возвращает первую строку результата текущего запроса.
     */
    public function getOne()
    {
        return $this->stmt->fetch();
    }

    /**
     * Возвращает значение первой колонки из первой строки результата.
     */
    public function getColumn()
    {
        return $this->stmt->fetchColumn();
    }

    /**
     * Возвращает все записи из указанной таблицы.
     */
    public function findAll($tbl): false|array
    {
        $this->query("SELECT * FROM {$tbl}");
        return $this->stmt->fetchAll();
    }

    /**
     * Ищет одну запись по значению указанного поля.
     */
    public function findOne($tbl, $value, $key = 'id')
    {
        $this->query("SELECT * FROM {$tbl} WHERE {$key} = ? LIMIT 1", [$value]);
        return $this->stmt->fetch();
    }

    /**
     * Ищет запись и завершает запрос ошибкой 404, если запись не найдена.
     */
    public function findOrFail($tbl, $value, $key = 'id')
    {
        $result = $this->findOne($tbl, $value, $key);
        if (!$result) {
            if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
                response()->json(['status' => 'error', 'Not found'], 404);
            }

            abort();
        }

        return $result;
    }

    /**
     * Возвращает идентификатор последней добавленной записи.
     */
    public function getInsertId(): false|string
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Возвращает количество строк, затронутых последним запросом.
     */
    public function rowCount(): int
    {
        return $this->stmt->rowCount();
    }

    /**
     * Начинает транзакцию базы данных.
     */
    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Подтверждает текущую транзакцию.
     */
    public function commit(): bool
    {
        return $this->connection->commit();
    }

    /**
     * Откатывает текущую транзакцию.
     */
    public function rollBack(): bool
    {
        return $this->connection->rollBack();
    }

}
