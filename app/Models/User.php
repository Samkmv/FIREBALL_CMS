<?php

namespace App\Models;

use App\Services\TwoFactorService;
use FBL\File;
use FBL\Pagination;

/**
 * Управляет пользователями, ролями, аватарами и сценариями восстановления доступа.
 */
class User
{
    public const ROLE_CREATOR = 'creator';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_USER = 'user';
    public const CREATOR_ROLE_ID = 1;
    public const ONLINE_WINDOW_SECONDS = 70;

    protected string $usersTable = 'users';
    protected string $passwordResetsTable = 'password_resets';
    protected string $twoFactorRecoveryTokensTable = 'two_factor_recovery_tokens';
    protected string $rolesTable = 'user_roles';
    protected array $roles = [self::ROLE_CREATOR, self::ROLE_ADMIN, self::ROLE_MODERATOR, self::ROLE_USER];
    protected static bool $schemaReady = false;

    /**
     * Создаёт таблицы пользователей, ролей и восстановления пароля, а также обновляет старую схему.
     */
    public function ensureUsersTableExists(): void
    {
        if (self::$schemaReady) {
            return;
        }

        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->usersTable} (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                login VARCHAR(50) NOT NULL,
                email VARCHAR(190) NOT NULL,
                password VARCHAR(255) NOT NULL,
                avatar VARCHAR(255) NULL,
                role VARCHAR(50) NOT NULL DEFAULT 'user',
                two_factor_secret TEXT NULL,
                two_factor_recovery_codes TEXT NULL,
                two_factor_enabled_at DATETIME NULL,
                last_seen_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY login (login),
                UNIQUE KEY email (email),
                KEY role (role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureRolesTableExists();
        $this->ensurePasswordResetsTableExists();
        $this->ensureTwoFactorRecoveryTokensTableExists();
        $this->ensureLoginColumnExists();
        $this->ensureRoleColumnExists();
        $this->ensureAvatarColumnExists();
        $this->ensureTwoFactorColumnsExist();
        $this->ensureLastSeenColumnExists();
        $this->ensureSecurityColumnsExist();
        $this->ensureUserIndexes();
        $this->ensureCreatorUserExists();
        $this->syncRolesFromUsers();
        self::$schemaReady = true;
    }

    /**
     * Ищет пользователя по e-mail.
     */
    public function findByEmail(string $email): array|false
    {
        $this->ensureUsersTableExists();
        return db()->findOne($this->usersTable, $email, 'email');
    }

    /**
     * Ищет пользователя по логину.
     */
    public function findByLogin(string $login): array|false
    {
        $this->ensureUsersTableExists();
        return db()->findOne($this->usersTable, $this->normalizeLogin($login), 'login');
    }

    /**
     * Ищет пользователя по идентификатору.
     */
    public function findById(int $id): array|false
    {
        $this->ensureUsersTableExists();
        return db()->findOne($this->usersTable, $id);
    }

    /**
     * Проверяет, относится ли роль к полному административному доступу.
     */
    public function isAdministrativeRole(?string $role): bool
    {
        return in_array(trim((string)$role), [self::ROLE_CREATOR, self::ROLE_ADMIN], true);
    }

    /**
     * Проверяет, защищён ли пользователь ролью создателя.
     */
    public function isProtectedUser(array|false $user): bool
    {
        return is_array($user) && trim((string)($user['role'] ?? '')) === self::ROLE_CREATOR;
    }

    /**
     * Проверяет, защищена ли системная роль создателя от изменений.
     */
    public function isProtectedRole(array|false $role): bool
    {
        return is_array($role) && trim((string)($role['slug'] ?? '')) === self::ROLE_CREATOR;
    }

    /**
     * Обновляет время последней активности пользователя.
     */
    public function touchPresence(int $id): void
    {
        $this->ensureUsersTableExists();

        if ($id <= 0) {
            return;
        }

        db()->query(
            "UPDATE {$this->usersTable}
             SET last_seen_at = ?
             WHERE id = ?",
            [date('Y-m-d H:i:s'), $id]
        );
    }

    /**
     * Возвращает статус присутствия пользователя для чата.
     */
    public function getPresenceForChat(int $id): array
    {
        $this->ensureUsersTableExists();

        $user = db()->query(
            "SELECT id, last_seen_at
             FROM {$this->usersTable}
             WHERE id = ?
             LIMIT 1",
            [$id]
        )->getOne();

        if (!$user) {
            return [
                'id' => $id,
                'last_seen_at' => null,
                'is_online' => false,
            ];
        }

        return [
            'id' => (int)$user['id'],
            'last_seen_at' => $user['last_seen_at'] ?: null,
            'is_online' => $this->isOnline($user['last_seen_at'] ?? null),
        ];
    }

    /**
     * Определяет, считается ли пользователь онлайн по времени последней активности.
     */
    public function isOnline(?string $lastSeenAt, int $windowSeconds = self::ONLINE_WINDOW_SECONDS): bool
    {
        $lastSeenAt = trim((string)$lastSeenAt);
        if ($lastSeenAt === '') {
            return false;
        }

        $timestamp = strtotime($lastSeenAt);
        if (!$timestamp) {
            return false;
        }

        return (time() - $timestamp) <= $windowSeconds;
    }

    /**
     * Возвращает расширенные данные пользователя для административного редактирования.
     */
    public function findEditableUserById(int $id): array|false
    {
        $this->ensureUsersTableExists();

        return db()->query(
            "SELECT u.id,
                    u.name,
                    u.login,
                    u.email,
                    u.avatar,
                    u.role,
                    u.two_factor_enabled_at,
                    u.last_seen_at,
                    u.created_at,
                    r.name AS role_name,
                    CASE
                        WHEN u.role IN ('creator', 'admin') THEN (
                            SELECT COUNT(*)
                            FROM {$this->usersTable} admins
                            WHERE admins.role IN ('creator', 'admin')
                              AND admins.id != u.id
                        )
                        ELSE 0
                    END AS other_admins_count
             FROM {$this->usersTable} u
             LEFT JOIN {$this->rolesTable} r ON r.slug = u.role
             WHERE u.id = ?
             LIMIT 1",
            [$id]
        )->getOne();
    }

    /**
     * Создаёт нового пользователя и назначает ему начальную роль.
     */
    public function create(array $data): int
    {
        $this->ensureUsersTableExists();
        $role = $this->resolveRoleForNewUser();

        db()->query(
            "INSERT INTO {$this->usersTable} (name, login, email, password, role, created_at)
             VALUES (:name, :login, :email, :password, :role, :created_at)",
            [
                'name' => trim($data['name']),
                'login' => $this->ensureUniqueLogin($this->normalizeLogin((string)($data['login'] ?? '')), null),
                'email' => mb_strtolower(trim($data['email'])),
                'password' => password_hash($data['password'], PASSWORD_DEFAULT),
                'role' => $role,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        return (int)db()->getInsertId();
    }

    /**
     * Создаёт пользователя из административной панели с выбранной ролью.
     */
    public function createFromAdmin(array $data): int
    {
        $this->ensureUsersTableExists();
        $role = trim((string)($data['role'] ?? self::ROLE_USER)) ?: self::ROLE_USER;
        if (!$this->findRoleBySlug($role) || $role === self::ROLE_CREATOR) {
            $role = self::ROLE_USER;
        }

        db()->query(
            "INSERT INTO {$this->usersTable} (name, login, email, password, avatar, role, created_at)
             VALUES (:name, :login, :email, :password, :avatar, :role, :created_at)",
            [
                'name' => trim((string)$data['name']),
                'login' => $this->ensureUniqueLogin($this->normalizeLogin((string)($data['login'] ?? '')), null),
                'email' => mb_strtolower(trim((string)$data['email'])),
                'password' => password_hash((string)$data['password'], PASSWORD_DEFAULT),
                'avatar' => trim((string)($data['avatar'] ?? '')) ?: null,
                'role' => $role,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        return (int)db()->getInsertId();
    }

    /**
     * Возвращает всех пользователей вместе с названиями ролей.
     */
    public function getUsers(): array
    {
        $this->ensureUsersTableExists();

        return db()->query(
            "SELECT u.id, u.name, u.login, u.email, u.avatar, u.role, u.created_at, r.name AS role_name
             FROM {$this->usersTable} u
             LEFT JOIN {$this->rolesTable} r ON r.slug = u.role
             ORDER BY u.id DESC"
        )->get() ?: [];
    }

    /**
     * Возвращает пользователей для административной таблицы с пагинацией.
     */
    public function getPaginatedUsers(array $options = []): array
    {
        $this->ensureUsersTableExists();

        $perPage = max(1, (int)($options['per_page'] ?? 15));
        $search = trim((string)($options['search'] ?? ''));
        $sort = (string)($options['sort'] ?? 'created_at');
        $direction = strtolower((string)($options['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $onlineCutoff = date('Y-m-d H:i:s', time() - self::ONLINE_WINDOW_SECONDS);
        $onlineOrderSql = "CASE WHEN u.last_seen_at IS NOT NULL AND u.last_seen_at >= '{$onlineCutoff}' THEN 1 ELSE 0 END";

        $sortMap = [
            'id' => 'u.id',
            'name' => 'u.name',
            'login' => 'u.login',
            'email' => 'u.email',
            'role' => 'u.role',
            'online' => $onlineOrderSql,
            'created_at' => 'u.created_at',
        ];

        if (!array_key_exists($sort, $sortMap)) {
            $sort = 'created_at';
        }

        $orderBy = $sortMap[$sort];
        $secondaryOrder = $sort === 'online' ? ', u.last_seen_at DESC' : '';
        $where = '';
        $params = [];

        if ($search !== '') {
            $where = "WHERE u.name LIKE ? OR u.login LIKE ? OR u.email LIKE ? OR u.role LIKE ?";
            $searchLike = '%' . $search . '%';
            $params = [$searchLike, $searchLike, $searchLike, $searchLike];
        }

        $total = (int)db()->query(
            "SELECT COUNT(*)
             FROM {$this->usersTable} u
             {$where}",
            $params
        )->getColumn();

        $pagination = new Pagination($total, $perPage);
        $offset = $pagination->getOffset();

        $items = db()->query(
            "SELECT u.id,
                    u.name,
                    u.login,
                    u.email,
                    u.avatar,
                    u.role,
                    u.two_factor_enabled_at,
                    u.last_seen_at,
                    u.created_at,
                    r.name AS role_name,
                    CASE
                        WHEN u.role IN ('creator', 'admin') THEN (
                            SELECT COUNT(*)
                            FROM {$this->usersTable} admins
                            WHERE admins.role IN ('creator', 'admin')
                              AND admins.id != u.id
                        )
                        ELSE 0
                    END AS other_admins_count
             FROM {$this->usersTable} u
             LEFT JOIN {$this->rolesTable} r ON r.slug = u.role
             {$where}
             ORDER BY {$orderBy} {$direction}{$secondaryOrder}, u.id DESC
             LIMIT {$offset}, {$perPage}",
            $params
        )->get() ?: [];

        foreach ($items as &$item) {
            $item['is_online'] = $this->isOnline($item['last_seen_at'] ?? null) ? 1 : 0;
        }
        unset($item);

        return [
            'items' => $items,
            'total' => $total,
            'pagination' => $pagination,
            'search' => $search,
            'sort' => $sort,
            'direction' => strtolower($direction),
            'per_page' => $perPage,
        ];
    }

    /**
     * Обновляет пользователя из административной панели.
     */
    public function updateFromAdmin(int $id, array $data): void
    {
        $this->ensureUsersTableExists();
        $currentUser = $this->findById($id);
        if ($this->isProtectedUser($currentUser)) {
            return;
        }

        $params = [
            'id' => $id,
            'name' => trim((string)$data['name']),
            'login' => $this->ensureUniqueLogin($this->normalizeLogin((string)($data['login'] ?? '')), $id),
            'email' => mb_strtolower(trim((string)$data['email'])),
            'avatar' => trim((string)($data['avatar'] ?? '')) ?: null,
            'role' => trim((string)$data['role']) ?: 'user',
        ];

        $passwordSql = '';
        if (!empty($data['password'])) {
            $passwordSql = ', password = :password';
            $params['password'] = password_hash((string)$data['password'], PASSWORD_DEFAULT);
        }

        db()->query(
            "UPDATE {$this->usersTable}
             SET name = :name,
                 login = :login,
                 email = :email,
                 avatar = :avatar,
                 role = :role
                 {$passwordSql}
             WHERE id = :id",
            $params
        );

        $this->syncUserContent($id, $params['name'], $params['role']);
    }

    /**
     * Обновляет путь к аватару пользователя.
     */
    public function updateAvatar(int $id, ?string $avatar): void
    {
        $this->ensureUsersTableExists();

        db()->query(
            "UPDATE {$this->usersTable}
             SET avatar = ?
             WHERE id = ?",
            [$avatar, $id]
        );
    }

    /**
     * Обновляет данные профиля пользователя из личного кабинета.
     */
    public function updateProfile(int $id, array $data): void
    {
        $this->ensureUsersTableExists();

        $params = [
            'id' => $id,
            'name' => trim((string)$data['name']),
            'login' => $this->ensureUniqueLogin($this->normalizeLogin((string)($data['login'] ?? '')), $id),
            'email' => mb_strtolower(trim((string)$data['email'])),
        ];

        $passwordSql = '';
        if (!empty($data['password'])) {
            $passwordSql = ', password = :password';
            $params['password'] = password_hash((string)$data['password'], PASSWORD_DEFAULT);
        }

        db()->query(
            "UPDATE {$this->usersTable}
             SET name = :name,
                 login = :login,
                 email = :email
                 {$passwordSql}
             WHERE id = :id",
            $params
        );

        $currentUser = $this->findById($id);
        if ($currentUser) {
            $this->syncUserContent($id, (string)$currentUser['name'], (string)($currentUser['role'] ?? 'user'));
        }
    }

    public function hasTwoFactorEnabled(array $user): bool
    {
        return !empty($user['two_factor_enabled_at']) && !empty($user['two_factor_secret']);
    }

    public function enableTwoFactor(int $id, string $secret, array $recoveryCodes): void
    {
        $this->ensureUsersTableExists();
        $twoFactor = new TwoFactorService();
        $recoveryHashes = array_map(
            static fn(string $code): string => $twoFactor->hashRecoveryCode($code),
            $recoveryCodes
        );

        db()->query(
            "UPDATE {$this->usersTable}
             SET two_factor_secret = ?,
                 two_factor_recovery_codes = ?,
                 two_factor_enabled_at = ?
             WHERE id = ?",
            [
                $twoFactor->encryptSecret($secret),
                json_encode($recoveryHashes, JSON_UNESCAPED_SLASHES),
                date('Y-m-d H:i:s'),
                $id,
            ]
        );
    }

    public function disableTwoFactor(int $id): void
    {
        $this->ensureUsersTableExists();
        db()->query(
            "UPDATE {$this->usersTable}
             SET two_factor_secret = NULL,
                 two_factor_recovery_codes = NULL,
                 two_factor_enabled_at = NULL
             WHERE id = ?",
            [$id]
        );
        $this->deleteTwoFactorRecoveryTokensForUser($id);
    }

    public function invalidateSessions(int $id): void
    {
        $this->ensureUsersTableExists();
        db()->query(
            "UPDATE {$this->usersTable}
             SET session_version = COALESCE(session_version, 1) + 1
             WHERE id = ?",
            [$id]
        );
    }

    public function markTwoFactorResetNotice(int $id): void
    {
        $this->ensureUsersTableExists();
        db()->query("UPDATE {$this->usersTable} SET two_factor_reset_notice = 1 WHERE id = ?", [$id]);
    }

    public function clearTwoFactorResetNotice(int $id): void
    {
        $this->ensureUsersTableExists();
        db()->query("UPDATE {$this->usersTable} SET two_factor_reset_notice = 0 WHERE id = ?", [$id]);
    }

    public function verifyTwoFactorCode(array $user, string $code): bool
    {
        if (!$this->hasTwoFactorEnabled($user)) {
            return false;
        }

        $twoFactor = new TwoFactorService();
        $secret = $twoFactor->decryptSecret((string)$user['two_factor_secret']);

        return $secret !== '' && $twoFactor->verifyCode($secret, $code);
    }

    public function consumeRecoveryCode(int $id, string $code): bool
    {
        $this->ensureUsersTableExists();
        $user = $this->findById($id);
        if (!$user || !$this->hasTwoFactorEnabled($user)) {
            return false;
        }

        $twoFactor = new TwoFactorService();
        $candidate = $twoFactor->hashRecoveryCode($code);
        $hashes = json_decode((string)($user['two_factor_recovery_codes'] ?? ''), true);
        if (!is_array($hashes)) {
            return false;
        }

        foreach ($hashes as $index => $hash) {
            if (is_string($hash) && hash_equals($hash, $candidate)) {
                unset($hashes[$index]);
                db()->query(
                    "UPDATE {$this->usersTable}
                     SET two_factor_recovery_codes = ?
                     WHERE id = ?",
                    [json_encode(array_values($hashes), JSON_UNESCAPED_SLASHES), $id]
                );
                return true;
            }
        }

        return false;
    }

    public function createTwoFactorRecoveryToken(int $userId): ?string
    {
        $this->ensureUsersTableExists();
        $user = $this->findById($userId);
        if (!$user || !$this->hasTwoFactorEnabled($user)) {
            return null;
        }

        $this->deleteTwoFactorRecoveryTokensForUser($userId);
        $token = bin2hex(random_bytes(32));

        db()->query(
            "INSERT INTO {$this->twoFactorRecoveryTokensTable}
             (user_id, token_hash, expires_at, used_at, ip_address, user_agent, created_at)
             VALUES (:user_id, :token_hash, :expires_at, :used_at, :ip_address, :user_agent, :created_at)",
            [
                'user_id' => $userId,
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'used_at' => null,
                'ip_address' => client_ip(),
                'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        return $token;
    }

    public function findActiveTwoFactorRecoveryByToken(string $token): array|false
    {
        $this->ensureUsersTableExists();

        return db()->query(
            "SELECT tr.id, tr.user_id, tr.expires_at, tr.used_at, u.name, u.email, u.login
             FROM {$this->twoFactorRecoveryTokensTable} tr
             INNER JOIN {$this->usersTable} u ON u.id = tr.user_id
             WHERE tr.token_hash = ?
               AND tr.used_at IS NULL
               AND tr.expires_at >= ?
             LIMIT 1",
            [hash('sha256', $token), date('Y-m-d H:i:s')]
        )->getOne();
    }

    public function resetTwoFactorByRecoveryToken(string $token): array|false
    {
        $this->ensureUsersTableExists();
        $database = db();
        $database->beginTransaction();

        try {
            $now = date('Y-m-d H:i:s');
            $recovery = $database->query(
                "SELECT tr.id, tr.user_id, u.email, u.name, u.login
                 FROM {$this->twoFactorRecoveryTokensTable} tr
                 INNER JOIN {$this->usersTable} u ON u.id = tr.user_id
                 WHERE tr.token_hash = ?
                   AND tr.used_at IS NULL
                   AND tr.expires_at >= ?
                 LIMIT 1
                 FOR UPDATE",
                [hash('sha256', $token), $now]
            )->getOne();

            if (!$recovery) {
                $database->rollBack();
                return false;
            }

            $database->query(
                "UPDATE {$this->twoFactorRecoveryTokensTable}
                 SET used_at = ?
                 WHERE id = ? AND used_at IS NULL",
                [$now, (int)$recovery['id']]
            );

            if ($database->rowCount() !== 1) {
                $database->rollBack();
                return false;
            }

            $database->query(
                "UPDATE {$this->usersTable}
                 SET two_factor_secret = NULL,
                     two_factor_recovery_codes = NULL,
                     two_factor_enabled_at = NULL,
                     two_factor_reset_notice = 1,
                     session_version = COALESCE(session_version, 1) + 1
                 WHERE id = ?",
                [(int)$recovery['user_id']]
            );
            $database->query(
                "UPDATE {$this->twoFactorRecoveryTokensTable}
                 SET used_at = COALESCE(used_at, ?)
                 WHERE user_id = ?",
                [$now, (int)$recovery['user_id']]
            );
            $database->commit();

            return $recovery;
        } catch (\Throwable $exception) {
            try {
                $database->rollBack();
            } catch (\Throwable) {
            }
            throw $exception;
        }
    }

    /**
     * Проверяет загруженный файл аватара по типу, размеру и содержимому.
     */
    public function validateAvatarFile(File $file): array
    {
        if (!$file->isFile && $file->getError() === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        if (!$file->isFile || $file->getError() !== UPLOAD_ERR_OK) {
            return ['avatar_file' => [$this->translate('auth_profile_avatar_upload_error')]];
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            return ['avatar_file' => [$this->translate('auth_profile_avatar_size_error')]];
        }

        $extension = strtolower($file->getExt());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return ['avatar_file' => [$this->translate('auth_profile_avatar_type_error')]];
        }

        if (!@getimagesize($file->getTmpName())) {
            return ['avatar_file' => [$this->translate('auth_profile_avatar_type_error')]];
        }

        return [];
    }

    /**
     * Сохраняет новый аватар и при необходимости удаляет старый.
     */
    public function storeAvatar(File $file, ?string $currentAvatar = null): string|false|null
    {
        if (!$file->isFile) {
            return $currentAvatar ? ltrim($currentAvatar, '/') : null;
        }

        $savedPath = $file->save('avatars');
        if (!$savedPath) {
            return false;
        }

        $savedPath = ltrim((string)$savedPath, '/');
        if ($currentAvatar && ltrim($currentAvatar, '/') !== $savedPath) {
            $this->removeStoredAvatar($currentAvatar);
        }

        return $savedPath;
    }

    /**
     * Удаляет пользователя с учётом ограничений безопасности.
     */
    public function deleteUser(int $id, ?int $currentUserId = null): ?string
    {
        $this->ensureUsersTableExists();

        $user = $this->findById($id);
        if (!$user) {
            return 'not_found';
        }

        if ($this->isProtectedUser($user)) {
            return 'protected';
        }

        if ($currentUserId !== null && $id === $currentUserId) {
            return 'self';
        }

        if ($this->isAdministrativeRole($user['role'] ?? self::ROLE_USER) && $this->countAdmins($id) === 0) {
            return 'last_admin';
        }

        $database = db();
        $avatarPath = (string)($user['avatar'] ?? '');
        $chatAttachmentPaths = [];

        $database->beginTransaction();

        try {
            $database->query(
                "UPDATE {$this->usersTable}
                 SET session_version = COALESCE(session_version, 1) + 1
                 WHERE id = ?",
                [$id]
            );

            $chatAttachmentPaths = $this->collectUserChatAttachmentPaths($id);
            $this->deleteUserRelatedData($id);

            $database->query("DELETE FROM {$this->usersTable} WHERE id = ?", [$id]);
            $database->commit();
        } catch (\Throwable $exception) {
            try {
                $database->rollBack();
            } catch (\Throwable) {
            }
            throw $exception;
        }

        $this->removeStoredAvatar($avatarPath);
        $this->removeStoredFiles($chatAttachmentPaths);
        Post::clearPublicCache();

        return null;
    }

    /**
     * Валидирует данные регистрации нового пользователя.
     */
    public function validateRegistration(array $data): array
    {
        $this->ensureUsersTableExists();

        $errors = [];
        $name = trim((string)($data['name'] ?? ''));
        $login = $this->normalizeLogin((string)($data['login'] ?? ''));
        $email = mb_strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');
        $passwordConfirmation = (string)($data['password_confirmation'] ?? '');

        if ($name === '') {
            $errors['name'][] = $this->translate('auth_validation_name_required');
        } elseif (mb_strlen($name) < 2) {
            $errors['name'][] = $this->translate('auth_validation_name_length');
        }

        $loginErrors = $this->validateLoginField($login);
        if (!empty($loginErrors)) {
            $errors['login'] = $loginErrors;
        }

        if ($email === '') {
            $errors['email'][] = $this->translate('auth_validation_email_required');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = $this->translate('auth_validation_email_invalid');
        } elseif ($this->findByEmail($email)) {
            $errors['email'][] = $this->translate('auth_validation_email_exists');
        }

        if ($password === '') {
            $errors['password'][] = $this->translate('auth_validation_password_required');
        } elseif (mb_strlen($password) < 8) {
            $errors['password'][] = $this->translate('auth_validation_password_length');
        } elseif (!$this->isStrongPassword($password)) {
            $errors['password'][] = $this->translate('auth_validation_password_strength');
        }

        if ($passwordConfirmation === '') {
            $errors['password_confirmation'][] = $this->translate('auth_validation_password_confirmation_required');
        } elseif ($password !== $passwordConfirmation) {
            $errors['password_confirmation'][] = $this->translate('auth_validation_password_confirmation_match');
        }

        return $errors;
    }

    /**
     * Валидирует данные формы входа.
     */
    public function validateLogin(array $data): array
    {
        $this->ensureUsersTableExists();

        $errors = [];
        $login = $this->normalizeLogin((string)($data['login'] ?? ''));
        $password = (string)($data['password'] ?? '');

        if ($login === '') {
            $errors['login'][] = $this->translate('auth_validation_login_required');
        } elseif (!$this->isValidLogin($login)) {
            $errors['login'][] = $this->translate('auth_validation_login_format');
        }

        if ($password === '') {
            $errors['password'][] = $this->translate('auth_validation_password_required');
        }

        return $errors;
    }

    /**
     * Валидирует данные пользователя при создании из админки.
     */
    public function validateAdminCreate(array $data): array
    {
        $this->ensureUsersTableExists();

        $errors = [];
        $name = trim((string)($data['name'] ?? ''));
        $login = $this->normalizeLogin((string)($data['login'] ?? ''));
        $email = mb_strtolower(trim((string)($data['email'] ?? '')));
        $role = trim((string)($data['role'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $passwordConfirmation = (string)($data['password_confirmation'] ?? '');

        if ($name === '') {
            $errors['name'][] = $this->translate('admin_validation_user_name_required');
        } elseif (mb_strlen($name) < 2) {
            $errors['name'][] = $this->translate('admin_validation_user_name_length');
        }

        $loginErrors = $this->validateLoginField($login, null, true);
        if (!empty($loginErrors)) {
            $errors['login'] = $loginErrors;
        }

        if ($email === '') {
            $errors['email'][] = $this->translate('admin_validation_user_email_required');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = $this->translate('admin_validation_user_email_invalid');
        } elseif ($this->emailExists($email, null)) {
            $errors['email'][] = $this->translate('admin_validation_user_email_exists');
        }

        if ($role === '') {
            $errors['role'][] = $this->translate('admin_validation_role_required');
        } elseif (!$this->findRoleBySlug($role) || $role === self::ROLE_CREATOR) {
            $errors['role'][] = $this->translate('admin_validation_role_invalid');
        }

        if ($password === '') {
            $errors['password'][] = $this->translate('auth_validation_password_required');
        } elseif (mb_strlen($password) < 8) {
            $errors['password'][] = $this->translate('admin_validation_user_password_length');
        } elseif (!$this->isStrongPassword($password)) {
            $errors['password'][] = $this->translate('admin_validation_user_password_strength');
        }

        if ($passwordConfirmation === '') {
            $errors['password_confirmation'][] = $this->translate('auth_validation_password_confirmation_required');
        } elseif ($password !== $passwordConfirmation) {
            $errors['password_confirmation'][] = $this->translate('admin_validation_user_password_confirmation_match');
        }

        return $errors;
    }

    /**
     * Валидирует данные пользователя при редактировании из админки.
     */
    public function validateAdminUpdate(array $data, int $userId): array
    {
        $this->ensureUsersTableExists();

        $errors = [];
        $name = trim((string)($data['name'] ?? ''));
        $login = $this->normalizeLogin((string)($data['login'] ?? ''));
        $email = mb_strtolower(trim((string)($data['email'] ?? '')));
        $role = trim((string)($data['role'] ?? ''));
        $password = (string)($data['password'] ?? '');
        $passwordConfirmation = (string)($data['password_confirmation'] ?? '');

        if ($name === '') {
            $errors['name'][] = $this->translate('admin_validation_user_name_required');
        } elseif (mb_strlen($name) < 2) {
            $errors['name'][] = $this->translate('admin_validation_user_name_length');
        }

        $loginErrors = $this->validateLoginField($login, $userId, true);
        if (!empty($loginErrors)) {
            $errors['login'] = $loginErrors;
        }

        if ($email === '') {
            $errors['email'][] = $this->translate('admin_validation_user_email_required');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = $this->translate('admin_validation_user_email_invalid');
        } elseif ($this->emailExists($email, $userId)) {
            $errors['email'][] = $this->translate('admin_validation_user_email_exists');
        }

        if ($role === '') {
            $errors['role'][] = $this->translate('admin_validation_role_required');
        } elseif (!$this->findRoleBySlug($role)) {
            $errors['role'][] = $this->translate('admin_validation_role_invalid');
        } elseif ($role === self::ROLE_CREATOR && ($this->findById($userId)['role'] ?? self::ROLE_USER) !== self::ROLE_CREATOR) {
            $errors['role'][] = $this->translate('admin_role_creator_assignment_blocked');
        }

        if ($password !== '' && mb_strlen($password) < 8) {
            $errors['password'][] = $this->translate('admin_validation_user_password_length');
        } elseif ($password !== '' && !$this->isStrongPassword($password)) {
            $errors['password'][] = $this->translate('admin_validation_user_password_strength');
        }

        if ($password !== '' && $password !== $passwordConfirmation) {
            $errors['password_confirmation'][] = $this->translate('admin_validation_user_password_confirmation_match');
        }

        $currentUser = $this->findById($userId);
        if ($this->isProtectedUser($currentUser)) {
            $errors['role'][] = $this->translate('admin_users_creator_protected');
        }

        if ($currentUser && $this->isAdministrativeRole($currentUser['role'] ?? self::ROLE_USER) && !$this->isAdministrativeRole($role) && $this->countAdmins($userId) === 0) {
            $errors['role'][] = $this->translate('admin_users_last_admin');
        }

        return $errors;
    }

    /**
     * Валидирует данные обновления пользовательского профиля.
     */
    public function validateProfileUpdate(array $data, int $userId): array
    {
        $this->ensureUsersTableExists();

        $errors = [];
        $name = trim((string)($data['name'] ?? ''));
        $login = $this->normalizeLogin((string)($data['login'] ?? ''));
        $email = mb_strtolower(trim((string)($data['email'] ?? '')));
        $currentPassword = (string)($data['current_password'] ?? '');
        $password = (string)($data['password'] ?? '');
        $passwordConfirmation = (string)($data['password_confirmation'] ?? '');

        if ($name === '') {
            $errors['name'][] = $this->translate('auth_validation_name_required');
        } elseif (mb_strlen($name) < 2) {
            $errors['name'][] = $this->translate('auth_validation_name_length');
        }

        $loginErrors = $this->validateLoginField($login, $userId);
        if (!empty($loginErrors)) {
            $errors['login'] = $loginErrors;
        }

        if ($email === '') {
            $errors['email'][] = $this->translate('auth_validation_email_required');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = $this->translate('auth_validation_email_invalid');
        } elseif ($this->emailExists($email, $userId)) {
            $errors['email'][] = $this->translate('auth_validation_email_exists');
        }

        if ($password !== '' && mb_strlen($password) < 8) {
            $errors['password'][] = $this->translate('auth_validation_password_length');
        } elseif ($password !== '' && !$this->isStrongPassword($password)) {
            $errors['password'][] = $this->translate('auth_validation_password_strength');
        }

        if ($password !== '' && $passwordConfirmation === '') {
            $errors['password_confirmation'][] = $this->translate('auth_validation_password_confirmation_required');
        } elseif ($password !== '' && $password !== $passwordConfirmation) {
            $errors['password_confirmation'][] = $this->translate('auth_validation_password_confirmation_match');
        }

        $currentUser = $this->findById($userId);
        $sensitiveChange = $currentUser
            && (
                $login !== $this->normalizeLogin((string)($currentUser['login'] ?? ''))
                || $email !== mb_strtolower(trim((string)($currentUser['email'] ?? '')))
                || $password !== ''
            );

        if ($sensitiveChange && ($currentPassword === '' || !password_verify($currentPassword, (string)($currentUser['password'] ?? '')))) {
            $errors['current_password'][] = $this->translate('auth_validation_current_password');
        }

        return $errors;
    }

    /**
     * Создаёт токен сброса пароля для пользователя по e-mail.
     */
    public function createPasswordResetToken(string $email): ?string
    {
        $this->ensureUsersTableExists();
        $user = $this->findByEmail($email);
        if (!$user) {
            return null;
        }

        $this->deletePasswordResetTokensForUser((int)$user['id']);

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        db()->query(
            "INSERT INTO {$this->passwordResetsTable}
             (user_id, email, token_hash, expires_at, used_at, ip_address, user_agent, created_at)
             VALUES (:user_id, :email, :token_hash, :expires_at, :used_at, :ip_address, :user_agent, :created_at)",
            [
                'user_id' => (int)$user['id'],
                'email' => mb_strtolower(trim((string)$user['email'])),
                'token_hash' => $tokenHash,
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'used_at' => null,
                'ip_address' => client_ip(),
                'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        return $token;
    }

    /**
     * Ищет активный запрос на сброс пароля по токену.
     */
    public function findActivePasswordResetByToken(string $token): array|false
    {
        $this->ensureUsersTableExists();
        $tokenHash = hash('sha256', $token);

        return db()->query(
            "SELECT pr.id, pr.user_id, pr.email, pr.expires_at, pr.used_at, u.name
             FROM {$this->passwordResetsTable} pr
             INNER JOIN {$this->usersTable} u ON u.id = pr.user_id
             WHERE pr.token_hash = ?
               AND pr.used_at IS NULL
               AND pr.expires_at >= ?
             LIMIT 1",
            [$tokenHash, date('Y-m-d H:i:s')]
        )->getOne();
    }

    /**
     * Сбрасывает пароль пользователя по валидному токену.
     */
    public function resetPasswordByToken(string $token, string $password): bool
    {
        $this->ensureUsersTableExists();
        $database = db();
        $database->beginTransaction();

        try {
            $tokenHash = hash('sha256', $token);
            $now = date('Y-m-d H:i:s');
            $reset = $database->query(
                "SELECT id, user_id
                 FROM {$this->passwordResetsTable}
                 WHERE token_hash = ?
                   AND used_at IS NULL
                   AND expires_at >= ?
                 LIMIT 1
                 FOR UPDATE",
                [$tokenHash, $now]
            )->getOne();

            if (!$reset) {
                $database->rollBack();
                return false;
            }

            $database->query(
                "UPDATE {$this->passwordResetsTable}
                 SET used_at = ?
                 WHERE id = ? AND used_at IS NULL",
                [$now, (int)$reset['id']]
            );

            if ($database->rowCount() !== 1) {
                $database->rollBack();
                return false;
            }

            $database->query(
                "UPDATE {$this->usersTable}
                 SET password = ?,
                     session_version = COALESCE(session_version, 1) + 1
                 WHERE id = ?",
                [password_hash($password, PASSWORD_DEFAULT), (int)$reset['user_id']]
            );
            $database->query(
                "DELETE FROM {$this->passwordResetsTable}
                 WHERE user_id = ?",
                [(int)$reset['user_id']]
            );
            $database->commit();

            return true;
        } catch (\Throwable $exception) {
            try {
                $database->rollBack();
            } catch (\Throwable) {
            }
            throw $exception;
        }
    }

    /**
     * Валидирует форму запроса на восстановление пароля.
     */
    public function validatePasswordResetRequest(array $data): array
    {
        $this->ensureUsersTableExists();

        $errors = [];
        $email = mb_strtolower(trim((string)($data['email'] ?? '')));

        if ($email === '') {
            $errors['reset_email'][] = $this->translate('auth_validation_email_required');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['reset_email'][] = $this->translate('auth_validation_email_invalid');
        }

        return $errors;
    }

    /**
     * Валидирует форму установки нового пароля.
     */
    public function validatePasswordReset(array $data): array
    {
        $errors = [];
        $password = (string)($data['password'] ?? '');
        $passwordConfirmation = (string)($data['password_confirmation'] ?? '');

        if ($password === '') {
            $errors['password'][] = $this->translate('auth_validation_password_required');
        } elseif (mb_strlen($password) < 8) {
            $errors['password'][] = $this->translate('auth_validation_password_length');
        } elseif (!$this->isStrongPassword($password)) {
            $errors['password'][] = $this->translate('auth_validation_password_strength');
        }

        if ($passwordConfirmation === '') {
            $errors['password_confirmation'][] = $this->translate('auth_validation_password_confirmation_required');
        } elseif ($password !== $passwordConfirmation) {
            $errors['password_confirmation'][] = $this->translate('auth_validation_password_confirmation_match');
        }

        return $errors;
    }

    /**
     * Возвращает список ролей с количеством привязанных пользователей.
     */
    public function getRoles(): array
    {
        $this->ensureUsersTableExists();

        return db()->query(
            "SELECT r.id, r.name, r.slug, r.is_system, r.created_at, COUNT(u.id) AS users_count
             FROM {$this->rolesTable} r
             LEFT JOIN {$this->usersTable} u ON u.role = r.slug
             GROUP BY r.id, r.name, r.slug, r.is_system, r.created_at
             ORDER BY r.is_system DESC, r.id ASC"
        )->get() ?: [];
    }

    /**
     * Возвращает роли для административной таблицы с пагинацией.
     */
    public function getPaginatedRoles(array $options = []): array
    {
        $this->ensureUsersTableExists();

        $perPage = max(1, (int)($options['per_page'] ?? 15));
        $search = trim((string)($options['search'] ?? ''));
        $sort = (string)($options['sort'] ?? 'id');
        $direction = strtolower((string)($options['direction'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $sortMap = [
            'id' => 'r.id',
            'name' => 'r.name',
            'slug' => 'r.slug',
            'users_count' => 'users_count',
            'type' => 'r.is_system',
        ];

        $orderBy = $sortMap[$sort] ?? 'r.id';
        $where = '';
        $params = [];

        if ($search !== '') {
            $where = "WHERE r.name LIKE ? OR r.slug LIKE ?";
            $searchLike = '%' . $search . '%';
            $params = [$searchLike, $searchLike];
        }

        $total = (int)db()->query(
            "SELECT COUNT(*)
             FROM {$this->rolesTable} r
             {$where}",
            $params
        )->getColumn();

        $pagination = new Pagination($total, $perPage);
        $offset = $pagination->getOffset();

        $items = db()->query(
            "SELECT r.id, r.name, r.slug, r.is_system, r.created_at, COUNT(u.id) AS users_count
             FROM {$this->rolesTable} r
             LEFT JOIN {$this->usersTable} u ON u.role = r.slug
             {$where}
             GROUP BY r.id, r.name, r.slug, r.is_system, r.created_at
             ORDER BY {$orderBy} {$direction}, r.id ASC
             LIMIT {$offset}, {$perPage}",
            $params
        )->get() ?: [];

        return [
            'items' => $items,
            'total' => $total,
            'pagination' => $pagination,
            'search' => $search,
            'sort' => $sort,
            'direction' => strtolower($direction),
            'per_page' => $perPage,
        ];
    }

    /**
     * Ищет роль по идентификатору.
     */
    public function findRoleById(int $id): array|false
    {
        $this->ensureUsersTableExists();
        return db()->query(
            "SELECT r.id, r.name, r.slug, r.is_system, r.created_at, COUNT(u.id) AS users_count
             FROM {$this->rolesTable} r
             LEFT JOIN {$this->usersTable} u ON u.role = r.slug
             WHERE r.id = ?
             GROUP BY r.id, r.name, r.slug, r.is_system, r.created_at
             LIMIT 1",
            [$id]
        )->getOne();
    }

    /**
     * Ищет роль по slug.
     */
    public function findRoleBySlug(string $slug): array|false
    {
        $this->ensureUsersTableExists();
        return db()->findOne($this->rolesTable, trim($slug), 'slug');
    }

    /**
     * Возвращает отображаемое название роли по её slug.
     */
    public function getRoleLabel(string $slug): ?string
    {
        $role = $this->findRoleBySlug($slug);

        if (!$role) {
            return null;
        }

        return trim((string)($role['name'] ?? '')) ?: null;
    }

    /**
     * Создаёт пользовательскую роль.
     */
    public function createRole(array $data): int
    {
        $this->ensureUsersTableExists();

        db()->query(
            "INSERT INTO {$this->rolesTable} (name, slug, is_system, created_at)
             VALUES (?, ?, 0, ?)",
            [
                trim((string)$data['name']),
                trim((string)$data['slug']),
                date('Y-m-d H:i:s'),
            ]
        );

        return (int)db()->getInsertId();
    }

    /**
     * Обновляет роль и синхронизирует её slug в связанных данных.
     */
    public function updateRole(int $id, array $data): void
    {
        $this->ensureUsersTableExists();

        $role = $this->findRoleById($id);
        if (!$role || $this->isProtectedRole($role)) {
            return;
        }

        $newSlug = (int)($role['is_system'] ?? 0) === 1
            ? (string)$role['slug']
            : trim((string)$data['slug']);

        db()->query(
            "UPDATE {$this->rolesTable}
             SET name = ?, slug = ?
             WHERE id = ?",
            [
                trim((string)$data['name']),
                $newSlug,
                $id,
            ]
        );

        if ($newSlug !== (string)$role['slug']) {
            db()->query("UPDATE {$this->usersTable} SET role = ? WHERE role = ?", [$newSlug, $role['slug']]);
            $this->syncPostsRoleSlug((string)$role['slug'], $newSlug);
        }
    }

    /**
     * Валидирует данные формы роли.
     */
    public function validateRoleData(array $data, ?int $roleId = null): array
    {
        $this->ensureUsersTableExists();

        $errors = [];
        $name = trim((string)($data['name'] ?? ''));
        $slug = trim((string)($data['slug'] ?? ''));
        $role = $roleId ? $this->findRoleById($roleId) : false;

        if ($name === '') {
            $errors['name'][] = $this->translate('admin_validation_role_name_required');
        } elseif (mb_strlen($name) < 2) {
            $errors['name'][] = $this->translate('admin_validation_role_name_length');
        }

        if ($slug === '') {
            $errors['slug'][] = $this->translate('admin_validation_slug_required');
        } elseif (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            $errors['slug'][] = $this->translate('admin_validation_role_slug_format');
        } elseif ($this->roleSlugExists($slug, $roleId)) {
            $errors['slug'][] = $this->translate('admin_validation_role_slug_exists');
        }

        if ($role && (int)($role['is_system'] ?? 0) === 1 && $slug !== (string)$role['slug']) {
            $errors['slug'][] = $this->translate('admin_roles_system_slug_locked');
        }

        if ($this->isProtectedRole($role)) {
            if ($name !== trim((string)($role['name'] ?? ''))) {
                $errors['name'][] = $this->translate('admin_roles_creator_protected');
            }

            if ($slug !== trim((string)($role['slug'] ?? ''))) {
                $errors['slug'][] = $this->translate('admin_roles_creator_protected');
            }
        }

        return $errors;
    }

    /**
     * Удаляет роль, если это разрешено ограничениями системы.
     */
    public function deleteRole(int $id): ?string
    {
        $this->ensureUsersTableExists();

        $role = $this->findRoleById($id);
        if (!$role) {
            return 'not_found';
        }

        if ($this->isProtectedRole($role)) {
            return 'protected';
        }

        if ((int)($role['is_system'] ?? 0) === 1) {
            return 'system';
        }

        $hasUsers = (int)db()->query(
            "SELECT COUNT(*) FROM {$this->usersTable} WHERE role = ?",
            [$role['slug']]
        )->getColumn() > 0;

        if ($hasUsers) {
            return 'assigned';
        }

        db()->query("DELETE FROM {$this->rolesTable} WHERE id = ?", [$id]);

        return null;
    }

    /**
     * Возвращает перевод по ключу для сообщений валидации и интерфейса.
     */
    protected function translate(string $key): string
    {
        return \FBL\Language::get($key);
    }

    /**
     * Создаёт таблицу ролей и системные роли по умолчанию.
     */
    protected function ensureRolesTableExists(): void
    {
        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->rolesTable} (
                id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                slug VARCHAR(50) NOT NULL,
                is_system TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $isSystemExists = (bool)db()->query("SHOW COLUMNS FROM {$this->rolesTable} LIKE 'is_system'")->getColumn();
        if (!$isSystemExists) {
            db()->query("ALTER TABLE {$this->rolesTable} ADD COLUMN is_system TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER slug");
        }

        foreach ($this->roles as $slug) {
            $name = match ($slug) {
                self::ROLE_CREATOR => 'Creator',
                self::ROLE_ADMIN => 'Admin',
                self::ROLE_MODERATOR => 'Moderator',
                default => 'User',
            };
            db()->query(
                "INSERT IGNORE INTO {$this->rolesTable} (name, slug, is_system, created_at) VALUES (?, ?, 1, ?)",
                [$name, $slug, date('Y-m-d H:i:s')]
            );
        }

        db()->query("UPDATE {$this->rolesTable} SET is_system = 1 WHERE slug IN ('creator', 'admin', 'moderator', 'user')");
        $this->ensureCreatorRoleFirst();
    }

    /**
     * Добавляет и заполняет поле логина в старой таблице пользователей.
     */
    protected function ensureLoginColumnExists(): void
    {
        $columnExists = (bool)db()->query("SHOW COLUMNS FROM {$this->usersTable} LIKE 'login'")->getColumn();
        if (!$columnExists) {
            db()->query("ALTER TABLE {$this->usersTable} ADD COLUMN login VARCHAR(50) NULL AFTER name");
        }

        $usersWithoutLogin = db()->query(
            "SELECT id, name, email
             FROM {$this->usersTable}
             WHERE login IS NULL OR login = ''
             ORDER BY id ASC"
        )->get() ?: [];

        foreach ($usersWithoutLogin as $user) {
            $login = $this->generateLoginForUser(
                (int)$user['id'],
                (string)($user['name'] ?? ''),
                (string)($user['email'] ?? '')
            );

            db()->query(
                "UPDATE {$this->usersTable}
                 SET login = ?
                 WHERE id = ?",
                [$login, (int)$user['id']]
            );
        }

        db()->query("ALTER TABLE {$this->usersTable} MODIFY COLUMN login VARCHAR(50) NOT NULL");

        $loginIndexExists = (bool)db()->query("SHOW INDEX FROM {$this->usersTable} WHERE Key_name = 'login'")->getColumn();
        if (!$loginIndexExists) {
            db()->query("ALTER TABLE {$this->usersTable} ADD UNIQUE KEY login (login)");
        }
    }

    protected function ensureUserIndexes(): void
    {
        $this->ensureIndex($this->usersTable, 'role', ['role'], "ALTER TABLE {$this->usersTable} ADD KEY role (role)");
        $this->ensureIndex($this->usersTable, 'role_id', ['role_id'], "ALTER TABLE {$this->usersTable} ADD KEY role_id (role_id)");
    }

    protected function ensureIndex(string $table, string $name, array $columns, string $sql): void
    {
        $exists = (bool)db()->query("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$name])->getOne();
        if ($exists) {
            return;
        }

        foreach ($columns as $column) {
            $columnExists = (bool)db()->query("SHOW COLUMNS FROM {$table} LIKE ?", [$column])->getColumn();
            if (!$columnExists) {
                return;
            }
        }

        db()->query($sql);
    }

    /**
     * Создаёт таблицу токенов восстановления пароля.
     */
    protected function ensurePasswordResetsTableExists(): void
    {
        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->passwordResetsTable} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT(10) UNSIGNED NOT NULL,
                email VARCHAR(190) NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(500) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY token_hash (token_hash),
                KEY user_id (user_id),
                KEY email (email),
                KEY expires_at (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $usedAtExists = (bool)db()->query("SHOW COLUMNS FROM {$this->passwordResetsTable} LIKE 'used_at'")->getColumn();
        if (!$usedAtExists) {
            db()->query("ALTER TABLE {$this->passwordResetsTable} ADD COLUMN used_at DATETIME NULL AFTER expires_at");
        }

        $ipExists = (bool)db()->query("SHOW COLUMNS FROM {$this->passwordResetsTable} LIKE 'ip_address'")->getColumn();
        if (!$ipExists) {
            db()->query("ALTER TABLE {$this->passwordResetsTable} ADD COLUMN ip_address VARCHAR(45) NULL AFTER used_at");
        }

        $userAgentExists = (bool)db()->query("SHOW COLUMNS FROM {$this->passwordResetsTable} LIKE 'user_agent'")->getColumn();
        if (!$userAgentExists) {
            db()->query("ALTER TABLE {$this->passwordResetsTable} ADD COLUMN user_agent VARCHAR(500) NULL AFTER ip_address");
        }
    }

    protected function ensureTwoFactorRecoveryTokensTableExists(): void
    {
        db()->query(
            "CREATE TABLE IF NOT EXISTS {$this->twoFactorRecoveryTokensTable} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id INT(10) UNSIGNED NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(500) NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY token_hash (token_hash),
                KEY user_id (user_id),
                KEY expires_at (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    protected function ensureSecurityColumnsExist(): void
    {
        $sessionVersionExists = (bool)db()->query("SHOW COLUMNS FROM {$this->usersTable} LIKE 'session_version'")->getColumn();
        if (!$sessionVersionExists) {
            db()->query("ALTER TABLE {$this->usersTable} ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER last_seen_at");
        }

        $noticeExists = (bool)db()->query("SHOW COLUMNS FROM {$this->usersTable} LIKE 'two_factor_reset_notice'")->getColumn();
        if (!$noticeExists) {
            db()->query("ALTER TABLE {$this->usersTable} ADD COLUMN two_factor_reset_notice TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER session_version");
        }
    }

    /**
     * Добавляет поле роли в таблицу пользователей и назначает первого администратора.
     */
    protected function ensureRoleColumnExists(): void
    {
        $columnExists = (bool)db()->query("SHOW COLUMNS FROM {$this->usersTable} LIKE 'role'")->getColumn();

        if (!$columnExists) {
            db()->query("ALTER TABLE {$this->usersTable} ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'user' AFTER password");
        }

        db()->query("UPDATE {$this->usersTable} SET role = 'user' WHERE role IS NULL OR role = ''");

        if (!$this->hasAdmin()) {
            db()->query("UPDATE {$this->usersTable} SET role = 'creator' ORDER BY id ASC LIMIT 1");
        }
    }

    /**
     * Добавляет поле аватара в таблицу пользователей.
     */
    protected function ensureAvatarColumnExists(): void
    {
        $columnExists = (bool)db()->query("SHOW COLUMNS FROM {$this->usersTable} LIKE 'avatar'")->getColumn();

        if (!$columnExists) {
            db()->query("ALTER TABLE {$this->usersTable} ADD COLUMN avatar VARCHAR(255) NULL AFTER password");
        }
    }

    protected function ensureTwoFactorColumnsExist(): void
    {
        $columns = [
            'two_factor_secret' => "ALTER TABLE {$this->usersTable} ADD COLUMN two_factor_secret TEXT NULL AFTER role",
            'two_factor_recovery_codes' => "ALTER TABLE {$this->usersTable} ADD COLUMN two_factor_recovery_codes TEXT NULL AFTER two_factor_secret",
            'two_factor_enabled_at' => "ALTER TABLE {$this->usersTable} ADD COLUMN two_factor_enabled_at DATETIME NULL AFTER two_factor_recovery_codes",
        ];

        foreach ($columns as $column => $sql) {
            $exists = (bool)db()->query("SHOW COLUMNS FROM {$this->usersTable} LIKE ?", [$column])->getColumn();
            if (!$exists) {
                db()->query($sql);
            }
        }
    }

    /**
     * Добавляет поле времени последней активности пользователя.
     */
    protected function ensureLastSeenColumnExists(): void
    {
        $columnExists = (bool)db()->query("SHOW COLUMNS FROM {$this->usersTable} LIKE 'last_seen_at'")->getColumn();

        if (!$columnExists) {
            db()->query("ALTER TABLE {$this->usersTable} ADD COLUMN last_seen_at DATETIME NULL AFTER role");
        }
    }

    /**
     * Проверяет, есть ли в системе хотя бы один администратор.
     */
    protected function hasAdmin(): bool
    {
        return (int)db()->query(
            "SELECT COUNT(*) FROM {$this->usersTable} WHERE role IN ('creator', 'admin')"
        )->getColumn() > 0;
    }

    /**
     * Валидирует логин и проверяет его уникальность.
     */
    protected function validateLoginField(string $login, ?int $ignoreId = null, bool $adminContext = false): array
    {
        $requiredKey = $adminContext ? 'admin_validation_user_login_required' : 'auth_validation_login_required';
        $formatKey = $adminContext ? 'admin_validation_user_login_format' : 'auth_validation_login_format';
        $existsKey = $adminContext ? 'admin_validation_user_login_exists' : 'auth_validation_login_exists';
        $reservedKey = $adminContext ? 'admin_validation_user_login_reserved' : 'auth_validation_login_reserved';

        if ($login === '') {
            return [$this->translate($requiredKey)];
        }

        if (!$this->isValidLogin($login)) {
            return [$this->translate($formatKey)];
        }

        if ($this->isReservedLogin($login) && !$this->isCurrentUserLogin($login, $ignoreId)) {
            return [$this->translate($reservedKey)];
        }

        if ($this->loginExists($login, $ignoreId)) {
            return [$this->translate($existsKey)];
        }

        return [];
    }

    /**
     * Нормализует логин в slug-формат и ограничивает длину.
     */
    protected function normalizeLogin(string $login): string
    {
        $login = make_slug($login, '');

        return mb_substr($login, 0, 50);
    }

    /**
     * Проверяет формат и длину логина.
     */
    protected function isValidLogin(string $login): bool
    {
        $length = mb_strlen($login);
        if ($length < 3 || $length > 50) {
            return false;
        }

        return (bool)preg_match('/^[a-z0-9-]+$/', $login);
    }

    /**
     * Проверяет обязательные требования к новому паролю.
     */
    protected function isStrongPassword(string $password): bool
    {
        return (bool)preg_match('/[A-Z]/', $password) && (bool)preg_match('/\d/', $password);
    }

    /**
     * Запрещает системные логины для регистрации и создания пользователей.
     */
    protected function isReservedLogin(string $login): bool
    {
        return in_array(mb_strtolower($login), ['admin', 'administration', 'administrator'], true);
    }

    /**
     * Позволяет сохранить существующий зарезервированный логин без смены.
     */
    protected function isCurrentUserLogin(string $login, ?int $userId): bool
    {
        if (!$userId) {
            return false;
        }

        $user = $this->findById($userId);

        return $user && $this->normalizeLogin((string)($user['login'] ?? '')) === $login;
    }

    /**
     * Генерирует логин для старого пользователя при миграции схемы.
     */
    protected function generateLoginForUser(int $userId, string $name, string $email): string
    {
        $localPart = trim((string)strtok($email, '@'));
        $candidates = [$localPart, $name, 'user-' . $userId];

        foreach ($candidates as $candidate) {
            $candidate = $this->normalizeLogin($candidate);
            if ($candidate !== '') {
                return $this->ensureUniqueLogin($candidate, $userId);
            }
        }

        return 'user-' . $userId;
    }

    /**
     * Подбирает уникальный логин, добавляя числовой суффикс при конфликте.
     */
    protected function ensureUniqueLogin(string $login, ?int $ignoreId = null): string
    {
        $login = $this->normalizeLogin($login);
        if ($login === '') {
            $login = 'user';
        }

        $base = $login;
        $suffix = 2;

        while ($this->loginExists($login, $ignoreId)) {
            $postfix = '-' . $suffix;
            $login = mb_substr($base, 0, 50 - mb_strlen($postfix)) . $postfix;
            $suffix++;
        }

        return $login;
    }

    /**
     * Возвращает роль нового пользователя с учётом наличия администратора в системе.
     */
    protected function resolveRoleForNewUser(): string
    {
        if (!$this->hasAdmin()) {
            return self::ROLE_CREATOR;
        }

        return self::ROLE_USER;
    }

    /**
     * Проверяет, используется ли e-mail другим пользователем.
     */
    protected function emailExists(string $email, ?int $ignoreId = null): bool
    {
        if ($ignoreId) {
            return (int)db()->query(
                "SELECT COUNT(*) FROM {$this->usersTable} WHERE email = ? AND id != ?",
                [$email, $ignoreId]
            )->getColumn() > 0;
        }

        return (int)db()->query("SELECT COUNT(*) FROM {$this->usersTable} WHERE email = ?", [$email])->getColumn() > 0;
    }

    /**
     * Проверяет, занят ли логин другим пользователем.
     */
    protected function loginExists(string $login, ?int $ignoreId = null): bool
    {
        $login = $this->normalizeLogin($login);

        if ($ignoreId) {
            return (int)db()->query(
                "SELECT COUNT(*) FROM {$this->usersTable} WHERE login = ? AND id != ?",
                [$login, $ignoreId]
            )->getColumn() > 0;
        }

        return (int)db()->query("SELECT COUNT(*) FROM {$this->usersTable} WHERE login = ?", [$login])->getColumn() > 0;
    }

    /**
     * Проверяет, занят ли slug другой ролью.
     */
    protected function roleSlugExists(string $slug, ?int $ignoreId = null): bool
    {
        if ($ignoreId) {
            return (int)db()->query(
                "SELECT COUNT(*) FROM {$this->rolesTable} WHERE slug = ? AND id != ?",
                [$slug, $ignoreId]
            )->getColumn() > 0;
        }

        return (int)db()->query("SELECT COUNT(*) FROM {$this->rolesTable} WHERE slug = ?", [$slug])->getColumn() > 0;
    }

    /**
     * Возвращает количество администраторов, при необходимости исключая одного пользователя.
     */
    protected function countAdmins(?int $excludeUserId = null): int
    {
        if ($excludeUserId) {
            return (int)db()->query(
                "SELECT COUNT(*) FROM {$this->usersTable} WHERE role IN ('creator', 'admin') AND id != ?",
                [$excludeUserId]
            )->getColumn();
        }

        return (int)db()->query(
            "SELECT COUNT(*) FROM {$this->usersTable} WHERE role IN ('creator', 'admin')"
        )->getColumn();
    }

    /**
     * Создаёт записи ролей на основе уже существующих пользователей.
     */
    protected function syncRolesFromUsers(): void
    {
        $roles = db()->query(
            "SELECT DISTINCT role FROM {$this->usersTable}
             WHERE role IS NOT NULL AND role != ''"
        )->get() ?: [];

        foreach ($roles as $row) {
            $slug = trim((string)$row['role']);
            if ($slug === '') {
                continue;
            }

            db()->query(
                "INSERT IGNORE INTO {$this->rolesTable} (name, slug, is_system, created_at) VALUES (?, ?, ?, ?)",
                [
                    ucfirst(str_replace('-', ' ', $slug)),
                    $slug,
                    in_array($slug, $this->roles, true) ? 1 : 0,
                    date('Y-m-d H:i:s'),
                ]
            );
        }
    }

    /**
     * Гарантирует системной роли creator первый идентификатор в таблице ролей.
     */
    protected function ensureCreatorRoleFirst(): void
    {
        $creatorRole = db()->query(
            "SELECT id, slug FROM {$this->rolesTable} WHERE slug = ? LIMIT 1",
            [self::ROLE_CREATOR]
        )->getOne();

        if (!$creatorRole || (int)($creatorRole['id'] ?? 0) === self::CREATOR_ROLE_ID) {
            return;
        }

        $firstRole = db()->query(
            "SELECT id, slug FROM {$this->rolesTable} WHERE id = ? LIMIT 1",
            [self::CREATOR_ROLE_ID]
        )->getOne();

        if ($firstRole && trim((string)($firstRole['slug'] ?? '')) !== self::ROLE_CREATOR) {
            $temporaryId = (int)db()->query("SELECT COALESCE(MAX(id), 0) + 1 FROM {$this->rolesTable}")->getColumn();
            db()->query(
                "UPDATE {$this->rolesTable} SET id = ? WHERE id = ?",
                [$temporaryId, self::CREATOR_ROLE_ID]
            );
        }

        db()->query(
            "UPDATE {$this->rolesTable} SET id = ? WHERE slug = ?",
            [self::CREATOR_ROLE_ID, self::ROLE_CREATOR]
        );
    }

    /**
     * Назначает роль creator самому раннему привилегированному пользователю, если её ещё никто не получил.
     */
    protected function ensureCreatorUserExists(): void
    {
        $creatorExists = (int)db()->query(
            "SELECT COUNT(*) FROM {$this->usersTable} WHERE role = ?",
            [self::ROLE_CREATOR]
        )->getColumn() > 0;

        if ($creatorExists) {
            return;
        }

        $candidateId = (int)db()->query(
            "SELECT id
             FROM {$this->usersTable}
             WHERE role IN ('creator', 'admin')
             ORDER BY id ASC
             LIMIT 1"
        )->getColumn();

        if ($candidateId <= 0) {
            $candidateId = (int)db()->query(
                "SELECT id FROM {$this->usersTable} ORDER BY id ASC LIMIT 1"
            )->getColumn();
        }

        if ($candidateId > 0) {
            db()->query(
                "UPDATE {$this->usersTable} SET role = ? WHERE id = ?",
                [self::ROLE_CREATOR, $candidateId]
            );

            $creatorUser = $this->findById($candidateId);
            if ($creatorUser) {
                $this->syncUserContent(
                    $candidateId,
                    trim((string)($creatorUser['name'] ?? '')),
                    self::ROLE_CREATOR
                );
            }
        }
    }

    /**
     * Синхронизирует имя автора и его роль в связанных постах пользователя.
     */
    protected function syncUserContent(int $userId, string $name, string $role): void
    {
        $postsTableExists = (bool)db()->query("SHOW TABLES LIKE 'posts'")->getColumn();
        if (!$postsTableExists) {
            return;
        }

        db()->query(
            "UPDATE posts SET author_name = ?, author_role = ? WHERE author_id = ?",
            [$name, $role, $userId]
        );
        Post::clearPublicCache();
    }

    /**
     * Обновляет slug роли автора в уже созданных постах.
     */
    protected function syncPostsRoleSlug(string $oldSlug, string $newSlug): void
    {
        $postsTableExists = (bool)db()->query("SHOW TABLES LIKE 'posts'")->getColumn();
        if (!$postsTableExists) {
            return;
        }

        db()->query("UPDATE posts SET author_role = ? WHERE author_role = ?", [$newSlug, $oldSlug]);
        Post::clearPublicCache();
    }

    /**
     * Удаляет все токены восстановления пароля для пользователя.
     */
    protected function deletePasswordResetTokensForUser(int $userId): void
    {
        db()->query("DELETE FROM {$this->passwordResetsTable} WHERE user_id = ?", [$userId]);
    }

    protected function deleteTwoFactorRecoveryTokensForUser(int $userId): void
    {
        db()->query("DELETE FROM {$this->twoFactorRecoveryTokensTable} WHERE user_id = ?", [$userId]);
    }

    /**
     * Удаляет или отвязывает данные, связанные с пользователем только через его ID.
     */
    protected function deleteUserRelatedData(int $userId): void
    {
        $this->deletePasswordResetTokensForUser($userId);
        $this->deleteTwoFactorRecoveryTokensForUser($userId);

        if ($this->tableExists('chat_audit_logs')) {
            if ($this->tableExists('chat_messages')) {
                db()->query(
                    "DELETE FROM chat_audit_logs
                     WHERE actor_user_id = ?
                        OR conversation_first_user_id = ?
                        OR conversation_second_user_id = ?
                        OR message_id IN (
                            SELECT id
                            FROM chat_messages
                            WHERE sender_id = ? OR receiver_id = ? OR deleted_by = ?
                        )",
                    [$userId, $userId, $userId, $userId, $userId, $userId]
                );
            } else {
                db()->query(
                    "DELETE FROM chat_audit_logs
                     WHERE actor_user_id = ?
                        OR conversation_first_user_id = ?
                        OR conversation_second_user_id = ?",
                    [$userId, $userId, $userId]
                );
            }
        }

        if ($this->tableExists('chat_messages')) {
            db()->query(
                "DELETE FROM chat_messages
                 WHERE sender_id = ? OR receiver_id = ? OR deleted_by = ?",
                [$userId, $userId, $userId]
            );
        }

        if ($this->tableExists('security_logs')) {
            db()->query(
                "DELETE FROM security_logs
                 WHERE actor_user_id = ? OR target_user_id = ?",
                [$userId, $userId]
            );
        }

        if ($this->tableExists('posts') && $this->columnExists('posts', 'author_id')) {
            db()->query("UPDATE posts SET author_id = NULL WHERE author_id = ?", [$userId]);
        }

        if ($this->tableExists('contact_subjects') && $this->columnExists('contact_subjects', 'responsible_user_id')) {
            db()->query("UPDATE contact_subjects SET responsible_user_id = NULL WHERE responsible_user_id = ?", [$userId]);
        }

        if ($this->tableExists('database_maintenance_logs') && $this->columnExists('database_maintenance_logs', 'user_id')) {
            db()->query("UPDATE database_maintenance_logs SET user_id = NULL WHERE user_id = ?", [$userId]);
        }

        if ($this->tableExists('cms_update_logs') && $this->columnExists('cms_update_logs', 'user_id')) {
            db()->query("UPDATE cms_update_logs SET user_id = NULL WHERE user_id = ?", [$userId]);
        }
    }

    protected function collectUserChatAttachmentPaths(int $userId): array
    {
        if (!$this->tableExists('chat_messages') || !$this->columnExists('chat_messages', 'attachment_path')) {
            return [];
        }

        $rows = db()->query(
            "SELECT attachment_path
             FROM chat_messages
             WHERE (sender_id = ? OR receiver_id = ? OR deleted_by = ?)
               AND attachment_path IS NOT NULL
               AND attachment_path != ''",
            [$userId, $userId, $userId]
        )->get() ?: [];

        return array_values(array_unique(array_filter(array_map(
            static fn(array $row): string => (string)($row['attachment_path'] ?? ''),
            $rows
        ))));
    }

    protected function tableExists(string $table): bool
    {
        $this->assertDatabaseIdentifier($table);

        return (bool)db()->query('SHOW TABLES LIKE ?', [$table])->getColumn();
    }

    protected function columnExists(string $table, string $column): bool
    {
        $this->assertDatabaseIdentifier($table);
        $this->assertDatabaseIdentifier($column);

        return (bool)db()->query("SHOW COLUMNS FROM {$table} LIKE ?", [$column])->getColumn();
    }

    protected function assertDatabaseIdentifier(string $identifier): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException('Invalid database identifier.');
        }
    }

    protected function removeStoredFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $this->removeStoredAvatar((string)$path);
        }
    }

    /**
     * Удаляет сохранённый аватар пользователя, если он находится в управляемых загрузках.
     */
    protected function removeStoredAvatar(?string $path): void
    {
        $path = ltrim(trim((string)$path), '/');
        if (!$this->isManagedUploadPath($path)) {
            return;
        }

        File::remove(WWW . '/' . $path);
    }

    /**
     * Проверяет, принадлежит ли путь каталогу управляемых загрузок.
     */
    protected function isManagedUploadPath(?string $path): bool
    {
        $path = ltrim(trim((string)$path), '/');

        return $path !== '' && str_starts_with($path, 'uploads/');
    }

}
