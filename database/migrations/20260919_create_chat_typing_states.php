<?php
// FIREBALL_CHAT24_TYPING
return static function (): void {
    db()->query("CREATE TABLE IF NOT EXISTS chat_typing_states (
        conversation_id BIGINT UNSIGNED NOT NULL,
        user_id INT(10) UNSIGNED NOT NULL,
        typing_until DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (conversation_id, user_id),
        KEY typing_until (typing_until),
        KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->query(
        "DELETE FROM chat_typing_states
         WHERE typing_until < DATE_SUB(NOW(), INTERVAL 1 DAY)"
    );
};
