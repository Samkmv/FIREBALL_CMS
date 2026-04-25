<?php

namespace App\Models;

use FBL\File;
use FBL\Pagination;

/**
 * Управляет пользователями, ролями, аватарами и сценариями восстановления доступа.
 */
class User
{

    protected string $usersTable = 'users';
    protected string $passwordResetsTable = 'password_resets';
    protected string $rolesTable = 'user_roles';
    protected array $roles = ['admin', 'user'];
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
                last_seen_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY login (login),
                UNIQUE KEY email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureRolesTableExists();
        $this->ensurePasswordResetsTableExists();
        $this->ensureLoginColumnExists();
        $this->ensureRoleColumnExists();
        $this->ensureAvatarColumnExists();
        $this->ensureLastSeenColumnExists();
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
    public function isOnline(?string $lastSeenAt, int $windowSeconds = 70): bool
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
                    u.created_at,
                    r.name AS role_name,
                    CASE
                        WHEN u.role = 'admin' THEN (
                            SELECT COUNT(*)
                            FROM {$this->usersTable} admins
                            WHERE admins.role = 'admin'
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

        $sortMap = [
            'id' => 'u.id',
            'name' => 'u.name',
            'login' => 'u.login',
            'email' => 'u.email',
            'role' => 'u.role',
            'created_at' => 'u.created_at',
        ];

        $orderBy = $sortMap[$sort] ?? 'u.created_at';
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
                    u.created_at,
                    r.name AS role_name,
                    CASE
                        WHEN u.role = 'admin' THEN (
                            SELECT COUNT(*)
                            FROM {$this->usersTable} admins
                            WHERE admins.role = 'admin'
                              AND admins.id != u.id
                        )
                        ELSE 0
                    END AS other_admins_count
             FROM {$this->usersTable} u
             LEFT JOIN {$this->rolesTable} r ON r.slug = u.role
             {$where}
             ORDER BY {$orderBy} {$direction}, u.id DESC
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
     * Обновляет пользователя из административной панели.
     */
    public function updateFromAdmin(int $id, array $data): void
    {
        $this->ensureUsersTableExists();

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

        if ($currentUserId !== null && $id === $currentUserId) {
            return 'self';
        }

        if (($user['role'] ?? 'user') === 'admin' && $this->countAdmins($id) === 0) {
            return 'last_admin';
        }

        $this->removeStoredAvatar((string)($user['avatar'] ?? ''));
        db()->query("DELETE FROM {$this->usersTable} WHERE id = ?", [$id]);

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
        }

        if ($password !== '' && mb_strlen($password) < 8) {
            $errors['password'][] = $this->translate('admin_validation_user_password_length');
        }

        if ($password !== '' && $password !== $passwordConfirmation) {
            $errors['password_confirmation'][] = $this->translate('admin_validation_user_password_confirmation_match');
        }

        $currentUser = $this->findById($userId);
        if ($currentUser && ($currentUser['role'] ?? 'user') === 'admin' && $role !== 'admin' && $this->countAdmins($userId) === 0) {
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
        }

        if ($password !== '' && $passwordConfirmation === '') {
            $errors['password_confirmation'][] = $this->translate('auth_validation_password_confirmation_required');
        } elseif ($password !== '' && $password !== $passwordConfirmation) {
            $errors['password_confirmation'][] = $this->translate('auth_validation_password_confirmation_match');
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
             (user_id, email, token_hash, expires_at, used_at, created_at)
             VALUES (:user_id, :email, :token_hash, :expires_at, :used_at, :created_at)",
            [
                'user_id' => (int)$user['id'],
                'email' => mb_strtolower(trim((string)$user['email'])),
                'token_hash' => $tokenHash,
                'expires_at' => date('Y-m-d H:i:s', time() + 3600),
                'used_at' => null,
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
        $reset = $this->findActivePasswordResetByToken($token);
        if (!$reset) {
            return false;
        }

        db()->query(
            "UPDATE {$this->usersTable}
             SET password = ?
             WHERE id = ?",
            [password_hash($password, PASSWORD_DEFAULT), (int)$reset['user_id']]
        );

        db()->query(
            "UPDATE {$this->passwordResetsTable}
             SET used_at = ?
             WHERE id = ?",
            [date('Y-m-d H:i:s'), (int)$reset['id']]
        );

        return true;
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
        if (!$role) {
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
            db()->query(
                "INSERT IGNORE INTO {$this->rolesTable} (name, slug, is_system, created_at) VALUES (?, ?, 1, ?)",
                [ucfirst($slug), $slug, date('Y-m-d H:i:s')]
            );
        }

        db()->query("UPDATE {$this->rolesTable} SET is_system = 1 WHERE slug IN ('admin', 'user')");
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
            db()->query("UPDATE {$this->usersTable} SET role = 'admin' ORDER BY id ASC LIMIT 1");
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
        return (int)db()->query("SELECT COUNT(*) FROM {$this->usersTable} WHERE role = 'admin'")->getColumn() > 0;
    }

    /**
     * Валидирует логин и проверяет его уникальность.
     */
    protected function validateLoginField(string $login, ?int $ignoreId = null, bool $adminContext = false): array
    {
        $requiredKey = $adminContext ? 'admin_validation_user_login_required' : 'auth_validation_login_required';
        $formatKey = $adminContext ? 'admin_validation_user_login_format' : 'auth_validation_login_format';
        $existsKey = $adminContext ? 'admin_validation_user_login_exists' : 'auth_validation_login_exists';

        if ($login === '') {
            return [$this->translate($requiredKey)];
        }

        if (!$this->isValidLogin($login)) {
            return [$this->translate($formatKey)];
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
            return 'admin';
        }

        return 'user';
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
                "SELECT COUNT(*) FROM {$this->usersTable} WHERE role = 'admin' AND id != ?",
                [$excludeUserId]
            )->getColumn();
        }

        return (int)db()->query("SELECT COUNT(*) FROM {$this->usersTable} WHERE role = 'admin'")->getColumn();
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
    }

    /**
     * Удаляет все токены восстановления пароля для пользователя.
     */
    protected function deletePasswordResetTokensForUser(int $userId): void
    {
        db()->query("DELETE FROM {$this->passwordResetsTable} WHERE user_id = ?", [$userId]);
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
