<?php

return static function (): void {
    // Preflight BEFORE DDL: do not overwrite legacy identifiers to resolve conflicts.
    $rows = db()->query('SELECT id, email FROM users')->get() ?: [];
    $seen = [];
    foreach ($rows as $row) {
        $raw = (string)$row['email'];
        $key = \App\Services\AuthIdentity::email($raw);
        if ($key === '') continue;
        if (trim($raw) !== trim($raw, ' ')) {
            throw new \RuntimeException('Auth migration: unsupported legacy email whitespace; review user ID ' . (int)$row['id'] . '. No account was changed.');
        }
        if (isset($seen[$key])) {
            throw new \RuntimeException('Auth migration: normalized email conflict between user IDs ' . $seen[$key] . ' and ' . (int)$row['id'] . '. No account was changed.');
        }
        $seen[$key] = (int)$row['id'];
    }
    $duplicates = db()->query("SELECT MIN(id) AS id FROM users WHERE TRIM(email) <> '' GROUP BY LOWER(TRIM(email)) HAVING COUNT(*) > 1 LIMIT 1")->getOne();
    if ($duplicates) throw new \RuntimeException('Auth migration: email collation conflict near user ID ' . (int)$duplicates['id'] . '. No account was changed.');
    $columns = array_column(db()->query("SHOW COLUMNS FROM users")->get(), 'Field');
    $phones = [];
    if (in_array('phone', $columns, true)) {
        foreach (db()->query('SELECT id, phone FROM users')->get() ?: [] as $row) {
            if (trim((string)$row['phone']) === '') continue;
            try { $phone = \App\Services\AuthIdentity::phone((string)$row['phone']); }
            catch (\InvalidArgumentException) { throw new \RuntimeException('Auth migration: invalid legacy phone for user ID ' . (int)$row['id']); }
            if (isset($phones[$phone])) throw new \RuntimeException('Auth migration: duplicate phone for user IDs ' . $phones[$phone] . ' and ' . (int)$row['id']);
            $phones[$phone] = (int)$row['id'];
        }
    }
    foreach ([
        'email_normalized' => "VARCHAR(190) GENERATED ALWAYS AS (NULLIF(LOWER(TRIM(email)), '')) STORED",
        'phone' => 'VARCHAR(40) NULL',
        'phone_normalized' => 'VARCHAR(16) NULL',
        'email_verified_at' => 'DATETIME NULL',
        'email_verification_required' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
        'pending_email' => 'VARCHAR(190) NULL',
    ] as $name => $definition) {
        if (!in_array($name, $columns, true)) db()->query("ALTER TABLE users ADD COLUMN {$name} {$definition}");
    }
    foreach ($phones as $phone => $userId) db()->query('UPDATE users SET phone_normalized = ? WHERE id = ?', [$phone, $userId]);
    $indexes = array_column(db()->query('SHOW INDEX FROM users')->get(), 'Key_name');
    foreach (['auth_email_unique' => 'email_normalized', 'auth_phone_unique' => 'phone_normalized'] as $name => $column) {
        if (!in_array($name, $indexes, true)) db()->query("ALTER TABLE users ADD UNIQUE KEY {$name} ({$column})");
    }
    db()->query('CREATE TABLE IF NOT EXISTS user_email_verifications (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        email VARCHAR(190) NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        UNIQUE KEY email_verification_token (token_hash),
        KEY email_verification_user (user_id, expires_at),
        CONSTRAINT email_verification_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    foreach (\App\Services\AuthSettings::DEFAULTS as $key => $value) {
        db()->query('INSERT IGNORE INTO site_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)', [$key, $value, date('Y-m-d H:i:s')]);
    }
};
