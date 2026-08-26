<?php

require_once dirname(__DIR__) . '/app/Services/ChatMediaStorage.php';
if (!defined('CHAT_ENCRYPTION_KEY')) {
    define('CHAT_ENCRYPTION_KEY', 'chat-message-unit-test-key');
}
require_once dirname(__DIR__) . '/app/Services/ChatCipher.php';
require_once dirname(__DIR__) . '/app/Models/ChatMessage.php';

use App\Models\ChatMessage;
use App\Services\ChatMediaStorage;

if (!function_exists('get_role_rank')) {
    function get_role_rank(?string $role = null): int
    {
        return [
            'user' => 10,
            'moderator' => 20,
            'admin' => 30,
            'creator' => 40,
        ][(string)($role ?? 'user')] ?? 0;
    }
}

final class ChatMediaFakeDatabase
{
    public bool $transaction = false;
    public int $insertId = 0;
    public int $schemaQueriesInsideTransaction = 0;
    private mixed $column = true;

    public function query(string $sql, array $params = []): self
    {
        $normalized = strtoupper(ltrim($sql));
        if ($this->transaction && (str_starts_with($normalized, 'CREATE ') || str_starts_with($normalized, 'ALTER '))) {
            $this->schemaQueriesInsideTransaction++;
            $this->transaction = false;
        }
        if (str_starts_with($normalized, 'INSERT ')) {
            $this->insertId++;
        }
        $this->column = true;
        return $this;
    }

    public function getColumn(): mixed
    {
        return $this->column;
    }

    public function getInsertId(): int
    {
        return $this->insertId;
    }

    public function beginTransaction(): bool
    {
        $this->transaction = true;
        return true;
    }

    public function commit(): bool
    {
        if (!$this->transaction) {
            throw new RuntimeException('There is no active transaction.');
        }
        $this->transaction = false;
        return true;
    }

    public function rollBack(): bool
    {
        $this->transaction = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->transaction;
    }
}

$chatMediaFakeDatabase = new ChatMediaFakeDatabase();

function db(): ChatMediaFakeDatabase
{
    global $chatMediaFakeDatabase;
    return $chatMediaFakeDatabase;
}

function chatMediaAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$testRoot = sys_get_temp_dir() . '/fireball-chat-media-test-' . bin2hex(random_bytes(6));
$sourcePath = $testRoot . '/source.bin';
@mkdir($testRoot, 0700, true);

try {
    $marker = 'FIREBALL-CHAT-PLAINTEXT-MARKER';
    $plainText = random_bytes(1024 * 1024 - 9)
        . $marker
        . random_bytes(1024 * 1024 + 12345);
    file_put_contents($sourcePath, $plainText);

    $storage = new ChatMediaStorage($testRoot . '/encrypted', 'unit-test-secret-that-is-not-used-in-production');
    $relativePath = $storage->store($sourcePath);
    $encryptedPath = $testRoot . '/encrypted/' . basename($relativePath);

    chatMediaAssert(ChatMediaStorage::isProtectedPath($relativePath), 'Protected media path format is invalid.');
    chatMediaAssert(is_file($encryptedPath), 'Encrypted media file was not created.');
    chatMediaAssert(strpos((string)file_get_contents($encryptedPath), $marker) === false, 'Plaintext leaked into encrypted storage.');
    chatMediaAssert($storage->readRange($relativePath) === $plainText, 'Full encrypted media round-trip failed.');

    $rangeStart = 1024 * 1024 - 64;
    $rangeEnd = 1024 * 1024 + 96;
    chatMediaAssert(
        $storage->readRange($relativePath, $rangeStart, $rangeEnd) === substr($plainText, $rangeStart, $rangeEnd - $rangeStart + 1),
        'Cross-chunk byte range decryption failed.'
    );

    $handle = fopen($encryptedPath, 'c+b');
    fseek($handle, -1, SEEK_END);
    $lastByte = fread($handle, 1);
    fseek($handle, -1, SEEK_END);
    fwrite($handle, chr(ord($lastByte) ^ 0x01));
    fclose($handle);

    $tamperDetected = false;
    try {
        $storage->readRange($relativePath);
    } catch (RuntimeException) {
        $tamperDetected = true;
    }
    chatMediaAssert($tamperDetected, 'Authenticated encryption did not detect a modified file.');

    $storage->delete($relativePath);
    chatMediaAssert(!is_file($encryptedPath), 'Protected media deletion failed.');

    $projectRoot = dirname(__DIR__);
    $routes = (string)file_get_contents($projectRoot . '/config/routes.php');
    $view = (string)file_get_contents($projectRoot . '/app/Views/themes/default/chat/index.php');
    $javascript = (string)file_get_contents($projectRoot . '/public/assets/default/js/chat.js');
    $controller = (string)file_get_contents($projectRoot . '/app/Controllers/ChatController.php');
    $model = (string)file_get_contents($projectRoot . '/app/Models/ChatMessage.php');
    $helpers = (string)file_get_contents($projectRoot . '/helpers/helpers.php');
    $publicEntry = (string)file_get_contents($projectRoot . '/public/index.php');

    chatMediaAssert(str_contains($routes, '/chat/media/(?P<id>\\d+)/?'), 'Authenticated chat media route is missing.');
    chatMediaAssert(str_contains($view, 'data-chat-record-voice'), 'Voice recording control is missing.');
    chatMediaAssert(str_contains($view, 'data-chat-message-input'), 'Auto-growing chat textarea is missing.');
    chatMediaAssert(str_contains($javascript, 'navigator.mediaDevices.getUserMedia'), 'Voice recorder implementation is missing.');
    chatMediaAssert(str_contains($javascript, 'data-chat-voice-player'), 'Messenger-style voice player is missing.');
    chatMediaAssert(str_contains($javascript, 'resizeMessageInput'), 'Chat textarea auto-resize implementation is missing.');
    chatMediaAssert(str_contains($javascript, "event.key !== 'Enter' || event.shiftKey"), 'Chat textarea keyboard behavior is missing.');
    chatMediaAssert(str_contains($javascript, 'messagesRequestId'), 'Stale chat request protection is missing.');
    chatMediaAssert(str_contains($javascript, 'data-chat-confirm-reason'), 'Audit reason input wiring is missing.');
    chatMediaAssert(str_contains($javascript, 'audit-deleted-count-text'), 'Deleted message count is not rendered in chat audit.');
    chatMediaAssert(!str_contains($controller, "->save('chat')"), 'Chat controller still writes new attachments to public uploads.');
    chatMediaAssert(str_contains($controller, "get_route_param('id', 0)"), 'Protected media endpoint does not read its route id.');
    chatMediaAssert(str_contains($controller, "'conversation_user_ids' => [\$currentUserId, \$contactId]"), 'Message deletion is not constrained to the active conversation.');
    chatMediaAssert(str_contains($model, "'action' => 'clear_conversation'"), 'Conversation clear audit event is missing.');
    chatMediaAssert(str_contains($model, "'suppress_audit' => true"), 'Conversation clear still creates duplicate per-message audit events.');
    chatMediaAssert(str_contains($model, "'deleted_count' => (int)(\$result['deleted_count'] ?? 0)"), 'Conversation clear count is missing from audit details.');
    chatMediaAssert(str_contains($helpers, "return has_role_level('admin');"), 'Chat audit is not available to administrators who can clear conversations.');
    chatMediaAssert(str_contains($publicEntry, 'microphone=(self)'), 'Same-origin microphone access is blocked by Permissions-Policy.');

    $chatMessages = new ChatMessage();
    chatMediaAssert($chatMessages->getPermissionsForRole('admin')['can_view_audit'] === true, 'Administrators cannot view chat audit.');
    chatMediaAssert($chatMessages->getPermissionsForRole('moderator')['can_view_audit'] === false, 'Moderators unexpectedly gained chat audit access.');
    chatMediaAssert($chatMessages->getPermissionsForRole('creator')['can_delete_audit'] === true, 'Creator cannot delete chat audit logs.');
    chatMediaAssert($chatMessages->getPermissionsForRole('admin')['can_delete_audit'] === false, 'Administrator unexpectedly can delete chat audit logs.');
    $chatMessages->ensureTableExists();
    $chatMediaFakeDatabase->beginTransaction();
    $chatMessages->create(10, 20, 'transaction check');
    $chatMediaFakeDatabase->commit();
    chatMediaAssert(
        $chatMediaFakeDatabase->schemaQueriesInsideTransaction === 0,
        'Chat schema checks implicitly committed a message transaction.'
    );

    echo "Chat media unit checks passed.\n";
} finally {
    if (is_file($sourcePath)) {
        @unlink($sourcePath);
    }
    $encryptedDirectory = $testRoot . '/encrypted';
    if (is_dir($encryptedDirectory)) {
        foreach (scandir($encryptedDirectory) ?: [] as $file) {
            if ($file !== '.' && $file !== '..') {
                @unlink($encryptedDirectory . '/' . $file);
            }
        }
        @rmdir($encryptedDirectory);
    }
    @rmdir($testRoot);
}
