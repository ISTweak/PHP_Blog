<?php
declare(strict_types=1);

/**
 * ログイン状態と権限の管理
 *
 * ロール: admin（管理者・ローカルアカウント） > editor（編集者） > author（投稿者）
 * AD ユーザーは初回ログイン時に投稿者として自動登録されます。
 */
final class Auth
{
    public const ROLES = ['admin' => 3, 'editor' => 2, 'author' => 1];

    private const MAX_FAIL_PER_USER = 5;
    private const MAX_FAIL_PER_IP = 20;
    private const LOCK_SECONDS = 900;

    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;

        $id = $_SESSION['uid'] ?? null;
        if (!$id) {
            return null;
        }
        $lifetime = (int) App::config('session_lifetime', 28800);
        if (time() - (int) ($_SESSION['last_seen'] ?? 0) > $lifetime) {
            self::logout();
            return null;
        }
        $user = db_one('SELECT * FROM users WHERE id = ? AND is_active = 1', [(int) $id]);
        // 一般公開サイトでは管理者（ローカルアカウント）以外ログイン不可
        if (!$user || (!App::isPrivate() && $user['auth_type'] !== 'local')) {
            self::logout();
            return null;
        }
        $_SESSION['last_seen'] = time();
        return self::$user = $user;
    }

    public static function atLeast(string $role): bool
    {
        $user = self::user();
        return $user !== null && (self::ROLES[$user['role']] ?? 0) >= (self::ROLES[$role] ?? PHP_INT_MAX);
    }

    public static function canEditPost(array $post): bool
    {
        $user = self::user();
        return $user !== null && (self::atLeast('editor') || (int) $post['author_id'] === (int) $user['id']);
    }

    public static function canDeleteMedia(array $media): bool
    {
        $user = self::user();
        return $user !== null && (self::atLeast('editor') || (int) $media['uploaded_by'] === (int) $user['id']);
    }

    /** ログインを試みる。成功時 null、失敗時はエラーメッセージ */
    public static function attempt(string $username, string $password): ?string
    {
        $username = trim($username);
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if ($username === '' || $password === '') {
            return 'ユーザー名とパスワードを入力してください。';
        }
        if (self::isLocked($username, $ip)) {
            return 'ログインの失敗が続いたため一時的にロックしています。15 分ほど待ってから再度お試しください。';
        }
        $invalid = 'ユーザー名またはパスワードが正しくありません。';

        // 1. ローカルアカウント（管理者）
        $local = db_one("SELECT * FROM users WHERE auth_type = 'local' AND username = ?", [$username]);
        if ($local) {
            if ((int) $local['is_active'] === 1 && $local['password_hash']
                && password_verify($password, (string) $local['password_hash'])) {
                if (password_needs_rehash((string) $local['password_hash'], PASSWORD_DEFAULT)) {
                    db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $local['id']]);
                }
                self::login($local, $username);
                return null;
            }
            self::recordFailure($username, $ip);
            return $invalid;
        }

        // 2. AD アカウント（会員限定サイトのみ）
        if (!App::ldapEnabled()) {
            self::recordFailure($username, $ip);
            return $invalid;
        }
        $name = Ldap::normalizeUsername($username);
        if ($name === null) {
            self::recordFailure($username, $ip);
            return $invalid;
        }
        try {
            $info = Ldap::authenticate($name, $password);
        } catch (RuntimeException $e) {
            error_log('LDAP error: ' . $e->getMessage());
            return 'AD サーバーとの通信に失敗しました。時間をおいて再度お試しください。';
        }
        if ($info === null) {
            self::recordFailure($username, $ip);
            return $invalid;
        }

        $user = db_one("SELECT * FROM users WHERE auth_type = 'ldap' AND username = ?", [$info['username']]);
        if ($user) {
            if ((int) $user['is_active'] !== 1) {
                return 'このアカウントは無効化されています。管理者にお問い合わせください。';
            }
            db_exec('UPDATE users SET display_name = ?, email = ? WHERE id = ?', [$info['display_name'], $info['email'], $user['id']]);
        } else {
            db_exec(
                "INSERT INTO users (username, display_name, email, role, auth_type, is_active, created_at)
                 VALUES (?, ?, ?, 'author', 'ldap', 1, ?)",
                [$info['username'], $info['display_name'], $info['email'], now()]
            );
            $user = db_one('SELECT * FROM users WHERE id = ?', [(int) db()->lastInsertId()]);
        }
        self::login($user, $username);
        return null;
    }

    public static function login(array $user, string $attemptName = ''): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['last_seen'] = time();
        unset($_SESSION['_csrf']);
        db_exec('UPDATE users SET last_login_at = ? WHERE id = ?', [now(), $user['id']]);
        if ($attemptName !== '') {
            db_exec('DELETE FROM login_attempts WHERE username = ?', [mb_strtolower($attemptName)]);
        }
        self::$user = $user;
        self::$loaded = true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::$user = null;
        self::$loaded = true;
    }

    private static function isLocked(string $username, string $ip): bool
    {
        $since = time() - self::LOCK_SECONDS;
        db_exec('DELETE FROM login_attempts WHERE attempted_at < ?', [$since]);
        $byUser = (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE username = ? AND attempted_at >= ?', [mb_strtolower($username), $since]);
        $byIp = (int) db_val('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at >= ?', [$ip, $since]);
        return $byUser >= self::MAX_FAIL_PER_USER || $byIp >= self::MAX_FAIL_PER_IP;
    }

    private static function recordFailure(string $username, string $ip): void
    {
        db_exec('INSERT INTO login_attempts (username, ip, attempted_at) VALUES (?, ?, ?)', [mb_strtolower($username), $ip, time()]);
    }
}
