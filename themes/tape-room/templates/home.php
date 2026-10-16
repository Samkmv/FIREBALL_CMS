<?php
declare(strict_types=1);

if (!class_exists(\Fireball\ReelPlayer\Controllers\PlayerController::class)) {
    http_response_code(503);
    echo '<section class="tr-card"><h1>Tape Room</h1><p>Музыкальная комната временно недоступна. Включите плагин Tape Room в панели управления.</p></section>';
    return;
}

extract((new \Fireball\ReelPlayer\Controllers\PlayerController())->pageData(), EXTR_SKIP);
require dirname(__DIR__) . '/includes/player-shell.php';
