<?php
// Additive only: existing messages and encryption keys are left intact.
return static function (): void {
    foreach ([
        'chat_conversations' => [
            'avatar_path' => 'VARCHAR(255) NULL',
            'revision' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
        ],
        'chat_members' => [
            'pinned' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'archived' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'draft_ciphertext' => 'TEXT NULL',
        ],
        'chat_messages' => ['forwarded_label' => 'VARCHAR(190) NULL'],
    ] as $table => $columns) {
        foreach ($columns as $column => $definition) {
            if (!db()->query('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column])->getColumn()) {
                db()->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        }
    }
};
