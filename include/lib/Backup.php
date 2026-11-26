<?php
declare(strict_types=1);

/**
 * 投稿の保存・削除時に SQLite データベースのコピーを作成します。
 * VACUUM INTO を使うため、書き込み途中でも整合性のとれたコピーになります。
 */
final class Backup
{
    public static function run(): void
    {
        try {
            $dir = (string) App::config('backup_dir', DATA . '/backup');
            if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
                throw new RuntimeException('バックアップフォルダを作成できません: ' . $dir);
            }
            $dest = $dir . '/blog-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.sqlite';
            try {
                db()->exec('VACUUM INTO ' . db()->quote($dest));
            } catch (PDOException) {
                // 古い SQLite 向けのフォールバック
                copy((string) App::config('db_path'), $dest);
            }
            self::prune($dir);
        } catch (Throwable $e) {
            error_log('Backup failed: ' . $e->getMessage());
        }
    }

    public static function latest(): ?array
    {
        $files = self::files();
        if (!$files) {
            return null;
        }
        $file = end($files);
        return ['file' => basename($file), 'time' => date('Y-m-d H:i:s', (int) filemtime($file)), 'count' => count($files)];
    }

    /** @return list<string> 古い順 */
    private static function files(): array
    {
        $dir = (string) App::config('backup_dir', DATA . '/backup');
        $files = glob($dir . '/blog-*.sqlite') ?: [];
        sort($files, SORT_STRING);
        return $files;
    }

    private static function prune(string $dir): void
    {
        $keep = max(1, (int) App::config('backup_keep', 30));
        $files = self::files();
        foreach (array_slice($files, 0, max(0, count($files) - $keep)) as $old) {
            @unlink($old);
        }
    }
}
