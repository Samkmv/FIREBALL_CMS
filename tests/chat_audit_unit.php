<?php

namespace {
    if (!defined('CHAT_ENCRYPTION_KEY')) {
        define('CHAT_ENCRYPTION_KEY', 'chat-audit-unit-test-key');
    }

    require_once dirname(__DIR__) . '/app/Services/ChatCipher.php';

    use App\Services\ChatCipher;

    function get_role_rank(?string $role = null): int
    {
        return [
            'user' => 10,
            'moderator' => 20,
            'admin' => 30,
            'creator' => 40,
        ][(string)($role ?? 'user')] ?? 0;
    }

    function return_translation(string $key): string
    {
        return $key;
    }

    function base_url(string $path = ''): string
    {
        return 'http://localhost' . $path;
    }

    final class ChatAuditFakeDatabase
    {
        public array $messages = [];
        public array $auditRows = [];
        private array $result = [];
        private mixed $column = true;
        private int $affectedRows = 0;
        private bool $transaction = false;

        public function seed(array $messages): void
        {
            $this->messages = [];
            foreach ($messages as $message) {
                $message['deleted_at'] = $message['deleted_at'] ?? null;
                $message['message_ciphertext'] = $message['message_ciphertext'] ?? ChatCipher::encrypt((string)($message['message'] ?? 'test'));
                $message['attachment_path'] = $message['attachment_path'] ?? null;
                $message['attachment_name'] = $message['attachment_name'] ?? null;
                $message['attachment_type'] = $message['attachment_type'] ?? null;
                $message['attachment_size'] = $message['attachment_size'] ?? null;
                $message['sender_ip'] = $message['sender_ip'] ?? '127.0.0.1';
                $message['sender_user_agent'] = $message['sender_user_agent'] ?? 'Unit test';
                $this->messages[(int)$message['id']] = $message;
            }
            $this->auditRows = [];
        }

        public function query(string $sql, array $params = []): self
        {
            $normalized = preg_replace('/\s+/', ' ', strtoupper(trim($sql))) ?: '';
            $this->result = [];
            $this->column = true;
            $this->affectedRows = 0;

            if (str_starts_with($normalized, 'SELECT ID FROM CHAT_MESSAGES')) {
                $actorId = (int)($params['actor_id'] ?? 0);
                $contactId = (int)($params['contact_id'] ?? 0);
                foreach ($this->messages as $message) {
                    $isConversation = (
                        ((int)$message['sender_id'] === $actorId && (int)$message['receiver_id'] === $contactId)
                        || ((int)$message['sender_id'] === $contactId && (int)$message['receiver_id'] === $actorId)
                    );
                    if ($message['deleted_at'] === null && $isConversation) {
                        $this->result[] = ['id' => (int)$message['id']];
                    }
                }
                return $this;
            }

            if (str_starts_with($normalized, 'SELECT ID, SENDER_ID, RECEIVER_ID')) {
                preg_match('/ID IN \(([^)]+)\)/', $normalized, $matches);
                $idCount = isset($matches[1]) ? substr_count($matches[1], '?') : count($params);
                $ids = array_map('intval', array_slice(array_values($params), 0, $idCount));
                $constraint = array_map('intval', array_slice(array_values($params), $idCount));

                foreach ($ids as $id) {
                    $message = $this->messages[$id] ?? null;
                    if (!$message || $message['deleted_at'] !== null) {
                        continue;
                    }
                    if (count($constraint) === 4) {
                        $matchesConversation = (
                            ((int)$message['sender_id'] === $constraint[0] && (int)$message['receiver_id'] === $constraint[1])
                            || ((int)$message['sender_id'] === $constraint[2] && (int)$message['receiver_id'] === $constraint[3])
                        );
                        if (!$matchesConversation) {
                            continue;
                        }
                    }
                    $this->result[] = $message;
                }
                return $this;
            }

            if (str_starts_with($normalized, 'UPDATE CHAT_MESSAGES')) {
                $values = array_values($params);
                $messageId = (int)end($values);
                if (isset($this->messages[$messageId]) && $this->messages[$messageId]['deleted_at'] === null) {
                    $this->messages[$messageId]['deleted_at'] = (string)$values[0];
                    $this->messages[$messageId]['attachment_path'] = null;
                    $this->messages[$messageId]['attachment_name'] = null;
                    $this->messages[$messageId]['attachment_type'] = null;
                    $this->messages[$messageId]['attachment_size'] = null;
                    $this->affectedRows = 1;
                }
                return $this;
            }

            if (str_starts_with($normalized, 'INSERT INTO CHAT_AUDIT_LOGS')) {
                $this->auditRows[] = $params;
                $this->affectedRows = 1;
                return $this;
            }

            return $this;
        }

        public function get(): array
        {
            return $this->result;
        }

        public function getColumn(): mixed
        {
            return $this->column;
        }

        public function rowCount(): int
        {
            return $this->affectedRows;
        }

        public function beginTransaction(): bool
        {
            $this->transaction = true;
            return true;
        }

        public function commit(): bool
        {
            if (!$this->transaction) {
                throw new \RuntimeException('No active transaction.');
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

    $chatAuditDatabase = new ChatAuditFakeDatabase();

    function db(): ChatAuditFakeDatabase
    {
        global $chatAuditDatabase;
        return $chatAuditDatabase;
    }

    function chatAuditAssert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }
}

namespace App\Models {
    final class User
    {
        public function findById(int $id): ?array
        {
            return $id > 0 ? ['id' => $id, 'role' => 'admin'] : null;
        }
    }
}

namespace {
    require_once dirname(__DIR__) . '/app/Models/ChatMessage.php';

    use App\Models\ChatMessage;

    $chatMessages = new ChatMessage();

    db()->seed([
        ['id' => 1, 'sender_id' => 10, 'receiver_id' => 20, 'message' => 'first'],
        ['id' => 2, 'sender_id' => 20, 'receiver_id' => 10, 'message' => 'second'],
    ]);
    $clearResult = $chatMessages->clearConversation(10, 20, [
        'reason' => 'unit clear',
        'ip' => '127.0.0.1',
        'user_agent' => 'Unit test',
    ]);

    chatAuditAssert($clearResult['deleted_count'] === 2, 'Conversation clear count is incorrect.');
    chatAuditAssert(count(db()->auditRows) === 1, 'Conversation clear produced duplicate audit events.');
    chatAuditAssert(db()->auditRows[0]['action'] === 'clear_conversation', 'Conversation clear audit action is incorrect.');
    $clearDetails = json_decode((string)db()->auditRows[0]['details_json'], true);
    chatAuditAssert(($clearDetails['deleted_count'] ?? null) === 2, 'Conversation clear audit count is missing.');
    chatAuditAssert(($clearDetails['reason'] ?? '') === 'unit clear', 'Conversation clear audit reason is missing.');
    chatAuditAssert(!db()->inTransaction(), 'Conversation clear left a transaction open.');

    db()->seed([
        ['id' => 3, 'sender_id' => 10, 'receiver_id' => 99, 'message' => 'foreign conversation'],
    ]);
    $crossConversationBlocked = false;
    try {
        $chatMessages->softDeleteMessages([3], 10, [
            'conversation_user_ids' => [10, 20],
        ]);
    } catch (\RuntimeException $exception) {
        $crossConversationBlocked = $exception->getMessage() === 'chat_access_denied';
    }
    chatAuditAssert($crossConversationBlocked, 'Cross-conversation message deletion was not blocked.');
    chatAuditAssert(db()->messages[3]['deleted_at'] === null, 'Foreign conversation message was modified.');
    chatAuditAssert(db()->auditRows === [], 'Blocked cross-conversation deletion created an audit event.');

    db()->seed([
        ['id' => 4, 'sender_id' => 10, 'receiver_id' => 20, 'message' => 'bulk one'],
        ['id' => 5, 'sender_id' => 20, 'receiver_id' => 10, 'message' => 'bulk two'],
    ]);
    $bulkResult = $chatMessages->softDeleteMessages([4, 5], 10, [
        'conversation_user_ids' => [10, 20],
        'reason' => 'unit bulk',
    ]);
    chatAuditAssert($bulkResult['deleted_count'] === 2, 'Bulk deletion count is incorrect.');
    chatAuditAssert(count(db()->auditRows) === 1, 'Bulk deletion should create one aggregate audit event.');
    chatAuditAssert(db()->auditRows[0]['action'] === 'bulk_delete', 'Bulk deletion audit action is incorrect.');

    echo "Chat audit unit checks passed.\n";
}
