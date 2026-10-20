<?php

return static function (): void {
    // Additive and retry-safe: legacy links and existing account verification flags remain intact.
    $columns = array_column(db()->query('SHOW COLUMNS FROM user_email_verifications')->get(), 'Field');
    foreach ([
        'code_hash' => 'VARCHAR(255) NULL',
        'code_attempts' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
    ] as $name => $definition) {
        if (!in_array($name, $columns, true)) db()->query("ALTER TABLE user_email_verifications ADD COLUMN {$name} {$definition}");
    }
    db()->query('INSERT IGNORE INTO site_settings (setting_key, setting_value, updated_at) VALUES (?, ?, ?)',
        ['auth_email_verification_required', '1', date('Y-m-d H:i:s')]);
};
