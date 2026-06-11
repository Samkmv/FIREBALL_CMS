<?php

namespace App\Models;

/**
 * Manages contact form subjects independently from site settings.
 */
class ContactSubject
{

    protected string $table = 'contact_subjects';
    protected static bool $schemaReady = false;

    public function ensureTableExists(): void
    {
        if (self::$schemaReady) {
            return;
        }

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                is_active TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                recipient_email VARCHAR(190) NULL,
                responsible_user_id INT(10) UNSIGNED NULL,
                category VARCHAR(100) NULL,
                auto_reply TEXT NULL,
                color_label VARCHAR(32) NULL,
                priority TINYINT(3) UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY name (name),
                KEY active_sort (is_active, sort_order, id),
                KEY responsible_user_id (responsible_user_id),
                KEY category (category),
                KEY priority (priority)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaReady = true;
        $this->migrateLegacySubjects();
    }

    public function getAll(): array
    {
        $this->ensureTableExists();

        return db()->query(
            "SELECT id, name, is_active, sort_order, created_at, updated_at
             FROM {$this->table}
             ORDER BY sort_order ASC, id ASC"
        )->get() ?: [];
    }

    public function getActiveNames(): array
    {
        $this->ensureTableExists();

        $rows = db()->query(
            "SELECT name
             FROM {$this->table}
             WHERE is_active = 1
             ORDER BY sort_order ASC, id ASC"
        )->get() ?: [];

        return array_map(
            static fn(array $row): string => (string)$row['name'],
            $rows
        );
    }

    public function find(int $id): ?array
    {
        $this->ensureTableExists();
        if ($id <= 0) {
            return null;
        }

        $row = db()->query(
            "SELECT *
             FROM {$this->table}
             WHERE id = ?
             LIMIT 1",
            [$id]
        )->getOne();

        return is_array($row) ? $row : null;
    }

    public function existsByName(string $name, int $excludeId = 0): bool
    {
        $this->ensureTableExists();
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE name = ?";
        $params = [$name];

        if ($excludeId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }

        return (int)db()->query($sql, $params)->getColumn() > 0;
    }

    public function create(array $data): int
    {
        $this->ensureTableExists();
        $now = date('Y-m-d H:i:s');

        db()->query(
            "INSERT INTO {$this->table}
             (name, is_active, sort_order, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?)",
            [
                trim((string)$data['name']),
                !empty($data['is_active']) ? 1 : 0,
                (int)$data['sort_order'],
                $now,
                $now,
            ]
        );

        return (int)db()->getInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $this->ensureTableExists();
        if ($id <= 0) {
            return false;
        }

        db()->query(
            "UPDATE {$this->table}
             SET name = ?, is_active = ?, sort_order = ?, updated_at = ?
             WHERE id = ?
             LIMIT 1",
            [
                trim((string)$data['name']),
                !empty($data['is_active']) ? 1 : 0,
                (int)$data['sort_order'],
                date('Y-m-d H:i:s'),
                $id,
            ]
        );

        return db()->rowCount() > 0 || $this->find($id) !== null;
    }

    public function setActive(int $id, bool $isActive): bool
    {
        $this->ensureTableExists();
        if ($id <= 0) {
            return false;
        }

        db()->query(
            "UPDATE {$this->table}
             SET is_active = ?, updated_at = ?
             WHERE id = ?
             LIMIT 1",
            [$isActive ? 1 : 0, date('Y-m-d H:i:s'), $id]
        );

        return db()->rowCount() > 0 || $this->find($id) !== null;
    }

    public function delete(int $id): bool
    {
        $this->ensureTableExists();
        if ($id <= 0) {
            return false;
        }

        db()->query("DELETE FROM {$this->table} WHERE id = ? LIMIT 1", [$id]);

        return db()->rowCount() > 0;
    }

    /**
     * Imports the former JSON setting once so updates do not lose configured subjects.
     */
    protected function migrateLegacySubjects(): void
    {
        $siteSettings = new SiteSetting();
        $siteSettings->ensureTableExists();
        if ($siteSettings->get('contact_subjects_migrated', '0') === '1') {
            return;
        }

        if ((int)db()->query("SELECT COUNT(*) FROM {$this->table}")->getColumn() > 0) {
            $siteSettings->setMany(['contact_subjects_migrated' => '1']);
            return;
        }

        $legacyJson = db()->query(
            "SELECT setting_value
             FROM site_settings
             WHERE setting_key = 'contacts_form_subjects'
             LIMIT 1"
        )->getColumn();
        $subjects = is_string($legacyJson) ? json_decode($legacyJson, true) : null;

        if (!is_array($subjects) || empty($subjects)) {
            $subjects = array_map(
                static fn(string $key): string => return_translation($key),
                [
                    'contacts_subject_general_inquiry',
                    'contacts_subject_order_status',
                    'contacts_subject_product_information',
                    'contacts_subject_technical_support',
                    'contacts_subject_website_feedback',
                    'contacts_subject_account_assistance',
                    'contacts_subject_security_concerns',
                ]
            );
        }

        $seen = [];
        $sortOrder = 10;
        $now = date('Y-m-d H:i:s');
        foreach ($subjects as $subject) {
            $name = mb_substr(trim((string)$subject), 0, 190);
            $normalized = mb_strtolower($name);
            if ($name === '' || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            db()->query(
                "INSERT IGNORE INTO {$this->table}
                 (name, is_active, sort_order, created_at, updated_at)
                 VALUES (?, 1, ?, ?, ?)",
                [$name, $sortOrder, $now, $now]
            );
            $sortOrder += 10;
        }

        $siteSettings->setMany(['contact_subjects_migrated' => '1']);
    }
}
