<?php

return [
    'DEBUG' => 0,
    'PATH' => 'https://example.com',
    'SITE_NAME' => 'FIREBALL_CMS',
    'CHAT_ENCRYPTION_KEY' => 'change-this-chat-key-in-production',
    'APP_TIMEZONE' => 'Europe/Moscow',
    'DB_SETTINGS' => [
        'host' => 'localhost',
        'database' => 'fireball',
        'username' => 'root',
        'password' => '',
        'port' => 3306,
    ],
    'MAIL_SETTINGS' => [
        'host' => 'smtp.example.com',
        'username' => 'user@example.com',
        'password' => 'secret',
        'from_email' => 'noreply@example.com',
        'from_name' => 'Fireball CMS',
    ],
];
