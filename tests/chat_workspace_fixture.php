<?php
/** CLI-only disposable conversation for browser QA; recipients are disabled test accounts. */
if (PHP_SAPI !== 'cli') exit;
define('FIREBALL_CLI', true);
require dirname(__DIR__) . '/config/config.php';
require ROOT . '/vendor/autoload.php';
require HELPERS . '/helpers.php';
$app = new FBL\Application(false); $app->db = new FBL\Database();
$file = ROOT . '/tmp/chat-workspace-fixture.json';
$mode = $argv[1] ?? '';
if ($mode === 'create') {
    if (is_file($file)) throw new RuntimeException('Clean up previous fixture first.');
    $owner = (int)db()->query("SELECT id FROM users WHERE login = 'agent'")->getColumn();
    if (!$owner) throw new RuntimeException('Local agent test login is missing.');
    $token = bin2hex(random_bytes(5));
    $users = []; $paths = [];
    db()->beginTransaction();
    try {
        foreach (['Мария QA', 'Алексей QA', 'Новый участник QA'] as $i => $name) {
            $login = 'qa-chat-' . $token . '-' . $i;
            db()->query('INSERT INTO users (name, login, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$name, $login, $login . '@example.test', 'disabled-test-account', 'user', date('Y-m-d H:i:s')]);
            $users[] = (int)db()->getInsertId();
        }
        $groups = new App\Services\GroupChatService();
        $id = $groups->createGroup($owner, 'Тест чата · QA ' . $token, [$users[0], $users[1]]);
        for ($i = 1; $i <= 105; $i++) {
            $groups->createMessage($id, $i % 3 === 0 ? $owner : $users[$i % 2], $i === 1 ? 'Архивный маяк: поиск всей истории работает.' : ($i === 105 ? 'Проверяем групповой чат, файлы и ответы 👋' : 'Тестовое сообщение ' . $i));
        }
        $source = tempnam(sys_get_temp_dir(), 'chat-qa-');
        file_put_contents($source, "QA attachment\nOnly disposable test data.\n");
        $paths[] = $path = (new App\Services\ChatMediaStorage())->store($source); unlink($source);
        $groups->createMessage($id, $users[0], 'Тестовый документ', [], ['path' => $path, 'name' => 'qa-document.txt', 'type' => 'text/plain', 'size' => 40]);
        db()->commit();
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0775, true);
        file_put_contents($file, json_encode(['conversation_id' => $id, 'users' => $users, 'token' => $token], JSON_PRETTY_PRINT));
        echo 'http://localhost:8888/chat/group?conversation_id=' . $id . "\n";
    } catch (Throwable $error) {
        if (db()->inTransaction()) db()->rollBack();
        foreach ($paths as $path) (new App\Services\ChatMediaStorage())->delete($path);
        throw $error;
    }
} elseif ($mode === 'cleanup') {
    if (!is_file($file)) { echo "No fixture.\n"; exit; }
    $fixture = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    $id = (int)$fixture['conversation_id'];
    $group = db()->query('SELECT title FROM chat_conversations WHERE id = ?', [$id])->getColumn();
    if (!str_contains((string)$group, $fixture['token'])) throw new RuntimeException('Fixture identity mismatch; refusing cleanup.');
    $paths = db()->query('SELECT attachment_path FROM chat_messages WHERE conversation_id = ? AND attachment_path IS NOT NULL', [$id])->get() ?: [];
    $avatar = db()->query('SELECT avatar_path FROM chat_conversations WHERE id = ?', [$id])->getColumn();
    if ($avatar) $paths[] = ['attachment_path' => $avatar];
    db()->beginTransaction();
    try {
        foreach (['chat_reactions', 'chat_receipts', 'chat_attachments'] as $table) db()->query("DELETE t FROM {$table} t JOIN chat_messages m ON m.id = t.message_id WHERE m.conversation_id = ?", [$id]);
        foreach (['chat_messages', 'chat_members', 'chat_typing_states'] as $table) db()->query("DELETE FROM {$table} WHERE conversation_id = ?", [$id]);
        db()->query('DELETE FROM chat_conversations WHERE id = ?', [$id]);
        foreach ($fixture['users'] as $user) db()->query("DELETE FROM users WHERE id = ? AND login LIKE ?", [(int)$user, 'qa-chat-' . $fixture['token'] . '-%']);
        db()->query('DELETE FROM notifications WHERE action_url = ?', ['/chat/group?conversation_id=' . $id]);
        db()->commit();
        foreach ($paths as $path) (new App\Services\ChatMediaStorage())->delete($path['attachment_path']);
        unlink($file); echo "Disposable chat fixture removed.\n";
    } catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
} else {
    echo "Usage: php tests/chat_workspace_fixture.php create|cleanup\n";
}
