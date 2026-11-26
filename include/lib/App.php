<?php
declare(strict_types=1);

/**
 * 設定・DB 接続・サイト設定値を保持するアプリケーションコンテナ
 */
final class App
{
    /** schema.sql を変更したら 1 増やす（既存 DB に自動で反映される） */
    private const SCHEMA_VERSION = 2;

    private static array $config = [];
    private static ?PDO $db = null;
    private static ?array $settings = null;

    public static function init(array $config): void
    {
        self::$config = $config;
        error_reporting(E_ALL);
        ini_set('display_errors', !empty($config['debug']) ? '1' : '0');
        ini_set('log_errors', '1');
        $logDir = DATA . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0770, true);
        }
        ini_set('error_log', $logDir . '/php-error.log');
        set_exception_handler([self::class, 'handleException']);
    }

    /** ドット区切りで設定値を取得（例: "ldap.host"） */
    public static function config(string $key, mixed $default = null): mixed
    {
        $value = self::$config;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function isPrivate(): bool
    {
        return self::config('site_mode') === 'private';
    }

    public static function ldapEnabled(): bool
    {
        return self::isPrivate() && (bool) self::config('ldap.enabled', false);
    }

    public static function db(): PDO
    {
        if (self::$db === null) {
            $path = (string) self::config('db_path', DATA . '/blog.sqlite');
            $dir = dirname($path);
            if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
                throw new RuntimeException('データベースのフォルダを作成できません: ' . $dir);
            }
            $pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');

            // スキーマの作成・更新（schema.sql はすべて IF NOT EXISTS なので、既存 DB にも安全に流せる）
            $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
            if ($version < self::SCHEMA_VERSION) {
                $pdo->exec((string) file_get_contents(INC . '/schema.sql'));
                $pdo->exec('ANALYZE');
                $pdo->exec('PRAGMA user_version = ' . self::SCHEMA_VERSION);
            }
            self::$db = $pdo;
        }
        return self::$db;
    }

    /** サイト設定（管理画面で変更可能な値）。未設定なら config.php の値 */
    public static function setting(string $key): string
    {
        if (self::$settings === null) {
            self::$settings = [];
            foreach (self::db()->query('SELECT name, value FROM settings') as $row) {
                self::$settings[$row['name']] = (string) $row['value'];
            }
        }
        if (array_key_exists($key, self::$settings)) {
            return self::$settings[$key];
        }
        return match ($key) {
            'site_name'          => (string) self::config('site_name', 'ブログ'),
            'site_description'   => (string) self::config('site_description', ''),
            'comment_moderation' => self::isPrivate() ? '0' : '1',
            'posts_per_page'     => (string) self::config('posts_per_page', 10),
            default              => '',
        };
    }

    public static function saveSetting(string $key, string $value): void
    {
        db_exec(
            'INSERT INTO settings (name, value) VALUES (?, ?) ON CONFLICT(name) DO UPDATE SET value = excluded.value',
            [$key, $value]
        );
        self::$settings = null;
    }

    public static function handleException(Throwable $e): void
    {
        error_log((string) $e);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        $detail = !empty(self::$config['debug'])
            ? '<pre style="white-space:pre-wrap">' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>'
            : '<p>しばらくしてから再度お試しください。</p>';
        echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><title>エラー</title></head>'
            . '<body><h1>サーバーエラーが発生しました</h1>' . $detail . '</body></html>';
    }
}
