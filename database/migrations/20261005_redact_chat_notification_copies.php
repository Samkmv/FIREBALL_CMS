<?php

/** Remove legacy plaintext copies only; encrypted chat messages/media and delivery history stay intact. */
return static function (): void {
    $private = \App\Services\NotificationPrivacy::chatSqlPredicate();
    $title = \App\Services\NotificationPrivacy::PRIVATE_TITLE;
    db()->beginTransaction();
    try {
        db()->query("UPDATE pwa_notifications SET title = ?, body = '', payload = '{}' WHERE {$private}", [$title]);
        db()->query("UPDATE notifications SET title = ?, message = '', metadata = '{}' WHERE {$private}", [$title]);
        db()->commit();
    } catch (\Throwable $exception) {
        db()->rollBack();
        throw $exception;
    }
};
